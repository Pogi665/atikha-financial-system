-- Additive designation only. Apply once to an explicitly authorized target.
-- No automatic account selection, triggers, historical migration replay or USE.
-- Initialize separately with scripts/initialize_primary_administrator.php.
CREATE TABLE system_primary_administrator (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    designated_by INT UNSIGNED NOT NULL,
    designated_at DATETIME NOT NULL,
    schema_version TINYINT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY primary_administrator_user (user_id),
    CONSTRAINT primary_administrator_singleton CHECK (id = 1),
    CONSTRAINT primary_administrator_version CHECK (schema_version = 1),
    CONSTRAINT primary_administrator_account FOREIGN KEY (user_id)
        REFERENCES Users(UserID) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT primary_administrator_actor FOREIGN KEY (designated_by)
        REFERENCES user_identities(UserID) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
