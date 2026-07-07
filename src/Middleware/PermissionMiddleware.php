<?php
/**
 * PermissionMiddleware — Autorização Granular por Rota
 *
 * Verifica se o usuário autenticado possui a permissão exigida pela rota.
 * Deve ser executado APÓS o AuthMiddleware (usuário já autenticado).
 *
 * ESTRATÉGIA DE CACHE DE PERMISSÕES:
 * ──────────────────────────────────────────────────────────
 *   1. Na criação da sessão (login), carrega todas as permissões do usuário:
 *        SELECT p.slug FROM permissions p
 *        JOIN role_permissions rp ON rp.permission_id = p.id
 *        JOIN user_roles ur ON ur.role_id = rp.role_id
 *        WHERE ur.user_id = :user_id
 *   2. Armazena o array de slugs em $_SESSION['permissions']
 *   3. O middleware verifica in_array($requiredSlug, $_SESSION['permissions'])
 *   4. Ao revogar/alterar perfil → SessionService invalida a sessão do usuário
 *
 * USO NAS ROTAS:
 *   $router->get('/boards/delete/{id}',
 *       [BoardController::class, 'delete'],
 *       [AuthMiddleware::class, new PermissionMiddleware('boards.delete')]
 *   );
 *
 * ADMIN BYPASS:
 *   Usuários com role 'admin' possuem permissão implícita em tudo.
 *   Verificado antes do array de permissões para evitar query.
 *
 * @package Conecta360\Middleware
 */

declare(strict_types=1);

namespace Conecta360\Middleware;

final class PermissionMiddleware
{
    public function __construct(
        /** Slug da permissão exigida: "boards.create", "items.delete" */
        private readonly string $requiredPermission
    ) {}

    public function handle(array $params, callable $next): void { /* ... */ }

    /** Verifica se o usuário da sessão tem a permissão no cache de sessão */
    private function userHasPermission(string $permissionSlug): bool { /* ... */ }

    /** Resposta de acesso negado (HTML ou JSON conforme Accept header) */
    private function forbidden(): never { /* ... */ }
}
