@php
    $props = $page['props'] ?? [];
    $seo = $props['seo'] ?? [];
    $analytics = $props['site']['analytics'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- SEO is rendered server-side so crawlers and social previews never depend on JavaScript. --}}
    <title inertia>{{ $seo['title'] ?? config('app.name') }}</title>
    @if (! empty($seo['description']))<meta name="description" content="{{ $seo['description'] }}" inertia="description">@endif
    @if (! empty($seo['keywords']))<meta name="keywords" content="{{ $seo['keywords'] }}">@endif
    <meta name="robots" content="{{ $seo['robots'] ?? 'index,follow' }}">
    @if (! empty($seo['canonical']))<link rel="canonical" href="{{ $seo['canonical'] }}">@endif
    @foreach ($seo['alternates'] ?? [] as $alt)
        <link rel="alternate" hreflang="{{ $alt['hreflang'] }}" href="{{ $alt['url'] }}">
    @endforeach
    @foreach ($seo['og'] ?? [] as $property => $value)
        <meta property="{{ $property }}" content="{{ $value }}">
    @endforeach
    @foreach ($seo['twitter'] ?? [] as $name => $value)
        <meta name="{{ $name }}" content="{{ $value }}">
    @endforeach
    @if (! empty($seo['json_ld']))
        <script type="application/ld+json">{!! json_encode($seo['json_ld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endif
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @if (! empty($analytics['gtm']))
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@json($analytics['gtm']));</script>
    @elseif (! empty($analytics['ga']))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $analytics['ga'] }}"></script>
        <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',@json($analytics['ga']));</script>
    @endif
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>
