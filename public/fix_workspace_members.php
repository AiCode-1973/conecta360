<?php
/**
 * Migração: adiciona membros aos workspaces existentes
 * Acesse: https://conecta360.aicode.dev.br/fix_workspace_members.php?token=c360fix2026
 * DELETE após usar!
 */
declare(strict_types=1);

if (($_GET['token'] ?? '') !== 'c360fix2026') {
    http_response_code(403); die('Proibido.');
}

$base = dirname(__DIR__);
require_once $base . '/config/app.php';

$pdo = pdo_master();
$log = [];

// 1. Atualiza os arquivos PHP no servidor (patch do routes/web.php e BoardRepository)
// ─── Patch BoardRepository: adicionar allWorkspacesByUser, getWorkspaceMembers, etc. ───
$repoPath = $base . '/src/Modules/Board/BoardRepository.php';
$repoContent = file_get_contents($repoPath);

$needsUpdate = !str_contains($repoContent, 'allWorkspacesByUser')
            || !str_contains($repoContent, 'getWorkspaceMembers')
            || !str_contains($repoContent, 'removeWorkspaceMember')
            || !str_contains($repoContent, 'getWorkspaceById');

if ($needsUpdate) {
    $newRepo = <<<'PHPEOF'
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
                   EXISTS (SELECT 1 FROM board_members bm WHERE bm.board_id = b.id AND bm.user_id = :uid1)
                   OR (
                       b.visibility = \'public\'
                       AND EXISTS (SELECT 1 FROM workspace_members wm WHERE wm.workspace_id = b.workspace_id AND wm.user_id = :uid2)
                   )
                   OR (b.created_by = :uid3)
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
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }

    public function allWorkspaces(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM workspaces WHERE deleted_at IS NULL ORDER BY name'
        );
        return $stmt->fetchAll();
    }

    public function allWorkspacesByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT w.*
             FROM workspaces w
             WHERE w.deleted_at IS NULL
               AND (
                   w.created_by = :uid1
                   OR EXISTS (
                       SELECT 1 FROM workspace_members wm
                       WHERE wm.workspace_id = w.id AND wm.user_id = :uid2
                   )
               )
             ORDER BY w.name'
        );
        $stmt->execute([':uid1' => $userId, ':uid2' => $userId]);
        return $stmt->fetchAll();
    }

    public function getWorkspaceMembers(int $workspaceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT wm.id, wm.user_id, wm.role, wm.joined_at,
                    u.name AS user_name, u.email AS user_email, u.status AS user_status
             FROM workspace_members wm
             INNER JOIN users u ON u.id = wm.user_id
             WHERE wm.workspace_id = :wid
             ORDER BY wm.role, u.name'
        );
        $stmt->execute([':wid' => $workspaceId]);
        return $stmt->fetchAll();
    }

    public function getWorkspaceById(int $workspaceId): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM workspaces WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $workspaceId]);
        return $stmt->fetch();
    }

    public function removeWorkspaceMember(int $workspaceId, int $userId): void
    {
        $this->pdo->prepare(
            'DELETE FROM workspace_members WHERE workspace_id = ? AND user_id = ?'
        )->execute([$workspaceId, $userId]);
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

    // Fix: getColumns usa $boardId, não $id
    $newRepo = str_replace(
        "SELECT * FROM board_columns WHERE board_id = :id ORDER BY order_index, id'
        );
        $stmt->execute([':id' => \$id]);",
        "SELECT * FROM board_columns WHERE board_id = :id ORDER BY order_index, id'
        );
        \$stmt->execute([':id' => \$boardId]);",
        $newRepo
    );

    if (file_put_contents($repoPath, $newRepo)) {
        $log[] = '✅ BoardRepository.php atualizado com métodos de workspace';
    } else {
        $log[] = '❌ Falha ao escrever BoardRepository.php';
    }
} else {
    $log[] = '⏭️  BoardRepository.php já estava atualizado';
}

// 2. Corrige dados existentes no banco
// ─── Para cada workspace, adiciona o criador como owner em workspace_members ───
try {
    $workspaces = $pdo->query('SELECT id, name, created_by FROM workspaces WHERE deleted_at IS NULL')->fetchAll();
    $added = 0;
    foreach ($workspaces as $ws) {
        $check = $pdo->prepare('SELECT 1 FROM workspace_members WHERE workspace_id = ? AND user_id = ? LIMIT 1');
        $check->execute([$ws['id'], $ws['created_by']]);
        if (!$check->fetchColumn()) {
            $pdo->prepare('INSERT IGNORE INTO workspace_members (workspace_id, user_id, role) VALUES (?, ?, ?)')
                ->execute([$ws['id'], $ws['created_by'], 'owner']);
            $added++;
            $log[] = "✅ Workspace #{$ws['id']} \"{$ws['name']}\": criador (user #{$ws['created_by']}) adicionado como owner";
        } else {
            $log[] = "⏭️  Workspace #{$ws['id']} \"{$ws['name']}\": criador já era membro";
        }
    }
} catch (Throwable $e) {
    $log[] = '❌ Erro ao corrigir workspace_members: ' . $e->getMessage();
}

// 3. Para cada board, adiciona criador como board_member se ainda não for
try {
    $boards = $pdo->query('SELECT id, name, created_by FROM boards WHERE deleted_at IS NULL')->fetchAll();
    foreach ($boards as $b) {
        $check = $pdo->prepare('SELECT 1 FROM board_members WHERE board_id = ? AND user_id = ? LIMIT 1');
        $check->execute([$b['id'], $b['created_by']]);
        if (!$check->fetchColumn()) {
            $pdo->prepare('INSERT IGNORE INTO board_members (board_id, user_id, role) VALUES (?, ?, ?)')
                ->execute([$b['id'], $b['created_by'], 'owner']);
            $log[] = "✅ Board #{$b['id']} \"{$b['name']}\": criador adicionado como owner";
        } else {
            $log[] = "⏭️  Board #{$b['id']} \"{$b['name']}\": criador já era membro";
        }
    }
} catch (Throwable $e) {
    $log[] = '❌ Erro ao corrigir board_members: ' . $e->getMessage();
}

// 4. Verifica resultado final
$wsMembers   = (int)$pdo->query('SELECT COUNT(*) FROM workspace_members')->fetchColumn();
$boardMembers = (int)$pdo->query('SELECT COUNT(*) FROM board_members')->fetchColumn();

?><!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="UTF-8"><title>Fix Members — Conecta360</title>
<style>body{font-family:monospace;background:#111;color:#eee;padding:2rem;line-height:1.9}
h2{color:#fdab3d;margin-bottom:1rem} .ok{color:#00c875} .err{color:#e2445c} .warn{color:#fdab3d}
.box{background:#1a1a2e;border:1px solid #333;padding:1rem 1.5rem;border-radius:8px;margin-bottom:1.5rem}
</style></head><body>
<h2>Conecta360 — Correção de Membros</h2>
<div class="box">
<?php foreach ($log as $l): ?>
<span class="<?= str_starts_with($l,'✅') ? 'ok' : (str_starts_with($l,'❌') ? 'err' : 'warn') ?>"><?= htmlspecialchars($l) ?></span><br>
<?php endforeach ?>
</div>
<div class="box">
<strong>Estado final:</strong><br>
<span class="ok">✅ workspace_members: <?= $wsMembers ?> registros</span><br>
<span class="ok">✅ board_members: <?= $boardMembers ?> registros</span>
</div>
<div class="box">
<strong>Próximos passos:</strong><br>
1. <a href="/boards" style="color:#579bfc">Acesse /boards</a> — você verá o botão "Gerenciar Equipe" nos workspaces<br>
2. Use "Gerenciar Equipe" para convidar outros usuários<br>
3. <strong class="err">DELETE este arquivo: public/fix_workspace_members.php</strong>
</div>
</body></html>
