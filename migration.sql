-- migration.sql - perditeson nje databaze EKZISTUESE (lokale ose ne host) ne strukturen e re.
-- I sigurt per t'u ekzekutuar disa here: cdo hap kontrollon nese ekziston para se te ndryshoje.
-- Behet BACKUP (Export ne phpMyAdmin) para se ta ekzekutosh.
-- Funksionon ne MySQL 5.7+ dhe MariaDB 10.3+. Importoje ne databazen e aplikacionit.

SET NAMES utf8mb4;

-- ---------- 1. Tabelat qe mungonin ----------
CREATE TABLE IF NOT EXISTS two_factor_codes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT              NOT NULL,
    code_hash  CHAR(64)         NOT NULL,
    attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME         NOT NULL,
    created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_2fa_user (user_id),
    CONSTRAINT fk_2fa_user FOREIGN KEY (user_id) REFERENCES regjistrimi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS passkeys (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL,
    credential_id   VARCHAR(1400) CHARACTER SET ascii NOT NULL,
    credential_hash CHAR(64) CHARACTER SET ascii NOT NULL,
    public_key      TEXT         NOT NULL,
    sign_count      INT UNSIGNED NOT NULL DEFAULT 0,
    name            VARCHAR(100) NOT NULL DEFAULT 'Passkey',
    transports      VARCHAR(100) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at    TIMESTAMP    NULL DEFAULT NULL,
    UNIQUE KEY uq_passkey_hash (credential_hash),
    INDEX idx_passkeys_user (user_id),
    CONSTRAINT fk_passkeys_user FOREIGN KEY (user_id) REFERENCES regjistrimi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 2. Kolonat e reja ne regjistrimi (vetem nese mungojne) ----------
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE regjistrimi ADD COLUMN reminders_enabled TINYINT(1) NOT NULL DEFAULT 1', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'regjistrimi' AND COLUMN_NAME = 'reminders_enabled');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE regjistrimi ADD COLUMN last_reminder_on DATE NULL', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'regjistrimi' AND COLUMN_NAME = 'last_reminder_on');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE regjistrimi ADD COLUMN language VARCHAR(2) NOT NULL DEFAULT ''sq''', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'regjistrimi' AND COLUMN_NAME = 'language');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE regjistrimi ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'regjistrimi' AND COLUMN_NAME = 'two_factor_enabled');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE regjistrimi ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'regjistrimi' AND COLUMN_NAME = 'session_version');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Gjuha: vlera e vjeter 'al' behet 'sq' (kodi perdor 'sq' dhe 'en')
ALTER TABLE regjistrimi MODIFY language VARCHAR(2) NOT NULL DEFAULT 'sq';
UPDATE regjistrimi SET language = 'sq' WHERE language NOT IN ('sq', 'en');

-- ---------- 3. Korrigjime te strukturës ----------
-- created_at nuk duhet te ndryshoje sa here perditesohet rreshti (data "anetar qe nga" po ndryshonte).
-- Shenim: datat e regjistrimit qe u mbishkruan me pare nuk rikthehen dot.
ALTER TABLE regjistrimi MODIFY created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE regjistrimi MODIFY provider ENUM('local','google','apple','passkey') NOT NULL DEFAULT 'local';
ALTER TABLE regjistrimi MODIFY email_verified TINYINT(1) NOT NULL DEFAULT 0;

-- google_id duhet te jete unik (nje llogari Google = nje llogari TaskStack)
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE regjistrimi ADD UNIQUE KEY unique_google_id (google_id)', 'DO 0')
          FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'regjistrimi' AND COLUMN_NAME = 'google_id' AND NON_UNIQUE = 0);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- indeksi per afatet
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tasks ADD INDEX idx_tasks_user_due (user_id, due_date)', 'DO 0')
          FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND INDEX_NAME = 'idx_tasks_user_due');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- 4. Çelesat e huaj qe mungojne ----------
-- Pastro kategorite "jetime" (te userave qe nuk ekzistojne me), pastaj shto lidhjet.
DELETE FROM categories WHERE user_id NOT IN (SELECT id FROM regjistrimi);
UPDATE tasks SET category_id = NULL WHERE category_id IS NOT NULL AND category_id NOT IN (SELECT id FROM categories);

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE categories ADD CONSTRAINT fk_categories_user FOREIGN KEY (user_id) REFERENCES regjistrimi(id) ON DELETE CASCADE', 'DO 0')
          FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = 'regjistrimi');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tasks ADD CONSTRAINT fk_tasks_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL', 'DO 0')
          FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'category_id' AND REFERENCED_TABLE_NAME = 'categories');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
