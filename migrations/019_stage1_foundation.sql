-- Stage 1 foundation. Apply ONCE after 018, under maintenance, after database
-- AND protected-file backup and disposable rehearsal. Never rerun 015.
-- DDL implicitly commits. Stop on error; inspect/restore rather than rerun.
-- Does not create accounts, opening balances, parties, projects, or transactions.
USE atikha_finance;
DELIMITER $$
CREATE PROCEDURE stage1_preflight_019()
BEGIN
 IF EXISTS (SELECT 1 FROM journal_entry_lines WHERE fund_project_id IS NOT NULL) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='019 requires reviewed resolution of existing project references; no automatic mapping';
 END IF;
 IF EXISTS (SELECT 1 FROM journal_entries WHERE status='draft') THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='019 requires review of existing journal drafts before migration';
 END IF;
END$$
DELIMITER ;
CALL stage1_preflight_019();
DROP PROCEDURE stage1_preflight_019;

CREATE TABLE projects (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(30) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL UNIQUE,
 name VARCHAR(100) NOT NULL, description VARCHAR(2000) NOT NULL DEFAULT '',
 is_active BOOLEAN NOT NULL DEFAULT 1, revision INT UNSIGNED NOT NULL DEFAULT 1,
 created_by INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_s1_project_actor FOREIGN KEY(created_by) REFERENCES user_identities(UserID) ON DELETE RESTRICT,
 CONSTRAINT chk_s1_project_active CHECK(is_active IN(0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE parties (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 name VARCHAR(100) NOT NULL, party_type ENUM('person','organization') NOT NULL,
 reference VARCHAR(100) NOT NULL DEFAULT '', description VARCHAR(2000) NOT NULL DEFAULT '',
 is_active BOOLEAN NOT NULL DEFAULT 1, revision INT UNSIGNED NOT NULL DEFAULT 1,
 created_by INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_s1_party_actor FOREIGN KEY(created_by) REFERENCES user_identities(UserID) ON DELETE RESTRICT,
 CONSTRAINT chk_s1_party_active CHECK(is_active IN(0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE journal_entries
 ADD COLUMN source_book ENUM('CRB','CDB','GJ') NULL,
 ADD COLUMN transaction_kind VARCHAR(32) NULL,
 ADD COLUMN party_id INT UNSIGNED NULL,
 ADD COLUMN party_snapshot LONGTEXT NULL,
 ADD KEY idx_s1_book_date(source_book,entry_date,id),
 ADD CONSTRAINT fk_s1_journal_party FOREIGN KEY(party_id) REFERENCES parties(id) ON DELETE RESTRICT,
 ADD CONSTRAINT chk_s1_party_snapshot CHECK(party_snapshot IS NULL OR JSON_VALID(party_snapshot));
ALTER TABLE journal_entry_lines
 ADD COLUMN project_code_snapshot VARCHAR(30) NULL,
 ADD COLUMN project_name_snapshot VARCHAR(100) NULL,
 ADD CONSTRAINT fk_s1_line_project FOREIGN KEY(fund_project_id) REFERENCES projects(id) ON DELETE RESTRICT;
CREATE TABLE journal_drafts (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 owner_id INT UNSIGNED NOT NULL, source_book ENUM('CRB','CDB','GJ') NOT NULL,
 payload_version SMALLINT UNSIGNED NOT NULL DEFAULT 2,
 payload LONGTEXT NOT NULL, creation_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 submission_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 state ENUM('Draft','Posted','Discarded') NOT NULL DEFAULT 'Draft',
 posted_journal_id INT UNSIGNED NULL UNIQUE,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_s1_draft_owner(owner_id,state,updated_at),
 CONSTRAINT fk_s1_draft_owner FOREIGN KEY(owner_id) REFERENCES user_identities(UserID) ON DELETE RESTRICT,
 CONSTRAINT fk_s1_draft_journal FOREIGN KEY(posted_journal_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
 CONSTRAINT chk_s1_draft_json CHECK(JSON_VALID(payload)),
 CONSTRAINT chk_s1_draft_posted CHECK((state='Posted' AND posted_journal_id IS NOT NULL) OR (state<>'Posted' AND posted_journal_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE draft_evidence_reservations (
 receipt_id INT UNSIGNED NOT NULL PRIMARY KEY, draft_id INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_s1_reservation_draft(draft_id),
 CONSTRAINT fk_s1_reservation_receipt FOREIGN KEY(receipt_id) REFERENCES Receipts(ReceiptID) ON DELETE RESTRICT,
 CONSTRAINT fk_s1_reservation_draft FOREIGN KEY(draft_id) REFERENCES journal_drafts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE advance_control_designations (
 account_id INT UNSIGNED NOT NULL PRIMARY KEY, designated_by INT UNSIGNED NOT NULL,
 designated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_s1_control_account FOREIGN KEY(account_id) REFERENCES Categories(CategoryID) ON DELETE RESTRICT,
 CONSTRAINT fk_s1_control_actor FOREIGN KEY(designated_by) REFERENCES user_identities(UserID) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE posted_evidence_associations (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 journal_id INT UNSIGNED NOT NULL, receipt_id INT UNSIGNED NOT NULL,
 purpose ENUM('supporting','amount','legacy') NOT NULL,
 support_side ENUM('debit','credit') NULL,
 declared_amount DECIMAL(15,2) NULL, accepted_amount DECIMAL(15,2) NULL,
 exclusion_reason VARCHAR(2000) NOT NULL DEFAULT '',
 reviewed_by INT UNSIGNED NULL, reviewed_at DATETIME NULL, review_snapshot LONGTEXT NULL,
 UNIQUE KEY uq_s1_evidence(journal_id,receipt_id),
 CONSTRAINT fk_s1_evidence_journal FOREIGN KEY(journal_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s1_evidence_receipt FOREIGN KEY(receipt_id) REFERENCES Receipts(ReceiptID) ON DELETE RESTRICT,
 CONSTRAINT fk_s1_evidence_actor FOREIGN KEY(reviewed_by) REFERENCES user_identities(UserID) ON DELETE RESTRICT,
 CONSTRAINT chk_s1_review_json CHECK(review_snapshot IS NULL OR JSON_VALID(review_snapshot)),
 CONSTRAINT chk_s1_amount_review CHECK(purpose<>'amount' OR (support_side IS NOT NULL AND declared_amount>0 AND accepted_amount>0 AND accepted_amount<=declared_amount))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE evidence_allocations (
 association_id INT UNSIGNED NOT NULL, line_id INT UNSIGNED NOT NULL,
 amount DECIMAL(15,2) NOT NULL,
 PRIMARY KEY(association_id,line_id),
 CONSTRAINT fk_s1_allocation_evidence FOREIGN KEY(association_id) REFERENCES posted_evidence_associations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_s1_allocation_line FOREIGN KEY(line_id) REFERENCES journal_entry_lines(id) ON DELETE RESTRICT,
 CONSTRAINT chk_s1_allocation_positive CHECK(amount>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE accounting_write_state (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY, change_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
 CONSTRAINT chk_s1_singleton CHECK(id=1)
) ENGINE=InnoDB;
INSERT INTO accounting_write_state(id) VALUES(1);
-- Preserve original receipt ownership/hash and do not invent historical reviews.
INSERT INTO posted_evidence_associations(journal_id,receipt_id,purpose)
 SELECT JournalEntryID,ReceiptID,'legacy' FROM Receipts WHERE JournalEntryID IS NOT NULL;
DELIMITER $$
CREATE TRIGGER s1_evidence_no_update BEFORE UPDATE ON posted_evidence_associations FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted evidence is immutable'; END$$
CREATE TRIGGER s1_evidence_no_delete BEFORE DELETE ON posted_evidence_associations FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted evidence is immutable'; END$$
CREATE TRIGGER s1_allocation_no_update BEFORE UPDATE ON evidence_allocations FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted evidence allocation is immutable'; END$$
CREATE TRIGGER s1_allocation_no_delete BEFORE DELETE ON evidence_allocations FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted evidence allocation is immutable'; END$$
DELIMITER ;
SELECT '019 ready; feature remains disabled until explicitly configured' AS deployment_status;
