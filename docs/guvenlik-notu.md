# Güvenlik Notu – Kimlik Doğrulama ve Yetkilendirme

> Hafta 5 teslim çıktısı. Sistemin kullanıcı hesaplarını ve yetkileri nasıl koruduğunu özetler.

## 1. Parolalar
- Parolalar **asla düz metin saklanmaz**. `password_hash()` ile **bcrypt** özeti alınır (`users.password_hash`).
- bcrypt her parola için otomatik ve rastgele bir **salt (tuz)** üretir ve özetin içine yazar. Bu yüzden aynı parolaya sahip iki kullanıcının özetleri birbirinden farklıdır ve hazır özet tabloları (rainbow table) işe yaramaz.
- Doğrulama `password_verify()` ile yapılır; bu fonksiyon karşılaştırmayı sabit sürede yapar.
- `password_needs_rehash()`: İleride bcrypt maliyeti artırılırsa, eski özetler kullanıcı giriş yaptığında otomatik olarak güncellenir.
- **Parola kuralı:** en az 8, en fazla 72 karakter (72 bcrypt'in sınırıdır), en az bir harf ve bir rakam içermeli, e-posta adıyla aynı olmamalı. Kurallar hem arayüzde hem sunucuda kontrol edilir; asıl kontrol sunucudadır.
- API yanıtlarında `password_hash` sütunu hiçbir zaman yer almaz (`UserRepository::PUBLIC_COLS`).

## 2. Oturum (token) yönetimi
- Girişte `random_bytes(32)` ile 256 bitlik, tahmin edilemez bir token üretilir.
- Veritabanında token'ın kendisi değil, **SHA-256 özeti** saklanır (`auth_tokens.token_hash`). Veritabanı sızsa bile buradaki değerlerle giriş yapılamaz.
- Token'ın **son kullanma süresi** 8 saattir (`config.php` → `token_ttl_hours`). Süresi dolan token reddedilir; süresi dolmuş kayıtlar her girişte temizlenir.
- **Çıkış** yapıldığında token veritabanından silinir; yani sadece tarayıcıdaki çerez değil, sunucudaki oturum da biter.
- Token tarayıcıya **HttpOnly çerez** olarak gönderilir:
  - `HttpOnly`: JavaScript çerezi okuyamaz (`document.cookie` boş döner). Sitede bir XSS açığı olsa bile token çalınamaz.
  - `SameSite=Lax`: Başka bir siteden gönderilen POST/PUT/DELETE isteklerine çerez eklenmez (CSRF'e karşı koruma).
  - `Secure`: Site HTTPS'e taşındığında `cookie_secure => true` yapılmalıdır.
- API'yi Postman gibi araçlarla test edebilmek için `Authorization: Bearer <token>` başlığı da kabul edilir.

## 3. Başarısız giriş ve kaba kuvvet (brute force) koruması
- Hata mesajı bilerek geneldir: **"E-posta veya parola hatalı"**. Saldırgan, hangi e-postaların kayıtlı olduğunu bu mesajdan öğrenemez.
- E-posta kayıtlı olmasa bile `password_verify()` sahte bir özetle yine çalıştırılır. Böylece yanıt süresine bakarak da hesabın var olup olmadığı anlaşılamaz (zamanlama saldırısı).
- Aynı hesaba **art arda 5 hatalı deneme** yapılırsa hesap **15 dakika kilitlenir** (HTTP 429). Kilit süresi dolunca sayaç sıfırlanır.
- Başarılı girişte hatalı deneme sayacı sıfırlanır ve `last_login_at` güncellenir.

## 4. Rol tabanlı erişim (öğrenci / yönetici)
- `users.role` sütunu `ogrenci` veya `yonetici` değerini alır.
- Kayıt olan **herkes öğrencidir**. İstekte `"role": "yonetici"` gönderilse bile sunucu bunu dikkate almaz. Rolü sadece bir yönetici, yönetim panelinden değiştirebilir.
- Yönetici kendi rolünü değiştiremez; böylece sistem yöneticisiz kalamaz.
- Yetki kontrolleri **sunucuda** yapılır (`Auth::requireUser()`, `Auth::requireRole('yonetici')`). Arayüzde "Yönetim" bağlantısını gizlemek sadece görünümdür; adres elle yazılsa bile sunucu **403** döner.

| Uç | Kimler erişebilir |
|---|---|
| `GET /api/listings`, `GET /api/listings/{id}` | Herkes |
| `POST /api/listings` | Giriş yapmış kullanıcı (ilan sahibi oturumdan alınır, istekteki `user_id` yok sayılır) |
| `PUT/DELETE /api/listings/{id}` | İlanın sahibi **veya** yönetici |
| `GET /api/auth/me` | Giriş yapmış kullanıcı |
| `GET /api/admin/users`, `PUT /api/admin/users/{id}/role`, `/verify` | Sadece yönetici |

**HTTP durum kodları:** `401` giriş gerekli · `403` giriş var ama yetki yok · `422` form hatası · `429` çok fazla deneme

## 5. Diğer önlemler
- **SQL injection:** Tüm sorgular prepared statement (`?` yer tutucu) ile çalışır.
- **XSS:** Kullanıcıdan gelen tüm metinler, sayfaya yazılmadan önce `esc()` ile HTML'e güvenli hale getirilir.
- **Açık yönlendirme:** Giriş sonrası `?next=` parametresine sadece site içi adresler (`/` ile başlayan) kabul edilir.
- **Güvenlik başlıkları:** `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` (sitenin başka bir sitede çerçeve içinde açılmasını, yani clickjacking'i engeller), `Referrer-Policy: same-origin`.
- **Gizli ayarlar:** `config.php` dosyası `.gitignore`'da; veritabanı şifresi GitHub'a yüklenmez.

## 6. OWASP Top 10 ile eşleştirme

| OWASP riski | Projedeki önlem |
|---|---|
| A01 Erişim kontrolü hataları | Sunucu tarafı rol ve sahiplik kontrolü, 401/403 |
| A02 Kriptografik hatalar | bcrypt ile parola, SHA-256 ile token özeti, `random_bytes` |
| A03 Enjeksiyon | Prepared statement, `esc()` |
| A04 Güvensiz tasarım | Kayıtta rol seçilemiyor, yönetici kendi rolünü değiştiremiyor |
| A05 Güvenlik yapılandırması | Güvenlik başlıkları, `debug` yayında kapatılacak, `config.php` gizli |
| A07 Kimlik doğrulama hataları | Parola kuralı, hesap kilidi, genel hata mesajı, token süresi |

## 7. Bilinen sınırlar / ileride yapılacaklar
- Şu an site HTTP üzerinde çalışıyor. Yayına alınırken HTTPS açılmalı ve `cookie_secure => true` yapılmalı.
- "Parolamı unuttum" ve e-posta ile doğrulama henüz yok; e-posta gönderimi gerektirdiği için ileride eklenebilir. Öğrenci doğrulaması şimdilik okul e-postası alan adına ve yönetici onayına dayanıyor.
- Hesap kilidi e-posta bazlıdır. Çok sayıda farklı hesabı deneyen bir saldırgana karşı IP bazlı sınırlama da eklenebilir.
