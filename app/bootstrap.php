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
function storage_path(string $path = ''): string { return app_path('storage' . ($path ? '/' . ltrim($path, '/') : '')); }
function public_path(string $path = ''): string { return app_path('public' . ($path ? '/' . ltrim($path, '/') : '')); }


function app_normalize_attribution_markup(string $markup): string
{
    $markup = html_entity_decode($markup, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $markup = preg_replace('/<!--.*?-->/s', '', $markup) ?? $markup;
    return trim((string) preg_replace('/\s+/', ' ', $markup));
}

function app_enforce_attribution_guard(): void
{
    $layoutFile = app_path('app/Views/layouts/app.php');

    clearstatcache(true, $layoutFile);

    $requiredCreatorName = 'GingerDev';
    $requiredCreatorLink = 'https://github.com/GingerDev0';
    $requiredTmdbNotice = 'TMDB data powers imports. This product is not endorsed or certified by TMDB.';
    $requiredProjectLink = 'https://github.com/GingerDev0/StreamHIVE-V2';

    $layout = is_file($layoutFile) ? (string) file_get_contents($layoutFile) : '';
    $normalizedLayout = app_normalize_attribution_markup($layout);

    $hasCreatorLine = (bool) preg_match(
        '~<p>\s*Created\s+by\s*<a\b(?=[^>]*href=["\']' . preg_quote($requiredCreatorLink, '~') . '["\'])(?=[^>]*target=["\']_blank["\'])(?=[^>]*rel=["\']noopener\s+noreferrer["\'])[^>]*>\s*' . preg_quote($requiredCreatorName, '~') . '\s*</a>\s*</p>~i',
        $normalizedLayout
    );

    $hasTmdbNotice = str_contains($normalizedLayout, '<p>' . $requiredTmdbNotice . '</p>');

    $hasProjectLine = (bool) preg_match(
        '~<p>\s*Project\s+link:\s*<a\b(?=[^>]*href=["\']' . preg_quote($requiredProjectLink, '~') . '["\'])(?=[^>]*target=["\']_blank["\'])(?=[^>]*rel=["\']noopener\s+noreferrer["\'])[^>]*>\s*' . preg_quote($requiredProjectLink, '~') . '\s*</a>\s*</p>~i',
        $normalizedLayout
    );

    $hasFooterNavLink = (bool) preg_match(
        '~<a\b(?=[^>]*href=["\']' . preg_quote($requiredProjectLink, '~') . '["\'])(?=[^>]*target=["\']_blank["\'])(?=[^>]*rel=["\']noopener\s+noreferrer["\'])[^>]*>\s*(?:<i\b[^>]*>\s*</i>\s*)?GitHub\s*</a>~i',
        $normalizedLayout
    );

    $valid = $layout !== ''
        && $hasCreatorLine
        && $hasTmdbNotice
        && $hasProjectLine
        && $hasFooterNavLink
        && substr_count($layout, $requiredProjectLink) >= 2;

    if ($valid) {
        return;
    }

    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Project attribution required</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#07080d;color:#fff;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;padding:24px}.card{max-width:760px;background:linear-gradient(145deg,rgba(20,24,35,.96),rgba(10,12,18,.96));border:1px solid rgba(255,255,255,.12);border-radius:24px;padding:32px;box-shadow:0 24px 80px rgba(0,0,0,.45)}h1{margin:0 0 12px;font-size:clamp(28px,5vw,44px)}p{color:#c9d1e4;line-height:1.6}.link{display:block;margin-top:16px;padding:14px 16px;border-radius:14px;background:rgba(229,9,20,.14);border:1px solid rgba(229,9,20,.35);color:#fff;word-break:break-all}.muted{font-size:14px;color:#8892aa}</style></head><body><main class="card"><h1>Project attribution required</h1><p>This StreamHIVE build requires the visible creator credit, TMDB notice, and original project link to remain in the footer.</p><p>Restore the required project link below, then reload the page.</p><code class="link">' . htmlspecialchars($requiredProjectLink, ENT_QUOTES, 'UTF-8') . '</code><p class="muted">Required credit: Created by GingerDev. Required notice: TMDB data powers imports. This product is not endorsed or certified by TMDB.</p></main></body></html>';
    exit;
}

App\Core\Config::load(app_path('.env'));

app_enforce_attribution_guard();

if (App\Core\Config::bool('APP_DEBUG', false)) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
