# Hafta 3 – Kurulum, Mimari ve Veri Modeli

## Haftanın amacı
Geliştirme ortamını kurmak, proje mimarisini ve veri modelini tasarlamak, GitHub deposunu oluşturmak.

## Yapılanlar
- [x] Teknoloji yığını seçildi: **PHP 8 + MySQL (XAMPP)**, frontend **HTML/CSS/JavaScript**
- [x] Boş proje iskeleti ve katmanlı klasör yapısı (`backend/src/Controllers – Services – Repositories`)
- [x] GitHub deposu, `.gitignore` ve `README.md`
- [x] ER diyagramı: kullanıcı, ilan, kategori, mesaj, takas teklifi (+ fotoğraf, favori, değerlendirme, bildirim) → [er-diyagrami.md](er-diyagrami.md)
- [x] Mimari şema → [mimari.md](mimari.md)
- [x] Çalışan boş iskele: `GET /api/health` ve `GET /api/categories`

## Teslim edilen çıktılar
| Çıktı | Konum |
|---|---|
| GitHub deposu (README + .gitignore) | depo kökü |
| ER diyagramı | `docs/er-diyagrami.md`, `backend/database/schema.sql` |
| Mimari şema | `docs/mimari.md` |

## Yapay zekâ kullanımı
Yapay zekâ; katmanlı mimari ile MVC alternatiflerini karşılaştırmak ve ER diyagramı için varlık/ilişki önerisi almak amacıyla kullanıldı. Önerilen varlıklar gözden geçirildi; **favoriler** (N-N örneği) ve **bağış modu** (`listing_type`) proje ihtiyacına göre eklendi, son karar öğrenci tarafından verildi.

> ✏️ Öğrenci notu: Aşağıdaki "kendi geliştirdiğim bölümler" kısmını kendi cümlelerinle doldur.

## Öğrencinin kendi geliştirdiği bölümler
- Varlıklar ve ilişkiler: …
- Klasör/katman yapısının gerekçesi: …

---

## Sözlü sınav hazırlık notları

**1) Katmanlı mimariyi neden tercih ettin?**
Her katmanın tek sorumluluğu var. Sunum katmanı (Controller) sadece istek/yanıtla, iş katmanı (Service) kurallarla, veri katmanı (Repository) sadece SQL ile ilgilenir. Bir değişiklik tek katmanı etkiler, kod okunur ve test edilir hale gelir. Ayrıca frontend ayrı olduğu için aynı API'yi ileride mobil uygulama da kullanabilir.

**2) İlişki türlerini (1-1, 1-N, N-N) örnekle açıkla.**
- 1-1: Bir kullanıcının tek bir profil ayarı olması.
- 1-N: Bir kullanıcının çok ilanı olur, her ilanın tek sahibi vardır (`listings.user_id`).
- N-N: Bir öğrenci çok ilanı favorileyebilir, bir ilanı çok öğrenci favorileyebilir; bunun için `favorites` ara tablosu kullanılır.

**3) Bu projede hangi varlıklar birincil anahtar taşıyor ve neden?**
Tüm ana tablolar (`users`, `listings`, `categories`, `messages`, `trade_offers`, `reviews`, `notifications`) `id` birincil anahtarı taşır; her kaydı benzersiz tanımlamak ve diğer tablolardan yabancı anahtarla bağlanabilmek için. `favorites` tablosunun ayrı bir id'si yok; `(user_id, listing_id)` birleşik birincil anahtarı aynı ilanın iki kez favorilenmesini engeller.

**4) Klasör yapını hangi mantıkla kurdun?**
Backend ve frontend ayrı. Backend'de sadece `public/` dışarı açık, böylece `config` içindeki şifreler tarayıcıdan okunamaz. `src/` altında her katmanın kendi klasörü var.

**5) .gitignore neden gereklidir, neler eklenmeli?**
Depoya girmemesi gereken dosyaları dışlar: şifre içeren `config.php`/`.env`, kullanıcıların yüklediği fotoğraflar, `vendor/` ve `node_modules/` gibi bağımlılıklar, log dosyaları ve `.vscode`, `Thumbs.db` gibi kişisel/sistem dosyaları. Gizli bilgi sızmasını ve depo şişmesini önler.

## Teorik kavramlar (kısa)
- **Birincil anahtar (PK):** Kaydı benzersiz tanımlar. **Yabancı anahtar (FK):** Başka tablonun PK'sına bağlanır.
- **1NF:** Hücrede tek değer. **2NF:** Birleşik anahtarın bir kısmına bağımlılık yok. **3NF:** Anahtar olmayan sütunlar arası bağımlılık yok.
- **Sürüm kontrolü:** `git add` → `git commit` → `git push`; her değişiklik geri alınabilir şekilde kaydedilir.

## Kaçınılan sık hatalar
- Her şeyi tek dosyada toplamak → katmanlara ayrıldı.
- İlişki ve anahtarları eksik bırakmak → tüm FK'lar `schema.sql`'de tanımlı.
- Depoya gizli dosya yüklemek → `config.php` `.gitignore`'da.
