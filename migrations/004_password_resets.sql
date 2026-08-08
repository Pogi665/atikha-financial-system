-- Migration 004: Password Reset Requests
-- Stores admin-mediated password reset requests submitted from the login page.
-- Run against the existing atikha_finance database.

USE atikha_finance;

CREATE TABLE IF NOT EXISTS password_resets (
  ResetID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  UserID INT UNSIGNED NOT NULL,
  Email VARCHAR(255) NOT NULL,
  Status ENUM('pending', 'completed', 'rejected') NOT NULL DEFAULT 'pending',
  RequestedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ResolvedAt TIMESTAMP NULL,
  ResolvedBy_UserID INT UNSIGNED NULL,
  ip_address VARCHAR(45) NOT NULL,
  CONSTRAINT fk_password_resets_user
    FOREIGN KEY (UserID) REFERENCES Users(UserID),
  CONSTRAINT fk_password_resets_resolver
    FOREIGN KEY (ResolvedBy_UserID) REFERENCES Users(UserID),
  KEY idx_password_resets_status (Status),
  KEY idx_password_resets_requested (RequestedAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
