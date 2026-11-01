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
