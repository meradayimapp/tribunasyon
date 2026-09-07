@extends('layouts.app')
@section('title', 'Site Ayarları')
@section('mobile-title', 'Site Ayarları')
@section('content')
<div class="panel-shell">
    <div class="eyebrow">Yönetim</div>
    <h1 class="page-title">Site Ayarları</h1>
    <x-panel-nav />

    <form class="surface" method="POST" enctype="multipart/form-data" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-12">
                <div class="settings-section-heading"><span>Genel</span><h2>Marka ayarları</h2></div>
            </div>
            <div class="col-12">
                <label class="form-label" for="site-name">Site adı</label>
                <input id="site-name" class="form-control" name="site_name" value="{{ old('site_name', $settings->displayName()) }}" maxlength="100" required>
            </div>

            <div class="col-md-6">
                <x-admin.image-upload
                    name="site_logo"
                    label="Site logosu"
                    :current-url="$settings->mediaUrl('logo_path')"
                    remove-name="remove_site_logo"
                    help="Yatay marka kullanımı için PNG veya WebP, en fazla 5 MB."
                />
            </div>
            <div class="col-md-6">
                <x-admin.image-upload
                    name="small_logo"
                    label="Küçük logo / ikon"
                    :current-url="$settings->mediaUrl('small_logo_path')"
                    remove-name="remove_small_logo"
                    help="Mobil ve kompakt alanlar için PNG veya WebP, en fazla 5 MB."
                />
            </div>
            <div class="col-md-6">
                <x-admin.image-upload
                    name="favicon"
                    label="Favicon"
                    :current-url="$settings->mediaUrl('favicon_path')"
                    remove-name="remove_favicon"
                    help="Kare PNG veya WebP önerilir, en fazla 5 MB."
                />
            </div>
            <div class="col-md-6">
                <x-admin.image-upload
                    name="light_logo"
                    label="Açık tema logosu (opsiyonel)"
                    :current-url="$settings->mediaUrl('light_logo_path')"
                    remove-name="remove_light_logo"
                />
            </div>
            <div class="col-md-6">
                <x-admin.image-upload
                    name="dark_logo"
                    label="Koyu tema logosu (opsiyonel)"
                    :current-url="$settings->mediaUrl('dark_logo_path')"
                    remove-name="remove_dark_logo"
                />
            </div>

            <div class="col-12"><div class="settings-divider"></div></div>
            <div class="col-12">
                <div class="settings-section-heading"><span>Giriş / Kayıt Sayfası</span><h2>Auth görselleri</h2><p>Giriş ve kayıt ekranlarının masaüstü görsel panellerini ayrı ayrı yönetin.</p></div>
            </div>
            <div class="col-md-6">
                <x-admin.image-upload
                    name="login_image"
                    label="Giriş sayfası görseli"
                    :current-url="$settings->mediaUrl('login_image_path')"
                    remove-name="remove_login_image"
                    accept="image/jpeg,image/png,image/webp"
                    help="JPG, PNG veya WebP, en fazla 5 MB. Masaüstünde kırpılarak gösterilir."
                />
            </div>
            <div class="col-md-6">
                <x-admin.image-upload
                    name="register_image"
                    label="Kayıt sayfası görseli"
                    :current-url="$settings->mediaUrl('register_image_path')"
                    remove-name="remove_register_image"
                    accept="image/jpeg,image/png,image/webp"
                    help="JPG, PNG veya WebP, en fazla 5 MB. Masaüstünde kırpılarak gösterilir."
                />
            </div>
        </div>

        @if($errors->any())<div class="alert alert-danger mt-4">{{ $errors->first() }}</div>@endif
        <button class="btn btn-primary mt-4" type="submit">Ayarları kaydet</button>
    </form>
</div>
@endsection
