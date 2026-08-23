@php $seo = seo(); $siteName = setting('site_name', 'Trillfa Fa'); @endphp
<title>{{ $seo->title }}</title>
<meta name="description" content="{{ $seo->description }}">
@if($seo->keywords)<meta name="keywords" content="{{ $seo->keywords }}">@endif
<meta name="robots" content="{{ $seo->robots }}">
@if($seo->canonical)<link rel="canonical" href="{{ $seo->canonical }}">@endif
<link rel="alternate" hreflang="vi-VN" href="{{ url('/') }}">
<link rel="alternate" hreflang="x-default" href="{{ url('/') }}">

{{-- Open Graph --}}
<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $seo->title }}">
<meta property="og:description" content="{{ $seo->description }}">
<meta property="og:url" content="{{ $seo->canonical ?: url()->current() }}">
<meta property="og:image" content="{{ $seo->image ?: asset('images/logo.png') }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="vi_VN">
<meta property="og:locale:alternate" content="vi_VN">

{{-- Twitter Card --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo->title }}">
<meta name="twitter:description" content="{{ $seo->description }}">
<meta name="twitter:image" content="{{ $seo->image ?: asset('images/logo.png') }}">

@foreach($seo->jsonLd as $ld)
    <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endforeach
