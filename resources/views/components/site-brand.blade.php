@props(['settings', 'variant' => 'desktop'])
@php
    $name = $settings->displayName();
    $lightLogo = $settings->themeLogoUrl('light', $variant === 'mobile');
    $darkLogo = $settings->themeLogoUrl('dark', $variant === 'mobile');
    $smallLogo = $settings->mediaUrl('small_logo_path');
    $showFullLogo = $lightLogo || $darkLogo;
@endphp

<span class="site-brand site-brand--{{ $variant }}">
    @if($showFullLogo)
        @if($lightLogo)<img class="site-brand-full site-brand-full--light {{ $variant === 'mobile' && $smallLogo ? 'site-brand-compact' : '' }}" src="{{ $lightLogo }}" alt="{{ $name }}">@endif
        @if($darkLogo)<img class="site-brand-full site-brand-full--dark {{ $variant === 'mobile' && $smallLogo ? 'site-brand-compact' : '' }}" src="{{ $darkLogo }}" alt="{{ $name }}">@endif
    @else
        @if($smallLogo)
            <img class="site-brand-icon" src="{{ $smallLogo }}" alt="" aria-hidden="true">
        @else
            <span class="brand-mark"><x-ui.icon name="activity" /></span>
        @endif
        <span class="site-brand-name">{{ $name }}</span>
    @endif
</span>
