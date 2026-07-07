<?php
/**
 * Bootstrap: Variáveis de Ambiente
 *
 * Lê o arquivo .env da raiz do projeto e popula $_ENV / getenv().
 * Em cPanel, pode-se usar também o painel de variáveis de ambiente.
 *
 * Responsabilidades:
 *   - Localizar .env na raiz do projeto
 *   - Parsear linhas no formato CHAVE=VALOR
 *   - Ignorar linhas em branco e comentários (#)
 *   - NÃO sobrescrever variáveis já definidas pelo servidor (Apache SetEnv)
 *   - Lançar RuntimeException se .env não existir em ambiente de produção
 *
 * Formato do .env:
 *   APP_ENV=production
 *   APP_KEY=base64:...
 *   MASTER_DB_HOST=186.209.113.107
 *   MASTER_DB_USER=dema5738_conecta360
 *   MASTER_DB_PASS=...
 *   MASTER_DB_NAME=dema5738_conecta360
 *   SESSION_LIFETIME=7200
 *   BCRYPT_COST=12
 *   REMEMBER_ME_DAYS=30
 *   TOKEN_EXPIRY_MINUTES=60
 *   MAIL_HOST=...
 *   MAIL_PORT=587
 */

declare(strict_types=1);

$envFile = dirname(__DIR__) . '/.env';

if (!file_exists($envFile)) {
    // Em desenvolvimento aceita ausência; em produção falha imediatamente
    if (getenv('APP_ENV') === 'production') {
        throw new RuntimeException('.env não encontrado. Configure as variáveis de ambiente no servidor.');
    }
    return;
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    $line = trim($line);

    // Ignora comentários
    if (str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $key   = trim($key);
    $value = trim($value, " \t\n\r\0\x0B\"'"); // Remove aspas opcionais

    // Não sobrescreve variáveis já definidas pelo ambiente do servidor
    if (getenv($key) === false) {
        putenv("{$key}={$value}");
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
    }
}
