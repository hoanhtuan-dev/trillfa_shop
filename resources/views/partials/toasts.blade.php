<div x-data class="pointer-events-none fixed bottom-5 left-1/2 z-[70] flex w-full max-w-sm -translate-x-1/2 flex-col items-center gap-2 px-4">
    <template x-for="t in $store.toast.items" :key="t.id">
        <div x-transition.duration.200ms class="pointer-events-auto flex w-full items-center gap-3 rounded-2xl border bg-white px-4 py-3 shadow-xl" :class="t.type === 'error' ? 'border-red-200' : 'border-brand-200'">
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full" :class="t.type === 'error' ? 'bg-red-100 text-red-600' : 'bg-brand-100 text-brand-700'">
                <template x-if="t.type === 'error'">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                </template>
                <template x-if="t.type !== 'error'">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                </template>
            </span>
            <p class="flex-1 text-sm font-medium text-ink-900" x-text="t.message"></p>
            <button @click="$store.toast.remove(t.id)" class="text-ink-500 hover:text-ink-900">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </template>
</div>
