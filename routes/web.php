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
    setcookie(session_name(), '', time() - 3600, '/', '', true, true);
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
