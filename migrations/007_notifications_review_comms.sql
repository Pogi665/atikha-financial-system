-- Notifications, review workflow columns, report snapshots, and board communications.
-- Run manually in phpMyAdmin or mysql CLI after migrations 001–006.

USE atikha_finance;

CREATE TABLE IF NOT EXISTS Notifications (
  NotificationID   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  Recipient_UserID INT UNSIGNED NULL,
  Recipient_Role   ENUM('Admin','Management','Staff') NULL,
  Message          VARCHAR(500) NOT NULL,
  Target_URL       VARCHAR(255) NOT NULL DEFAULT '',
  Is_Read          TINYINT(1) NOT NULL DEFAULT 0,
  Created_At       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_recipient_user (Recipient_UserID, Is_Read, Created_At),
  INDEX idx_recipient_role (Recipient_Role, Is_Read, Created_At),
  CONSTRAINT fk_notifications_user
    FOREIGN KEY (Recipient_UserID) REFERENCES Users(UserID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE Expenses
  ADD COLUMN Review_Status ENUM('None','Requested','Reviewed') NOT NULL DEFAULT 'None',
  ADD COLUMN Review_Notes TEXT NULL;

ALTER TABLE Incoming_Funds
  ADD COLUMN Review_Status ENUM('None','Requested','Reviewed') NOT NULL DEFAULT 'None',
  ADD COLUMN Review_Notes TEXT NULL;

CREATE TABLE IF NOT EXISTS Reports (
  ReportID            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  Report_Month        TINYINT UNSIGNED NOT NULL,
  Report_Year         SMALLINT UNSIGNED NOT NULL,
  SubmittedBy_UserID  INT UNSIGNED NOT NULL,
  Total_Revenue       DECIMAL(12, 2) NOT NULL DEFAULT 0,
  Total_Expenses      DECIMAL(12, 2) NOT NULL DEFAULT 0,
  Net_Income          DECIMAL(12, 2) NOT NULL DEFAULT 0,
  Review_Status       ENUM('None','Requested','Reviewed') NOT NULL DEFAULT 'None',
  Review_Notes        TEXT NULL,
  Created_At          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_report_period (Report_Month, Report_Year),
  CONSTRAINT fk_reports_user FOREIGN KEY (SubmittedBy_UserID) REFERENCES Users(UserID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Board_Communications (
  CommunicationID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  Sender_UserID   INT UNSIGNED NOT NULL,
  Subject         VARCHAR(255) NOT NULL,
  Message_Body    TEXT NOT NULL,
  File_Path       VARCHAR(255) NULL,
  Review_Status   ENUM('None','Requested','Reviewed') NOT NULL DEFAULT 'Requested',
  Created_At      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_board_sender FOREIGN KEY (Sender_UserID) REFERENCES Users(UserID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
