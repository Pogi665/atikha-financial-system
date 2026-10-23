-- Stage5 reporting setup. Apply once after preflight/backup only under deployment authorization.
-- Implementation rehearsals: new guarded disposable database only. No hardcoded USE/database.
-- MariaDB DDL commits implicitly. A partial apply is a stop, not permission to rerun blindly.
CREATE TABLE report_configuration_drafts (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 owner_id INT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 request_key CHAR(64) NOT NULL,
 creation_hash CHAR(64) NOT NULL,
 payload LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (JSON_VALID(payload)),
 state ENUM('Draft','Submitted') NOT NULL DEFAULT 'Draft',
 base_revision_id INT UNSIGNED NULL,
 submitted_revision_id INT UNSIGNED NULL,
 created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
 UNIQUE KEY r5_config_owner_key(owner_id,request_key),
 CONSTRAINT r5_config_owner FOREIGN KEY(owner_id) REFERENCES user_identities(UserID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE report_configuration_revisions (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 revision_number INT UNSIGNED NOT NULL UNIQUE,
 draft_id INT UNSIGNED NOT NULL UNIQUE,
 effective_from DATE NOT NULL,
 parent_revision_id INT UNSIGNED NULL,
 payload LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (JSON_VALID(payload)),
 payload_hash CHAR(64) NOT NULL,
 draft_hash CHAR(64) NOT NULL,
 prepared_by INT UNSIGNED NOT NULL, prepared_at DATETIME NOT NULL,
 KEY r5_config_effective(effective_from,revision_number),
 CONSTRAINT r5_config_source_draft FOREIGN KEY(draft_id) REFERENCES report_configuration_drafts(id),
 CONSTRAINT r5_config_parent FOREIGN KEY(parent_revision_id) REFERENCES report_configuration_revisions(id),
 CONSTRAINT r5_config_preparer FOREIGN KEY(prepared_by) REFERENCES user_identities(UserID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE report_configuration_drafts ADD CONSTRAINT r5_config_submitted_revision
 FOREIGN KEY(submitted_revision_id) REFERENCES report_configuration_revisions(id);
ALTER TABLE report_configuration_drafts ADD CONSTRAINT r5_config_base_revision
 FOREIGN KEY(base_revision_id) REFERENCES report_configuration_revisions(id);

CREATE TABLE report_configuration_state (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY CHECK (id=1),
 schema_version SMALLINT UNSIGNED NOT NULL CHECK (schema_version=1),
 current_revision_id INT UNSIGNED NULL,
 next_sequence INT UNSIGNED NOT NULL DEFAULT 1,
 CONSTRAINT r5_config_current FOREIGN KEY(current_revision_id) REFERENCES report_configuration_revisions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO report_configuration_state(id,schema_version,current_revision_id,next_sequence) VALUES(1,1,NULL,1);

DELIMITER $$
CREATE TRIGGER r5_config_revision_no_update BEFORE UPDATE ON report_configuration_revisions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Published report configuration is immutable'; END$$
CREATE TRIGGER r5_config_revision_no_delete BEFORE DELETE ON report_configuration_revisions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Published report configuration is immutable'; END$$
CREATE TRIGGER r5_config_draft_update BEFORE UPDATE ON report_configuration_drafts FOR EACH ROW
BEGIN
 IF OLD.state='Submitted' OR NEW.owner_id<>OLD.owner_id OR NEW.request_key<>OLD.request_key
 OR NOT (NEW.base_revision_id <=> OLD.base_revision_id)
 OR NEW.creation_hash<>OLD.creation_hash OR NEW.created_at<>OLD.created_at
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submitted configuration and draft identity are immutable'; END IF;
END$$
CREATE TRIGGER r5_config_draft_delete BEFORE DELETE ON report_configuration_drafts FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Report setup draft history is retained'; END$$
CREATE TRIGGER r5_config_state_delete BEFORE DELETE ON report_configuration_state FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reporting coordination state is required'; END$$
DELIMITER ;
