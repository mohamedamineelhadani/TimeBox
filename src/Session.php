<?php
declare(strict_types=1);

namespace App;

final class Session
{
    public function __construct(
        private readonly string $name,
        private readonly string $cookiePath,
    ) {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '28800'); // 8 hours
        session_name($this->name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $this->cookiePath,
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public function logIn(int $adminId): void
    {
        session_regenerate_id(true);
        unset($_SESSION['csrf']);
        $_SESSION['admin_id'] = $adminId;
    }

    public function logOut(): void
    {
        $_SESSION = [];
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $cookie['path'],
            'secure'   => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => 'Lax',
        ]);
        session_destroy();
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['admin_id']);
    }

    public function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf'];
    }

    public function isValidCsrf(string $token): bool
    {
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    public function flash(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }

    public function pullFlash(string $key): ?string
    {
        $message = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);

        return $message;
    }
}
