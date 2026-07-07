<?php
/**
 * AuthMiddleware — Guarda de Autenticação
 *
 * Executado em TODAS as rotas protegidas antes do controller.
 *
 * FLUXO:
 * ──────────────────────────────────────────────────────────
 *  1. SessionService::isAuthenticated() → verifica $_SESSION
 *  2. Se sessão inválida → tenta renovar via cookie "remember_me"
 *       a. Lê o cookie remember_me
 *       b. Gera SHA-256 do valor do cookie
 *       c. Consulta user_sessions WHERE token_hash=hash AND expires_at>NOW() AND revoked_at IS NULL
 *       d. Verifica fingerprint (User-Agent + IP parcial)
 *       e. Se válido: recria sessão PHP, rotaciona o token (sliding expiration)
 *       f. Se inválido: apaga o cookie e redireciona para login
 *  3. Se sessão válida mas expirada por inatividade → logout + redirect login
 *  4. Se sessão válida:
 *       a. Atualiza last_activity_at na sessão
 *       b. Verifica se o usuário ainda existe no banco tenant
 *       c. Verifica se o usuário está ativo (status='active')
 *       d. Passa para o próximo middleware / controller
 *  5. Redirect para login preserva a URL original em ?redirect= (para retorno pós-login)
 *
 * SEGURANÇA:
 *   - Verificação de existência do usuário no banco a cada N requests (não toda req)
 *     para detectar usuários deletados/bloqueados sem sobrecarregar o banco
 *   - Cookie remember_me: HttpOnly, Secure, SameSite=Strict, Path=/
 *   - Token no cookie é raw; banco guarda apenas o hash
 *
 * @package Conecta360\Middleware
 */

declare(strict_types=1);

namespace Conecta360\Middleware;

use Conecta360\Services\SessionService;

final class AuthMiddleware
{
    /** Executa o middleware e chama o próximo handler se autenticado */
    public function handle(array $params, callable $next): void { /* ... */ }

    /** Tenta renovar sessão via cookie remember_me */
    private function tryRememberMe(): bool { /* ... */ }

    /** Rotaciona o token remember_me (sliding expiration) */
    private function rotateRememberMeToken(int $sessionId, string $userId): void { /* ... */ }

    /** Redireciona para login preservando URL de destino */
    private function redirectToLogin(): never { /* ... */ }
}
