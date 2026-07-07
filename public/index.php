<?php
/**
 * CONECTA360 — Front Controller
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

// ── Sessão segura ─────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.name', 'c360sess');
    ini_set('session.gc_maxlifetime', (string)env('SESSION_LIFETIME', '7200'));
    session_start();
}

// ── Roteamento simples ────────────────────────────────────────────────────────
$uri    = strtok($_SERVER['REQUEST_URI'], '?');
$uri    = '/' . trim(parse_url($uri, PHP_URL_PATH), '/');
$method = strtoupper($_SERVER['REQUEST_METHOD']);

// Remove prefixo de subpasta apenas em ambiente local (ex: /conecta360/public)
// Em produção com domínio próprio o SCRIPT_NAME será /public/index.php ou /index.php
// e o REQUEST_URI nunca começa com /public
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($basePath !== '' && $basePath !== '/public' && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath));
}
$uri = $uri === '' ? '/' : $uri;

require_once dirname(__DIR__) . '/routes/web.php';
