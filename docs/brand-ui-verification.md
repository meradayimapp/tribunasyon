# Tribünasyon marka arayüzü doğrulaması

7 Eylül 2026 — yerel çalışma; push/deploy yapılmadı.

## Sonuç ve kök neden

Mobil logo bileşenindeki `$showFullLogo`, yalnız `desktop` varyantında true
olabiliyordu. Mobil varyant küçük logo boşken tema/site logosunu atlayıp varsayılan
işaret ve site adına düşüyordu. Sorun tarayıcı cache'i değildi: mevcut upload
servisi zaten benzersiz `branding/...` dosya yolları üretiyor.

`SiteSetting::themeLogoUrl($theme, $compact)` artık aynı çözümlemeyi iki ekran
için kullanıyor. Mobilde küçük logo → uygun tema logosu → site logosu → mevcut
diğer tema fallback'i sırası uygulanır. Hiçbiri yoksa mevcut işaret/metin fallback'i
gösterilir. Masaüstünün önceki öncelik sırası korunur. Silinmiş dosyalar mevcut
`mediaUrl` doğrulamasıyla atlanır; query string veya yeni DB alanı eklenmedi.

## Marka ve ikonlar

Merkezi tokenlar `resources/scss/app.scss` içinde. Cyan→lime gradient CTA'lara,
koyu `#111216` CTA metniyle uygulandı. Sidebar, mobil navigasyon/menü, admin
sekmeleri, bağlantılar, arama, focus ring, checkbox/radio/switch, sayfalama,
takip/beğeni, yükleme ve Livewire ilerleme rengi güncellendi. Açık temada küçük
metinler için okunabilir koyu teal (`#006b61`) kullanılıyor.

Önceki `app-bg`, `surface`, `surface-soft`, `surface-raised`, `ink`, `muted`,
`line`, `line-strong`, `hover`, `input-bg`, `nav-glass`, `header-glass`, `danger`
tokenları iki temada da birebir korundu. Metin gönderilerinin mevcut mavi tonlu
kart background'u da background değiştirmeme şartı nedeniyle korundu.
Takım görselleri ve takım renkleri değiştirilmedi.

`resources/views/components/ui/icon.blade.php` merkezi, dekoratif `aria-hidden`
SVG bileşenidir. Gestalt v159.11.0 SVG path'leri yerel Blade'e alınmıştır;
React/Gestalt runtime veya yeni paket kurulmamıştır. Kaynak/eşleştirme ve
Apache-2.0 lisansı `licenses/gestalt-NOTICE.md` ve `licenses/gestalt-LICENSE.txt`
dosyalarındadır. Futbol, kupa, activity ve more ikonları aynı ağırlığa uygun
yerel eklemelerdir. Navigation 24px, post action 26px; temel dokunma alanları
44px. Beğeni outline/filled olarak değişir. Eski `bi-*` kullanımı kalmadı;
Bootstrap Icons font import'u kaldırıldı, mevcut dependency listesi korunuyor.

## Mobil header

`resources/js/app-shell.js` mevcut Alpine layout state'ine bağlanır. Yalnız
768px altındaki CSS fixed header kullanır. 8px birikimli yön eşiğinde aşağı
kaydırma gizler, yukarı kaydırma gösterir; 20px altındaki scroll konumunda
görünürdür. Transform geçişi 200ms; layout offset ve safe-area korunur.

Menü, görünen dropdown/arama önerisi, header input odağı ve klavye odağı header'ı
görünür tutar. Tema düğmesinde kalan pointer odağı kaydırmayı engellemez.
Alpine'in deklaratif window listener'ları layout kaldırılınca temizlenir;
destroy body scroll kilidini kaldırır. Livewire navigation ve resize state'i
sıfırlar. Masaüstündeki mevcut sticky konumlandırma korunur; hide transform'u
mobil media query dışına uygulanmaz.

## Değişen kaynak dosyaları

- `app/Models/SiteSetting.php`
- `resources/scss/app.scss`
- `resources/js/app.js`, `resources/js/app-shell.js`
- `resources/views/layouts/app.blade.php`
- `resources/views/components/ui/icon.blade.php`
- `resources/views/components/site-brand.blade.php`
- `resources/views/components/admin/image-upload.blade.php`
- `resources/views/components/post-media-carousel.blade.php`
- `resources/views/livewire/post-actions.blade.php`
- `resources/views/livewire/comments.blade.php`
- `resources/views/livewire/follow-team.blade.php`
- `resources/views/livewire/header-search.blade.php`
- `resources/views/livewire/feed.blade.php`
- `resources/views/feed.blade.php`
- `resources/views/search/index.blade.php`
- `resources/views/posts/show.blade.php`
- `resources/views/profile/show.blade.php`
- `resources/views/moderator/posts/form.blade.php`
- `tests/Feature/SiteBrandTest.php`
- `tests/Feature/DynamicTeamsBrandingNavigationTest.php`
- `tests/Feature/FeedPaginationTest.php`
- `tests/Frontend/app-shell.test.js`
- `licenses/gestalt-LICENSE.txt`, `licenses/gestalt-NOTICE.md`
- `public/build/manifest.json`, `public/build/assets/*`

## Testler

| Kontrol | Sonuç |
| --- | --- |
| `php artisan test` | 97 test, 380 assertion geçti |
| `php vendor/bin/pint --test` | Geçti (Windows PHP üzerinden) |
| `node --test tests/Frontend/app-shell.test.js` | 7 test geçti |
| `npm run build` | Geçti; mevcut Bootstrap/Sass deprecation uyarıları var |
| `git diff --check` | Geçti |
| Public/admin/moderatör ekranları | 276 viewport/tema/sayfa kontrolü geçti |

Tarayıcı matrisi: 375 / 390 / 430 / 768 / 1024 / 1280px, dark + light.
Her kombinasyonda 200 response, SVG ikon varlığı, yatay taşma, JavaScript
hataları, background tokenları ve mevcut primary CTA'ların koyu metni/gradient'i
kontrol edildi. Mobil/masaüstü feed, yorum ve admin settings ekran görüntüleri
ayrıca gözle incelendi. Test verisi ve upload'lar geçici, ayrı SQLite/media
alanında oluşturuldu; kullanıcının yerel/prod verisi değiştirilmedi.

Kontrol edilen sayfalar: `/`, `/takimlar`, `/takim/fenerbahce`, post detay/yorum,
`/maclar`, `/ara`, profil, giriş, kayıt, şifre sıfırlama isteği; tüm sekiz admin
ana sayfası (`/admin` ve teams/organizations/users/moderators/posts/comments/settings);
moderatör dashboard, gönderi listesi/oluşturma, yorum listesi, profil düzenleme.

Gerçek tarayıcı etkileşimleri: aşağı/yukarı/top scroll; tema düğmesi sonrası
scroll; layout offset; masaüstünde hide sınıfı/transform uygulanmaması; member ve
admin menüleri; active settings; beğeni kaldır/ekle; takip bırak/ekle;
yorum/yanıt gönderimi; admin site logosu upload/değiştirme; masaüstü-mobil
aynı logo; küçük logo önceliği; küçük logo kaldırılınca site logosuna dönüş.

Logo testleri site/small/theme/default ve kayıp dosya senaryolarını kapsar.
Upload regression testi aynı isimli yüklemede farklı stored path oluştuğunu,
eski dosyanın temizlendiğini ve küçük logo silme fallback'ini doğrular.

Eski sayfalama testinin tüm sayfada SVG yasaklayan beklentisi yeni SVG sistemiyle
çelişiyordu. Bootstrap sayfalama alanında SVG bulunmaması kontrolü korundu;
sayfalama container/listesi ve sayfanın diğer alanlarında yeni SVG ikonlarının
bulunması kontrolleri eklendi. Önceki pagination/içerik/dil beklentileri korundu.

## Build ve sınırlar

Manifest'in gösterdiği güncel asset'ler:

- `public/build/assets/app-RUPvgfVC.css`
- `public/build/assets/app-Nz5yPXWc.js`

Eski hash'li build dosyaları ve artık kullanılmayan ikon fontları Vite build
sırasında yenilendi/kaldırıldı; önceki sürümleri Git geçmişinde bulunur.
Runtime upload'lar build içine konmadı. Migration eklenmedi; authorization,
middleware/policy, route ve dependency dosyaları değiştirilmedi. Git history
değiştirilmedi; commit/push/deploy yapılmadı.

Kalan manuel cihaz kontrolü: gerçek iOS Safari'de safe-area, adres çubuğu/klavye
ve overscroll; native paylaşım penceresi. Yerel headless Chrome kontrolleri fiziksel
iPhone doğrulaması yerine geçmez. Production üzerinde test/deploy yapılmadı.
