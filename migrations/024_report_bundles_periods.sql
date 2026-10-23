-- Complete immutable monthly reports and posting controls. Additive after022/023.
-- Apply once only to an explicitly authorized target; DDL may commit implicitly.
CREATE TABLE complete_report_roots (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 report_month DATE NOT NULL UNIQUE CHECK(DAY(report_month)=1),
 next_revision INT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE complete_report_bundles (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,root_id INT UNSIGNED NOT NULL,
 revision_number INT UNSIGNED NOT NULL,period_epoch INT UNSIGNED NOT NULL,
 prepared_by INT UNSIGNED NOT NULL,prepared_at DATETIME NOT NULL,prepared_name VARCHAR(100) NOT NULL,
 request_key CHAR(64) NOT NULL,request_hash CHAR(64) NOT NULL,
 source_fingerprint CHAR(64) NOT NULL,payload_hash CHAR(64) NOT NULL,
 payload LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK(JSON_VALID(payload)),
 UNIQUE KEY r5_bundle_revision(root_id,revision_number),UNIQUE KEY r5_bundle_request(prepared_by,request_key),
 CONSTRAINT r5_bundle_root FOREIGN KEY(root_id) REFERENCES complete_report_roots(id),
 CONSTRAINT r5_bundle_preparer FOREIGN KEY(prepared_by) REFERENCES user_identities(UserID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE complete_report_reviews (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,bundle_id INT UNSIGNED NOT NULL UNIQUE,
 decision ENUM('Reviewed','Returned') NOT NULL,reason VARCHAR(2000) NOT NULL,
 actor_id INT UNSIGNED NOT NULL,actor_name VARCHAR(100) NOT NULL,reviewed_at DATETIME NOT NULL,
 request_key CHAR(64) NOT NULL,request_hash CHAR(64) NOT NULL,
 UNIQUE KEY r5_bundle_review_request(actor_id,request_key),
 CONSTRAINT r5_bundle_review_bundle FOREIGN KEY(bundle_id) REFERENCES complete_report_bundles(id),
 CONSTRAINT r5_bundle_reviewer FOREIGN KEY(actor_id) REFERENCES user_identities(UserID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE accounting_period_events (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,event_kind ENUM('Closed','Reopened') NOT NULL,
 actor_id INT UNSIGNED NOT NULL,actor_name VARCHAR(100) NOT NULL,event_at DATETIME NOT NULL,
 reason VARCHAR(2000) NOT NULL,request_key CHAR(64) NOT NULL,request_hash CHAR(64) NOT NULL,
 payload LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK(JSON_VALID(payload)),payload_hash CHAR(64) NOT NULL,
 UNIQUE KEY r5_period_event_request(actor_id,request_key),
 CONSTRAINT r5_period_actor FOREIGN KEY(actor_id) REFERENCES user_identities(UserID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE accounting_period_roots (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 period_month DATE NOT NULL UNIQUE CHECK(DAY(period_month)=1),
 state ENUM('Open','Closed') NOT NULL DEFAULT 'Open',epoch INT UNSIGNED NOT NULL DEFAULT 0,
 closing_bundle_id INT UNSIGNED NULL,closing_review_id INT UNSIGNED NULL,last_event_id INT UNSIGNED NULL,
 CONSTRAINT r5_period_bundle FOREIGN KEY(closing_bundle_id) REFERENCES complete_report_bundles(id),
 CONSTRAINT r5_period_review FOREIGN KEY(closing_review_id) REFERENCES complete_report_reviews(id),
 CONSTRAINT r5_period_event FOREIGN KEY(last_event_id) REFERENCES accounting_period_events(id),
 CHECK((state='Open' AND closing_bundle_id IS NULL AND closing_review_id IS NULL) OR (state='Closed' AND closing_bundle_id IS NOT NULL AND closing_review_id IS NOT NULL)),
 CHECK((epoch=0 AND state='Open' AND last_event_id IS NULL) OR (epoch>0 AND last_event_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DELIMITER $$
CREATE TRIGGER r5_bundle_immutable_update BEFORE UPDATE ON complete_report_bundles FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submitted complete reports are immutable'; END$$
CREATE TRIGGER r5_bundle_immutable_delete BEFORE DELETE ON complete_report_bundles FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submitted complete reports are immutable'; END$$
CREATE TRIGGER r5_bundle_review_update BEFORE UPDATE ON complete_report_reviews FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Complete report reviews are immutable'; END$$
CREATE TRIGGER r5_bundle_review_delete BEFORE DELETE ON complete_report_reviews FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Complete report reviews are immutable'; END$$
CREATE TRIGGER r5_bundle_root_delete BEFORE DELETE ON complete_report_roots FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Complete report sequence history is retained'; END$$
CREATE TRIGGER r5_period_event_update BEFORE UPDATE ON accounting_period_events FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Period events are immutable'; END$$
CREATE TRIGGER r5_period_event_delete BEFORE DELETE ON accounting_period_events FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Period events are immutable'; END$$
CREATE TRIGGER r5_period_root_delete BEFORE DELETE ON accounting_period_roots FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Period control history is retained'; END$$
DELIMITER ;
