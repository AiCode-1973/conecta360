<?php
/**
 * Bootstrap: Inicialização da Aplicação
 *
 * Orquestrador central — executado a cada requisição HTTP.
 *
 * FLUXO DE EXECUÇÃO (em ordem estrita):
 * ─────────────────────────────────────
 *  1. Inicializa a sessão PHP de forma segura (ver SessionService)
 *  2. Extrai e sanitiza o subdomínio do HTTP_HOST (ver SubdomainHelper)
 *  3. Consulta o banco MASTER via PDO buscando o tenant pelo subdomínio
 *  4. Valida o status do tenant:
 *       - 'pending'   → HTTP 503 + página de ativação pendente
 *       - 'suspended' → HTTP 402 + página de inadimplência
 *       - 'cancelled' → HTTP 410 + página de encerramento
 *       - 'active'    → continua o fluxo
 *  5. Armazena o objeto Tenant no container da aplicação (singleton)
 *  6. Abre a conexão PDO com o banco do tenant (TenantConnection)
 *  7. Disponibiliza a conexão globalmente via App::db()
 *  8. Retorna o Router configurado com as rotas do tenant
 *
 * SEGURANÇA:
 *   - Subdomínio é validado por regex antes de qualquer consulta
 *   - Tenant ID jamais vem do front-end — sempre do subdomínio resolvido
 *   - Conexão master é fechada após resolver o tenant (princípio do menor privilégio)
 *   - Em caso de erro, resposta genérica (não revelar detalhes de infra)
 *
 * @return \Conecta360\Core\Router
 */

declare(strict_types=1);

use Conecta360\Core\Application;
use Conecta360\Core\Router;
use Conecta360\Database\MasterConnection;
use Conecta360\Database\TenantConnection;
use Conecta360\Helpers\SubdomainHelper;
use Conecta360\Services\TenantResolverService;
use Conecta360\Services\SessionService;
use Conecta360\Exceptions\TenantNotFoundException;
use Conecta360\Exceptions\TenantSuspendedException;

// ── 1. Sessão segura ──────────────────────────────────────────────────────────
SessionService::start();

// ── 2. Resolve subdomínio ─────────────────────────────────────────────────────
$subdomain = SubdomainHelper::extract($_SERVER['HTTP_HOST'] ?? '');

if ($subdomain === null || $subdomain === 'www') {
    // Acesso direto ao domínio raiz → landing page ou redirecionamento
    // (sem banco de tenant)
    $app = Application::getInstance();
    $app->setLandingMode(true);
    return require __DIR__ . '/../routes/landing.php';
}

// ── 3 e 4. Consulta master e valida tenant ────────────────────────────────────
try {
    $masterPdo = MasterConnection::getInstance();
    $resolver  = new TenantResolverService($masterPdo);
    $tenant    = $resolver->resolveBySubdomain($subdomain);
} catch (TenantNotFoundException $e) {
    http_response_code(404);
    require __DIR__ . '/../views/errors/404_tenant.php';
    exit;
} catch (TenantSuspendedException $e) {
    http_response_code(402);
    require __DIR__ . '/../views/errors/402_suspended.php';
    exit;
} catch (\Throwable $e) {
    // Log interno — nunca expor detalhes ao usuário
    error_log('[bootstrap] Erro ao resolver tenant: ' . $e->getMessage());
    http_response_code(500);
    require __DIR__ . '/../views/errors/500.php';
    exit;
}

// ── 5. Container da aplicação ─────────────────────────────────────────────────
$app = Application::getInstance();
$app->setTenant($tenant);

// ── 6. Abre conexão com banco do tenant ───────────────────────────────────────
$tenantPdo = TenantConnection::open($tenant);
$app->setDb($tenantPdo);

// ── 7. Fecha conexão master (menor privilégio) ────────────────────────────────
MasterConnection::close();

// ── 8. Retorna router configurado ────────────────────────────────────────────
return require __DIR__ . '/../routes/web.php';
