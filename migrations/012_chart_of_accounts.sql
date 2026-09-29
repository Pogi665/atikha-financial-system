-- Apply once, after migration 001. Back up and inspect Categories first.
-- DDL commits independently; verify on a disposable database before deployment.
USE atikha_finance;

ALTER TABLE Categories
    ADD COLUMN Detail_Type VARCHAR(100) NULL AFTER Type,
    ADD COLUMN Description TEXT NULL AFTER Detail_Type;
