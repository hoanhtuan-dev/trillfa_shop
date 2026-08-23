@props(['items' => []])

@if(count($items))
<nav class="flex flex-wrap items-center gap-1.5 text-sm text-ink-500">
    @foreach($items as $item)
        @php
            $isLast = $loop->last;
            $label = is_string($item) ? $item : ($item['label'] ?? '');
            $url = is_string($item) ? null : ($item['url'] ?? null);
        @endphp
        @if(!$loop->first)
            <svg class="h-3.5 w-3.5 text-ink-500/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
        @endif
        @if($url)
            <a href="{{ $url }}" class="hover:text-brand-700">{{ $label }}</a>
        @else
            <span class="{{ $isLast ? 'font-medium text-ink-900' : 'text-ink-500' }}">{{ $label }}</span>
        @endif
    @endforeach
</nav>
@endif
