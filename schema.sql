-- =============================================================
-- SQL — Shamir Login System
-- Executar cada bloco no banco correspondente
-- =============================================================

-- -------------------------------------------------------
-- DB1: banco principal — tabelas users + shares (share 1)
-- -------------------------------------------------------
CREATE DATABASE IF NOT EXISTS shamir_db1
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE shamir_db1;

CREATE TABLE IF NOT EXISTS users (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email      VARCHAR(255)    NOT NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- shares do DB1: armazena o share de índice 1
CREATE TABLE IF NOT EXISTS shares (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    share_index TINYINT UNSIGNED NOT NULL COMMENT '1-based index do share',
    share_value TEXT            NOT NULL COMMENT 'share codificado em base64',
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_share (user_id, share_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- DB2: banco secundário — somente tabela shares (share 2)
-- -------------------------------------------------------
CREATE DATABASE IF NOT EXISTS shamir_db2
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE shamir_db2;

CREATE TABLE IF NOT EXISTS shares (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    share_index TINYINT UNSIGNED NOT NULL,
    share_value TEXT            NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_share (user_id, share_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- DB3: banco secundário — somente tabela shares (share 3)
-- -------------------------------------------------------
CREATE DATABASE IF NOT EXISTS shamir_db3
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE shamir_db3;

CREATE TABLE IF NOT EXISTS shares (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    share_index TINYINT UNSIGNED NOT NULL,
    share_value TEXT            NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_share (user_id, share_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- DB4: banco secundário — somente tabela shares (share 4)
-- -------------------------------------------------------
CREATE DATABASE IF NOT EXISTS shamir_db4
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE shamir_db4;

CREATE TABLE IF NOT EXISTS shares (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    share_index TINYINT UNSIGNED NOT NULL,
    share_value TEXT            NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_share (user_id, share_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;