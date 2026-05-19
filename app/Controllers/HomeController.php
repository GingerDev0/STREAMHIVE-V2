<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Repository;
use App\Services\ImportService;
use App\Services\TmdbClient;
use App\Services\SqliteStore;

final class HomeController
{
    public function index(): string
    {
        $tmdb = new TmdbClient();
        $importer = new ImportService(new Repository(), $tmdb);
        $safe = static function (callable $cb): array {
            try { return $cb(); }
            catch (\Throwable) { return []; }
        };

        $moviesRecent = array_values(array_filter($safe(fn() => $tmdb->recentMovies()['results'] ?? []), 'is_released_media'));
        $moviesTrending = array_values(array_filter($safe(fn() => $tmdb->trending('movie')['results'] ?? []), 'is_released_media'));
        $tvRecent = array_values(array_filter($safe(fn() => $tmdb->recentTv()['results'] ?? []), 'is_released_media'));
        $tvTrending = array_values(array_filter($safe(fn() => $tmdb->trending('tv')['results'] ?? []), 'is_released_media'));

        // Eagerly save everything displayed on the homepage, so cards point to permanent local slugs before users click.
        $safe(fn() => ['count' => $importer->prefetchResults(array_slice($moviesRecent, 0, 12), 'movie', 12)]);
        $safe(fn() => ['count' => $importer->prefetchResults(array_slice($moviesTrending, 0, 12), 'movie', 12)]);
        $safe(fn() => ['count' => $importer->prefetchResults(array_slice($tvRecent, 0, 12), 'tv', 12)]);
        $safe(fn() => ['count' => $importer->prefetchResults(array_slice($tvTrending, 0, 12), 'tv', 12)]);

        $repo = new Repository();
        $moviesRecent = $this->hydrateFromLocal($moviesRecent, 'movie', $repo);
        $moviesTrending = $this->hydrateFromLocal($moviesTrending, 'movie', $repo);
        $tvRecent = $this->hydrateFromLocal($tvRecent, 'tv', $repo);
        $tvTrending = $this->hydrateFromLocal($tvTrending, 'tv', $repo);

        // Carousel spotlight should rotate through the full local movie database,
        // not just the current TMDB trending/recent response. Missing-poster
        // movies are excluded here only; they still show everywhere else.
        $heroMovies = SqliteStore::randomReleasedFromBucket('movies', 10, true);
        if (!$heroMovies) {
            $heroMovies = $this->randomMoviesForHero($moviesTrending, $moviesRecent);
        }

        return View::render('pages/home', compact('moviesRecent','moviesTrending','tvRecent','tvTrending','heroMovies') + [
            'title' => 'StreamHIVE',
            'metaDescription' => 'Explore trending movies and TV shows with posters, cast, episodes, ratings, genres, bookmarks, and instant playback.',
            'ogTitle' => 'StreamHIVE | Trending Movies and TV Shows',
            'ogDescription' => 'Discover trending movies and TV shows in a bold cinematic interface.',
            'canonicalUrl' => absolute_url('/'),
        ]);
    }

    private function randomMoviesForHero(array ...$groups): array
    {
        $seen = [];
        $movies = [];

        foreach ($groups as $group) {
            foreach ($group as $item) {
                $id = (int)($item['tmdb_id'] ?? $item['id'] ?? 0);
                if ($id <= 0 || isset($seen[$id])) continue;

                // Carousel-only rule: skip movies without a real poster here,
                // but do not remove them from normal listings/search/details.
                if (!$this->hasUsablePoster($item)) continue;

                $seen[$id] = true;
                $item['media_type'] = 'movie';
                $movies[] = $item;
            }
        }

        shuffle($movies);
        return array_slice($movies, 0, 10);
    }

    private function hasUsablePoster(array $item): bool
    {
        $poster = trim((string)($item['poster_path'] ?? ''));
        if ($poster === '') return false;

        return !str_contains($poster, 'placeholder.jpg')
            && !str_contains($poster, 'placeholder.svg');
    }

    private function hydrateFromLocal(array $items, string $type, Repository $repo): array
    {
        $store = $type === 'movie' ? $repo->movies : $repo->tv;
        return array_values(array_map(static function (array $item) use ($store): array {
            $id = (int)($item['id'] ?? 0);
            if ($id > 0) {
                $local = $store->find($id);
                if ($local) return $local;
            }
            return $item;
        }, $items));
    }
}
