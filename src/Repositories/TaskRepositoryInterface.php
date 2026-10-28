<?php
declare(strict_types=1);

namespace App\Repositories;

/**
 * A task is: ['id' => int, 'name' => string, 'duration' => int (minutes),
 *             'start' => ?int (minutes since 00:00), 'status' => string]
 */
interface TaskRepositoryInterface
{
    public function all(): array;

    public function find(int $id): ?array;

    /** Tasks that have a start time, optionally leaving one out. */
    public function scheduled(?int $excludeId = null): array;

    public function create(string $name, int $duration, ?int $start, string $status): array;

    /** @param array<string, mixed> $fields any of: name, duration, start, status */
    public function update(int $id, array $fields): void;

    public function delete(int $id): bool;
}
