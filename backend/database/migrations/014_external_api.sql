-- 014: Harici API entegrasyonu (Hafta 9)

-- E2: Kullanıcı önemli bildirimlerin e-postayla gelmesini kapatabilir (varsayılan: açık)
ALTER TABLE users ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1;

-- Harici API çağrılarının günlüğü (Cloudinary, Brevo).
-- API anahtarı, e-posta adresi ve e-posta içeriği bu tabloya YAZILMAZ; sadece sonuç bilgisi tutulur.
CREATE TABLE IF NOT EXISTS external_api_logs (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service      VARCHAR(30)       NOT NULL,              -- cloudinary, brevo
  action       VARCHAR(30)       NOT NULL,              -- upload, destroy, email
  user_id      INT UNSIGNED      NULL,                  -- işlemi yapan / e-postayı alan kullanıcı
  status_code  SMALLINT UNSIGNED NULL,                  -- HTTP durum kodu; NULL = ağ hatası veya zaman aşımı
  success      TINYINT(1)        NOT NULL DEFAULT 0,
  attempts     TINYINT UNSIGNED  NOT NULL DEFAULT 1,    -- yeniden denemeler dahil; 0 = hiç gönderilmedi (E5 sınırı)
  duration_ms  INT UNSIGNED      NOT NULL DEFAULT 0,
  error        VARCHAR(255)      NULL,
  created_at   DATETIME          NOT NULL,
  CONSTRAINT fk_api_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Yönetim paneli özeti (servis + zaman) ve E5 saatlik e-posta sayacı (kullanıcı + işlem + zaman) için
CREATE INDEX idx_api_logs_service_time ON external_api_logs (service, created_at);
CREATE INDEX idx_api_logs_user_action  ON external_api_logs (user_id, action, created_at);
