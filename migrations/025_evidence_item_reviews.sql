-- Optional private evidence-detail revisions. Additive; apply once on an authorized target.
CREATE TABLE evidence_item_review_roots (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 receipt_id INT UNSIGNED NOT NULL, context_key VARCHAR(40) NOT NULL, draft_id INT UNSIGNED NULL,
 next_revision INT UNSIGNED NOT NULL DEFAULT 1,
 UNIQUE KEY r7_review_context(receipt_id,context_key),
 CONSTRAINT r7_root_receipt FOREIGN KEY(receipt_id) REFERENCES Receipts(ReceiptID),
 CONSTRAINT r7_root_draft FOREIGN KEY(draft_id) REFERENCES journal_drafts(id),
 CHECK((draft_id IS NULL AND context_key='intake') OR (draft_id IS NOT NULL AND context_key=CONCAT('draft:',draft_id)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE evidence_item_reviews (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, root_id INT UNSIGNED NOT NULL,
 revision_number INT UNSIGNED NOT NULL, actor_id INT UNSIGNED NOT NULL, actor_name VARCHAR(100) NOT NULL,
 confirmed_at DATETIME NOT NULL, source_hash CHAR(64) NOT NULL, attempt_id INT UNSIGNED NULL,
 request_key CHAR(64) NOT NULL, request_hash CHAR(64) NOT NULL, payload_hash CHAR(64) NOT NULL,
 payload LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK(JSON_VALID(payload)),
 UNIQUE KEY r7_review_sequence(root_id,revision_number), UNIQUE KEY r7_review_request(actor_id,request_key),
 CONSTRAINT r7_review_root FOREIGN KEY(root_id) REFERENCES evidence_item_review_roots(id),
 CONSTRAINT r7_review_actor FOREIGN KEY(actor_id) REFERENCES user_identities(UserID),
 CONSTRAINT r7_review_attempt FOREIGN KEY(attempt_id) REFERENCES receipt_ocr_attempts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE evidence_item_review_links (
 association_id INT UNSIGNED NOT NULL PRIMARY KEY, review_id INT UNSIGNED NOT NULL,
 CONSTRAINT r7_link_association FOREIGN KEY(association_id) REFERENCES posted_evidence_associations(id),
 CONSTRAINT r7_link_review FOREIGN KEY(review_id) REFERENCES evidence_item_reviews(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DELIMITER $$
CREATE TRIGGER r7_review_update BEFORE UPDATE ON evidence_item_reviews FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Confirmed evidence details are immutable'; END$$
CREATE TRIGGER r7_review_delete BEFORE DELETE ON evidence_item_reviews FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Confirmed evidence details are immutable'; END$$
CREATE TRIGGER r7_link_update BEFORE UPDATE ON evidence_item_review_links FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted item review links are immutable'; END$$
CREATE TRIGGER r7_link_delete BEFORE DELETE ON evidence_item_review_links FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted item review links are immutable'; END$$
CREATE TRIGGER r7_root_delete BEFORE DELETE ON evidence_item_review_roots FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Evidence review sequence history is retained'; END$$
DELIMITER ;
