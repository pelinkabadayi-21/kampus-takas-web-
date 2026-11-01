-- 004: İlan fotoğrafları  (listings 1-N listing_images)
CREATE TABLE IF NOT EXISTS listing_images (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id  INT UNSIGNED NOT NULL,
  image_url   VARCHAR(255) NOT NULL,
  sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_image_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
