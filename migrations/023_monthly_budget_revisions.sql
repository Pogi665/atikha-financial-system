-- Stage6 monthly budgets. Additive, once only after022; disposable rehearsal authorized separately.
-- No legacy Budgets conversion or working database selection. DDL commits implicitly.
CREATE TABLE budget_revision_roots (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 budget_month DATE NOT NULL UNIQUE CHECK(DAY(budget_month)=1),
 approved_revision_id INT UNSIGNED NULL,
 approval_epoch INT UNSIGNED NOT NULL DEFAULT 0,
 next_revision INT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE budget_revision_drafts (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 root_id INT UNSIGNED NOT NULL,owner_id INT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 request_key CHAR(64) NOT NULL,creation_hash CHAR(64) NOT NULL,
 payload LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK(JSON_VALID(payload)),
 state ENUM('Draft','Submitted') NOT NULL DEFAULT 'Draft',
 base_approved_id INT UNSIGNED NULL,base_approval_epoch INT UNSIGNED NOT NULL,
 copied_revision_id INT UNSIGNED NULL,submitted_revision_id INT UNSIGNED NULL,
 created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,
 UNIQUE KEY r6_budget_owner_key(owner_id,request_key),
 CONSTRAINT r6_budget_draft_root FOREIGN KEY(root_id) REFERENCES budget_revision_roots(id),
 CONSTRAINT r6_budget_owner FOREIGN KEY(owner_id) REFERENCES user_identities(UserID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE budget_revisions (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 root_id INT UNSIGNED NOT NULL,revision_number INT UNSIGNED NOT NULL,
 draft_id INT UNSIGNED NOT NULL UNIQUE,
 payload LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK(JSON_VALID(payload)),
 payload_hash CHAR(64) NOT NULL,draft_hash CHAR(64) NOT NULL,
 expected_approved_id INT UNSIGNED NULL,expected_approval_epoch INT UNSIGNED NOT NULL,
 prepared_by INT UNSIGNED NOT NULL,prepared_at DATETIME NOT NULL,
 UNIQUE KEY r6_budget_root_revision(root_id,revision_number),
 CONSTRAINT r6_budget_revision_root FOREIGN KEY(root_id) REFERENCES budget_revision_roots(id),
 CONSTRAINT r6_budget_source_draft FOREIGN KEY(draft_id) REFERENCES budget_revision_drafts(id),
 CONSTRAINT r6_budget_preparer FOREIGN KEY(prepared_by) REFERENCES user_identities(UserID),
 CONSTRAINT r6_budget_expected_approval FOREIGN KEY(expected_approved_id) REFERENCES budget_revisions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE budget_revision_roots ADD CONSTRAINT r6_budget_approved FOREIGN KEY(approved_revision_id) REFERENCES budget_revisions(id);
ALTER TABLE budget_revision_drafts ADD CONSTRAINT r6_budget_base_approval FOREIGN KEY(base_approved_id) REFERENCES budget_revisions(id);
ALTER TABLE budget_revision_drafts ADD CONSTRAINT r6_budget_copy FOREIGN KEY(copied_revision_id) REFERENCES budget_revisions(id);
ALTER TABLE budget_revision_drafts ADD CONSTRAINT r6_budget_submitted FOREIGN KEY(submitted_revision_id) REFERENCES budget_revisions(id);
CREATE TABLE budget_revision_allocations (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 revision_id INT UNSIGNED NOT NULL,account_id INT UNSIGNED NOT NULL,project_id INT UNSIGNED NULL,
 bucket_key VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 amount DECIMAL(20,2) NOT NULL CHECK(amount>=0),
 account_label VARCHAR(100) NOT NULL,account_code VARCHAR(30) NULL,
 project_label VARCHAR(100) NOT NULL,project_code VARCHAR(30) NULL,
 UNIQUE KEY r6_budget_allocation(revision_id,account_id,bucket_key),
 CHECK((project_id IS NULL AND bucket_key='operations') OR (project_id IS NOT NULL AND bucket_key=CONCAT('project:',project_id))),
 CONSTRAINT r6_budget_allocation_revision FOREIGN KEY(revision_id) REFERENCES budget_revisions(id),
 CONSTRAINT r6_budget_allocation_account FOREIGN KEY(account_id) REFERENCES Categories(CategoryID),
 CONSTRAINT r6_budget_allocation_project FOREIGN KEY(project_id) REFERENCES projects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE budget_review_events (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 revision_id INT UNSIGNED NOT NULL UNIQUE,
 decision ENUM('Approved','Returned') NOT NULL,
 reason VARCHAR(2000) NOT NULL,
 actor_id INT UNSIGNED NOT NULL,actor_name VARCHAR(100) NOT NULL,
 request_key CHAR(64) NOT NULL,request_hash CHAR(64) NOT NULL,
 reviewed_at DATETIME NOT NULL,
 UNIQUE KEY r6_budget_review_key(actor_id,request_key),
 CONSTRAINT r6_budget_review_revision FOREIGN KEY(revision_id) REFERENCES budget_revisions(id),
 CONSTRAINT r6_budget_reviewer FOREIGN KEY(actor_id) REFERENCES user_identities(UserID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DELIMITER $$
CREATE TRIGGER r6_budget_revision_update BEFORE UPDATE ON budget_revisions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submitted budget content is immutable'; END$$
CREATE TRIGGER r6_budget_revision_delete BEFORE DELETE ON budget_revisions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submitted budget content is immutable'; END$$
CREATE TRIGGER r6_budget_allocation_update BEFORE UPDATE ON budget_revision_allocations FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submitted budget allocations are immutable'; END$$
CREATE TRIGGER r6_budget_allocation_delete BEFORE DELETE ON budget_revision_allocations FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submitted budget allocations are immutable'; END$$
CREATE TRIGGER r6_budget_allocation_insert BEFORE INSERT ON budget_revision_allocations FOR EACH ROW
BEGIN
 IF NOT EXISTS(SELECT 1 FROM budget_revisions r JOIN budget_revision_drafts d ON d.id=r.draft_id WHERE r.id=NEW.revision_id AND d.state='Draft' AND d.submitted_revision_id IS NULL)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submitted budget allocations cannot be extended'; END IF;
END$$
CREATE TRIGGER r6_budget_event_update BEFORE UPDATE ON budget_review_events FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Budget approval history is immutable'; END$$
CREATE TRIGGER r6_budget_event_delete BEFORE DELETE ON budget_review_events FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Budget approval history is immutable'; END$$
CREATE TRIGGER r6_budget_draft_update BEFORE UPDATE ON budget_revision_drafts FOR EACH ROW
BEGIN
 IF OLD.state='Submitted' OR NEW.owner_id<>OLD.owner_id OR NEW.root_id<>OLD.root_id OR NEW.request_key<>OLD.request_key
 OR NEW.creation_hash<>OLD.creation_hash OR NOT(NEW.base_approved_id <=> OLD.base_approved_id)
 OR NEW.base_approval_epoch<>OLD.base_approval_epoch OR NOT(NEW.copied_revision_id <=> OLD.copied_revision_id) OR NEW.created_at<>OLD.created_at
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Budget draft identity and submitted content are immutable'; END IF;
END$$
CREATE TRIGGER r6_budget_draft_delete BEFORE DELETE ON budget_revision_drafts FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Budget draft history is retained'; END$$
CREATE TRIGGER r6_budget_root_delete BEFORE DELETE ON budget_revision_roots FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Budget approval coordination is required'; END$$
DELIMITER ;
