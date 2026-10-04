-- Phase 4. Apply once after 017, under maintenance after database AND file backup.
-- Rehearse on a disposable copy. DDL implicitly commits. Stop on any error.
-- No journals, legacy evidence, or uploaded files are deleted or backfilled.
USE atikha_finance;
ALTER TABLE Receipts
 ADD COLUMN JournalEntryID INT UNSIGNED NULL,
 ADD COLUMN File_SHA256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 ADD COLUMN Posted_File_SHA256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 ADD COLUMN Upload_Key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 ADD UNIQUE KEY uq_receipt_upload_key (Upload_Key),
 ADD UNIQUE KEY uq_receipt_posted_hash (Posted_File_SHA256),
 ADD KEY idx_receipt_journal (JournalEntryID),
 ADD CONSTRAINT fk_receipt_journal FOREIGN KEY (JournalEntryID) REFERENCES journal_entries(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 ADD CONSTRAINT chk_receipt_link CHECK (ExpenseID IS NULL OR JournalEntryID IS NULL),
 ADD CONSTRAINT chk_receipt_hash CHECK (
  (JournalEntryID IS NULL AND Posted_File_SHA256 IS NULL) OR
  (JournalEntryID IS NOT NULL AND File_SHA256 IS NOT NULL AND Posted_File_SHA256 IS NOT NULL AND File_SHA256=Posted_File_SHA256)),
 DROP FOREIGN KEY fk_receipts_user,
 ADD CONSTRAINT fk_receipt_identity FOREIGN KEY (UploadedBy_UserID) REFERENCES user_identities(UserID) ON DELETE RESTRICT ON UPDATE RESTRICT;

CREATE TABLE receipt_ocr_attempts (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 receipt_id INT UNSIGNED NOT NULL,
 requested_by_user_id INT UNSIGNED NOT NULL,
 request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 state ENUM('Pending','Processed','Failed') NOT NULL DEFAULT 'Pending',
 started_at DATETIME NOT NULL,
 completed_at DATETIME NULL,
 source_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 catalog_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 model VARCHAR(100) NOT NULL,
 schema_version VARCHAR(40) NOT NULL,
 raw_response LONGTEXT NULL,
 normalized_json LONGTEXT NULL,
 error_message VARCHAR(500) NULL,
 UNIQUE KEY uq_receipt_attempt_key (request_key),
 KEY idx_receipt_attempt (receipt_id,id),
 KEY idx_receipt_attempt_rate (requested_by_user_id,started_at),
 CONSTRAINT fk_attempt_receipt FOREIGN KEY (receipt_id) REFERENCES Receipts(ReceiptID) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_attempt_identity FOREIGN KEY (requested_by_user_id) REFERENCES user_identities(UserID) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT chk_attempt_state CHECK ((state='Pending' AND completed_at IS NULL) OR (state<>'Pending' AND completed_at IS NOT NULL)),
 CONSTRAINT chk_attempt_json CHECK (normalized_json IS NULL OR JSON_VALID(normalized_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Attempts may transition once from Pending to a terminal state. Completed
-- evidence cannot be rewritten or deleted, including by accidental SQL updates.
DELIMITER $$
CREATE TRIGGER receipt_attempt_no_rewrite BEFORE UPDATE ON receipt_ocr_attempts FOR EACH ROW
BEGIN
 IF OLD.state<>'Pending' OR NEW.state='Pending'
  OR NEW.id<>OLD.id OR NEW.receipt_id<>OLD.receipt_id OR NEW.requested_by_user_id<>OLD.requested_by_user_id
  OR NEW.request_key<>OLD.request_key OR NEW.started_at<>OLD.started_at OR NEW.source_hash<>OLD.source_hash
  OR NEW.catalog_fingerprint<>OLD.catalog_fingerprint OR NEW.model<>OLD.model OR NEW.schema_version<>OLD.schema_version THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='OCR attempt history is immutable';
 END IF;
END$$
CREATE TRIGGER receipt_attempt_no_delete BEFORE DELETE ON receipt_ocr_attempts FOR EACH ROW
BEGIN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='OCR attempt history is immutable';
END$$
DELIMITER ;
SELECT COUNT(*) AS receipt_count FROM Receipts;
SELECT COUNT(*) AS extraction_attempt_count FROM receipt_ocr_attempts;
