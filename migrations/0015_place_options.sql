ALTER TABLE invite_places ADD INDEX idx_place_invite (invite_id);
ALTER TABLE invite_places DROP INDEX uq_place_invite_meal;
ALTER TABLE invite_places ADD COLUMN sort INT NOT NULL DEFAULT 0;
ALTER TABLE responses ADD COLUMN chosen_place_id BIGINT UNSIGNED NULL;
