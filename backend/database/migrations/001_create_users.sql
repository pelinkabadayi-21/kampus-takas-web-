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
