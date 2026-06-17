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

App\Core\Config::load(app_path('.env'));

if (App\Core\Config::bool('APP_DEBUG', false)) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
