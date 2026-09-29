-- Apply after migrations 008-010. Back up Users and verify on a disposable database.
-- MariaDB DDL commits independently. Existing rows remain active; no rows are deleted.
ALTER TABLE Users ADD COLUMN IF NOT EXISTS Is_Active TINYINT(1) NOT NULL DEFAULT 1;
