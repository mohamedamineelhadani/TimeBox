<?php
declare(strict_types=1);

// PHP built-in server: let it serve existing static files itself.
if (PHP_SAPI === 'cli-server') {
    $requested = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($requested !== '/' && $requested !== '/index.php' && is_file(__DIR__ . $requested)) {
        return false;
    }
}

$root = dirname(__DIR__);

// Folder the app is served from ('' when public/ is the web root).
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
define('BASE_PATH', PHP_SAPI === 'cli-server' ? '' : $scriptDir);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

ini_set('display_errors', '0');

(new App\Application($root, require $root . '/config.php'))->run();
