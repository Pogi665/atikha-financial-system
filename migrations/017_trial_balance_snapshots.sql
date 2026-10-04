-- Phase 3: frozen Trial Balance figures and append-only review revisions.
-- Apply once after 016, in maintenance, after backup and disposable rehearsal.
-- Additive: legacy Reports, journals, budgets and audit history are preserved.
-- Snapshot timestamps are UTC; as_of is an Asia/Manila calendar date.
USE `atikha_finance`;
CREATE TABLE `trial_balance_snapshots` (
 `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
 `report_year` SMALLINT UNSIGNED NOT NULL,
 `report_month` TINYINT UNSIGNED NOT NULL,
 `revision` INT UNSIGNED NOT NULL,
 `as_of` DATE NOT NULL,
 `accounting_basis` VARCHAR(40) NOT NULL,
 `captured_at` DATETIME NOT NULL,
 `source_fingerprint` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 `submission_key` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 `submitted_by_user_id` INT UNSIGNED NOT NULL,
 `total_debits` DECIMAL(30,2) NOT NULL,
 `total_credits` DECIMAL(30,2) NOT NULL,
 `review_status` ENUM('Requested','Reviewed') NOT NULL DEFAULT 'Requested',
 `reviewed_by_user_id` INT UNSIGNED NULL,
 `reviewed_at` DATETIME NULL,
 `review_notes` TEXT NULL,
 PRIMARY KEY (`id`),
 UNIQUE KEY `uq_tb_period_revision` (`report_year`,`report_month`,`revision`),
 UNIQUE KEY `uq_tb_submission` (`submission_key`),
 KEY `idx_tb_review_period` (`review_status`,`report_year`,`report_month`,`revision`),
 CONSTRAINT `fk_tb_submitter` FOREIGN KEY (`submitted_by_user_id`) REFERENCES `user_identities` (`UserID`) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT `fk_tb_reviewer` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `user_identities` (`UserID`) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT `chk_tb_period` CHECK (`report_month` BETWEEN 1 AND 12 AND `revision`>0),
 CONSTRAINT `chk_tb_totals` CHECK (`total_debits`>=0 AND `total_debits`=`total_credits`),
 CONSTRAINT `chk_tb_review` CHECK ((`review_status`='Requested' AND `reviewed_by_user_id` IS NULL AND `reviewed_at` IS NULL)
  OR (`review_status`='Reviewed' AND `reviewed_by_user_id` IS NOT NULL AND `reviewed_at` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `trial_balance_snapshot_lines` (
 `snapshot_id` INT UNSIGNED NOT NULL,
 `account_id` INT UNSIGNED NOT NULL,
 `account_code` VARCHAR(30) NULL,
 `account_name` VARCHAR(100) NOT NULL,
 `account_type` ENUM('Asset','Liability','Equity','Income','Expense') NOT NULL,
 `normal_balance` ENUM('Debit','Credit') NOT NULL,
 `is_active` BOOLEAN NOT NULL,
 `is_cash_account` BOOLEAN NOT NULL,
 `debit_balance` DECIMAL(30,2) NOT NULL,
 `credit_balance` DECIMAL(30,2) NOT NULL,
 PRIMARY KEY (`snapshot_id`,`account_id`),
 KEY `idx_tb_snapshot_account` (`account_id`,`snapshot_id`),
 CONSTRAINT `fk_tb_line_snapshot` FOREIGN KEY (`snapshot_id`) REFERENCES `trial_balance_snapshots` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT `fk_tb_line_account` FOREIGN KEY (`account_id`) REFERENCES `Categories` (`CategoryID`) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT `chk_tb_line_balance` CHECK (`debit_balance`>=0 AND `credit_balance`>=0 AND (`debit_balance`=0 OR `credit_balance`=0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SELECT COUNT(*) AS snapshot_count FROM `trial_balance_snapshots`;
SELECT COUNT(*) AS snapshot_line_count FROM `trial_balance_snapshot_lines`;
