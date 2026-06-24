ALTER TABLE users
  ADD COLUMN avatar_key           VARCHAR(32)  NULL,
  ADD COLUMN pronouns             VARCHAR(32)  NULL,
  ADD COLUMN bio                  VARCHAR(280) NULL,
  ADD COLUMN contact              VARCHAR(191) NULL,
  ADD COLUMN profile_completed_at DATETIME     NULL;
