<?php

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require_once __DIR__ . '/Helpers/helpers.php';

function app_path(string $path = ''): string { return dirname(__DIR__) . ($path ? '/' . ltrim($path, '/') : ''); }
function public_path(string $path = ''): string { return app_path('public' . ($path ? '/' . ltrim($path, '/') : '')); }


function app_normalize_attribution_markup(string $markup): string
{
    $markup = html_entity_decode($markup, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $markup = preg_replace('/<!--.*?-->/s', '', $markup) ?? $markup;
    return trim((string) preg_replace('/\s+/', ' ', $markup));
}

$envFile = app_path('.env');
$envExampleFile = app_path('.env.example');
$envFileRealPath = is_file($envFile) ? realpath($envFile) : false;
$envExampleRealPath = is_file($envExampleFile) ? realpath($envExampleFile) : false;

if (!$envFileRealPath || ($envExampleRealPath && $envFileRealPath === $envExampleRealPath)) {
    $message = 'Missing runtime configuration. Copy .env.example to .env and fill in your values before running the site.';
    if (PHP_SAPI === 'cli') {
        throw new RuntimeException($message);
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

App\Core\Config::load($envFile);

if (App\Core\Config::bool('APP_DEBUG', false)) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
