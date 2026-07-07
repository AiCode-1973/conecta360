<?php
/**
 * AuthService — Lógica de Negócio de Autenticação
 *
 * Único serviço que conhece as regras de autenticação.
 * Controllers são burros — apenas chamam AuthService e retornam a View.
 *
 * MÉTODO: login(email, password, rememberMe, ip, userAgent)
 * ──────────────────────────────────────────────────────────
 *  1. BruteForceGuard::isBlocked(email, ip) → se bloqueado: lança BruteForceException
 *  2. SELECT id, name, email, password, status, ... FROM users WHERE email=:email LIMIT 1
 *      → Prepared statement (nunca interpolar e-mail na query)
 *  3. Se usuário não encontrado:
 *       - Executa password_verify('dummy', FAKE_HASH) ← timing attack mitigation
 *       - BruteForceGuard::recordAttempt(email, ip, false)
 *       - Lança InvalidCredentialsException (mensagem genérica)
 *  4. password_verify($password, $user['password']) → se falso:
 *       - BruteForceGuard::recordAttempt(email, ip, false)
 *       - Lança InvalidCredentialsException (MESMA mensagem genérica)
 *  5. Verifica $user['status']:
 *       - 'invited' → lança AccountNotActivatedException
 *       - 'inactive' ou 'blocked' → lança AccountDisabledException (mensagem genérica)
 *  6. Verifica $user['locked_until'] → se no futuro: lança BruteForceException
 *  7. BruteForceGuard::recordAttempt(email, ip, true)
 *  8. BruteForceGuard::clearAttemptsForEmail(email)
 *  9. Detecta se senha precisa de rehash (password_needs_rehash)
 *     → se sim, atualiza o hash no banco transparentemente
 * 10. SessionService::login($user) → cria sessão segura
 * 11. Se $rememberMe=true → cria registro em user_sessions, seta cookie
 * 12. Retorna objeto User
 *
 * MÉTODO: logout(userId)
 * ──────────────────────────────────────────────────────────
 *  1. Revoga a sessão persistente (user_sessions.revoked_at = NOW())
 *  2. SessionService::logout() → destrói sessão PHP
 *  3. Remove cookie remember_me (seta com expired no passado)
 *
 * MÉTODO: register(name, email, password, jobTitle)
 * ──────────────────────────────────────────────────────────
 *  1. Valida e sanitiza todos os campos
 *  2. Verifica unicidade do e-mail: SELECT COUNT(*) WHERE email=:email
 *  3. password_hash($password, PASSWORD_BCRYPT, ['cost' => env('BCRYPT_COST', 12)])
 *  4. INSERT INTO users (name, email, password, status='invited', ...)
 *  5. Cria token de verificação: random_bytes(32) → raw
 *     Salva hash em email_verifications: hash('sha256', $tokenRaw)
 *  6. Envia e-mail com link: https://{subdomain}.conecta360.com.br/verify-email?token={tokenRaw}
 *  7. Retorna User com status='invited'
 *
 * MÉTODO: verifyEmail(tokenRaw)
 * ──────────────────────────────────────────────────────────
 *  1. hash('sha256', $tokenRaw)
 *  2. SELECT * FROM email_verifications WHERE token_hash=:hash AND verified_at IS NULL AND expires_at>NOW()
 *  3. Se não encontrado → lança InvalidTokenException
 *  4. UPDATE users SET status='active', email_verified_at=NOW()
 *  5. UPDATE email_verifications SET verified_at=NOW()
 *
 * MÉTODO: forgotPassword(email, ip)
 * ──────────────────────────────────────────────────────────
 *  1. Valida formato do e-mail
 *  2. SELECT id FROM users WHERE email=:email → se não existir: retorna silenciosamente
 *     (não revelar se o e-mail existe — anti-enumeration)
 *  3. Invalida tokens anteriores deste e-mail: UPDATE password_resets SET used_at=NOW()
 *  4. Gera token: random_bytes(32) → base64url encode
 *  5. Salva: INSERT password_resets (email, token_hash=sha256(token), expires_at=+60min)
 *  6. Envia e-mail com link de reset
 *  7. Retorna sempre true (mesmo se e-mail não existir — anti-enumeration)
 *
 * MÉTODO: resetPassword(tokenRaw, newPassword)
 * ──────────────────────────────────────────────────────────
 *  1. hash('sha256', $tokenRaw)
 *  2. SELECT pr.*, u.id FROM password_resets pr JOIN users u ON u.email=pr.email
 *     WHERE pr.token_hash=:hash AND pr.used_at IS NULL AND pr.expires_at>NOW()
 *  3. Se não encontrado → lança InvalidTokenException
 *  4. Valida nova senha (mínimo 8 chars, complexidade)
 *  5. UPDATE users SET password=password_hash(newPass), updated_at=NOW()
 *  6. UPDATE password_resets SET used_at=NOW() (invalida o token)
 *  7. Revoga TODAS as sessões do usuário (user_sessions.revoked_at = NOW())
 *  8. Força re-login
 *
 * @package Conecta360\Modules\Auth
 */

declare(strict_types=1);

namespace Conecta360\Modules\Auth;

use Conecta360\Services\SessionService;
use Conecta360\Services\BruteForceGuard;
use Conecta360\Services\CsrfService;
use PDO;

final class AuthService
{
    public function __construct(
        private readonly PDO             $pdo,
        private readonly BruteForceGuard $bruteForce,
        private readonly SessionService  $session
    ) {}

    public function login(string $email, string $password, bool $rememberMe, string $ip, string $userAgent): array { /* ... */ }
    public function logout(int $userId): void { /* ... */ }
    public function register(array $data): array { /* ... */ }
    public function verifyEmail(string $tokenRaw): bool { /* ... */ }
    public function forgotPassword(string $email, string $ip): void { /* ... */ }
    public function resetPassword(string $tokenRaw, string $newPassword): void { /* ... */ }

    /** Cria sessão persistente e seta cookie remember_me seguro */
    private function createRememberMeSession(int $userId, string $userAgent, string $ip): void { /* ... */ }

    /** Revoga todas as sessões ativas de um usuário */
    private function revokeAllSessions(int $userId): void { /* ... */ }

    /** Executa password_verify contra um hash fake (timing attack mitigation) */
    private function runDummyVerification(): void { /* ... */ }
}
