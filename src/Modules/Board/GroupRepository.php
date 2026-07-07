<?php
declare(strict_types=1);

class GroupRepository
{
    public function __construct(private PDO $pdo) {}

    public function allByBoard(int $boardId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM groups WHERE board_id = :id AND deleted_at IS NULL ORDER BY order_index, id'
        );
        $stmt->execute([':id' => $boardId]);
        return $stmt->fetchAll();
    }

    public function create(int $boardId, string $name, string $color = '#579bfc'): int
    {
        $max = $this->pdo->prepare(
            'SELECT COALESCE(MAX(order_index),0) FROM groups WHERE board_id = :bid AND deleted_at IS NULL'
        );
        $max->execute([':bid' => $boardId]);
        $pos = (int)$max->fetchColumn() + 1;

        $stmt = $this->pdo->prepare(
            'INSERT INTO groups (board_id, name, color, order_index) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$boardId, $name, $color, $pos]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];
        foreach (['name', 'color'] as $f) {
            if (isset($data[$f])) {
                $fields[] = "{$f} = ?";
                $params[] = $data[$f];
            }
        }
        if (empty($fields)) return;
        $params[] = $id;
        $this->pdo->prepare('UPDATE groups SET ' . implode(', ', $fields) . ' WHERE id = ?')
                  ->execute($params);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('UPDATE groups SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
    }

    public function reorder(int $boardId, array $orderedIds): void
    {
        $stmt = $this->pdo->prepare('UPDATE groups SET order_index = ? WHERE id = ? AND board_id = ?');
        foreach ($orderedIds as $pos => $gid) {
            $stmt->execute([$pos * 10, (int)$gid, $boardId]);
        }
    }
}
