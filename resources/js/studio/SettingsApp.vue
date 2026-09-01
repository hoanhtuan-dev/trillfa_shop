<script setup>
import { ref, onMounted } from 'vue';
const data = ref(null); const tab = ref('keys');
const form = ref({ key_provider:'', key_label:'', key_value:'', key_kind:'', key_priority:5, model_group:'image', model_name:'', model_provider:'', model_id:'', model_key_ref:'', model_priority:5 });
const saving = ref(false);
async function load() { try { const r = await fetch('/studio/settings/data', { headers: { Accept:'application/json' } }); data.value = await r.json(); } catch(e){} }
onMounted(load);
async function save() {
  saving.value = true;
  try { const r = await fetch('/studio/settings/save', { method:'POST', headers:{ 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')||{}).content||'', 'Content-Type':'application/json', Accept:'application/json' }, body: JSON.stringify({ key_value: form.value.key_value, key_provider: form.value.key_provider, key_label: form.value.key_label, key_kind: form.value.key_kind, key_priority: Number(form.value.key_priority)||5, model_name: form.value.model_name, model_group: form.value.model_group, model_provider: form.value.model_provider, model_id: form.value.model_id, model_key_ref: form.value.model_key_ref, model_priority: Number(form.value.model_priority)||5 }) });
    const d = await r.json(); if (d.ok) { form.value = { key_provider:'', key_label:'', key_value:'', key_kind:'', key_priority:5, model_group:'image', model_name:'', model_provider:'', model_id:'', model_key_ref:'', model_priority:5 }; await load(); }
  } catch(e){}
  finally { saving.value = false; }
}
</script>
<template>
  <div class="studio-dark mx-auto max-w-4xl p-5">
    <div class="mb-4 flex items-center justify-between">
      <h1 class="font-display text-lg font-semibold text-cream-50">⚙️ Studio Settings (Vue)</h1>
      <div class="flex gap-1.5">
        <button @click="tab='keys'" :class="tab==='keys' ? 'bg-brand-600 text-white' : 'bg-ink-700 text-cream-200'" class="rounded-xl px-3 py-1.5 text-xs font-semibold">🔑 API Keys</button>
        <button @click="tab='models'" :class="tab==='models' ? 'bg-brand-600 text-white' : 'bg-ink-700 text-cream-200'" class="rounded-xl px-3 py-1.5 text-xs font-semibold">🤖 Model</button>
        <button @click="tab='providers'" :class="tab==='providers' ? 'bg-brand-600 text-white' : 'bg-ink-700 text-cream-200'" class="rounded-xl px-3 py-1.5 text-xs font-semibold">Providers</button>
      </div>
    </div>
    <!-- Keys -->
    <div v-if="tab==='keys'" class="card p-5">
      <p class="mb-2 text-xs text-ink-500">⚙️ {{ data?.providers ? Object.keys(data.providers).length : 0 }} nhóm · {{ data?.api_keys?.length || 0 }} key. Chỉ cần provider + key.</p>
      <div v-for="k in data?.api_keys" :key="k.id" class="flex flex-wrap items-center gap-2 rounded-xl border border-cream-200 p-2 text-xs">
        <span class="font-semibold text-ink-900">{{ k.label }}</span>
        <span class="text-ink-500">{{ k.provider }} · {{ k.kind || '' }}</span>
        <span class="rounded-full bg-cream-200 px-2 py-0.5 text-[10px]">Ưu tiên {{ k.priority }}</span>
        <span :class="k.enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600'" class="rounded-full px-2 py-0.5 text-[10px]">{{ k.enabled ? 'Bật' : 'Tắt' }}</span>
      </div>
      <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
        <div><label class="label">Provider</label><select v-model="form.key_provider" class="input !py-2"><option v-for="(p,pid) in data?.providers" :key="pid" :value="pid">{{ pid }}</option></select></div>
        <div><label class="label">Nhãn</label><input v-model="form.key_label" class="input !py-2" placeholder="VD: Qwen Token-Plan"></div>
        <div><label class="label">Key</label><input v-model="form.key_value" class="input !py-2" placeholder="sk-..."></div>
        <div><label class="label">Loại</label><input v-model="form.key_kind" class="input !py-2" placeholder="plan/paygo"></div>
        <div><label class="label">Ưu tiên</label><input type="number" v-model.number="form.key_priority" min="0" max="100" class="input !py-2"></div>
      </div>
      <button @click="save" :disabled="saving" class="btn-brand btn-sm mt-3">{{ saving ? 'Đang lưu…' : '➕ Thêm API key' }}</button>
    </div>
    <!-- Models -->
    <div v-if="tab==='models'" class="card p-5">
      <p class="mb-2 text-xs text-ink-500">Chọn API key → tự nhận provider → gán vai trò + ưu tiên.</p>
      <div v-for="m in data?.models" :key="m.id" class="flex flex-wrap items-center gap-2 rounded-xl border border-cream-200 p-2 text-xs">
        <span class="font-semibold text-ink-900">{{ m.name }}</span>
        <span class="text-ink-500">{{ m.provider }} · {{ m.model_id }} <span v-if="m.api_key_ref" class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] text-indigo-700">🔑 {{ m.api_key_ref }}</span></span>
        <span class="rounded-full bg-cream-200 px-2 py-0.5 text-[10px]">Ưu tiên {{ m.priority }}</span>
      </div>
      <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
        <div><label class="label">Vai trò</label><select v-model="form.model_group" class="input !py-2"><option value="image">Ảnh</option><option value="video">Video</option><option value="inference">Suy luận</option><option value="text">Ngôn ngữ</option></select></div>
        <div><label class="label">Tên</label><input v-model="form.model_name" class="input !py-2" placeholder="VD: Wan 2.2 i2v"></div>
        <div><label class="label">Provider</label><input v-model="form.model_provider" class="input !py-2" placeholder="wan/qwen/..."></div>
        <div><label class="label">Model ID</label><input v-model="form.model_id" class="input !py-2" placeholder="wan2.2-i2v"></div>
        <div><label class="label">Chọn key</label><select v-model="form.model_key_ref" class="input !py-2"><option value="">—</option><option v-for="k in data?.api_keys" :key="k.id" :value="k.provider">{{ k.provider }}</option></select></div>
        <div><label class="label">Ưu tiên</label><input type="number" v-model.number="form.model_priority" min="0" max="100" class="input !py-2"></div>
      </div>
      <button @click="save" :disabled="saving" class="btn-brand btn-sm mt-3">{{ saving ? 'Đang lưu…' : '➕ Thêm model' }}</button>
    </div>
    <!-- Providers -->
    <div v-if="tab==='providers'" class="card p-5">
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div v-for="(p,pid) in data?.providers" :key="pid" class="rounded-xl border border-cream-200 p-3 text-xs">
          <p class="font-semibold text-ink-900">{{ p.label }}</p>
          <p class="mt-1" :class="p.configured ? 'text-emerald-600' : 'text-amber-600'">{{ p.configured ? '✓ Đã cấu hình' : '⚠ Chưa có key' }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
