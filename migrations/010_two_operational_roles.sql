-- Schema checkpoint B. Each ALTER TABLE implicitly commits independently.
-- The runner checks that only operational roles remain BEFORE this file runs.
ALTER TABLE Users MODIFY Role ENUM('Admin','Management') NOT NULL;
ALTER TABLE Notifications MODIFY Recipient_Role ENUM('Admin','Management') NULL;
