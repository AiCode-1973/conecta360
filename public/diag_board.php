<?php
declare(strict_types=1);
// Diagnóstico de criação de board — remova após resolver
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/src/Modules/Board/BoardRepository.php';
require_once dirname(__DIR__) . '/src/Modules/Board/GroupRepository.php';

$pdo = pdo_master();

echo "<pre>";

// 1. Verifica workspaces
$ws = $pdo->query('SELECT * FROM workspaces LIMIT 5')->fetchAll();
echo "=== WORKSPACES ===\n";
foreach ($ws as $w) echo "  id={$w['id']} name={$w['name']}\n";

// 2. Verifica workspace_members
$wm = $pdo->query('SELECT * FROM workspace_members LIMIT 5')->fetchAll();
echo "\n=== WORKSPACE_MEMBERS ===\n";
echo count($wm) > 0 ? print_r($wm, true) : "  (vazia)\n";

// 3. Simula create() de board
echo "\n=== SIMULANDO BoardRepository::create() ===\n";
try {
    $repo = new BoardRepository($pdo);
    $pdo->beginTransaction();

    $boardId = $repo->create([
        'workspace_id' => 1,
        'name'         => 'TESTE DIAGNOSTICO',
        'description'  => '',
        'visibility'   => 'public',
        'color'        => '#0073ea',
        'icon'         => '📋',
        'created_by'   => 1,
    ]);
    echo "  create() OK → board_id={$boardId}\n";

    $repo->addMember($boardId, 1, 'owner');
    echo "  addMember() OK\n";

    $repo->createColumn(['board_id' => $boardId, 'name' => 'Status', 'type' => 'status',
        'settings' => json_encode(['options' => [['slug'=>'done','label'=>'Concluído','color'=>'#00c875']]])]);
    echo "  createColumn(status) OK\n";

    $repo->createColumn(['board_id' => $boardId, 'name' => 'Responsável', 'type' => 'person', 'settings' => '{}']);
    echo "  createColumn(person) OK\n";

    $repo->createColumn(['board_id' => $boardId, 'name' => 'Data Limite', 'type' => 'date', 'settings' => '{}']);
    echo "  createColumn(date) OK\n";

    $grpRepo = new GroupRepository($pdo);
    $grpId = $grpRepo->create($boardId, 'Principal');
    echo "  GroupRepository::create() OK → group_id={$grpId}\n";

    $pdo->rollBack();
    echo "\n✅ TUDO OK — nenhum erro no fluxo de criação\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . " linha " . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}

// 4. PHP version
echo "\n=== PHP ===\n";
echo "  versão: " . PHP_VERSION . "\n";
echo "  SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'n/a') . "\n";
echo "</pre>";
