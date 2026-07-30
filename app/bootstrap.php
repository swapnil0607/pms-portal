<?php

declare(strict_types=1);

session_start();

$app = require __DIR__ . '/../config/app.php';
date_default_timezone_set($app['timezone'] ?? 'Asia/Kolkata');

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

function config(string $key, mixed $default = null): mixed
{
    static $configs = [];
    [$file, $item] = array_pad(explode('.', $key, 2), 2, null);

    if (!isset($configs[$file])) {
        $path = __DIR__ . '/../config/' . $file . '.php';
        $configs[$file] = is_file($path) ? require $path : [];
    }

    return $item ? ($configs[$file][$item] ?? $default) : $configs[$file];
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_base_path(): string
{
    static $basePath = null;
    if ($basePath !== null) {
        return $basePath;
    }

    $configured = trim((string) config('app.base_url', ''));
    if ($configured !== '') {
        $parsedPath = parse_url($configured, PHP_URL_PATH);
        $basePath = rtrim('/' . ltrim((string) ($parsedPath ?: $configured), '/'), '/');
        return $basePath === '/' ? '' : $basePath;
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (str_ends_with($scriptName, '/public/index.php')) {
        $basePath = substr($scriptName, 0, -strlen('/public/index.php'));
    } elseif (str_ends_with($scriptName, '/index.php')) {
        $basePath = substr($scriptName, 0, -strlen('/index.php'));
    } else {
        $basePath = '';
    }

    $basePath = rtrim($basePath, '/');
    return $basePath === '/' ? '' : $basePath;
}

function url(string $path = '/'): string
{
    if (preg_match('#^https?://#', $path)) {
        return $path;
    }

    $path = '/' . ltrim($path, '/');
    return app_base_path() . ($path === '//' ? '/' : $path);
}

/**
 * Same as url(), but appends the file's mtime as a ?v= query string so
 * browsers fetch a fresh copy whenever this file changes on deploy,
 * instead of serving a stale cached CSS/JS after an update is uploaded.
 */
function asset_url(string $path): string
{
    $filePath = __DIR__ . '/../' . ltrim($path, '/');
    $version = is_file($filePath) ? filemtime($filePath) : time();
    return url($path) . '?v=' . $version;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid request token.');
    }
}
