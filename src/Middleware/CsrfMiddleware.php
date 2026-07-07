<?php
/**
 * CsrfMiddleware — Validação de Token CSRF
 *
 * Executa em todos os métodos mutantes: POST, PUT, PATCH, DELETE
 *
 * Ordem de verificação do token (prioridade):
 *   1. Header HTTP "X-CSRF-Token" (AJAX/fetch)
 *   2. Campo POST "_csrf" (formulários HTML)
 *
 * Resposta em caso de falha:
 *   - Requisição HTML (Accept: text/html) → HTTP 419 + redirect com flash message
 *   - Requisição AJAX (Accept: application/json) → HTTP 419 + JSON {"error":"CSRF token inválido"}
 *
 * @package Conecta360\Middleware
 */

declare(strict_types=1);

namespace Conecta360\Middleware;

use Conecta360\Services\CsrfService;

final class CsrfMiddleware
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(array $params, callable $next): void { /* ... */ }

    /** Extrai o token do header ou do POST body */
    private function extractToken(): ?string { /* ... */ }

    /** Verifica se é uma requisição AJAX */
    private function isAjax(): bool { /* ... */ }
}
