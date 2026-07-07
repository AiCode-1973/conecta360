<?php
/**
 * BruteForceGuard — Proteção contra Ataques de Força Bruta
 *
 * ESTRATÉGIA DUPLA: bloqueio por IP E por e-mail (identificador)
 * Nenhuma mensagem ao usuário revela qual dos dois está bloqueado (anti-enumeration)
 *
 * LIMITES (configuráveis via .env):
 * ──────────────────────────────────────────────────────────
 *  Por e-mail:
 *    - 5 falhas em 10 minutos → bloqueia por 15 minutos
 *    - Contagem zerada ao fazer login com sucesso
 *
 *  Por IP:
 *    - 20 falhas em 15 minutos → bloqueia por 30 minutos
 *    - IPs da rede interna/localhost são isentos (whitelist)
 *
 * FLUXO:
 * ──────────────────────────────────────────────────────────
 *  isBlocked(email, ip):
 *    1. Consulta login_attempts WHERE (identifier=email OR ip_address=ip)
 *         AND success=0 AND created_at > NOW() - INTERVAL X MINUTE
 *    2. Conta separadamente por email e por IP
 *    3. Retorna true se qualquer um exceder o limite
 *    4. Resposta genérica: "Muitas tentativas. Tente novamente em alguns minutos."
 *       → não informa se é por IP ou e-mail (evita enumeração)
 *
 *  recordAttempt(email, ip, success):
 *    1. Insere em login_attempts
 *    2. Se success=true, atualiza users.failed_login_count=0, locked_until=NULL
 *    3. Se success=false e atingiu limite do usuário, atualiza users.locked_until
 *
 * MENSAGENS DE ERRO (nunca revelar o motivo exato):
 *   - Usuário não existe      → "E-mail ou senha inválidos"
 *   - Senha errada            → "E-mail ou senha inválidos"
 *   - Conta bloqueada         → "E-mail ou senha inválidos"  ← MESMO texto
 *   - IP bloqueado            → "Muitas tentativas. Aguarde X minutos."
 *   - Conta suspensa pelo adm → "Conta desativada. Entre em contato com o suporte."
 *
 * @package Conecta360\Services
 */

declare(strict_types=1);

namespace Conecta360\Services;

use PDO;

final class BruteForceGuard
{
    // Janelas de tempo em segundos
    private const WINDOW_EMAIL_SECONDS   = 600;  // 10 min
    private const WINDOW_IP_SECONDS      = 900;  // 15 min

    // Limites de tentativas
    private const MAX_ATTEMPTS_EMAIL     = 5;
    private const MAX_ATTEMPTS_IP        = 20;

    // Durações de bloqueio em segundos
    private const BLOCK_EMAIL_SECONDS    = 900;  // 15 min
    private const BLOCK_IP_SECONDS       = 1800; // 30 min

    public function __construct(private readonly PDO $pdo) {}

    /**
     * Verifica se o par email+IP está bloqueado.
     * Consulta única com UNION para minimizar queries ao banco.
     */
    public function isBlocked(string $email, string $ip): bool { /* ... */ }

    /**
     * Registra uma tentativa de login.
     * Se success=false e limite atingido, bloqueia o usuário em users.locked_until.
     */
    public function recordAttempt(string $email, string $ip, bool $success): void { /* ... */ }

    /** Reseta contadores do usuário após login bem-sucedido */
    public function clearAttemptsForEmail(string $email): void { /* ... */ }

    /** Retorna quantos segundos restam para desbloquear (para exibir no frontend) */
    public function getRemainingBlockSeconds(string $email, string $ip): int { /* ... */ }

    /** IPs internos são isentos de bloqueio por IP */
    private function isWhitelistedIp(string $ip): bool { /* ... */ }
}
