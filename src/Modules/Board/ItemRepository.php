<?php
declare(strict_types=1);

class ItemRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Carrega todos os itens de um board (com assignee) + pivot de valores.
     * Retorna [ 'items' => [group_id => [item, ...]], 'values' => [item_id => [col_id => value]] ]
     */
    public function loadBoard(int $boardId): array
    {
        // Items
        $stmt = $this->pdo->prepare(
            'SELECT i.*, u.name AS assignee_name
             FROM items i
             LEFT JOIN users u ON u.id = i.assignee_id
             WHERE i.board_id = :bid AND i.deleted_at IS NULL AND i.is_archived = 0
             ORDER BY i.order_index, i.id'
        );
        $stmt->execute([':bid' => $boardId]);
        $rows = $stmt->fetchAll();

        $byGroup = [];
        $itemIds = [];
        foreach ($rows as $row) {
            $byGroup[(int)$row['group_id']][] = $row;
            $itemIds[] = (int)$row['id'];
        }

        // Values (um único SELECT para todo o board)
        $values = [];
        if (!empty($itemIds)) {
            $in = implode(',', $itemIds);
            $vStmt = $this->pdo->query(
                "SELECT iv.item_id, iv.column_id,
                        iv.value_text, iv.value_number, iv.value_date, iv.value_json
                 FROM item_values iv
                 WHERE iv.item_id IN ({$in})"
            );
            foreach ($vStmt->fetchAll() as $v) {
                $values[(int)$v['item_id']][(int)$v['column_id']] = $v;
            }
        }

        return ['items' => $byGroup, 'values' => $values];
    }

    public function create(int $boardId, int $groupId, string $name, int $userId): int
    {
        $max = $this->pdo->prepare(
            'SELECT COALESCE(MAX(order_index),0) FROM items WHERE group_id = :gid AND deleted_at IS NULL'
        );
        $max->execute([':gid' => $groupId]);
        $pos = (int)$max->fetchColumn() + 1000;

        $stmt = $this->pdo->prepare(
            'INSERT INTO items (board_id, group_id, name, created_by, order_index) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$boardId, $groupId, $name, $userId, $pos]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateName(int $id, string $name): void
    {
        $this->pdo->prepare('UPDATE items SET name = ?, updated_at = NOW() WHERE id = ?')
                  ->execute([$name, $id]);
    }

    public function move(int $id, int $newGroupId): void
    {
        $max = $this->pdo->prepare(
            'SELECT COALESCE(MAX(order_index),0) FROM items WHERE group_id = :gid AND deleted_at IS NULL'
        );
        $max->execute([':gid' => $newGroupId]);
        $pos = (int)$max->fetchColumn() + 1000;

        $this->pdo->prepare('UPDATE items SET group_id = ?, order_index = ?, updated_at = NOW() WHERE id = ?')
                  ->execute([$newGroupId, $pos, $id]);
    }

    public function archive(int $id): void
    {
        $this->pdo->prepare('UPDATE items SET is_archived = 1, archived_at = NOW() WHERE id = ?')
                  ->execute([$id]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('UPDATE items SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->pdo->prepare('SELECT * FROM items WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function upsertValue(int $itemId, int $columnId, array $value): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO item_values (item_id, column_id, value_text, value_number, value_date, value_json)
             VALUES (:item_id, :col_id, :vt, :vn, :vd, :vj)
             ON DUPLICATE KEY UPDATE
               value_text   = VALUES(value_text),
               value_number = VALUES(value_number),
               value_date   = VALUES(value_date),
               value_json   = VALUES(value_json),
               updated_at   = NOW()'
        );
        $stmt->execute([
            ':item_id' => $itemId,
            ':col_id'  => $columnId,
            ':vt'      => $value['value_text']   ?? null,
            ':vn'      => $value['value_number']  ?? null,
            ':vd'      => $value['value_date']    ?? null,
            ':vj'      => $value['value_json']    ?? null,
        ]);
    }

    public function reorderInGroup(int $groupId, array $orderedIds): void
    {
        $stmt = $this->pdo->prepare('UPDATE items SET order_index = ? WHERE id = ? AND group_id = ?');
        foreach ($orderedIds as $pos => $iid) {
            $stmt->execute([($pos + 1) * 1000, (int)$iid, $groupId]);
        }
    }
}
