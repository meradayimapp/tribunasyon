@props(['data', 'siteSettings'])
@php
    $seoService = app(\App\Services\SeoService::class);
    $image = $data->image
        ?? $seoService->absolute($siteSettings->themeLogoUrl('dark', true))
        ?? $seoService->absolute(asset('favicon.ico'));
    $twitterCard = $image ? 'summary_large_image' : 'summary';
    $websiteData = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $siteSettings->displayName(),
        'url' => $seoService->canonical('/'),
    ];
    $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
@endphp
<title>{{ $data->title }}</title>
<meta name="description" content="{{ $data->description }}">
<meta name="robots" content="{{ $data->robots }}">
<link rel="canonical" href="{{ $data->canonical }}">
<meta property="og:title" content="{{ $data->title }}">
<meta property="og:description" content="{{ $data->description }}">
<meta property="og:url" content="{{ $data->canonical }}">
<meta property="og:type" content="{{ $data->type }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:site_name" content="{{ $siteSettings->displayName() }}">
<meta property="og:locale" content="tr_TR">
<meta name="twitter:card" content="{{ $twitterCard }}">
<meta name="twitter:title" content="{{ $data->title }}">
<meta name="twitter:description" content="{{ $data->description }}">
<meta name="twitter:image" content="{{ $image }}">
<script type="application/ld+json">{!! json_encode($websiteData, $jsonFlags) !!}</script>
@if($data->structuredData !== [])
    <script type="application/ld+json">{!! json_encode($data->structuredData, $jsonFlags) !!}</script>
@endif
