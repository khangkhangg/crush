CREATE TABLE IF NOT EXISTS invites (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  public_token       CHAR(64)     NOT NULL,
  sender_id          BIGINT UNSIGNED NOT NULL,
  crush_email        VARCHAR(191) NOT NULL,
  crush_name         VARCHAR(191) NULL,
  is_anonymous       TINYINT(1)   NOT NULL DEFAULT 0,
  reveal_on_response TINYINT(1)   NOT NULL DEFAULT 0,
  date_mode          VARCHAR(16)  NOT NULL,
  status             VARCHAR(24)  NOT NULL,
  theme_key          VARCHAR(32)  NULL,
  message            TEXT         NULL,
  created_at         DATETIME     NOT NULL,
  expires_at         DATETIME     NOT NULL,
  UNIQUE KEY uq_invite_token (public_token),
  KEY idx_invite_sender (sender_id),
  KEY idx_invite_crush (crush_email),
  CONSTRAINT fk_invite_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invite_date_options (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  invite_id BIGINT UNSIGNED NOT NULL,
  start_at  DATETIME NOT NULL,
  end_at    DATETIME NOT NULL,
  KEY idx_opt_invite (invite_id),
  CONSTRAINT fk_opt_invite FOREIGN KEY (invite_id) REFERENCES invites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS responses (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  invite_id        BIGINT UNSIGNED NOT NULL,
  chosen_start     DATETIME     NULL,
  chosen_end       DATETIME     NULL,
  meal_choice      VARCHAR(32)  NULL,
  meal_wish        TEXT         NULL,
  crush_contact    VARCHAR(191) NULL,
  pickup_raw       TEXT         NULL,
  pickup_name      VARCHAR(191) NULL,
  pickup_address   VARCHAR(512) NULL,
  pickup_clean_url VARCHAR(1024) NULL,
  created_at       DATETIME     NOT NULL,
  UNIQUE KEY uq_response_invite (invite_id),
  CONSTRAINT fk_response_invite FOREIGN KEY (invite_id) REFERENCES invites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
