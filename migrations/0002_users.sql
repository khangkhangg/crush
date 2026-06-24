CREATE TABLE IF NOT EXISTS users (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(191) NOT NULL,
  name          VARCHAR(191) NULL,
  auth_provider VARCHAR(16)  NOT NULL,
  google_id     VARCHAR(64)  NULL,
  avatar_url    VARCHAR(512) NULL,
  is_admin      TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_google (google_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS magic_tokens (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64)     NOT NULL,
  expires_at DATETIME     NOT NULL,
  used_at    DATETIME     NULL,
  UNIQUE KEY uq_magic_hash (token_hash),
  KEY idx_magic_user (user_id),
  CONSTRAINT fk_magic_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
