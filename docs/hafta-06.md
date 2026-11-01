# Hafta 6 – Çekirdek Modül A: İlan Oluşturma ve Katalog

## Haftanın amacı
İlan oluşturma ve kategorili katalog modülünü geliştirmek.

## Bu hafta eklenen / değişen dosyalar

| Katman | Dosya | Ne yapar |
|---|---|---|
| **İş kuralları** | `Services/ListingService.php` | 7 iş kuralı (K1–K7, aşağıda), form seçenekleri, kendi ilanlarım |
| | `Services/ImageService.php` | Fotoğraf yükleme: tür/boyut kontrolü, yeniden çizme, EXIF/GPS silme, küçültme, kapak seçme |
| **Veri erişim** | `Repositories/ListingRepository.php` | Fotoğraf CRUD, kapak sırası, aktif ilan sayısı, mükerrer başlık kontrolü, ürün durumu filtresi |
| **API** | `Controllers/ListingController.php` | Fotoğraf yükleme/silme/kapak, `/api/me/listings`, `/api/listings/options` |
| **Güvenlik** | `public/uploads/.htaccess` | Yükleme klasöründe PHP ve betik çalıştırmayı kapatır |
| **Test** | `tests/listing_test.php` | Gerçek MySQL üzerinde iş kuralları ve fotoğraf testi |
| **Frontend** | `pages/ilan-ver.html`, `js/ilan-ver.js` | İlan verme **ve** düzenleme formu (`?id=5` ile düzenleme), fotoğraf önizleme |
| | `pages/ilan.html`, `js/ilan.js` | Fotoğraf galerisi; sahibine Düzenle / Yayından kaldır / Sil butonları |
| | `pages/hesabim.html` | Tüm durumlarıyla ilanlarım, aktif ilan limiti |
| | `pages/ilanlar.html` | Ürün durumu filtresi, kapak fotoğraflı kartlar |

## Veri modeli
Bu hafta yeni tablo eklenmedi. Hafta 4'te kurulan model zaten bu modülü taşıyacak şekilde tasarlanmıştı:

```
categories 1 ──< N listings N >── 1 users
                     │
                     1
                     │
                     N
              listing_images   (sort_order = 0 olan kapak fotoğrafıdır)
```

- Bir ilan **tek** kategoriye aittir (`listings.category_id` → `categories.id`). İçinde ilan olan kategori silinemez (RESTRICT).
- Bir ilanın **0–5** fotoğrafı olur. Fotoğraf dosyası diskte, adresi `listing_images` tablosunda tutulur.
- İlan silinince fotoğraf **kayıtları** CASCADE ile otomatik silinir. Diskteki **dosyaları** ise `ImageService` siler, çünkü veritabanı diskteki dosyaları bilmez.

## İş kuralları (`ListingService`)

| Kod | Kural | Neden |
|---|---|---|
| **K1** | Başlık 3–120, açıklama ≤ 2000, buluşma noktası ≤ 120 karakter | Boş veya aşırı uzun içerik engellenir |
| **K2** | Satışta fiyat zorunlu; bağışta fiyat yok; fiyat 0–100.000 ₺ | Veritabanındaki CHECK kısıtıyla aynı kural; kullanıcıya anlaşılır hata mesajı verir |
| **K3** | Doğrulanmamış öğrenci en fazla **3**, doğrulanmış en fazla **20** aktif ilan açabilir | Sahte hesaplarla spam ilanı önler, öğrenci doğrulamasını teşvik eder |
| **K4** | İlanı sadece **sahibi veya yönetici** değiştirir/siler; fotoğraflar için de aynı kural geçerli | Sahiplik kontrolü (Hafta 5 ile entegre) |
| **K5** | Sahip sadece *yayında / kaldırıldı / tamamlandı* seçebilir. *Rezerve* durumunu sadece takas kabulü verir; tamamlanan ilan tekrar yayına alınamaz | Durum geçişleri anlamlı kalır, sahte "rezerve" ilanı olmaz |
| **K6** | Aynı kullanıcının aynı başlıkta iki aktif ilanı olamaz | Mükerrer ilan engellenir |
| **K7** | `ai_suggested_price` alanını kullanıcı yazamaz | Bu değeri sadece yapay zekâ modülü doldurur; kullanıcı sahte "önerilen fiyat" gösteremez |

Ayrıca, **kaldırılmış** bir ilan katalogda görünmez. Adresi bilinse bile sadece sahibi ve yönetici açabilir; diğer herkes 404 alır.

## Fotoğraf yükleme güvenliği (`ImageService`)
1. **Tür kontrolü dosyanın içeriğine göre yapılır** (`finfo`), dosya adına göre değil. `kotu.php` dosyasının adı `kotu.jpg` yapılsa bile reddedilir.
2. Sadece JPG, PNG ve WEBP kabul edilir. Dosya en fazla **5 MB** olabilir, ilan başına en fazla **5** fotoğraf eklenebilir.
3. Dosya adı **rastgele** üretilir (`a3f9…e1.jpg`). Kullanıcının verdiği ad hiç kullanılmadığı için `../../index.php` gibi hilelerle başka bir dosyanın üzerine yazılamaz.
4. Fotoğraf GD kütüphanesiyle **yeniden çizilir**. Bunun iki faydası var:
   - İçine gizlenmiş kod varsa temizlenir.
   - **EXIF bilgisi silinir.** Telefonla çekilen fotoğraflar çekildiği yerin GPS konumunu içerir; bu bilgi silinmezse öğrencinin yurt veya ev adresi herkese açık hale gelebilir (KVKK).
5. Büyük fotoğraflar en fazla 1600 px'e küçültülür, telefonda yan çekilen fotoğrafların yönü düzeltilir.
6. `uploads/.htaccess` dosyası bu klasörde PHP'nin çalışmasını tamamen kapatır. Bu, ilk 5 önlem bir şekilde atlatılsa bile devreye giren son korumadır.

## Arama ve filtre
`GET /api/listings` için:
- **Arama:** `q` (başlık ve açıklamada arar)
- **Filtreler:** `category_id`, `listing_type`, `item_condition`, `min_price`, `max_price`
- **Sıralama:** `sort` → `yeni`, `ucuz`, `pahali`
- **Sayfalama:** `limit`, `offset`

Hafta 4'teki indeksler bu sorguları hızlandırıyor.

## Önceki modüllerle entegrasyon
- **Kimlik doğrulama (Hafta 5):** İlan vermek giriş gerektirir. İlanın sahibi istekteki bilgiden değil, oturumdan alınır. Rol ve sahiplik kontrolleri aynı `Auth` sınıfıyla yapılır.
- **Öğrenci doğrulama (Hafta 5):** Doğrulanmış öğrencilerin ilan limiti daha yüksek (K3).
- **Takas teklifi (Hafta 4'te yazıldı, arayüzü Hafta 7'de gelecek):** İlanı "rezerve" durumuna sadece `TradeOfferService::accept()` geçirebilir (K5).
- **Yapay zekâ (ileride):** `ai_suggested_price` alanı korunuyor (K7). İlan detayında "önerilen piyasa fiyatı" olarak gösteriliyor.

## Nasıl denenir (XAMPP)
```bash
php backend/database/migrate.php --fresh --seed
php backend/tests/listing_test.php
```

**Gösterilecek ilerleme (sırayla):**
1. Giriş yap, menüden **＋ İlan Ver**'e tıkla. Formu boş gönder → her alanın altında hata mesajı çıkar.
2. Formu doldur, 2–3 fotoğraf ekle ve ilanı yayınla. İlan sayfası fotoğraf galerisiyle açılır.
3. **Düzenle**'ye tıkla: ikinci fotoğrafı ★ ile kapak yap, bir fotoğrafı sil, ilan türünü **Bağış** yap ve kaydet. Fiyat alanı kaybolur, ilanda "Ücretsiz" yazar.
4. **Yayından kaldır**'a tıkla. Başka bir hesapla aynı ilan adresini aç → "İlan bulunamadı" mesajı çıkar.
5. **İlanlar** sayfasında kategori ve ürün durumuyla filtrele, fiyata göre sırala.
6. Uzantısı `.jpg` yapılmış bir metin dosyası yüklemeyi dene → "Sadece JPG, PNG veya WEBP" hatası çıkar.

## Test sonucu
- **İş kuralı ve fotoğraf testleri:** 32 test. Sahte dosya, boyut, en fazla 5 fotoğraf, GPS bilgisinin silinmesi, kapak sırası ve dosya silme dahil hepsi geçti.
- **Tarayıcı akış testi:** ilan ver → fotoğraf yükle → düzenle → yayından kaldır → başka kullanıcıyla görüntüle. Testte JavaScript hatası çıkmadı.
- **Önceki haftalar:** Hafta 4 ve 5'in testleri de hâlâ geçiyor.

## Yapay zekâ kullanımı
Yapay zekâ şu amaçlarla kullanıldı:
- Dosya yükleme güvenliği örneklerini incelemek: MIME taklidi, dosya adıyla klasör dışına çıkma (path traversal), EXIF içindeki konum bilgisi.
- İş kurallarını tek bir servis sınıfında toplamak için refaktör önerisi almak.
- Form seçeneklerini sunucudan tek kaynaktan vermek (`/api/listings/options`) için öneri almak.

Kurallardaki limitler (3/20 ilan, 5 fotoğraf, 100.000 ₺) projenin ihtiyacına göre seçildi.

---

> ✏️ **Öğrenci görevi (kendin yapmalısın):** Ders, "modülün çekirdek mantığını ve iş kurallarını kendin yazmanı" istiyor. `ListingService.php` dosyasını okuyup K1–K7 kurallarından en az ikisini kendi cümlelerinle açıkla. Ardından kendin yeni bir kural ekle. Örnekler:
> - "Ders Kitabı kategorisinde açıklama en az 20 karakter olmalı (baskı yılı yazılsın diye)."
> - "Başlık tamamen büyük harf olamaz."
>
> Kuralı `validate()` fonksiyonuna eklemen yeterli.

## Öğrencinin kendi geliştirdiği bölümler
- Açıkladığım iş kuralları: …
- Kendi eklediğim kural ve nedeni: …

---

## Sözlü sınav hazırlık notları

**1) İlanı nasıl kategorize ettin?**
`categories` ayrı bir tablo ve her ilanın `category_id` alanı bu tabloya yabancı anahtarla bağlı (1-N). Kategori adı ilanın içine yazılmadığı için, ad değişirse tek yerden güncellenir (3NF). İçinde ilan olan kategori silinemez (`ON DELETE RESTRICT`). Kategoriler `slug` alanıyla adreslerde de kullanılabiliyor. İlan formu kategori listesini `/api/categories` ucundan alıyor; listede ayrıca her kategorideki aktif ilan sayısı da var.

**2) Aramayı nasıl kurdun?**
`ListingRepository::buildWhere()` gelen filtrelere göre `WHERE` koşullarını tek tek ekliyor: arama metni için `title LIKE ? OR description LIKE ?`, ayrıca kategori, tür, ürün durumu ve fiyat aralığı. Tüm değerler `?` yer tutucusuyla gönderildiği için SQL injection yapılamaz. Sıralama sadece izin verilen seçeneklerden (`yeni/ucuz/pahali`) seçilebilir; kullanıcı `ORDER BY` içine kendi yazdığı bir şeyi koyamaz. Sonuçlar `limit/offset` ile sayfalanıyor. Kategori + durum + tarih için bileşik indeks olduğundan liste sorgusu hızlı çalışıyor.

**3) Bu modülün verisini nasıl modelledin?**
- `listings` ana tablodur.
- `users` ve `categories` ile ilişkisi 1-N'dir.
- Fotoğraflar 1-N ilişkiyle ayrı `listing_images` tablosundadır; 1NF gereği fotoğraf adresleri tek bir sütuna virgülle yazılmaz. `sort_order` alanı kapak fotoğrafını belirler.
- İlanın yaşam döngüsü `status` alanıyla izlenir: yayında → rezerve → tamamlandı, ya da kaldırıldı.
- Tür (`satis/takas/bagis`), fiyatın zorunlu olup olmadığını belirler.

**4) Bu modülde hangi iş kuralını neden koydun?**
Örnek olarak K3: Doğrulanmamış hesaplar en fazla 3 aktif ilan açabiliyor. Amaç, sahte hesaplarla kampüs dışından spam ilan açılmasını önlemek ve öğrencileri okul e-postasıyla doğrulamaya teşvik etmek. Diğer örnek K7: Önerilen fiyatı sadece yapay zekâ modülü yazabiliyor. Kullanıcı bu alanı kendisi doldurabilseydi, kendi ilanına sahte bir "piyasa fiyatı" koyup alıcıyı yanıltabilirdi.

**5) Bu modülü diğer modüllerle nasıl entegre ettin?**
- **Kimlik doğrulama:** İlan vermek giriş gerektiriyor. İlanın sahibi, istekteki `user_id` bilgisinden değil oturumdan alınıyor; böylece başkası adına ilan açılamıyor.
- **Roller:** Yönetici her ilanı düzenleyip silebiliyor, öğrenci sadece kendi ilanını.
- **Öğrenci doğrulama:** İlan limitini belirliyor.
- **Takas modülü:** İlanı "rezerve" durumuna sadece takas kabulü geçirebiliyor.
- **Ortak bileşenler:** Bütün modüller aynı `ValidationException` ve JSON hata biçimini kullanıyor. Arayüzde aynı `listingCard()` fonksiyonu ana sayfada, ilanlar sayfasında ve hesabım sayfasında ortak kullanılıyor.

## Teorik kavramlar (kısa)
- **Veri modeli:** Varlıklar (ilan, kategori, fotoğraf) ve aralarındaki ilişkiler.
- **İş kuralı / doğrulama:** Doğrulama verinin *biçimini* kontrol eder (başlık boş mu, fiyat sayı mı). İş kuralı ise *anlamını* kontrol eder (bu kullanıcı bu ilanı açabilir mi, bu durum geçişi mümkün mü).
- **Modülerlik:** Her sınıfın tek bir görevi var: `ListingService` kuralları uygular, `ImageService` dosyaları işler, `ListingRepository` SQL çalıştırır, `ListingController` HTTP isteğini karşılar. Bir parça değişince diğerleri etkilenmez.

## Kaçınılan sık hatalar
- **Doğrulamayı atlamak:** Her kural hem arayüzde (kullanıcıya anında bilgi vermek için) hem sunucuda (güvenlik için) kontrol ediliyor.
- **Erişim/sahiplik kontrolünü unutmak:** Düzenleme, silme ve fotoğraf işlemlerinin hepsinde `assertCanModify()` çağrılıyor.
- **İlişkili kayıtları yönetmemek:** İlan silinince fotoğraf kayıtları CASCADE ile, diskteki dosyalar da `ImageService` tarafından siliniyor. Kategori silinmek istenirse içindeki ilanlar RESTRICT ile korunuyor.
