<?php
$pdo = new PDO(
    'mysql:host=186.209.113.107;dbname=dema5738_conecta360;charset=utf8mb4;port=3306',
    'dema5738_conecta360',
    'Dema@1973',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$sql   = file_get_contents(__DIR__ . '/auth_tables.sql');
$lines = explode("\n", $sql);

$buffer = '';
$ok     = 0;
$err    = 0;

foreach ($lines as $line) {
    $trimmed = trim($line);
    if (str_starts_with($trimmed, '--') || $trimmed === '') continue;
    $buffer .= $line . "\n";
    if (str_ends_with(rtrim($trimmed), ';')) {
        $stmt = trim($buffer);
        if (!empty($stmt)) {
            try {
                $pdo->exec($stmt);
                echo "  OK: " . mb_substr(preg_replace('/\s+/', ' ', $stmt), 0, 80) . PHP_EOL;
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
