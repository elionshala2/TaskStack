-- database.sql - TaskStack: instalim i FRESKET (vetem struktura, pa te dhena)
-- Importoje ne databazen bosh qe ke zgjedhur ne phpMyAdmin (nuk krijon databaze, qe te punoje edhe ne hostet e perbashketa).
-- Lokalisht (XAMPP): krijo me pare nje databaze `todolist` (utf8mb4_unicode_ci), pastaj importo kete file.
-- Databaze ekzistuese? Mos e importo kete: perdor migration.sql.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Userat
CREATE TABLE IF NOT EXISTS regjistrimi (
    id               INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Qelesi Primar ID',
    username         VARCHAR(255) NOT NULL COMMENT 'Username duhet te jete unik',
    email            VARCHAR(255) NOT NULL COMMENT 'Email duhet te jete unik',
    password         VARCHAR(255) NOT NULL,
    email_verified   TINYINT(1)   NOT NULL DEFAULT 0,
    language         VARCHAR(2)   NOT NULL DEFAULT 'sq',          -- 'sq' | 'en' (per gjuhen e emaileve te kujtesave)
    provider         ENUM('local','google','apple','passkey') NOT NULL DEFAULT 'local',
    google_id        VARCHAR(255) NULL,
    session_version  INT UNSIGNED NOT NULL DEFAULT 1,             -- rritet kur ndryshon/rivendoset passwordi ose mbyllen sesionet e tjera
    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,             -- 2FA me kod ne email
    reminders_enabled  TINYINT(1) NOT NULL DEFAULT 1,             -- kujtesat e afateve me email
    last_reminder_on   DATE NULL,                                 -- dita e kujteses se fundit (nje email ne dite)
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, -- pa ON UPDATE, qe data e regjistrimit mos te ndryshoje
    UNIQUE KEY unique_username (username),
    UNIQUE KEY unique_email (email),
    UNIQUE KEY unique_google_id (google_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kategorite
CREATE TABLE IF NOT EXISTS categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    name       VARCHAR(100) NOT NULL,
    color      VARCHAR(255) DEFAULT '#3b82f6',
    is_default TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_category (user_id, name),
    INDEX idx_category_user (user_id),
    CONSTRAINT fk_categories_user FOREIGN KEY (user_id) REFERENCES regjistrimi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detyrat
CREATE TABLE IF NOT EXISTS tasks (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT          NOT NULL,
    category_id  INT UNSIGNED NULL,
    title        VARCHAR(255) NOT NULL,
    description  TEXT         NULL,
    priority     ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    due_date     DATE         NULL,
    is_completed TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_tasks (user_id, is_completed),
    INDEX idx_tasks_user_category (user_id, category_id),
    INDEX idx_tasks_user_due (user_id, due_date),
    CONSTRAINT fk_tasks_user     FOREIGN KEY (user_id)     REFERENCES regjistrimi(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tasks_category FOREIGN KEY (category_id) REFERENCES categories(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting (login attempts)
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip           VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limiti i kerkesave per email
CREATE TABLE IF NOT EXISTS mail_requests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip           VARCHAR(45) NOT NULL,
    requested_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip, requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tokenat per verifikim emaili, reset password, ndryshim emaili dhe "anulo ndryshimin"
CREATE TABLE IF NOT EXISTS email_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    type       ENUM('verify','reset','email_change','revert_email','revert_password') NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    payload    VARCHAR(255) NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_token_hash (token_hash),
    INDEX idx_user_type (user_id, type),
    CONSTRAINT fk_email_tokens_user FOREIGN KEY (user_id) REFERENCES regjistrimi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kodet 6-shifrore per 2FA me email
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

-- Passkeys (WebAuthn). Ruhet vetem celesi PUBLIK; celesi privat mbetet ne pajisjen e userit.
CREATE TABLE IF NOT EXISTS passkeys (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL,
    credential_id   VARCHAR(1400) CHARACTER SET ascii NOT NULL,   -- base64url
    credential_hash CHAR(64) CHARACTER SET ascii NOT NULL,        -- SHA-256 i credential_id (per kerkim)
    public_key      TEXT         NOT NULL,                        -- PEM
    sign_count      INT UNSIGNED NOT NULL DEFAULT 0,
    name            VARCHAR(100) NOT NULL DEFAULT 'Passkey',
    transports      VARCHAR(100) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at    TIMESTAMP    NULL DEFAULT NULL,
    UNIQUE KEY uq_passkey_hash (credential_hash),
    INDEX idx_passkeys_user (user_id),
    CONSTRAINT fk_passkeys_user FOREIGN KEY (user_id) REFERENCES regjistrimi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
