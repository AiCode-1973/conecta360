<?php
/**
 * Bootstrap: Autoloader PSR-4 manual
 *
 * Mapeia namespaces para diretórios sem depender do Composer.
 * Convenção: Conecta360\Auth\AuthService → src/Auth/AuthService.php
 *
 * Responsabilidades:
 *   - Registrar spl_autoload para o namespace raiz "Conecta360"
 *   - Resolver o caminho a partir do namespace
 *   - Lançar RuntimeException se o arquivo não existir
 */

declare(strict_types=1);

spl_autoload_register(static function (string $className): void {
    // Namespace raiz do projeto
    $prefix   = 'Conecta360\\';
    $baseDir  = dirname(__DIR__) . '/src/';

    if (!str_starts_with($className, $prefix)) {
        return;
    }

    $relativeClass = substr($className, strlen($prefix));
    $filePath      = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (!file_exists($filePath)) {
        throw new RuntimeException("Autoload: classe não encontrada em {$filePath}");
    }

    require $filePath;
});
