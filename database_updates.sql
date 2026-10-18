-- database_updates.sql  (ekzekutoje ne databazen "todolist" ne phpMyAdmin)

-- 1) Flamuri 2FA per userat
ALTER TABLE regjistrimi
    ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER session_version;

-- 2) Kodet e 2FA (ruhet vetem hash-i, jo kodi)
CREATE TABLE IF NOT EXISTS two_factor_codes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT        NOT NULL,
    code_hash  CHAR(64)   NOT NULL,
    attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME   NOT NULL,
    created_at TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_2fa_user (user_id),
    FOREIGN KEY (user_id) REFERENCES regjistrimi(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3) (Opsionale, per performance) indeks per renditjen sipas afatit
ALTER TABLE tasks ADD INDEX idx_tasks_user_due (user_id, due_date);
