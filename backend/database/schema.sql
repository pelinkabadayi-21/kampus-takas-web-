-- =====================================================================
-- Kampüs Takas - TEK DOSYA ŞEMA (phpMyAdmin "İçe Aktar" için)
-- Bu dosya database/migrations/*.sql dosyalarının birleşimidir.
-- Önerilen yol: php backend/database/migrate.php --seed
-- Örnek veri bu dosyada YOK; seed.php ile yüklenir (şifre özetleri PHP ile üretilir).
-- =====================================================================
CREATE DATABASE IF NOT EXISTS kampus_takas CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
USE kampus_takas;

-- 001: Kullanıcılar (öğrenciler)
CREATE TABLE IF NOT EXISTS users (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name      VARCHAR(100)  NOT NULL,
  email          VARCHAR(150)  NOT NULL,
  password_hash  VARCHAR(255)  NOT NULL,
  student_no     VARCHAR(20)   NULL,
  department     VARCHAR(100)  NULL,
  is_verified    TINYINT(1)    NOT NULL DEFAULT 0,
  avatar_url     VARCHAR(255)  NULL,
  created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_users_email      UNIQUE (email),
  CONSTRAINT uq_users_student_no UNIQUE (student_no),
  CONSTRAINT chk_users_email     CHECK (email LIKE '%@%')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- 002: Kategoriler
CREATE TABLE IF NOT EXISTS categories (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(60) NOT NULL,
  slug  VARCHAR(60) NOT NULL,
  CONSTRAINT uq_categories_name UNIQUE (name),
  CONSTRAINT uq_categories_slug UNIQUE (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- 003: İlanlar  (users 1-N listings, categories 1-N listings)
CREATE TABLE IF NOT EXISTS listings (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id            INT UNSIGNED  NOT NULL,
  category_id        INT UNSIGNED  NOT NULL,
  title              VARCHAR(120)  NOT NULL,
  description        TEXT          NULL,
  item_condition     ENUM('yeni','cok_iyi','iyi','orta','yipranmis') NOT NULL DEFAULT 'iyi',
  listing_type       ENUM('satis','takas','bagis') NOT NULL DEFAULT 'satis',
  price              DECIMAL(10,2) NULL,
  ai_suggested_price DECIMAL(10,2) NULL,
  meeting_point      VARCHAR(120)  NULL,
  status             ENUM('aktif','rezerve','tamamlandi','kaldirildi') NOT NULL DEFAULT 'aktif',
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_listing_user     FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
  CONSTRAINT fk_listing_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  -- Satış ilanında fiyat zorunlu ve negatif olamaz; bağış ilanında fiyat olmaz
  CONSTRAINT chk_listing_price CHECK (
       (listing_type = 'satis' AND price IS NOT NULL AND price >= 0)
    OR (listing_type = 'takas' AND (price IS NULL OR price >= 0))
    OR (listing_type = 'bagis' AND price IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- 004: İlan fotoğrafları  (listings 1-N listing_images)
CREATE TABLE IF NOT EXISTS listing_images (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id  INT UNSIGNED NOT NULL,
  image_url   VARCHAR(255) NOT NULL,
  sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_image_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- 005: Favoriler  (users N-N listings, ara tablo)
CREATE TABLE IF NOT EXISTS favorites (
  user_id     INT UNSIGNED NOT NULL,
  listing_id  INT UNSIGNED NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, listing_id),
  CONSTRAINT fk_fav_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_fav_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- 006: Mesajlar  (bir ilan üzerinden iki kullanıcı arasında)
CREATE TABLE IF NOT EXISTS messages (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id   INT UNSIGNED NOT NULL,
  sender_id    INT UNSIGNED NOT NULL,
  receiver_id  INT UNSIGNED NOT NULL,
  body         TEXT NOT NULL,
  is_read      TINYINT(1) NOT NULL DEFAULT 0,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_msg_listing  FOREIGN KEY (listing_id)  REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_sender   FOREIGN KEY (sender_id)   REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id) REFERENCES users(id)    ON DELETE CASCADE
  -- "Kendine mesaj atamaz" kuralı MessageService'te kontrol edilir
  -- (MySQL 8, ON DELETE eylemli FK sütunlarında CHECK kısıtına izin vermez)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- 007: Takas teklifleri
CREATE TABLE IF NOT EXISTS trade_offers (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id          INT UNSIGNED NOT NULL,
  offerer_id          INT UNSIGNED NOT NULL,
  offered_listing_id  INT UNSIGNED NULL,
  offered_amount      DECIMAL(10,2) NULL,
  note                VARCHAR(255) NULL,
  status              ENUM('beklemede','kabul','red','iptal') NOT NULL DEFAULT 'beklemede',
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_offer_listing FOREIGN KEY (listing_id)         REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_offer_user    FOREIGN KEY (offerer_id)         REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_offer_offered FOREIGN KEY (offered_listing_id) REFERENCES listings(id) ON DELETE SET NULL,
  -- "Teklif boş olamaz" ve "kendi ilanına teklif veremez" kuralları TradeOfferService'te
  CONSTRAINT chk_offer_amount    CHECK (offered_amount IS NULL OR offered_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- 008: Değerlendirmeler  (bir teklif için her kişi en fazla 1 yorum)
CREATE TABLE IF NOT EXISTS reviews (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trade_offer_id  INT UNSIGNED NOT NULL,
  reviewer_id     INT UNSIGNED NOT NULL,
  reviewed_id     INT UNSIGNED NOT NULL,
  rating          TINYINT UNSIGNED NOT NULL,
  comment         VARCHAR(500) NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_review_once     UNIQUE (trade_offer_id, reviewer_id),
  CONSTRAINT fk_review_offer    FOREIGN KEY (trade_offer_id) REFERENCES trade_offers(id) ON DELETE CASCADE,
  CONSTRAINT fk_review_reviewer FOREIGN KEY (reviewer_id)    REFERENCES users(id)        ON DELETE CASCADE,
  CONSTRAINT fk_review_reviewed FOREIGN KEY (reviewed_id)    REFERENCES users(id)        ON DELETE CASCADE,
  CONSTRAINT chk_review_rating  CHECK (rating BETWEEN 1 AND 5)
  -- "Kendini değerlendiremez" kuralı ReviewService'te
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- 009: Bildirimler
CREATE TABLE IF NOT EXISTS notifications (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  type        ENUM('mesaj','teklif','teklif_sonuc','degerlendirme','sistem') NOT NULL,
  content     VARCHAR(255) NOT NULL,
  link        VARCHAR(255) NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- 010: Sorgu performansı için indeksler
-- Not: Yabancı anahtar sütunlarına InnoDB zaten otomatik indeks koyar.
-- Buradakiler, sitede EN SIK çalışacak sorgulara göre seçildi.

-- "Kategorideki aktif ilanları en yeniden eskiye listele" (ana liste sorgusu)
CREATE INDEX idx_listings_cat_status_date ON listings (category_id, status, created_at);

-- "Tüm aktif ilanlar, en yeniler" (kategori seçilmeden)
CREATE INDEX idx_listings_status_date ON listings (status, created_at);

-- Fiyat aralığı filtresi ve fiyata göre sıralama
CREATE INDEX idx_listings_price ON listings (price);

-- Bir ilan üzerindeki konuşmayı tarih sırasıyla getirme
CREATE INDEX idx_messages_conversation ON messages (listing_id, sender_id, receiver_id, created_at);

-- "Okunmamış mesaj sayım"
CREATE INDEX idx_messages_unread ON messages (receiver_id, is_read);

-- Bir ilana gelen bekleyen teklifler
CREATE INDEX idx_offers_listing_status ON trade_offers (listing_id, status);

-- Bildirim zili: okunmamış bildirimler
CREATE INDEX idx_notifications_unread ON notifications (user_id, is_read, created_at);

-- Bilinçli olarak EKLENMEYENLER:
--  * listings.title  -> aramada LIKE '%kelime%' kullanılıyor; baştaki % yüzünden normal indeks işe yaramaz.
--  * is_verified gibi sadece 0/1 alan sütunlar tek başına -> seçicilik çok düşük.

-- 011: Kimlik doğrulama ve roller (Hafta 5)

-- Rol tabanlı erişim: her kullanıcı ya öğrenci ya yönetici
ALTER TABLE users ADD COLUMN role ENUM('ogrenci','yonetici') NOT NULL DEFAULT 'ogrenci';

-- Kaba kuvvet (brute force) koruması: art arda hatalı giriş sayısı ve kilit süresi
ALTER TABLE users ADD COLUMN failed_login_count TINYINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN locked_until DATETIME NULL;
ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL;

-- Oturum token'ları
-- Token'ın KENDİSİ değil, SHA-256 özeti saklanır: veritabanı sızsa bile token'lar kullanılamaz.
CREATE TABLE IF NOT EXISTS auth_tokens (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  token_hash    CHAR(64)     NOT NULL,
  expires_at    DATETIME     NOT NULL,
  last_used_at  DATETIME     NULL,
  user_agent    VARCHAR(255) NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_auth_token_hash UNIQUE (token_hash),
  CONSTRAINT fk_auth_token_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE INDEX idx_auth_tokens_expires ON auth_tokens (expires_at);

-- 012: Mesajlaşma ve takas teklifi iyileştirmeleri (Hafta 7)

-- Teklife ne zaman yanıt verildiği (kabul/red/iptal anı)
ALTER TABLE trade_offers ADD COLUMN responded_at DATETIME NULL;

-- "Gönderdiğim teklifler" ve "bu kişinin bekleyen teklifi var mı?" sorguları için
CREATE INDEX idx_offers_offerer_status ON trade_offers (offerer_id, status);

-- Bir ilanın karşılığında önerildiği teklifleri bulmak için (ilan rezerve olunca o teklifler iptal edilir)
CREATE INDEX idx_offers_offered_listing ON trade_offers (offered_listing_id, status);

-- Gelen kutusu: kullanıcının gönderdiği mesajlar (aldıkları için idx_messages_unread zaten var)
CREATE INDEX idx_messages_sender_date ON messages (sender_id, created_at);

-- Başlangıç kategorileri (seed.php de aynılarını ekler)
INSERT INTO categories (name, slug) VALUES
  ('Ders Kitabı','ders-kitabi'), ('Roman & Kitap','roman-kitap'), ('Elektronik','elektronik'),
  ('Kırtasiye','kirtasiye'), ('Yurt & Ev Eşyası','yurt-ev'), ('Giyim','giyim'),
  ('Spor & Hobi','spor-hobi'), ('Diğer','diger');
