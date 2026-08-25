@props(['items' => [], 'isRoot' => false, 'level' => 0])

@foreach($items as $item)
    @php $hasChildren = $item->children->isNotEmpty(); @endphp
    <div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" class="relative">
        @if($hasChildren)
            <button type="button" @click.prevent="open = !open" class="flex items-center gap-1 {{ $level === 0 ? 'rounded-full px-4 py-2 text-sm font-medium text-ink-700 hover:bg-cream-200/70 hover:text-ink-900' : 'flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-cream-100' }}">
                <span>{{ $item->label }}</span>
                <svg class="h-3.5 w-3.5 {{ $level === 0 ? '' : 'ml-2' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $level === 0 ? 'M19.5 8.25l-7.5 7.5-7.5-7.5' : 'M8.25 4.5l7.5 7.5-7.5 7.5' }}"/>
                </svg>
            </button>
        @else
            <a href="{{ $item->url }}" class="{{ $level === 0 ? 'block rounded-full px-4 py-2 text-sm font-medium text-ink-700 hover:bg-cream-200/70 hover:text-ink-900' : 'block rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-cream-100' }}">{{ $item->label }}</a>
        @endif

        @if($hasChildren)
            <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="absolute z-50 {{ $level === 0 ? 'left-0 top-full' : 'left-full top-0' }} pt-1">
                <div class="min-w-[220px] rounded-2xl border border-cream-200 bg-white p-2 shadow-xl">
                    @include('partials.menu-flyout', ['items' => $item->children, 'isRoot' => false, 'level' => $level + 1])
                </div>
            </div>
        @endif
    </div>
@endforeach
