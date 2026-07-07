<?php
/**
 * Conecta360 — Patch de Correção do Board Create
 * ATENÇÃO: Delete este arquivo após executar!
 *
 * Acesse: https://conecta360.aicode.dev.br/patch_board.php?token=c360patch2026
 */
declare(strict_types=1);

if (($_GET['token'] ?? '') !== 'c360patch2026') {
    http_response_code(403);
    die('Proibido.');
}

$base = dirname(__DIR__);
$log  = [];
$errors = [];

// ═══════════════════════════════════════════════════════════════
// PATCH 1 — BoardRepository.php (fix create + ensureWorkspaceMember)
// ═══════════════════════════════════════════════════════════════
$repoPath = $base . '/src/Modules/Board/BoardRepository.php';

$newRepoContent = <<<'PHPEOF'
<?php
declare(strict_types=1);

class BoardRepository
{
    public function __construct(private PDO $pdo) {}

    public function allByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT b.*, w.name AS workspace_name,
                    (SELECT COUNT(*) FROM items i WHERE i.board_id = b.id AND i.deleted_at IS NULL AND i.is_archived = 0) AS item_count
             FROM boards b
             INNER JOIN workspaces w ON w.id = b.workspace_id
             WHERE b.deleted_at IS NULL
               AND (
                   -- Membro direto do board (qualquer visibilidade)
                   EXISTS (SELECT 1 FROM board_members bm WHERE bm.board_id = b.id AND bm.user_id = :uid1)
                   OR (
                       -- Board público: só visível para membros do workspace
                       b.visibility = \'public\'
                       AND EXISTS (SELECT 1 FROM workspace_members wm WHERE wm.workspace_id = b.workspace_id AND wm.user_id = :uid2)
                   )
                   OR (
                       -- Criador do board sempre vê
                       b.created_by = :uid3
                   )
               )
             ORDER BY w.name, b.order_index, b.name'
        );
        $stmt->execute([':uid1' => $userId, ':uid2' => $userId, ':uid3' => $userId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT b.*, w.name AS workspace_name
             FROM boards b
             INNER JOIN workspaces w ON w.id = b.workspace_id
             WHERE b.id = :id AND b.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function getMemberRole(int $boardId, int $userId): string|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT role FROM board_members WHERE board_id = :bid AND user_id = :uid LIMIT 1'
        );
        $stmt->execute([':bid' => $boardId, ':uid' => $userId]);
        $row = $stmt->fetch();
        return $row ? $row['role'] : false;
    }

    public function isWorkspaceMember(int $workspaceId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM workspace_members WHERE workspace_id = :wid AND user_id = :uid LIMIT 1'
        );
        $stmt->execute([':wid' => $workspaceId, ':uid' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function getColumns(int $boardId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM board_columns WHERE board_id = :id ORDER BY order_index, id'
        );
        $stmt->execute([':id' => $boardId]);
        return $stmt->fetchAll();
    }

    public function allWorkspaces(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM workspaces WHERE deleted_at IS NULL ORDER BY name'
        );
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $maxStmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(order_index),0) FROM boards WHERE workspace_id = :wid'
        );
        $maxStmt->execute([':wid' => $data['workspace_id']]);
        $maxOrder = (int)$maxStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            'INSERT INTO boards (workspace_id, name, description, visibility, color, icon, created_by, order_index)
             VALUES (:workspace_id, :name, :description, :visibility, :color, :icon, :created_by, :order_index)'
        );
        $stmt->execute([
            ':workspace_id' => $data['workspace_id'],
            ':name'         => $data['name'],
            ':description'  => $data['description'] ?? '',
            ':visibility'   => $data['visibility'] ?? 'public',
            ':color'        => $data['color'] ?? '#0073ea',
            ':icon'         => $data['icon'] ?? '📋',
            ':created_by'   => $data['created_by'],
            ':order_index'  => $maxOrder + 1,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function ensureWorkspaceMember(int $workspaceId, int $userId, string $role = 'member'): void
    {
        $this->pdo->prepare(
            'INSERT IGNORE INTO workspace_members (workspace_id, user_id, role) VALUES (?, ?, ?)'
        )->execute([$workspaceId, $userId, $role]);
    }

    public function addMember(int $boardId, int $userId, string $role = 'owner'): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO board_members (board_id, user_id, role) VALUES (?, ?, ?)'
        );
        $stmt->execute([$boardId, $userId, $role]);
    }

    public function createColumn(array $data): int
    {
        $maxOrder = $this->pdo->prepare(
            'SELECT COALESCE(MAX(order_index),0) FROM board_columns WHERE board_id = :bid'
        );
        $maxOrder->execute([':bid' => $data['board_id']]);
        $pos = (int)$maxOrder->fetchColumn() + 1;

        $stmt = $this->pdo->prepare(
            'INSERT INTO board_columns (board_id, name, type, order_index, settings)
             VALUES (:board_id, :name, :type, :order_index, :settings)'
        );
        $stmt->execute([
            ':board_id'    => $data['board_id'],
            ':name'        => $data['name'],
            ':type'        => $data['type'] ?? 'text',
            ':order_index' => $pos,
            ':settings'    => $data['settings'] ?? '{}',
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function archive(int $id): void
    {
        $this->pdo->prepare('UPDATE boards SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
    }
}
PHPEOF;

if (!file_exists($repoPath)) {
    $errors[] = "ARQUIVO NÃO ENCONTRADO: $repoPath";
} else {
    $backup = $repoPath . '.bak';
    copy($repoPath, $backup);
    if (file_put_contents($repoPath, $newRepoContent) !== false) {
        $log[] = "✅ BoardRepository.php atualizado (backup: BoardRepository.php.bak)";
    } else {
        $errors[] = "❌ Falha ao escrever BoardRepository.php — verifique permissões";
    }
}

// ═══════════════════════════════════════════════════════════════
// PATCH 2 — routes/web.php (add try-catch ao POST /boards/create)
// ═══════════════════════════════════════════════════════════════
$routesPath = $base . '/routes/web.php';

if (!file_exists($routesPath)) {
    $errors[] = "ARQUIVO NÃO ENCONTRADO: $routesPath";
} else {
    $routesContent = file_get_contents($routesPath);

    // Detecta se o patch já foi aplicado
    if (str_contains($routesContent, 'catch (Exception $e)')) {
        $log[] = "⏭️  routes/web.php — try-catch já presente, sem alteração";
    } else {
        // Padrão antigo: $repo->create() direto, sem try-catch
        $oldBlock = <<<'OLDEOF'
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
OLDEOF;

        $newBlock = <<<'NEWEOF'
    $repo    = board_repo();
    try {
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
NEWEOF;

        if (!str_contains($routesContent, trim($oldBlock))) {
            $errors[] = "⚠️  routes/web.php — padrão antigo não encontrado (talvez já atualizado ou diferente)";
        } else {
            $patched = str_replace($oldBlock, $newBlock, $routesContent);

            // Também precisa fechar o try{} antes do flash/redirect final
            $oldEnd = <<<'OLDEND'
    // Grupo padrão
    group_repo()->create($boardId, 'Principal');

    flash_set('success', 'Board criado com sucesso!');
    redirect('/boards/' . $boardId);
}
OLDEND;

            $newEnd = <<<'NEWEND'
    // Grupo padrão
        group_repo()->create($boardId, 'Principal');

    } catch (Exception $e) {
        error_log('[board.create] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        flash_set('error', 'Erro ao criar board. Tente novamente.');
        redirect('/boards/create');
    }

    flash_set('success', 'Board criado com sucesso!');
    redirect('/boards/' . $boardId);
}
NEWEND;

            $patched = str_replace($oldEnd, $newEnd, $patched);

            // Adiciona ensureWorkspaceMember se ausente
            if (!str_contains($patched, 'ensureWorkspaceMember')) {
                $patched = str_replace(
                    "\$repo->addMember(\$boardId, \$_SESSION['user_id'], 'owner');\n",
                    "\$repo->addMember(\$boardId, \$_SESSION['user_id'], 'owner');\n        \$repo->ensureWorkspaceMember(\$workspaceId, \$_SESSION['user_id'], 'owner');\n",
                    $patched
                );
            }

            $backup2 = $routesPath . '.bak';
            copy($routesPath, $backup2);
            if (file_put_contents($routesPath, $patched) !== false) {
                $log[] = "✅ routes/web.php atualizado com try-catch (backup: routes/web.php.bak)";
            } else {
                $errors[] = "❌ Falha ao escrever routes/web.php — verifique permissões";
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// VERIFICAÇÃO RÁPIDA — Testa a conexão e o fluxo de criação
// ═══════════════════════════════════════════════════════════════
$diagResult = '';
try {
    require_once $base . '/config/app.php';
    $pdo = pdo_master();

    // Workspace existe?
    $ws = $pdo->query('SELECT id, name FROM workspaces LIMIT 1')->fetch();
    if (!$ws) {
        $errors[] = "⚠️  Nenhum workspace encontrado — execute board_module_migration.sql";
    } else {
        $diagResult = "Workspace encontrado: #{$ws['id']} — {$ws['name']}";

        // Testa INSERT no boards
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO boards (workspace_id,name,description,visibility,color,icon,created_by,order_index) VALUES (?,?,?,?,?,?,?,999)');
        $stmt->execute([$ws['id'], '__patch_test__', '', 'public', '#0073ea', '📋', 1]);
        $testId = (int)$pdo->lastInsertId();
        $pdo->rollBack();

        if ($testId > 0) {
            $log[] = "✅ Teste de INSERT no boards: OK (id=$testId, rolled back)";
        } else {
            $errors[] = "❌ INSERT no boards retornou id=0";
        }
    }
} catch (Throwable $e) {
    $errors[] = "❌ Erro na verificação: " . $e->getMessage();
}

// ═══════════════════════════════════════════════════════════════
// OUTPUT
// ═══════════════════════════════════════════════════════════════
?><!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="UTF-8"><title>Patch — Conecta360</title>
<style>body{font-family:monospace;background:#111;color:#eee;padding:2rem;line-height:1.8}
h2{color:#fdab3d;margin-bottom:1rem} .ok{color:#00c875} .err{color:#e2445c} .warn{color:#fdab3d}
pre{background:#1e1e1e;padding:1rem;border-radius:6px;overflow-x:auto}
.box{background:#1a1a2e;border:1px solid #333;padding:1rem;border-radius:8px;margin-bottom:1.5rem}
</style></head><body>
<h2>Conecta360 — Patch de Correção do Board Create</h2>

<div class="box">
<strong>Resultado:</strong><br>
<?php foreach ($log as $l) echo "<span class='ok'>$l</span><br>"; ?>
<?php foreach ($errors as $e) echo "<span class='err'>$e</span><br>"; ?>
<?php if ($diagResult) echo "<span class='ok'>✅ DB: $diagResult</span><br>"; ?>
</div>

<?php if (empty($errors)): ?>
<div class="box">
<span class="ok">✅ Patch aplicado com sucesso!</span><br><br>
<strong>Próximos passos:</strong><br>
1. <a href="/boards/create" style="color:#579bfc">Teste criar um novo board</a><br>
2. <strong class="err">DELETE este arquivo do servidor: public/patch_board.php</strong><br>
3. Delete também os backups .bak se quiser
</div>
<?php else: ?>
<div class="box">
<span class="err">⚠️ Alguns erros ocorreram. Verifique acima.</span><br>
Pode ser necessário dar permissão de escrita (chmod 644) nos arquivos via cPanel.
</div>
<?php endif; ?>

<pre>PHP: <?= PHP_VERSION ?> | Server: <?= php_uname('s') ?> | Path: <?= $base ?></pre>
</body></html>
