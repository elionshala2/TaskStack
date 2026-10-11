-- database.sql
-- Importoje ne phpMyAdmin (ose: mysql -u root < database.sql)

CREATE DATABASE IF NOT EXISTS todolist
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE todolist;

-- Userat
CREATE TABLE IF NOT EXISTS regjistrimi (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    username       VARCHAR(255) NOT NULL UNIQUE,
    email          VARCHAR(255) NOT NULL UNIQUE,
    password       VARCHAR(255) NOT NULL,
    email_verified TINYINT(1)   NOT NULL DEFAULT 0,
    provider       ENUM('local','google','apple','passkey') NOT NULL,
    google_id      VARCHAR(255) NULL UNIQUE,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Kategorite
CREATE TABLE IF NOT EXISTS categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT         NOT NULL,
    name       VARCHAR(100) NOT NULL,
    color      VARCHAR(255) DEFAULT '#3b82f6',
    is_default TINYINT(1)  NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_category (user_id, name),
    INDEX idx_category_user (user_id),
    FOREIGN KEY (user_id) REFERENCES regjistrimi(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Detyrat
CREATE TABLE IF NOT EXISTS tasks (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT         NOT NULL,
    category_id  INT UNSIGNED NULL,
    title        VARCHAR(255) NOT NULL,
    description  TEXT         NULL,
    priority     ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    due_date     DATE         NULL,
    is_completed TINYINT(1)  NOT NULL DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_tasks (user_id, is_completed),
    INDEX idx_tasks_user_category (user_id, category_id),
    FOREIGN KEY (user_id)     REFERENCES regjistrimi(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- Per rate limiting (login attempts)
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip           VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB;

-- Tokenat per verifikim emaili, reset password, ndryshim emaili dhe "anulo ndryshimin"
CREATE TABLE IF NOT EXISTS email_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT         NOT NULL,
    type       ENUM('verify','reset','email_change','revert_email','revert_password') NOT NULL,
    token_hash CHAR(64)    NOT NULL UNIQUE,
    payload    VARCHAR(255) NULL,
    expires_at DATETIME    NOT NULL,
    used_at    DATETIME    NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_type (user_id, type),
    FOREIGN KEY (user_id) REFERENCES regjistrimi(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Per limitin e kerkesave per email (mail requests)
CREATE TABLE IF NOT EXISTS mail_requests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip           VARCHAR(45) NOT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip, requested_at)
) ENGINE=InnoDB;