<?php
declare(strict_types=1);

namespace App\Repositories;

interface AdminRepositoryInterface
{
    public function exists(): bool;

    public function create(string $username, string $passwordHash): void;

    /** @return array{id: int, username: string, password_hash: string}|null */
    public function findByUsername(string $username): ?array;
}
