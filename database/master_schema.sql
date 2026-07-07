-- =============================================================================
-- CONECTA360 — BANCO MASTER (dema5738_conecta360)
-- Arquitetura SaaS Multi-Tenant | Versão 1.0.0
-- Gerado em: 2026-07-07
-- Charset: utf8mb4 | Collation: utf8mb4_unicode_ci | Engine: InnoDB
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------------------------------
-- Tabela: plans
-- Planos disponíveis na plataforma (Free, Starter, Pro, Enterprise)
-- Decisão: usar JSON para `features` pois a lista pode crescer sem ALTER TABLE
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `plans` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK — identificador do plano',
    `name`             VARCHAR(100)    NOT NULL                COMMENT 'Nome exibível: Starter, Pro, Enterprise',
    `slug`             VARCHAR(100)    NOT NULL                COMMENT 'Slug único para referência interna: starter',
    `description`      TEXT                                    COMMENT 'Descrição marketing do plano',
    `max_users`        INT UNSIGNED    NOT NULL DEFAULT 10     COMMENT 'Máximo de usuários ativos no tenant',
    `max_workspaces`   INT UNSIGNED    NOT NULL DEFAULT 5      COMMENT 'Máximo de workspaces criados',
    `max_boards`       INT UNSIGNED    NOT NULL DEFAULT 20     COMMENT 'Máximo de boards no tenant',
    `max_storage_mb`   INT UNSIGNED    NOT NULL DEFAULT 1024   COMMENT 'Cota de armazenamento de arquivos em MB',
    `max_automations`  INT UNSIGNED    NOT NULL DEFAULT 10     COMMENT 'Máximo de automações ativas por board',
    `price_monthly`    DECIMAL(10,2)   NOT NULL DEFAULT 0.00   COMMENT 'Preço mensal em BRL',
    `price_yearly`     DECIMAL(10,2)   NOT NULL DEFAULT 0.00   COMMENT 'Preço anual em BRL (geralmente com desconto)',
    `trial_days`       TINYINT UNSIGNED NOT NULL DEFAULT 14    COMMENT 'Dias de trial gratuito ao ativar',
    `features`         JSON                                    COMMENT 'Array JSON de features habilitadas: ["automations","dashboards","api"]',
    `is_active`        TINYINT(1)      NOT NULL DEFAULT 1      COMMENT '1=plano disponível para novos contratos',
    `sort_order`       INT UNSIGNED    NOT NULL DEFAULT 0      COMMENT 'Ordem de exibição na página de preços',
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_plans_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Planos de assinatura disponíveis na plataforma';

-- -----------------------------------------------------------------------------
-- Tabela: tenants
-- Cada registro representa um cliente (hospital) com banco próprio
-- Decisão: `database_name` é imutável após criação — chave de roteamento do sistema
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tenants` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK — identificador do tenant',
    `name`             VARCHAR(200)    NOT NULL                COMMENT 'Razão social ou nome do hospital',
    `subdomain`        VARCHAR(100)    NOT NULL                COMMENT 'Subdomínio exclusivo: hcor → hcor.conecta360.com',
    `database_name`    VARCHAR(100)    NOT NULL                COMMENT 'Nome do banco isolado: dema5738_hcor (imutável)',
    `cnpj`             VARCHAR(20)                             COMMENT 'CNPJ formatado: 00.000.000/0001-00',
    `email`            VARCHAR(255)    NOT NULL                COMMENT 'E-mail principal do contrato',
    `phone`            VARCHAR(30)                             COMMENT 'Telefone principal com DDD',
    `address`          JSON                                    COMMENT 'Endereço estruturado: {logradouro, numero, cidade, estado, cep}',
    `logo_url`         VARCHAR(500)                            COMMENT 'URL absoluta do logotipo do hospital',
    `primary_color`    VARCHAR(7)      NOT NULL DEFAULT '#0073ea' COMMENT 'Cor primária HEX para white-label',
    `status`           ENUM('pending','active','suspended','cancelled') NOT NULL DEFAULT 'pending'
                                                               COMMENT 'Status do tenant: pending=aguardando ativação',
    `activated_at`     DATETIME                                COMMENT 'Timestamp da primeira ativação',
    `suspended_at`     DATETIME                                COMMENT 'Timestamp da suspensão (inadimplência/solicitação)',
    `cancelled_at`     DATETIME                                COMMENT 'Timestamp do cancelamento definitivo',
    `timezone`         VARCHAR(50)     NOT NULL DEFAULT 'America/Sao_Paulo' COMMENT 'Timezone padrão do tenant',
    `locale`           VARCHAR(10)     NOT NULL DEFAULT 'pt_BR' COMMENT 'Locale padrão: pt_BR, en_US',
    `metadata`         JSON                                    COMMENT 'Dados extras: integração, configurações especiais',
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`       DATETIME                                COMMENT 'Soft delete — tenant nunca é removido fisicamente',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tenants_subdomain`     (`subdomain`),
    UNIQUE KEY `uq_tenants_database_name` (`database_name`),
    INDEX `idx_tenants_status`            (`status`),
    INDEX `idx_tenants_deleted_at`        (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Clientes (hospitais) da plataforma — cada um com banco isolado';

-- -----------------------------------------------------------------------------
-- Tabela: tenant_plan
-- Histórico de contratos: um tenant pode trocar de plano (upgrade/downgrade)
-- Decisão: manter histórico completo — nunca deletar registros anteriores
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tenant_plan` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `tenant_id`            BIGINT UNSIGNED NOT NULL                COMMENT 'FK → tenants.id',
    `plan_id`              BIGINT UNSIGNED NOT NULL                COMMENT 'FK → plans.id',
    `status`               ENUM('trial','active','past_due','cancelled','expired') NOT NULL DEFAULT 'trial'
                                                                   COMMENT 'Status do contrato atual',
    `billing_cycle`        ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly' COMMENT 'Ciclo de cobrança',
    `trial_ends_at`        DATETIME                                COMMENT 'Fim do período trial (NULL se não trial)',
    `current_period_start` DATETIME                                COMMENT 'Início do período de cobrança corrente',
    `current_period_end`   DATETIME                                COMMENT 'Fim do período — renovação esperada',
    `cancelled_at`         DATETIME                                COMMENT 'Data do cancelamento deste contrato',
    `price_override`       DECIMAL(10,2)                           COMMENT 'Preço negociado (sobrescreve plans.price_*)',
    `discount_percent`     DECIMAL(5,2)   NOT NULL DEFAULT 0.00    COMMENT 'Percentual de desconto aplicado',
    `payment_method`       ENUM('credit_card','pix','boleto','invoice') COMMENT 'Método de pagamento',
    `external_id`          VARCHAR(255)                            COMMENT 'ID no gateway de pagamento (Stripe, Asaas, etc.)',
    `metadata`             JSON                                    COMMENT 'Dados adicionais do contrato/gateway',
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_tp_tenant_id`  (`tenant_id`),
    INDEX `idx_tp_plan_id`    (`plan_id`),
    INDEX `idx_tp_status`     (`status`),
    INDEX `idx_tp_period_end` (`current_period_end`),
    CONSTRAINT `fk_tp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tp_plan`   FOREIGN KEY (`plan_id`)   REFERENCES `plans`   (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Histórico de planos contratados por tenant';

-- -----------------------------------------------------------------------------
-- Tabela: superadmins
-- Usuários internos da Conecta360 com acesso ao painel master
-- Decisão: tabela separada de `users` — segurança por isolamento
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `superadmins` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `name`                 VARCHAR(200)    NOT NULL                COMMENT 'Nome completo',
    `email`                VARCHAR(255)    NOT NULL                COMMENT 'E-mail de acesso ao master',
    `password`             VARCHAR(255)    NOT NULL                COMMENT 'Hash bcrypt — NUNCA armazenar plain text',
    `avatar_url`           VARCHAR(500)                            COMMENT 'URL do avatar',
    `role`                 ENUM('owner','admin','support','finance') NOT NULL DEFAULT 'support'
                                                                   COMMENT 'Nível de acesso ao master',
    `last_login_at`        DATETIME                                COMMENT 'Timestamp do último login',
    `last_login_ip`        VARCHAR(45)                             COMMENT 'IP do último login (suporta IPv6)',
    `failed_login_count`   TINYINT UNSIGNED NOT NULL DEFAULT 0     COMMENT 'Tentativas falhas consecutivas (reset no login ok)',
    `locked_until`         DATETIME                                COMMENT 'Conta bloqueada por brute-force até esta data',
    `is_active`            TINYINT(1)      NOT NULL DEFAULT 1      COMMENT '1=ativo, 0=desativado',
    `two_factor_secret`    VARCHAR(255)                            COMMENT 'Secret TOTP para 2FA (Google Authenticator)',
    `two_factor_enabled`   TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '1=2FA obrigatório no login',
    `remember_token`       VARCHAR(100)                            COMMENT 'Token de sessão persistente',
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`           DATETIME                                COMMENT 'Soft delete',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_superadmins_email` (`email`),
    INDEX `idx_superadmins_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Administradores internos da plataforma Conecta360';

-- -----------------------------------------------------------------------------
-- Tabela: audit_master
-- Log imutável de toda ação crítica no banco master
-- Decisão: sem UPDATE/DELETE nesta tabela — append-only por design
-- Decisão: actor_email snapshoted para preservar histórico mesmo após exclusão
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_master` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK — sequencial imutável',
    `actor_type`   ENUM('superadmin','system','api','webhook') NOT NULL DEFAULT 'superadmin'
                                                               COMMENT 'Tipo de ator que executou a ação',
    `actor_id`     BIGINT UNSIGNED                             COMMENT 'ID do ator (NULL se automação do sistema)',
    `actor_email`  VARCHAR(255)                                COMMENT 'E-mail snapshot no momento da ação',
    `action`       VARCHAR(100)    NOT NULL                    COMMENT 'Ação: tenant.created, plan.changed, superadmin.login',
    `entity_type`  VARCHAR(100)                                COMMENT 'Entidade afetada: tenant, plan, superadmin',
    `entity_id`    BIGINT UNSIGNED                             COMMENT 'ID da entidade afetada',
    `old_value`    JSON                                        COMMENT 'Estado da entidade ANTES da ação',
    `new_value`    JSON                                        COMMENT 'Estado da entidade APÓS a ação',
    `ip_address`   VARCHAR(45)                                 COMMENT 'IP de origem da requisição',
    `user_agent`   TEXT                                        COMMENT 'User-Agent do navegador/cliente',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp imutável da ação',
    PRIMARY KEY (`id`),
    INDEX `idx_audit_actor`      (`actor_type`, `actor_id`),
    INDEX `idx_audit_entity`     (`entity_type`, `entity_id`),
    INDEX `idx_audit_action`     (`action`),
    INDEX `idx_audit_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Log imutável (append-only) de ações críticas no master';

-- =============================================================================
-- DADOS INICIAIS — Planos padrão da plataforma
-- =============================================================================
INSERT IGNORE INTO `plans` (`name`, `slug`, `description`, `max_users`, `max_workspaces`, `max_boards`, `max_storage_mb`, `max_automations`, `price_monthly`, `price_yearly`, `trial_days`, `features`, `is_active`, `sort_order`) VALUES
('Gratuito',    'free',       'Ideal para times pequenos e avaliação inicial', 5,   2,   5,   256,    0,    0.00,    0.00,   0,  '["boards","items","comments"]', 1, 1),
('Starter',     'starter',    'Para equipes em crescimento com necessidades básicas', 15,  5,   20,  1024,   5,    89.90,   799.00, 14, '["boards","items","comments","automations","attachments"]', 1, 2),
('Profissional','professional','Recursos completos para grandes equipes hospitalares', 50,  20,  100, 5120,   25,   249.90,  2199.00,14, '["boards","items","comments","automations","attachments","dashboards","api","timeline","calendar"]', 1, 3),
('Enterprise',  'enterprise', 'Solução ilimitada com SLA dedicado e suporte prioritário', 999, 999, 999, 51200,  999,  0.00,    0.00,   14, '["all"]', 1, 4);

-- =============================================================================
-- SUPERADMIN INICIAL (senha: Conecta@2026 — TROCAR NO PRIMEIRO ACESSO)
-- Hash bcrypt gerado com cost=12
-- =============================================================================
INSERT IGNORE INTO `superadmins` (`name`, `email`, `password`, `role`, `is_active`) VALUES
('Administrador Master', 'admin@conecta360.com.br',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'owner', 1);

SET FOREIGN_KEY_CHECKS = 1;
