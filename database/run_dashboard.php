<?php
/**
 * Executa dashboard_schema.sql no banco remoto
 */
declare(strict_types=1);

$pdo = new PDO(
    'mysql:host=186.209.113.107;dbname=dema5738_conecta360;charset=utf8mb4;port=3306',
    'dema5738_conecta360',
    'Dema@1973',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

$sql    = file_get_contents(__DIR__ . '/dashboard_schema.sql');
$buffer = '';
$ok     = 0;
$err    = 0;

foreach (explode("\n", $sql) as $line) {
    $t = trim($line);
    if (str_starts_with($t, '--') || $t === '') continue;
    $buffer .= $line . "\n";
    if (str_ends_with(rtrim($t), ';')) {
        $stmt = trim($buffer);
        if (!empty($stmt)) {
            try {
                $pdo->exec($stmt);
                echo "  OK: " . mb_substr(preg_replace('/\s+/', ' ', $stmt), 0, 90) . PHP_EOL;
                $ok++;
            } catch (Exception $e) {
                echo "  ERRO: " . $e->getMessage() . PHP_EOL;
                $err++;
            }
        }
        $buffer = '';
    }
}

echo PHP_EOL . "Resultado: {$ok} OK | {$err} erro(s)" . PHP_EOL;
