-- =====================================================================
-- Central de Mídia ADMoema — Envio de Fotos
-- Banco: MySQL 5.7+ / MariaDB 10.3+ (utf8mb4)
-- Importar pelo phpMyAdmin (aba "Importar"). Pode ser importado de novo:
-- todas as tabelas usam IF NOT EXISTS.
-- =====================================================================

SET NAMES utf8mb4;

-- Usuários do sistema
CREATE TABLE IF NOT EXISTS users (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                 VARCHAR(120) NOT NULL,
    email                VARCHAR(190) NOT NULL,
    password_hash        VARCHAR(255) NOT NULL,
    role                 ENUM('admin','membro') NOT NULL DEFAULT 'membro',
    is_active            TINYINT(1) NOT NULL DEFAULT 1,
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    totp_secret_enc      VARCHAR(255) NULL,
    totp_enabled         TINYINT(1) NOT NULL DEFAULT 0,
    totp_last_step       BIGINT NULL,
    recovery_codes       TEXT NULL,
    session_version      INT UNSIGNED NOT NULL DEFAULT 1,
    failed_logins        INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until         DATETIME NULL,
    last_login_at        DATETIME NULL,
    last_login_ip        VARCHAR(45) NULL,
    password_changed_at  DATETIME NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tentativas de login (controle de força bruta)
CREATE TABLE IF NOT EXISTS login_attempts (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip          VARCHAR(45) NOT NULL,
    email_hash  CHAR(64) NOT NULL,
    success     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attempts_ip (ip, created_at),
    KEY idx_attempts_email (email_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pastas de eventos (uma pasta física no servidor para cada registro)
CREATE TABLE IF NOT EXISTS folders (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_date  DATE NOT NULL,
    name        VARCHAR(80) NOT NULL,
    slug        VARCHAR(90) NOT NULL,
    rel_path    VARCHAR(200) NOT NULL,
    was_suggested TINYINT(1) NOT NULL DEFAULT 1,
    created_by  INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_folder_date_slug (event_date, slug),
    KEY idx_folder_date (event_date),
    CONSTRAINT fk_folder_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fotos enviadas
CREATE TABLE IF NOT EXISTS photos (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    folder_id     INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name   VARCHAR(80) NOT NULL,
    mime          VARCHAR(50) NOT NULL,
    size_bytes    BIGINT UNSIGNED NOT NULL,
    width         INT UNSIGNED NULL,
    height        INT UNSIGNED NULL,
    sha256        CHAR(64) NOT NULL,
    taken_at      DATETIME NULL,
    has_thumb     TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at    DATETIME NULL,
    deleted_by    INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_photos_folder (folder_id, deleted_at),
    KEY idx_photos_hash (folder_id, sha256),
    CONSTRAINT fk_photo_folder FOREIGN KEY (folder_id) REFERENCES folders (id),
    CONSTRAINT fk_photo_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro de ações (auditoria)
CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(60) NOT NULL,
    entity      VARCHAR(40) NULL,
    entity_id   INT UNSIGNED NULL,
    details     TEXT NULL,
    ip          VARCHAR(45) NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user (user_id, created_at),
    KEY idx_audit_action (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
