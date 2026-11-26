-- 015: Yapay zekâ kategori ve fiyat önerisi günlüğü (Hafta 10)
-- Her öneri isteği kaydedilir: hangi istem sürümü, hangi model, ham çıktı, doğrulamadan sonra
-- ne önerildi, hangi düzeltmeler yapıldı ve kullanıcı öneriyi kabul etti mi.
-- Kişisel veri yazılmaz: açıklamadaki e-posta, telefon ve bağlantılar kaydedilmeden önce maskelenir.
CREATE TABLE IF NOT EXISTS ai_suggestions (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id            INT UNSIGNED      NULL,
  listing_id         INT UNSIGNED      NULL,              -- öneri kullanılarak açılan ilan
  source             VARCHAR(10)       NOT NULL,          -- ai = yapay zekâ, kural = kural tabanlı yedek
  model              VARCHAR(60)       NULL,
  prompt_version     VARCHAR(10)       NOT NULL,
  input_title        VARCHAR(120)      NOT NULL,
  input_summary      VARCHAR(500)      NULL,              -- maskelenmiş açıklamanın ilk 500 karakteri
  item_condition     VARCHAR(20)       NULL,
  listing_type       VARCHAR(10)       NULL,
  had_image          TINYINT(1)        NOT NULL DEFAULT 0,
  raw_output         TEXT              NULL,              -- modelin ham cevabı (doğrulamadan önce)
  category_slug      VARCHAR(60)       NULL,
  price_min          DECIMAL(10,2)     NULL,
  price              DECIMAL(10,2)     NULL,
  price_max          DECIMAL(10,2)     NULL,
  confidence         DECIMAL(3,2)      NULL,
  reason             VARCHAR(300)      NULL,
  warnings           VARCHAR(1000)     NULL,              -- doğrulamada yapılan düzeltmeler, JSON dizisi
  error              VARCHAR(255)      NULL,              -- yapay zekâ çağrısı başarısızsa nedeni
  accepted_category  TINYINT(1)        NULL,              -- kullanıcı önerilen kategoriyi seçti mi
  accepted_price     TINYINT(1)        NULL,              -- kullanıcı önerilen fiyatı (yaklaşık) kullandı mı
  duration_ms        INT UNSIGNED      NOT NULL DEFAULT 0,
  created_at         DATETIME          NOT NULL,
  CONSTRAINT fk_ai_sugg_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL,
  CONSTRAINT fk_ai_sugg_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- A6 saatlik sınır sayacı (kullanıcı + zaman) ve yönetim paneli listesi (zaman) için
CREATE INDEX idx_ai_sugg_user_time ON ai_suggestions (user_id, created_at);
CREATE INDEX idx_ai_sugg_time      ON ai_suggestions (created_at);
