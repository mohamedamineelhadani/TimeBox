<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AppException;
use App\Repositories\AdminRepositoryInterface;

final class AuthService
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(private readonly AdminRepositoryInterface $admins)
    {
    }

    public function hasAdmin(): bool
    {
        return $this->admins->exists();
    }

    public function setupAdmin(string $username, string $password, string $confirmation): void
    {
        if ($this->hasAdmin()) {
            throw new AppException('An admin already exists.', 409);
        }

        $username = trim($username);
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            throw new AppException('Username must be 3–50 characters: letters, numbers, dot, dash or underscore.');
        }
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new AppException('Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.');
        }
        if (!hash_equals($password, $confirmation)) {
            throw new AppException('The two passwords don’t match.');
        }

        $this->admins->create($username, password_hash($password, PASSWORD_DEFAULT));
    }

    /** @return int|null the admin id when the credentials are right */
    public function authenticate(string $username, string $password): ?int
    {
        $admin = $this->admins->findByUsername(trim($username));

        // Always hash/verify, so a wrong username takes as long as a wrong password.
        $hash = $admin['password_hash'] ?? password_hash($password, PASSWORD_DEFAULT);
        $valid = password_verify($password, $hash);

        return ($admin !== null && $valid) ? (int) $admin['id'] : null;
    }
}
