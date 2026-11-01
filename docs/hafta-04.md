# Hafta 4 – Veritabanı Tasarımı ve Veri Erişim Katmanı

## Haftanın amacı
Veritabanı şemasını oluşturmak, ilişkileri kurmak ve temel veri erişim katmanını yazmak.

## Bu hafta eklenen / değişen dosyalar

| Katman | Dosya | Ne yapar |
|---|---|---|
| **Veritabanı** | `backend/database/migrations/001…010_*.sql` | Her tablo ayrı, numaralı migration dosyası |
| | `backend/database/migrate.php` | Migration'ları sırayla çalıştırır, hangisinin çalıştığını `migrations` tablosunda tutar |
| | `backend/database/seeds/seed.php` | Sentetik (uydurma) örnek veri: 15 kullanıcı, 8 kategori, 32 ilan, teklifler, mesajlar, yorumlar |
| | `backend/database/schema.sql` | Migration'ların tek dosya hali (phpMyAdmin ile içe aktarmak için) |
| **Veri erişim** | `backend/src/Repositories/*Repository.php` | Her tablo için CRUD fonksiyonları (prepared statement) |
| | `BaseRepository.php` | Ortak `insert / update / fetch / transaction` fonksiyonları |
| **İş kuralları** | `backend/src/Services/*Service.php` | Doğrulama, fiyat kuralları, teklif kabul işlemi (transaction) |
| **API** | `backend/public/index.php` | `GET/POST/PUT/DELETE /api/listings` uç noktaları |
| **Test** | `backend/tests/crud_test.php` | Gerçek MySQL üzerinde CRUD + kısıt + transaction testi |
| **Frontend** | `frontend/pages/ilanlar.html`, `ilan.html` | İlan listesi (arama, filtre, sıralama) ve ilan detayı |

## Nasıl çalıştırılır (XAMPP)
XAMPP Kontrol Paneli → **Shell** butonu → proje klasörüne geç:

```bash
cd C:\xampp\htdocs\kampus-takas-web-
php backend/database/migrate.php --fresh --seed   # tabloları kur + örnek veri
php backend/database/migrate.php --status         # hangi migration çalıştı
php backend/tests/crud_test.php                   # CRUD testleri
```

Ardından tarayıcıda: `http://localhost/kampus-takas-web-/frontend/pages/ilanlar.html`

## Tablolar ve kısıtlar

| Tablo | Kısıtlar |
|---|---|
| `users` | `email` UNIQUE, `student_no` UNIQUE, `CHECK (email LIKE '%@%')` |
| `categories` | `name`, `slug` UNIQUE |
| `listings` | FK → users (CASCADE), FK → categories (RESTRICT), **CHECK fiyat kuralı** (satışta fiyat zorunlu ve ≥ 0, bağışta fiyat NULL) |
| `listing_images` | FK → listings (CASCADE) |
| `favorites` | **Birleşik PK (user_id, listing_id)** — N-N ilişki |
| `messages` | 3 FK (ilan, gönderen, alan) |
| `trade_offers` | FK → listings; `offered_listing_id` için ON DELETE SET NULL; `CHECK offered_amount ≥ 0` |
| `reviews` | `UNIQUE (trade_offer_id, reviewer_id)`, `CHECK rating 1–5` |
| `notifications` | FK → users (CASCADE) |

**ON DELETE seçimleri neden farklı?**
- `CASCADE`: Kullanıcı silinirse ilanları, mesajları da silinsin (sahipsiz veri kalmasın).
- `RESTRICT`: İçinde ilan olan kategori silinemez (ilanlar kategorisiz kalmasın).
- `SET NULL`: Teklifte önerilen ilan silinirse teklif kaydı kalsın, sadece bağlantı boşalsın (geçmiş korunsun).

**Neden bazı kurallar veritabanında değil de serviste?**
"Kendine mesaj atamaz", "kendi ilanına teklif veremez" gibi kurallar `MessageService` ve `TradeOfferService` içinde. MySQL 8, `ON DELETE CASCADE` olan yabancı anahtar sütunlarında `CHECK` kısıtına izin vermiyor. Bu yüzden bu kurallar uygulama katmanında kontrol ediliyor.

## İndeksler (`010_add_indexes.sql`)

| İndeks | Hangi sorgu için |
|---|---|
| `listings (category_id, status, created_at)` | "Bu kategorideki aktif ilanlar, en yeniler" — ilan sayfasının ana sorgusu |
| `listings (status, created_at)` | Kategori seçilmeden tüm aktif ilanlar |
| `listings (price)` | Fiyat aralığı filtresi ve fiyata göre sıralama |
| `messages (listing_id, sender_id, receiver_id, created_at)` | Bir konuşmayı sırasıyla getirme |
| `messages (receiver_id, is_read)` | Okunmamış mesaj sayısı |
| `trade_offers (listing_id, status)` | Bir ilana gelen bekleyen teklifler |
| `notifications (user_id, is_read, created_at)` | Bildirim zili |

**Bilinçli olarak eklenmeyenler:**
- `title` üzerine normal indeks: Arama `LIKE '%kelime%'` ile yapılıyor. Baştaki `%` yüzünden B-tree indeks kullanılamaz, sadece yazma işlemlerini yavaşlatırdı. İlan sayısı çok artarsa `FULLTEXT` indeks düşünülebilir.
- `is_verified` gibi sadece 0/1 alan sütunlar tek başına indekslenmedi, çünkü seçicilikleri çok düşük.

İndeksin kullanıldığını görmek için phpMyAdmin → SQL sekmesi:
```sql
EXPLAIN SELECT * FROM listings WHERE category_id = 1 AND status = 'aktif' ORDER BY created_at DESC;
-- "key" sütununda idx_listings_cat_status_date görünmeli
```

## CRUD örnekleri (ListingRepository)

```sql
-- CREATE
INSERT INTO listings (user_id, category_id, title, listing_type, price) VALUES (?, ?, ?, ?, ?);
-- READ (JOIN ile kategori ve sahip adı)
SELECT l.*, c.name AS category_name, u.full_name AS owner_name
FROM listings l JOIN categories c ON c.id = l.category_id JOIN users u ON u.id = l.user_id
WHERE l.id = ?;
-- UPDATE (sadece izin verilen sütunlar — beyaz liste)
UPDATE listings SET price = ? WHERE id = ?;
-- DELETE (fotoğraf, mesaj, favori ve teklifler CASCADE ile otomatik silinir)
DELETE FROM listings WHERE id = ?;
```

`?` işaretleri **prepared statement** yer tutucularıdır. Kullanıcıdan gelen değer SQL metnine yapıştırılmaz, ayrı gönderilir. Böylece SQL injection engellenir.

## İşlem (transaction) örneği
`TradeOfferService::accept()` bir teklif kabul edilince 4 işi **tek işlemde** yapar:
1. Teklifin durumu → `kabul`
2. Aynı ilana gelen diğer bekleyen teklifler → `red`
3. İlan (ve karşılığında verilen ilan) → `rezerve`
4. Teklif sahibine bildirim

Biri hata verirse `rollBack()` ile hepsi geri alınır; ilan "rezerve" olup teklif "beklemede" kalmaz.

## Örnek veri ve KVKK
- İsimler, ad ve soyad listelerinden rastgele birleştirilmiş **uydurma** isimlerdir.
- E-postalar `@ogrenci.example.com` adresindedir. `example.com` alanı RFC 2606 ile test için ayrılmıştır ve gerçek bir kişiye ait olamaz.
- Öğrenci numaraları `TEST0001` biçimindedir. Şifreler düz metin değil, `password_hash()` ile özetlenmiş olarak tutulur.
- Sabit tohum (`mt_srand(2026)`) sayesinde her çalıştırmada aynı veri üretilir.
- İlanlardaki "önerilen fiyat" değerleri şimdilik örnek veridir. Gerçek yapay zekâ önerisi ilerleyen haftalarda eklenecek.

Test girişi: `deniz.soylu1@ogrenci.example.com` / `Test1234!`

## Test sonucu
Geliştirme sırasında migration, seed, CRUD, kısıtlar (CHECK, FK, UNIQUE), CASCADE/RESTRICT ve transaction rollback için 22 test çalıştırıldı, hepsi geçti. Kendi bilgisayarında `php backend/tests/crud_test.php` ile gerçek MySQL üzerinde doğrulayabilirsin.

## Yapay zekâ kullanımı
Yapay zekâ; indeks önerisi almak (hangi sorgu için hangi bileşik indeks), migration yaklaşımını (dosya başına bir tablo) incelemek ve MySQL 8'in FK sütunlarında CHECK kısıtı sınırlamasını tespit etmek için kullanıldı. Önerilen indeksler, sitenin gerçek sorgularıyla karşılaştırılarak seçildi.

---

> ✏️ **Öğrenci görevi (kendin yapmalısın):** Ders bu hafta "en az bir ilişki ve kısıtı elle yazıp açıklamanı" istiyor. Aşağıya, örneğin `favorites` tablosunu veya `listings` fiyat CHECK'ini kendi cümlelerinle açıkla. İstersen kendi yazdığın yeni bir kısıt da ekle (ör. `reviews.comment` en az 5 karakter).

## Öğrencinin kendi geliştirdiği bölümler
- Elle yazdığım ilişki: …
- Elle yazdığım kısıt ve açıklaması: …

---

## Sözlü sınav hazırlık notları

**1) Bir tabloya neden indeks ekledin / eklemedin?**
`listings` tablosuna `(category_id, status, created_at)` bileşik indeksi ekledim, çünkü ilan sayfasının en sık sorgusu "bu kategorideki aktif ilanları en yeniden sırala". İndeks olmasa MySQL tüm tabloyu tarar. `title` sütununa normal indeks eklemedim, çünkü `LIKE '%kelime%'` aramasında baştaki `%` yüzünden indeks kullanılamaz. İndeksler okumayı hızlandırır ama her INSERT/UPDATE'te güncellendiği için yazmayı yavaşlatır, bu yüzden her sütuna eklenmez.

**2) Yabancı anahtar kısıtı ne işe yarar?**
Bir tablodaki değerin başka tabloda gerçekten var olmasını garanti eder (referans bütünlüğü). Örneğin olmayan bir kullanıcıya (`user_id = 999999`) ilan eklenemez. Ayrıca `ON DELETE` ile silme davranışı belirlenir: CASCADE (birlikte sil), RESTRICT (silmeyi engelle), SET NULL (bağlantıyı boşalt).

**3) N-N ilişkiyi nasıl modelledin?**
Bir öğrenci çok ilanı favorileyebilir, bir ilanı da çok öğrenci favorileyebilir. Bunu `favorites` ara tablosuyla modelledim. Tabloda iki yabancı anahtar (`user_id`, `listing_id`) var ve ikisi birlikte **birleşik birincil anahtar**. Bu yüzden aynı ilan aynı kişi tarafından iki kez favorilenemez.

**4) Migration neden kullanılır?**
Veritabanı değişikliklerini kod gibi sürüm kontrolünde tutmak için. Her değişiklik numaralı bir dosya olur (`001_…`, `010_add_indexes.sql`). Ekipteki herkes `migrate.php` çalıştırarak aynı şemaya ulaşır. Hangi dosyanın çalıştığı `migrations` tablosunda tutulduğu için aynı değişiklik iki kez uygulanmaz.

**5) Bir CRUD işleminin SQL'ini açıkla.**
İlan güncelleme: `UPDATE listings SET price = ? WHERE id = ?`. `SET` hangi sütunun değişeceğini, `WHERE` hangi satırın etkileneceğini belirler. `WHERE` unutulursa tüm ilanlar güncellenir. `?` yer tutucuları sayesinde kullanıcıdan gelen değer SQL'e karışmaz (SQL injection önlenir). Ayrıca `updateById()` sadece izin verilen sütunları (beyaz liste) günceller; kullanıcı `user_id` gönderip ilanın sahibini değiştiremez.

## Teorik kavramlar (kısa)
- **DDL** (Data Definition Language): Yapıyı tanımlar — `CREATE`, `ALTER`, `DROP`. Migration dosyaları DDL'dir.
- **DML** (Data Manipulation Language): Veriyi işler — `SELECT`, `INSERT`, `UPDATE`, `DELETE`. Repository'ler DML çalıştırır.
- **İndeks:** Kitabın arkasındaki dizin gibi; aranan satıra tüm tabloyu taramadan ulaşır.
- **Referans bütünlüğü:** Yabancı anahtarın işaret ettiği kaydın her zaman var olması.
- **İşlem (transaction):** Birden çok sorgunun "ya hep ya hiç" çalışması (ACID: Atomiklik, Tutarlılık, Yalıtım, Dayanıklılık).

## Kaçınılan sık hatalar
- İlişkisiz/denormalize tablolar → kategori adı ilanda tekrar etmiyor, `category_id` ile bağlı.
- Kısıt tanımlamamak → FK, UNIQUE ve CHECK kısıtları migration'larda tanımlı.
- Gerçek kişisel veri kullanmak → tüm örnek veri uydurma, e-postalar `example.com`.
