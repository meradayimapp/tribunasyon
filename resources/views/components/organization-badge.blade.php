@props(['team', 'size' => 'feed'])
@php($badge = $team->organization_badge ? app(\App\Services\OrganizationBadgeCatalog::class)->find($team->organization_badge) : null)
@if($badge)
    <img
        class="organization-badge organization-badge--{{ $size }}"
        src="{{ $badge['url'] }}"
        alt="{{ $badge['label'] }} rozeti"
        title="{{ $badge['label'] }}"
        loading="lazy"
        decoding="async"
    >
@endif
