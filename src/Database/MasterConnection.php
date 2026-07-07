<?php
/**
 * MasterConnection — PDO para o banco MASTER
 *
 * Singleton com abertura lazy (só conecta quando necessário).
 *
 * Responsabilidades:
 *   - getInstance(): abre (ou retorna) a conexão PDO com o banco master
 *   - close(): fecha a conexão após resolver o tenant (menor privilégio)
 *   - Configurações de segurança PDO obrigatórias:
 *       • PDO::ATTR_ERRMODE            → ERRMODE_EXCEPTION
 *       • PDO::ATTR_DEFAULT_FETCH_MODE → FETCH_ASSOC
 *       • PDO::ATTR_EMULATE_PREPARES   → false (prepared statements reais)
 *       • charset=utf8mb4 no DSN
 *
 * SEGURANÇA:
 *   - Credenciais lidas de variáveis de ambiente (nunca hardcoded)
 *   - ATTR_EMULATE_PREPARES=false: MySQL recebe queries e parâmetros separados
 *     (mitiga segunda ordem SQL injection)
 *
 * @package Conecta360\Database
 */

declare(strict_types=1);

namespace Conecta360\Database;

use PDO;
use PDOException;

final class MasterConnection
{
    private static ?PDO $instance = null;

    private function __construct() {}

    /** Abre conexão lazy com o banco master lendo credenciais do .env */
    public static function getInstance(): PDO { /* ... */ }

    /** Fecha e nulifica a conexão (chame após resolver o tenant) */
    public static function close(): void { /* ... */ }

    /** Monta DSN com charset obrigatório utf8mb4 */
    private static function buildDsn(): string { /* ... */ }
}
