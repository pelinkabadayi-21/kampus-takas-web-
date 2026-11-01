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
| İlan oluşturma | 🟡 API hazır (Hafta 4), form ⏳ |
| Kategori / arama | 🟡 Listeleme, filtre, sıralama hazır (Hafta 4) |
| Mesajlaşma | ⏳ |
| Takas teklifi | ⏳ |
| Değerlendirme | ⏳ |
| Bildirim | ⏳ |
| Yapay zekâ kategori/fiyat önerisi | ⏳ |
| Öğrenci doğrulama · Bağış modu · Konum bazlı buluşma | ⏳ |

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
docs/       ER diyagramı, mimari şema, haftalık raporlar
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
   ```
   (Alternatif: phpMyAdmin → İçe aktar → `backend/database/schema.sql`. Bu yolla sadece tablolar ve kategoriler gelir.)
5. Test:
   - API: http://localhost/kampus-takas-web-/backend/public/api/health
   - Site: http://localhost/kampus-takas-web-/frontend/

Örnek veri giriş bilgisi: `deniz.soylu1@ogrenci.example.com` / `Test1234!` (tüm veriler uydurmadır).

Ana sayfada **"API çalışıyor"** ve **"Veritabanı bağlı"** yazıyorsa kurulum tamamdır.

## API Uç Noktaları
| Yöntem | Adres | Açıklama |
|---|---|---|
| GET | `/api/health` | API ve veritabanı durumu |
| GET | `/api/categories` | Kategori listesi (aktif ilan sayısıyla) |
| GET | `/api/listings` | İlan listesi. Filtreler: `q`, `category_id`, `listing_type`, `min_price`, `max_price`, `sort` (yeni/ucuz/pahali), `limit`, `offset` |
| GET | `/api/listings/{id}` | İlan detayı (kategori, sahip, fotoğraflar) |
| POST | `/api/listings` | İlan oluştur (JSON) |
| PUT | `/api/listings/{id}` | İlan güncelle (JSON, kısmi) |
| DELETE | `/api/listings/{id}` | İlan sil |

## Haftalık İlerleme

Her haftanın işi ayrı bir commit ve **etiket (tag)** olarak kaydedilir. GitHub'da **Tags** sekmesinden istediğin haftanın halini görebilir, **Compare** ile iki hafta arasındaki farkı inceleyebilirsin.

| Hafta | Konu | Rapor | Etiket | Backend | Frontend |
|---|---|---|---|---|---|
| 3 | Kurulum, Mimari ve Veri Modeli | [hafta-03.md](docs/hafta-03.md) | `hafta-03` | Katmanlı iskelet, `/api/health`, `/api/categories` | Ana sayfa, sistem durumu |
| 4 | Veritabanı Tasarımı ve Veri Erişim Katmanı | [hafta-04.md](docs/hafta-04.md) | `hafta-04` | 10 migration, indeksler, sentetik veri, 8 repository (CRUD), servisler, transaction, ilan CRUD API, CRUD testi | İlan listesi (arama, filtre, sıralama), ilan detayı, ana sayfada son ilanlar |
