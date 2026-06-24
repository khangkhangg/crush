CREATE TABLE IF NOT EXISTS themes (
  `key`     VARCHAR(32)  NOT NULL PRIMARY KEY,
  name      VARCHAR(64)  NOT NULL,
  is_active TINYINT(1)   NOT NULL DEFAULT 1,
  weight    INT          NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO themes (`key`, name, is_active, weight) VALUES
  ('love-letter', 'Love Letter', 1, 1),
  ('bubblegum',   'Bubblegum Cutecore', 1, 1),
  ('midnight',    'Midnight Crush', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS ab_events (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  invite_id  BIGINT UNSIGNED NOT NULL,
  theme_key  VARCHAR(32) NOT NULL,
  event      VARCHAR(16) NOT NULL,
  created_at DATETIME    NOT NULL,
  KEY idx_ab_invite (invite_id),
  KEY idx_ab_theme_event (theme_key, event)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
