-- Migration 008: Email MFA (OTP)
-- Adds one-time password columns to Users for login verification.
-- Run against the existing atikha_finance database.

USE atikha_finance;

ALTER TABLE Users
  ADD COLUMN MFA_Code VARCHAR(6) NULL DEFAULT NULL AFTER Password,
  ADD COLUMN MFA_Expires_At DATETIME NULL DEFAULT NULL AFTER MFA_Code;
