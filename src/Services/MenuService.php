<?php
/**
 * MenuService — Montagem do Menu Lateral Dinâmico
 *
 * RESPONSABILIDADE ÚNICA:
 *   Dado um usuário autenticado, retornar a árvore de itens de menu
 *   que ele tem permissão de ver — pronta para renderização na view.
 *
 * FLUXO DE MONTAGEM:
 * ─────────────────────────────────────────────────────────────────
 *  1. Carrega todos os menu_items ativos (cache 10 min — raramente muda)
 *     ORDER BY parent_id ASC, order_index ASC
 *
 *  2. Filtra cada item aplicando as regras em ordem:
 *     a. is_active = 1
 *     b. min_role: compara com o papel mais alto do usuário
 *        hierarquia: viewer < member < editor < admin
 *     c. permission_slug: se preenchido, chama PermissionService::can()
 *     d. menu_item_roles: se a tabela tiver registros para o item,
 *        verifica se algum role do usuário está na lista
 *
 *  3. Para itens is_dynamic = 1:
 *     - 'workspaces_list': carrega workspace_members WHERE user_id=X
 *       → monta subitens dinamicamente com workspace.name + board count
 *     - Outros dinâmicos seguem o mesmo padrão
 *
 *  4. Monta a árvore hierárquica: parent_id → children[]
 *     - Remove grupos (parent_id=NULL) que ficaram sem filhos visíveis
 *     - Remove separadores órfãos (antes de grupos vazios)
 *
 *  5. Para cada item visível com badge_source preenchido:
 *     - Chama $this->{$item['badge_source']}()
 *     - Ex: getUnreadCount() → SELECT COUNT(*) FROM notifications WHERE user_id=X AND is_read=0
 *
 *  6. Carrega user_menu_state (expandido/colapsado/pinned) para o usuário
 *
 *  7. Retorna MenuTree object com:
 *     - items: array hierárquico filtrado e enriquecido
 *     - sidebarCollapsed: bool
 *     - expandedGroups: array de keys
 *     - pinnedItems: array de keys
 *
 * CACHE:
 *   - Estrutura base (sem filtro de usuário): APCu/arquivo por 10 min
 *   - Badges: NUNCA cacheados — sempre em tempo real (são counts simples)
 *   - Estado do usuário: vem do banco user_menu_state (leve, 1 row por usuário)
 *
 * SEGURANÇA:
 *   - Filtro acontece exclusivamente no servidor — nunca no frontend
 *   - URL de cada item é gerada pelo Router::url(routeKey) — não vem do banco
 *   - Mesmo que o item seja visível no menu, o backend valida permissão no request
 *
 * @package Conecta360\Services
 */

declare(strict_types=1);

namespace Conecta360\Services;

use Conecta360\Models\User;
use PDO;

final class MenuService
{
    public function __construct(
        private readonly PDO               $pdo,
        private readonly PermissionService $permissions
    ) {}

    /**
     * Retorna a árvore de menu filtrada e enriquecida para o usuário.
     * Resultado pronto para passar à view partial do menu.
     */
    public function buildForUser(User $user): array { /* ... */ }

    /** Carrega e cacheia os itens base do banco */
    private function loadBaseItems(): array { /* ... */ }

    /** Aplica filtros de permissão e papel para um item e usuário */
    private function isItemVisible(array $item, User $user): bool { /* ... */ }

    /** Monta itens dinâmicos (workspaces, boards fixados, etc.) */
    private function resolveDynamicItems(array $items, User $user): array { /* ... */ }

    /** Constrói árvore hierárquica a partir do array plano */
    private function buildTree(array $flatItems): array { /* ... */ }

    /** Remove grupos vazios e separadores órfãos da árvore */
    private function pruneEmptyGroups(array $tree): array { /* ... */ }

    /** Resolve badges em tempo real para itens com badge_source */
    private function resolveBadges(array $tree, User $user): array { /* ... */ }

    /** Gera URL de cada item via Router::url() */
    private function resolveUrls(array $tree): array { /* ... */ }

    // ── Badge sources ────────────────────────────────────────────────────────

    /** Badge de notificações não lidas */
    private function getUnreadCount(int $userId): int { /* ... */ }

    /** Badge de tarefas vencidas */
    private function getOverdueCount(int $userId): int { /* ... */ }
}
