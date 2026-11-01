# Hafta 7 – Çekirdek Modül B: Mesajlaşma ve Takas Teklifi + Entegrasyon

## Haftanın amacı
Kullanıcılar arası mesajlaşma ve takas teklif akışını geliştirmek.

## Bu hafta eklenen / değişen dosyalar

| Katman | Dosya | Ne yapar |
|---|---|---|
| **Veritabanı** | `migrations/012_messaging_offers.sql` | `trade_offers.responded_at`, gelen kutusu ve teklif sorguları için indeksler |
| **Bildirim kancası** | `Core/Events.php` | Basit olay sistemi: `Events::emit()` / `Events::on()` |
| | `Services/NotificationListeners.php` | Mesaj ve teklif olaylarını dinleyip bildirim yazar |
| **İş kuralları** | `Services/MessageService.php` | Mesaj kuralları M1–M7 |
| | `Services/TradeOfferService.php` | Teklif durum makinesi ve kurallar T1–T7 |
| **Veri erişim** | `Repositories/MessageRepository.php` | Gelen kutusu (konuşma başına tek satır), yeni mesajları çekme, flood sayacı |
| | `Repositories/TradeOfferRepository.php` | Gelen/giden teklifler, etkilenen bekleyen teklifler |
| | `Repositories/NotificationRepository.php` | Okunmamış sayısı, tek tek okundu işaretleme |
| **API** | `Controllers/MessageController.php`, `OfferController.php`, `NotificationController.php` | Uçlar aşağıda |
| **Test** | `tests/offer_message_test.php` | Gerçek MySQL üzerinde mesaj + teklif testi |
| **Frontend** | `pages/mesajlar.html`, `js/mesajlar.js` | İki panelli mesajlaşma, 8 saniyede bir yeni mesaj kontrolü, mobilde tek panel |
| | `pages/teklifler.html`, `js/teklifler.js`, `js/offers.js` | Gelen / gönderdiğim teklifler ve aşamaya göre butonlar |
| | `pages/ilan.html`, `js/ilan.js` | "Mesaj gönder", ilan türüne göre teklif formu, ilan sahibine gelen teklifler |
| | `pages/bildirimler.html`, `js/auth.js` | Bildirim listesi; menüde okunmamış mesaj / bekleyen teklif / bildirim sayaçları |

## Veri modeli

```
              ┌──────────── messages ────────────┐
users ──< sender_id      listing_id >── listings   receiver_id >── users
              └──────────────────────────────────┘
              ┌────────── trade_offers ──────────┐
users ──< offerer_id     listing_id >── listings (istenen)
                         offered_listing_id >── listings (karşılığında verilen, isteğe bağlı)
              └──────────────────────────────────┘
users ──< notifications
```

- **Mesaj–ilan ilişkisi:** Her mesaj bir ilana bağlıdır (`messages.listing_id`). Bir "konuşma" ayrı bir tablo değildir; **(ilan, kişi A, kişi B)** üçlüsüyle tanımlanır. Gelen kutusu bu üçlüye göre gruplayıp her konuşmanın son mesajını gösterir.
- **Teklif–ilan ilişkisi:** `trade_offers` bir ilana iki kez bağlanır: istenen ilan (`listing_id`) ve karşılığında verilen ilan (`offered_listing_id`). Karşılığında verilen ilan silinirse teklif kaydı kalır, bağlantı boşalır (SET NULL).

## Takas durum makinesi

```
                 ┌── sahip reddeder ──────────────> red
                 │
 beklemede ──────┼── teklif veren geri çeker ─────> iptal
                 │
                 └── sahip kabul eder ──> kabul ──┬── taraflardan biri "tamamlandı" ─> (ilanlar: tamamlandi)
                                         (ilanlar  │
                                          rezerve) └── taraflardan biri "vazgeç" ───> iptal (ilanlar tekrar aktif)
```

| Durum | Kim değiştirebilir | Sonraki durumlar |
|---|---|---|
| `beklemede` | Sahip: kabul/red · Teklif veren: geri çek | `kabul`, `red`, `iptal` |
| `kabul` (ilanlar rezerve) | İki taraf da | Tamamlandı (ilanlar `tamamlandi`) veya `iptal` (ilanlar `aktif`) |
| `red`, `iptal` | — | Son durum |

Arayüzde ayrıca "tamamlandı" aşaması gösterilir: teklif `kabul` durumundayken ilan da `tamamlandi` ise takas bitmiş demektir. Bu bilgi ayrı bir sütunda tutulmaz, iki tablodan hesaplanır; böylece aynı bilgi iki yerde saklanıp birbiriyle çelişemez.

## İş kuralları

### Mesajlaşma (`MessageService`)
| Kod | Kural | Neden |
|---|---|---|
| M1 | Mesaj 1–2000 karakter, baştaki ve sondaki boşluklar silinir | Boş ve aşırı uzun mesaj olmasın |
| M2 | Kişi kendine mesaj atamaz | Anlamsız kayıt olmasın |
| M3 | Her konuşmanın bir tarafı ilan sahibidir. İlan sahibi olmayan kişinin mesajı **her zaman** ilan sahibine gider; istekte başka bir alıcı yazılsa bile dikkate alınmaz | Site, ilan dışındaki kişilere mesaj göndermek için kullanılamaz |
| M4 | İlan sahibi konuşmayı **başlatamaz**; sadece kendisine yazan ya da teklif veren kişiye cevap verir | İstenmeyen mesajı (spam) önler |
| M5 | Yeni konuşma sadece yayındaki veya rezerve ilanlarda başlar | Kapanmış ilana yeni mesaj gelmez; mevcut konuşmalar sürer |
| M6 | Dakikada en fazla 10 mesaj (429) | Flood koruması |
| M7 | Konuşmayı sadece iki taraf okur; açılınca "okundu" olur (✓✓) | Gizlilik |

### Takas teklifi (`TradeOfferService`)
| Kod | Kural | Neden |
|---|---|---|
| T1 | Sadece yayındaki ilana teklif verilir; kişi kendi ilanına teklif veremez | Rezerve veya kapanmış ilana teklif olmaz |
| T2 | Bir kişinin aynı ilana aynı anda sadece **bir** bekleyen teklifi olabilir | Teklif yağmuru olmaz; teklif değişecekse önce geri çekilir |
| T3 | İlan türüne göre teklif: **satılık** → tutar zorunlu; **takas** → ilan ve/veya tutar; **bağış** → sadece talep (tutar yazılsa bile alınmaz) | Teklif ilanın türüyle tutarlı olur |
| T4 | Karşılığında önerilen ilan teklif verene ait, yayında ve istenen ilandan farklı olmalı | Başkasının eşyası teklif edilemez |
| T5 | Tutar 0–100.000 ₺, not en fazla 255 karakter | İlan kurallarıyla tutarlı |
| T6 | Kabul/red: sadece ilan sahibi · Geri çekme: sadece teklif veren · Tamamlama/vazgeçme: iki taraf | Yetki (403) |
| T7 | Kabul **tek işlemde** (transaction) yapılır: teklif kabul edilir → iki ilan rezerve olur → aynı ilana gelen diğer bekleyen teklifler `red` olur → rezerve olan ilanları içeren başka bekleyen teklifler `iptal` olur | Veri yarım kalmaz. Aynı kitap iki ayrı takasa birden verilemez |

## Bildirim kancası (Events)
Servisler bildirim tablosunu **bilmez**; bir şey olduğunda sadece olayı duyurur:

```php
// TradeOfferService::accept() içinde
Events::emit('offer.accepted', ['offerer_id' => ..., 'listing_title' => ..., 'auto_closed' => [...]]);

// NotificationListeners.php içinde (ayrı dosya)
Events::on('offer.accepted', fn($e) => $notifications->create($e['offerer_id'], 'teklif_sonuc', '🎉 Teklifiniz kabul edildi…'));
```

| Olay | Kime bildirim gider |
|---|---|
| `message.sent` | Mesajı alan kişi |
| `offer.created` | İlan sahibi |
| `offer.accepted` | Teklif veren + otomatik kapanan tekliflerin sahipleri |
| `offer.rejected` | Teklif veren |
| `offer.cancelled` | İlan sahibi |
| `offer.withdrawn` | Karşı taraf |
| `offer.completed` | Karşı taraf (değerlendirme hatırlatması) |

**Neden böyle?** Yarın e-posta ya da SMS bildirimi eklenecekse mesaj ve teklif servislerine dokunulmaz; sadece yeni bir dinleyici eklenir. Buna gevşek bağlılık (loose coupling) denir.

## API uçları (hepsi giriş gerektirir)

| Yöntem | Adres | Açıklama |
|---|---|---|
| GET | `/api/messages` | Gelen kutusu (konuşma başına son mesaj + okunmamış sayısı) |
| GET | `/api/messages/{ilanId}/{kisiId}?after=ID` | Konuşma; `after` verilirse sadece o mesajdan sonrakiler gelir |
| POST | `/api/messages` | `{listing_id, receiver_id?, body}` |
| GET | `/api/offers` | Gelen ve gönderdiğim teklifler |
| GET | `/api/listings/{id}/offers` | İlan sahibi tüm teklifleri, diğer kullanıcılar sadece kendi tekliflerini görür |
| POST | `/api/offers` | `{listing_id, offered_listing_id?, offered_amount?, note?}` |
| PUT | `/api/offers/{id}/accept\|reject\|cancel\|complete\|withdraw` | Durum değiştir |
| GET | `/api/notifications` | Bildirimler |
| GET | `/api/notifications/counts` | Menü sayaçları (mesaj, teklif, bildirim) tek istekte |
| PUT | `/api/notifications/{id}/read`, `/api/notifications/read-all` | Okundu yap |

## Önceki modüllerle entegrasyon
- **Kimlik doğrulama (H5):** Bütün uçlar `Auth::requireUser()` ile korunuyor. Gönderen ve teklif veren her zaman oturumdan alınıyor.
- **İlan modülü (H6):** Teklif kabul edilince ilan `rezerve`, tamamlanınca `tamamlandi` oluyor. H6'daki K5 kuralı da bu durumları sadece takas modülünün verebileceğini söylüyordu. İlan sahibi bekleyen teklifleri ilan sayfasından yönetiyor.
- **Değerlendirme (H4'te yazıldı):** `ReviewService` sadece kabul edilmiş takaslara yorum izni veriyor; tamamlanma bildirimi değerlendirme hatırlatması gönderiyor.
- **Bildirimler:** Menüdeki sayaçlar 30 saniyede bir tek istekle (`/counts`) güncelleniyor; sekme arka plandayken istek atılmıyor.

## Nasıl denenir (XAMPP)
```bash
php backend/database/migrate.php            # sadece 012'yi ekler (veya --fresh --seed)
php backend/tests/offer_message_test.php
```

**Gösterilecek ilerleme** (iki farklı tarayıcıda ya da biri gizli pencerede, iki hesapla):
1. **A** hesabı bir takas ilanı verir, **B** hesabı da bir takas ilanı verir.
2. **B**, A'nın ilanında **Mesaj gönder**'e tıklar ve yazar. A'nın menüsünde 💬 sayacı **1** olur.
3. **B**, **Takas teklif et**'e tıklar, kendi ilanını seçip not yazar. A'nın menüsünde 🔄 ve 🔔 sayaçları artar.
4. **A** mesajlara cevap verir. Ardından ilan sayfasında **Gelen teklifler** bölümünden teklifi **kabul eder**; iki ilan da "Rezerve" olur.
5. **B**, Bildirimler'de "teklifiniz kabul edildi" yazısını görür. Teklifler → Gönderdiğim sekmesinde **Takas tamamlandı**'ya tıklar.
6. Dene: A'nın konuşma başlatamaması (M4), aynı ilana ikinci teklif verilememesi (T2), rezerve ilana teklif verilememesi (T1).

## Test sonucu
- **Mesaj ve teklif kuralları:** 42 test; M1–M7, T1–T7, durum geçişleri, transaction etkileri ve 7 bildirim olayının tamamı geçti.
- **Tarayıcı testi (iki kullanıcı):** mesaj → teklif → cevap → kabul → bildirim → tamamlama akışı uçtan uca çalıştı. Menü sayaçları doğru, JavaScript hatası yok, mobilde sohbet tek panel olarak görünüyor.
- **Önceki haftalar:** Hafta 4, 5 ve 6'nın testleri hâlâ geçiyor.

## Yapay zekâ kullanımı
Yapay zekâ şu amaçlarla kullanıldı:
- Takas akışını bir **durum makinesi** olarak modellemek için örnek incelemek.
- Bildirimleri servislerin içine gömmek yerine **olay (event) tabanlı kancaya** taşıyan refaktörü tartışmak.
- Gelen kutusu SQL'ini (konuşma başına tek satır) MySQL ve SQLite'ta çalışacak şekilde yazmak.

M4 (ilan sahibi konuşma başlatamaz) ve T7'deki "aynı kitap iki takasa verilemez" kuralları, test sırasında fark edilen gerçek kullanım sorunlarına göre eklendi.

---

> ✏️ **Öğrenci görevi (kendin yapmalısın):** Ders bu hafta da "çekirdek mantığı ve iş kurallarını kendin yazmanı" istiyor. Önce `TradeOfferService::accept()` fonksiyonunu satır satır okuyup transaction içindeki 3 adımı kendi cümlelerinle aşağıya yaz. Sonra kendin bir kural ekle. Örnekler:
> - **Kolay:** Satılık ilanda teklif edilen tutar, ilan fiyatının yarısından az olamaz. `create()` içindeki `case 'satis':` bloğuna eklenir.
> - **Orta:** Bir kullanıcının toplam bekleyen teklifi 10'u geçemez. `TradeOfferRepository`'ye bir sayım fonksiyonu yazıp `create()` içinde kontrol et.

## Öğrencinin kendi geliştirdiği bölümler
- `accept()` içindeki transaction adımları (kendi cümlelerimle): …
- Kendi eklediğim kural ve nedeni: …

---

## Sözlü sınav hazırlık notları

**1) Takas durumlarını nasıl tasarladın?**
Teklif bir durum makinesi: `beklemede → kabul / red / iptal`; `kabul → tamamlandı / iptal`. Her geçişte iki kontrol yapılıyor: **kim** yapabilir (kabul ve red sadece ilan sahibi, geri çekme sadece teklif veren, tamamlama ve vazgeçme iki taraf) ve **hangi durumdan** yapılabilir (ör. sadece bekleyen teklif kabul edilir). Kurala uymayan geçiş hata döndürüyor. Teklif durumu ile ilan durumu birlikte değişiyor: kabulde ilanlar `rezerve`, tamamlamada `tamamlandi`, vazgeçmede tekrar `aktif` oluyor. Bu değişiklikler tek bir transaction içinde yapılıyor.

**2) Mesaj–ilan ilişkisini nasıl kurdun?**
Her mesajda `listing_id`, `sender_id` ve `receiver_id` var; üçü de yabancı anahtar. Ayrı bir "konuşma" tablosu yok; konuşma (ilan, A, B) üçlüsüyle tanımlanıyor. Gelen kutusu bu üçlüye göre `GROUP BY` yapıp her konuşmanın son mesajını ve okunmamış sayısını alıyor. Mesajı bir ilana bağlamak, konuşmanın neyle ilgili olduğunu her zaman gösteriyor. Ayrıca "taraflardan biri ilan sahibi olmalı" kuralını uygulamayı kolaylaştırıyor.

**3) Bu modülün verisini nasıl modelledin?**
Üç tablo kullanılıyor: `messages`, `trade_offers` ve `notifications`.
- `trade_offers` ilan tablosuna iki kez bağlı: istenen ilan ve karşılığında verilen ilan. Bu sayede takas, para ya da ikisi birden teklif edilebiliyor.
- `status` alanı teklifin durumunu, `responded_at` alanı ne zaman yanıtlandığını tutuyor.
- "Tamamlandı" bilgisi ayrı bir sütunda tutulmuyor, ilanın durumundan hesaplanıyor; böylece aynı bilgi iki yerde saklanıp birbiriyle çelişmiyor.
- Hızlı sorgular için `(offerer_id, status)` ve `(offered_listing_id, status)` indeksleri eklendi.

**4) Bu modülde hangi iş kuralını neden koydun?**
Örnek olarak T7: Bir teklif kabul edildiğinde, rezerve olan ilanları içeren **başka** bekleyen teklifler otomatik iptal ediliyor. Bu kural olmasaydı aynı kitap iki ayrı takasta birden "verilebilirdi" ve ikinci kişi hiç olmayan bir eşya için bekleyecekti. Diğer örnek M4: İlan sahibi konuşma başlatamıyor; böylece biri ilan açıp tüm kullanıcılara toplu mesaj atamıyor.

**5) Bu modülü diğer modüllerle nasıl entegre ettin?**
- **Kimlik:** Bütün uçlar girişi zorunlu tutuyor. Gönderen ve teklif veren oturumdan alınıyor.
- **İlan:** Teklif ilanın durumunu değiştiriyor. İlan türü (satılık, takas, bağış) teklif formunu ve kurallarını belirliyor.
- **Değerlendirme:** Sadece tamamlanan takaslar değerlendirilebiliyor.
- **Bildirimler:** Olay tabanlı kanca kullanılıyor; mesaj ve teklif servisleri bildirim tablosunu bilmiyor, sadece olayı duyuruyor.
- **Arayüz:** Teklif kartı tek bir dosyada (`offers.js`) ve hem ilan sayfası hem teklifler sayfası bunu kullanıyor.

## Teorik kavramlar (kısa)
- **Veri modeli:** Konuşma bir tablo değil, mesajlardan hesaplanan bir gruptur. Tamamlanma bilgisi de ilan durumundan hesaplanır.
- **İş kuralı ve doğrulama:** Doğrulama verinin biçimini kontrol eder (mesaj boş mu). İş kuralı ise anlamını kontrol eder (bu kişi bu teklifi kabul edebilir mi, bu durumdan o duruma geçilebilir mi).
- **Modülerlik ve olaylar:** Servisler olayı duyurur, dinleyiciler tepki verir. Bir modül değiştiğinde ötekiler etkilenmez.
- **Transaction:** Kabul işlemindeki tüm adımlar ya birlikte gerçekleşir ya da hiçbiri gerçekleşmez.

## Kaçınılan sık hatalar
- **Doğrulamayı atlamak:** Tüm kurallar sunucuda kontrol ediliyor. Arayüz de aynı kuralları gösteriyor (bekleyen teklif varsa buton gizleniyor, form ilan türüne göre değişiyor).
- **Erişim/sahiplik kontrolünü unutmak:** Konuşmayı sadece taraflar okuyabiliyor. Teklif işlemlerinin hepsinde kimin ne yapabileceği kontrol ediliyor (403).
- **İlişkili kayıtları yönetmemek:** Teklif kabul edilince diğer bekleyen teklifler kapanıyor ve iki ilanın durumu birlikte değişiyor. Vazgeçince ilanlar tekrar yayına dönüyor. Kullanıcı silinince mesajları, teklifleri ve bildirimleri CASCADE ile siliniyor.
