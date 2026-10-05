-- Stage 2: apply ONCE after 019 to an explicitly selected, verified database.
-- No USE statement, data seeding or historical inference. DDL implicitly commits.
-- Back up database/application/protected files. Never rerun 015 or 019.
CREATE TABLE cash_advances (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 party_id INT UNSIGNED NOT NULL, originating_project_id INT UNSIGNED NULL,
 control_account_id INT UNSIGNED NOT NULL, release_journal_id INT UNSIGNED NOT NULL UNIQUE,
 purpose VARCHAR(2000) NOT NULL, reference VARCHAR(100) NOT NULL DEFAULT '',
 party_snapshot LONGTEXT NOT NULL, project_snapshot LONGTEXT NULL,
 initial_due_date DATE NOT NULL, approval_snapshot LONGTEXT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 created_by INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
 CONSTRAINT fk_s2_advance_party FOREIGN KEY(party_id) REFERENCES parties(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s2_advance_project FOREIGN KEY(originating_project_id) REFERENCES projects(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s2_advance_control FOREIGN KEY(control_account_id) REFERENCES advance_control_designations(account_id) ON DELETE RESTRICT,
 CONSTRAINT fk_s2_advance_release FOREIGN KEY(release_journal_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s2_advance_actor FOREIGN KEY(created_by) REFERENCES user_identities(UserID) ON DELETE RESTRICT,
 CONSTRAINT chk_s2_advance_json CHECK(JSON_VALID(party_snapshot) AND (project_snapshot IS NULL OR JSON_VALID(project_snapshot)) AND (approval_snapshot IS NULL OR JSON_VALID(approval_snapshot)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE journal_drafts
 ADD COLUMN workflow_kind ENUM('ordinary','advance_release','advance_liquidation','advance_return') NOT NULL DEFAULT 'ordinary',
 ADD COLUMN advance_id INT UNSIGNED NULL,
 ADD COLUMN return_confirmation LONGTEXT NULL,
 ADD CONSTRAINT fk_s2_draft_advance FOREIGN KEY(advance_id) REFERENCES cash_advances(id) ON DELETE RESTRICT,
 ADD CONSTRAINT chk_s2_confirmation_json CHECK(return_confirmation IS NULL OR JSON_VALID(return_confirmation));
CREATE TABLE cash_advance_operations (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, advance_id INT UNSIGNED NOT NULL,
 operation_kind ENUM('release','liquidation','return') NOT NULL,
 journal_id INT UNSIGNED NOT NULL UNIQUE, draft_id INT UNSIGNED NOT NULL UNIQUE,
 control_line_id INT UNSIGNED NOT NULL UNIQUE,
 release_advance_id INT UNSIGNED GENERATED ALWAYS AS (IF(operation_kind='release',advance_id,NULL)) PERSISTENT,
 UNIQUE KEY uq_s2_one_release(release_advance_id),
 review_snapshot LONGTEXT NOT NULL, created_by INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
 KEY idx_s2_operation_advance(advance_id,id),
 CONSTRAINT fk_s2_operation_advance FOREIGN KEY(advance_id) REFERENCES cash_advances(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s2_operation_journal FOREIGN KEY(journal_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s2_operation_draft FOREIGN KEY(draft_id) REFERENCES journal_drafts(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s2_operation_line FOREIGN KEY(control_line_id) REFERENCES journal_entry_lines(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s2_operation_actor FOREIGN KEY(created_by) REFERENCES user_identities(UserID) ON DELETE RESTRICT,
 CONSTRAINT chk_s2_operation_json CHECK(JSON_VALID(review_snapshot))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE cash_advance_due_changes (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, advance_id INT UNSIGNED NOT NULL,
 old_due_date DATE NOT NULL, new_due_date DATE NOT NULL, effective_date DATE NOT NULL,
 reason VARCHAR(2000) NOT NULL, created_by INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
 KEY idx_s2_due_history(advance_id,effective_date,id),
 CONSTRAINT fk_s2_due_advance FOREIGN KEY(advance_id) REFERENCES cash_advances(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s2_due_actor FOREIGN KEY(created_by) REFERENCES user_identities(UserID) ON DELETE RESTRICT,
 CONSTRAINT chk_s2_due_extension CHECK(new_due_date>old_due_date AND new_due_date>=effective_date AND CHAR_LENGTH(reason)>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
DELIMITER $$
CREATE TRIGGER s2_operations_no_update BEFORE UPDATE ON cash_advance_operations FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted advance operations are immutable'; END$$
CREATE TRIGGER s2_operations_no_delete BEFORE DELETE ON cash_advance_operations FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted advance operations are immutable'; END$$
CREATE TRIGGER s2_due_no_update BEFORE UPDATE ON cash_advance_due_changes FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Deadline history is immutable'; END$$
CREATE TRIGGER s2_due_no_delete BEFORE DELETE ON cash_advance_due_changes FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Deadline history is immutable'; END$$
CREATE TRIGGER s2_advance_identity BEFORE UPDATE ON cash_advances FOR EACH ROW
BEGIN
 IF NOT (OLD.id<=>NEW.id) OR NOT (OLD.party_id<=>NEW.party_id) OR NOT (OLD.originating_project_id<=>NEW.originating_project_id)
 OR NOT (OLD.control_account_id<=>NEW.control_account_id) OR NOT (OLD.release_journal_id<=>NEW.release_journal_id)
 OR NOT (OLD.purpose<=>NEW.purpose) OR NOT (OLD.reference<=>NEW.reference) OR NOT (OLD.party_snapshot<=>NEW.party_snapshot)
 OR NOT (OLD.project_snapshot<=>NEW.project_snapshot) OR NOT (OLD.initial_due_date<=>NEW.initial_due_date)
 OR NOT (OLD.approval_snapshot<=>NEW.approval_snapshot) OR NOT (OLD.created_by<=>NEW.created_by) OR NOT (OLD.created_at<=>NEW.created_at)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted advance identity is immutable'; END IF;
END$$
CREATE TRIGGER s2_advance_no_delete BEFORE DELETE ON cash_advances FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted advances cannot be deleted'; END$$
DELIMITER ;
