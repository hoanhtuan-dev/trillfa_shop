@if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4500)" x-transition.opacity.duration.500ms class="fixed bottom-5 left-1/2 z-[70] w-full max-w-sm -translate-x-1/2 px-4">
        <div class="flex items-center gap-3 rounded-2xl border border-brand-200 bg-white px-4 py-3 shadow-xl">
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand-100 text-brand-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            </span>
            <p class="flex-1 text-sm font-medium text-ink-900">{{ session('success') }}</p>
        </div>
    </div>
@endif

@if(session('error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4500)" x-transition.opacity.duration.500ms class="fixed bottom-5 left-1/2 z-[70] w-full max-w-sm -translate-x-1/2 px-4">
        <div class="flex items-center gap-3 rounded-2xl border border-red-200 bg-white px-4 py-3 shadow-xl">
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-red-100 text-red-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
            </span>
            <p class="flex-1 text-sm font-medium text-ink-900">{{ session('error') }}</p>
        </div>
    </div>
@endif
