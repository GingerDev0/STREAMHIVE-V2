<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\MediaController;
use App\Controllers\ActorController;
use App\Controllers\AdminController;
use App\Controllers\ProfileController;

require dirname(__DIR__) . '/app/bootstrap.php';

$router = new Router();

$router->get('/', [HomeController::class, 'index']);
$router->get('/movies', [MediaController::class, 'movies']);
$router->get('/tv', [MediaController::class, 'tvShows']);
$router->get('/s/{query}', [MediaController::class, 'search']);
$router->get('/s', [MediaController::class, 'search']);
$router->get('/profile', [ProfileController::class, 'index']);
$router->get('/coming-this-year', [MediaController::class, 'comingThisYear']);
$router->get('/actors', [ActorController::class, 'index']);
$router->get('/ajax/content-status', [MediaController::class, 'contentStatus']);
$router->get('/ajax/ensure-content', [MediaController::class, 'ensureContent']);
$router->get('/ajax/live-search', [MediaController::class, 'liveSearch']);
$router->get('/movies/{slug}', [MediaController::class, 'movie']);
$router->get('/tv/{slug}', [MediaController::class, 'tv']);
$router->get('/tv/{slug}/s{season}/e{episode}', [MediaController::class, 'episode']);
$router->get('/tv/{slug}/s{season}', [MediaController::class, 'season']);
$router->get('/actors/{slug}', [ActorController::class, 'show']);
$router->get('/actor/{slug}', [ActorController::class, 'show']);
$router->match(['GET','POST'], '/admin', [AdminController::class, 'dashboard']);
$router->match(['GET','POST'], '/admin/import', [AdminController::class, 'import']);
$router->post('/admin/import-prefetched', [AdminController::class, 'importPrefetched']);
$router->get('/admin/manage/{type}', [AdminController::class, 'manage']);
$router->post('/admin/delete/{type}/{id}', [AdminController::class, 'delete']);

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
} catch (Throwable $e) {
    http_response_code(500);
    if ($e instanceof \mysqli_sql_exception || str_contains($e->getMessage(), 'MySQL') || str_contains($e->getMessage(), 'MySQLi')) {
        echo '<!doctype html><meta charset="utf-8"><title>MySQL connection error</title><body style="font-family:system-ui;background:#09090b;color:#fff;padding:40px"><h1>MySQL connection error</h1><p>Check the <code>DB_*</code> values in <code>.env</code> and make sure the PHP <code>mysqli</code> extension is enabled.</p><pre style="white-space:pre-wrap;color:#fbbf24">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre></body>';
        return;
    }
    throw $e;
}
