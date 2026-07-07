<?php
/**
 * Application — Container e Kernel da Aplicação
 *
 * Singleton que centraliza:
 *   - Instância do tenant ativo
 *   - Conexão PDO com o banco do tenant
 *   - Modo da aplicação (tenant | landing)
 *   - Acesso global thread-safe aos serviços principais
 *
 * Responsabilidades:
 *   - getInstance(): retorna o singleton
 *   - setTenant(Tenant $tenant): armazena o tenant resolvido
 *   - getTenant(): retorna o tenant ativo
 *   - setDb(PDO $pdo): armazena a conexão tenant
 *   - db(): retorna a conexão PDO do tenant
 *   - setLandingMode(bool): modo sem tenant (landing page)
 *   - isLandingMode(): verifica se está em landing mode
 *
 * @package Conecta360\Core
 */

declare(strict_types=1);

namespace Conecta360\Core;

use Conecta360\Models\Tenant;
use PDO;

final class Application
{
    private static ?self $instance = null;
    private ?Tenant $tenant        = null;
    private ?PDO    $db            = null;
    private bool    $landingMode   = false;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): self { /* ... */ }
    public function setTenant(Tenant $tenant): void { /* ... */ }
    public function getTenant(): Tenant { /* ... */ }
    public function setDb(PDO $pdo): void { /* ... */ }
    public function db(): PDO { /* ... */ }
    public function setLandingMode(bool $value): void { /* ... */ }
    public function isLandingMode(): bool { /* ... */ }
}
