<?php
/**
 * Conecta360 — Roteador funcional
 * Variáveis esperadas do front controller: $uri, $method
 */
declare(strict_types=1);

// ── Helper de redirecionamento ────────────────────────────────────────────────
function redirect(string $path): never {
    $base = rtrim(env('APP_URL', ''), '/');
    header('Location: ' . $base . $path);
    exit;
}

// ── Helpers de autenticação ───────────────────────────────────────────────────
function is_logged_in(): bool { return !empty($_SESSION['user_id']); }
function require_auth(): void { if (!is_logged_in()) redirect('/login'); }
function require_guest(): void { if (is_logged_in()) redirect('/dashboard'); }

// ── Helpers CSRF ──────────────────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_verify(): bool {
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

// ── Helpers de flash ──────────────────────────────────────────────────────────
function flash_set(string $type, string $msg): void { $_SESSION['flash'] = compact('type', 'msg'); }
function flash_get(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

// ── Helper de view ────────────────────────────────────────────────────────────
function render(string $view, array $data = []): void {
    extract($data, EXTR_SKIP);
    require BASE_PATH . '/views/' . ltrim($view, '/');
}

// ═══════════════════════════════════════════════════════════════════════════════
// ROTAS
// ═══════════════════════════════════════════════════════════════════════════════

// Raiz
if ($uri === '/') {
    is_logged_in() ? redirect('/dashboard') : redirect('/login');
}

// GET /login
if ($uri === '/login' && $method === 'GET') {
    require_guest();
    require BASE_PATH . '/views/auth/login.php';
    exit;
}

// POST /login
if ($uri === '/login' && $method === 'POST') {
    require_guest();
    if (!csrf_verify()) { flash_set('error', 'Token de segurança inválido.'); redirect('/login'); }

    $email    = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        flash_set('error', 'E-mail e senha são obrigatórios.');
        redirect('/login');
    }

    try {
        $pdo  = pdo_master();
        $stmt = $pdo->prepare('SELECT id, name, email, password, status FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
    } catch (Exception $e) {
        error_log('[login] DB: ' . $e->getMessage());
        flash_set('error', 'Erro interno. Tente novamente.');
        redirect('/login');
    }

    $hash = $user['password'] ?? '$2y$12$invalidhashXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
    if (!$user || !password_verify($password, $hash)) {
        flash_set('error', 'E-mail ou senha inválidos.');
        redirect('/login');
    }
    if ($user['status'] !== 'active') {
        flash_set('error', 'Conta inativa. Contate o administrador.');
        redirect('/login');
    }

    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['logged_at']  = time();
    unset($_SESSION['csrf_token']);

    try {
        $pdo->prepare('UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?')
            ->execute([$_SERVER['REMOTE_ADDR'] ?? '', $user['id']]);
    } catch (Exception) {}

    redirect('/dashboard');
}

// GET /logout
if ($uri === '/logout') {
    $_SESSION = []; session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
    redirect('/login');
}

// GET /dashboard
if ($uri === '/dashboard' && $method === 'GET') {
    require_auth();
    require BASE_PATH . '/views/dashboard/index.php';
    exit;
}

// 404
http_response_code(404);
require BASE_PATH . '/views/errors/404.php';
exit;

// ─── código antigo (mantido como referência, não executado) ──────────────────
if (false) {
// CONVENÇÕES:
//  - Rotas públicas: sem middleware de autenticação
//  - Rotas protegidas: [AuthMiddleware::class] obrigatório
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
