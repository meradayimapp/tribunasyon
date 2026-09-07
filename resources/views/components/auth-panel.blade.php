@props([
    'imageUrl' => null,
    'variant' => 'login',
])

<section class="auth-experience auth-experience--{{ $variant }}">
    <div class="auth-visual">
        @if($imageUrl)
            <img src="{{ $imageUrl }}" alt="" aria-hidden="true">
        @else
            <div class="auth-visual-fallback" aria-hidden="true">
                <span class="auth-visual-kicker">Futbolun yeni sosyal ağı</span>
                <strong>Tribün seninle daha güçlü.</strong>
                <span class="auth-visual-line"></span>
            </div>
        @endif
    </div>
    <div class="auth-form-panel">
        <div class="auth-form-inner">
            {{ $slot }}
        </div>
    </div>
</section>
