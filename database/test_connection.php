<?php
require 'config/app.php';
try {
    $pdo = pdo_master();
    $r = $pdo->query('SELECT id, name, email, status FROM users LIMIT 3');
    foreach ($r->fetchAll() as $u) {
        echo $u['id'] . ' | ' . $u['name'] . ' | ' . $u['email'] . ' | ' . $u['status'] . PHP_EOL;
    }
    echo 'Conexao OK' . PHP_EOL;
} catch (Exception $e) {
    echo 'ERRO: ' . $e->getMessage() . PHP_EOL;
}
