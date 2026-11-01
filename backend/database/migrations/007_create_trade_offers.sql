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
