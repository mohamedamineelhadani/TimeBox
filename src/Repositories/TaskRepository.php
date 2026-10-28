<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class TaskRepository implements TaskRepositoryInterface
{
    private const SELECT = 'SELECT id, name, duration, start_minute, status FROM tasks';

    /** API field => database column (whitelist used by update()). */
    private const COLUMNS = [
        'name'     => 'name',
        'duration' => 'duration',
        'start'    => 'start_minute',
        'status'   => 'status',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        $rows = $this->pdo->query(self::SELECT . ' ORDER BY id')->fetchAll();

        return array_map([$this, 'hydrate'], $rows);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function scheduled(?int $excludeId = null): array
    {
        $stmt = $this->pdo->prepare(
            self::SELECT . ' WHERE start_minute IS NOT NULL AND id <> ? ORDER BY start_minute'
        );
        $stmt->execute([$excludeId ?? 0]);

        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    public function create(string $name, int $duration, ?int $start, string $status): array
    {
        $this->pdo
            ->prepare('INSERT INTO tasks (name, duration, start_minute, status) VALUES (?, ?, ?, ?)')
            ->execute([$name, $duration, $start, $status]);

        return [
            'id'       => (int) $this->pdo->lastInsertId(),
            'name'     => $name,
            'duration' => $duration,
            'start'    => $start,
            'status'   => $status,
        ];
    }

    public function update(int $id, array $fields): void
    {
        $sets = [];
        $values = [];
        foreach (self::COLUMNS as $key => $column) {
            if (array_key_exists($key, $fields)) {
                $sets[] = $column . ' = ?';
                $values[] = $fields[$key];
            }
        }
        if ($sets === []) {
            return;
        }

        $values[] = $id;
        $this->pdo
            ->prepare('UPDATE tasks SET ' . implode(', ', $sets) . ' WHERE id = ?')
            ->execute($values);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM tasks WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    private function hydrate(array $row): array
    {
        return [
            'id'       => (int) $row['id'],
            'name'     => $row['name'],
            'duration' => (int) $row['duration'],
            'start'    => $row['start_minute'] === null ? null : (int) $row['start_minute'],
            'status'   => $row['status'],
        ];
    }
}
