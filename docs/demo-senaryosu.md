# Demo Senaryosu – Vize (Ara Sürüm v0.8.0)

> Süre: yaklaşık 5 dakika · İki tarayıcı penceresi gerekir (biri normal, biri gizli pencere).
> Adres: canlı link (`https://…onrender.com`) veya XAMPP'te `http://localhost/kampus-takas-web-/`

**Hazırlık:** Canlı linki sunumdan 1–2 dakika önce aç. Ücretsiz sunucu uyku modundaysa ilk açılış yaklaşık 1 dakika sürer.

| Hesap | E-posta | Parola |
|---|---|---|
| Öğrenci (örnek veri) | `deniz.soylu1@ogrenci.example.com` | `Test1234!` |
| Yönetici | `yonetici@ogrenci.example.com` | `Yonetici1234!` |

---

## 1. Tanıtım (30 sn)
"Kampüs Takas, öğrencilerin ders kitabı ve eşyalarını satabildiği, takas edebildiği ya da bağışlayabildiği bir platform. Backend PHP ve MySQL ile katmanlı mimaride, frontend ise HTML/CSS/JavaScript ile yazıldı."

- Ana sayfada **"API çalışıyor"** ve **"Veritabanı bağlı"** yazılarını göster: kullanıcı arayüzü, API ve veritabanı birbirine bağlı.

## 2. Katalog ve arama (45 sn) — Hafta 4 + 6
- **İlanlar** sayfasında bir kategori seç (ör. *Elektronik*), türü **Satılık** yap, sıralamayı **En ucuz** seç.
- Bir ilana tıkla. Detay sayfasında kategori, durum, 📍 buluşma noktası ve satıcının ⭐ puanı görünüyor.

## 3. Kayıt ve güvenlik (45 sn) — Hafta 5
- **Gizli pencerede** *Kayıt Ol* sayfasını aç. Önce zayıf bir parola dene: kurallar kırmızı kalır.
- `@bingol.edu.tr` uzantılı bir e-postayla kayıt ol → hesap **doğrulanmış öğrenci** olur. Ardından giriş yap.
- Adres çubuğuna `pages/yonetim.html` yaz → **"erişim yetkiniz yok"** mesajı çıkar (sunucu 403 döndürüyor).

## 4. İlan verme (45 sn) — Hafta 6
- **＋ İlan Ver** → türü **Takas** seç, başlık yaz, bir fotoğraf ekle ve yayınla.
- Başlığı tamamen BÜYÜK HARFLE yazmayı dene → *"Başlık tamamen büyük harfle yazılamaz"* hatası çıkar (K8, öğrenci kuralı).

## 5. Mesaj ve takas teklifi (1 dk) — Hafta 7
- **Normal pencerede** öğrenci hesabıyla gir. Gizli penceredeki yeni ilana git → **Mesaj gönder** → bir mesaj yaz.
- **Takas teklif et** → kendi ilanlarından birini seç ve gönder.
- **Gizli pencerede** menüdeki 💬 ve 🔄 sayaçlarının arttığını göster. Mesaja cevap ver, ilan sayfasındaki **Gelen teklifler** bölümünden **Kabul et**'e bas → iki ilan da **Rezerve** olur.

## 6. Tamamlama ve değerlendirme (45 sn) — Hafta 8
- **Normal pencerede** 🔔 Bildirimler'de *"teklifiniz kabul edildi"* yazısını göster.
- **Teklifler → Gönderdiğim** → **✅ Takas tamamlandı** → **⭐ Değerlendir** → 5 yıldız + yorum gönder.
- **Gizli pencerede** *Hesabım* sayfasında yeni değerlendirmeyi göster.

## 7. Kapanış (15 sn)
"Kullanıcı, veritabanı ve çekirdek modüller (ilan, mesaj, takas, değerlendirme, bildirim) uçtan uca çalışıyor. Bu sürüm GitHub'da **v0.8.0** etiketiyle işaretlendi. Bir sonraki adım harici API entegrasyonu: görsel depolama ve bildirim servisi."

---

### Sorun çıkarsa
| Sorun | Çözüm |
|---|---|
| Canlı link açılmıyor / yavaş | Ücretsiz planda uyku modu var; 1 dakika bekleyip yenile |
| "Veritabanına bağlanılamadı" | Render → Logs'ta "MariaDB hazır" yazısını bekle. XAMPP'te MySQL'in açık olduğunu kontrol et |
| Eklediğim ilan kayboldu | Ücretsiz planda veritabanı her yeniden başlatmada örnek veriyle sıfırlanır (bilinen sınır) |
