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
            <x-ui.icon name="image" x-show="!preview" />
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
                <x-ui.icon name="upload" />
                <span x-text="preview ? 'Görseli değiştir' : 'Dosya seç'"></span>
            </label>
            @if($removeName)
                <button class="btn btn-outline-danger" type="button" x-show="preview" @click="clear()">
                    <x-ui.icon name="delete" /> Kaldır
                </button>
                <input type="hidden" name="{{ $removeName }}" :value="removed ? '1' : '0'">
            @endif
            <small>{{ $help }}</small>
        </div>
    </div>
</div>
