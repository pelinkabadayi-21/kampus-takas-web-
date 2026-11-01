-- =====================================================================
-- Kampüs Takas - Veritabanı Şeması (Hafta 3: Veri Modeli)
-- MySQL 8 / MariaDB 10.4+ (XAMPP)  |  Karakter seti: utf8mb4 (Türkçe)
-- Normalizasyon: 3NF  (tekrarlayan veri yok, her tablo tek bir varlık)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS kampus_takas
  CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
USE kampus_takas;

-- ---------------------------------------------------------------------
-- 1) KULLANICI  (öğrenci)
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name      VARCHAR(100)  NOT NULL,
  email          VARCHAR(150)  NOT NULL UNIQUE,          -- okul e-postası
  password_hash  VARCHAR(255)  NOT NULL,
  student_no     VARCHAR(20)   NULL UNIQUE,
  department     VARCHAR(100)  NULL,
  is_verified    TINYINT(1)    NOT NULL DEFAULT 0,       -- öğrenci doğrulama
  avatar_url     VARCHAR(255)  NULL,
  created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2) KATEGORİ
-- ---------------------------------------------------------------------
CREATE TABLE categories (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(60) NOT NULL UNIQUE,
  slug  VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3) İLAN   (1 kullanıcı -> N ilan, 1 kategori -> N ilan)
-- ---------------------------------------------------------------------
CREATE TABLE listings (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED  NOT NULL,
  category_id     INT UNSIGNED  NOT NULL,
  title           VARCHAR(120)  NOT NULL,
  description     TEXT          NULL,
  item_condition  ENUM('yeni','cok_iyi','iyi','orta','yipranmis') NOT NULL DEFAULT 'iyi',
  listing_type    ENUM('satis','takas','bagis') NOT NULL DEFAULT 'satis',  -- bağış modu
  price           DECIMAL(10,2) NULL,                     -- takas/bağışta NULL
  ai_suggested_price DECIMAL(10,2) NULL,                  -- yapay zekâ önerisi
  meeting_point   VARCHAR(120)  NULL,                     -- konum bazlı buluşma
  status          ENUM('aktif','rezerve','tamamlandi','kaldirildi') NOT NULL DEFAULT 'aktif',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_listing_user     FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
  CONSTRAINT fk_listing_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  INDEX idx_listing_status (status),
  FULLTEXT INDEX ft_listing_search (title, description)  -- arama modülü
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4) İLAN FOTOĞRAFI   (1 ilan -> N fotoğraf)
-- ---------------------------------------------------------------------
CREATE TABLE listing_images (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id  INT UNSIGNED NOT NULL,
  image_url   VARCHAR(255) NOT NULL,
  sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_image_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5) FAVORİ   (N kullanıcı <-> N ilan : ara tablo örneği)
-- ---------------------------------------------------------------------
CREATE TABLE favorites (
  user_id     INT UNSIGNED NOT NULL,
  listing_id  INT UNSIGNED NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, listing_id),                      -- birleşik anahtar
  CONSTRAINT fk_fav_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_fav_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6) MESAJ   (bir ilan üzerinden iki kullanıcı arasında)
-- ---------------------------------------------------------------------
CREATE TABLE messages (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id   INT UNSIGNED NOT NULL,
  sender_id    INT UNSIGNED NOT NULL,
  receiver_id  INT UNSIGNED NOT NULL,
  body         TEXT NOT NULL,
  is_read      TINYINT(1) NOT NULL DEFAULT 0,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_msg_listing  FOREIGN KEY (listing_id)  REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_sender   FOREIGN KEY (sender_id)   REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id) REFERENCES users(id)    ON DELETE CASCADE,
  INDEX idx_msg_conversation (listing_id, sender_id, receiver_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7) TAKAS TEKLİFİ
--    Teklif veren, hedef ilana karşılık kendi bir ilanını ve/veya para önerir.
-- ---------------------------------------------------------------------
CREATE TABLE trade_offers (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id          INT UNSIGNED NOT NULL,              -- istenen ilan
  offerer_id          INT UNSIGNED NOT NULL,              -- teklifi yapan
  offered_listing_id  INT UNSIGNED NULL,                  -- karşılığında verilen ilan
  offered_amount      DECIMAL(10,2) NULL,                 -- ek para teklifi
  note                VARCHAR(255) NULL,
  status              ENUM('beklemede','kabul','red','iptal') NOT NULL DEFAULT 'beklemede',
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_offer_listing  FOREIGN KEY (listing_id)         REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_offer_user     FOREIGN KEY (offerer_id)         REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_offer_offered  FOREIGN KEY (offered_listing_id) REFERENCES listings(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8) DEĞERLENDİRME   (tamamlanan takas/satış sonrası, teklif başına 1 yorum/kişi)
-- ---------------------------------------------------------------------
CREATE TABLE reviews (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trade_offer_id  INT UNSIGNED NOT NULL,
  reviewer_id     INT UNSIGNED NOT NULL,
  reviewed_id     INT UNSIGNED NOT NULL,
  rating          TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment         VARCHAR(500) NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review_once (trade_offer_id, reviewer_id),
  CONSTRAINT fk_review_offer    FOREIGN KEY (trade_offer_id) REFERENCES trade_offers(id) ON DELETE CASCADE,
  CONSTRAINT fk_review_reviewer FOREIGN KEY (reviewer_id)    REFERENCES users(id)        ON DELETE CASCADE,
  CONSTRAINT fk_review_reviewed FOREIGN KEY (reviewed_id)    REFERENCES users(id)        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9) BİLDİRİM
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  type        ENUM('mesaj','teklif','teklif_sonuc','degerlendirme','sistem') NOT NULL,
  content     VARCHAR(255) NOT NULL,
  link        VARCHAR(255) NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notif_unread (user_id, is_read)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Başlangıç verisi: kategoriler
-- ---------------------------------------------------------------------
INSERT INTO categories (name, slug) VALUES
  ('Ders Kitabı',        'ders-kitabi'),
  ('Roman & Kitap',      'roman-kitap'),
  ('Elektronik',         'elektronik'),
  ('Kırtasiye',          'kirtasiye'),
  ('Yurt & Ev Eşyası',   'yurt-ev'),
  ('Giyim',              'giyim'),
  ('Spor & Hobi',        'spor-hobi'),
  ('Diğer',              'diger');
