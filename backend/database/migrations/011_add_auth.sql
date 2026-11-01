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
