<?php
/**
 * PermissionService — Motor Central de Autorização
 *
 * Responsável por responder UMA pergunta: "Este usuário pode fazer X?"
 *
 * ALGORITMO DE RESOLUÇÃO (em ordem de prioridade):
 * ────────────────────────────────────────────────────────────────
 *  Para verificar se user_id=5 tem permissão "items.delete":
 *
 *  1. Verifica user_permissions (permissões diretas)
 *     - type='revoke' para este slug → NEGA imediatamente (highest priority)
 *     - type='grant'  para este slug → CONCEDE imediatamente
 *     - Verifica expires_at se aplicável
 *
 *  2. Verifica se o usuário é admin (role slug='admin')
 *     → admins têm todas as permissões implicitamente → CONCEDE
 *
 *  3. Verifica role_permissions (permissão via papel)
 *     - JOIN user_roles → role_permissions → permissions WHERE slug=:slug
 *     → Se encontrar → CONCEDE
 *
 *  4. Nenhuma regra concedeu → NEGA
 *
 * CACHE:
 *   Ao fazer login (SessionService::login), carrega:
 *     SELECT p.slug, 'role' as src FROM permissions p
 *     JOIN role_permissions rp ON rp.permission_id = p.id
 *     JOIN user_roles ur ON ur.role_id = rp.role_id
 *     WHERE ur.user_id = :uid
 *   UNION ALL
 *     SELECT p.slug, CONCAT('direct_',up.type) as src FROM permissions p
 *     JOIN user_permissions up ON up.permission_id = p.id
 *     WHERE up.user_id = :uid AND (up.expires_at IS NULL OR up.expires_at > NOW())
 *
 *   Armazena em $_SESSION['perms'] = ['items.delete','boards.create',...]
 *   Armazena revokes em $_SESSION['perms_revoke'] = ['settings.manage']
 *   is_admin flag em $_SESSION['is_admin'] = true/false
 *
 * VERIFICAÇÃO RÁPIDA (sem banco):
 *   can('items.delete')
 *     1. se slug em perms_revoke → false
 *     2. se is_admin → true
 *     3. se slug em perms → true
 *     4. → false
 *
 * QUANDO INVALIDAR O CACHE:
 *   - Quando admin alterar papel do usuário
 *   - Quando admin conceder/revogar permissão direta
 *   - Invalidar via: SessionService::invalidatePermissions($userId)
 *     que remove $_SESSION['perms'] da sessão daquele usuário
 *     (em multi-processo: usar tabela user_sessions ou APCu compartilhado)
 *
 * VERIFICAÇÃO WORKSPACE/BOARD LEVEL:
 *   canInWorkspace(userId, workspaceId, permission)
 *   canInBoard(userId, boardId, permission)
 *   → Consulta workspace_members.role ou board_members.role
 *   → Converte o papel contextual para permissão usando mapa local
 *   → Cache em $_SESSION['ws_roles'][workspaceId] = 'editor'
 *
 * @package Conecta360\Services
 */

declare(strict_types=1);

namespace Conecta360\Services;

use PDO;

final class PermissionService
{
    /**
     * Verifica permissão usando cache de sessão.
     * NUNCA faz query ao banco — usa $_SESSION['perms'].
     * Rápida o suficiente para chamar em qualquer ponto do código.
     */
    public static function can(string $permissionSlug): bool { /* ... */ }

    /**
     * Verifica permissão consultando o banco diretamente.
     * Use apenas quando o cache estiver desatualizado ou para auditoria.
     */
    public static function canDb(int $userId, string $permissionSlug, PDO $pdo): bool { /* ... */ }

    /**
     * Verifica o papel do usuário em um workspace específico.
     * Papel contextual: owner > admin > member > viewer
     */
    public static function canInWorkspace(int $userId, int $workspaceId, string $minRole): bool { /* ... */ }

    /**
     * Verifica o papel do usuário em um board específico.
     * Verifica também acesso herdado pelo workspace.
     */
    public static function canInBoard(int $userId, int $boardId, string $minRole): bool { /* ... */ }

    /**
     * Carrega e armazena em sessão todas as permissões do usuário.
     * Chamado pelo SessionService::login() — executado uma vez por sessão.
     */
    public static function loadIntoSession(int $userId, PDO $pdo): void { /* ... */ }

    /**
     * Invalida o cache de permissões da sessão.
     * Chamado quando admin altera papéis ou permissões diretas.
     */
    public static function invalidateCache(int $userId): void { /* ... */ }

    /** Verifica se o usuário tem o papel admin */
    public static function isAdmin(): bool { /* ... */ }

    /** Retorna o papel mais alto do usuário (usado para min_role do menu) */
    public static function getHighestRole(): string { /* ... */ }

    /**
     * Verifica MÚLTIPLAS permissões de uma vez.
     * any(['boards.create','boards.edit']) → true se tiver qualquer uma
     * all(['items.create','items.edit'])   → true se tiver todas
     */
    public static function any(array $slugs): bool { /* ... */ }
    public static function all(array $slugs): bool { /* ... */ }
}
