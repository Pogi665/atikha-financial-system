-- Phase 2 journal ownership and durable submission protection.
-- Apply once after 015, under maintenance, after a verified backup.
-- Additive only: existing journal data is retained. DDL implicitly commits.
-- Nullable columns preserve existing records; the posting service supplies all three.
USE `atikha_finance`;

ALTER TABLE `journal_entries`
    ADD COLUMN `submission_key` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ADD COLUMN `submission_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ADD COLUMN `posted_by_user_id` INT UNSIGNED NULL,
    ADD UNIQUE KEY `uq_journal_entries_submission_key` (`submission_key`),
    ADD CONSTRAINT `fk_journal_entries_posted_identity`
        FOREIGN KEY (`posted_by_user_id`) REFERENCES `user_identities` (`UserID`)
        ON DELETE RESTRICT ON UPDATE RESTRICT;

SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journal_entries'
  AND COLUMN_NAME IN ('submission_key', 'submission_hash', 'posted_by_user_id');
