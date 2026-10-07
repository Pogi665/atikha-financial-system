-- Stage 3 checkpoint 1. Apply ONCE after complete 019/020 to a verified target.
-- No USE, seeds, history rewrites or disabled constraints. DDL implicitly commits.
-- Back up first; stop on error and inspect/restore rather than rerun.
CREATE TABLE journal_corrections (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 target_journal_id INT UNSIGNED NOT NULL UNIQUE,
 reversal_journal_id INT UNSIGNED NOT NULL UNIQUE,
 replacement_journal_id INT UNSIGNED NULL UNIQUE,
 root_journal_id INT UNSIGNED NOT NULL,
 parent_correction_id INT UNSIGNED NULL,
 draft_id INT UNSIGNED NOT NULL UNIQUE,
 submission_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 mode ENUM('reverse_only','reverse_replace') NOT NULL,
 accounting_date DATE NOT NULL,
 reason VARCHAR(2000) NOT NULL, backdate_reason VARCHAR(2000) NOT NULL DEFAULT '',
 request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 review_snapshot LONGTEXT NOT NULL,
 created_by INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
 KEY idx_s3_root(root_journal_id,id), KEY idx_s3_date(accounting_date,id),
 CONSTRAINT fk_s3_target FOREIGN KEY(target_journal_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s3_reversal FOREIGN KEY(reversal_journal_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s3_replacement FOREIGN KEY(replacement_journal_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s3_root FOREIGN KEY(root_journal_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s3_parent FOREIGN KEY(parent_correction_id) REFERENCES journal_corrections(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s3_draft FOREIGN KEY(draft_id) REFERENCES journal_drafts(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s3_actor FOREIGN KEY(created_by) REFERENCES user_identities(UserID) ON DELETE RESTRICT,
 CONSTRAINT chk_s3_mode CHECK((mode='reverse_only' AND replacement_journal_id IS NULL) OR (mode='reverse_replace' AND replacement_journal_id IS NOT NULL)),
 CONSTRAINT chk_s3_distinct CHECK(target_journal_id<>reversal_journal_id AND (replacement_journal_id IS NULL OR (replacement_journal_id<>target_journal_id AND replacement_journal_id<>reversal_journal_id))),
 CONSTRAINT chk_s3_review CHECK(JSON_VALID(review_snapshot)),
 CONSTRAINT chk_s3_reason CHECK(CHAR_LENGTH(TRIM(reason))>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE journal_correction_lines (
 correction_id INT UNSIGNED NOT NULL,
 original_line_id INT UNSIGNED NOT NULL PRIMARY KEY,
 reversal_line_id INT UNSIGNED NOT NULL UNIQUE,
 KEY idx_s3_mapping_correction(correction_id),
 CONSTRAINT fk_s3_mapping_correction FOREIGN KEY(correction_id) REFERENCES journal_corrections(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s3_mapping_original FOREIGN KEY(original_line_id) REFERENCES journal_entry_lines(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s3_mapping_reversal FOREIGN KEY(reversal_line_id) REFERENCES journal_entry_lines(id) ON DELETE RESTRICT,
 CONSTRAINT chk_s3_mapping_distinct CHECK(original_line_id<>reversal_line_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE cash_advance_operation_reversals (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 original_operation_id INT UNSIGNED NOT NULL UNIQUE,
 correction_id INT UNSIGNED NOT NULL UNIQUE,
 reversal_journal_id INT UNSIGNED NOT NULL UNIQUE,
 control_line_id INT UNSIGNED NOT NULL UNIQUE,
 CONSTRAINT fk_s3_op_original FOREIGN KEY(original_operation_id) REFERENCES cash_advance_operations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s3_op_correction FOREIGN KEY(correction_id) REFERENCES journal_corrections(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s3_op_journal FOREIGN KEY(reversal_journal_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s3_op_line FOREIGN KEY(control_line_id) REFERENCES journal_entry_lines(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE correction_evidence_reservations (
 receipt_id INT UNSIGNED NOT NULL PRIMARY KEY,
 draft_id INT UNSIGNED NOT NULL, source_association_id INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 KEY idx_s3_reservation_draft(draft_id),
 CONSTRAINT fk_s3_reservation_receipt FOREIGN KEY(receipt_id) REFERENCES Receipts(ReceiptID) ON DELETE RESTRICT,
 CONSTRAINT fk_s3_reservation_draft FOREIGN KEY(draft_id) REFERENCES journal_drafts(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s3_reservation_source FOREIGN KEY(source_association_id) REFERENCES posted_evidence_associations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE journal_drafts
 MODIFY COLUMN workflow_kind ENUM('ordinary','advance_release','advance_liquidation','advance_return','correction') NOT NULL DEFAULT 'ordinary',
 ADD COLUMN correction_target_journal_id INT UNSIGNED NULL,
 ADD COLUMN correction_mode ENUM('reverse_only','reverse_replace') NULL,
 ADD CONSTRAINT fk_s3_draft_target FOREIGN KEY(correction_target_journal_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
 ADD CONSTRAINT chk_s3_draft_context CHECK(
  (workflow_kind='correction' AND payload_version=4 AND correction_target_journal_id IS NOT NULL AND correction_mode IS NOT NULL)
  OR (workflow_kind<>'correction' AND payload_version<>4 AND correction_target_journal_id IS NULL AND correction_mode IS NULL));
ALTER TABLE posted_evidence_associations
 ADD COLUMN source_association_id INT UNSIGNED NULL,
 ADD COLUMN correction_id INT UNSIGNED NULL,
 ADD CONSTRAINT fk_s3_evidence_source FOREIGN KEY(source_association_id) REFERENCES posted_evidence_associations(id) ON DELETE RESTRICT,
 ADD CONSTRAINT fk_s3_evidence_correction FOREIGN KEY(correction_id) REFERENCES journal_corrections(id) ON DELETE RESTRICT,
 ADD CONSTRAINT chk_s3_evidence_source CHECK(source_association_id IS NULL OR correction_id IS NOT NULL);
DELIMITER $$
CREATE TRIGGER s3_corrections_no_update BEFORE UPDATE ON journal_corrections FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted corrections are immutable'; END$$
CREATE TRIGGER s3_corrections_no_delete BEFORE DELETE ON journal_corrections FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted corrections are immutable'; END$$
CREATE TRIGGER s3_mapping_no_update BEFORE UPDATE ON journal_correction_lines FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reversal line mappings are immutable'; END$$
CREATE TRIGGER s3_mapping_no_delete BEFORE DELETE ON journal_correction_lines FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reversal line mappings are immutable'; END$$
CREATE TRIGGER s3_operations_no_update BEFORE UPDATE ON cash_advance_operation_reversals FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Advance operation reversals are immutable'; END$$
CREATE TRIGGER s3_operations_no_delete BEFORE DELETE ON cash_advance_operation_reversals FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Advance operation reversals are immutable'; END$$
CREATE TRIGGER s3_journal_no_update BEFORE UPDATE ON journal_entries FOR EACH ROW
BEGIN IF OLD.status='posted' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted journals are immutable'; END IF; END$$
CREATE TRIGGER s3_journal_no_delete BEFORE DELETE ON journal_entries FOR EACH ROW
BEGIN IF OLD.status='posted' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted journals are immutable'; END IF; END$$
CREATE TRIGGER s3_line_no_update BEFORE UPDATE ON journal_entry_lines FOR EACH ROW
BEGIN
 IF EXISTS(SELECT 1 FROM journal_entries WHERE id IN (OLD.journal_entry_id,NEW.journal_entry_id) AND status='posted')
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted journal lines are immutable'; END IF;
END$$
CREATE TRIGGER s3_line_no_delete BEFORE DELETE ON journal_entry_lines FOR EACH ROW
BEGIN
 IF EXISTS(SELECT 1 FROM journal_entries WHERE id=OLD.journal_entry_id AND status='posted')
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted journal lines are immutable'; END IF;
END$$
DELIMITER ;
