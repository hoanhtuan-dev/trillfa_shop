@props(['value' => 0, 'count' => 0])

<div class="flex items-center gap-1.5">
    <div class="flex items-center text-amber-400">
        @for($i = 1; $i <= 5; $i++)
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="{{ $i <= round((float)$value) ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.5">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.367 2.446a1 1 0 00-.364 1.118l1.287 3.957c.3.922-.755 1.688-1.539 1.118l-3.367-2.446a1 1 0 00-1.175 0l-3.367 2.446c-.783.57-1.838-.196-1.539-1.118l1.287-3.957a1 1 0 00-.364-1.118L2.06 9.385c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.289-3.958z"/>
            </svg>
        @endfor
    </div>
    @if($count)
        <span class="text-xs text-ink-500">({{ $count >= 1 ? number_format((float)$value, 1) : '—' }})</span>
    @endif
</div>
