const { spawn } = require('child_process');
const WebSocket = require('ws');
const fs = require('fs');

const CHROME = '/usr/bin/google-chrome';
const PORT = 9322;
const BASE = 'http://127.0.0.1:8765';

const chrome = spawn(CHROME, [
  '--headless=new', '--disable-gpu', '--no-sandbox',
  `--remote-debugging-port=${PORT}`, '--user-data-dir=/tmp/dsh_chrome_profile',
  'about:blank'
], { stdio: 'ignore' });

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

async function getTargets() {
  const res = await fetch(`http://127.0.0.1:${PORT}/json`);
  return res.json();
}

async function connect() {
  for (let i = 0; i < 30; i++) {
    try {
      const targets = await getTargets();
      const page = targets.find(t => t.type === 'page');
      if (page) return new CDP(page.webSocketDebuggerUrl);
    } catch (e) {}
    await sleep(300);
  }
  throw new Error('chrome not ready');
}

class CDP {
  constructor(url) {
    this.ws = new WebSocket(url);
    this.id = 0;
    this.pending = new Map();
    this.events = [];
    this.ws.on('message', (data) => {
      const msg = JSON.parse(data);
      if (msg.id && this.pending.has(msg.id)) {
        const { resolve, reject } = this.pending.get(msg.id);
        this.pending.delete(msg.id);
        if (msg.error) reject(new Error(JSON.stringify(msg.error)));
        else resolve(msg.result);
      } else if (msg.method) {
        this.events.push(msg);
      }
    });
  }
  async open() {
    while (this.ws.readyState !== WebSocket.OPEN) await sleep(30);
  }
  send(method, params = {}) {
    const id = ++this.id;
    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      this.ws.send(JSON.stringify({ id, method, params }));
    });
  }
}

(async () => {
  const cdp = await connect();
  await cdp.open();
  const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
  const pageWs = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
  const session = new CDP(`ws://127.0.0.1:${PORT}/devtools/page/${targetId}`);
  await session.open();
  const s = (m, p={}) => session.send(m, p);

  // enable network capture
  await s('Network.enable');
  await s('Page.enable');

  // --- 1. login ---
  await s('Page.navigate', { url: `${BASE}/dang-nhap` });
  await sleep(2500);
  const loginHtml = await s('Runtime.evaluate', { expression: `document.querySelector('meta[name="csrf-token"]')?.content || 'NONE'`, returnByValue: true });
  const token = loginHtml.result.value;
  console.log('CSRF token:', token);

  // submit login form via JS
  const loginRes = await s('Runtime.evaluate', {
    expression: `(async () => {
      const f = new FormData();
      f.append('_token', '${token}');
      f.append('email', 'admin@trillfa.com');
      f.append('password', 'password');
      const r = await fetch('${BASE}/dang-nhap', { method: 'POST', body: f, redirect: 'follow', credentials: 'include' });
      return r.status + ' ' + r.url;
    })()`,
    awaitPromise: true, returnByValue: true
  });
  console.log('login fetch:', loginRes.result.value);

  // --- 2. open categories page ---
  await s('Page.navigate', { url: `${BASE}/admin/categories` });
  await sleep(3000);

  // capture requests
  const netRequests = [];
  session.ws.on('message', (data) => {
    const msg = JSON.parse(data);
    if (msg.method === 'Network.requestWillBeSent') {
      netRequests.push({ url: msg.params.request.url, method: msg.params.request.method, postData: (msg.params.request.postData||'').slice(0,300) });
    }
  });

  // --- 3. inspect form initial state (after Alpine init) ---
  const inspect = await s('Runtime.evaluate', {
    expression: `(() => {
      const form = document.querySelector('form[enctype]');
      const m = document.querySelector('input[name="_method"]');
      const a = document.querySelector('input[name="is_active"]');
      return JSON.stringify({
        formActionAttr: form.getAttribute('action'),
        formMethodAttr: form.getAttribute('method'),
        methodField: m ? (m.value + '|type=' + m.type) : 'MISSING',
        xDataPresent: !!document.querySelector('[x-data*="categoryForm"]'),
        alpine: typeof window.Alpine
      });
    })()`,
    returnByValue: true
  });
  console.log('INITIAL FORM:', inspect.result.value);

  // --- 4. click the first edit button ---
  await s('Runtime.evaluate', { expression: `document.querySelectorAll('button[title="Sửa"]')[0].click()` });
  await sleep(800);

  const inspect2 = await s('Runtime.evaluate', {
    expression: `(() => {
      const form = document.querySelector('form[enctype]');
      const m = document.querySelector('input[name="_method"]');
      return JSON.stringify({
        formActionAttr: form.getAttribute('action'),
        formMethodAttr: form.getAttribute('method'),
        methodField: m.value
      });
    })()`,
    returnByValue: true
  });
  console.log('AFTER EDIT CLICK:', inspect2.result.value);

  // --- 5. fill file input and submit ---
  await s('Runtime.evaluate', {
    expression: `(() => {
      const input = document.querySelector('input[type="file"][name="image"]');
      // create a tiny image file
      const bytes = new Uint8Array([0xff,0xd8,0xff,0xe0,0,16,74,70,73,70,0,1,1,0,0,1,0,1,0,0,0xff,0xd9]);
      const file = new File([bytes], 'test.jpg', { type: 'image/jpeg' });
      const dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return input.files.length;
    })()`, returnByValue: true
  });
  await sleep(300);

  // submit form manually (capture network)
  const submitRes = await s('Runtime.evaluate', {
    expression: `(async () => {
      const form = document.querySelector('form[enctype]');
      // read the actual action & method the browser would use
      const action = form.getAttribute('action');
      const method = form.getAttribute('method');
      const data = new FormData(form);
      let entries = {};
      for (const [k,v] of data.entries()) entries[k] = (v instanceof File) ? '[FILE '+v.name+']' : v;
      const body = new URLSearchParams();
      for (const [k,v] of data.entries()) body.append(k, v);
      const r = await fetch(action || location.href, { method: method || 'GET', body: body, redirect: 'manual', credentials: 'include' });
      return JSON.stringify({ action, method, entries, status: r.status, type: r.type, loc: r.headers.get('location') });
    })()`,
    awaitPromise: true, returnByValue: true
  });
  console.log('SUBMIT RESULT:', submitRes.result.value);

  await sleep(1000);
  console.log('NET REQUESTS CAPTURED:');
  for (const r of netRequests.slice(-8)) console.log(' ', r.method, r.url, r.postData ? '|'+r.postData.replace(/\n/g,' ').slice(0,200) : '');

  chrome.kill();
  process.exit(0);
})().catch(e => { console.error('ERR', e); try{chrome.kill()}catch(_){}; process.exit(1); });
