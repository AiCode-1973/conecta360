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

// ═══════════════════════════════════════════════════════════════════════════════
// BOARDS
// ═══════════════════════════════════════════════════════════════════════════════

function board_repo(): BoardRepository {
    static $r = null;
    if (!$r) {
        require_once BASE_PATH . '/src/Modules/Board/BoardRepository.php';
        $r = new BoardRepository(pdo_master());
    }
    return $r;
}
function group_repo(): GroupRepository {
    static $r = null;
    if (!$r) {
        require_once BASE_PATH . '/src/Modules/Board/GroupRepository.php';
        $r = new GroupRepository(pdo_master());
    }
    return $r;
}
function item_repo(): ItemRepository {
    static $r = null;
    if (!$r) {
        require_once BASE_PATH . '/src/Modules/Board/ItemRepository.php';
        $r = new ItemRepository(pdo_master());
    }
    return $r;
}

function json_response(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// GET /boards
if ($uri === '/boards' && $method === 'GET') {
    require_auth();
    require BASE_PATH . '/views/boards/index.php';
    exit;
}

// GET /boards/create
if ($uri === '/boards/create' && $method === 'GET') {
    require_auth();
    require BASE_PATH . '/views/boards/create.php';
    exit;
}

// POST /boards/create
if ($uri === '/boards/create' && $method === 'POST') {
    require_auth();
    if (!csrf_verify()) { flash_set('error', 'Token inválido.'); redirect('/boards/create'); }

    $name        = trim($_POST['name'] ?? '');
    $workspaceId = (int)($_POST['workspace_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $visibility  = in_array($_POST['visibility'] ?? '', ['public','private','shared'], true)
                   ? $_POST['visibility'] : 'public';
    $color       = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#0073ea';
    $icon        = trim($_POST['icon'] ?? '📋');

    if (strlen($name) < 2 || strlen($name) > 120) {
        flash_set('error', 'Nome deve ter entre 2 e 120 caracteres.');
        redirect('/boards/create');
    }
    if ($workspaceId < 1) {
        flash_set('error', 'Selecione um workspace.');
        redirect('/boards/create');
    }

    $repo    = board_repo();
    $boardId = $repo->create([
        'workspace_id' => $workspaceId,
        'name'         => $name,
        'description'  => $description,
        'visibility'   => $visibility,
        'color'        => $color,
        'icon'         => $icon,
        'created_by'   => $_SESSION['user_id'],
    ]);
    $repo->addMember($boardId, $_SESSION['user_id'], 'owner');

    // Colunas padrão
    $statusOptions = json_encode(['options' => [
        ['slug' => 'not_started', 'label' => 'Não Iniciado', 'color' => '#c4c4c4'],
        ['slug' => 'in_progress', 'label' => 'Em Andamento', 'color' => '#fdab3d'],
        ['slug' => 'done',        'label' => 'Concluído',    'color' => '#00c875'],
        ['slug' => 'stuck',       'label' => 'Travado',      'color' => '#e2445c'],
    ]]);
    $repo->createColumn(['board_id' => $boardId, 'name' => 'Status',      'type' => 'status', 'settings' => $statusOptions]);
    $repo->createColumn(['board_id' => $boardId, 'name' => 'Responsável', 'type' => 'person', 'settings' => '{}']);
    $repo->createColumn(['board_id' => $boardId, 'name' => 'Data Limite', 'type' => 'date',   'settings' => '{}']);

    // Grupo padrão
    group_repo()->create($boardId, 'Principal');

    flash_set('success', 'Board criado com sucesso!');
    redirect('/boards/' . $boardId);
}

// Workspace create inline
if ($uri === '/workspaces/create' && $method === 'POST') {
    require_auth();
    if (!csrf_verify()) { json_response(['error' => 'Token inválido'], 403); }
    $name = trim($_POST['name'] ?? '');
    if (strlen($name) < 2) { flash_set('error', 'Nome inválido.'); redirect('/boards/create'); }
    $stmt = pdo_master()->prepare(
        'INSERT INTO workspaces (name, created_by) VALUES (?, ?)'
    );
    $stmt->execute([$name, $_SESSION['user_id']]);
    $id = (int)pdo_master()->lastInsertId();
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        json_response(['id' => $id, 'name' => $name]);
    }
    redirect('/boards/create');
}

// GET /boards/{id}
if (preg_match('#^/boards/(\d+)$#', $uri, $m) && $method === 'GET') {
    require_auth();
    $boardId = (int)$m[1];
    require BASE_PATH . '/views/boards/show.php';
    exit;
}

// POST /boards/{id}/groups/create
if (preg_match('#^/boards/(\d+)/groups/create$#', $uri, $m) && $method === 'POST') {
    require_auth();
    if (!csrf_verify()) { json_response(['error' => 'Token inválido'], 403); }
    $boardId = (int)$m[1];
    $name    = trim($_POST['name'] ?? 'Novo Grupo');
    $color   = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#579bfc';
    $id      = group_repo()->create($boardId, $name ?: 'Novo Grupo', $color);
    json_response(['id' => $id, 'name' => $name, 'color' => $color]);
}

// POST /groups/{id}/update
if (preg_match('#^/groups/(\d+)/update$#', $uri, $m) && $method === 'POST') {
    require_auth();
    if (!csrf_verify()) { json_response(['error' => 'Token inválido'], 403); }
    group_repo()->update((int)$m[1], ['name' => $_POST['name'] ?? null, 'color' => $_POST['color'] ?? null]);
    json_response(['ok' => true]);
}

// POST /groups/{id}/delete
if (preg_match('#^/groups/(\d+)/delete$#', $uri, $m) && $method === 'POST') {
    require_auth();
    if (!csrf_verify()) { json_response(['error' => 'Token inválido'], 403); }
    group_repo()->delete((int)$m[1]);
    json_response(['ok' => true]);
}

// POST /boards/{id}/items/create
if (preg_match('#^/boards/(\d+)/items/create$#', $uri, $m) && $method === 'POST') {
    require_auth();
    if (!csrf_verify()) { json_response(['error' => 'Token inválido'], 403); }
    $boardId = (int)$m[1];
    $groupId = (int)($_POST['group_id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    if (!$name || !$groupId) { json_response(['error' => 'Dados inválidos'], 422); }
    $id = item_repo()->create($boardId, $groupId, $name, $_SESSION['user_id']);
    json_response(['id' => $id, 'name' => $name, 'group_id' => $groupId]);
}

// POST /items/{id}/update  (nome)
if (preg_match('#^/items/(\d+)/update$#', $uri, $m) && $method === 'POST') {
    require_auth();
    if (!csrf_verify()) { json_response(['error' => 'Token inválido'], 403); }
    $name = trim($_POST['name'] ?? '');
    if (!$name) { json_response(['error' => 'Nome inválido'], 422); }
    item_repo()->updateName((int)$m[1], $name);
    json_response(['ok' => true]);
}

// POST /items/{id}/values  (valor de coluna dinâmica)
if (preg_match('#^/items/(\d+)/values$#', $uri, $m) && $method === 'POST') {
    require_auth();
    if (!csrf_verify()) { json_response(['error' => 'Token inválido'], 403); }
    $itemId   = (int)$m[1];
    $colId    = (int)($_POST['column_id'] ?? 0);
    $valText  = $_POST['value_text']   ?? null;
    $valNum   = isset($_POST['value_number'])  ? (float)$_POST['value_number']  : null;
    $valDate  = $_POST['value_date']   ?? null;
    $valJson  = $_POST['value_json']   ?? null;
    if (!$colId) { json_response(['error' => 'column_id obrigatório'], 422); }
    item_repo()->upsertValue($itemId, $colId, [
        'value_text'   => $valText,
        'value_number' => $valNum,
        'value_date'   => $valDate ?: null,
        'value_json'   => $valJson,
    ]);
    json_response(['ok' => true]);
}

// POST /items/{id}/move
if (preg_match('#^/items/(\d+)/move$#', $uri, $m) && $method === 'POST') {
    require_auth();
    if (!csrf_verify()) { json_response(['error' => 'Token inválido'], 403); }
    $groupId = (int)($_POST['group_id'] ?? 0);
    if (!$groupId) { json_response(['error' => 'group_id obrigatório'], 422); }
    item_repo()->move((int)$m[1], $groupId);
    json_response(['ok' => true]);
}

// POST /items/{id}/archive
if (preg_match('#^/items/(\d+)/archive$#', $uri, $m) && $method === 'POST') {
    require_auth();
    if (!csrf_verify()) { json_response(['error' => 'Token inválido'], 403); }
    item_repo()->archive((int)$m[1]);
    json_response(['ok' => true]);
}

// POST /items/{id}/delete
if (preg_match('#^/items/(\d+)/delete$#', $uri, $m) && $method === 'POST') {
    require_auth();
    if (!csrf_verify()) { json_response(['error' => 'Token inválido'], 403); }
    item_repo()->delete((int)$m[1]);
    json_response(['ok' => true]);
}

// POST /boards/{id}/columns/create
if (preg_match('#^/boards/(\d+)/columns/create$#', $uri, $m) && $method === 'POST') {
    require_auth();
    if (!csrf_verify()) { json_response(['error' => 'Token inválido'], 403); }
    $boardId  = (int)$m[1];
    $colName  = trim($_POST['name'] ?? '');
    $colType  = $_POST['type'] ?? 'text';
    if (!$colName) { json_response(['error' => 'Nome obrigatório'], 422); }
    $allowedTypes = ['text','long_text','number','date','status','person','checkbox','dropdown','email','phone','link','rating','file'];
    if (!in_array($colType, $allowedTypes, true)) { $colType = 'text'; }
    $settings = '{}';
    if ($colType === 'status') {
        $settings = json_encode(['options' => [
            ['slug' => 'opt1', 'label' => 'Opção 1', 'color' => '#579bfc'],
            ['slug' => 'opt2', 'label' => 'Opção 2', 'color' => '#fdab3d'],
        ]]);
    }
    $id = board_repo()->createColumn(['board_id' => $boardId, 'name' => $colName, 'type' => $colType, 'settings' => $settings]);
    json_response(['id' => $id, 'name' => $colName, 'type' => $colType]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// USUÁRIOS
// ═══════════════════════════════════════════════════════════════════════════════

// GET /users
if ($uri === '/users' && $method === 'GET') {
    require_auth();
    require BASE_PATH . '/views/users/index.php';
    exit;
}

// POST /users/create
if ($uri === '/users/create' && $method === 'POST') {
    require_auth();
    if (!csrf_verify()) { flash_set('error', 'Token inválido.'); redirect('/users'); }

    $name   = trim($_POST['name'] ?? '');
    $email  = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? ''));
    $pass   = $_POST['password'] ?? '';
    $status = in_array($_POST['status'] ?? '', ['active','invited','inactive'], true) ? $_POST['status'] : 'invited';

    if (strlen($name) < 2)     { flash_set('error', 'Nome deve ter ao menos 2 caracteres.'); redirect('/users'); }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { flash_set('error', 'E-mail inválido.'); redirect('/users'); }
    if (strlen($pass) < 6)     { flash_set('error', 'Senha deve ter ao menos 6 caracteres.'); redirect('/users'); }

    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    try {
        $stmt = pdo_master()->prepare(
            'INSERT INTO users (name, email, password, status, email_verified_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())'
        );
        $stmt->execute([$name, $email, $hash, $status]);
        flash_set('success', "Usuário {$name} criado com sucesso.");
    } catch (Exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate')) {
            flash_set('error', 'E-mail já cadastrado.');
        } else {
            error_log('[users.create] ' . $e->getMessage());
            flash_set('error', 'Erro ao criar usuário.');
        }
    }
    redirect('/users');
}

// POST /users/{id}/toggle-status
if (preg_match('#^/users/(\d+)/toggle-status$#', $uri, $m) && $method === 'POST') {
    require_auth();
    if (!csrf_verify()) { json_response(['error' => 'Token inválido'], 403); }
    $uid = (int)$m[1];
    if ($uid === (int)$_SESSION['user_id']) { json_response(['error' => 'Não é possível alterar seu próprio status.'], 422); }
    $user = pdo_master()->prepare('SELECT status FROM users WHERE id = ? LIMIT 1');
    $user->execute([$uid]);
    $row = $user->fetch();
    if (!$row) { json_response(['error' => 'Usuário não encontrado.'], 404); }
    $newStatus = $row['status'] === 'active' ? 'inactive' : 'active';
    pdo_master()->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$newStatus, $uid]);
    json_response(['ok' => true, 'status' => $newStatus]);
}

// POST /users/{id}/delete
if (preg_match('#^/users/(\d+)/delete$#', $uri, $m) && $method === 'POST') {
    require_auth();
    if (!csrf_verify()) { json_response(['error' => 'Token inválido'], 403); }
    $uid = (int)$m[1];
    if ($uid === (int)$_SESSION['user_id']) { json_response(['error' => 'Não é possível excluir seu próprio usuário.'], 422); }
    pdo_master()->prepare('UPDATE users SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL')->execute([$uid]);
    json_response(['ok' => true]);
}

// 404
http_response_code(404);
require BASE_PATH . '/views/errors/404.php';
exit;
