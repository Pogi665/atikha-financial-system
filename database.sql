-- Internal Financial Management System
-- Database: atikha_finance

CREATE DATABASE IF NOT EXISTS atikha_finance
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE atikha_finance;

-- ---------------------------------------------------------------------------
-- Users
-- ---------------------------------------------------------------------------
CREATE TABLE Users (
  UserID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  FullName VARCHAR(255) NOT NULL,
  Role ENUM('Admin', 'Management', 'Staff') NOT NULL,
  Email VARCHAR(255) NOT NULL UNIQUE,
  Password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Incoming_Funds
-- ---------------------------------------------------------------------------
CREATE TABLE Incoming_Funds (
  FundID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  Source_Donor VARCHAR(255) NOT NULL,
  Category VARCHAR(100) NOT NULL,
  Amount DECIMAL(10, 2) NOT NULL,
  Date_Received DATE NOT NULL,
  RecordedBy_UserID INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_incoming_recorded_by
    FOREIGN KEY (RecordedBy_UserID) REFERENCES Users (UserID)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Expenses
-- ---------------------------------------------------------------------------
CREATE TABLE Expenses (
  ExpenseID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  Payee VARCHAR(255) NOT NULL,
  Category VARCHAR(100) NOT NULL,
  Amount DECIMAL(10, 2) NOT NULL,
  Date_Incurred DATE NOT NULL,
  RecordedBy_UserID INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_expenses_recorded_by
    FOREIGN KEY (RecordedBy_UserID) REFERENCES Users (UserID)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Seed users (plain text password for testing: password123)
-- Bcrypt hash pre-computed via PHP password_hash('password123', PASSWORD_DEFAULT)
-- ---------------------------------------------------------------------------
INSERT INTO Users (FullName, Role, Email, Password) VALUES
(
  'System Administrator',
  'Admin',
  'admin@atikha.local',
  '$2y$10$znS6ZWqZ6KTgHXvcnTAEW.aSLa6IKp4qX2kb81qMFaImYNVr0VOca'
),
(
  'Test Staff',
  'Staff',
  'staff@atikha.local',
  '$2y$10$znS6ZWqZ6KTgHXvcnTAEW.aSLa6IKp4qX2kb81qMFaImYNVr0VOca'
);
