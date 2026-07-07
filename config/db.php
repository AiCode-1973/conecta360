<?php

define('DB_HOST', '186.209.113.107');
define('DB_USER', 'dema5738_conecta360');
define('DB_PASS', 'Dema@1973');
define('DB_NAME', 'dema5738_conecta360');
define('DB_PORT', 3306);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    die('Erro na conexão com o banco de dados: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
