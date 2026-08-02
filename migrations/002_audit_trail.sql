-- Migration 002: System Audit Trail
-- Adds the append-only audit_logs table plus the triggers that enforce it.
-- Run against the existing atikha_finance database.
--
-- APPEND-ONLY RULE
-- This table is evidence. Rows are written once and never changed:
--   * No PHP code issues UPDATE, DELETE or TRUNCATE against audit_logs.
--   * The two triggers below reject UPDATE and DELETE at the engine level, so
--     phpMyAdmin and raw SQL sessions are covered too.
-- Dropping either trigger, or altering this table, is itself an event that a
-- reviewer should be able to account for.
--
-- Column names are snake_case here, unlike the PascalCase used by the older
-- tables, because the audit schema is specified independently of them.

USE atikha_finance;

-- ---------------------------------------------------------------------------
-- audit_logs
-- user_id is INT UNSIGNED to match Users.UserID; a plain INT would make the
-- foreign key incompatible (errno 3780).
-- old_values / new_values hold only the fields relevant to the action, never
-- credentials.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  action_type VARCHAR(30) NOT NULL,
  module VARCHAR(50) NOT NULL,
  record_id INT UNSIGNED NULL,
  old_values JSON NULL,
  new_values JSON NULL,
  source_link VARCHAR(255) NULL,
  ip_address VARCHAR(45) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_logs_user (user_id),
  KEY idx_audit_logs_action (action_type),
  KEY idx_audit_logs_module (module),
  KEY idx_audit_logs_created (created_at),
  CONSTRAINT fk_audit_logs_user
    FOREIGN KEY (user_id) REFERENCES Users (UserID)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Immutability triggers
-- SIGNAL aborts the statement, so an attempted edit fails loudly instead of
-- silently succeeding.
-- ---------------------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_audit_logs_no_update;
DROP TRIGGER IF EXISTS trg_audit_logs_no_delete;

CREATE TRIGGER trg_audit_logs_no_update
BEFORE UPDATE ON audit_logs FOR EACH ROW
  SIGNAL SQLSTATE '45000'
  SET MESSAGE_TEXT = 'audit_logs is append-only: UPDATE is forbidden.';

CREATE TRIGGER trg_audit_logs_no_delete
BEFORE DELETE ON audit_logs FOR EACH ROW
  SIGNAL SQLSTATE '45000'
  SET MESSAGE_TEXT = 'audit_logs is append-only: DELETE is forbidden.';
