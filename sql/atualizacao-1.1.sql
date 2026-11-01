-- =====================================================================
-- Atualização 1.0 → 1.1 (visualização, editor de fotos e redes sociais)
-- Use SOMENTE se o sistema já estava instalado com a versão 1.0.
-- Instalação nova: importe apenas o schema.sql.
-- Importar pelo phpMyAdmin (aba "Importar"), uma única vez.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE users
    MODIFY role ENUM('admin','membro','visualizador') NOT NULL DEFAULT 'membro',
    ADD COLUMN social_signature VARCHAR(500) NULL AFTER password_changed_at;

ALTER TABLE photos
    ADD COLUMN parent_id INT UNSIGNED NULL AFTER folder_id,
    ADD KEY idx_photos_parent (parent_id);

CREATE TABLE IF NOT EXISTS social_accounts (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED NOT NULL,
    network          ENUM('facebook','instagram') NOT NULL,
    api_host         ENUM('facebook','instagram') NOT NULL,
    external_id      VARCHAR(64) NOT NULL,
    display_name     VARCHAR(190) NOT NULL,
    token_enc        TEXT NOT NULL,
    token_hint       VARCHAR(12) NOT NULL,
    token_expires_at DATETIME NULL,
    token_updated_at DATETIME NOT NULL,
    last_error       VARCHAR(500) NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_social_user_network (user_id, network),
    CONSTRAINT fk_social_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_posts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    photo_id     INT UNSIGNED NOT NULL,
    network      ENUM('facebook','instagram') NOT NULL,
    status       ENUM('publicado','erro') NOT NULL,
    external_id  VARCHAR(64) NULL,
    permalink    VARCHAR(500) NULL,
    caption      TEXT NULL,
    error        VARCHAR(500) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_posts_photo (photo_id),
    KEY idx_posts_user (user_id, created_at),
    CONSTRAINT fk_post_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_post_photo FOREIGN KEY (photo_id) REFERENCES photos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
