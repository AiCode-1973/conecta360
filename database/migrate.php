<?php
/**
 * Conecta360 — Script de Migração do Banco de Dados
 * Executa os schemas master e tenant no servidor remoto
 *
 * USO: php database/migrate.php [master|tenant|all] [nome_banco_tenant]
 *
 * Exemplos:
 *   php database/migrate.php master
 *   php database/migrate.php tenant dema5738_hospital_exemplo
 *   php database/migrate.php all dema5738_hospital_exemplo
 */

declare(strict_types=1);

// ── Configurações de conexão ──────────────────────────────────────────────────
define('DB_HOST', '186.209.113.107');
define('DB_USER', 'dema5738_conecta360');
define('DB_PASS', 'Dema@1973');
define('DB_MASTER', 'dema5738_conecta360');
define('DB_PORT', 3306);

// ── Utilitários de output ─────────────────────────────────────────────────────
function out(string $msg, string $type = 'info'): void
{
    $colors = [
        'info'    => "\033[36m",   // Cyan
        'success' => "\033[32m",   // Verde
        'error'   => "\033[31m",   // Vermelho
        'warn'    => "\033[33m",   // Amarelo
        'title'   => "\033[35;1m", // Magenta bold
        'reset'   => "\033[0m",
    ];

    $isCli = (PHP_SAPI === 'cli');

    if ($isCli) {
        $color = $colors[$type] ?? $colors['info'];
        echo $color . $msg . $colors['reset'] . PHP_EOL;
    } else {
        $htmlMap = [
            'title'   => '<h3 style="color:#9c27b0">',
            'success' => '<p style="color:#2e7d32">✔ ',
            'error'   => '<p style="color:#c62828">✖ ',
            'warn'    => '<p style="color:#e65100">⚠ ',
            'info'    => '<p style="color:#0277bd">ℹ ',
        ];
        $close  = isset($htmlMap[$type]) ? (strpos($htmlMap[$type], '<h') !== false ? '</h3>' : '</p>') : '</p>';
        echo ($htmlMap[$type] ?? '<p>') . htmlspecialchars($msg) . $close . PHP_EOL;
    }
}

// ── Parser de SQL (ignora comentários e queries vazias) ───────────────────────
function parseSqlFile(string $filePath): array
{
    if (!file_exists($filePath)) {
        throw new RuntimeException("Arquivo SQL não encontrado: {$filePath}");
    }

    $content    = file_get_contents($filePath);
    $statements = [];
    $buffer     = '';
    $lines      = explode("\n", $content);

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Ignora linhas de comentário puro
        if (str_starts_with($trimmed, '--') || $trimmed === '') {
            continue;
        }

        $buffer .= $line . "\n";

        // Detecta fim de statement
        if (str_ends_with(rtrim($trimmed), ';')) {
            $stmt = trim($buffer);
            if (!empty($stmt)) {
                $statements[] = $stmt;
            }
            $buffer = '';
        }
    }

    // Captura statement sem `;` final
    if (!empty(trim($buffer))) {
        $statements[] = trim($buffer);
    }

    return $statements;
}

// ── Executa um arquivo SQL em uma conexão ─────────────────────────────────────
function runSqlFile(mysqli $conn, string $filePath, string $label): array
{
    out("", 'info');
    out("═══════════════════════════════════════════════════", 'title');
    out(" Executando: {$label}", 'title');
    out("═══════════════════════════════════════════════════", 'title');

    $statements = parseSqlFile($filePath);
    $ok         = 0;
    $errs       = [];

    foreach ($statements as $i => $sql) {
        $preview = mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 80);

        if ($conn->query($sql) === true) {
            $ok++;
            out("  ✔ [{$ok}] " . $preview, 'success');
        } else {
            $errs[] = ['sql' => $preview, 'error' => $conn->error];
            out("  ✖ ERRO: {$conn->error}", 'error');
            out("    SQL: {$preview}", 'warn');
        }
    }

    out("", 'info');
    out("  Resultado: {$ok} OK | " . count($errs) . " erro(s)", count($errs) === 0 ? 'success' : 'warn');

    return $errs;
}

// ── Conecta ao MySQL ──────────────────────────────────────────────────────────
function connect(string $database): mysqli
{
    mysqli_report(MYSQLI_REPORT_OFF);

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, $database, DB_PORT);

    if ($conn->connect_errno) {
        throw new RuntimeException("Falha na conexão com '{$database}': " . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '-03:00'");

    return $conn;
}

// ── Cria banco do tenant (se não existir) ─────────────────────────────────────
function createTenantDatabase(mysqli $masterConn, string $dbName): bool
{
    // Valida o nome do banco contra injeção SQL (somente alphanum e underscore)
    if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $dbName)) {
        throw new InvalidArgumentException("Nome de banco inválido: {$dbName}");
    }

    $sql    = "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    $result = $masterConn->query($sql);

    if ($result === false) {
        out("  Não foi possível criar o banco '{$dbName}': " . $masterConn->error, 'warn');
        out("  → Em hospedagem compartilhada, crie o banco manualmente no cPanel.", 'warn');
        return false;
    }

    out("  Banco '{$dbName}' criado (ou já existia).", 'success');
    return true;
}

// ═══════════════════════════════════════════════════════════════════════════════
// MAIN
// ═══════════════════════════════════════════════════════════════════════════════
$isCli  = (PHP_SAPI === 'cli');
$action = $isCli ? ($argv[1] ?? 'all') : ($_GET['action'] ?? 'all');
$tenant = $isCli ? ($argv[2] ?? null)  : ($_GET['tenant'] ?? null);

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8">';
    echo '<title>Conecta360 — Migração</title>';
    echo '<style>body{font-family:monospace;background:#1e1e1e;color:#ccc;padding:2rem}h3{margin:0.5rem 0}</style>';
    echo '</head><body>';
}

out('', 'info');
out('╔══════════════════════════════════════════════════╗', 'title');
out('║   CONECTA360 — MIGRAÇÃO DO BANCO DE DADOS        ║', 'title');
out('║   Host: ' . DB_HOST . '                   ║', 'title');
out('╚══════════════════════════════════════════════════╝', 'title');
out('', 'info');

$baseDir     = __DIR__;
$masterSql   = $baseDir . '/master_schema.sql';
$tenantSql   = $baseDir . '/tenant_schema.sql';
$allErrors   = [];

// ── Migração MASTER ───────────────────────────────────────────────────────────
if (in_array($action, ['master', 'all'], true)) {
    out("Conectando ao banco master: " . DB_MASTER, 'info');

    try {
        $masterConn = connect(DB_MASTER);
        out("  Conexão OK.", 'success');

        $errs       = runSqlFile($masterConn, $masterSql, 'BANCO MASTER (' . DB_MASTER . ')');
        $allErrors  = array_merge($allErrors, $errs);

        $masterConn->close();
    } catch (RuntimeException $e) {
        out("FALHA: " . $e->getMessage(), 'error');
        exit(1);
    }
}

// ── Migração TENANT ───────────────────────────────────────────────────────────
if (in_array($action, ['tenant', 'all'], true)) {
    $tenantDb = $tenant ?? 'dema5738_tenant_hospital_exemplo';

    out("", 'info');
    out("Iniciando migração do tenant: {$tenantDb}", 'info');

    try {
        // Primeiro tenta criar o banco via master
        $masterConn = connect(DB_MASTER);
        createTenantDatabase($masterConn, $tenantDb);
        $masterConn->close();

        // Conecta ao banco do tenant e executa o schema
        $tenantConn = connect($tenantDb);
        out("  Conexão com tenant OK.", 'success');

        $errs      = runSqlFile($tenantConn, $tenantSql, "BANCO TENANT ({$tenantDb})");
        $allErrors = array_merge($allErrors, $errs);

        $tenantConn->close();
    } catch (RuntimeException $e) {
        out("FALHA na migração do tenant: " . $e->getMessage(), 'error');
        out("Verifique se o banco '{$tenantDb}' existe e o usuário tem permissão.", 'warn');
    }
}

// ── Relatório Final ───────────────────────────────────────────────────────────
out('', 'info');
out('═══════════════════════════════════════════════════', 'title');
out(' RELATÓRIO FINAL', 'title');
out('═══════════════════════════════════════════════════', 'title');

if (empty($allErrors)) {
    out('  ✔ Migração concluída com SUCESSO — nenhum erro encontrado!', 'success');
} else {
    out("  ✖ " . count($allErrors) . " erro(s) encontrado(s):", 'error');
    foreach ($allErrors as $err) {
        out("    • {$err['error']}", 'error');
        out("      SQL: {$err['sql']}", 'warn');
    }
}

out('', 'info');

if (!$isCli) {
    echo '</body></html>';
}
