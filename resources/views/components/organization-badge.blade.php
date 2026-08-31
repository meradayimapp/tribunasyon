@props(['team', 'size' => 'feed'])
@php($organization = $team->organization)
@if($organization?->status === \App\Enums\OrganizationStatus::Active && $organization->logoUrl())
    <img
        class="organization-badge organization-badge--{{ $size }}"
        src="{{ $organization->logoUrl() }}"
        alt="{{ $organization->name }} rozeti"
        title="{{ $organization->name }}"
        loading="lazy"
        decoding="async"
    >
@endif
