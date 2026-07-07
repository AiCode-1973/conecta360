<?php
/**
 * Conecta360 — Configuração Central
 * Carregado pelo bootstrap antes de qualquer coisa
 */
declare(strict_types=1);

// ── Lê .env ──────────────────────────────────────────────────────────────────
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v, " \t\"'");
        if (getenv($k) === false) { putenv("{$k}={$v}"); $_ENV[$k] = $v; }
    }
}

// ── Helpers de config ─────────────────────────────────────────────────────────
function env(string $key, mixed $default = null): mixed {
    $val = getenv($key);
    return $val !== false ? $val : ($_ENV[$key] ?? $default);
}

// ── Constantes globais ────────────────────────────────────────────────────────
define('APP_VERSION', env('APP_VERSION', '1.0.0'));
define('APP_ENV',     env('APP_ENV', 'production'));
define('APP_DEBUG',   env('APP_DEBUG', 'false') === 'true');
define('BASE_PATH',   dirname(__DIR__));
define('BASE_URL',    env('APP_URL', ''));

// ── Erros ────────────────────────────────────────────────────────────────────
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

header_remove('X-Powered-By');

// ── PDO Master ───────────────────────────────────────────────────────────────
function pdo_master(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('MASTER_DB_HOST'), env('MASTER_DB_PORT', '3306'), env('MASTER_DB_NAME')
    );
    $pdo = new PDO($dsn, env('MASTER_DB_USER'), env('MASTER_DB_PASS'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

// ── Container simples ─────────────────────────────────────────────────────────
$GLOBALS['app'] = [];
function app_set(string $key, mixed $value): void { $GLOBALS['app'][$key] = $value; }
function app_get(string $key): mixed              { return $GLOBALS['app'][$key] ?? null; }
