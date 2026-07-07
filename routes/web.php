<?php
/**
 * Routes: web.php — Definição de rotas da aplicação
 *
 * Todas as rotas do tenant passam por aqui.
 * O Router é criado e retornado para o front controller (index.php).
 *
 * CONVENÇÕES:
 *  - Rotas públicas: sem middleware de autenticação
 *  - Rotas protegidas: [AuthMiddleware::class] obrigatório
 *  - Rotas com permissão: [AuthMiddleware::class, new PermissionMiddleware('slug')]
 *  - Todos os POSTs: [CsrfMiddleware::class] obrigatório
 */

declare(strict_types=1);

use Conecta360\Core\Router;
use Conecta360\Middleware\AuthMiddleware;
use Conecta360\Middleware\CsrfMiddleware;
use Conecta360\Middleware\PermissionMiddleware;
use Conecta360\Modules\Auth\AuthController;
use Conecta360\Modules\Dashboard\DashboardController;
use Conecta360\Modules\Board\BoardController;

$router = new Router();

// ── Rotas públicas (sem autenticação) ─────────────────────────────────────────
$router->get( '/login',           [AuthController::class, 'showLogin']);
$router->post('/login',           [AuthController::class, 'login'],          [CsrfMiddleware::class]);
$router->post('/logout',          [AuthController::class, 'logout'],         [AuthMiddleware::class, CsrfMiddleware::class]);
$router->get( '/register',        [AuthController::class, 'showRegister']);
$router->post('/register',        [AuthController::class, 'register'],       [CsrfMiddleware::class]);
$router->get( '/forgot-password', [AuthController::class, 'showForgotPassword']);
$router->post('/forgot-password', [AuthController::class, 'forgotPassword'], [CsrfMiddleware::class]);
$router->get( '/reset-password',  [AuthController::class, 'showResetPassword']);
$router->post('/reset-password',  [AuthController::class, 'resetPassword'],  [CsrfMiddleware::class]);
$router->get( '/verify-email',    [AuthController::class, 'verifyEmail']);

// ── Rotas protegidas (requerem autenticação) ───────────────────────────────────
$router->group('', [AuthMiddleware::class], function (Router $r): void {

    // Dashboard
    $r->get('/',          [DashboardController::class, 'index']);
    $r->get('/dashboard', [DashboardController::class, 'index']);

    // Boards
    $r->get( '/boards',         [BoardController::class, 'index']);
    $r->get( '/boards/{id}',    [BoardController::class, 'show']);
    $r->post('/boards',         [BoardController::class, 'store'],   [CsrfMiddleware::class, new PermissionMiddleware('boards.create')]);
    $r->put( '/boards/{id}',    [BoardController::class, 'update'],  [CsrfMiddleware::class, new PermissionMiddleware('boards.edit')]);
    $r->delete('/boards/{id}',  [BoardController::class, 'destroy'], [CsrfMiddleware::class, new PermissionMiddleware('boards.delete')]);

    // ... demais módulos seguem o mesmo padrão

});

return $router;
