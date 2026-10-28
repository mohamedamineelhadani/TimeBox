<?php
declare(strict_types=1);

namespace App;

use App\Controllers\AuthController;
use App\Controllers\PageController;
use App\Controllers\TaskController;
use App\Exceptions\AppException;
use App\Repositories\AdminRepository;
use App\Repositories\TaskRepository;
use App\Services\AuthService;
use App\Services\TaskService;

/**
 * Wires the objects together, then runs one request:
 * route → access check (setup / login / logged in) → CSRF → controller.
 */
final class Application
{
    private readonly Session $session;
    private readonly Router $router;
    private readonly string $path;
    private AuthService $auth;

    public function __construct(private readonly string $root, private readonly array $config)
    {
        $this->session = new Session($config['session_name'], BASE_PATH !== '' ? BASE_PATH : '/');
        $this->router = new Router();
        $this->path = $this->resolvePath();
    }

    public function run(): void
    {
        $this->sendSecurityHeaders();

        try {
            $this->session->start();
            $this->wire();
            $this->handle();
        } catch (AppException $e) {
            $this->fail($e->getMessage(), $e->httpStatus());
        } catch (\PDOException $e) {
            error_log((string) $e);
            $this->fail('The database isn’t ready. Check config.php and import schema.sql.', 503);
        } catch (\Throwable $e) {
            error_log((string) $e);
            $this->fail('Something went wrong on our side.', 500);
        }
    }

    private function wire(): void
    {
        $pdo = Database::connect($this->config['db']);

        $this->auth = new AuthService(new AdminRepository($pdo));
        $tasks = new TaskService(new TaskRepository($pdo));
        $view = new View($this->root . '/views', $this->root . '/public', $this->session);

        $authController = new AuthController($this->auth, $this->session, $view);
        $pageController = new PageController($tasks, $view);
        $taskController = new TaskController($tasks);

        $this->router->add('GET', '/setup', [$authController, 'showSetup'], Router::SETUP);
        $this->router->add('POST', '/setup', [$authController, 'setup'], Router::SETUP);
        $this->router->add('GET', '/login', [$authController, 'showLogin'], Router::GUEST);
        $this->router->add('POST', '/login', [$authController, 'login'], Router::GUEST);
        $this->router->add('POST', '/logout', [$authController, 'logout'], Router::AUTH);

        $this->router->add('GET', '/', [$pageController, 'app'], Router::AUTH);

        $this->router->add('POST', '/api/tasks', [$taskController, 'store'], Router::AUTH);
        $this->router->add('PUT', '/api/tasks/{id}', [$taskController, 'update'], Router::AUTH);
        $this->router->add('DELETE', '/api/tasks/{id}', [$taskController, 'destroy'], Router::AUTH);
    }

    private function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $route = $this->router->match($method, $this->path)
            ?? throw new AppException('Page not found.', 404);

        $redirect = $this->redirectFor($route['access']);
        if ($redirect !== null) {
            if ($this->isApiRequest()) {
                throw new AppException('Please log in again.', 401);
            }
            Response::redirect($redirect);

            return;
        }

        $input = $this->readInput();
        if ($method !== 'GET') {
            $this->assertCsrf($input);
        }

        ($route['handler'])($route['params'], $input);
    }

    /** Where to send the visitor, or null when the route is allowed. */
    private function redirectFor(string $access): ?string
    {
        if (!$this->auth->hasAdmin()) {
            return $access === Router::SETUP ? null : '/setup';
        }

        return match ($access) {
            Router::SETUP => '/login',
            Router::GUEST => $this->session->isLoggedIn() ? '/' : null,
            Router::AUTH  => $this->session->isLoggedIn() ? null : '/login',
        };
    }

    private function assertCsrf(array $input): void
    {
        $token = $input['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || !$this->session->isValidCsrf($token)) {
            throw new AppException('Your session expired. Reload the page and try again.', 419);
        }
    }

    private function readInput(): array
    {
        if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $data = json_decode((string) file_get_contents('php://input'), true);

            return is_array($data) ? $data : [];
        }

        return $_POST;
    }

    private function resolvePath(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
            $path = substr($path, strlen(BASE_PATH));
        }
        $path = '/' . trim($path, '/');

        return $path === '/index.php' ? '/' : $path;
    }

    private function isApiRequest(): bool
    {
        return str_starts_with($this->path, '/api/');
    }

    private function sendSecurityHeaders(): void
    {
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header(
            "Content-Security-Policy: default-src 'self'; script-src 'self'; "
            . "style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; "
            . "img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'"
        );
    }

    private function fail(string $message, int $status): void
    {
        if ($this->isApiRequest()) {
            Response::json(['error' => $message], $status);

            return;
        }

        $title = match ($status) {
            404     => 'Page not found',
            419     => 'Session expired',
            default => 'Something went wrong',
        };
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        Response::html(
            '<!doctype html><html lang="en"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>' . $e($title) . ' — TimeBox</title>'
            . '<link rel="stylesheet" href="' . $e(BASE_PATH) . '/assets/css/style.css"></head>'
            . '<body><div class="app auth-shell"><section class="auth-card">'
            . '<h1 class="auth-title">' . $e($title) . '</h1>'
            . '<p class="auth-hint">' . $e($message) . '</p>'
            . '<a class="add-btn" href="' . $e(BASE_PATH) . '/">Back to TimeBox</a>'
            . '</section></div></body></html>',
            $status
        );
    }
}
