<?php
declare(strict_types=1);
$pdo = new PDO(
    'mysql:host=186.209.113.107;port=3306;dbname=dema5738_conecta360;charset=utf8mb4',
    'dema5738_conecta360', 'Dema@1973',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$sql = file_get_contents(__DIR__ . '/board_module_migration.sql');

// Remove linhas de comentário e linhas em branco, depois junta e divide por ;
$lines = explode("\n", $sql);
$cleaned = [];
foreach ($lines as $line) {
    $trimmed = ltrim($line);
    if (str_starts_with($trimmed, '--') || $trimmed === '') continue;
    $cleaned[] = $line;
}
$cleanSql = implode("\n", $cleaned);
$stmts    = array_filter(array_map('trim', explode(';', $cleanSql)));

$ok   = 0;
$errs = [];
foreach ($stmts as $s) {
    if (empty($s)) continue;
    try {
        $pdo->exec($s);
        $ok++;
    } catch (Exception $e) {
        $errs[] = substr($e->getMessage(), 0, 140);
    }
}

echo "OK: {$ok} | Erros: " . count($errs) . PHP_EOL;
foreach ($errs as $err) {
    echo "  - {$err}" . PHP_EOL;
}
