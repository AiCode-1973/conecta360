<?php
declare(strict_types=1);
$p = new PDO(
    'mysql:host=186.209.113.107;port=3306;dbname=dema5738_conecta360;charset=utf8mb4',
    'dema5738_conecta360', 'Dema@1973',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// Colunas da tabela boards
$cols = $p->query('DESCRIBE boards')->fetchAll();
echo "=== COLUMNS boards ===" . PHP_EOL;
foreach ($cols as $c) {
    echo "  {$c['Field']} | {$c['Type']} | Null:{$c['Null']} | Default:{$c['Default']}" . PHP_EOL;
}

// Testa insert simples
echo PHP_EOL . "=== TEST INSERT ===" . PHP_EOL;
try {
    $p->beginTransaction();
    $stmt = $p->prepare(
        "INSERT INTO boards (workspace_id, name, description, visibility, color, icon, created_by, order_index)
         VALUES (1, 'TESTE', '', 'public', '#0073ea', '📋', 1, 1)"
    );
    $stmt->execute();
    $id = $p->lastInsertId();
    $p->rollBack();
    echo "INSERT OK — id seria: {$id}" . PHP_EOL;
} catch (Exception $e) {
    $p->rollBack();
    echo "INSERT ERRO: " . $e->getMessage() . PHP_EOL;
}
