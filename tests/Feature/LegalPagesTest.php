<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://tribunasyon.com']);
    }

    public function test_privacy_policy_is_public_indexable_and_uses_central_seo(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('<h1>Gizlilik Politikası</h1>', false)
            ->assertSee('Son güncelleme: 22 Eylül 2026')
            ->assertSee('<title>Gizlilik Politikası | Tribünasyon</title>', false)
            ->assertSee('content="Tribünasyon Gizlilik Politikası; hesap, Google ile giriş, profil, etkileşim ve kişisel verilerin nasıl işlendiğini açıklar."', false)
            ->assertSee('<link rel="canonical" href="https://tribunasyon.com/gizlilik-politikasi">', false)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('Google access token kalıcı olarak saklanmaz')
            ->assertSee('Silinmiş kullanıcı');

        $this->assertGuest();
    }

    public function test_terms_are_public_indexable_and_render_required_content(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('<h1>Kullanım Koşulları</h1>', false)
            ->assertSee('Son güncelleme: 22 Eylül 2026')
            ->assertSee('<title>Kullanım Koşulları | Tribünasyon</title>', false)
            ->assertSee('content="Tribünasyon kullanım koşulları; hesap, topluluk kuralları, içerik, moderasyon ve platform kullanımına ilişkin temel şartları açıklar."', false)
            ->assertSee('<link rel="canonical" href="https://tribunasyon.com/kullanim-kosullari">', false)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('Tribünasyon bir bahis platformu değildir')
            ->assertSee('Tribünasyon resmi maç sonucu otoritesi değildir');

        $this->assertGuest();
    }

    public function test_register_and_public_footer_link_to_both_legal_pages(): void
    {
        $termsUrl = route('legal.terms');
        $privacyUrl = route('legal.privacy');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('href="'.$termsUrl.'"', false)
            ->assertSee('href="'.$privacyUrl.'"', false)
            ->assertSee("Kullanım Koşulları'nı", false)
            ->assertSee("Gizlilik Politikası'nı", false);

        $this->get(route('legal.privacy'))
            ->assertSee('href="'.$termsUrl.'"', false)
            ->assertSee('href="'.$privacyUrl.'"', false);
    }
}
