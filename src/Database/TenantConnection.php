<?php
/**
 * TenantConnection — PDO para o banco do tenant ativo
 *
 * Responsabilidades:
 *   - open(Tenant $tenant): abre a conexão com o banco do tenant
 *   - Mesmo usuário MySQL do master (em cPanel o usuário tem acesso a múltiplos bancos)
 *   - Mesmas configurações de segurança do MasterConnection
 *   - Verifica se o banco existe antes de conectar (evita erro genérico exposto)
 *
 * SEGURANÇA:
 *   - O nome do banco vem do objeto Tenant resolvido pelo subdomínio (servidor)
 *   - NUNCA aceita o nome do banco vindo do front-end, cookie ou parâmetro HTTP
 *   - O nome do banco é validado por regex [a-zA-Z0-9_] antes de montar o DSN
 *   - Em caso de falha, loga internamente e exibe erro genérico ao usuário
 *
 * Sobre cPanel / hospedagem compartilhada:
 *   - Em cPanel, o usuário MySQL precisa ter privilégios explícitos em cada banco tenant
 *   - Ao criar um novo tenant, o cPanel Wizard (ou API) deve:
 *       1. Criar o banco (ex: dema5738_hospital_abc)
 *       2. Adicionar o usuário ao banco com ALL PRIVILEGES
 *       3. Executar tenant_schema.sql no novo banco
 *
 * @package Conecta360\Database
 */

declare(strict_types=1);

namespace Conecta360\Database;

use Conecta360\Models\Tenant;
use PDO;

final class TenantConnection
{
    private function __construct() {}

    /**
     * Abre conexão PDO com o banco do tenant.
     *
     * @param Tenant $tenant  Objeto tenant com `database_name` validado
     * @return PDO            Conexão pronta para uso
     * @throws \RuntimeException se o banco não existir ou conexão falhar
     */
    public static function open(Tenant $tenant): PDO { /* ... */ }

    /** Valida o nome do banco contra regex segura */
    private static function validateDatabaseName(string $name): bool { /* ... */ }
}
