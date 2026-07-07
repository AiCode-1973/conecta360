-- =============================================================================
-- CONECTA360 — Board Module Migration
-- Tabelas complementares ao módulo de boards (que não existem em tenant_schema.sql)
-- Execute no banco: dema5738_conecta360
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Tabela: board_column_options
-- Opções de colunas do tipo status/dropdown (alternativa ao JSON em settings)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `board_column_options` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `column_id`   BIGINT UNSIGNED NOT NULL COMMENT 'FK → board_columns.id',
    `slug`        VARCHAR(80)  NOT NULL,
    `label`       VARCHAR(100) NOT NULL,
    `color`       VARCHAR(7)   NOT NULL DEFAULT '#c4c4c4',
    `position`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_default`  TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_bco_col_slug` (`column_id`, `slug`),
    INDEX `idx_bco_column_id` (`column_id`),
    CONSTRAINT `fk_bco_column` FOREIGN KEY (`column_id`) REFERENCES `board_columns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Opções de colunas tipo status/dropdown';

-- ------------------------------------------------------------
-- Tabela: board_views
-- Visões salvas por usuário (tabela, kanban, calendário)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `board_views` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `board_id`      BIGINT UNSIGNED NOT NULL COMMENT 'FK → boards.id',
    `user_id`       BIGINT UNSIGNED NULL     COMMENT 'NULL = visão global do board',
    `name`          VARCHAR(100) NOT NULL DEFAULT 'Principal',
    `type`          ENUM('table','kanban','calendar','timeline') NOT NULL DEFAULT 'table',
    `is_default`    TINYINT(1) NOT NULL DEFAULT 0,
    `settings_json` JSON NULL COMMENT 'colunas visíveis, agrupamento, filtros, ordem',
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_bv_board_id` (`board_id`),
    INDEX `idx_bv_user_id`  (`user_id`),
    CONSTRAINT `fk_bv_board` FOREIGN KEY (`board_id`) REFERENCES `boards` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bv_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Visões salvas por board (tabela, kanban, calendário)';

-- ------------------------------------------------------------
-- Tabela: board_filters
-- Filtros salvos por visão
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `board_filters` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `view_id`     BIGINT UNSIGNED NOT NULL COMMENT 'FK → board_views.id',
    `column_id`   BIGINT UNSIGNED NULL     COMMENT 'NULL = campo fixo (title, assignee, etc.)',
    `field_name`  VARCHAR(80)  NOT NULL,
    `operator`    VARCHAR(20)  NOT NULL DEFAULT 'eq'
                               COMMENT 'eq,neq,gt,lt,contains,in,between,is_empty,is_not_empty',
    `value_json`  JSON NULL    COMMENT 'Valor(es) do filtro',
    `position`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_bf_view_id` (`view_id`),
    CONSTRAINT `fk_bf_view` FOREIGN KEY (`view_id`) REFERENCES `board_views` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Filtros salvos por visão de board';

-- ------------------------------------------------------------
-- Tabela: item_activity_logs
-- Histórico de ações específicas por item (linha do tempo)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `item_activity_logs` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_id`        BIGINT UNSIGNED NOT NULL COMMENT 'FK → items.id',
    `board_id`       BIGINT UNSIGNED NOT NULL COMMENT 'Desnormalizado para queries rápidas',
    `user_id`        BIGINT UNSIGNED NULL,
    `action`         VARCHAR(60) NOT NULL
                     COMMENT 'item.created,value.changed,item.moved,item.archived,comment.added',
    `column_id`      BIGINT UNSIGNED NULL COMMENT 'Coluna alterada (quando action=value.changed)',
    `old_value_json` JSON NULL,
    `new_value_json` JSON NULL,
    `meta_json`      JSON NULL COMMENT 'contexto extra: grupo origem/destino, nome da coluna',
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ial_item_id`    (`item_id`, `created_at`),
    INDEX `idx_ial_board_id`   (`board_id`, `created_at`),
    INDEX `idx_ial_user_id`    (`user_id`),
    CONSTRAINT `fk_ial_item`  FOREIGN KEY (`item_id`)  REFERENCES `items`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ial_board` FOREIGN KEY (`board_id`) REFERENCES `boards` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ial_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Log de atividades por item (append-only)';

-- ------------------------------------------------------------
-- Tabela: user_group_state
-- Estado de grupos colapsados POR USUÁRIO (não global)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_group_state` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `group_id`    BIGINT UNSIGNED NOT NULL,
    `is_collapsed` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ugs_user_group` (`user_id`, `group_id`),
    INDEX `idx_ugs_user_id` (`user_id`),
    CONSTRAINT `fk_ugs_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ugs_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Estado de colapso de grupos por usuário';

-- ------------------------------------------------------------
-- Seed: Workspace padrão (se não existir)
-- ------------------------------------------------------------
INSERT IGNORE INTO `workspaces` (`id`, `name`, `description`, `icon`, `color`, `created_by`, `created_at`, `updated_at`)
SELECT 1, 'Principal', 'Workspace principal do sistema', '🏥', '#0073ea', id, NOW(), NOW()
FROM users WHERE id = 1 LIMIT 1;

-- Seed: Board de exemplo
INSERT IGNORE INTO `boards` (`id`, `workspace_id`, `name`, `description`, `type`, `visibility`, `color`, `created_by`, `created_at`, `updated_at`)
SELECT 1, 1, 'Gestão de Pacientes', 'Controle de admissões e alta hospitalar', 'board', 'public', '#0073ea', id, NOW(), NOW()
FROM users WHERE id = 1 LIMIT 1;

-- Seed: Member owner no board
INSERT IGNORE INTO `board_members` (`board_id`, `user_id`, `role`, `created_at`)
SELECT 1, id, 'owner', NOW() FROM users WHERE id = 1 LIMIT 1;

-- Seed: Coluna Status
INSERT IGNORE INTO `board_columns` (`id`, `board_id`, `name`, `type`, `order_index`, `settings`, `created_at`, `updated_at`)
VALUES (1, 1, 'Status', 'status', 1,
'{"options":[
  {"slug":"not_started","label":"Não Iniciado","color":"#c4c4c4"},
  {"slug":"in_progress","label":"Em Andamento","color":"#fdab3d"},
  {"slug":"done","label":"Concluído","color":"#00c875"},
  {"slug":"stuck","label":"Travado","color":"#e2445c"}
]}', NOW(), NOW());

-- Seed: Coluna Responsável
INSERT IGNORE INTO `board_columns` (`id`, `board_id`, `name`, `type`, `order_index`, `settings`, `created_at`, `updated_at`)
VALUES (2, 1, 'Responsável', 'person', 2, '{}', NOW(), NOW());

-- Seed: Coluna Data
INSERT IGNORE INTO `board_columns` (`id`, `board_id`, `name`, `type`, `order_index`, `settings`, `created_at`, `updated_at`)
VALUES (3, 1, 'Data Limite', 'date', 3, '{}', NOW(), NOW());

-- Seed: Coluna Prioridade
INSERT IGNORE INTO `board_columns` (`id`, `board_id`, `name`, `type`, `order_index`, `settings`, `created_at`, `updated_at`)
VALUES (4, 1, 'Prioridade', 'status', 4,
'{"options":[
  {"slug":"low","label":"Baixa","color":"#579bfc"},
  {"slug":"medium","label":"Média","color":"#fdab3d"},
  {"slug":"high","label":"Alta","color":"#e2445c"},
  {"slug":"critical","label":"Crítica","color":"#9d2b2b"}
]}', NOW(), NOW());

-- Seed: Grupos
INSERT IGNORE INTO `groups` (`id`, `board_id`, `name`, `color`, `order_index`, `created_at`, `updated_at`)
VALUES
(1, 1, 'Esta semana',   '#579bfc', 0, NOW(), NOW()),
(2, 1, 'Próxima semana','#a25ddc', 1, NOW(), NOW()),
(3, 1, 'Concluídos',    '#00c875', 2, NOW(), NOW());

-- Seed: Itens de exemplo
INSERT IGNORE INTO `items` (`id`, `board_id`, `group_id`, `name`, `created_by`, `order_index`, `created_at`, `updated_at`)
SELECT 1, 1, 1, 'Admissão paciente — Leito 12', id, 1000, NOW(), NOW() FROM users WHERE id = 1;
INSERT IGNORE INTO `items` (`id`, `board_id`, `group_id`, `name`, `created_by`, `order_index`, `created_at`, `updated_at`)
SELECT 2, 1, 1, 'Alta médica — Leito 7', id, 2000, NOW(), NOW() FROM users WHERE id = 1;
INSERT IGNORE INTO `items` (`id`, `board_id`, `group_id`, `name`, `created_by`, `order_index`, `created_at`, `updated_at`)
SELECT 3, 1, 2, 'Revisão de prontuários UTI', id, 1000, NOW(), NOW() FROM users WHERE id = 1;

-- Seed: Valores de status dos itens
INSERT IGNORE INTO `item_values` (`item_id`, `column_id`, `value_text`, `created_at`, `updated_at`)
VALUES
(1, 1, 'in_progress', NOW(), NOW()),
(2, 1, 'not_started', NOW(), NOW()),
(3, 1, 'stuck',       NOW(), NOW());

-- Seed: Valores de prioridade
INSERT IGNORE INTO `item_values` (`item_id`, `column_id`, `value_text`, `created_at`, `updated_at`)
VALUES
(1, 4, 'high',   NOW(), NOW()),
(2, 4, 'medium', NOW(), NOW()),
(3, 4, 'critical', NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;
