@props(['settings', 'variant' => 'desktop'])
@php
    $name = $settings->displayName();
    $lightLogo = $settings->themeLogoUrl('light');
    $darkLogo = $settings->themeLogoUrl('dark');
    $smallLogo = $settings->mediaUrl('small_logo_path');
    $showFullLogo = $variant === 'desktop' && ($lightLogo || $darkLogo);
@endphp

<span class="site-brand site-brand--{{ $variant }}">
    @if($showFullLogo)
        @if($lightLogo)<img class="site-brand-full site-brand-full--light" src="{{ $lightLogo }}" alt="{{ $name }}">@endif
        @if($darkLogo)<img class="site-brand-full site-brand-full--dark" src="{{ $darkLogo }}" alt="{{ $name }}">@endif
    @else
        @if($smallLogo)
            <img class="site-brand-icon" src="{{ $smallLogo }}" alt="" aria-hidden="true">
        @else
            <span class="brand-mark"><i class="bi bi-activity" aria-hidden="true"></i></span>
        @endif
        <span class="site-brand-name">{{ $name }}</span>
    @endif
</span>
