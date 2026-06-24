CREATE TABLE IF NOT EXISTS invite_places (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  invite_id              BIGINT UNSIGNED NOT NULL,
  meal_key               VARCHAR(32)  NOT NULL,
  place_name             VARCHAR(191) NOT NULL,
  place_url              VARCHAR(1024) NULL,
  place_resolved_name    VARCHAR(191) NULL,
  place_resolved_address VARCHAR(512) NULL,
  place_clean_url        VARCHAR(1024) NULL,
  UNIQUE KEY uq_place_invite_meal (invite_id, meal_key),
  CONSTRAINT fk_place_invite FOREIGN KEY (invite_id) REFERENCES invites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
