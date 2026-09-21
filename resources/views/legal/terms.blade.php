@extends('layouts.auth')
@section('title', 'Kullanım Koşulları')
@section('content')
<article class="legal-page">
    <header class="legal-hero">
        <span class="eyebrow">Yasal bilgiler</span>
        <h1>Kullanım Koşulları</h1>
        <p>Tribünasyon'u kullanırken geçerli temel kurallar ve kullanıcı sorumlulukları.</p>
        <small>Son güncelleme: 22 Eylül 2026</small>
    </header>

    <nav class="legal-toc" aria-label="Kullanım Koşulları içindekiler">
        <strong>İçindekiler</strong>
        <ol>
            <li><a href="#hizmet">Hizmetin Tanımı</a></li>
            <li><a href="#hesap">Hesap Oluşturma</a></li>
            <li><a href="#icerikler">Kullanıcı İçerikleri</a></li>
            <li><a href="#yasaklar">Yasak Davranışlar</a></li>
            <li><a href="#moderasyon">Moderasyon</a></li>
            <li><a href="#futbol-verileri">Futbol Verileri</a></li>
        </ol>
    </nav>

    <div class="legal-content">
        <section id="hizmet">
            <h2>1. Hizmetin Tanımı</h2>
            <p>Tribünasyon; takım gönderileri, maç merkezi, canlı skor ve istatistikler, oyuncu ve takım sayfaları, yorumlar, sohbet ve takip sistemi gibi özellikler sunan bir futbol topluluk platformudur.</p>
        </section>

        <section id="hesap">
            <h2>2. Hesap Oluşturma</h2>
            <p>Kullanıcı, kayıt sırasında verdiği bilgilerin doğruluğundan ve hesabının güvenliğini korumaktan sorumludur. Kullanıcı adı kurallarına uyulmalı; başka bir kişi, kurum veya yetkili hesap taklit edilmemelidir.</p>
        </section>

        <section id="google">
            <h2>3. Google ile Giriş</h2>
            <p>Google hesabınızla Tribünasyon'a giriş yapabilir veya kayıt olabilirsiniz. Google ile giriş seçeneğinin sunulması, Google ile Tribünasyon arasında bir ortaklık, sponsorluk veya onay ilişkisi bulunduğu anlamına gelmez.</p>
        </section>

        <section id="icerikler">
            <h2>4. Kullanıcı İçerikleri</h2>
            <p>Kullanıcı; paylaştığı yorumlardan, sohbet mesajlarından ve profil bilgilerinden sorumludur. İçeriğiniz üzerindeki haklarınız sizde kalır.</p>
            <p>İçerik göndererek Tribünasyon'a yalnızca içeriği hizmet içinde saklamak, teknik olarak işlemek, göstermek ve platform özellikleri kapsamında sunmak için gerekli, sınırlı ve münhasır olmayan kullanım izni verirsiniz.</p>
        </section>

        <section id="yasaklar">
            <h2>5. Yasak Davranışlar</h2>
            <p>Aşağıdaki davranışlara izin verilmez:</p>
            <ul>
                <li>Spam, tehdit, taciz, nefret söylemi veya yasa dışı içerik paylaşmak</li>
                <li>Başka bir kişiyi ya da kurumu taklit etmek</li>
                <li>Başkalarının kişisel bilgilerini izinsiz paylaşmak</li>
                <li>Sistemi kötüye kullanmak veya zararlı otomasyon ve bot çalıştırmak</li>
                <li>Güvenlik açıklarını istismar etmek ya da hizmeti kesintiye uğratmaya çalışmak</li>
            </ul>
        </section>

        <section id="moderasyon">
            <h2>6. Moderasyon</h2>
            <p>Topluluğun güvenliğini ve hizmetin sağlıklı çalışmasını korumak için ihlalin niteliğine göre içerikler kaldırılabilir, yorumlar silinebilir, hesaplar askıya alınabilir veya kullanıcı erişimi kısıtlanabilir. Moderasyon kararlarında topluluk kuralları, güvenlik ve diğer kullanıcıların hakları dikkate alınır.</p>
        </section>

        <section id="kaynaklar">
            <h2>7. Haberler ve Kaynaklar</h2>
            <p>Tribünasyon gönderilerinde harici kaynak ve haber bağlantıları bulunabilir. Harici sitelerin içerik, güvenlik veya gizlilik uygulamalarından ilgili site sorumludur; Tribünasyon bu sitelerin kontrolünü üstlenmez.</p>
        </section>

        <section id="ucuncu-taraf-haklari">
            <h2>8. Takımlar, Logolar ve Üçüncü Taraf Hakları</h2>
            <p>Kulüp, lig, organizasyon ve diğer üçüncü taraflara ait isim, marka ve logolar ilgili hak sahiplerine ait olabilir. Bunların platformda bilgi ve tanımlama amacıyla yer alması, Tribünasyon'un söz konusu markaların sahibi olduğu anlamına gelmez.</p>
        </section>

        <section id="futbol-verileri">
            <h2>9. Futbol Verileri</h2>
            <p>Canlı skor, maç dakikası, kadro, fikstür, puan durumu, istatistik ve benzeri bilgiler üçüncü taraf futbol veri sağlayıcılarından gelebilir. Veriler gecikebilir, eksik olabilir veya hata içerebilir. Tribünasyon resmi maç sonucu otoritesi değildir.</p>
        </section>

        <section id="bahis">
            <h2>10. Bahis</h2>
            <p>Tribünasyon bir bahis platformu değildir ve bahis hizmeti sunmaz. Platformdaki içerik, skor veya istatistikler bahis yönlendirmesi ya da finansal tavsiye amacı taşımaz.</p>
        </section>

        <section id="sureklilik">
            <h2>11. Hizmet Sürekliliği</h2>
            <p>Bakım çalışmaları, teknik arızalar, güvenlik önlemleri veya üçüncü taraf servislerdeki sorunlar nedeniyle hizmet zaman zaman yavaşlayabilir ya da kesintiye uğrayabilir. Kesintisiz veya hatasız hizmet garantisi verilmez.</p>
        </section>

        <section id="hesap-silme">
            <h2>12. Hesap Silme</h2>
            <p>Member kullanıcılar kendi hesaplarını silebilir. Hesap silindiğinde kişisel hesap alanları anonimleştirilebilir, Google bağlantısı kaldırılabilir ve takip veya beğeni ilişkileri temizlenebilir. Topluluk konuşmalarının bütünlüğü için yorum ve sohbet içerikleri “Silinmiş kullanıcı” adıyla sistemde kalabilir.</p>
        </section>

        <section id="degisiklikler">
            <h2>13. Koşullardaki Değişiklikler</h2>
            <p>Bu Kullanım Koşulları, hizmetteki veya ilgili gerekliliklerdeki değişikliklere göre zaman zaman güncellenebilir. Güncel koşullar bu sayfada yayımlanır.</p>
        </section>

        <section id="iletisim">
            <h2>14. İletişim</h2>
            <p>Koşullarla ilgili sorularınız için <a href="mailto:tribunasyon@gmail.com">tribunasyon@gmail.com</a> adresinden bize ulaşabilirsiniz.</p>
        </section>

        <section id="yururluk">
            <h2>15. Yürürlük Tarihi</h2>
            <p>Bu Kullanım Koşulları 22 Eylül 2026 tarihinde yürürlüğe girmiştir.</p>
        </section>
    </div>
</article>
@endsection
