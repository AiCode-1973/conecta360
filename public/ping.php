<?php
// Arquivo de diagnóstico — remova após confirmar funcionamento
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
    // Aceita apenas localmente em produção — comente para testar
    // http_response_code(403); exit;
}
echo json_encode([
    'status'      => 'ok',
    'php'         => PHP_VERSION,
    'server_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'n/a',
    'script'      => $_SERVER['SCRIPT_FILENAME'] ?? 'n/a',
    'uri'         => $_SERVER['REQUEST_URI'] ?? 'n/a',
    'https'       => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'on' : 'off',
], JSON_PRETTY_PRINT);
