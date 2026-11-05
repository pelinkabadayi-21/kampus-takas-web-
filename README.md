# 📚 Kampüs Takas

Kampüs içi ikinci el kitap ve eşya **ilan, takas ve mesajlaşma** platformu.

> Bilgisayar Uygulamaları I – Proje Tabanlı Ders · **P20 – Kampüs İkinci El Kitap ve Eşya Takas** · Zorluk: Orta

## Problem
Öğrenciler ders kitaplarını ve eşyalarını değerinde alıp satamıyor, çoğu çöpe gidiyor. Bu platform dairesel ekonomiye katkı sağlayarak hem tasarruf hem israfın azalmasını hedefler.

## Modüller
| Modül | Durum |
|---|---|
| Proje iskeleti, mimari, veri modeli | ✅ Hafta 3 |
| Veritabanı, migration, örnek veri, CRUD veri erişim katmanı | ✅ Hafta 4 |
| Kayıt / giriş, parola hash, oturum, rol tabanlı erişim (öğrenci/yönetici) | ✅ Hafta 5 |
| İlan oluşturma (form, fotoğraf yükleme, düzenleme, yayından kaldırma) | ✅ Hafta 6 |
| Kategori / arama / filtre | ✅ Hafta 4 + 6 |
| Mesajlaşma (gelen kutusu, sohbet, okundu bilgisi, spam/flood koruması) | ✅ Hafta 7 |
| Takas teklifi (durum makinesi: kabul/red/geri çek/tamamla/vazgeç) | ✅ Hafta 7 |
| Değerlendirme | ⏳ |
| Bildirim (olay tabanlı kanca, menü sayaçları) | ✅ Hafta 7 |
| Yapay zekâ kategori/fiyat önerisi | ⏳ |
| Öğrenci doğrulama · Bağış modu · Konum bazlı buluşma | 🟡 Doğrulama (Hafta 5), bağış ilanı ve önerilen buluşma noktaları (Hafta 6) |

## Teknolojiler
- **Backend:** PHP 8 (katmanlı mimari, PDO), REST/JSON API
- **Veritabanı:** MySQL / MariaDB
- **Frontend:** HTML5, CSS3, JavaScript (fetch API)
- **Ortam:** XAMPP, Git/GitHub

## Klasör Yapısı
```
backend/    PHP API  (Controllers → Services → Repositories)
  database/   migrations/, seeds/, migrate.php, schema.sql
  tests/      CRUD testi
frontend/   HTML/CSS/JS arayüz
docs/       ER diyagramı, mimari şema, güvenlik notu, haftalık raporlar
```
Ayrıntı: [docs/mimari.md](docs/mimari.md) · Veri modeli: [docs/er-diyagrami.md](docs/er-diyagrami.md)

## Kurulum (XAMPP)
1. Depoyu `C:\xampp\htdocs\` içine klonla:
   ```bash
   git clone https://github.com/pelinkabadayi-21/kampus-takas-web-.git
   ```
2. XAMPP'ten **Apache** ve **MySQL**'i başlat.
3. `backend/config/config.example.php` dosyasını kopyalayıp adını `config.php` yap, gerekirse şifreyi düzenle.
4. XAMPP Kontrol Paneli → **Shell** → veritabanını kur ve örnek veriyi yükle:
   ```bash
   cd C:\xampp\htdocs\kampus-takas-web-
   php backend/database/migrate.php --fresh --seed
   php backend/tests/crud_test.php
   php backend/tests/auth_test.php
   php backend/tests/listing_test.php
   php backend/tests/offer_message_test.php
   php backend/tests/ogrenci_test.php
   ```
   (Alternatif: phpMyAdmin → İçe aktar → `backend/database/schema.sql`. Bu yolla sadece tablolar ve kategoriler gelir.)
5. Test:
   - API: http://localhost/kampus-takas-web-/backend/public/api/health
   - Site: http://localhost/kampus-takas-web-/frontend/

Örnek hesaplar (tüm veriler uydurmadır):

| Rol | E-posta | Parola |
|---|---|---|
| Öğrenci | `deniz.soylu1@ogrenci.example.com` | `Test1234!` |
| Yönetici | `yonetici@ogrenci.example.com` | `Yonetici1234!` |

Ana sayfada **"API çalışıyor"** ve **"Veritabanı bağlı"** yazıyorsa kurulum tamamdır.

## API Uç Noktaları
🔓 herkese açık · 🔑 giriş gerekli · 🛡️ sadece yönetici

| Yöntem | Adres | Erişim | Açıklama |
|---|---|---|---|
| GET | `/api/health` | 🔓 | API ve veritabanı durumu |
| POST | `/api/auth/register` | 🔓 | Kayıt ol |
| POST | `/api/auth/login` | 🔓 | Giriş yap (HttpOnly çerez + token döner) |
| POST | `/api/auth/logout` | 🔑 | Çıkış yap (token sunucudan silinir) |
| GET | `/api/auth/me` | 🔑 | Giriş yapmış kullanıcı |
| GET | `/api/categories` | 🔓 | Kategori listesi (aktif ilan sayısıyla) |
| GET | `/api/listings` | 🔓 | İlan listesi (sadece yayındakiler). Filtreler: `q`, `category_id`, `listing_type`, `item_condition`, `min_price`, `max_price`, `sort` (yeni/ucuz/pahali), `limit`, `offset` |
| GET | `/api/listings/options` | 🔓 | Form seçenekleri (durumlar, buluşma noktaları, limitler) |
| GET | `/api/listings/{id}` | 🔓 | İlan detayı (kategori, sahip, fotoğraflar); kaldırılmış ilanı sadece sahibi/yönetici görür |
| GET | `/api/me/listings` | 🔑 | Kendi ilanlarım (tüm durumlar) |
| POST | `/api/listings` | 🔑 | İlan oluştur (sahibi oturumdan alınır) |
| PUT | `/api/listings/{id}` | 🔑 sahibi / 🛡️ | İlan güncelle (JSON, kısmi) |
| DELETE | `/api/listings/{id}` | 🔑 sahibi / 🛡️ | İlan sil (fotoğraf dosyaları da silinir) |
| POST | `/api/listings/{id}/images` | 🔑 sahibi / 🛡️ | Fotoğraf yükle (`multipart/form-data`, alan: `image`; JPG/PNG/WEBP, ≤ 5 MB, ≤ 5 adet) |
| DELETE | `/api/listings/{id}/images/{imageId}` | 🔑 sahibi / 🛡️ | Fotoğraf sil |
| PUT | `/api/listings/{id}/images/{imageId}/cover` | 🔑 sahibi / 🛡️ | Kapak fotoğrafı yap |
| GET | `/api/admin/users` | 🛡️ | Kullanıcı listesi |
| PUT | `/api/admin/users/{id}/role` | 🛡️ | Rol değiştir (`ogrenci` / `yonetici`) |
| PUT | `/api/admin/users/{id}/verify` | 🛡️ | Öğrenci doğrulamasını aç/kapat |
| GET | `/api/admin/stats` | 🛡️ | Yönetici istatistikleri (öğrenci bölümü, Hafta 5) |
| GET | `/api/messages` | 🔑 | Gelen kutusu |
| GET | `/api/messages/{ilanId}/{kisiId}` | 🔑 taraflar | Konuşma (`?after=ID` ile sadece yeni mesajlar) |
| POST | `/api/messages` | 🔑 | Mesaj gönder `{listing_id, receiver_id?, body}` |
| GET | `/api/offers` | 🔑 | Gelen ve gönderdiğim teklifler |
| GET | `/api/listings/{id}/offers` | 🔑 | İlanın teklifleri (sahibi hepsini, diğerleri kendininkini görür) |
| POST | `/api/offers` | 🔑 | Teklif ver `{listing_id, offered_listing_id?, offered_amount?, note?}` |
| PUT | `/api/offers/{id}/accept\|reject\|cancel\|complete\|withdraw` | 🔑 ilgili taraf | Teklif durumunu değiştir |
| GET | `/api/notifications` | 🔑 | Bildirimler |
| GET | `/api/notifications/counts` | 🔑 | Menü sayaçları |
| PUT | `/api/notifications/{id}/read`, `/read-all` | 🔑 | Okundu yap |

Güvenlik önlemlerinin özeti: [docs/guvenlik-notu.md](docs/guvenlik-notu.md)

## Öğrencinin Kendi Geliştirdiği Bölümler

| Hafta | Eklenen | Dosya |
|---|---|---|
| 4 | Başlık uzunluğu CHECK kısıtı (`chk_listing_title_len`) | `database/migrations/013_ogrenci_kisitlari.sql` |
| 5 | Rol kontrollü uç `GET /api/admin/stats` | `AdminController::stats()` |
| 6 | K8: Başlık tamamen büyük harf olamaz | `ListingService::validate()` |
| 7 | T8: Satılık ilanda teklif fiyatın yarısından az olamaz | `TradeOfferService::create()` |

Ayrıntılar her haftanın raporunda (`docs/hafta-0N.md`), test: `php backend/tests/ogrenci_test.php`. Haftalık ilerleme raporları ve AI kullanım günlükleri: `docs/raporlar/`.

## Haftalık İlerleme

Her haftanın commit mesajı **"Hafta N [backend] / [frontend] / [docs]"** ile başlar. GitHub'da **Commits** sayfasında hangi haftada neyin eklendiğini, commit'e tıklayınca da değişen satırları görebilirsin. Haftanın ayrıntılı açıklaması `docs/hafta-NN.md` raporundadır.

| Hafta | Konu | Rapor | Backend | Frontend |
|---|---|---|---|---|
| 3 | Kurulum, Mimari ve Veri Modeli | [hafta-03.md](docs/hafta-03.md) | Katmanlı iskelet, `/api/health`, `/api/categories` | Ana sayfa, sistem durumu |
| 4 | Veritabanı Tasarımı ve Veri Erişim Katmanı | [hafta-04.md](docs/hafta-04.md) | 10 migration, indeksler, sentetik veri, 8 repository (CRUD), servisler, transaction, ilan CRUD API, CRUD testi | İlan listesi (arama, filtre, sıralama), ilan detayı, ana sayfada son ilanlar |
| 5 | Kullanıcı Yönetimi ve Kimlik Doğrulama | [hafta-05.md](docs/hafta-05.md) · [güvenlik notu](docs/guvenlik-notu.md) | Kayıt/giriş/çıkış API, bcrypt, token (SHA-256 + HttpOnly çerez), hesap kilidi, rol ve sahiplik kontrolü, yönetim API, auth testi | Giriş, kayıt, hesabım (korumalı), yönetim paneli, giriş durumuna göre menü |
| 6 | Çekirdek Modül A: İlan Oluşturma ve Katalog | [hafta-06.md](docs/hafta-06.md) | 7 iş kuralı (ilan limiti, durum geçişleri, mükerrer ilan…), fotoğraf yükleme (içerik kontrolü, EXIF/GPS silme, küçültme), kapak seçme, kendi ilanlarım, ürün durumu filtresi, ilan testi | İlan ver / düzenle formu, fotoğraf önizleme, galeri, sahibine düzenle/kaldır/sil, ilanlarım (durumlarla) |
| 7 | Çekirdek Modül B: Mesajlaşma ve Takas Teklifi + Entegrasyon | [hafta-07.md](docs/hafta-07.md) | Mesaj kuralları M1–M7, teklif durum makinesi ve kuralları T1–T7, transaction ile kabul (çakışan teklifleri kapatma), olay tabanlı bildirim kancası, mesaj/teklif/bildirim uçları, migration 012, test | Mesajlar (iki panel, canlı yenileme, mobil), teklif formu (ilan türüne göre), gelen teklifler, teklifler sayfası, bildirimler, menü sayaçları |
