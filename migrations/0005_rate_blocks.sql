CREATE TABLE IF NOT EXISTS rate_limits (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  scope        VARCHAR(48)  NOT NULL,
  identifier   VARCHAR(191) NOT NULL,
  window_start DATETIME     NOT NULL,
  count        INT          NOT NULL DEFAULT 0,
  UNIQUE KEY uq_rate (scope, identifier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blocks (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sender_id   BIGINT UNSIGNED NOT NULL,
  crush_email VARCHAR(191) NOT NULL,
  reason      VARCHAR(191) NULL,
  created_at  DATETIME     NOT NULL,
  UNIQUE KEY uq_block (sender_id, crush_email),
  KEY idx_block_email (crush_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
