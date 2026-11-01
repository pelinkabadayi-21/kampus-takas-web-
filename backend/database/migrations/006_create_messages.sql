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
