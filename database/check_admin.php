<?php
declare(strict_types=1);
$pdo = new PDO(
    'mysql:host=186.209.113.107;port=3306;dbname=dema5738_conecta360;charset=utf8mb4',
    'dema5738_conecta360', 'Dema@1973',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// 1. Tabela users existe?
$tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
echo "Tabela users: " . (count($tables) ? "EXISTE" : "NAO EXISTE") . PHP_EOL;

if (!count($tables)) exit;

// 2. Usuário admin existe?
$stmt = $pdo->query("SELECT id, name, email, password, status, deleted_at FROM users WHERE email='admin@conecta360.com.br' LIMIT 1");
$user = $stmt->fetch();

if (!$user) {
    echo "Usuario admin: NAO ENCONTRADO" . PHP_EOL;

    // Mostra todos os usuários
    $all = $pdo->query("SELECT id, email, status FROM users LIMIT 10")->fetchAll();
    echo "Usuarios na tabela: " . count($all) . PHP_EOL;
    foreach ($all as $u) {
        echo "  id={$u['id']} email={$u['email']} status={$u['status']}" . PHP_EOL;
    }
    exit;
}

echo "Usuario encontrado:" . PHP_EOL;
echo "  id={$user['id']}" . PHP_EOL;
echo "  status={$user['status']}" . PHP_EOL;
echo "  deleted_at=" . ($user['deleted_at'] ?? 'NULL') . PHP_EOL;
echo "  hash=" . $user['password'] . PHP_EOL;

// 3. Verifica password_verify
$ok = password_verify('Admin@2026', $user['password']);
echo "password_verify('Admin@2026'): " . ($ok ? "OK - CORRETO" : "FALHOU") . PHP_EOL;

// 4. Verifica info do hash
$info = password_get_info($user['password']);
echo "Algoritmo do hash: " . ($info['algoName'] ?? 'desconhecido') . PHP_EOL;
