-- Add an optional project / grant code to incoming funds so Staff can tag
-- deposits without disturbing the Category field used by reports.

ALTER TABLE Incoming_Funds
  ADD COLUMN Project_Code VARCHAR(50) NULL AFTER Category;

CREATE INDEX idx_incoming_funds_project ON Incoming_Funds (Project_Code);
