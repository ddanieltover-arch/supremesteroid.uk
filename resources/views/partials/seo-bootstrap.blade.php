{{--
  Server-rendered SEO bootstrap for crawlers when Inertia SSR is off.
  Mirrors page props.seo / structured_data into the initial HTML document.
--}}
@php
    $pageData = is_array($page ?? null) ? $page : [];
    $props = is_array($pageData['props'] ?? null) ? $pageData['props'] : [];
    $seo = is_array($props['seo'] ?? null) ? $props['seo'] : [];
    $schemas = [];
    if (is_array($props['structured_data'] ?? null)) {
        $schemas = $props['structured_data'];
    } elseif (is_array($props['product']['structured_data'] ?? null)) {
        $schemas = $props['product']['structured_data'];
    }
    $title = is_string($seo['title'] ?? null) && $seo['title'] !== ''
        ? $seo['title']
        : (string) config('store.name', 'Supreme Steroids');
    $description = is_string($seo['description'] ?? null) && $seo['description'] !== ''
        ? $seo['description']
        : (string) (config('store.tagline') ?: 'Premium lawful sports nutrition, wellness, and lab-tested formulations.');
    $canonical = is_string($seo['canonical'] ?? null) ? $seo['canonical'] : null;
    $robots = is_string($seo['robots'] ?? null) && $seo['robots'] !== '' ? $seo['robots'] : 'index,follow';
    $ogTitle = is_string($seo['og_title'] ?? null) && $seo['og_title'] !== '' ? $seo['og_title'] : $title;
    $ogDescription = is_string($seo['og_description'] ?? null) && $seo['og_description'] !== '' ? $seo['og_description'] : $description;
    $ogImage = is_string($seo['og_image'] ?? null) && $seo['og_image'] !== '' ? $seo['og_image'] : null;
@endphp
<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<meta name="robots" content="{{ $robots }}">
@if ($canonical)
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:url" content="{{ $canonical }}">
@endif
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $ogDescription }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('store.name', 'Supreme Steroids') }}">
<meta property="og:locale" content="en_GB">
@if ($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
@endif
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $ogTitle }}">
<meta name="twitter:description" content="{{ $ogDescription }}">
@foreach ($schemas as $schema)
    @if (is_array($schema))
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
@endforeach
