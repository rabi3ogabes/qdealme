@props(['seo' => null])
@php
    $s = ($seo instanceof \App\Support\Seo ? $seo : \App\Support\Seo::make())->toArray();
    $ogLocale = $s['locale'] === 'ar' ? 'ar_QA' : 'en_QA';
@endphp
<title>{{ $s['title'] }}</title>
<meta name="description" content="{{ $s['description'] }}">
<link rel="canonical" href="{{ $s['canonical'] }}">
@if ($s['noindex'])
    <meta name="robots" content="noindex, follow">
@else
    <meta name="robots" content="index, follow, max-image-preview:large">
@endif
@foreach ($s['alternates'] as $locale => $url)
    <link rel="alternate" hreflang="{{ $locale }}" href="{{ $url }}">
@endforeach
@if (isset($s['alternates'][config('qdealme.default_locale')]))
    <link rel="alternate" hreflang="x-default" href="{{ $s['alternates'][config('qdealme.default_locale')] }}">
@endif
<meta property="og:site_name" content="{{ brand() }}">
<meta property="og:type" content="{{ $s['type'] }}">
<meta property="og:title" content="{{ $s['title'] }}">
<meta property="og:description" content="{{ $s['description'] }}">
<meta property="og:url" content="{{ $s['canonical'] }}">
<meta property="og:image" content="{{ $s['image'] }}">
<meta property="og:locale" content="{{ $ogLocale }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $s['title'] }}">
<meta name="twitter:description" content="{{ $s['description'] }}">
<meta name="twitter:image" content="{{ $s['image'] }}">
@foreach ($s['jsonLd'] as $schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
