-- Migration 003: Predictive AI Financial Forecasting
-- Adds the forecast_cache table that backs the dashboard forecast card.
-- Run against the existing atikha_finance database.
--
-- CACHE SEMANTICS
-- One row per generated forecast, org-wide rather than per-user: the underlying
-- Expenses and Incoming_Funds data is the same for everybody. A read takes the
-- newest row younger than 24 hours; anything older is ignored and eventually
-- pruned by forecast_ai.php. Rows are disposable, so nothing here is a
-- foreign-key dependency of another table.
--
-- LONGTEXT rather than JSON so this runs on both MySQL and MariaDB under XAMPP,
-- the same reasoning as Receipts.OCR_Raw_JSON in migration 001.

USE atikha_finance;

-- ---------------------------------------------------------------------------
-- forecast_cache
-- History_JSON keeps the aggregate that was sent to the model, so a stored
-- forecast can always be redrawn against the numbers it was actually derived
-- from rather than against whatever the tables hold today.
-- GeneratedBy_UserID is nullable and ON DELETE SET NULL: a cache row must never
-- be the reason a user cannot be removed.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS forecast_cache (
  ForecastID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  Horizon_Months TINYINT UNSIGNED NOT NULL DEFAULT 6,
  Data_Fingerprint CHAR(40) NOT NULL,
  History_JSON LONGTEXT NOT NULL,
  Forecast_JSON LONGTEXT NOT NULL,
  Model VARCHAR(100) NOT NULL,
  GeneratedBy_UserID INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_forecast_cache_created (created_at),
  CONSTRAINT fk_forecast_cache_user
    FOREIGN KEY (GeneratedBy_UserID) REFERENCES Users (UserID)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
