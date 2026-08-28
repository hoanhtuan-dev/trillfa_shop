@extends('layouts.studio')

@section('title', 'Pattern Maker · Trillfa Studio')

@php
    $items = $latest->map(fn ($g) => ['id' => $g->id, 'status' => $g->status, 'media_url' => $g->media_url, 'error' => $g->error, 'created' => $g->created_at?->format('d/m H:i')])->values();
@endphp

@section('content')
<div x-data="{ prompt:'', busy:false, items: {{ Js::from($items) }}, _t:{},
    async api(url, body){ const res = await fetch(url, { method:'POST', headers:{ 'X-CSRF-TOKEN':(document.querySelector('meta[name=csrf-token]')||{}).content||'', 'Content-Type':'application/json', Accept:'application/json' }, body: JSON.stringify(body) }); const d = await res.json().catch(()=>({})); if(!res.ok) throw new Error(d.message||'Lỗi.'); return d; },
    addGen(g){ const e = this.items.find(x=>x.id===g.id); if(e) Object.assign(e,g); else this.items.unshift(g); if(g.status==='pending'||g.status==='processing') this.poll(g.id); },
    async gen(){
        if(!this.prompt.trim()||this.busy) return; this.busy = true;
        try { const d = await this.api('/studio/pattern', { prompt: this.prompt }); this.addGen({ id:d.generation_id, status:d.status, media_url:d.media_url, error:d.error, created:'Vừa gửi' }); this.prompt=''; }
        catch(e){ Alpine.store('toast').show(e.message,'error'); }
        finally { this.busy = false; }
    },
    poll(id){ if(this._t[id]) return; this._t[id]=setInterval(async()=>{ try{ const res=await fetch('/studio/generations/'+id,{headers:{Accept:'application/json'}}); const g=await res.json(); const it=this.items.find(x=>x.id===Number(g.id)); if(it){it.status=g.status;it.media_url=g.media_url;it.error=g.error;} if(['completed','failed','cancelled'].includes(g.status)){clearInterval(this._t[id]); delete this._t[id];} }catch(e){} },3000); }
}">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold text-ink-900">Pattern Maker</h1>
            <p class="mt-1 text-sm text-ink-500">Tạo họa tiết vải / rập liền mạch từ mô tả.</p>
        </div>
        <a href="{{ route('studio.index') }}" class="btn-outline btn-sm">← Studio</a>
    </div>

    <div class="card mb-6 p-5">
        <label class="label">Mô tả họa tiết</label>
        <textarea x-model="prompt" rows="2" class="input" placeholder="VD: hoa cúc vintage màu ngà trên nền be, phong cách toile" @keydown.enter.prevent="gen()"></textarea>
        <button @click="gen()" :disabled="busy || !prompt" class="btn-brand mt-3 w-full whitespace-nowrap"><span x-show="!busy">Tạo họa tiết</span><span x-show="busy">Đang tạo…</span></button>
    </div>

    <h2 class="mb-3 font-display text-lg font-semibold text-ink-900">Kết quả</h2>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
        <template x-for="g in items" :key="g.id">
            <div class="card overflow-hidden">
                <template x-if="g.status==='completed' && g.media_url"><img :src="g.media_url" class="aspect-[3/4] w-full object-cover" onerror="this.src='/images/placeholder.svg'"></template>
                <template x-if="g.status!=='completed'"><div class="grid aspect-[3/4] w-full place-items-center bg-cream-100"><span x-show="g.status==='pending'||g.status==='processing'" class="inline-block h-6 w-6 animate-spin rounded-full border-2 border-brand-600 border-t-transparent"></span><p class="px-3 text-xs text-ink-500" x-text="g.status==='failed' ? ('Lỗi: '+(g.error||'')) : 'Đang tạo…'"></p></div></template>
                <div class="border-t border-cream-200 px-3 py-2 text-xs text-ink-500"><span x-text="g.created||''"></span></div>
            </div>
        </template>
    </div>
    <div class="mt-4 text-center text-sm text-ink-500" x-show="!items.length">Nhập mô tả rồi bấm “Tạo họa tiết”.</div>
</div>
@endsection
