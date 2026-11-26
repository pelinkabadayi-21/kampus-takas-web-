# Hafta 9 – Harici API Entegrasyonu: Görsel / Bildirim

## Haftanın amacı
Görsel işleme ve bildirimleri harici servislerle entegre etmek:
- İlan fotoğrafları **Cloudinary** görsel depolama API'sine yükleniyor.
- Önemli bildirimler **Brevo** e-posta API'si ile de gönderiliyor.
- Her iki servis için **hata, zaman aşımı ve hız sınırı (rate limit) yönetimi** yapılıyor.
- API anahtarları **koda ve GitHub'a yazılmıyor**, ortam değişkeninde saklanıyor.

**Neden bu iki servis?**
- **Cloudinary:** Hafta 8'de canlıya aldığımız Render'ın ücretsiz planında disk kalıcı değil; sunucu her yeniden başladığında yüklenen fotoğraflar siliniyordu. Cloudinary fotoğrafları kalıcı saklıyor ve hızlı bir adresten (CDN) sunuyor.
- **Brevo:** Site içi bildirim, kullanıcı siteye girmeden görünmüyor. Yeni teklif veya teklif kabulü gibi önemli olaylarda e-posta gitmesi gerekiyordu. Ücretsiz planda günde 300 e-posta hakkı var.

## Bu hafta eklenen / değişen dosyalar

| Katman | Dosya | Ne yapar |
|---|---|---|
| **Ortak** | `Core/HttpClient.php` | Bütün harici çağrıların geçtiği istemci: zaman aşımı, yeniden deneme, 429/Retry-After, günlük |
| | `Core/ExternalApiException.php` | Harici servis hatası (servis, HTTP kodu, deneme sayısı) |
| | `Core/Env.php` | Ortam değişkenleri ve `backend/.env` dosyası |
| **Görsel** | `Services/ImageStorage.php` | Depolama sözleşmesi (arayüz) |
| | `Services/CloudinaryImageStorage.php` | Cloudinary: imzalı yükleme, silme, adres sahipliği kontrolü |
| | `Services/LocalImageStorage.php` | Disk (XAMPP'te varsayılan, canlıda yedek) |
| | `Services/ImageService.php` | Fotoğraf hazırlandıktan sonra depolamaya gönderiliyor; Cloudinary çökükse diske yedekleniyor |
| **Bildirim** | `Services/Mailer.php`, `Services/BrevoMailer.php` | E-posta sözleşmesi ve Brevo uygulaması |
| | `Services/EmailListeners.php` | Olayları dinleyip e-posta gönderen kanca (kurallar E1–E5) |
| | `Services/Integrations.php` | Anahtar varsa servisi açar, yoksa kapalı çalıştırır |
| **Veri** | `migrations/014_external_api.sql` | `users.email_notifications`, `external_api_logs` tablosu |
| | `Repositories/ApiLogRepository.php` | API günlüğü: kayıt, 24 saat özeti, saatlik e-posta sayacı |
| **API** | `AuthController::preferences()` | `PUT /api/me/preferences` – e-posta bildirimi aç/kapat |
| | `AdminController::integrations()`, `testIntegration()` | Yönetici için servis durumu, son çağrılar, canlı deneme |
| | `HealthController` | `/api/health` cevabında hangi servisin açık olduğu |
| **Ayar** | `backend/.env.example`, `config.example.php`, `docker/start.sh`, `render.yaml` | Anahtarların nereye yazılacağı |
| **Test** | `tests/api_integration_test.php` | Sahte servis cevaplarıyla 42 test (veritabanı sunucusu gerekmez) |
| | `tests/api_canli_test.php` | Gerçek Cloudinary ve Brevo'ya istek atan deneme (öğrenci bölümü) |
| **Frontend** | `js/api.js` | Cloudinary adreslerini doğru gösterme + boyuta göre küçültülmüş fotoğraf |
| | `pages/hesabim.html`, `js/hesabim.js` | "E-posta bildirimi" kutusu |
| | `pages/yonetim.html`, `js/yonetim.js` | "Harici servisler" kartı: durum, 24 saat özeti, son çağrılar, test butonları |
| | `index.html`, `js/app.js` | Sistem durumunda harici servisler, modül listesi |
| | `js/ilan-ver.js` | Fotoğraf yedeğe düştüyse kullanıcıya bilgi |

## Akış

```
            ┌───────────────── Fotoğraf yükleme ─────────────────┐
Tarayıcı ──► ImageService ──► içerik kontrolü ──► GD ile yeniden çiz (EXIF/GPS silinir, ≤1600 px)
                                                        │
                                       ┌────────────────┴──────────────┐
                              CLOUDINARY_URL var?                      yok
                                       │                                │
                     HttpClient ─► Cloudinary /image/upload        diske kaydet
                       │  başarılı → https://res.cloudinary.com/…  (uploads/listings)
                       └─ başarısız (zaman aşımı, 5xx, 429) ──► diske YEDEKLE + günlük

            ┌─────────────── Bildirim (olay tabanlı, Hafta 7) ──────────────┐
TradeOfferService ── Events::emit('offer.accepted') ──┬─► NotificationListeners → site içi bildirim (her zaman)
                                                      └─► EmailListeners → Brevo /v3/smtp/email (anahtar varsa)
```

**Teklif servisine hiç dokunulmadı.** Hafta 7'de "yarın e-posta eklenecekse sadece yeni bir dinleyici eklenir" demiştik; bu hafta tam olarak bu yapıldı.

## REST ve API kimlik doğrulama

| | Cloudinary | Brevo |
|---|---|---|
| Uç | `POST https://api.cloudinary.com/v1_1/{hesap}/image/upload` ve `/image/destroy` | `POST https://api.brevo.com/v3/smtp/email` |
| Gövde | `multipart/form-data` (dosya + parametreler) | JSON |
| Kimlik doğrulama | **İmzalı istek:** parametreler ada göre sıralanır → `public_id=…&timestamp=…` → sonuna gizli anahtar eklenir → **SHA-1**. Gizli anahtar ağ üzerinden **hiç gitmez**. İmza 1 saat geçerli. | **API anahtarı başlıkta:** `api-key: …`. HTTPS ile gittiği için şifreli. |
| Başarılı yanıt | `200` + `{"secure_url": "https://res.cloudinary.com/…", "public_id": …}` | `201` + `{"messageId": "<…>"}` |
| Hata yanıtı | `{"error": {"message": "…"}}` | `{"code": "…", "message": "…"}` |

**Yanıtın ayrıştırılması:** `json_decode` ile diziye çevriliyor. Başarı sadece HTTP kodu 2xx ise değil, beklenen alan da varsa kabul ediliyor (Cloudinary'de `https://` ile başlayan `secure_url`, Brevo'da `messageId`). Hata yanıtında servisin kendi mesajı (`error.message` veya `message`) okunup Türkçe açıklamanın sonuna ekleniyor. Örnek: `HTTP 401, API anahtarı geçersiz veya yetkisiz – Key not found`.

İmza algoritmasının doğruluğu, Cloudinary belgelerindeki örnek parametreler ve bilinen sonuç (`bfd09f95…`) ile test edildi.

## Hata, zaman aşımı ve hız sınırı yönetimi (`HttpClient`)

| Durum | Ne yapılıyor | Neden |
|---|---|---|
| Servis cevap vermiyor | Bağlantı en fazla **3 sn**, cevap en fazla **8 sn** (fotoğraf yüklemede **10 sn**) bekleniyor | Kullanıcının isteği sonsuza kadar asılı kalmasın |
| Zaman aşımı / ağ hatası / `5xx` | **Geçici hata:** 0,5 sn → 1 sn bekleyip en fazla 2 kez daha deneniyor (üstel bekleme) | Kısa süreli kesintilerde işlem kendiliğinden başarılı olsun |
| `429` Çok fazla istek (rate limit) | Servisin `Retry-After` başlığında söylediği kadar bekleniyor (en fazla 3 sn), sonra tekrar deneniyor | Servisin hız sınırına uyulsun; aynı anda yağmur gibi istek gönderilmesin |
| `400`, `401`, `403`, `404` | **Kalıcı hata:** tekrar DENENMİYOR | Yanlış anahtar 3 kez gönderilse de düzelmez, boşuna bekletir |
| Bütün denemeler başarısız | `ExternalApiException` fırlatılıyor; çağıran servis **yedek yola** geçiyor | Harici servis çökse bile site çalışmaya devam etsin |
| Her çağrı | `external_api_logs` tablosuna: servis, işlem, HTTP kodu, deneme sayısı, süre, hata | Yönetici sorunları panelden görsün. **Anahtar, e-posta adresi ve içerik yazılmaz** |

**Yedek yollar (zarif geri çekilme):**
- **Cloudinary çöktü:** Fotoğraf kaybolmuyor, diske kaydediliyor. Kullanıcıya "Görsel servisine şu an ulaşılamadı; fotoğraf geçici olarak sunucuya kaydedildi" bilgisi veriliyor.
- **Cloudinary'de silme başarısız:** Veritabanı kaydı silinmiş oluyor, buluttaki dosya artık olarak kalıyor ve hata günlükte görünüyor. Kullanıcının işlemi engellenmiyor.
- **Brevo çöktü:** Teklif yine kabul ediliyor, site içi bildirim yine yazılıyor. Sadece e-posta gitmiyor ve bu günlüğe düşüyor (E3).
- **Anahtar hiç girilmemiş:** O özellik kapalı çalışıyor (fotoğraflar diske, e-posta yok). XAMPP'te anahtar girmeden de proje çalışıyor.

## E-posta bildirimi kuralları (`EmailListeners`)

| Kod | Kural | Neden |
|---|---|---|
| E1 | Sadece önemli olaylarda e-posta gider: **yeni teklif** (ilan sahibine), **teklif kabulü** (teklif verene, buluşma noktasıyla), **kabulden sonra vazgeçme** (karşı tarafa) | Her mesajda e-posta göndermek kullanıcıyı rahatsız eder; mesajlar sitede görünüyor |
| E2 | Kullanıcı Hesabım sayfasından e-posta bildirimlerini kapatabilir | Kullanıcının tercihi; KVKK'daki "rıza" ilkesine uygun |
| E3 | E-posta gönderilemezse asıl işlem bozulmaz | Harici servis hatası, takası engellememeli |
| E4 | E-postada karşı tarafın e-posta adresi/telefonu yok; kullanıcının yazdığı metinler HTML kaçışıyla (`htmlspecialchars`) ekleniyor | Kişisel veri sızmaz; ilan başlığına yazılan `<script>` e-postada çalışmaz |
| E5 | **Öğrenci bölümü:** Bir kullanıcıya saatte en fazla 5 e-posta | Aşağıda |

## API uçları

| Yöntem | Adres | Erişim | Açıklama |
|---|---|---|---|
| GET | `/api/health` | 🔓 | Cevaba `integrations` eklendi: `{"image_storage": "cloudinary"\|"yerel", "email": "brevo"\|"kapalı"}`. Anahtarlar gösterilmez |
| POST | `/api/listings/{id}/images` | 🔑 sahibi | Cevaba `storage` eklendi: `cloudinary`, `yerel` veya `yerel (yedek)` |
| PUT | `/api/me/preferences` | 🔑 | `{email_notifications: true\|false}` |
| GET | `/api/admin/integrations` | 🛡️ | Servis durumu, son 24 saat özeti, son 20 çağrı |
| POST | `/api/admin/integrations/test` | 🛡️ | `{service: "email"\|"image"}`: yöneticiye test e-postası gönderir veya test görseli yükleyip siler. Servis hata verirse `502` |

## Önceki modüllerle entegrasyon
- **İlan modülü (H6):** Fotoğraf yükleme kuralları (tür, boyut, EXIF silme, en fazla 5 fotoğraf) aynen geçerli. Sadece "nereye kaydedilecek" kısmı değişti. Fotoğraf, EXIF/GPS bilgisi silindikten **sonra** Cloudinary'ye gidiyor; harici servise konum bilgisi içeren ham fotoğraf hiç gönderilmiyor. İlan silinince fotoğraflar Cloudinary'den de siliniyor.
- **Bildirim (H7):** Olay sistemine ikinci bir dinleyici eklendi; teklif servisinde tek satır değişmedi.
- **Kimlik (H5):** Tercih ucu kullanıcı kimliğini istekten değil oturumdan alıyor. Yönetici uçları `requireRole('yonetici')` ile korunuyor.
- **Canlı yayın (H8):** `docker/start.sh`, Render panelinde girilen anahtarları tarayıcıya kapalı `backend/.env` dosyasına aktarıyor. `SITE_URL` girilmediyse Render'ın kendi adresi kullanılıyor.

## Nasıl kurulur ve denenir

### 1. Anahtarları almak (ikisi de ücretsiz)
- **Cloudinary:** https://cloudinary.com → kayıt ol → Dashboard → **API Environment variable** satırını kopyala (`cloudinary://API_KEY:API_SECRET@CLOUD_NAME`).
- **Brevo:** https://www.brevo.com → kayıt ol → **SMTP & API → API Keys → Generate** → anahtarı kopyala. **Senders** bölümünden gönderen e-posta adresini doğrula.

### 2a. XAMPP'te
```bash
copy backend\.env.example backend\.env      # sonra .env içine anahtarları yaz
php backend/database/migrate.php            # sadece 014'ü ekler
php backend/tests/api_integration_test.php  # sahte cevaplarla 42 test
php backend/tests/api_canli_test.php        # gerçek servislerle deneme (MAIL_TEST_TO da yazılmalı)
```

### 2b. Render'da
Render paneli → servis → **Environment** → `CLOUDINARY_URL`, `BREVO_API_KEY`, `MAIL_FROM` ekle → **Save, rebuild and deploy**. Logs'ta `Harici servisler: Cloudinary açık, e-posta açık` satırı görünür.

### Gösterilecek ilerleme
1. Ana sayfada **Sistem Durumu:** "Fotoğraflar: Cloudinary ☁️ · E-posta bildirimi: açık ✉️".
2. **A** hesabı fotoğraflı bir ilan verir. Fotoğrafa sağ tıklayıp adresini kopyala: `https://res.cloudinary.com/…` ile başlar. Cloudinary panelinde **Media Library → kampus-takas/listings** klasöründe görünür.
3. **B** hesabı bu ilana teklif verir → **A**'nın e-posta kutusuna "Yeni takas teklifi" e-postası gelir.
4. **A** teklifi kabul eder → **B**'ye buluşma noktasını içeren "Teklifiniz kabul edildi 🎉" e-postası gelir.
5. **B**, Hesabım'da e-posta bildirimini kapatır; sonraki teklifte e-posta gelmez, site içi bildirim gelir.
6. Yönetici → **Yönetim Paneli → Harici servisler:** son çağrılar, süreleri ve sonuçları. "Görsel servisini dene" ve "Bana test e-postası gönder" butonları.
7. **Hata yönetimi gösterimi:** Render'da `BREVO_API_KEY` değerini bilerek bozup yeniden başlat → teklif kabulü yine çalışır, yönetim panelinde ❌ 401 ve "API anahtarı geçersiz" görünür, deneme sayısı **1**'dir (tekrar denenmedi).

## Test sonucu
- **`api_integration_test.php`:** 42 testin hepsi geçti. Kapsadığı konular:
  - **HTTP istemcisi:** 200, 401, 429/Retry-After, 503 ve zaman aşımı durumları.
  - **Gerçek cURL zaman aşımı:** 3 saniyede cevap veren yerel bir sunucu, 1 saniye sonunda kesildi.
  - **Cloudinary:** imza (belgelerdeki örnekle aynı), yükleme, silme ve adres sahipliği kontrolü; servis çökünce diske yedekleme.
  - **Brevo:** istek biçimi; anahtar ve e-posta adresinin günlüğe yazılmadığı.
  - **E-posta kuralları:** E1–E5.
  - **Ortam değişkenleri:** `.env` okuma; kaynak kodda gerçek anahtar bulunmadığı.
- **`api_canli_test.php`:** Anahtarı olmayan servis atlanıyor ve nedeni yazılıyor. XAMPP'te gerçek Brevo sunucusuna yanlış anahtarla istek atıldı: `HTTP 401 – Key not found` döndü ve **tek denemede** bırakıldı (kalıcı hata tekrar denenmiyor). Cloudinary ve e-posta gönderimi, anahtarlar `backend/.env` dosyasına yazıldıktan sonra aynı komutla denenecek.
- **Regresyon (XAMPP, PHP 8.2 + MariaDB, 26.11.2026):** `migrate.php --fresh --seed` ile 14 migration çalıştı ve örnek veri yüklendi. `crud_test`, `auth_test`, `listing_test`, `offer_message_test`, `ogrenci_test`, `api_integration_test` ve `api_canli_test` testlerinin hepsi geçti.
- **Regresyon testinin bulduğu iki hata ve çözümü:**
  1. Migration 014'teki satır sonu açıklamalarında `;` vardı. `migrate.php` dosyayı `;` işaretinden böldüğü için açıklamanın yarısını SQL sanıp hata verdi. Açıklamalardaki `;` virgülle değiştirildi.
  2. Entegrasyon testi geçici sunucunun çıktısını `/dev/null`'a yönlendiriyordu; Windows'ta bu dosya yok. Windows'ta `NUL` kullanılacak şekilde düzeltildi.
- **XAMPP ayarı:** `php.ini` dosyasında GD eklentisi kapalıydı (`;extension=gd`); bu yüzden `listing_test` çalışmıyordu. Fotoğrafı yeniden çizip EXIF/GPS bilgisini silmek için gereken eklenti açıldı. Eski dosyanın yedeği `C:\xampp\php\php.ini.yedek` olarak saklandı.

## Yapay zekâ kullanımı
Yapay zekâ, ders paketinde belirtildiği gibi **API dokümantasyonunu çözümlemek ve örnek istek/yanıt incelemek** için kullanıldı:
- Cloudinary'nin imzalı yükleme kuralları (hangi parametreler imzaya girer, sıralama, SHA-1) ve örnek `secure_url` cevabı incelendi.
- Brevo `/v3/smtp/email` isteğinin JSON biçimi ve `201 {"messageId": …}` cevabı incelendi.
- Hangi HTTP kodlarının tekrar denenmesi gerektiği (429, 5xx evet; 4xx hayır) ve `Retry-After` başlığı tartışıldı.

Yapay zekânın verdiği bilgiler testlerle doğrulandı. Cloudinary imzası, belgelerdeki örnekle aynı sonucu veriyor. Hata senaryoları sahte cevaplarla tek tek denendi.

---

## Öğrencinin kendi geliştirdiği bölümler

### 1. API çağrılarını kendim yazıp test ettim
`BrevoMailer::send()`:
```php
$body = json_encode([                                            // 1
    'sender'      => ['name' => $this->fromName, 'email' => $this->fromEmail],
    'to'          => [['email' => $toEmail, 'name' => $toName]],
    'subject'     => $subject,
    'htmlContent' => $html,
], JSON_UNESCAPED_UNICODE);

$res = $this->http->request('POST', self::ENDPOINT, [            // 2
    'api-key'      => $this->apiKey,                             // 3
    'Content-Type' => 'application/json',
    'Accept'       => 'application/json',
], $body, ['action' => 'email', 'user_id' => $userId]);          // 4

if (empty($res['json']['messageId'])) {                          // 5
    throw new ExternalApiException('brevo: cevapta messageId yok', 'brevo', $res['status'], $res['attempts']);
}
```
1. Brevo'nun istediği JSON gövde hazırlanıyor: gönderen, alıcı listesi, konu ve HTML içerik. `JSON_UNESCAPED_UNICODE` sayesinde Türkçe harfler `ş` gibi kodlara dönüşmüyor.
2. İstek doğrudan cURL ile değil, `HttpClient` üzerinden gidiyor. Böylece zaman aşımı, yeniden deneme ve günlük kendiliğinden geliyor.
3. Kimlik doğrulama: anahtar `api-key` başlığında. Anahtar `$this->apiKey` içinde; o da ortam değişkeninden geliyor, kodda yazılı değil.
4. Günlük için işlemin adı ve e-postayı alan kişinin id'si veriliyor. E-posta adresi ve içerik günlüğe gitmiyor.
5. HTTP 201 gelse bile cevapta `messageId` yoksa başarılı sayılmıyor (yanıtın ayrıştırılması).

**Test:**
- `php backend/tests/api_canli_test.php` gerçek servislere istek atıyor:
  - Cloudinary'ye test görseli yükleyip adresin açıldığını (HTTP 200) kontrol ediyor ve görseli siliyor.
  - Brevo ile `MAIL_TEST_TO` adresine e-posta gönderiyor.
  - **Yanlış anahtarla** istek atıp `401` döndüğünü ve **tekrar denenmediğini** kontrol ediyor.
- Ayrıca yönetim panelindeki test butonlarıyla canlı sitede de deneniyor.

### 2. Anahtarı ortam değişkeninde sakladım
- Anahtarlar **hiçbir PHP dosyasında yok.**
  - XAMPP'te `backend/.env` dosyasında duruyor. Bu dosya `.gitignore`'da, yani GitHub'a gitmiyor. `backend/.htaccess` ile de tarayıcıya kapalı.
  - Render'da anahtarlar panelin **Environment** bölümünde. `render.yaml` içinde `sync: false` olarak işaretlendikleri için değerleri depoya yazılmıyor.
- `Env::load()` sunucuda tanımlı değişkeni `.env` ile **ezmiyor**; sunucu ayarı her zaman öncelikli.
- `/api/health` ve yönetim paneli sadece "açık/kapalı" bilgisini gösteriyor, anahtarın kendisini asla göstermiyor.
- Test, kaynak kodda `xkeysib-…` veya `cloudinary://…@` biçiminde gerçek bir anahtar olmadığını da kontrol ediyor.
- `backend/.env.example` dosyasında anahtarların adları var, değerleri boş. Projeyi klonlayan kişi neyi nereye yazacağını buradan görüyor.

### 3. Kendi eklediğim kural — E5: Bir kullanıcıya saatte en fazla 5 e-posta
`EmailListeners::notify()` içinde, e-posta göndermeden önce `external_api_logs` tablosundan bu kişiye son 60 dakikada **başarıyla** gönderilen e-posta sayısı okunuyor (`ApiLogRepository::countRecent()`). Sayı 5'e ulaştıysa e-posta gönderilmiyor. Bu durum günlüğe "E5: kullanıcının saatlik e-posta sınırı doldu" hatası ve **0 deneme** ile yazılıyor.

**Neden:**
- Popüler bir ilana kısa sürede çok teklif gelirse ilan sahibinin e-posta kutusu dolmasın.
- Brevo'nun günlük 300 e-posta kotası tek bir kullanıcı yüzünden bitmesin. Bu, **bizim tarafımızdaki hız sınırı**dır. Servisin hız sınırı (429) ise `HttpClient`'ta yönetiliyor.
- Site içi bildirim sınırsız gitmeye devam ediyor; kullanıcı hiçbir şeyi kaçırmıyor.

**Test:** `api_integration_test.php` → 7 e-postadan ilk 5'i gitti, 6. ve 7. engellendi; başka kullanıcının sınırı ayrı sayıldı; 1 saat geçince tekrar gönderilebildi.

---

## Sözlü sınav hazırlık notları

**1) Görseli nasıl sakladın / optimize ettin?**
Üç adımda:
- **Sunucuda hazırlama (H6'dan beri):**
  - Dosya türü içerikten kontrol ediliyor.
  - Fotoğraf GD ile yeniden çiziliyor; EXIF/GPS bilgisi siliniyor.
  - Uzun kenar en fazla 1600 px'e küçültülüyor ve JPEG kalite 82 ile kaydediliyor.
  - Dosya adı rastgele (32 hex) üretiliyor.
- **Saklama (H9):** Hazırlanan fotoğraf Cloudinary'ye **imzalı istekle** yükleniyor. Veritabanında sadece dönen `https://res.cloudinary.com/…` adresi tutuluyor. Cloudinary'ye ulaşılamazsa diske yedekleniyor.
- **Gösterme:** Arayüz, Cloudinary adresine `f_auto,q_auto,w_480` ekliyor. Böylece ilan kartında küçük, tarayıcının desteklediği en hafif biçimde (WebP/AVIF) bir kopya geliyor. Büyük fotoğraf sadece ilan detayında yükleniyor.

**2) Bildirimi ne zaman tetikledin?**
Servisler iş bittikten **sonra** olay yayıyor. Örneğin `accept()` içinde transaction **commit edildikten sonra** `Events::emit('offer.accepted')` çağrılıyor. Böylece yarım kalan (geri alınan) bir işlem için e-posta gitmiyor.

Bu olayı iki dinleyici duyuyor:
- Site içi bildirim (her zaman yazılıyor).
- E-posta (anahtar varsa, kullanıcı kapatmadıysa ve E5 sınırı dolmadıysa gönderiliyor).

E-posta sadece üç olayda gidiyor: yeni teklif, teklif kabulü ve kabulden sonra vazgeçme. Mesajlarda e-posta gitmiyor.

**3) API anahtarını nasıl ve nerede sakladın?**
Ortam değişkeninde. XAMPP'te `backend/.env` dosyasında, Render'da panelin Environment bölümünde duruyor. `.env` dosyası `.gitignore`'da olduğu için depoya gitmiyor; `backend/` klasörü `.htaccess` ile tarayıcıya kapalı olduğu için dışarıdan okunamıyor.

İki servisin anahtarı farklı şekilde kullanılıyor:
- **Cloudinary:** Gizli anahtar isteğe hiç eklenmiyor, sadece imza hesaplamakta kullanılıyor.
- **Brevo:** Anahtar HTTPS üzerinden başlıkta gidiyor.

Günlük, sağlık kontrolü ve yönetim paneli anahtarı hiçbir zaman göstermiyor.

**4) API çağrısı başarısızsa ne oluyor?**
Önce hatanın türüne bakılıyor:
- **Geçici hata** (zaman aşımı, ağ hatası, 5xx, 429): Kısa bir beklemeden sonra en fazla 2 kez daha deneniyor. 429'da servisin istediği süre kadar bekleniyor.
- **Kalıcı hata** (401 yanlış anahtar, 400 eksik alan): Hiç tekrar denenmiyor.

Sonuç ne olursa olsun günlüğe yazılıyor. Hepsi başarısız olursa **yedek yol** devreye giriyor:
- Fotoğraf diske kaydediliyor.
- E-posta gitmiyor ama teklif yine kabul ediliyor ve site içi bildirim yine yazılıyor.

Kullanıcı hata ekranı görmüyor; yönetici sorunu panelde ❌, HTTP kodu ve hata mesajıyla görüyor.

**5) Yanıtı nasıl ayrıştırdın?**
`json_decode($body, true)` ile diziye çevirdim. Başarıyı sadece HTTP koduna göre değil, **beklediğim alanın varlığına** göre kabul ediyorum:
- Cloudinary'de `https://` ile başlayan `secure_url`.
- Brevo'da `messageId`.

Hata cevabında servisin kendi mesajını okuyorum (Cloudinary `error.message`, Brevo `message`) ve kısaltıp Türkçe açıklamanın sonuna ekliyorum. Cloudinary'den gelen adresin bizim hesabımızın bizim klasörüne ait olduğunu da düzenli ifadeyle kontrol ediyorum (`owns()`). Başka bir adres veritabanına yazılsa bile onu silmeye çalışmıyoruz.

## Teorik kavramlar (kısa)
- **REST:** Kaynaklara adres (URL) ve HTTP yöntemiyle erişmek. Örnekler: `POST /image/upload` fotoğraf oluşturur, `POST /v3/smtp/email` e-posta oluşturur. Cevabın anlamı durum kodundan anlaşılır: 2xx başarılı, 4xx bizim hatamız, 5xx servisin hatası.
- **API kimlik doğrulama:** Servisin isteği kimin gönderdiğini anlaması. Bu projede iki yöntem var:
  - **API anahtarı başlıkta** (Brevo).
  - **İmzalı istek** (Cloudinary): gizli anahtar gönderilmez, onunla hesaplanan özet gönderilir.
- **Rate limit (hız sınırı):** Bir servisin belli sürede kabul ettiği en fazla istek sayısı. Aşılırsa servis 429 döner; biz `Retry-After` kadar bekliyoruz. Kendi tarafımızda da kullanıcı başına saatlik e-posta sınırı (E5) var.
- **Hata yönetimi:** Hataları yakalamak, türüne göre (geçici veya kalıcı) karar vermek, yedek yola geçmek ve günlüğe yazmak. Bir servisin çökmesi bütün siteyi çökertmemeli.

## Kaçınılan sık hatalar
- **Anahtarı koda gömmek:** Anahtarlar `.env` dosyasında veya Render panelinde. Depoda sadece adları olan `.env.example` var. Test, kaynak kodda anahtar olmadığını kontrol ediyor.
- **Hataları yakalamamak:** Bütün harici çağrılar `ExternalApiException` fırlatıyor ve her çağıran onu yakalayıp yedek yola geçiyor. E-posta dinleyicisi hiçbir hatayı dışarı sızdırmıyor (E3).
- **Zaman aşımını yönetmemek:** Bağlantı 3 sn, cevap 8–10 sn ile sınırlı. Gerçek cURL ile 3 saniyede cevap veren bir sunucunun 1 saniyede kesildiği test edildi.

## Bilinen sınırlar / ileride yapılacaklar
- E-posta, isteğin içinde gönderiliyor. Brevo yavaşsa teklif kabulü de en fazla birkaç saniye gecikebilir. Daha büyük bir projede e-postalar bir **kuyruğa** yazılır ve arka planda gönderilir.
- Cloudinary'ye ulaşılamadığında diske yedeklenen fotoğraflar, Render yeniden başlarsa kaybolabilir. İleride bu fotoğrafları servis düzelince Cloudinary'ye taşıyan bir görev eklenebilir.
