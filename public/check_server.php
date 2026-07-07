<?php
/**
 * Diagnóstico rápido do servidor — DELETE APÓS USO
 * Acesse: https://conecta360.aicode.dev.br/check_server.php?token=c360check
 */
if (($_GET['token'] ?? '') !== 'c360check') { http_response_code(403); die('Proibido.'); }

$base = dirname(__DIR__);

$files = [
    'routes/web.php'                              => 'Rotas principais',
    'config/app.php'                              => 'Bootstrap',
    'src/Modules/Board/BoardRepository.php'       => 'Board Repository',
    'src/Modules/Board/GroupRepository.php'       => 'Group Repository',
    'src/Modules/Board/ItemRepository.php'        => 'Item Repository ← provável causa do 500',
    'views/boards/show.php'                       => 'View: board detalhes',
    'views/boards/create.php'                     => 'View: criar board',
    'views/boards/index.php'                      => 'View: listar boards',
    'views/users/index.php'                       => 'View: usuários',
    'views/dashboard/index.php'                   => 'View: dashboard',
    '.env'                                        => 'Configurações (.env)',
];

echo '<pre style="background:#111;color:#eee;padding:2rem;font-family:monospace;line-height:2">';
echo "<b style='color:#fdab3d'>=== ARQUIVOS NO SERVIDOR ===</b>\n\n";
foreach ($files as $rel => $label) {
    $path = $base . '/' . $rel;
    if (file_exists($path)) {
        $size = number_format(filesize($path));
        $ts   = date('d/m/Y H:i', filemtime($path));
        echo "<span style='color:#00c875'>✅ EXISTE</span>  {$rel} ({$size} bytes | {$ts}) — {$label}\n";
    } else {
        echo "<span style='color:#e2445c'>❌ FALTA  </span>  {$rel} — {$label}\n";
    }
}

// Verifica método ensureWorkspaceMember no BoardRepository
echo "\n<b style='color:#fdab3d'>=== VERSÃO DOS ARQUIVOS ===</b>\n\n";
$repoFile = $base . '/src/Modules/Board/BoardRepository.php';
if (file_exists($repoFile)) {
    $content = file_get_contents($repoFile);
    $hasEnsure  = str_contains($content, 'ensureWorkspaceMember');
    $hasFetch   = str_contains($content, 'fetchColumn');
    echo "BoardRepository → ensureWorkspaceMember: " . ($hasEnsure ? "<span style='color:#00c875'>SIM (novo)</span>" : "<span style='color:#e2445c'>NÃO (velho)</span>") . "\n";
    echo "BoardRepository → fetchColumn em create(): " . ($hasFetch ? "<span style='color:#00c875'>SIM (correto)</span>" : "<span style='color:#e2445c'>NÃO (bug PDO::execute)</span>") . "\n";
}

$routesFile = $base . '/routes/web.php';
if (file_exists($routesFile)) {
    $content = file_get_contents($routesFile);
    $hasTryCatch = str_contains($content, 'catch (Exception $e)');
    echo "routes/web.php → try-catch no create: " . ($hasTryCatch ? "<span style='color:#00c875'>SIM</span>" : "<span style='color:#e2445c'>NÃO (sem proteção)</span>") . "\n";
}

// Testa DB + tabelas
echo "\n<b style='color:#fdab3d'>=== BANCO DE DADOS ===</b>\n\n";
try {
    require_once $base . '/config/app.php';
    $pdo = pdo_master();
    echo "<span style='color:#00c875'>✅ Conexão OK</span>\n";

    $tables = ['boards','board_columns','board_members','groups','items','item_values','workspaces','workspace_members','users'];
    foreach ($tables as $t) {
        $exists = $pdo->query("SHOW TABLES LIKE '$t'")->rowCount() > 0;
        $count  = $exists ? (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn() : 0;
        echo ($exists ? "<span style='color:#00c875'>✅</span>" : "<span style='color:#e2445c'>❌</span>") . " $t" . ($exists ? " ($count rows)" : " — TABELA NÃO EXISTE") . "\n";
    }
} catch (Throwable $e) {
    echo "<span style='color:#e2445c'>❌ Erro DB: " . $e->getMessage() . "</span>\n";
}

echo "\n<b style='color:#fdab3d'>=== PHP ===</b>\n";
echo "Versão: " . PHP_VERSION . "\n";
echo "BASE_PATH: $base\n";
echo '</pre>';
echo '<p style="font-family:monospace;color:#e2445c;background:#111;padding:0 2rem 2rem"><b>⚠️ DELETE este arquivo após usar: public/check_server.php</b></p>';
