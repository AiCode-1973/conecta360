<?php
/**
 * SubdomainHelper — Extração e Validação do Subdomínio
 *
 * LÓGICA DE EXTRAÇÃO:
 * ──────────────────────────────────────────────────────────
 *  HTTP_HOST: "hcor.conecta360.com.br"
 *
 *  1. Obtém o domínio base configurado no .env: APP_DOMAIN=conecta360.com.br
 *  2. Remove o domínio base do HTTP_HOST
 *  3. Remove o ponto separador: "hcor."  → "hcor"
 *  4. Valida o resultado com regex: ^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$
 *  5. Retorna o subdomínio em lowercase ou NULL se inválido
 *
 * CASOS ESPECIAIS:
 *   - HTTP_HOST = "conecta360.com.br"     → retorna null (raiz = landing page)
 *   - HTTP_HOST = "www.conecta360.com.br" → retorna "www" (redirecionado na landing)
 *   - HTTP_HOST = "admin.conecta360.com.br" → reservado, nunca resolve tenant
 *   - HTTP_HOST inclui porta (ex: :8080)  → remove a porta antes de processar
 *
 * SUBDOMÍNIOS RESERVADOS (nunca resolvem tenant):
 *   www, admin, api, mail, ftp, cpanel, webmail, whm, ns1, ns2
 *
 * SEGURANÇA:
 *   - Nunca confiar no HTTP_HOST sem validação — pode ser forjado
 *   - Regex estrita: apenas [a-z0-9-], sem caracteres especiais
 *   - Comprimento máximo: 63 caracteres (RFC 1035)
 *   - Rejeitar subdomínios com hifens consecutivos (--) ou hifens nas extremidades
 *
 * @package Conecta360\Helpers
 */

declare(strict_types=1);

namespace Conecta360\Helpers;

final class SubdomainHelper
{
    /** Subdomínios reservados que nunca devem resolver para um tenant */
    private const RESERVED = ['www', 'admin', 'api', 'mail', 'ftp', 'cpanel', 'webmail', 'whm', 'ns1', 'ns2', 'smtp', 'pop', 'imap'];

    /**
     * Extrai e valida o subdomínio do HTTP_HOST.
     *
     * @param string $httpHost  Valor de $_SERVER['HTTP_HOST']
     * @return string|null      Subdomínio válido ou null
     */
    public static function extract(string $httpHost): ?string { /* ... */ }

    /** Verifica se o subdomínio é reservado pelo sistema */
    public static function isReserved(string $subdomain): bool { /* ... */ }

    /** Valida o subdomínio contra as regras RFC 1035 + restrições do sistema */
    public static function isValid(string $subdomain): bool { /* ... */ }
}
