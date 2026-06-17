<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\MediaController;
use App\Controllers\ActorController;
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
$router->get('/ajax/upcoming-trailer', [MediaController::class, 'upcomingTrailer']);
$router->get('/movies/{slug}', [MediaController::class, 'movie']);
$router->get('/tv/{slug}', [MediaController::class, 'tv']);
$router->get('/tv/{slug}/s{season}/e{episode}', [MediaController::class, 'episode']);
$router->get('/tv/{slug}/s{season}', [MediaController::class, 'season']);
$router->get('/actors/{slug}', [ActorController::class, 'show']);
$router->get('/actor/{slug}', [ActorController::class, 'show']);
try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
} catch (Throwable $e) {
    http_response_code(500);
    throw $e;
}
