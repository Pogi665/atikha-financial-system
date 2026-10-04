-- 015_double_entry_core.sql
-- Phase 1: clean-slate double-entry database core.
-- Prepared for manual application; creating this file does not run the migration.
--
-- APPROVED DECISIONS
-- Retain and truncate legacy transaction/receipt tables.
-- Clear derived financial history; preserve budgets and audit history.
-- Leave Account_Code NULL until approved codes are assigned manually.
--
-- EXECUTION REQUIREMENTS
-- Apply once, after migrations 001-014, using a client that stops on error.
-- Take a database backup and put the application into maintenance first.
-- Do not reopen legacy entry workflows after this migration.
-- ALTER and TRUNCATE implicitly commit; transaction rollback is insufficient.
-- On failure, disconnect, inspect the partial state, and recover from backup.
--
-- PHASE BOUNDARY
-- The line CHECK enforces one positive Debit OR Credit per line.
-- It does not enforce journal-wide balance or posted-entry immutability.
-- Posting must remain unavailable until a later phase implements those controls.
-- This migration creates no opening balances and migrates no transactions.
-- Uploaded receipt files remain untouched; their database links are removed.

USE `atikha_finance`;

-- 1. Extend the existing account master.
-- Preserve CategoryID, names, metadata, and active/inactive status.
-- Make legacy Type nullable so future Asset/Liability/Equity accounts
-- need not be incorrectly classified as Fund or Expense.
-- Add classification fields as nullable initially to map existing accounts.

ALTER TABLE `Categories`
    MODIFY COLUMN `Type`
        ENUM('Expense', 'Fund') NULL DEFAULT NULL,
    ADD COLUMN `Account_Code`
        VARCHAR(30) NULL DEFAULT NULL,
    ADD COLUMN `Account_Type`
        ENUM('Asset', 'Liability', 'Equity', 'Income', 'Expense')
        NULL DEFAULT NULL,
    ADD COLUMN `Normal_Balance`
        ENUM('Debit', 'Credit') NULL DEFAULT NULL,
    ADD COLUMN `Is_Cash_Account`
        BOOLEAN NOT NULL DEFAULT FALSE,
    ADD UNIQUE KEY `uq_categories_account_code` (`Account_Code`),
    ADD CONSTRAINT `chk_categories_cash_flag`
        CHECK (`Is_Cash_Account` IN (0, 1));

UPDATE `Categories`
SET
    `Account_Type` = 'Income',
    `Normal_Balance` = 'Credit'
WHERE `Type` = 'Fund';

UPDATE `Categories`
SET
    `Account_Type` = 'Expense',
    `Normal_Balance` = 'Debit'
WHERE `Type` = 'Expense';

-- Require explicit classifications for the new accounting workflow.
-- The new name/type key also covers accounts whose legacy Type is NULL.

ALTER TABLE `Categories`
    MODIFY COLUMN `Account_Type`
        ENUM('Asset', 'Liability', 'Equity', 'Income', 'Expense') NOT NULL,
    MODIFY COLUMN `Normal_Balance`
        ENUM('Debit', 'Credit') NOT NULL,
    ADD UNIQUE KEY `uq_categories_name_account_type`
        (`Name`, `Account_Type`);

-- 2. Create journal headers.
-- reference is an optional external reference, not a unique journal number.
-- Later phases must add posting authorization and audit attribution.

CREATE TABLE `journal_entries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entry_date` DATE NOT NULL,
    `reference` VARCHAR(100) NULL DEFAULT NULL,
    `description` TEXT NOT NULL,
    `status` ENUM('draft', 'posted') NOT NULL DEFAULT 'draft',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_journal_entries_status_date`
        (`status`, `entry_date`, `id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- 3. Create journal lines.
-- account_id matches Categories.CategoryID: INT UNSIGNED.
-- No fund/project master exists in the inspected database.
-- fund_project_id is therefore a nullable placeholder without a foreign key.
-- A later phase must introduce and validate the corresponding master records.
-- RESTRICT prevents accidental cascading deletion of accounting lines.

CREATE TABLE `journal_entry_lines` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `journal_entry_id` INT UNSIGNED NOT NULL,
    `account_id` INT UNSIGNED NOT NULL,
    `debit_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `credit_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `fund_project_id` INT UNSIGNED NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    KEY `idx_journal_lines_entry_account`
        (`journal_entry_id`, `account_id`),
    KEY `idx_journal_lines_account_entry`
        (`account_id`, `journal_entry_id`),
    KEY `idx_journal_lines_fund_account_entry`
        (`fund_project_id`, `account_id`, `journal_entry_id`),

    CONSTRAINT `fk_journal_lines_entry`
        FOREIGN KEY (`journal_entry_id`)
        REFERENCES `journal_entries` (`id`)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT `fk_journal_lines_account`
        FOREIGN KEY (`account_id`)
        REFERENCES `Categories` (`CategoryID`)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT `chk_journal_lines_debit_or_credit`
        CHECK (
            (`debit_amount` > 0 AND `credit_amount` = 0)
            OR
            (`credit_amount` > 0 AND `debit_amount` = 0)
        )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- 4. Clear legacy financial data only after schema creation succeeds.
-- Receipts references Expenses, so truncating the empty child alone
-- does not remove the parent-table foreign-key restriction.
-- Disable checks only for this connection and this bounded cleanup.
-- Run the complete script in one connection; disconnect on any error.

SET @phase1_previous_fk_checks = @@SESSION.foreign_key_checks;

SET SESSION foreign_key_checks = 0;

TRUNCATE TABLE `Receipts`;
TRUNCATE TABLE `Expenses`;
TRUNCATE TABLE `Incoming_Funds`;

SET SESSION foreign_key_checks = @phase1_previous_fk_checks;

-- 5. Clear financial snapshots and cached forecasts.

TRUNCATE TABLE `Reports`;
TRUNCATE TABLE `forecast_cache`;

-- Remove notifications targeting the existing financial workflows.
-- Preserve board communications and other unrelated notifications.

DELETE FROM `Notifications`
WHERE SUBSTRING_INDEX(`Target_URL`, '?', 1) IN (
    'funds.php',
    'expenses.php',
    'financial_records.php',
    'reports.php',
    'dashboard.php',
    'management_reviews.php'
);

-- Preserve:
-- Categories, Budgets, Users, user_identities, audit_logs,
-- Board_Communications, External_Communications, password_resets,
-- and unrelated Notifications.
-- Historical audit references to wiped records will no longer resolve.

-- 6. Read-only post-migration verification.
-- All seven counts below must be zero immediately after migration.

SELECT 'Incoming_Funds' AS `table_name`, COUNT(*) AS `row_count`
FROM `Incoming_Funds`
UNION ALL
SELECT 'Expenses', COUNT(*) FROM `Expenses`
UNION ALL
SELECT 'Receipts', COUNT(*) FROM `Receipts`
UNION ALL
SELECT 'Reports', COUNT(*) FROM `Reports`
UNION ALL
SELECT 'forecast_cache', COUNT(*) FROM `forecast_cache`
UNION ALL
SELECT 'journal_entries', COUNT(*) FROM `journal_entries`
UNION ALL
SELECT 'journal_entry_lines', COUNT(*) FROM `journal_entry_lines`;

-- Expected for the currently inspected account master:
-- Income / Credit: 7 accounts.
-- Expense / Debit: 20 accounts.
-- All Account_Code values remain NULL.

SELECT
    `Account_Type`,
    `Normal_Balance`,
    COUNT(*) AS `account_count`
FROM `Categories`
GROUP BY `Account_Type`, `Normal_Balance`;

SELECT COUNT(*) AS `invalid_existing_account_mappings`
FROM `Categories`
WHERE `Account_Type` IS NULL
   OR `Normal_Balance` IS NULL
   OR (`Type` = 'Fund'
       AND (`Account_Type` <> 'Income' OR `Normal_Balance` <> 'Credit'))
   OR (`Type` = 'Expense'
       AND (`Account_Type` <> 'Expense' OR `Normal_Balance` <> 'Debit'));

SELECT @@SESSION.foreign_key_checks AS `foreign_key_checks_restored`;

-- VALIDATION: scripts/test_double_entry_core.php uses an empty disposable database.
-- It checks account/protected-data preservation, cleanup, unique codes, valid and
-- invalid monetary lines, foreign keys, nullable legacy Type, and rerun failure.
--
-- An unbalanced journal can still exist under this Phase 1 schema.
-- Journal-wide balance, inactive-account checks, fund validation, period locks,
-- posting authorization, strict monetary input validation, and immutable posted
-- entries are later-phase prerequisites before accounting entry goes live.
