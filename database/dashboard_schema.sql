-- =============================================================================
-- CONECTA360 — SCHEMA: MENU, DASHBOARD E PERMISSÕES EXPANDIDAS
-- Banco Tenant | Versão 1.0.0 | 2026-07-07
-- Execute DENTRO do banco do tenant após tenant_schema.sql e auth_tables.sql
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- MÓDULO: MENU LATERAL DINÂMICO
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Tabela: menu_items
-- Define todos os itens navegáveis do menu lateral.
-- Dados SEMIPERMANENTES — mudam raramente, ideais para cache.
--
-- ESTRATÉGIA:
--   • Itens com parent_id NULL são raízes (grupos/seções)
--   • Itens com parent_id são submenus
--   • order_index define a posição dentro do pai
--   • route_key é o nome lógico da rota (não a URL) — permite alias futuros
--   • permission_slug NULL = item visível a todos os autenticados
--   • is_dynamic = 1 → item é gerado em runtime (ex: lista de workspaces)
--   • badge_source = nome do método no MenuService que retorna o badge count
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `menu_items` (
    `id`               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT  COMMENT 'PK',
    `parent_id`        BIGINT UNSIGNED                           COMMENT 'FK → menu_items.id (NULL=raiz/grupo)',
    `key`              VARCHAR(100)     NOT NULL                  COMMENT 'Chave única interna: dashboard, boards, settings',
    `label`            VARCHAR(150)     NOT NULL                  COMMENT 'Texto exibido no menu',
    `icon`             VARCHAR(100)                               COMMENT 'Classe de ícone: fas fa-home | heroicon:home | emoji',
    `route_key`        VARCHAR(150)                               COMMENT 'Nome lógico da rota para geração de URL',
    `route_params`     JSON                                       COMMENT 'Parâmetros fixos da rota: {"tab":"overview"}',
    `permission_slug`  VARCHAR(200)                               COMMENT 'Slug da permissão exigida (NULL=autenticados)',
    `min_role`         ENUM('viewer','member','editor','admin')   COMMENT 'Papel mínimo para ver o item (mais restritivo)',
    `is_dynamic`       TINYINT(1)       NOT NULL DEFAULT 0        COMMENT '1=gerado em runtime (ex: workspaces do usuário)',
    `is_separator`     TINYINT(1)       NOT NULL DEFAULT 0        COMMENT '1=divisor visual (sem link, apenas linha)',
    `is_external`      TINYINT(1)       NOT NULL DEFAULT 0        COMMENT '1=abre em nova aba',
    `badge_source`     VARCHAR(100)                               COMMENT 'Método do MenuService para badge: getUnreadCount',
    `badge_color`      VARCHAR(7)       NOT NULL DEFAULT '#e2445c' COMMENT 'Cor HEX do badge',
    `open_in_group`    TINYINT(1)       NOT NULL DEFAULT 0        COMMENT '1=expande grupo ao clicar',
    `order_index`      SMALLINT UNSIGNED NOT NULL DEFAULT 0       COMMENT 'Posição dentro do pai (asc)',
    `is_active`        TINYINT(1)       NOT NULL DEFAULT 1        COMMENT '1=visível no menu',
    `created_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_menu_key`             (`key`),
    INDEX `idx_menu_parent_id`           (`parent_id`),
    INDEX `idx_menu_order`               (`parent_id`, `order_index`),
    INDEX `idx_menu_permission`          (`permission_slug`),
    CONSTRAINT `fk_menu_parent` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Itens do menu lateral — estrutura hierárquica semipermanente';

-- -----------------------------------------------------------------------------
-- Tabela: menu_item_roles
-- Controla quais PAPÉIS podem ver cada item de menu.
-- Permite granularidade maior do que apenas min_role.
--
-- Regra: se a tabela tiver registros para um item, aplica lista de inclusão.
--        se NÃO tiver registros, usa apenas permission_slug + min_role do item.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `menu_item_roles` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `menu_item_id` BIGINT UNSIGNED NOT NULL                COMMENT 'FK → menu_items.id',
    `role_id`      BIGINT UNSIGNED NOT NULL                COMMENT 'FK → roles.id',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_mir_item_role` (`menu_item_id`, `role_id`),
    INDEX `idx_mir_menu_item_id`  (`menu_item_id`),
    INDEX `idx_mir_role_id`       (`role_id`),
    CONSTRAINT `fk_mir_menu_item` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mir_role`      FOREIGN KEY (`role_id`)      REFERENCES `roles`      (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Papéis autorizados por item de menu (lista de inclusão)';

-- -----------------------------------------------------------------------------
-- Tabela: user_menu_state
-- Persiste o estado do menu por usuário (expandido/colapsado, sidebar aberto).
-- Decisão: JSON para não criar coluna por item — o estado cresce organicamente.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_menu_state` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `user_id`          BIGINT UNSIGNED NOT NULL                COMMENT 'FK → users.id',
    `sidebar_collapsed`TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '0=sidebar aberto, 1=sidebar recolhido (modo ícone)',
    `expanded_groups`  JSON                                    COMMENT 'Array de keys de grupos expandidos: ["workspaces","admin"]',
    `pinned_items`     JSON                                    COMMENT 'Array de keys fixados no topo: ["board_123","calendar"]',
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ums_user_id` (`user_id`),
    CONSTRAINT `fk_ums_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Estado persistido do menu lateral por usuário';

-- =============================================================================
-- MÓDULO: DASHBOARD E WIDGETS
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Tabela: dashboard_widget_catalog
-- Catálogo de TIPOS de widget disponíveis na plataforma.
-- Fixo — alterado apenas em novas versões do sistema.
--
-- Decisão: separar catálogo de instâncias permite validar widgets sem duplicar
--          definições em cada dashboard de cada usuário.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dashboard_widget_catalog` (
    `id`               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `key`              VARCHAR(100)     NOT NULL                 COMMENT 'Chave única: my_tasks, recent_activity, board_summary',
    `name`             VARCHAR(150)     NOT NULL                 COMMENT 'Nome exibido no seletor de widgets',
    `description`      TEXT                                      COMMENT 'O que este widget exibe',
    `category`         ENUM('productivity','overview','team','analytics','quick_access','custom')
                       NOT NULL DEFAULT 'overview'               COMMENT 'Categoria para organização no seletor',
    `icon`             VARCHAR(100)                              COMMENT 'Ícone do widget no catálogo',
    `component_class`  VARCHAR(200)     NOT NULL                 COMMENT 'Classe PHP responsável por renderizar: MyTasksWidget',
    `default_width`    TINYINT UNSIGNED NOT NULL DEFAULT 4       COMMENT 'Largura padrão em colunas (grade 12)',
    `default_height`   TINYINT UNSIGNED NOT NULL DEFAULT 3       COMMENT 'Altura padrão em linhas',
    `min_width`        TINYINT UNSIGNED NOT NULL DEFAULT 2       COMMENT 'Largura mínima redimensionável',
    `min_height`       TINYINT UNSIGNED NOT NULL DEFAULT 2       COMMENT 'Altura mínima',
    `permission_slug`  VARCHAR(200)                              COMMENT 'Permissão necessária para usar (NULL=todos)',
    `min_role`         ENUM('viewer','member','editor','admin')  COMMENT 'Papel mínimo para usar este widget',
    `is_active`        TINYINT(1)       NOT NULL DEFAULT 1       COMMENT '1=disponível para uso',
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dwc_key`       (`key`),
    INDEX `idx_dwc_category`      (`category`),
    INDEX `idx_dwc_permission`    (`permission_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de tipos de widget disponíveis na plataforma';

-- -----------------------------------------------------------------------------
-- Tabela: user_dashboard_layout
-- Layout personalizado do dashboard por usuário.
-- Cada linha = um widget instanciado com posição e configuração própria.
--
-- Decisão: grade 12 colunas (Bootstrap/CSS Grid) para compatibilidade universal.
-- Decisão: config JSON — cada widget define seu próprio schema de configuração.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_dashboard_layout` (
    `id`           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `user_id`      BIGINT UNSIGNED  NOT NULL                COMMENT 'FK → users.id',
    `catalog_key`  VARCHAR(100)     NOT NULL                COMMENT 'Referência ao dashboard_widget_catalog.key',
    `position_x`   TINYINT UNSIGNED NOT NULL DEFAULT 0      COMMENT 'Coluna inicial na grade 12 (0–11)',
    `position_y`   TINYINT UNSIGNED NOT NULL DEFAULT 0      COMMENT 'Linha na grade (ordem vertical)',
    `width`        TINYINT UNSIGNED NOT NULL DEFAULT 4      COMMENT 'Largura em colunas (1–12)',
    `height`       TINYINT UNSIGNED NOT NULL DEFAULT 3      COMMENT 'Altura em linhas',
    `config`       JSON                                     COMMENT 'Config específica do widget: {"board_id":5,"limit":10}',
    `is_visible`   TINYINT(1)       NOT NULL DEFAULT 1      COMMENT '0=widget ocultado pelo usuário',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_udl_user_id`    (`user_id`),
    INDEX `idx_udl_catalog_key`(`catalog_key`),
    CONSTRAINT `fk_udl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Layout personalizado do dashboard por usuário (grade 12 colunas)';

-- -----------------------------------------------------------------------------
-- Tabela: role_dashboard_defaults
-- Layout padrão do dashboard por PAPEL.
-- Aplicado quando um usuário ainda não tem layout personalizado.
-- Também usado para "restaurar padrão".
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_dashboard_defaults` (
    `id`           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `role_id`      BIGINT UNSIGNED  NOT NULL                COMMENT 'FK → roles.id',
    `catalog_key`  VARCHAR(100)     NOT NULL                COMMENT 'Widget do catálogo',
    `position_x`   TINYINT UNSIGNED NOT NULL DEFAULT 0      COMMENT 'Posição X na grade',
    `position_y`   TINYINT UNSIGNED NOT NULL DEFAULT 0      COMMENT 'Posição Y na grade',
    `width`        TINYINT UNSIGNED NOT NULL DEFAULT 4      COMMENT 'Largura padrão',
    `height`       TINYINT UNSIGNED NOT NULL DEFAULT 3      COMMENT 'Altura padrão',
    `config`       JSON                                     COMMENT 'Config padrão do widget para este papel',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_rdd_role_id`   (`role_id`),
    CONSTRAINT `fk_rdd_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Layout padrão do dashboard por papel (fallback do layout personalizado)';

-- =============================================================================
-- MÓDULO: PERMISSÕES EXPANDIDAS (user_permissions + workspace/board level)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Tabela: user_permissions
-- Permissões DIRETAS por usuário (sobrepõem ou complementam o papel).
-- Permite conceder/revogar permissão específica a um usuário sem mudar o papel.
--
-- type='grant'  → usuário tem esta permissão MESMO se o papel não tiver
-- type='revoke' → usuário NÃO tem esta permissão MESMO se o papel tiver
--
-- Decisão: tipo de override explícito é mais seguro do que flags booleanos
--          e evita lógica de "negative permissions" ambígua.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_permissions` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `user_id`       BIGINT UNSIGNED NOT NULL                COMMENT 'FK → users.id',
    `permission_id` BIGINT UNSIGNED NOT NULL                COMMENT 'FK → permissions.id',
    `type`          ENUM('grant','revoke') NOT NULL DEFAULT 'grant'
                                                            COMMENT 'grant=concede, revoke=nega explicitamente',
    `granted_by`    BIGINT UNSIGNED                         COMMENT 'FK → users.id — quem concedeu/revogou',
    `reason`        VARCHAR(500)                            COMMENT 'Justificativa da permissão direta',
    `expires_at`    DATETIME                                COMMENT 'Permissão temporária (NULL=permanente)',
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_up_user_permission` (`user_id`, `permission_id`),
    INDEX `idx_up_user_id`             (`user_id`),
    INDEX `idx_up_permission_id`       (`permission_id`),
    INDEX `idx_up_expires_at`          (`expires_at`),
    CONSTRAINT `fk_up_user`       FOREIGN KEY (`user_id`)       REFERENCES `users`       (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_up_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_up_granted_by` FOREIGN KEY (`granted_by`)    REFERENCES `users`       (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Permissões diretas por usuário (grant/revoke — sobrepõe papel)';

-- =============================================================================
-- DADOS INICIAIS: Catálogo de Menu Lateral
-- =============================================================================
INSERT IGNORE INTO `menu_items`
    (`key`, `parent_id`, `label`, `icon`, `route_key`, `permission_slug`, `min_role`, `order_index`, `is_dynamic`, `is_separator`, `badge_source`) VALUES

-- ── GRUPO: Início ─────────────────────────────────────────────────────────────
('home',                NULL, 'Início',          'fas fa-home',          'dashboard.index',       NULL,                    NULL,     10,  0, 0, NULL),

-- ── GRUPO: Workspaces ─────────────────────────────────────────────────────────
('workspaces_group',    NULL, 'Workspaces',       'fas fa-th-large',      NULL,                    NULL,                    NULL,     20,  0, 0, NULL),
('workspaces_list',     NULL, 'Meus Workspaces',  'fas fa-layer-group',   'workspaces.index',      NULL,                    NULL,     21,  1, 0, NULL),
('workspace_new',       NULL, 'Novo Workspace',   'fas fa-plus-circle',   'workspaces.create',     'workspaces.create',     'admin',  22,  0, 0, NULL),

-- ── SEPARADOR ─────────────────────────────────────────────────────────────────
('sep_boards',          NULL, '',                 NULL,                   NULL,                    NULL,                    NULL,     30,  0, 1, NULL),

-- ── GRUPO: Quadros ────────────────────────────────────────────────────────────
('boards_group',        NULL, 'Quadros',          'fas fa-columns',       NULL,                    NULL,                    NULL,     40,  0, 0, NULL),
('boards_my',           NULL, 'Meus Boards',      'fas fa-table',         'boards.my',             NULL,                    NULL,     41,  0, 0, NULL),
('boards_all',          NULL, 'Todos os Boards',  'fas fa-border-all',    'boards.index',          NULL,                    NULL,     42,  0, 0, NULL),
('boards_templates',    NULL, 'Templates',        'fas fa-copy',          'boards.templates',      'boards.create',         'editor', 43,  0, 0, NULL),

-- ── GRUPO: Produtividade ──────────────────────────────────────────────────────
('productivity_group',  NULL, 'Produtividade',    'fas fa-tasks',         NULL,                    NULL,                    NULL,     50,  0, 0, NULL),
('my_tasks',            NULL, 'Minhas Tarefas',   'fas fa-check-square',  'items.my_tasks',        NULL,                    NULL,     51,  0, 0, NULL),
('calendar',            NULL, 'Calendário',       'fas fa-calendar-alt',  'calendar.index',        NULL,                    NULL,     52,  0, 0, NULL),
('timeline_view',       NULL, 'Linha do Tempo',   'fas fa-stream',        'timeline.index',        NULL,                    NULL,     53,  0, 0, NULL),

-- ── SEPARADOR ─────────────────────────────────────────────────────────────────
('sep_reports',         NULL, '',                 NULL,                   NULL,                    NULL,                    NULL,     60,  0, 1, NULL),

-- ── GRUPO: Análises ───────────────────────────────────────────────────────────
('analytics_group',     NULL, 'Análises',         'fas fa-chart-bar',     NULL,                    NULL,                    NULL,     70,  0, 0, NULL),
('dashboards_my',       NULL, 'Meus Dashboards',  'fas fa-tachometer-alt','dashboards.index',      NULL,                    NULL,     71,  0, 0, NULL),
('reports',             NULL, 'Relatórios',       'fas fa-file-alt',      'reports.index',         'reports.view',          'editor', 72,  0, 0, NULL),

-- ── GRUPO: Automação ──────────────────────────────────────────────────────────
('sep_automations',     NULL, '',                 NULL,                   NULL,                    NULL,                    NULL,     80,  0, 1, NULL),
('automations',         NULL, 'Automações',       'fas fa-robot',         'automations.index',     'automations.manage',    'editor', 81,  0, 0, NULL),

-- ── GRUPO: Arquivos ───────────────────────────────────────────────────────────
('files',               NULL, 'Arquivos',         'fas fa-folder-open',   'files.index',           NULL,                    NULL,     90,  0, 0, NULL),

-- ── GRUPO: Notificações ───────────────────────────────────────────────────────
('notifications',       NULL, 'Notificações',     'fas fa-bell',          'notifications.index',   NULL,                    NULL,    100,  0, 0, 'getUnreadCount'),

-- ── SEPARADOR ADMIN ───────────────────────────────────────────────────────────
('sep_admin',           NULL, '',                 NULL,                   NULL,                    NULL,                    'admin', 110,  0, 1, NULL),

-- ── GRUPO: Administração ──────────────────────────────────────────────────────
('admin_group',         NULL, 'Administração',    'fas fa-cog',           NULL,                    NULL,                    'admin', 120,  0, 0, NULL),
('users_manage',        NULL, 'Usuários',         'fas fa-users',         'users.index',           'users.invite',          'admin', 121,  0, 0, NULL),
('roles_manage',        NULL, 'Perfis e Acessos', 'fas fa-user-shield',   'roles.index',           'roles.manage',          'admin', 122,  0, 0, NULL),
('settings',            NULL, 'Configurações',    'fas fa-sliders-h',     'settings.index',        'settings.manage',       'admin', 123,  0, 0, NULL),
('audit_logs',          NULL, 'Auditoria',        'fas fa-history',       'audit.index',           'settings.manage',       'admin', 124,  0, 0, NULL),

-- ── Suporte ───────────────────────────────────────────────────────────────────
('sep_support',         NULL, '',                 NULL,                   NULL,                    NULL,                    NULL,    130,  0, 1, NULL),
('support',             NULL, 'Suporte',          'fas fa-life-ring',     'support.index',         NULL,                    NULL,    131,  0, 0, NULL);

-- =============================================================================
-- DADOS INICIAIS: Catálogo de Widgets do Dashboard
-- =============================================================================
INSERT IGNORE INTO `dashboard_widget_catalog`
    (`key`, `name`, `description`, `category`, `icon`, `component_class`, `default_width`, `default_height`, `min_width`, `min_height`, `permission_slug`, `min_role`) VALUES
('my_tasks',          'Minhas Tarefas',          'Itens assignados a mim com prazo próximo',             'productivity', 'fas fa-check-square', 'MyTasksWidget',          4, 4, 3, 3, NULL,                'viewer'),
('overdue_items',     'Itens Atrasados',         'Itens com data limite vencida',                        'productivity', 'fas fa-exclamation',  'OverdueItemsWidget',     4, 3, 3, 2, NULL,                'viewer'),
('recent_activity',   'Atividade Recente',       'Últimas ações nos boards que participo',               'overview',     'fas fa-history',      'RecentActivityWidget',   6, 4, 4, 3, NULL,                'viewer'),
('board_summary',     'Resumo do Board',         'Contadores de itens por status de um board específico','overview',     'fas fa-table',        'BoardSummaryWidget',     4, 3, 3, 2, NULL,                'viewer'),
('team_workload',     'Carga da Equipe',         'Distribuição de tarefas entre membros',                'team',         'fas fa-users',        'TeamWorkloadWidget',     6, 4, 4, 3, NULL,                'editor'),
('notifications_feed','Notificações',            'Notificações não lidas',                               'overview',     'fas fa-bell',         'NotificationsFeedWidget', 4, 4, 3, 3, NULL,                'viewer'),
('items_by_status',   'Itens por Status',        'Gráfico de pizza: distribuição por status',            'analytics',    'fas fa-chart-pie',    'ItemsByStatusWidget',    4, 4, 3, 3, 'reports.view',      'editor'),
('items_over_time',   'Evolução de Itens',       'Gráfico de linha: itens criados/concluídos por semana','analytics',    'fas fa-chart-line',   'ItemsOverTimeWidget',    6, 4, 4, 3, 'reports.view',      'editor'),
('quick_access',      'Acesso Rápido',           'Boards e workspaces fixados pelo usuário',             'quick_access', 'fas fa-star',         'QuickAccessWidget',      4, 3, 3, 2, NULL,                'viewer'),
('users_online',      'Usuários Online',         'Membros ativos no último período',                     'team',         'fas fa-circle',       'UsersOnlineWidget',      3, 2, 3, 2, 'users.invite',      'admin'),
('storage_usage',     'Uso de Armazenamento',    'Espaço utilizado vs. cota do plano',                   'overview',     'fas fa-hdd',          'StorageUsageWidget',     3, 2, 3, 2, 'settings.manage',   'admin'),
('pending_invites',   'Convites Pendentes',      'Usuários convidados que ainda não aceitaram',          'team',         'fas fa-envelope',     'PendingInvitesWidget',   4, 3, 3, 2, 'users.invite',      'admin'),
('my_mentions',       'Minhas Menções',          'Comentários onde fui mencionado',                      'productivity', 'fas fa-at',           'MyMentionsWidget',       4, 3, 3, 2, NULL,                'viewer'),
('calendar_preview',  'Próximos Eventos',        'Itens com data nos próximos 7 dias',                   'productivity', 'fas fa-calendar',     'CalendarPreviewWidget',  4, 4, 3, 3, NULL,                'viewer');

-- =============================================================================
-- DADOS INICIAIS: Layout padrão por papel
-- =============================================================================

-- Admin: visão completa de gestão
INSERT IGNORE INTO `role_dashboard_defaults` (`role_id`, `catalog_key`, `position_x`, `position_y`, `width`, `height`) 
SELECT r.id, w.catalog_key, w.px, w.py, w.w, w.h
FROM roles r
CROSS JOIN (
    SELECT 'my_tasks'         catalog_key, 0 px, 0 py, 4 w, 4 h UNION ALL
    SELECT 'overdue_items',             4,  0,  4,  3 UNION ALL
    SELECT 'team_workload',             8,  0,  4,  4 UNION ALL
    SELECT 'recent_activity',           0,  4,  6,  4 UNION ALL
    SELECT 'notifications_feed',        6,  4,  3,  4 UNION ALL
    SELECT 'users_online',              9,  4,  3,  2 UNION ALL
    SELECT 'storage_usage',             9,  6,  3,  2 UNION ALL
    SELECT 'items_by_status',           0,  8,  4,  4 UNION ALL
    SELECT 'items_over_time',           4,  8,  6,  4 UNION ALL
    SELECT 'pending_invites',          10,  8,  2,  4
) w
WHERE r.slug = 'admin';

-- Editor: foco em produtividade + análises
INSERT IGNORE INTO `role_dashboard_defaults` (`role_id`, `catalog_key`, `position_x`, `position_y`, `width`, `height`)
SELECT r.id, w.catalog_key, w.px, w.py, w.w, w.h
FROM roles r
CROSS JOIN (
    SELECT 'my_tasks'         catalog_key, 0 px, 0 py, 5 w, 4 h UNION ALL
    SELECT 'overdue_items',             5,  0,  4,  3 UNION ALL
    SELECT 'calendar_preview',          9,  0,  3,  4 UNION ALL
    SELECT 'recent_activity',           0,  4,  6,  4 UNION ALL
    SELECT 'notifications_feed',        6,  4,  3,  4 UNION ALL
    SELECT 'my_mentions',               9,  4,  3,  4 UNION ALL
    SELECT 'items_by_status',           0,  8,  4,  4 UNION ALL
    SELECT 'quick_access',              4,  8,  4,  3
) w
WHERE r.slug = 'editor';

-- Visualizador: apenas consulta e produtividade pessoal
INSERT IGNORE INTO `role_dashboard_defaults` (`role_id`, `catalog_key`, `position_x`, `position_y`, `width`, `height`)
SELECT r.id, w.catalog_key, w.px, w.py, w.w, w.h
FROM roles r
CROSS JOIN (
    SELECT 'my_tasks'         catalog_key, 0 px, 0 py, 6 w, 5 h UNION ALL
    SELECT 'calendar_preview',          6,  0,  6,  5 UNION ALL
    SELECT 'recent_activity',           0,  5,  6,  4 UNION ALL
    SELECT 'notifications_feed',        6,  5,  3,  4 UNION ALL
    SELECT 'my_mentions',               9,  5,  3,  4 UNION ALL
    SELECT 'quick_access',              0,  9,  4,  3
) w
WHERE r.slug = 'viewer';

SET FOREIGN_KEY_CHECKS = 1;
