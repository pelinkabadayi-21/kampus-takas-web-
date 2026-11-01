# 📚 Kampüs Takas

Kampüs içi ikinci el kitap ve eşya **ilan, takas ve mesajlaşma** platformu.

> Bilgisayar Uygulamaları I – Proje Tabanlı Ders · **P20 – Kampüs İkinci El Kitap ve Eşya Takas** · Zorluk: Orta

## Problem
Öğrenciler ders kitaplarını ve eşyalarını değerinde alıp satamıyor, çoğu çöpe gidiyor. Bu platform dairesel ekonomiye katkı sağlayarak hem tasarruf hem israfın azalmasını hedefler.

## Modüller
| Modül | Durum |
|---|---|
| Proje iskeleti, mimari, veri modeli | ✅ Hafta 3 |
| İlan oluşturma | ⏳ |
| Kategori / arama | ⏳ |
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
3. `http://localhost/phpmyadmin` → **İçe aktar** → `backend/database/schema.sql` dosyasını seç.
4. `backend/config/config.example.php` dosyasını kopyalayıp adını `config.php` yap, gerekirse şifreyi düzenle.
5. Test:
   - API: http://localhost/kampus-takas-web-/backend/public/api/health
   - Site: http://localhost/kampus-takas-web-/frontend/

Ana sayfada **"API çalışıyor"** ve **"Veritabanı bağlı"** yazıyorsa kurulum tamamdır.

## API Uç Noktaları
| Yöntem | Adres | Açıklama |
|---|---|---|
| GET | `/api/health` | API ve veritabanı durumu |
| GET | `/api/categories` | Kategori listesi |

## Haftalık İlerleme
- [Hafta 3 – Kurulum, Mimari ve Veri Modeli](docs/hafta-03.md)
