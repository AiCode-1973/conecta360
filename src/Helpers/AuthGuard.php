<?php
/**
 * AuthGuard — Helper de autorização inline
 *
 * Funções auxiliares para uso nas views e controllers.
 * Abstraem chamadas ao PermissionService com sintaxe curta.
 *
 * USO NAS VIEWS:
 *   <?php if (can('boards.create')): ?>
 *       <button>Novo Board</button>
 *   <?php endif ?>
 *
 *   <?php if (isAdmin()): ?>
 *       <a href="/settings">Configurações</a>
 *   <?php endif ?>
 *
 *   <?php if (hasRole('editor')): ?>
 *       ... bloco para editor ou acima ...
 *   <?php endif ?>
 *
 * USO NOS CONTROLLERS (proteção de rotas):
 *   requirePermission('items.delete');  // Lança ForbiddenException se não tiver
 *   requireRole('editor');              // Lança ForbiddenException se abaixo
 *   requireAuth();                      // Redireciona para login se não autenticado
 *
 * HIERARQUIA DE PAPÉIS:
 *   viewer < member < editor < admin
 *   hasMinRole('editor') retorna true para editor E admin
 *
 * PROTEÇÃO DE RECURSOS CONTEXTUAIS:
 *   requireWorkspaceRole($workspaceId, 'member')
 *   requireBoardRole($boardId, 'editor')
 *   → lança ForbiddenException se o papel contextual for insuficiente
 *
 * @package Conecta360\Helpers
 */

declare(strict_types=1);

namespace Conecta360\Helpers;

use Conecta360\Services\PermissionService;
use Conecta360\Services\SessionService;
use Conecta360\Exceptions\ForbiddenException;
use Conecta360\Exceptions\UnauthorizedException;

final class AuthGuard
{
    /** Verifica permissão (usa cache de sessão — sem query) */
    public static function can(string $slug): bool { /* ... */ }

    /** Lança ForbiddenException se não tiver a permissão */
    public static function requirePermission(string $slug): void { /* ... */ }

    /** Verifica se o usuário tem o papel exato */
    public static function hasRole(string $roleSlug): bool { /* ... */ }

    /** Verifica se o usuário tem o papel mínimo (inclui superiores) */
    public static function hasMinRole(string $minRoleSlug): bool { /* ... */ }

    /** Lança ForbiddenException se o papel for inferior ao mínimo */
    public static function requireRole(string $minRoleSlug): void { /* ... */ }

    /** Verifica se é admin (bypass total) */
    public static function isAdmin(): bool { /* ... */ }

    /** Lança UnauthorizedException se não estiver autenticado */
    public static function requireAuth(): void { /* ... */ }

    /** Lança ForbiddenException se não tiver papel mínimo no workspace */
    public static function requireWorkspaceRole(int $workspaceId, string $minRole): void { /* ... */ }

    /** Lança ForbiddenException se não tiver papel mínimo no board */
    public static function requireBoardRole(int $boardId, string $minRole): void { /* ... */ }

    /**
     * Retorna HTTP 403 ou redireciona dependendo do tipo de request.
     * AJAX (Accept: application/json) → JSON {"error":"Acesso negado"}
     * HTML → redirect /403 com flash message
     */
    public static function forbidden(string $message = 'Acesso negado'): never { /* ... */ }
}

// ── Funções globais de conveniência para uso nas views ───────────────────────
// (Importar no bootstrap para disponibilizar nas views sem namespace)

function can(string $slug): bool         { return AuthGuard::can($slug); }
function isAdmin(): bool                  { return AuthGuard::isAdmin(); }
function hasRole(string $slug): bool      { return AuthGuard::hasRole($slug); }
function hasMinRole(string $slug): bool   { return AuthGuard::hasMinRole($slug); }
function currentUser(): ?array            { return SessionService::getUser(); }
