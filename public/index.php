<?php
/**
 * CONECTA360 — Front Controller
 *
 * ÚNICO ponto de entrada HTTP da aplicação.
 * Todo request passa por aqui — sem exceção.
 *
 * Fluxo de execução (ordem obrigatória):
 *   1. Hardening básico de headers e configurações PHP
 *   2. Carrega autoloader e variáveis de ambiente
 *   3. Bootstrap do tenant (subdomínio → banco correto)
 *   4. Inicialização segura da sessão
 *   5. Pipeline de middlewares globais (CSRF, Auth, Rate Limit)
 *   6. Roteamento da URL para o Controller::action correto
 *   7. Renderização da resposta
 *
 * @package Conecta360
 */

declare(strict_types=1);

// ── 0. Hardening de runtime ──────────────────────────────────────────────────
// Nunca expor erros em produção — configurar via .env
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Remove header X-Powered-By (backup ao .htaccess)
header_remove('X-Powered-By');

// ── 1. Autoloader PSR-4 manual (sem Composer por ora) ────────────────────────
require_once __DIR__ . '/../bootstrap/autoload.php';

// ── 2. Variáveis de ambiente ──────────────────────────────────────────────────
require_once __DIR__ . '/../bootstrap/env.php';

// ── 3. Inicialização da Aplicação ─────────────────────────────────────────────
// Resolve subdomínio → tenant → abre conexão PDO com banco do tenant
// Lança TenantNotFoundException se subdomínio não existir
// Lança TenantSuspendedException se tenant estiver inativo
require_once __DIR__ . '/../bootstrap/app.php';

// ── 4. Roteamento ─────────────────────────────────────────────────────────────
$router = require_once __DIR__ . '/../routes/web.php';
$router->dispatch();
