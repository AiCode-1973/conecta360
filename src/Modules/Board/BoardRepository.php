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
                   -- Membro direto do board
                   EXISTS (SELECT 1 FROM board_members bm WHERE bm.board_id = b.id AND bm.user_id = :uid1)
                   OR (
                       -- Criador do board sempre vê
                       b.created_by = :uid2
                   )
               )
             ORDER BY w.name, b.order_index, b.name'
        );
        $stmt->execute([':uid1' => $userId, ':uid2' => $userId]);
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
