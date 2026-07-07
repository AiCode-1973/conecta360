<?php
/**
 * Router — Roteador HTTP
 *
 * Responsabilidades:
 *   - Registrar rotas: get(), post(), put(), patch(), delete()
 *   - Suportar grupos de rota com prefixo e middlewares compartilhados
 *   - Executar pipeline de middlewares antes do controller
 *   - Extrair parâmetros dinâmicos de URL: /items/{id}
 *   - Retornar 404 para rotas não encontradas
 *   - Retornar 405 para método HTTP incorreto
 *
 * Uso:
 *   $router->get('/login',  [AuthController::class, 'showLogin']);
 *   $router->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::class]);
 *
 *   $router->group('/dashboard', [AuthMiddleware::class], function($r) {
 *       $r->get('/',           [DashboardController::class, 'index']);
 *       $r->get('/boards/{id}',[BoardController::class, 'show']);
 *   });
 *
 * Pipeline de execução:
 *   Request → [Middleware1 → Middleware2 → ... → MiddlewareN] → Controller::action → Response
 *
 * @package Conecta360\Core
 */

declare(strict_types=1);

namespace Conecta360\Core;

final class Router
{
    /** @var array<string, array> Rotas registradas indexadas por método HTTP */
    private array $routes = [];

    /** @var array<string> Middlewares globais aplicados a todas as rotas */
    private array $globalMiddlewares = [];

    public function get(string $uri, array $handler, array $middlewares = []): void { /* ... */ }
    public function post(string $uri, array $handler, array $middlewares = []): void { /* ... */ }
    public function put(string $uri, array $handler, array $middlewares = []): void { /* ... */ }
    public function delete(string $uri, array $handler, array $middlewares = []): void { /* ... */ }

    /**
     * Agrupa rotas com prefixo URI e middlewares compartilhados.
     * Middlewares do grupo são ADICIONADOS aos da rota individual, não substituídos.
     */
    public function group(string $prefix, array $middlewares, callable $callback): void { /* ... */ }

    /** Adiciona middleware a todas as rotas (ex: TenantMiddleware, rate limiter) */
    public function addGlobalMiddleware(string $middlewareClass): void { /* ... */ }

    /**
     * Despacha a requisição atual.
     * Resolve URI, executa middlewares em cadeia, invoca controller.
     * Emite 404 ou 405 se necessário.
     */
    public function dispatch(): void { /* ... */ }

    /** Compara URI com padrão de rota e extrai parâmetros {param} */
    private function matchRoute(string $pattern, string $uri): array|false { /* ... */ }

    /** Executa o pipeline de middlewares recursivamente (padrão onion) */
    private function runMiddlewarePipeline(array $middlewares, array $params, callable $core): void { /* ... */ }
}
