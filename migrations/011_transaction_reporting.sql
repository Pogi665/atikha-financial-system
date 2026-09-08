-- Recommendation 4. Additive only; legacy values remain NULL.
-- Apply to the explicitly selected database after migrations 001-010.
ALTER TABLE Incoming_Funds
  ADD COLUMN IF NOT EXISTS Purpose VARCHAR(1000) NULL AFTER Category,
  ADD INDEX IF NOT EXISTS idx_incoming_funds_date (Date_Received);

ALTER TABLE Expenses
  ADD COLUMN IF NOT EXISTS Purpose VARCHAR(1000) NULL AFTER Category,
  ADD COLUMN IF NOT EXISTS Project_Code VARCHAR(50) NULL AFTER Purpose,
  ADD INDEX IF NOT EXISTS idx_expenses_date (Date_Incurred);
