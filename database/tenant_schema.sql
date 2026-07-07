-- =============================================================================
-- CONECTA360 — BANCO DO TENANT (template para cada hospital)
-- Exemplo: dema5738_tenant_hospital_exemplo
-- Arquitetura SaaS Multi-Tenant | Versão 1.0.0
-- Gerado em: 2026-07-07
-- Charset: utf8mb4 | Collation: utf8mb4_unicode_ci | Engine: InnoDB
-- ATENÇÃO: Execute APÓS criar e selecionar o banco do tenant
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ============================================================
-- MÓDULO 1: USUÁRIOS E PERMISSÕES
-- ============================================================

-- ------------------------------------------------------------
-- Tabela: users
-- Usuários internos do tenant (funcionários do hospital)
-- Decisão: `status=invited` é o padrão — usuário só vira active após definir senha
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `name`                     VARCHAR(200)    NOT NULL                COMMENT 'Nome completo',
    `email`                    VARCHAR(255)    NOT NULL                COMMENT 'E-mail único de acesso',
    `password`                 VARCHAR(255)    NOT NULL                COMMENT 'Hash bcrypt (cost≥10) — NUNCA plain text',
    `avatar_url`               VARCHAR(500)                            COMMENT 'URL do avatar (pode ser Gravatar ou upload)',
    `job_title`                VARCHAR(150)                            COMMENT 'Cargo: Enfermeiro, Médico, Gestor',
    `department`               VARCHAR(150)                            COMMENT 'Departamento: UTI, Cirurgia, TI',
    `phone`                    VARCHAR(30)                             COMMENT 'Telefone com DDI: +55 11 99999-9999',
    `status`                   ENUM('active','inactive','invited','blocked') NOT NULL DEFAULT 'invited'
                                                                       COMMENT 'invited=aguardando primeiro acesso',
    `last_login_at`            DATETIME                                COMMENT 'Timestamp do último login',
    `last_login_ip`            VARCHAR(45)                             COMMENT 'IP do último login (IPv4/IPv6)',
    `failed_login_count`       TINYINT UNSIGNED NOT NULL DEFAULT 0     COMMENT 'Tentativas falhas — reset após login ok',
    `locked_until`             DATETIME                                COMMENT 'Bloqueio temporário por brute-force',
    `email_verified_at`        DATETIME                                COMMENT 'Confirmação de e-mail (NULL=não verificado)',
    `remember_token`           VARCHAR(100)                            COMMENT 'Token para sessões persistentes (lembrar-me)',
    `timezone`                 VARCHAR(50)     NOT NULL DEFAULT 'America/Sao_Paulo' COMMENT 'Fuso horário pessoal',
    `locale`                   VARCHAR(10)     NOT NULL DEFAULT 'pt_BR' COMMENT 'Idioma da interface',
    `notification_preferences` JSON                                    COMMENT 'Preferências: {"email":true,"push":false,"digest":"daily"}',
    `two_factor_secret`        VARCHAR(255)                            COMMENT 'Secret TOTP (Google Authenticator)',
    `two_factor_enabled`       TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '1=login exige código 2FA',
    `created_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`               DATETIME                                COMMENT 'Soft delete — histórico preservado',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email`       (`email`),
    INDEX `idx_users_status`          (`status`),
    INDEX `idx_users_deleted_at`      (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuários internos do hospital (tenant)';

-- ------------------------------------------------------------
-- Tabela: roles
-- Perfis de permissão pré-definidos e customizáveis
-- Decisão: `is_system=1` protege perfis que não podem ser deletados (admin, viewer)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `name`        VARCHAR(100)    NOT NULL                COMMENT 'Nome exibível: Administrador, Editor, Visualizador',
    `slug`        VARCHAR(100)    NOT NULL                COMMENT 'Slug único: admin, editor, viewer',
    `description` TEXT                                    COMMENT 'Descrição das responsabilidades do perfil',
    `is_system`   TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '1=perfil nativo (não pode ser deletado ou renomeado)',
    `color`       VARCHAR(7)      NOT NULL DEFAULT '#6c757d' COMMENT 'Cor HEX para badge de identificação visual',
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Perfis de permissão do tenant';

-- ------------------------------------------------------------
-- Tabela: user_roles — pivot usuário × perfil (N:N)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_roles` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `user_id`    BIGINT UNSIGNED NOT NULL                COMMENT 'FK → users.id',
    `role_id`    BIGINT UNSIGNED NOT NULL                COMMENT 'FK → roles.id',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_role`    (`user_id`, `role_id`),
    INDEX `idx_ur_user_id`       (`user_id`),
    INDEX `idx_ur_role_id`       (`role_id`),
    CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Associação usuário × perfil de permissão';

-- ------------------------------------------------------------
-- Tabela: permissions
-- Permissões granulares no padrão módulo.ação
-- Decisão: slug no formato "resource.action" facilita verificação no código
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permissions` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `name`        VARCHAR(200)    NOT NULL                COMMENT 'Nome legível: Criar Boards',
    `slug`        VARCHAR(200)    NOT NULL                COMMENT 'Slug: boards.create, items.delete, users.invite',
    `module`      VARCHAR(100)    NOT NULL                COMMENT 'Módulo: boards, items, users, automations, settings',
    `description` TEXT                                    COMMENT 'O que esta permissão habilita',
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permissions_slug` (`slug`),
    INDEX `idx_permissions_module`   (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Permissões granulares do sistema';

-- ------------------------------------------------------------
-- Tabela: role_permissions — pivot perfil × permissão (N:N)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `role_id`       BIGINT UNSIGNED NOT NULL                COMMENT 'FK → roles.id',
    `permission_id` BIGINT UNSIGNED NOT NULL                COMMENT 'FK → permissions.id',
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_role_permission`  (`role_id`, `permission_id`),
    INDEX `idx_rp_role_id`           (`role_id`),
    INDEX `idx_rp_permission_id`     (`permission_id`),
    CONSTRAINT `fk_rp_role`       FOREIGN KEY (`role_id`)       REFERENCES `roles`       (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Associação perfil × permissão';

-- ============================================================
-- MÓDULO 2: WORKSPACES E BOARDS
-- ============================================================

-- ------------------------------------------------------------
-- Tabela: workspaces
-- Agrupadores de alto nível (ex: "Unidade de Cirurgia", "Recursos Humanos")
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `workspaces` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `name`        VARCHAR(200)    NOT NULL                COMMENT 'Nome do workspace',
    `description` TEXT                                    COMMENT 'Finalidade ou descrição do workspace',
    `icon`        VARCHAR(100)                            COMMENT 'Ícone: nome Material Icon, FontAwesome ou emoji UTF-8',
    `color`       VARCHAR(7)      NOT NULL DEFAULT '#0073ea' COMMENT 'Cor HEX da barra lateral',
    `created_by`  BIGINT UNSIGNED NOT NULL                COMMENT 'FK → users.id — criador e dono inicial',
    `is_private`  TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '0=visível a todos os membros, 1=acesso por convite',
    `order_index` INT UNSIGNED    NOT NULL DEFAULT 0      COMMENT 'Posição na lista lateral (drag and drop)',
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME                                COMMENT 'Soft delete',
    PRIMARY KEY (`id`),
    INDEX `idx_workspaces_created_by`  (`created_by`),
    INDEX `idx_workspaces_deleted_at`  (`deleted_at`),
    CONSTRAINT `fk_ws_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Workspaces — agrupadores de boards por área/departamento';

-- ------------------------------------------------------------
-- Tabela: workspace_members
-- Controla quem pode ver/editar cada workspace
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `workspace_members` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `workspace_id` BIGINT UNSIGNED NOT NULL                COMMENT 'FK → workspaces.id',
    `user_id`      BIGINT UNSIGNED NOT NULL                COMMENT 'FK → users.id',
    `role`         ENUM('owner','admin','member','viewer') NOT NULL DEFAULT 'member'
                                                           COMMENT 'owner=dono, admin=gerencia membros, member=edita, viewer=lê',
    `joined_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de entrada no workspace',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wm_workspace_user`  (`workspace_id`, `user_id`),
    INDEX `idx_wm_workspace_id`        (`workspace_id`),
    INDEX `idx_wm_user_id`             (`user_id`),
    CONSTRAINT `fk_wm_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wm_user`      FOREIGN KEY (`user_id`)      REFERENCES `users`      (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Membros e papéis por workspace';

-- ------------------------------------------------------------
-- Tabela: boards
-- Quadros de trabalho (Kanban, Timeline, Tabela, etc.)
-- Decisão: `type` define a VIEW padrão, não bloqueia outras visualizações
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `boards` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `workspace_id` BIGINT UNSIGNED NOT NULL                COMMENT 'FK → workspaces.id',
    `name`         VARCHAR(200)    NOT NULL                COMMENT 'Nome do board',
    `description`  TEXT                                    COMMENT 'Descrição do propósito do board',
    `type`         ENUM('board','kanban','timeline','calendar','gantt','form') NOT NULL DEFAULT 'board'
                                                           COMMENT 'Visualização padrão ao abrir o board',
    `visibility`   ENUM('public','private','shared') NOT NULL DEFAULT 'public'
                                                           COMMENT 'public=todos do workspace, private=apenas membros, shared=link externo',
    `icon`         VARCHAR(100)                            COMMENT 'Ícone do board',
    `color`        VARCHAR(7)      NOT NULL DEFAULT '#0073ea' COMMENT 'Cor de identificação',
    `created_by`   BIGINT UNSIGNED NOT NULL                COMMENT 'FK → users.id',
    `order_index`  INT UNSIGNED    NOT NULL DEFAULT 0      COMMENT 'Posição no workspace',
    `is_template`  TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '1=este board é um template reutilizável',
    `settings`     JSON                                    COMMENT 'Config extra: {"collapsed_groups":true,"item_limit":500}',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`   DATETIME                                COMMENT 'Soft delete',
    PRIMARY KEY (`id`),
    INDEX `idx_boards_workspace_id` (`workspace_id`),
    INDEX `idx_boards_created_by`   (`created_by`),
    INDEX `idx_boards_type`         (`type`),
    INDEX `idx_boards_deleted_at`   (`deleted_at`),
    CONSTRAINT `fk_boards_workspace`   FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_boards_created_by`  FOREIGN KEY (`created_by`)   REFERENCES `users`      (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Boards de trabalho dentro de workspaces';

-- ------------------------------------------------------------
-- Tabela: board_members
-- Controle de acesso granular por board (independe do workspace)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `board_members` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `board_id`   BIGINT UNSIGNED NOT NULL                COMMENT 'FK → boards.id',
    `user_id`    BIGINT UNSIGNED NOT NULL                COMMENT 'FK → users.id',
    `role`       ENUM('owner','editor','commenter','viewer') NOT NULL DEFAULT 'viewer'
                                                         COMMENT 'editor=cria/edita itens, commenter=só comenta, viewer=lê',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_bm_board_user`  (`board_id`, `user_id`),
    INDEX `idx_bm_board_id`        (`board_id`),
    INDEX `idx_bm_user_id`         (`user_id`),
    CONSTRAINT `fk_bm_board` FOREIGN KEY (`board_id`) REFERENCES `boards` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bm_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Membros e papéis por board';

-- ------------------------------------------------------------
-- Tabela: board_columns
-- Colunas dinâmicas — coração do sistema EAV
-- Decisão: `settings` JSON armazena opções de dropdown/status, fórmulas, etc.
-- Decisão: suporta 29 tipos de coluna para cobrir todos os casos de uso hospitalares
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `board_columns` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `board_id`    BIGINT UNSIGNED NOT NULL                COMMENT 'FK → boards.id',
    `name`        VARCHAR(200)    NOT NULL                COMMENT 'Rótulo exibido no cabeçalho da coluna',
    `type`        ENUM(
                    'status','text','number','date','person','file',
                    'checkbox','dropdown','email','phone','link',
                    'rating','timeline','location','color','formula',
                    'dependency','mirror','button','auto_number',
                    'hour','week','world_clock','vote','progress',
                    'tags','time_tracking','connect_boards','long_text'
                  ) NOT NULL DEFAULT 'text'              COMMENT 'Tipo de dado — define renderização e validação',
    `order_index` INT UNSIGNED    NOT NULL DEFAULT 0      COMMENT 'Posição horizontal da coluna (drag and drop)',
    `width`       INT UNSIGNED    NOT NULL DEFAULT 200    COMMENT 'Largura em pixels',
    `is_hidden`   TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '1=oculta na visualização padrão do board',
    `is_required` TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '1=obrigatória ao criar item',
    `settings`    JSON                                    COMMENT 'Config do tipo: {"options":[{"id":"opt1","label":"Em andamento","color":"#fdab3d"}]}',
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_bc_board_id` (`board_id`),
    INDEX `idx_bc_order`    (`board_id`, `order_index`),
    CONSTRAINT `fk_bc_board` FOREIGN KEY (`board_id`) REFERENCES `boards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Colunas dinâmicas dos boards (schema-on-read via EAV)';

-- ============================================================
-- MÓDULO 3: GRUPOS E ITENS
-- ============================================================

-- ------------------------------------------------------------
-- Tabela: groups
-- Seções dentro do board (ex: "Fase 1 — Triagem", "Backlog", "Concluído")
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `groups` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `board_id`     BIGINT UNSIGNED NOT NULL                COMMENT 'FK → boards.id',
    `name`         VARCHAR(200)    NOT NULL                COMMENT 'Título do grupo (seção do board)',
    `color`        VARCHAR(7)      NOT NULL DEFAULT '#579bfc' COMMENT 'Cor HEX da barra lateral do grupo',
    `order_index`  INT UNSIGNED    NOT NULL DEFAULT 0      COMMENT 'Posição vertical no board',
    `is_collapsed` TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '1=grupo recolhido na visualização',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`   DATETIME                                COMMENT 'Soft delete',
    PRIMARY KEY (`id`),
    INDEX `idx_groups_board_id`   (`board_id`),
    INDEX `idx_groups_order`      (`board_id`, `order_index`),
    INDEX `idx_groups_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_groups_board` FOREIGN KEY (`board_id`) REFERENCES `boards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Grupos (seções) de itens dentro de um board';

-- ------------------------------------------------------------
-- Tabela: items
-- Tarefa/registro central do sistema
-- Decisão: `board_id` redundante com `group_id→board_id` mas evita JOIN em queries críticas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `items` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `board_id`       BIGINT UNSIGNED NOT NULL                COMMENT 'FK → boards.id (desnormalizado para performance)',
    `group_id`       BIGINT UNSIGNED NOT NULL                COMMENT 'FK → groups.id',
    `name`           VARCHAR(500)    NOT NULL                COMMENT 'Título/nome do item',
    `created_by`     BIGINT UNSIGNED NOT NULL                COMMENT 'FK → users.id — criador do item',
    `assignee_id`    BIGINT UNSIGNED                         COMMENT 'FK → users.id — responsável principal (nullable)',
    `order_index`    INT UNSIGNED    NOT NULL DEFAULT 0      COMMENT 'Posição vertical dentro do grupo',
    `is_archived`    TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '1=arquivado (oculto, mas preservado)',
    `archived_at`    DATETIME                                COMMENT 'Timestamp do arquivamento',
    `parent_item_id` BIGINT UNSIGNED                         COMMENT 'FK → items.id — para itens espelhados (mirror)',
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     DATETIME                                COMMENT 'Soft delete',
    PRIMARY KEY (`id`),
    INDEX `idx_items_board_id`    (`board_id`),
    INDEX `idx_items_group_id`    (`group_id`),
    INDEX `idx_items_created_by`  (`created_by`),
    INDEX `idx_items_assignee_id` (`assignee_id`),
    INDEX `idx_items_archived`    (`is_archived`),
    INDEX `idx_items_order`       (`group_id`, `order_index`),
    INDEX `idx_items_deleted_at`  (`deleted_at`),
    CONSTRAINT `fk_items_board`      FOREIGN KEY (`board_id`)       REFERENCES `boards`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_items_group`      FOREIGN KEY (`group_id`)       REFERENCES `groups`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_items_created_by` FOREIGN KEY (`created_by`)     REFERENCES `users`   (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_items_assignee`   FOREIGN KEY (`assignee_id`)    REFERENCES `users`   (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_items_parent`     FOREIGN KEY (`parent_item_id`) REFERENCES `items`   (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Itens/tarefas — entidade central dos boards';

-- ------------------------------------------------------------
-- Tabela: item_values
-- Padrão EAV (Entity-Attribute-Value) — armazena valores das colunas dinâmicas
-- Decisão: 4 tipos de coluna nativa (text/number/date/json) evitam CAST custoso
--          O tipo correto a usar é determinado pela coluna em board_columns.type
-- Decisão: UNIQUE (item_id, column_id) garante exatamente 1 valor por célula
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `item_values` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `item_id`       BIGINT UNSIGNED NOT NULL                COMMENT 'FK → items.id',
    `column_id`     BIGINT UNSIGNED NOT NULL                COMMENT 'FK → board_columns.id',
    `value_text`    TEXT                                    COMMENT 'Para tipos: text, email, phone, link, long_text',
    `value_number`  DECIMAL(20,6)                           COMMENT 'Para tipos: number, rating, progress, auto_number',
    `value_date`    DATETIME                                COMMENT 'Para tipos: date, timeline (início), hour',
    `value_date_end`DATETIME                                COMMENT 'Data final para tipo: timeline (fim), range',
    `value_json`    JSON                                    COMMENT 'Para tipos: person, dropdown, status, tags, location, formula',
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_iv_item_column`  (`item_id`, `column_id`),
    INDEX `idx_iv_item_id`          (`item_id`),
    INDEX `idx_iv_column_id`        (`column_id`),
    INDEX `idx_iv_value_date`       (`value_date`),
    CONSTRAINT `fk_iv_item`   FOREIGN KEY (`item_id`)   REFERENCES `items`         (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_iv_column` FOREIGN KEY (`column_id`) REFERENCES `board_columns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Valores das colunas por item — padrão EAV (Entity-Attribute-Value)';

-- ------------------------------------------------------------
-- Tabela: subitems
-- Checklist / subtarefas vinculadas a um item
-- Decisão: status com ENUM fixo e não coluna dinâmica — subitems têm estrutura simples
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subitems` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `parent_item_id` BIGINT UNSIGNED NOT NULL                COMMENT 'FK → items.id — item pai',
    `name`           VARCHAR(500)    NOT NULL                COMMENT 'Descrição da subtarefa',
    `status`         ENUM('not_started','in_progress','done','stuck','on_hold') NOT NULL DEFAULT 'not_started'
                                                             COMMENT 'Status fixo — simplifica relatórios de progresso',
    `assignee_id`    BIGINT UNSIGNED                         COMMENT 'FK → users.id — responsável',
    `due_date`       DATE                                    COMMENT 'Data limite (só data, sem hora)',
    `order_index`    INT UNSIGNED    NOT NULL DEFAULT 0      COMMENT 'Ordem dentro do item pai',
    `created_by`     BIGINT UNSIGNED NOT NULL                COMMENT 'FK → users.id — criador',
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     DATETIME                                COMMENT 'Soft delete',
    PRIMARY KEY (`id`),
    INDEX `idx_subitems_parent_id`   (`parent_item_id`),
    INDEX `idx_subitems_assignee_id` (`assignee_id`),
    INDEX `idx_subitems_status`      (`status`),
    INDEX `idx_subitems_due_date`    (`due_date`),
    INDEX `idx_subitems_deleted_at`  (`deleted_at`),
    CONSTRAINT `fk_subitems_parent`     FOREIGN KEY (`parent_item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_subitems_assignee`   FOREIGN KEY (`assignee_id`)    REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_subitems_created_by` FOREIGN KEY (`created_by`)     REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Subitens/subtarefas vinculados a um item';

-- ============================================================
-- MÓDULO 4: COMENTÁRIOS E ATIVIDADE
-- ============================================================

-- ------------------------------------------------------------
-- Tabela: comments
-- Comentários com suporte a texto rico e respostas aninhadas
-- Decisão: `parent_comment_id` permite threads (1 nível de aninhamento recomendado)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comments` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `item_id`           BIGINT UNSIGNED NOT NULL                COMMENT 'FK → items.id',
    `user_id`           BIGINT UNSIGNED NOT NULL                COMMENT 'FK → users.id — autor',
    `parent_comment_id` BIGINT UNSIGNED                         COMMENT 'FK → comments.id — para respostas (thread)',
    `content`           LONGTEXT        NOT NULL                COMMENT 'HTML sanitizado ou Markdown — texto rico',
    `is_edited`         TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '1=editado após publicação original',
    `edited_at`         DATETIME                                COMMENT 'Timestamp da última edição',
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        DATETIME                                COMMENT 'Soft delete (exibe "mensagem removida")',
    PRIMARY KEY (`id`),
    INDEX `idx_comments_item_id`    (`item_id`),
    INDEX `idx_comments_user_id`    (`user_id`),
    INDEX `idx_comments_parent_id`  (`parent_comment_id`),
    INDEX `idx_comments_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_comments_item`   FOREIGN KEY (`item_id`)           REFERENCES `items`    (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_comments_user`   FOREIGN KEY (`user_id`)           REFERENCES `users`    (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_comment_id`) REFERENCES `comments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Comentários nos itens com suporte a texto rico e threads';

-- ------------------------------------------------------------
-- Tabela: comment_mentions
-- Rastreia @menções para gerar notificações precisas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comment_mentions` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `comment_id` BIGINT UNSIGNED NOT NULL                COMMENT 'FK → comments.id',
    `user_id`    BIGINT UNSIGNED NOT NULL                COMMENT 'FK → users.id — usuário mencionado',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cm_comment_user` (`comment_id`, `user_id`),
    INDEX `idx_cm_comment_id`       (`comment_id`),
    INDEX `idx_cm_user_id`          (`user_id`),
    CONSTRAINT `fk_cm_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cm_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Menções (@) de usuários em comentários';

-- ------------------------------------------------------------
-- Tabela: activity_logs
-- Linha do tempo de toda ação realizada no tenant
-- Decisão: append-only — nunca deletar, nunca atualizar
-- Decisão: `old_value`/`new_value` JSON para auditoria granular de campos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK — sequencial imutável',
    `user_id`     BIGINT UNSIGNED                         COMMENT 'FK → users.id (NULL se ação de sistema/automação)',
    `entity_type` VARCHAR(100)    NOT NULL                COMMENT 'item, board, workspace, group, comment, subitem',
    `entity_id`   BIGINT UNSIGNED NOT NULL                COMMENT 'ID da entidade afetada',
    `action`      VARCHAR(100)    NOT NULL                COMMENT 'created, updated, deleted, archived, commented, assigned, moved',
    `field_name`  VARCHAR(100)                            COMMENT 'Campo alterado: "status", "assignee_id", "name" (só para updates)',
    `old_value`   JSON                                    COMMENT 'Valor anterior (para updates e deletes)',
    `new_value`   JSON                                    COMMENT 'Valor novo (para creates e updates)',
    `metadata`    JSON                                    COMMENT 'Contexto adicional: board_name, workspace_name, etc.',
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Imutável',
    PRIMARY KEY (`id`),
    INDEX `idx_al_user_id`    (`user_id`),
    INDEX `idx_al_entity`     (`entity_type`, `entity_id`),
    INDEX `idx_al_action`     (`action`),
    INDEX `idx_al_created_at` (`created_at`),
    CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Log imutável de atividades e auditoria do tenant (append-only)';

-- ============================================================
-- MÓDULO 5: ARQUIVOS
-- ============================================================

-- ------------------------------------------------------------
-- Tabela: attachments
-- Arquivos enviados para itens ou comentários
-- Decisão: `disk` ENUM permite migração futura para S3/GCS sem mudar schema
-- Decisão: `public_token` para compartilhamento externo sem login
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `attachments` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `item_id`        BIGINT UNSIGNED                         COMMENT 'FK → items.id (NULL se é de um comentário)',
    `comment_id`     BIGINT UNSIGNED                         COMMENT 'FK → comments.id (NULL se é de um item)',
    `user_id`        BIGINT UNSIGNED NOT NULL                COMMENT 'FK → users.id — quem fez o upload',
    `original_name`  VARCHAR(500)    NOT NULL                COMMENT 'Nome original do arquivo enviado pelo usuário',
    `stored_name`    VARCHAR(500)    NOT NULL                COMMENT 'Nome UUID gerado para evitar colisão no storage',
    `file_path`      VARCHAR(1000)   NOT NULL                COMMENT 'Caminho relativo no disco: uploads/2026/07/uuid.pdf',
    `file_size`      BIGINT UNSIGNED NOT NULL                COMMENT 'Tamanho em bytes',
    `mime_type`      VARCHAR(255)    NOT NULL                COMMENT 'Tipo MIME: image/png, application/pdf',
    `disk`           ENUM('local','s3','gcs','azure') NOT NULL DEFAULT 'local'
                                                             COMMENT 'Driver de storage utilizado',
    `thumbnail_path` VARCHAR(1000)                           COMMENT 'Caminho do thumbnail gerado (imagens e PDFs)',
    `is_public`      TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '1=acessível por link sem autenticação',
    `public_token`   VARCHAR(100)                            COMMENT 'Token único aleatório para link de compartilhamento',
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     DATETIME                                COMMENT 'Soft delete (arquivo marcado para exclusão física posterior)',
    PRIMARY KEY (`id`),
    INDEX `idx_att_item_id`     (`item_id`),
    INDEX `idx_att_comment_id`  (`comment_id`),
    INDEX `idx_att_user_id`     (`user_id`),
    INDEX `idx_att_deleted_at`  (`deleted_at`),
    UNIQUE KEY `uq_att_public_token` (`public_token`),
    CONSTRAINT `fk_att_item`    FOREIGN KEY (`item_id`)    REFERENCES `items`    (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_att_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_att_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Arquivos e anexos de itens e comentários';

-- ============================================================
-- MÓDULO 6: NOTIFICAÇÕES
-- ============================================================

-- ------------------------------------------------------------
-- Tabela: notifications
-- Sistema de inbox para cada usuário
-- Decisão: `type` com VARCHAR (não ENUM) — novos tipos não exigem ALTER TABLE
-- Decisão: índice composto (user_id, is_read) acelera a query "caixa não lida"
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `user_id`     BIGINT UNSIGNED NOT NULL                COMMENT 'FK → users.id — destinatário',
    `type`        VARCHAR(100)    NOT NULL                COMMENT 'mention, assignment, status_change, comment, due_date, automation',
    `title`       VARCHAR(300)    NOT NULL                COMMENT 'Título curto da notificação',
    `message`     TEXT                                    COMMENT 'Mensagem completa (pode incluir HTML)',
    `is_read`     TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '0=não lida (badge no sino), 1=lida',
    `read_at`     DATETIME                                COMMENT 'Quando o usuário abriu/leu',
    `entity_type` VARCHAR(100)                            COMMENT 'Entidade relacionada: item, board, comment',
    `entity_id`   BIGINT UNSIGNED                         COMMENT 'ID da entidade para link de navegação',
    `actor_id`    BIGINT UNSIGNED                         COMMENT 'FK → users.id — quem gerou (NULL se sistema)',
    `data`        JSON                                    COMMENT 'Payload de renderização: {"item_name":"Alta paciente","board_name":"UTI"}',
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_notif_user_id`    (`user_id`),
    INDEX `idx_notif_is_read`    (`user_id`, `is_read`),
    INDEX `idx_notif_entity`     (`entity_type`, `entity_id`),
    INDEX `idx_notif_created_at` (`created_at`),
    CONSTRAINT `fk_notif_user`  FOREIGN KEY (`user_id`)  REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notif_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Notificações individuais por usuário';

-- ============================================================
-- MÓDULO 7: AUTOMAÇÕES
-- ============================================================

-- ------------------------------------------------------------
-- Tabela: automations
-- Regras "Quando X acontece → Fazer Y"
-- Decisão: trigger e action como JSON para máxima flexibilidade sem ALTER TABLE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `automations` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `board_id`       BIGINT UNSIGNED NOT NULL                COMMENT 'FK → boards.id — escopo da automação',
    `name`           VARCHAR(200)    NOT NULL                COMMENT 'Descrição legível: "Notificar quando status mudar para Atrasado"',
    `is_active`      TINYINT(1)      NOT NULL DEFAULT 1      COMMENT '1=habilitada, 0=pausada',
    `trigger_type`   VARCHAR(100)    NOT NULL                COMMENT 'status_change, date_arrives, item_created, column_changed, assigned_to',
    `trigger_config` JSON            NOT NULL                COMMENT '{"column_id":5,"from_value":"Em andamento","to_value":"Atrasado"}',
    `action_type`    VARCHAR(100)    NOT NULL                COMMENT 'notify_person, assign_person, move_item, create_item, send_email, set_status',
    `action_config`  JSON            NOT NULL                COMMENT '{"user_id":12,"message":"Item marcado como atrasado"}',
    `created_by`     BIGINT UNSIGNED NOT NULL                COMMENT 'FK → users.id — criador da regra',
    `run_count`      INT UNSIGNED    NOT NULL DEFAULT 0      COMMENT 'Total de execuções bem-sucedidas',
    `last_run_at`    DATETIME                                COMMENT 'Timestamp da última execução',
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     DATETIME                                COMMENT 'Soft delete',
    PRIMARY KEY (`id`),
    INDEX `idx_auto_board_id`     (`board_id`),
    INDEX `idx_auto_trigger_type` (`trigger_type`),
    INDEX `idx_auto_is_active`    (`is_active`),
    INDEX `idx_auto_deleted_at`   (`deleted_at`),
    CONSTRAINT `fk_auto_board`      FOREIGN KEY (`board_id`)   REFERENCES `boards` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_auto_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`  (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Automações de workflow (regras se/então) dos boards';

-- ------------------------------------------------------------
-- Tabela: automation_logs
-- Histórico de execução de cada automação
-- Decisão: `item_id` nullable — automações de board (ex: relatório diário) não têm item
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `automation_logs` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `automation_id` BIGINT UNSIGNED NOT NULL                COMMENT 'FK → automations.id',
    `item_id`       BIGINT UNSIGNED                         COMMENT 'FK → items.id — item que disparou (nullable)',
    `status`        ENUM('success','failed','skipped') NOT NULL DEFAULT 'success'
                                                             COMMENT 'skipped=condição não atendida na execução',
    `details`       JSON                                     COMMENT 'Resultado detalhado: {"error":"...","action_taken":"notified user 5"}',
    `executed_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Imutável',
    PRIMARY KEY (`id`),
    INDEX `idx_autolog_automation_id` (`automation_id`),
    INDEX `idx_autolog_item_id`       (`item_id`),
    INDEX `idx_autolog_status`        (`status`),
    INDEX `idx_autolog_executed_at`   (`executed_at`),
    CONSTRAINT `fk_autolog_automation` FOREIGN KEY (`automation_id`) REFERENCES `automations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_autolog_item`       FOREIGN KEY (`item_id`)       REFERENCES `items`       (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Histórico de execuções das automações';

-- ============================================================
-- MÓDULO 8: DASHBOARDS
-- ============================================================

-- ------------------------------------------------------------
-- Tabela: dashboards
-- Painéis de inteligência operacional por workspace
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dashboards` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `workspace_id` BIGINT UNSIGNED NOT NULL                COMMENT 'FK → workspaces.id',
    `name`         VARCHAR(200)    NOT NULL                COMMENT 'Nome do dashboard',
    `description`  TEXT                                    COMMENT 'Descrição do propósito',
    `created_by`   BIGINT UNSIGNED NOT NULL                COMMENT 'FK → users.id',
    `is_shared`    TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '1=visível a todos os membros do workspace',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`   DATETIME                                COMMENT 'Soft delete',
    PRIMARY KEY (`id`),
    INDEX `idx_dash_workspace_id` (`workspace_id`),
    INDEX `idx_dash_created_by`   (`created_by`),
    INDEX `idx_dash_deleted_at`   (`deleted_at`),
    CONSTRAINT `fk_dash_workspace`   FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dash_created_by`  FOREIGN KEY (`created_by`)   REFERENCES `users`      (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Dashboards de visualização de dados por workspace';

-- ------------------------------------------------------------
-- Tabela: dashboard_widgets
-- Componentes posicionados em grade (grid layout 12 colunas)
-- Decisão: sistema de grade igual ao Grafana/Monday — position_x/y + width/height
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dashboard_widgets` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `dashboard_id` BIGINT UNSIGNED NOT NULL                COMMENT 'FK → dashboards.id',
    `type`         ENUM('chart_bar','chart_pie','chart_line','number_card','kanban',
                        'timeline','calendar','battery','workload','table_view','text_block','countdown')
                   NOT NULL                                COMMENT 'Tipo de widget: chart_bar, number_card, kanban, etc.',
    `title`        VARCHAR(200)                            COMMENT 'Título exibido no cabeçalho do widget',
    `board_id`     BIGINT UNSIGNED                         COMMENT 'FK → boards.id — fonte de dados do widget (nullable)',
    `config`       JSON                                    COMMENT 'Configuração completa: {"column_id":3,"group_by":"status","filter":{}}',
    `position_x`   TINYINT UNSIGNED NOT NULL DEFAULT 0     COMMENT 'Coluna inicial na grade (0-11)',
    `position_y`   TINYINT UNSIGNED NOT NULL DEFAULT 0     COMMENT 'Linha inicial na grade',
    `width`        TINYINT UNSIGNED NOT NULL DEFAULT 4     COMMENT 'Largura em colunas da grade (1-12)',
    `height`       TINYINT UNSIGNED NOT NULL DEFAULT 3     COMMENT 'Altura em linhas da grade',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_dw_dashboard_id` (`dashboard_id`),
    INDEX `idx_dw_board_id`     (`board_id`),
    CONSTRAINT `fk_dw_dashboard` FOREIGN KEY (`dashboard_id`) REFERENCES `dashboards` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dw_board`     FOREIGN KEY (`board_id`)     REFERENCES `boards`     (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Widgets posicionados em grade nos dashboards';

-- ============================================================
-- MÓDULO 9: CONFIGURAÇÕES E TAGS
-- ============================================================

-- ------------------------------------------------------------
-- Tabela: settings
-- Configurações globais do tenant (tabela chave-valor tipada)
-- Decisão: `type` ENUM permite desserialização automática no PHP
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `key`         VARCHAR(200)    NOT NULL                COMMENT 'Chave única: app_name, logo_url, smtp_host, max_upload_mb',
    `value`       LONGTEXT                                COMMENT 'Valor raw — desserializar conforme `type`',
    `type`        ENUM('string','integer','boolean','json','array') NOT NULL DEFAULT 'string'
                                                         COMMENT 'Tipo para cast automático no PHP',
    `group`       VARCHAR(100)    NOT NULL DEFAULT 'general'
                                                         COMMENT 'Grupo lógico: general, email, security, appearance, integrations',
    `description` TEXT                                    COMMENT 'Descrição para o painel de configurações',
    `is_public`   TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '1=exposta no endpoint público (ex: app_name, logo_url)',
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key`  (`key`),
    INDEX `idx_settings_group`    (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Configurações globais do tenant (chave-valor tipada)';

-- ------------------------------------------------------------
-- Tabela: tags
-- Etiquetas reutilizáveis para categorização de itens
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tags` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `name`       VARCHAR(100)    NOT NULL                COMMENT 'Nome da tag: Urgente, LGPD, Manutenção',
    `color`      VARCHAR(7)      NOT NULL DEFAULT '#e2e2e2' COMMENT 'Cor HEX para o badge',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tags_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tags reutilizáveis para categorização de itens';

-- ------------------------------------------------------------
-- Tabela: item_tags — pivot item × tag (N:N)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `item_tags` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `item_id`    BIGINT UNSIGNED NOT NULL                COMMENT 'FK → items.id',
    `tag_id`     BIGINT UNSIGNED NOT NULL                COMMENT 'FK → tags.id',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_it_item_tag` (`item_id`, `tag_id`),
    INDEX `idx_it_item_id`      (`item_id`),
    INDEX `idx_it_tag_id`       (`tag_id`),
    CONSTRAINT `fk_it_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_it_tag`  FOREIGN KEY (`tag_id`)  REFERENCES `tags`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Associação item × tag';

-- =============================================================================
-- DADOS INICIAIS DO TENANT — Perfis e Permissões de sistema
-- =============================================================================
INSERT IGNORE INTO `roles` (`name`, `slug`, `description`, `is_system`, `color`) VALUES
('Administrador', 'admin',   'Acesso total ao tenant, gerencia usuários e configurações',  1, '#e2445c'),
('Editor',        'editor',  'Cria e edita boards, itens e grupos. Não acessa configurações', 1, '#fdab3d'),
('Membro',        'member',  'Edita itens nos boards de que é membro',                       1, '#00c875'),
('Visualizador',  'viewer',  'Apenas leitura em todos os boards públicos',                   1, '#579bfc'),
('Convidado',     'guest',   'Acesso restrito a boards específicos por convite',              1, '#a25ddc');

INSERT IGNORE INTO `permissions` (`name`, `slug`, `module`) VALUES
-- Usuários
('Convidar usuários',          'users.invite',           'users'),
('Editar usuários',            'users.edit',             'users'),
('Remover usuários',           'users.delete',           'users'),
('Gerenciar perfis',           'roles.manage',           'users'),
-- Workspaces
('Criar workspaces',           'workspaces.create',      'workspaces'),
('Editar workspaces',          'workspaces.edit',        'workspaces'),
('Deletar workspaces',         'workspaces.delete',      'workspaces'),
-- Boards
('Criar boards',               'boards.create',          'boards'),
('Editar boards',              'boards.edit',            'boards'),
('Deletar boards',             'boards.delete',          'boards'),
('Gerenciar colunas',          'boards.columns.manage',  'boards'),
-- Itens
('Criar itens',                'items.create',           'items'),
('Editar itens',               'items.edit',             'items'),
('Deletar itens',              'items.delete',           'items'),
('Arquivar itens',             'items.archive',          'items'),
-- Comentários
('Comentar em itens',          'comments.create',        'comments'),
('Deletar qualquer comentário','comments.delete_any',    'comments'),
-- Automações
('Gerenciar automações',       'automations.manage',     'automations'),
-- Dashboards
('Criar dashboards',           'dashboards.create',      'dashboards'),
-- Configurações
('Gerenciar configurações',    'settings.manage',        'settings');

INSERT IGNORE INTO `settings` (`key`, `value`, `type`, `group`, `description`, `is_public`) VALUES
('app_name',          'Conecta360',        'string',  'general',    'Nome da aplicação',                             1),
('app_timezone',      'America/Sao_Paulo', 'string',  'general',    'Timezone padrão do tenant',                     0),
('max_upload_mb',     '50',                'integer', 'general',    'Tamanho máximo de upload em MB',                0),
('allowed_mimetypes', '["image/jpeg","image/png","image/gif","application/pdf","application/zip","text/csv"]',
                                           'json',    'general',    'Tipos MIME permitidos no upload',               0),
('notifications_email_enabled', '1',       'boolean', 'email',      'Habilitar notificações por e-mail',             0),
('two_factor_required', '0',               'boolean', 'security',   'Exigir 2FA para todos os usuários',             0);

SET FOREIGN_KEY_CHECKS = 1;
