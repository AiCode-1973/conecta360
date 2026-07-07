<?php
declare(strict_types=1);
$pdo = new PDO(
    'mysql:host=186.209.113.107;port=3306;dbname=dema5738_conecta360;charset=utf8mb4',
    'dema5738_conecta360', 'Dema@1973',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$tables = ['board_column_options','board_views','board_filters','item_activity_logs','user_group_state','workspaces','groups','items','board_columns'];
foreach ($tables as $t) {
    try {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        echo "{$t}: {$n} rows\n";
    } catch (Exception $e) {
        echo "{$t}: ERRO - " . $e->getMessage() . "\n";
    }
}
