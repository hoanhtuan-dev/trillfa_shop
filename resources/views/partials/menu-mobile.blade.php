@props(['items' => []])

@foreach($items as $item)
    @php $hasChildren = $item->children->isNotEmpty(); @endphp
    <div x-data="{ open: false }">
        @if($hasChildren)
            <button type="button" @click="open = !open" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium text-ink-900 hover:bg-cream-100">
                <span>{{ $item->label }}</span>
                <svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 text-ink-500 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
            </button>
            <div x-show="open" x-collapse class="pl-4">
                @include('partials.menu-mobile', ['items' => $item->children])
            </div>
        @else
            <a href="{{ $item->getUrl() }}" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-ink-900 hover:bg-cream-100">{{ $item->label }}</a>
        @endif
    </div>
@endforeach
