<?php
/**
 * DashboardService — Dados dos Widgets do Dashboard
 *
 * Orquestra a coleta de dados de cada widget para o usuário autenticado.
 *
 * RESPONSABILIDADES:
 *   - Carregar o layout personalizado do usuário (user_dashboard_layout)
 *   - Se não houver layout personalizado: usar role_dashboard_defaults
 *   - Para cada widget no layout: verificar permissão e chamar o componente
 *   - Retornar array de widgets com dados prontos para a view
 *
 * FLUXO:
 * ─────────────────────────────────────────────────────────────────
 *  1. Carrega layout:
 *       SELECT udl.*, dwc.component_class, dwc.min_role, dwc.permission_slug
 *       FROM user_dashboard_layout udl
 *       JOIN dashboard_widget_catalog dwc ON dwc.key = udl.catalog_key
 *       WHERE udl.user_id = :uid AND udl.is_visible = 1
 *
 *       Se 0 resultados → usa role_dashboard_defaults do papel do usuário
 *
 *  2. Para cada widget do layout:
 *     a. Verifica permissão: PermissionService::can(widget.permission_slug)
 *     b. Verifica min_role: PermissionService::getHighestRole() >= widget.min_role
 *     c. Se autorizado: instancia o componente (ex: new MyTasksWidget($pdo, $config))
 *     d. Chama $widget->getData($userId) → retorna array de dados
 *     e. Chama $widget->getTemplate() → retorna caminho da view partial
 *     f. Adiciona ao array de resultado com: dados + template + posição
 *
 *  3. Retorna array de widgets renderizáveis ordenados por position_y, position_x
 *
 * WIDGETS DISPONÍVEIS — responsabilidade de cada componente:
 *   MyTasksWidget         → items WHERE assignee_id=X AND deleted_at IS NULL ORDER BY due_date
 *   OverdueItemsWidget    → item_values WHERE value_date < NOW() AND item assignado ao user
 *   RecentActivityWidget  → activity_logs WHERE user_id em boards que o user participa
 *   BoardSummaryWidget    → item_values GROUP BY status para um board específico
 *   TeamWorkloadWidget    → COUNT(items) GROUP BY assignee_id (só visible ao editor+)
 *   NotificationsFeedWidget→ notifications WHERE user_id=X AND is_read=0 ORDER BY created_at
 *   ItemsByStatusWidget   → gráfico pie via item_values + board_columns
 *   ItemsOverTimeWidget   → items criados/concluídos por semana (últimas 8 semanas)
 *   QuickAccessWidget     → workspace_members + board_members ORDER BY last_accessed_at
 *   UsersOnlineWidget     → user_sessions WHERE last_activity_at > NOW()-15min (só admin)
 *   StorageUsageWidget    → SUM(file_size) FROM attachments + plano do tenant
 *   PendingInvitesWidget  → users WHERE status='invited' AND created_at > NOW()-30d
 *   MyMentionsWidget      → comment_mentions WHERE user_id=X AND is_read=0
 *   CalendarPreviewWidget → item_values WHERE value_date BETWEEN NOW() AND NOW()+7d
 *
 * PERSISTÊNCIA DE LAYOUT:
 *   saveLayout(userId, widgets[]): atualiza user_dashboard_layout
 *   resetLayout(userId): DELETE FROM user_dashboard_layout WHERE user_id=X
 *   toggleWidget(userId, catalogKey, visible): atualiza is_visible
 *
 * @package Conecta360\Services
 */

declare(strict_types=1);

namespace Conecta360\Services;

use Conecta360\Models\User;
use PDO;

final class DashboardService
{
    public function __construct(
        private readonly PDO               $pdo,
        private readonly PermissionService $permissions
    ) {}

    /** Retorna widgets renderizáveis para o usuário, com dados já carregados */
    public function getWidgetsForUser(User $user): array { /* ... */ }

    /** Carrega layout personalizado ou padrão do papel */
    private function loadLayout(User $user): array { /* ... */ }

    /** Instancia e executa o componente de widget */
    private function resolveWidget(array $layoutRow, User $user): ?array { /* ... */ }

    /** Salva layout personalizado do usuário */
    public function saveLayout(int $userId, array $widgets): void { /* ... */ }

    /** Restaura layout padrão do papel */
    public function resetLayout(int $userId, int $roleId): void { /* ... */ }

    /** Retorna catálogo completo de widgets disponíveis para o usuário */
    public function getAvailableCatalog(User $user): array { /* ... */ }
}
