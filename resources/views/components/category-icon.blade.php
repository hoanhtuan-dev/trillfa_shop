@props(['name' => 'tag', 'image' => null, 'size' => 'h-7 w-7'])
@if($image)
    <img src="{{ $image }}" alt="" class="{{ $size }} rounded-xl object-cover" loading="lazy">
@else
    <svg class="{{ $size }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">{!! category_icon_svg($name) !!}</svg>
@endif
