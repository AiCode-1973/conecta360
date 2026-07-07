<?php
/**
 * TenantResolverService — Resolve Tenant pelo subdomínio
 *
 * FLUXO DETALHADO:
 * ─────────────────────────────────────────────────────────
 *  1. Recebe o subdomínio sanitizado (ex: "hcor")
 *  2. Consulta banco MASTER via prepared statement:
 *       SELECT id, name, subdomain, database_name, status, primary_color, timezone
 *       FROM tenants
 *       WHERE subdomain = :subdomain AND deleted_at IS NULL
 *       LIMIT 1
 *  3. Se não encontrar → lança TenantNotFoundException
 *  4. Verifica status:
 *       - 'active'    → retorna objeto Tenant
 *       - 'pending'   → lança TenantPendingException (HTTP 503)
 *       - 'suspended' → lança TenantSuspendedException (HTTP 402)
 *       - 'cancelled' → lança TenantCancelledException (HTTP 410)
 *  5. Verifica plano ativo via tenant_plan (status active/trial)
 *     → se plano vencido, trata como suspended
 *
 * CACHE:
 *   - Em produção, o resultado pode ser cacheado em APCu ou arquivo
 *     por até 5 minutos para evitar consulta ao master em todo request
 *   - Chave de cache: "tenant_subdomain_{subdomain}"
 *   - Invalidar cache ao atualizar status do tenant
 *
 * SEGURANÇA:
 *   - subdomínio validado por SubdomainHelper antes de chegar aqui
 *   - Prepared statement — nunca interpolar o subdomínio na query
 *   - Mensagem de erro ao usuário é genérica (não revelar estrutura do banco)
 *
 * @package Conecta360\Services
 */

declare(strict_types=1);

namespace Conecta360\Services;

use Conecta360\Models\Tenant;
use Conecta360\Exceptions\TenantNotFoundException;
use Conecta360\Exceptions\TenantSuspendedException;
use PDO;

final class TenantResolverService
{
    public function __construct(private readonly PDO $masterPdo) {}

    /** Resolve e retorna o tenant pelo subdomínio ou lança exceção */
    public function resolveBySubdomain(string $subdomain): Tenant { /* ... */ }

    /** Verifica se o plano ativo está dentro da validade */
    private function isPlanActive(int $tenantId): bool { /* ... */ }

    /** Busca tenant do cache (APCu ou arquivo) */
    private function fromCache(string $subdomain): ?Tenant { /* ... */ }

    /** Salva tenant no cache com TTL de 5 minutos */
    private function toCache(string $subdomain, Tenant $tenant): void { /* ... */ }
}
