@props([
    'name',
    'label',
    'currentUrl' => null,
    'required' => false,
    'removeName' => 'remove_logo',
    'help' => 'PNG veya WebP, en fazla 5 MB.',
])
@php($inputId = 'upload-'.str_replace(['[', ']'], '-', $name))

<div class="managed-image-upload" x-data="imageUploadPreview(@js($currentUrl))">
    <label class="form-label" for="{{ $inputId }}">{{ $label }}</label>
    <div class="managed-image-upload-body">
        <div class="managed-image-preview" :class="{ 'has-image': preview }">
            <img x-cloak x-show="preview" :src="preview" alt="{{ $label }} önizlemesi">
            <i x-show="!preview" class="bi bi-image" aria-hidden="true"></i>
        </div>
        <div class="managed-image-actions">
            <input
                class="visually-hidden"
                id="{{ $inputId }}"
                name="{{ $name }}"
                type="file"
                accept="image/png,image/webp"
                x-ref="file"
                @change="choose($event)"
                @required($required)
            >
            <label class="btn btn-light managed-image-select" for="{{ $inputId }}">
                <i class="bi bi-upload" aria-hidden="true"></i>
                <span x-text="preview ? 'Görseli değiştir' : 'Dosya seç'"></span>
            </label>
            @if($removeName)
                <button class="btn btn-outline-danger" type="button" x-show="preview" @click="clear()">
                    <i class="bi bi-trash3" aria-hidden="true"></i> Kaldır
                </button>
                <input type="hidden" name="{{ $removeName }}" :value="removed ? '1' : '0'">
            @endif
            <small>{{ $help }}</small>
        </div>
    </div>
</div>
