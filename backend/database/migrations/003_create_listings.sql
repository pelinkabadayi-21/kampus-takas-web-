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
