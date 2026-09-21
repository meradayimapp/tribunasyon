<?php

namespace App\Http\Controllers;

use App\Data\SeoData;
use App\Services\SeoService;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function privacy(SeoService $seo): View
    {
        return view('legal.privacy', [
            'seo' => new SeoData(
                title: 'Gizlilik Politikası | Tribünasyon',
                description: 'Tribünasyon Gizlilik Politikası; hesap, Google ile giriş, profil, etkileşim ve kişisel verilerin nasıl işlendiğini açıklar.',
                canonical: $seo->canonical(route('legal.privacy', absolute: false)),
            ),
        ]);
    }

    public function terms(SeoService $seo): View
    {
        return view('legal.terms', [
            'seo' => new SeoData(
                title: 'Kullanım Koşulları | Tribünasyon',
                description: 'Tribünasyon kullanım koşulları; hesap, topluluk kuralları, içerik, moderasyon ve platform kullanımına ilişkin temel şartları açıklar.',
                canonical: $seo->canonical(route('legal.terms', absolute: false)),
            ),
        ]);
    }
}
