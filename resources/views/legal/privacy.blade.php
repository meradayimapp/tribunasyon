@extends('layouts.auth')
@section('title', 'Gizlilik Politikası')
@section('content')
<article class="legal-page">
    <header class="legal-hero">
        <span class="eyebrow">Yasal bilgiler</span>
        <h1>Gizlilik Politikası</h1>
        <p>Tribünasyon'u kullanırken hangi bilgilerin işlendiğini ve bu bilgilerin nasıl kullanıldığını açıklar.</p>
        <small>Son güncelleme: 22 Eylül 2026</small>
    </header>

    <nav class="legal-toc" aria-label="Gizlilik Politikası içindekiler">
        <strong>İçindekiler</strong>
        <ol>
            <li><a href="#hakkinda">Tribünasyon Hakkında</a></li>
            <li><a href="#toplanan-bilgiler">Toplanan Bilgiler</a></li>
            <li><a href="#google">Google ile Giriş</a></li>
            <li><a href="#guvenlik">Şifreler ve Güvenlik</a></li>
            <li><a href="#hesap-silme">Hesap Silme</a></li>
            <li><a href="#haklar">Kullanıcı Hakları</a></li>
        </ol>
    </nav>

    <div class="legal-content">
        <section id="hakkinda">
            <h2>1. Tribünasyon Hakkında</h2>
            <p>Tribünasyon; futbolseverlerin takımları, oyuncuları ve maçları takip edebildiği, yorum ve sohbet yoluyla topluluk etkileşiminde bulunabildiği bir futbol topluluk platformudur.</p>
            <p>Gizlilikle ilgili sorularınız için <a href="mailto:tribunasyon@gmail.com">tribunasyon@gmail.com</a> adresinden bize ulaşabilirsiniz.</p>
        </section>

        <section id="toplanan-bilgiler">
            <h2>2. Toplanan Bilgiler</h2>
            <p>Hesap oluşturduğunuzda veya Tribünasyon'u kullandığınızda aşağıdaki bilgiler işlenebilir:</p>
            <ul>
                <li>Ad, kullanıcı adı ve e-posta adresi</li>
                <li>Profil fotoğrafı, biyografi ve diğer profil bilgileri</li>
                <li>Favori takım, takip edilen takımlar ve takip edilen oyuncular</li>
                <li>Yorumlar, yorum yanıtları ve beğeniler</li>
                <li>Maç sohbet mesajları ve diğer topluluk etkileşimleri</li>
            </ul>
        </section>

        <section id="google">
            <h2>3. Google ile Giriş</h2>
            <p>Google ile giriş veya kayıt seçeneğini kullandığınızda yalnızca hesabın kurulması ve doğrulanması için gereken Google kullanıcı kimliği, doğrulanmış e-posta adresi, ad ve varsa Google profil fotoğrafı kullanılır.</p>
            <ul>
                <li>Google access token kalıcı olarak saklanmaz.</li>
                <li>Google refresh token saklanmaz.</li>
                <li>Gmail, Drive, Calendar veya Google hesabınızdaki diğer özel içeriklere erişilmez.</li>
            </ul>
        </section>

        <section id="guvenlik">
            <h2>4. Şifreler ve Güvenlik</h2>
            <p>Kullanıcı parolaları plaintext olarak saklanmaz; güvenli bir hash yöntemiyle korunur. Şifre sıfırlama işlemleri süreli ve tek kullanımlık güvenli tokenlar üzerinden yürütülür. Gerçek parolanız e-posta ile gönderilmez.</p>
        </section>

        <section id="epostalar">
            <h2>5. E-postalar</h2>
            <p>Tribünasyon; şifre sıfırlama, hesap güvenliği, hesapla ilgili önemli bildirimler ve gelecekte açıkça izin verdiğiniz hizmet bildirimleri için e-posta gönderebilir. Sistem e-postalarının gönderen adresi <a href="mailto:tribunasyon@gmail.com">tribunasyon@gmail.com</a> olabilir.</p>
        </section>

        <section id="cerezler">
            <h2>6. Çerezler ve Oturum</h2>
            <p>Giriş oturumunu sürdürmek, CSRF gibi güvenlik kontrollerini uygulamak ve tema gibi kullanıcı tercihlerini hatırlamak için cookie, session ve gerektiğinde tarayıcı depolama özellikleri kullanılır. Bunlar sitenin temel işlevlerinin güvenli biçimde çalışmasına yardımcı olur.</p>
        </section>

        <section id="teknik-veriler">
            <h2>7. Teknik Veriler</h2>
            <p>Hizmet güvenliğini sağlamak, kötüye kullanımı araştırmak ve teknik hataları çözmek amacıyla IP adresi, istek zamanı, tarayıcı veya cihaz bilgisi, erişilen sayfa ve hata kayıtları gibi standart sunucu ve log bilgileri işlenebilir.</p>
        </section>

        <section id="kullanim-amaclari">
            <h2>8. Verilerin Kullanım Amaçları</h2>
            <p>Bilgiler; hesap oluşturma ve yönetimi, giriş ve kimlik doğrulama, kullanıcı profilini gösterme, takip sistemini çalıştırma, yorum ve sohbet özelliklerini sunma, moderasyon, güvenlik, spam ve kötüye kullanımın önlenmesi, teknik sorunların giderilmesi ve hizmetin geliştirilmesi amaçlarıyla kullanılabilir.</p>
        </section>

        <section id="hizmet-saglayicilar">
            <h2>9. Hizmet Sağlayıcılar</h2>
            <p>Platformun çalışması için Google OAuth, e-posta/SMTP altyapısı, hosting, CDN ve güvenlik altyapısı ile futbol veri sağlayıcılarından yararlanılabilir. Bu sağlayıcılar yalnız sundukları hizmetin gerektirdiği ölçüde veri işleyebilir.</p>
            <p>Futbol veri sağlayıcısı; maç, takım, oyuncu ve istatistik bilgilerini sağlamak için kullanılır, kullanıcıların kişisel verilerini sağlamak amacıyla kullanılmaz.</p>
        </section>

        <section id="hesap-silme">
            <h2>10. Hesap Silme</h2>
            <p>Member kullanıcılar ayarlar alanından kendi hesaplarını silebilir. Bu işlemde ad, e-posta, kullanıcı adı, profil ve Google bağlantısı gibi kişisel hesap alanları anonimleştirilebilir veya temizlenebilir; kişisel takip ve beğeni ilişkileri kaldırılabilir.</p>
            <p>Topluluk bütünlüğünü ve konuşma akışını korumak için yorumlar, sohbet mesajları veya benzeri içerikler sistemde kalabilir. Bu içeriklerde içerik sahibi “Silinmiş kullanıcı” olarak gösterilebilir. Bu nedenle hesap silme, kullanıcıya ait bütün veritabanı kayıtlarının fiziksel olarak silindiği anlamına gelmez.</p>
        </section>

        <section id="saklama">
            <h2>11. Veri Saklama</h2>
            <p>Veriler; hesabın kullanıldığı süre boyunca ve hizmetin çalışması, güvenliğin sağlanması veya yasal yükümlülüklerin yerine getirilmesi için gerekli olduğu sürece tutulabilir. Saklama ihtiyacı ortadan kalktığında bilgiler silinebilir veya anonimleştirilebilir.</p>
        </section>

        <section id="haklar">
            <h2>12. Kullanıcı Hakları</h2>
            <p>Hesap sahipleri mevcut profil ve ayarlar özellikleri üzerinden hesap bilgilerini görüntüleyebilir, güncelleyebilir ve member hesaplarını silebilir. Ek talepleriniz için <a href="mailto:tribunasyon@gmail.com">tribunasyon@gmail.com</a> adresine yazabilirsiniz.</p>
        </section>

        <section id="cocuklar">
            <h2>13. Çocukların Gizliliği</h2>
            <p>Tribünasyon 13 yaş altındaki çocukları hedefleyen bir hizmet değildir.</p>
        </section>

        <section id="degisiklikler">
            <h2>14. Politika Değişiklikleri</h2>
            <p>Bu Gizlilik Politikası, hizmetteki veya ilgili gerekliliklerdeki değişikliklere göre gerektiğinde güncellenebilir. Güncel metin bu sayfada yayımlanır.</p>
        </section>

        <section id="yururluk">
            <h2>15. Yürürlük Tarihi</h2>
            <p>Bu Gizlilik Politikası 22 Eylül 2026 tarihinde yürürlüğe girmiştir.</p>
        </section>
    </div>
</article>
@endsection
