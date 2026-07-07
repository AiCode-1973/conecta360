-- =============================================================================
-- CONECTA360 — TABELAS DE AUTENTICAÇÃO (Banco Tenant)
-- Complemento ao tenant_schema.sql
-- Charset: utf8mb4 | Collation: utf8mb4_unicode_ci | Engine: InnoDB
-- =============================================================================
-- Execute DENTRO do banco do tenant após tenant_schema.sql
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Tabela: password_resets
-- Tokens de recuperação de senha com vida útil limitada
--
-- SEGURANÇA:
--   • O `token` armazenado é o HASH SHA-256 do token enviado por e-mail
--   • O token raw NUNCA é persistido — só o hash
--   • `used_at` não permite reuso do mesmo token
--   • Expiração em 60 min por padrão (verificada na aplicação)
--   • Índice em `email` para revogar todos os tokens do usuário ao redefinir
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `email`      VARCHAR(255)    NOT NULL                COMMENT 'E-mail do usuário que solicitou a redefinição',
    `token_hash` VARCHAR(64)     NOT NULL                COMMENT 'SHA-256 do token enviado por e-mail (raw nunca persiste)',
    `ip_address` VARCHAR(45)                             COMMENT 'IP de quem solicitou o reset',
    `expires_at` DATETIME        NOT NULL                COMMENT 'Expiração: created_at + 60 min',
    `used_at`    DATETIME                                COMMENT 'Quando o token foi consumido (NULL=ainda válido)',
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_pr_email`      (`email`),
    INDEX `idx_pr_token_hash` (`token_hash`),
    INDEX `idx_pr_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tokens de recuperação de senha — hash-only, expiração em 60 min';

-- -----------------------------------------------------------------------------
-- Tabela: email_verifications
-- Confirma que o e-mail do usuário pertence a ele
--
-- SEGURANÇA:
--   • Mesmo padrão hash-only do password_resets
--   • Expiração em 24h
--   • `verified_at` impede reuso
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_verifications` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `user_id`     BIGINT UNSIGNED NOT NULL                COMMENT 'FK → users.id',
    `email`       VARCHAR(255)    NOT NULL                COMMENT 'E-mail a ser verificado (snapshot no momento da criação)',
    `token_hash`  VARCHAR(64)     NOT NULL                COMMENT 'SHA-256 do token enviado por e-mail',
    `expires_at`  DATETIME        NOT NULL                COMMENT 'Expiração: created_at + 24h',
    `verified_at` DATETIME                                COMMENT 'Quando o usuário confirmou o e-mail',
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ev_user_id`    (`user_id`),
    INDEX `idx_ev_token_hash` (`token_hash`),
    INDEX `idx_ev_expires_at` (`expires_at`),
    CONSTRAINT `fk_ev_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tokens de verificação de e-mail — hash-only, expiração em 24h';

-- -----------------------------------------------------------------------------
-- Tabela: user_sessions
-- Sessões persistentes ("lembrar-me") e multi-dispositivo
--
-- SEGURANÇA:
--   • `session_token_hash` = SHA-256 do cookie "remember_me" enviado ao browser
--   • Rotação de token a cada uso (sliding expiration)
--   • `fingerprint_hash` = SHA-256 de (User-Agent + Accept-Language) — detecta hijacking
--   • Revogar todas as sessões ao trocar senha
--   • `last_seen_ip` rastreado para alertas de acesso incomum
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `user_id`              BIGINT UNSIGNED NOT NULL                COMMENT 'FK → users.id',
    `session_token_hash`   VARCHAR(64)     NOT NULL                COMMENT 'SHA-256 do token "remember_me" — raw no cookie, hash no banco',
    `fingerprint_hash`     VARCHAR(64)                             COMMENT 'SHA-256(User-Agent + Accept-Language) — detecção de hijacking',
    `ip_address`           VARCHAR(45)                             COMMENT 'IP no momento da criação da sessão',
    `last_seen_ip`         VARCHAR(45)                             COMMENT 'Último IP registrado (atualizado a cada requisição)',
    `user_agent`           VARCHAR(500)                            COMMENT 'User-Agent completo (armazenado para auditoria)',
    `device_type`          ENUM('desktop','mobile','tablet','api','unknown') NOT NULL DEFAULT 'unknown' COMMENT 'Tipo de dispositivo inferido',
    `last_activity_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Última requisição autenticada — base para sliding expiration',
    `expires_at`           DATETIME        NOT NULL                COMMENT 'Expiração absoluta: created_at + 30 dias',
    `revoked_at`           DATETIME                                COMMENT 'Sessão revogada manualmente (logout, troca de senha)',
    `created_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_us_token_hash`      (`session_token_hash`),
    INDEX `idx_us_user_id`             (`user_id`),
    INDEX `idx_us_expires_at`          (`expires_at`),
    INDEX `idx_us_last_activity_at`    (`last_activity_at`),
    CONSTRAINT `fk_us_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sessões persistentes (remember me) e controle multi-dispositivo';

-- -----------------------------------------------------------------------------
-- Tabela: login_attempts
-- Controle de brute-force por IP e por e-mail
--
-- SEGURANÇA:
--   • Bloqueio por IP (10 tentativas/15 min) E por e-mail (5 tentativas/10 min)
--   • Não revela qual dos dois está bloqueado (evita login enumeration)
--   • `success` registra o login bem-sucedido também (rastreio de anomalias)
--   • Limpeza periódica de registros com mais de 24h (cron job)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `identifier`  VARCHAR(255)    NOT NULL                COMMENT 'E-mail tentado (pode não existir — não confirmar ao usuário)',
    `ip_address`  VARCHAR(45)     NOT NULL                COMMENT 'IP de origem da tentativa',
    `success`     TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '1=login bem-sucedido (rastreio de acesso legítimo)',
    `user_agent`  VARCHAR(500)                            COMMENT 'User-Agent para análise forense',
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_la_identifier`  (`identifier`, `created_at`),
    INDEX `idx_la_ip_address`  (`ip_address`, `created_at`),
    INDEX `idx_la_success`     (`success`),
    INDEX `idx_la_created_at`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tentativas de login para proteção anti brute-force';

-- -----------------------------------------------------------------------------
-- Tabela: csrf_tokens
-- OPCIONAL: se preferir CSRF server-side ao invés de sessão PHP
-- Recomendado quando houver API stateless futura
-- Em aplicação server-side pura, armazenar na $_SESSION é suficiente
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `csrf_tokens` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `user_id`    BIGINT UNSIGNED                         COMMENT 'FK → users.id (NULL se formulário público como login)',
    `token_hash` VARCHAR(64)     NOT NULL                COMMENT 'SHA-256 do token CSRF',
    `form_id`    VARCHAR(100)                            COMMENT 'Identificador do formulário: login, register, settings',
    `used_at`    DATETIME                                COMMENT 'Consumido em (token de uso único)',
    `expires_at` DATETIME        NOT NULL                COMMENT 'Expiração: geração + 30 min',
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_csrf_token_hash` (`token_hash`),
    INDEX `idx_csrf_user_id`        (`user_id`),
    INDEX `idx_csrf_expires_at`     (`expires_at`),
    CONSTRAINT `fk_csrf_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='CSRF tokens server-side (opcional — usar $_SESSION para apps SSR simples)';

SET FOREIGN_KEY_CHECKS = 1;
