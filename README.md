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
| İlan oluşturma | 🟡 API hazır (Hafta 4), form ⏳ |
| Kategori / arama | 🟡 Listeleme, filtre, sıralama hazır (Hafta 4) |
| Mesajlaşma | ⏳ |
| Takas teklifi | ⏳ |
| Değerlendirme | ⏳ |
| Bildirim | ⏳ |
| Yapay zekâ kategori/fiyat önerisi | ⏳ |
| Öğrenci doğrulama · Bağış modu · Konum bazlı buluşma | 🟡 Okul e-postasıyla otomatik doğrulama + yönetici onayı (Hafta 5) |

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
| GET | `/api/listings` | 🔓 | İlan listesi. Filtreler: `q`, `category_id`, `listing_type`, `min_price`, `max_price`, `sort` (yeni/ucuz/pahali), `limit`, `offset` |
| GET | `/api/listings/{id}` | 🔓 | İlan detayı (kategori, sahip, fotoğraflar) |
| POST | `/api/listings` | 🔑 | İlan oluştur (sahibi oturumdan alınır) |
| PUT | `/api/listings/{id}` | 🔑 sahibi / 🛡️ | İlan güncelle (JSON, kısmi) |
| DELETE | `/api/listings/{id}` | 🔑 sahibi / 🛡️ | İlan sil |
| GET | `/api/admin/users` | 🛡️ | Kullanıcı listesi |
| PUT | `/api/admin/users/{id}/role` | 🛡️ | Rol değiştir (`ogrenci` / `yonetici`) |
| PUT | `/api/admin/users/{id}/verify` | 🛡️ | Öğrenci doğrulamasını aç/kapat |

Güvenlik önlemlerinin özeti: [docs/guvenlik-notu.md](docs/guvenlik-notu.md)

## Haftalık İlerleme

Her haftanın commit mesajı **"Hafta N [backend] / [frontend] / [docs]"** ile başlar. GitHub'da **Commits** sayfasında hangi haftada neyin eklendiğini, commit'e tıklayınca da değişen satırları görebilirsin. Haftanın ayrıntılı açıklaması `docs/hafta-NN.md` raporundadır.

| Hafta | Konu | Rapor | Backend | Frontend |
|---|---|---|---|---|
| 3 | Kurulum, Mimari ve Veri Modeli | [hafta-03.md](docs/hafta-03.md) | Katmanlı iskelet, `/api/health`, `/api/categories` | Ana sayfa, sistem durumu |
| 4 | Veritabanı Tasarımı ve Veri Erişim Katmanı | [hafta-04.md](docs/hafta-04.md) | 10 migration, indeksler, sentetik veri, 8 repository (CRUD), servisler, transaction, ilan CRUD API, CRUD testi | İlan listesi (arama, filtre, sıralama), ilan detayı, ana sayfada son ilanlar |
| 5 | Kullanıcı Yönetimi ve Kimlik Doğrulama | [hafta-05.md](docs/hafta-05.md) · [güvenlik notu](docs/guvenlik-notu.md) | Kayıt/giriş/çıkış API, bcrypt, token (SHA-256 + HttpOnly çerez), hesap kilidi, rol ve sahiplik kontrolü, yönetim API, auth testi | Giriş, kayıt, hesabım (korumalı), yönetim paneli, giriş durumuna göre menü |
