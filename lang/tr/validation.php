<?php

return [
    'required' => ':attribute alanı zorunludur.',
    'email' => ':attribute geçerli bir e-posta adresi olmalıdır.',
    'unique' => 'Bu :attribute zaten kullanılıyor.',
    'confirmed' => ':attribute tekrarı eşleşmiyor.',
    'min' => ['string' => ':attribute en az :min karakter olmalıdır.'],
    'max' => ['string' => ':attribute en fazla :max karakter olmalıdır.', 'file' => ':attribute en fazla :max KB olabilir.'],
    'image' => ':attribute geçerli bir görsel olmalıdır.',
    'mimes' => ':attribute şu türlerden biri olmalıdır: :values.',
    'exists' => 'Seçilen :attribute geçersiz.',
    'alpha_dash' => ':attribute yalnızca harf, rakam, tire ve alt çizgi içerebilir.',
    'current_password' => 'Mevcut şifre hatalı.',
    'attributes' => [
        'name' => 'ad', 'username' => 'kullanıcı adı', 'email' => 'e-posta', 'password' => 'şifre',
        'favorite_team_id' => 'favori takım', 'body' => 'metin', 'image' => 'görsel', 'avatar' => 'profil fotoğrafı',
    ],
];
