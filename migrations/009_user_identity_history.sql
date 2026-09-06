-- Schema checkpoint A. DDL implicitly commits; restore/repair on failure.
-- Execute through scripts/migrate_schema.php; never disable foreign-key checks.
CREATE TABLE IF NOT EXISTS user_identities (
  UserID INT UNSIGNED NOT NULL PRIMARY KEY,
  FullName VARCHAR(255) NOT NULL,
  Email VARCHAR(255) NOT NULL,
  Role VARCHAR(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO user_identities (UserID, FullName, Email, Role)
SELECT u.UserID, u.FullName, u.Email, u.Role FROM Users u
LEFT JOIN user_identities h ON h.UserID=u.UserID WHERE h.UserID IS NULL;

-- Guard each ALTER independently so an interrupted checkpoint can be resumed.
DROP PROCEDURE IF EXISTS migrate_identity_foreign_keys;
DELIMITER $$
CREATE PROCEDURE migrate_identity_foreign_keys()
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='audit_logs' AND CONSTRAINT_NAME='fk_audit_logs_user') THEN
    ALTER TABLE audit_logs DROP FOREIGN KEY fk_audit_logs_user,
      ADD CONSTRAINT fk_audit_logs_identity FOREIGN KEY (user_id)
      REFERENCES user_identities(UserID) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='board_communications' AND CONSTRAINT_NAME='fk_board_sender') THEN
    ALTER TABLE Board_Communications DROP FOREIGN KEY fk_board_sender,
      ADD CONSTRAINT fk_board_identity FOREIGN KEY (Sender_UserID)
      REFERENCES user_identities(UserID) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;
END$$
DELIMITER ;
CALL migrate_identity_foreign_keys();
DROP PROCEDURE migrate_identity_foreign_keys;
-- Neither audit rows nor their UPDATE/DELETE protection triggers are changed.
