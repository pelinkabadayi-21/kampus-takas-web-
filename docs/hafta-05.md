# Hafta 5 – Kullanıcı Yönetimi ve Kimlik Doğrulama

## Haftanın amacı
Kayıt, giriş ve rol tabanlı erişim ile kullanıcı yönetimini kurmak.

## Bu hafta eklenen / değişen dosyalar

| Katman | Dosya | Ne yapar |
|---|---|---|
| **Veritabanı** | `migrations/011_add_auth.sql` | `users.role`, hatalı giriş sayacı, kilit süresi; `auth_tokens` tablosu |
| | `seeds/seed.php` | Yönetici hesabı eklendi |
| **Veri erişim** | `Repositories/AuthTokenRepository.php` | Token oluşturma, doğrulama, silme |
| | `Repositories/UserRepository.php` | Rol, giriş kaydı, kilit, kullanıcı listesi |
| **İş kuralları** | `Services/AuthService.php` | Kayıt doğrulama, parola hash, giriş, kilit, token üretimi |
| | `Services/ListingService.php` | Sahiplik kontrolü: başkasının ilanını sadece yönetici değiştirebilir |
| **Yetkilendirme** | `Core/Auth.php` | Token'dan kullanıcıyı bulur; `requireUser()`, `requireRole()` |
| **API** | `Controllers/AuthController.php` | `/api/auth/register`, `login`, `logout`, `me` |
| | `Controllers/AdminController.php` | `/api/admin/users` (sadece yönetici) |
| | `public/.htaccess` | `Authorization` başlığını PHP'ye iletir |
| **Test** | `tests/auth_test.php` | Gerçek MySQL üzerinde kimlik doğrulama testi |
| **Frontend** | `pages/giris.html`, `kayit.html` | Giriş ve kayıt formları, alan bazlı hata mesajları, parola kuralı göstergesi |
| | `pages/hesabim.html` | Korumalı sayfa: giriş yoksa giriş sayfasına yönlendirir |
| | `pages/yonetim.html` | Yönetim paneli: kullanıcı listesi, rol ve doğrulama değiştirme |
| | `js/auth.js` | Giriş durumuna göre değişen menü, çıkış |
| **Doküman** | [`guvenlik-notu.md`](guvenlik-notu.md) | Kısa güvenlik notu (teslim çıktısı) |

## Teslim edilen çıktılar
| Çıktı | Nerede |
|---|---|
| Çalışan kayıt/giriş | `pages/kayit.html`, `pages/giris.html` + `/api/auth/*` |
| Rol tabanlı erişim | `pages/yonetim.html` + `/api/admin/*`, ilan düzenleme/silme sahiplik kontrolü |
| Kısa güvenlik notu | [`docs/guvenlik-notu.md`](guvenlik-notu.md) |

## Nasıl denenir (XAMPP)
Önce veritabanını güncelle. Bu komut yeni migration'ı (011) çalıştırır ve örnek veriyi yeniden yükler:
```bash
php backend/database/migrate.php --fresh --seed
php backend/tests/auth_test.php
```

| Hesap | E-posta | Parola |
|---|---|---|
| Öğrenci | `deniz.soylu1@ogrenci.example.com` | `Test1234!` |
| Yönetici | `yonetici@ogrenci.example.com` | `Yonetici1234!` |

**Gösterilecek ilerleme (sırayla):**
1. **Kayıt Ol** sayfasında zayıf parola dene → kurallar kırmızı kalır. Kayıtlı bir e-postayla dene → "Bu e-posta ile kayıtlı bir hesap var" mesajı çıkar.
2. `@bingol.edu.tr` adresiyle kayıt ol → hesap otomatik "Doğrulanmış öğrenci" olur.
3. Giriş yapmadan `pages/hesabim.html` adresine git → giriş sayfasına yönlendirilirsin.
4. Öğrenci olarak giriş yap → menüde **Yönetim** bağlantısı görünmez. Adresi elle yazıp `yonetim.html` sayfasını açarsan "erişim yetkiniz yok" (403) mesajı çıkar.
5. Yönetici olarak giriş yap → Yönetim panelinde kullanıcıların rolünü ve doğrulama durumunu değiştir.
6. phpMyAdmin'de `users` tablosuna bak → `password_hash` sütununda `$2y$10$…` ile başlayan özetler var, düz parola yok.

## Test sonucu
Geliştirme sırasında şu testler çalıştırıldı ve hepsi geçti:
- 22 birim testi: parola hash, token, süre aşımı, çıkış, kilit, sahiplik.
- HTTP testleri: girişsiz istek 401, öğrenci yönetim ucuna 403, HttpOnly çerez, Bearer token, yöneticinin kendi rolünü değiştirememesi.
- Tarayıcı akış testi: kayıt → giriş → korumalı sayfa → çıkış. `document.cookie` boş döndü, yani token JavaScript'ten okunamıyor.
- Önceki haftanın 22 CRUD testi de hâlâ geçiyor.

## Yapay zekâ kullanımı
Yapay zekâ; güvenli kimlik doğrulama desenlerini (bcrypt, HttpOnly çerez, token özetleme) ve yaygın açıkları (kullanıcı adı sızdırma, zamanlama saldırısı, açık yönlendirme, arayüzde yetki kontrolü) araştırmak için kullanıldı. JWT ile veritabanı token'ı karşılaştırıldı; çıkışta token'ın sunucuda gerçekten iptal edilebilmesi için veritabanı token'ı seçildi.

---

## Öğrencinin kendi geliştirdiği bölümler
- **Parola hash ve doğrulama akışı:**
  1. *Kayıt:* Parola kurallara uyuyor mu diye kontrol ediliyor (en az 8 karakter, harf ve rakam). Uyuyorsa `password_hash()` ile bcrypt özeti alınıyor ve veritabanına parolanın kendisi değil bu özet yazılıyor. bcrypt her parolaya rastgele bir tuz (salt) ekliyor.
  2. *Giriş:* E-postayla kullanıcı bulunuyor, hesap kilitli mi kontrol ediliyor. Sonra `password_verify()` yazılan parolayı aynı yöntemle özetleyip kayıttaki özetle karşılaştırıyor.
  3. *Başarısızsa:* "E-posta veya parola hatalı" mesajı dönüyor ve hata sayacı artıyor; 5 hatada hesap 15 dakika kilitleniyor.
  4. *Başarılıysa:* Rastgele bir token üretiliyor. Veritabanına token'ın SHA-256 özeti yazılıyor, token'ın kendisi ise HttpOnly çerezle tarayıcıya gönderiliyor. Sonraki isteklerde bu token'ın özeti veritabanında aranıyor.
- **Kendi eklediğim rol kontrollü uç — `GET /api/admin/stats`:** Yönetici paneli için toplam kullanıcı, doğrulanmış kullanıcı, yönetici, yayındaki ilan ve bekleyen teklif sayılarını döndürüyor. Fonksiyonun ilk satırı `Auth::requireRole('yonetici');`. Giriş yapılmadan istek atılırsa **401**, öğrenci hesabıyla atılırsa **403**, yönetici hesabıyla atılırsa **200** dönüyor (üçü de denendi). Dosyalar: `AdminController::stats()`, `UserRepository::stats()`, `index.php` içindeki rota.

---

## Sözlü sınav hazırlık notları

**1) Parolayı neden hash'liyoruz, tuz (salt) nedir?**
Veritabanı çalınırsa parolalar okunamasın diye. Hash tek yönlüdür: özetten parolaya geri dönülemez. Girişte kullanıcının yazdığı parola aynı yöntemle özetlenip karşılaştırılır. **Salt**, her parolaya eklenen rastgele bir değerdir. Bu sayede aynı parolayı kullanan iki kişinin özeti farklı olur ve önceden hesaplanmış "parola → özet" tabloları (rainbow table) işe yaramaz. bcrypt salt'ı kendisi üretir ve özetin içine yazar (`$2y$10$` + 22 karakter salt + özet). Ayrıca bcrypt bilerek yavaş çalışır; bu da deneme-yanılma saldırısını çok pahalı hale getirir.

**2) Oturum ve token farkı nedir?**
- **Oturum (session):** Kullanıcı bilgisi sunucuda tutulur; tarayıcıda sadece bir oturum kimliği olur. Sunucu istediği an oturumu bitirebilir.
- **Token (ör. JWT):** Kullanıcı bilgisi imzalı olarak token'ın içindedir. Sunucu bir şey saklamaz, sadece imzayı doğrular. Ölçeklenmesi kolaydır ama süresi dolmadan iptal etmek zordur.
- **Bu projede:** Rastgele bir token üretiyoruz, özetini veritabanında saklıyoruz ve HttpOnly çerezle gönderiyoruz. Bu, ikisinin karışımıdır: API gibi token ile çalışır, oturum gibi çıkışta sunucudan silinebilir.

**3) Rol tabanlı erişimi nasıl uyguladın?**
`users` tablosunda `role` sütunu var (`ogrenci` / `yonetici`). Korumalı her uçta önce `Auth::requireRole('yonetici')` çağrılıyor. Giriş yoksa 401, rol yetmiyorsa 403 dönüyor. İlan düzenleme ve silmede ayrıca **sahiplik kontrolü** var: ilanı sadece sahibi ya da yönetici değiştirebilir. Kayıt olan herkes öğrencidir; rolü sadece yönetici değiştirebilir.

**4) Başarısız giriş denemesinde ne oluyor?**
Sunucu 401 ve genel bir mesaj döner: "E-posta veya parola hatalı". Hangisinin yanlış olduğu söylenmez, çünkü söylenirse saldırgan hangi e-postaların kayıtlı olduğunu öğrenir. Hatalı deneme sayacı bir artar. 5 hatalı denemeden sonra hesap 15 dakika kilitlenir (429). Bu süre içinde doğru parola girilse bile giriş yapılamaz.

**5) Yetkisiz kullanıcı korumalı sayfaya giderse ne olur?**
- **Giriş yapmamışsa:** Örneğin `hesabim.html` açıldığında sayfa `/api/auth/me` isteği atar, sunucu 401 döner ve kullanıcı `giris.html?next=...` adresine yönlendirilir. Giriş yapınca geldiği sayfaya geri döner.
- **Giriş yapmış ama yetkisi yoksa:** Örneğin öğrenci `yonetim.html` adresini açtığında sunucu 403 döner, sayfada "erişim yetkiniz yok" yazar ve hiçbir veri gelmez. Kontrol sunucuda olduğu için öğrenci API'ye doğrudan istek atsa bile veriye ulaşamaz.

## Teorik kavramlar (kısa)
- **Kimlik doğrulama (authentication):** "Sen kimsin?" → e-posta + parola ile kimliğin kanıtlanması.
- **Yetkilendirme (authorization):** "Bunu yapmaya iznin var mı?" → rol ve sahiplik kontrolü.
- **Hash ve salt:** Tek yönlü özet ve ona eklenen rastgele değer (bkz. soru 1).
- **JWT / oturum:** Bkz. soru 2.
- **OWASP Top 10:** Web uygulamalarındaki en yaygın 10 güvenlik riski. Projedeki karşılıkları [güvenlik notunda](guvenlik-notu.md#6-owasp-top-10-ile-eşleştirme).

## Kaçınılan sık hatalar
- **Parolayı düz metin saklamak** → bcrypt ile özetleniyor.
- **Yetkilendirmeyi yalnızca arayüzde yapmak** → tüm kontroller sunucuda; arayüz sadece bağlantıyı gizliyor.
- **Oturum süresini yönetmemek** → token 8 saat sonra geçersiz oluyor, çıkışta sunucudan siliniyor.
