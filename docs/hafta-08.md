# Hafta 8 – VİZE: Ara Sürüm, Entegrasyon ve Sözlü Hazırlık

## Haftanın amacı
O ana kadarki modülleri uçtan uca entegre edip çalışan bir ara sürüm ortaya koymak ve vize sözlüsüne hazırlanmak.

## Bu hafta yapılanlar

### 1. Modüllerin uçtan uca entegrasyonu
- **Değerlendirme modülü tamamlandı.** Bu, çekirdek akıştaki son eksik parçaydı.
  - Akış: ilan → mesaj → teklif → kabul → tamamlama → **değerlendirme**.
  - Uçlar: `POST /api/reviews` ve `GET /api/users/{id}/reviews`.
  - Kurallar R1–R4: sadece tamamlanan takas değerlendirilir, sadece takasın tarafları değerlendirir, puan 1–5, her takas için bir değerlendirme.
  - Teklifler sayfasına yıldızlı değerlendirme formu, ilan sayfasına satıcının puanı, Hesabım sayfasına alınan yorumlar eklendi.
  - Değerlendirme yapılınca bildirim gönderiliyor (`review.created` olayı).
- Ana sayfadaki modül listesi güncellendi: bütün çekirdek modüller **Hazır**.

### 2. Bilinen hataların giderilmesi
| # | Hata | Çözüm |
|---|---|---|
| 1 | `frontend/js/config.js` API adresi `http://localhost/kampus-takas-web-/…` olarak sabitti. Site başka bir adreste (canlı sunucu) açılınca API'ye ulaşamıyordu | Adres, sayfanın açıldığı yerden hesaplanıyor: `location.origin + …/frontend/ öncesi + /backend/public/api`. Hem XAMPP'te hem canlıda ayar değiştirmeden çalışıyor |
| 2 | `backend/config`, `src`, `database`, `tests` klasörleri adres yazılarak tarayıcıdan istenebiliyordu | `backend/.htaccess` → `Require all denied`; sadece `backend/public/.htaccess` → `Require all granted` |
| 3 | Gelen kutusu sorgusundaki `GROUP BY CASE …` ifadesi, MySQL 8'in `ONLY_FULL_GROUP_BY` modunda hata verebilirdi | `GROUP BY listing_id, other_id` (takma ad) olarak değiştirildi |
| 4 | Değerlendirme, takas "kabul" edilir edilmez (henüz tamamlanmadan) yapılabiliyordu | Sadece tamamlanan takasta izin veriliyor (R1). Aynı takası ikinci kez değerlendirmeye çalışınca veritabanı hatası yerine anlaşılır bir mesaj çıkıyor |
| 5 | Proje kök adresi (`/kampus-takas-web-/`) klasör listesi gösteriyordu | Kökteki `index.php` doğrudan `frontend/` sayfasına yönlendiriyor |

### 3. Canlı yayın (Render)
- `Dockerfile` hazırlandı: tek konteynerde PHP 8.2 + Apache + MariaDB çalışıyor; GD, pdo_mysql ve exif eklentileri kurulu.
- Açılış betiği `docker/start.sh`:
  1. Apache'yi Render'ın verdiği porta bağlar.
  2. MariaDB'yi başlatır.
  3. Veritabanı için rastgele bir şifre üretip `config.php` dosyasını oluşturur.
  4. `migrate.php --fresh --seed` çalıştırır.
  5. Apache'yi başlatır.
- Canlıda `debug` kapalı (hata ayrıntısı ziyaretçiye gösterilmez) ve çerez `Secure` (sadece HTTPS üzerinden gönderilir).
- `render.yaml` ile tek tıkla kurulum (Blueprint) de mümkün. Adımlar README'de.

### 4. Ara sürüm etiketi
- Sürüm: **v0.8.0**. Anlamı ve etiketleme adımları aşağıdaki "Sürümleme" bölümünde.

### 5. Demo senaryosu
- [`docs/demo-senaryosu.md`](demo-senaryosu.md): iki pencereyle, yaklaşık 5 dakikalık uçtan uca gösterim.

## Teslim edilen çıktılar
| Çıktı | Nerede |
|---|---|
| Çalışan ara sürüm (etiketli) | GitHub `v0.8.0` + canlı link (Render) |
| Güncel AI kullanım günlüğü | `docs/raporlar/hafta-08/ai-log.docx` |
| Kısa ilerleme raporu | Bu dosya + `docs/raporlar/hafta-08/haftalik-ilerleme.docx` |

## Sürümleme (Semantic Versioning)
Sürüm numarası `MAJOR.MINOR.PATCH` biçimindedir:
- **MAJOR (0):** 1.0.0'a kadar proje "geliştirme aşamasında" sayılır; 1.0.0 final teslimdir.
- **MINOR (8):** Yeni özellik eklendikçe artar. Burada hafta numarasıyla eşleştirildi.
- **PATCH (0):** Sadece hata düzeltmesinde artar. Örneğin vizeden sonra küçük bir düzeltme yapılırsa `v0.8.1` olur.

GitHub'da etiket oluşturma: depo → **Releases** → **Draft a new release** → *Choose a tag*: `v0.8.0` → başlık: "Vize – Ara Sürüm" → **Publish release**.
Komut satırından: `git tag -a v0.8.0 -m "Vize ara sürüm" && git push origin v0.8.0`

## Entegrasyon ve regresyon testi
Her değişiklikten sonra **bütün** testler yeniden çalıştırıldı (regresyon testi). Böylece yeni kodun eski bir özelliği bozmadığından emin olundu.

```bash
php backend/tests/crud_test.php            # H4 veri katmanı
php backend/tests/auth_test.php            # H5 kimlik doğrulama
php backend/tests/listing_test.php         # H6 ilan + fotoğraf
php backend/tests/offer_message_test.php   # H7 mesaj + teklif + bildirim
php backend/tests/ogrenci_test.php         # öğrenci bölümleri
```

Bu hafta değerlendirme kuralları (R1–R4) için `offer_message_test.php` dosyasına 5 yeni test eklendi; önceki haftaların testleriyle birlikte hepsi geçti. Tarayıcıda iki kullanıcıyla uçtan uca akış denendi: kayıt → ilan → teklif → kabul → tamamlama → değerlendirme. JavaScript hatası çıkmadı.

## Yapay zekâ kullanımı
Yapay zekâ şu amaçlarla kullanıldı:
- **Hata ayıklama:** `localhost`'a sabit API adresi, MySQL `ONLY_FULL_GROUP_BY` uyumu, açık kalan backend klasörleri.
- **Refaktör:** Değerlendirme kurallarının servis katmanına taşınması.
- **Canlı yayın:** Render için Docker yapılandırması.

Her değişiklik bu raporda ve AI kullanım günlüğünde açıklandı.

---

## Sözlü sınav hazırlık notları

**1) Şu ana kadarki mimarini özetle.**
İki parça var: **frontend** (HTML/CSS/JS sayfaları) ve **backend** (PHP REST API). İkisi JSON ile konuşuyor. Backend üç katmanlı:
- **Controller (sunum):** İsteği alır, JSON döndürür.
- **Service (iş):** Kuralları uygular. Örnek: K1–K8 ilan kuralları, M1–M7 mesaj kuralları, T1–T8 teklif kuralları, R1–R4 değerlendirme kuralları.
- **Repository (veri):** Sadece SQL çalıştırır (PDO, prepared statement).

Bu katmanların yanında üç ortak parça var:
- `Auth`: kimlik ve rol kontrolü.
- `Events`: bildirim kancası.
- `Router`: adresleri doğru Controller'a yönlendirir.

Veritabanı MySQL'de 10 tablodan oluşuyor (artı migration takip tablosu) ve 13 migration dosyasıyla kuruluyor. Canlıda hepsi tek bir Docker konteynerinde çalışıyor.

**2) Hangi modül hangi veriyi nereden alıyor?**

| Modül | Kullandığı tablolar | Veriyi nereden alıyor |
|---|---|---|
| Kimlik | `users`, `auth_tokens` | Kayıt/giriş formu → `/api/auth/*`; her istekte token HttpOnly çerezden okunur |
| İlan / katalog | `listings`, `categories`, `listing_images` | İlan formu → `/api/listings`. İlanın sahibi istekten değil **oturumdan** alınır. Fotoğraflar diske, adresleri veritabanına yazılır |
| Mesaj | `messages` + `listings` | `/api/messages`; alıcı, ilanın sahibinden belirlenir |
| Takas | `trade_offers` + `listings` | `/api/offers`; kabul edilince `listings.status` değişir |
| Değerlendirme | `reviews` + `trade_offers` | Takasın tarafları ve durumu `trade_offers` tablosundan okunur |
| Bildirim | `notifications` | Diğer modüllerin yaydığı olaylardan (`Events`) yazılır |

**3) En çok zorlandığın entegrasyon neydi, nasıl çözdün?**
Takas ile ilan modülünün birlikte çalışması. Bir teklif kabul edildiğinde aynı anda dört şeyin olması gerekiyor:
- teklifin durumu "kabul" olmalı,
- iki ilan da "rezerve" olmalı,
- aynı ilana gelen diğer teklifler reddedilmeli,
- **aynı kitabı başka bir takasta öneren teklifler** de kapanmalı.

Sonuncusu başta yoktu; aynı kitap iki takasa birden verilebiliyordu. Çözüm olarak `pendingInvolving()` ile etkilenen teklifleri bulup hepsini **tek bir transaction** içinde güncelledim. Biri hata verirse hepsi geri alınıyor. Vize haftasındaki ikinci zorluk, sitenin canlı sunucuda API'ye ulaşamamasıydı (`localhost`'a sabit adres). Bunu adresi sayfanın kendi konumundan hesaplayarak çözdüm.

**4) Yapay zekâdan aldığın bir kod parçasını satır satır açıkla.**
`BaseRepository::transaction()`:
```php
public function transaction(callable $work)
{
    $this->db->beginTransaction();      // 1
    try {
        $result = $work();              // 2
        $this->db->commit();            // 3
        return $result;
    } catch (Throwable $e) {
        $this->db->rollBack();          // 4
        throw $e;                       // 5
    }
}
```
1. Veritabanına "bir işlem başlıyor" der. Bundan sonraki değişiklikler hemen kalıcı olmaz.
2. Parametre olarak gelen fonksiyonu çalıştırır. Örneğin `accept()` içinde teklifi kabul etmek, ilanları rezerve yapmak ve diğer teklifleri kapatmak bu fonksiyonun içinde.
3. Hepsi hatasız biterse `commit()` ile değişiklikleri birlikte kalıcı yapar.
4. Herhangi bir adımda hata olursa `rollBack()` ile **bütün** değişiklikleri geri alır. Böylece "teklif kabul edildi ama ilan rezerve olmadı" gibi yarım bir durum oluşmaz.
5. Hatayı yukarı fırlatır. `index.php` bu hatayı yakalayıp kullanıcıya JSON hata mesajı olarak döndürür.

Bu, ACID'deki **A**'dır (atomiklik): işlemin parçaları ya hep birlikte gerçekleşir ya hiçbiri gerçekleşmez.

**5) Bir sonraki aşamanın planı nedir?**
Hafta 9'da **harici API entegrasyonu** yapılacak:
- İlan fotoğraflarını bir **görsel depolama servisine** taşımak. Ücretsiz sunucuda disk kalıcı olmadığı için bu, canlıda fotoğrafların kaybolması sorununu da çözer.
- Önemli bildirimleri (ör. teklifin kabul edilmesi) **e-posta ile** göndermek. Bunun için `Events` sistemine yeni bir dinleyici eklemek yeterli, mevcut servisler değişmiyor.
- API anahtarları koda yazılmayacak, **ortam değişkeninde** saklanacak. Zaman aşımı ve hata yönetimi eklenecek.

Daha sonraki haftalarda yapay zekâ ile fiyat ve kategori önerisi ile final sürümü (v1.0.0) gelecek.

## Teorik kavramlar (kısa)
- **Entegrasyon:** Ayrı yazılan modüllerin birlikte, doğru veri alışverişiyle çalışması. Bizde ortak sözleşme JSON API, ortak kimlik `Auth`, ortak olay sistemi `Events`.
- **Sürümleme:** `MAJOR.MINOR.PATCH`. Etiket (tag), belirli bir commit'e kalıcı bir isim verir; böylece "vizede teslim edilen kod" her zaman bulunabilir.
- **Regresyon:** Yeni bir değişikliğin daha önce çalışan bir şeyi bozması. Önlemek için her değişiklikten sonra bütün testler yeniden çalıştırılıyor.

## Kaçınılan sık hatalar
- **Entegrasyonu son ana bırakmak:** Her hafta yeni modül önceki modüllere bağlanarak eklendi. Bu hafta sadece son eksik parça (değerlendirme) tamamlandı.
- **Ara sürümü etiketlememek:** `v0.8.0` etiketi oluşturuldu.
- **Açıklanamayan AI kodu taşımak:** Kodun önemli parçaları (transaction, durum makinesi, kimlik doğrulama) haftalık raporlarda satır satır açıklandı.
