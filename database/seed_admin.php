<?php
declare(strict_types=1);
$pdo = new PDO(
    'mysql:host=186.209.113.107;dbname=dema5738_conecta360;charset=utf8mb4;port=3306',
    'dema5738_conecta360', 'Dema@1973',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$hash = password_hash('Admin@2026', PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $pdo->prepare(
    'INSERT INTO users (name, email, password, status, email_verified_at, job_title, created_at, updated_at)
     VALUES (?, ?, ?, ?, NOW(), ?, NOW(), NOW())
     ON DUPLICATE KEY UPDATE password=VALUES(password), status=VALUES(status), email_verified_at=NOW()'
);
$stmt->execute(['Administrador', 'admin@conecta360.com.br', $hash, 'active', 'Administrador Master']);

echo 'Usuário criado/atualizado:' . PHP_EOL;
echo '  E-mail: admin@conecta360.com.br' . PHP_EOL;
echo '  Senha:  Admin@2026' . PHP_EOL;
echo '  Hash:   ' . $hash . PHP_EOL;
