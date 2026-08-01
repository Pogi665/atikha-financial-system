-- Migration 001: Smart Expense OCR feature
-- Adds DB-driven categories and a receipt audit trail.
-- Run against the existing atikha_finance database.

USE atikha_finance;

-- ---------------------------------------------------------------------------
-- Categories
-- Single source of truth for expense and fund categories.
-- Expenses.Category / Incoming_Funds.Category remain VARCHAR so existing rows
-- and the reports.php GROUP BY Category keep working unchanged.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Categories (
  CategoryID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  Name VARCHAR(100) NOT NULL,
  Type ENUM('Expense', 'Fund') NOT NULL DEFAULT 'Expense',
  Is_Active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_categories_name_type (Name, Type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Receipts
-- ExpenseID is NULL between upload and the user confirming the review form.
-- OCR_Raw_JSON keeps the verbatim Gemini response for auditing bad extractions.
-- LONGTEXT rather than JSON so this runs on both MySQL and MariaDB under XAMPP.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Receipts (
  ReceiptID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ExpenseID INT UNSIGNED NULL,
  File_Path VARCHAR(255) NOT NULL,
  Original_Filename VARCHAR(255) NULL,
  Mime_Type VARCHAR(100) NULL,
  File_Size INT UNSIGNED NULL,
  OCR_Status ENUM('Pending', 'Processed', 'Failed', 'Discarded') NOT NULL DEFAULT 'Pending',
  OCR_Raw_JSON LONGTEXT NULL,
  OCR_Error TEXT NULL,
  UploadedBy_UserID INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_receipts_expense (ExpenseID),
  KEY idx_receipts_uploaded_by (UploadedBy_UserID),
  CONSTRAINT fk_receipts_expense
    FOREIGN KEY (ExpenseID) REFERENCES Expenses (ExpenseID)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_receipts_user
    FOREIGN KEY (UploadedBy_UserID) REFERENCES Users (UserID)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Seed categories
-- The six original expense categories from expenses.php, plus the four new
-- ones the OCR classifier needs. Fund categories come from funds.php.
-- ---------------------------------------------------------------------------
INSERT INTO Categories (Name, Type) VALUES
  ('Utilities',         'Expense'),
  ('Payroll',           'Expense'),
  ('Office Supplies',   'Expense'),
  ('Event Costs',       'Expense'),
  ('Equipment',         'Expense'),
  ('Transportation',    'Expense'),
  ('Meals',             'Expense'),
  ('Travel',            'Expense'),
  ('Professional Fees', 'Expense'),
  ('Miscellaneous',     'Expense'),
  ('Donation',          'Fund'),
  ('Grant',             'Fund'),
  ('Fundraiser',        'Fund'),
  ('Sponsorship',       'Fund'),
  ('Other',             'Fund')
ON DUPLICATE KEY UPDATE Is_Active = 1;
