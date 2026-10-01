-- External email send history. Apply after 009 and 013 using a verified backup.
-- Additive DDL; no internal messages or existing audit records are modified.
CREATE TABLE IF NOT EXISTS External_Communications (
  CommunicationID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  Sender_UserID INT UNSIGNED NOT NULL,
  To_Email VARCHAR(254) NOT NULL,
  From_Email VARCHAR(254) NOT NULL,
  Reply_To_Email VARCHAR(254) NOT NULL,
  Subject VARCHAR(255) NOT NULL,
  Message_Body TEXT NOT NULL,
  File_Path VARCHAR(255) NULL,
  Attachment_Name VARCHAR(255) NULL,
  Attachment_Mime VARCHAR(100) NULL,
  Attachment_Size INT UNSIGNED NULL,
  SMTP_Message_ID VARCHAR(255) NOT NULL,
  Submission_Key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  Send_Status ENUM('Sending','Sent','Failed','Unknown') NOT NULL DEFAULT 'Sending',
  Failure_Code VARCHAR(50) NULL,
  Created_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Updated_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  Sent_At TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uq_external_submission (Submission_Key),
  INDEX idx_external_sent (Send_Status, Sent_At, CommunicationID),
  INDEX idx_external_created (Send_Status, Created_At, CommunicationID),
  CONSTRAINT fk_external_sender_identity FOREIGN KEY (Sender_UserID)
    REFERENCES user_identities(UserID) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
