<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\AppException;
use PDO;
use PDOException;

final class AdminRepository implements AdminRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function exists(): bool
    {
        return (bool) $this->pdo->query('SELECT 1 FROM admin LIMIT 1')->fetchColumn();
    }

    public function create(string $username, string $passwordHash): void
    {
        try {
            $this->pdo
                ->prepare('INSERT INTO admin (id, username, password_hash) VALUES (1, ?, ?)')
                ->execute([$username, $passwordHash]);
        } catch (PDOException $e) {
            // The admin table accepts a single row: a duplicate key means one already exists.
            if ($e->getCode() === '23000') {
                throw new AppException('An admin already exists.', 409);
            }
            throw $e;
        }
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, username, password_hash FROM admin WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
