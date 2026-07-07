<?php
/**
 * CsrfService — Proteção CSRF (Cross-Site Request Forgery)
 *
 * ESTRATÉGIA: Synchronizer Token Pattern (armazenado na $_SESSION)
 *
 * FLUXO:
 * ──────────────────────────────────────────────────────────
 *  Geração (GET /login):
 *    1. generateToken() gera 32 bytes aleatórios via random_bytes()
 *    2. Token codificado em base64url e armazenado em $_SESSION['csrf_token']
 *    3. Token injetado no formulário HTML via campo oculto: <input type="hidden" name="_csrf" value="...">
 *
 *  Validação (POST /login):
 *    1. validateToken() compara $_POST['_csrf'] com $_SESSION['csrf_token']
 *    2. Usa hash_equals() (tempo constante — evita timing attack)
 *    3. Falha → HTTP 419 (token expirado) ou 403 (inválido)
 *    4. Sucesso → opcionalmente regenera o token (per-form tokens)
 *
 * SEGURANÇA:
 *   - random_bytes() é criptograficamente seguro (não usar rand() ou mt_rand())
 *   - hash_equals() garante comparação em tempo constante (evita timing attack)
 *   - Token vinculado à sessão → inválido após logout
 *   - Em SPAs/AJAX: enviar token no header X-CSRF-Token
 *
 * @package Conecta360\Services
 */

declare(strict_types=1);

namespace Conecta360\Services;

final class CsrfService
{
    private const SESSION_KEY = 'csrf_token';
    private const TOKEN_BYTES = 32;

    /** Gera (ou retorna existente) o token CSRF da sessão atual */
    public static function generateToken(): string { /* ... */ }

    /**
     * Valida o token recebido contra o armazenado na sessão.
     * Usa hash_equals() — comparação em tempo constante.
     * @throws \Conecta360\Exceptions\CsrfException em caso de token inválido
     */
    public static function validateToken(string $receivedToken): bool { /* ... */ }

    /** Renderiza o campo hidden para formulários HTML */
    public static function field(): string { /* ... */ }

    /** Regenera o token (usar após consumo em per-form tokens) */
    public static function regenerate(): string { /* ... */ }

    /** Invalida o token atual (usar no logout) */
    public static function invalidate(): void { /* ... */ }
}
