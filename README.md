# Tribün

Tribün, futbol takımlarının kendi topluluk sayfalarında sosyal medya akışı şeklinde paylaşım yaptığı Laravel tabanlı bir MVP'dir. Paylaşımlar takım adına görünür; içeriği oluşturan moderatör yalnızca yönetim ekranlarında kayıtlıdır.

> `Tribün` geçici çalışma adıdır. Arayüz adı `APP_NAME` üzerinden değiştirilir.

## Teknoloji yığını

- PHP 8.3+
- Laravel 13
- Blade ve Livewire 4
- Alpine.js (Livewire ile birlikte)
- Bootstrap 5.3, Bootstrap Icons ve özgün SCSS
- Vite 8
- SQLite (yerel) / MySQL uyumlu migration'lar
- Laravel Storage `public` diski

Redis, Laravel Reverb ve gerçek futbol veri sağlayıcısı bu fazda kurulu değildir.

## Yerel kurulum

Gereksinimler: PHP 8.3+, Composer, Node.js 22.12+ veya 24+, npm ve Git. PHP'de `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `mbstring`, `openssl`, `pdo`, `pdo_sqlite`, `pdo_mysql`, `tokenizer`, `xml` ve `zip` eklentileri etkin olmalıdır.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File database/database.sqlite -Force
php artisan migrate:fresh --seed
php artisan storage:link
npm install
npm run build
```

Geliştirme sunucusunu başlatmak için iki terminal kullanabilirsiniz:

```powershell
php artisan serve --no-reload
```

```powershell
npm run dev
```

Alternatif olarak Laravel'in birleşik geliştirme komutu kullanılabilir:

```powershell
composer run dev
```

Uygulama varsayılan olarak `http://127.0.0.1:8000` adresindedir. Bu Windows ortamında PHP yapılandırması `PHPRC` üzerinden bulunduğu için `--no-reload`, SQLite/MySQL sürücülerinin geliştirme sunucusu alt sürecine aktarılmasını sağlar. Sistem genelinde standart bir `php.ini` kullanan ortamlarda düz `php artisan serve` komutu da çalışır. Şifre sıfırlama e-postaları yerelde `storage/logs/laravel.log` dosyasına yazılır.

## Environment ve MySQL

Yerel `.env` varsayılanı SQLite'tır:

```dotenv
APP_NAME="Tribün"
APP_LOCALE=tr
DB_CONNECTION=sqlite
FILESYSTEM_DISK=public
```

MySQL kullanmak için `.env` içindeki veritabanı bölümünü düzenleyin:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tribun
DB_USERNAME=root
DB_PASSWORD=
```

Ardından `php artisan migrate:fresh --seed` çalıştırın. Migration'lar MySQL ile uyumlu string durum/tip alanları, foreign key'ler, indeksler ve birleşik unique constraint'ler kullanır.

## Demo hesapları

Demo hesapları yalnızca `local` ve `testing` ortamlarında oluşturulur. Tüm hesapların parolası `password` değeridir.

| Rol | E-posta | Kullanıcı adı | Atama |
|---|---|---|---|
| Admin | `admin@tribun.test` | `admin` | Tüm sistem |
| Moderatör | `fener.mod@tribun.test` | `fener.mod` | Fenerbahçe |
| Moderatör | `gala.mod@tribun.test` | `gala.mod` | Galatasaray |
| Moderatör | `multi.mod@tribun.test` | `multi.mod` | Beşiktaş, Trabzonspor |
| Üye | `uye@tribun.test` | `taraftar` | Fenerbahçe, Beşiktaş takipçisi |

Production ortamında `DatabaseSeeder` yalnızca takımları oluşturur; varsayılan şifreli kullanıcı oluşturmaz.

## Ana özellikler

- Takip edilen takımlara göre kişisel sosyal feed ve takipsiz kullanıcılar için son gönderiler
- SEO uyumlu takım sayfaları ve paylaşılabilir post detayları
- Livewire ile post/yorum beğenileri, takım takibi, yorum ve tek seviyeli yanıtlar
- Üyelik, giriş, çıkış, şifre sıfırlama ve profil/parola düzenleme
- Takım bazlı policy korumalı moderatör gönderi ve yorum yönetimi
- Takım, kullanıcı, moderatör ataması, gönderi ve yorum yönetimli admin paneli
- Public disk üzerinde doğrulanan görsel yüklemeleri
- `FootballDataService` üzerinden çalışan mock maç ekranı
- Mobil bottom navigation: Ana Sayfa, Takımlar, Maçlar, Profil

## Klasör yapısı

- `app/Enums`: rol, durum ve gönderi tipleri
- `app/Livewire`: feed ve sosyal etkileşim bileşenleri
- `app/Policies`: takım bazlı backend yetkilendirmesi
- `app/Services`: mock maç sağlayıcısı ve medya depolama servisi
- `resources/views`: Blade kullanıcı, admin ve moderatör ekranları
- `resources/scss`: Bootstrap değişkenleri ve özgün responsive tasarım
- `database/migrations`: MySQL uyumlu şema
- `database/seeders`: production-safe takım ve local demo seed'leri
- `database/seeders/assets`: özgün, markasız demo futbol görselleri
- `tests/Feature`: auth, erişim, policy ve Livewire etkileşim testleri

## Doğrulama

```powershell
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pint --test
composer validate
npm run build
```

## Sonraki faz

Redis cache/queue, Laravel Reverb canlı maç odaları, API-Football entegrasyonu, gerçek canlı skorlar, bildirimler, algoritmik feed, video/anket/kadro/maç gönderi tipleri ve rozet/seviye/puan/tahmin sistemleri sonraki fazlara bırakılmıştır.

## Demo görselleri

`stadium.png` ve `matchday.png` projeye özel olarak yapay zekâ ile üretilmiş, gerçek kulüp veya sponsor markası içermeyen demo raster görselleridir. Takım armaları GD ile takım renklerinden oluşturulan geçici monogramlardır ve gerçek kulüp logoları değildir.
