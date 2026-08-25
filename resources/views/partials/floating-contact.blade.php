@php $phone = preg_replace('/[^0-9]/', '', setting('site_phone', '19006363')); @endphp
<div class="fixed bottom-24 right-4 z-[60] flex flex-col gap-2.5 md:hidden">
    <a href="https://zalo.me/{{ $phone }}" target="_blank" rel="noopener" class="grid h-12 w-12 place-items-center rounded-full bg-[#0068ff] text-white shadow-lg shadow-ink-900/20 transition active:scale-95" aria-label="Chat Zalo">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.5 2 2 6 2 11c0 2.2.9 4.3 2.5 5.8L3 21l4.5-1.3c1.3.4 2.8.7 4.5.7 5.5 0 10-4 10-9s-4.5-9.4-10-9.4z"/></svg>
    </a>
    <a href="tel:{{ $phone }}" class="grid h-12 w-12 place-items-center rounded-full bg-brand-600 text-white shadow-lg shadow-ink-900/20 transition active:scale-95" aria-label="Gọi ngay">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
    </a>
</div>
