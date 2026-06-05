<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Repository;
use App\Services\TmdbClient;
use App\Services\ImportService;
use App\Services\MysqliStore;

final class MediaController
{
    private Repository $repo;
    private ImportService $importer;

    public function __construct()
    {
        $this->repo = new Repository();
        $this->importer = new ImportService($this->repo);
    }

    public function movie(array $params): string
    {
        try {
            $movie = $this->repo->bySlug('movie', $params['slug']);
            if (!$movie) $movie = $this->autoImport('movie', $params['slug']);
            if (!$movie) return $this->notFound();
            $movie = $this->importer->ensureFull($movie, 'movie');
        } catch (\Throwable) {
            return $this->fetchingPage('movie', (string)$params['slug'], 'This movie page is being fetched and saved locally. Please wait...');
        }
        if (!is_released_media($movie)) return $this->notFound();
        return View::render('pages/movie', [
            'title' => ($movie['title'] ?? 'Movie') . ' | Watch Movie',
            'metaDescription' => meta_excerpt($movie['overview'] ?? ('Watch ' . ($movie['title'] ?? 'this movie') . ' with cast, ratings, genres, and recommendations.')),
            'ogTitle' => ($movie['title'] ?? 'Movie') . ' | StreamHIVE',
            'ogDescription' => meta_excerpt($movie['overview'] ?? ''),
            'ogType' => 'video.movie',
            'ogImage' => meta_image($movie['backdrop_path'] ?? ($movie['poster_path'] ?? null)),
            'canonicalUrl' => absolute_url('movies/' . ($movie['slug'] ?? '')),
            'item' => $movie,
            'collectionMovies' => $this->safeCollectionMovies($movie),
            'related' => $this->relatedItems($movie, 'movie'),
        ]);
    }

    public function tv(array $params): string
    {
        try {
            $tv = $this->repo->bySlug('tv', $params['slug']);
            if (!$tv) $tv = $this->autoImport('tv', $params['slug']);
            if (!$tv) return $this->notFound();
            $tv = $this->importer->ensureFull($tv, 'tv');
        } catch (\Throwable) {
            return $this->fetchingPage('tv', (string)$params['slug'], 'This TV show is being fetched and saved locally. Please wait...');
        }
        if (!is_released_media($tv)) return $this->notFound();
        return View::render('pages/tv', [
            'title' => ($tv['title'] ?? 'TV Show') . ' | TV Show',
            'metaDescription' => meta_excerpt($tv['overview'] ?? ('Explore episodes, seasons, cast, ratings, and recommendations for ' . ($tv['title'] ?? 'this TV show') . '.')),
            'ogTitle' => ($tv['title'] ?? 'TV Show') . ' | StreamHIVE',
            'ogDescription' => meta_excerpt($tv['overview'] ?? ''),
            'ogType' => 'video.tv_show',
            'ogImage' => meta_image($tv['backdrop_path'] ?? ($tv['poster_path'] ?? null)),
            'canonicalUrl' => absolute_url('tv/' . ($tv['slug'] ?? '')),
            'item' => $tv,
            'related' => $this->relatedItems($tv, 'tv'),
        ]);
    }

    public function season(array $params): string
    {
        $tv = $this->repo->bySlug('tv', $params['slug']);
        if (!$tv) $tv = $this->autoImport('tv', $params['slug']);
        if (!$tv) return $this->notFound();
        $tv = $this->importer->ensureFull($tv, 'tv');
        if (!is_released_media($tv)) return $this->notFound();
        $seasonNumber = (int)$params['season'];
        if ($seasonNumber < 1) return $this->notFound();

        try {
            $season = (new TmdbClient())->season((int)$tv['tmdb_id'], $seasonNumber);
        } catch (\Throwable) {
            return $this->notFound();
        }
        if (trim((string)($season['air_date'] ?? '')) === '' || is_future_date((string)($season['air_date'] ?? ''))) return $this->notFound();
        $season['episodes'] = array_values(array_filter($season['episodes'] ?? [], static fn(array $episode): bool => trim((string)($episode['air_date'] ?? '')) !== '' && !is_future_date((string)($episode['air_date'] ?? ''))));

        return View::render('pages/season', [
            'title' => $tv['title'] . ' - Season ' . $seasonNumber,
            'metaDescription' => meta_excerpt('Browse every episode from ' . $tv['title'] . ' season ' . $seasonNumber . '.'),
            'ogTitle' => $tv['title'] . ' - Season ' . $seasonNumber . ' | StreamHIVE',
            'ogDescription' => meta_excerpt($season['overview'] ?? ($tv['overview'] ?? '')),
            'ogType' => 'video.tv_show',
            'ogImage' => meta_image($season['poster_path'] ?? ($tv['backdrop_path'] ?? ($tv['poster_path'] ?? null))),
            'canonicalUrl' => absolute_url('tv/' . ($tv['slug'] ?? '') . '/s' . str_pad((string)$seasonNumber, 2, '0', STR_PAD_LEFT)),
            'show' => $tv,
            'season' => $season,
            'seasonNumber' => $seasonNumber,
            'related' => $this->relatedItems($tv, 'tv'),
        ]);
    }

    public function episode(array $params): string
    {
        $tv = $this->repo->bySlug('tv', $params['slug']);
        if (!$tv) $tv = $this->autoImport('tv', $params['slug']);
        if (!$tv) return $this->notFound();
        $tv = $this->importer->ensureFull($tv, 'tv');
        if (!is_released_media($tv)) return $this->notFound();

        $seasonNumber = (int)$params['season'];
        $episodeNumber = (int)$params['episode'];
        $tmdb = new TmdbClient();

        try {
            $episode = $tmdb->episode((int)$tv['tmdb_id'], $seasonNumber, $episodeNumber);
        } catch (\Throwable) {
            return $this->notFound();
        }
        if (trim((string)($episode['air_date'] ?? '')) === '' || is_future_date((string)($episode['air_date'] ?? ''))) return $this->notFound();

        return View::render('pages/episode', [
            'title' => $tv['title'] . ' S' . $params['season'] . 'E' . $params['episode'] . ' - ' . ($episode['name'] ?? 'Episode'),
            'metaDescription' => meta_excerpt($episode['overview'] ?? ('Watch ' . $tv['title'] . ' season ' . $seasonNumber . ', episode ' . $episodeNumber . '.')),
            'ogTitle' => $tv['title'] . ' S' . str_pad((string)$seasonNumber, 2, '0', STR_PAD_LEFT) . 'E' . str_pad((string)$episodeNumber, 2, '0', STR_PAD_LEFT) . ' - ' . ($episode['name'] ?? 'Episode'),
            'ogDescription' => meta_excerpt($episode['overview'] ?? ($tv['overview'] ?? '')),
            'ogType' => 'video.episode',
            'ogImage' => meta_image($episode['still_path'] ?? ($tv['backdrop_path'] ?? ($tv['poster_path'] ?? null))),
            'canonicalUrl' => absolute_url('tv/' . ($tv['slug'] ?? '') . '/s' . str_pad((string)$seasonNumber, 2, '0', STR_PAD_LEFT) . '/e' . str_pad((string)$episodeNumber, 2, '0', STR_PAD_LEFT)),
            'show' => $tv,
            'episode' => $episode,
            'season' => $seasonNumber,
            'nextEpisode' => $this->nextEpisode($tmdb, $tv, $seasonNumber, $episodeNumber),
            'related' => $this->relatedItems($tv, 'tv'),
        ]);
    }


    public function contentStatus(array $params): string
    {
        return $this->jsonResponse($this->contentState(false));
    }

    public function ensureContent(array $params): string
    {
        return $this->jsonResponse($this->contentState(true));
    }

    private function contentState(bool $ensure): array
    {
        $type = (string)($_GET['type'] ?? '');
        $slug = trim((string)($_GET['slug'] ?? ''));
        $season = max(0, (int)($_GET['season'] ?? 0));
        $episode = max(0, (int)($_GET['episode'] ?? 0));

        if (!in_array($type, ['movie', 'tv', 'season', 'episode', 'person'], true) || $slug === '') {
            return ['ok' => false, 'ready' => false, 'message' => 'Invalid content request.'];
        }

        try {
            if ($type === 'movie') {
                $record = $this->repo->bySlug('movie', $slug);
                $ready = $record && (($record['import_status'] ?? '') === 'full') && !empty($record['cast']);
                if (!$ensure) return ['ok' => true, 'ready' => $ready, 'url' => url('movies/' . $slug)];
                if (!$record) $record = $this->autoImport('movie', $slug);
                if ($record) $record = $this->importer->ensureFull($record, 'movie');
                if (!$record || !is_released_media($record)) return ['ok' => false, 'ready' => false, 'message' => 'This movie could not be added right now.'];
                $finalSlug = (string)($record['slug'] ?? $slug);
                if (!MysqliStore::waitUntilRecordReadable('movies', $finalSlug)) {
                    return ['ok' => false, 'ready' => false, 'message' => 'The movie was saved, but MySQL is still finishing the write. Please try again.'];
                }
                return ['ok' => true, 'ready' => true, 'url' => url('movies/' . $finalSlug)];
            }

            if ($type === 'tv' || $type === 'season' || $type === 'episode') {
                $record = $this->repo->bySlug('tv', $slug);
                $ready = $record && (($record['import_status'] ?? '') === 'full') && !empty($record['cast']);
                if ($type === 'episode') $ready = $ready && $season > 0 && $episode > 0;
                if ($type === 'season') $ready = $ready && $season > 0;
                $targetUrl = $type === 'episode'
                    ? url('tv/' . $slug . '/s' . str_pad((string)$season, 2, '0', STR_PAD_LEFT) . '/e' . str_pad((string)$episode, 2, '0', STR_PAD_LEFT))
                    : ($type === 'season' ? url('tv/' . $slug . '/s' . str_pad((string)$season, 2, '0', STR_PAD_LEFT)) : url('tv/' . $slug));
                if (!$ensure) return ['ok' => true, 'ready' => $ready, 'url' => $targetUrl];
                if (!$record) $record = $this->autoImport('tv', $slug);
                if ($record) $record = $this->importer->ensureFull($record, 'tv');
                if (!$record || !is_released_media($record)) return ['ok' => false, 'ready' => false, 'message' => 'This TV show could not be added right now.'];

                if ($type === 'season' && $season > 0) {
                    $seasonData = (new TmdbClient())->season((int)$record['tmdb_id'], $season);
                    if (trim((string)($seasonData['air_date'] ?? '')) === '' || is_future_date((string)($seasonData['air_date'] ?? ''))) return ['ok' => false, 'ready' => false, 'message' => 'This season is not available yet.'];
                }
                if ($type === 'episode' && $season > 0 && $episode > 0) {
                    $episodeData = (new TmdbClient())->episode((int)$record['tmdb_id'], $season, $episode);
                    if (trim((string)($episodeData['air_date'] ?? '')) === '' || is_future_date((string)($episodeData['air_date'] ?? ''))) return ['ok' => false, 'ready' => false, 'message' => 'This episode is not available yet.'];
                }

                $targetUrl = $type === 'episode'
                    ? url('tv/' . ($record['slug'] ?? $slug) . '/s' . str_pad((string)$season, 2, '0', STR_PAD_LEFT) . '/e' . str_pad((string)$episode, 2, '0', STR_PAD_LEFT))
                    : ($type === 'season' ? url('tv/' . ($record['slug'] ?? $slug) . '/s' . str_pad((string)$season, 2, '0', STR_PAD_LEFT)) : url('tv/' . ($record['slug'] ?? $slug)));
                $finalSlug = (string)($record['slug'] ?? $slug);
                if (!MysqliStore::waitUntilRecordReadable('tv', $finalSlug)) {
                    return ['ok' => false, 'ready' => false, 'message' => 'The TV show was saved, but MySQL is still finishing the write. Please try again.'];
                }
                return ['ok' => true, 'ready' => true, 'url' => $targetUrl];
            }

            if ($type === 'person') {
                $record = $this->repo->bySlug('people', $slug);
                $ready = $record && (($record['import_status'] ?? '') === 'full') && array_key_exists('biography', $record);
                if (!$ensure) return ['ok' => true, 'ready' => $ready, 'url' => url('actors/' . $slug)];
                if (!$record) $record = $this->importer->importPersonFromSlug($slug);
                if ($record) $record = $this->importer->ensureFull($record, 'person');
                if (!$record) return ['ok' => false, 'ready' => false, 'message' => 'This actor could not be added right now.'];
                $finalSlug = (string)($record['slug'] ?? $slug);
                if (!MysqliStore::waitUntilRecordReadable('people', $finalSlug)) {
                    return ['ok' => false, 'ready' => false, 'message' => 'The actor was saved, but MySQL is still finishing the write. Please try again.'];
                }
                return ['ok' => true, 'ready' => true, 'url' => url('actors/' . $finalSlug)];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'ready' => false, 'message' => 'Fetching failed. Please try again.'];
        }

        return ['ok' => false, 'ready' => false, 'message' => 'Unsupported content request.'];
    }


    public function liveSearch(array $params): string
    {
        $query = trim((string)($_GET['q'] ?? ''));
        $items = MysqliStore::liveSearch($query, 6);
        $results = [];

        foreach ($items as $item) {
            $bucket = (string)($item['_bucket'] ?? '');
            $type = $bucket === 'people' ? 'person' : (($item['media_type'] ?? '') === 'tv' || $bucket === 'tv' ? 'tv' : 'movie');
            $title = (string)($item['title'] ?? $item['name'] ?? 'Untitled');
            $slug = (string)($item['slug'] ?? slugify($title));
            $date = media_release_date($item);
            $year = format_year($date);
            $rating = $type === 'person' ? '' : (round((float)($item['vote_average'] ?? 0), 1) ?: '');
            $url = $type === 'person' ? url('actors/' . $slug) : ($type === 'tv' ? url('tv/' . $slug) : url('movies/' . $slug));
            $posterSource = $type === 'person' ? ($item['profile_path'] ?? null) : ($item['poster_path'] ?? null);
            $typeLabel = $type === 'person' ? 'Actor' : ($type === 'tv' ? 'TV Show' : 'Movie');
            $meta = trim(implode(' · ', array_filter([
                $typeLabel,
                $year,
                ($type !== 'person' && !empty($item['age_rating'])) ? display_age_rating($item['age_rating'], $type) : null,
            ])));

            $media = [
                'type' => $type,
                'tmdb_id' => $item['tmdb_id'] ?? $item['id'] ?? null,
                'slug' => $slug,
                'title' => $title,
                'url' => $url,
                'poster' => tmdb_img($posterSource, 'w185'),
                'backdrop' => tmdb_img($item['backdrop_path'] ?? $posterSource, 'w780'),
                'year' => $year,
                'rating' => $rating,
                'meta' => $meta,
            ];

            $results[] = [
                'title' => $title,
                'type' => $type,
                'type_label' => $typeLabel,
                'year' => $year,
                'rating' => $rating,
                'meta' => $meta,
                'url' => $url,
                'poster' => tmdb_img($posterSource, 'w185'),
                'fetch_content' => (($item['_import_status'] ?? '') === 'full') ? '0' : '1',
                'media' => $media,
            ];
        }

        return $this->jsonResponse([
            'ok' => true,
            'query' => $query,
            'results' => array_slice($results, 0, 6),
            'search_url' => search_url(['q' => $query]),
        ]);
    }

    private function jsonResponse(array $payload): string
    {
        header('Content-Type: application/json; charset=utf-8');
        return (string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }


    public function comingThisYear(array $params): string
    {
        $year = (int)(new \DateTimeImmutable('today'))->format('Y');
        $this->prefetchComingThisYear($year);

        // Coming This Year is the one place future-dated movies/TV should still be visible.
        // Normal listings, search, detail pages, carousels, collections, and episodes remain released-only.
        $movies = $this->comingItems(MysqliStore::upcomingInYear('movies', $year), 'movie', $year);
        $tvShows = $this->comingItems(MysqliStore::upcomingInYear('tv', $year), 'tv', $year);

        return View::render('pages/coming', [
            'title' => 'Coming This Year',
            'metaDescription' => 'Browse movies and TV shows coming later this year, grouped into tabs with quick pagination.',
            'ogTitle' => 'Coming This Year | StreamHIVE',
            'ogDescription' => 'Upcoming movies and TV shows coming this year.',
            'canonicalUrl' => absolute_url('coming-this-year'),
            'year' => $year,
            'movies' => $movies,
            'tvShows' => $tvShows,
        ]);
    }

    public function movies(array $params): string
    {
        return $this->listing('movie');
    }

    public function tvShows(array $params): string
    {
        return $this->listing('tv');
    }

    public function search(array $params): string
    {
        $pathQuery = isset($params['query']) ? str_replace('+', ' ', urldecode($params['query'])) : '';
        $query = trim((string)($_GET['q'] ?? $pathQuery));
        $requestedType = (string)($_GET['type'] ?? 'all');
        $type = in_array($requestedType, ['all','movie','tv','person'], true) ? $requestedType : 'all';
        $genre = trim((string)($_GET['genre'] ?? ''));
        $rating = trim((string)($_GET['rating'] ?? ''));
        $year = trim((string)($_GET['year'] ?? ''));
        $score = trim((string)($_GET['user_rating'] ?? ($_GET['score'] ?? '')));
        $sort = (string)($_GET['sort'] ?? 'title_asc');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(48, max(6, (int)($_GET['per_page'] ?? 24)));

        $this->prefetchForSearch($query, $type, $page, $perPage);

        $buckets = match ($type) {
            'movie' => ['movies'],
            'tv' => ['tv'],
            'person' => ['people'],
            default => ['movies', 'tv'],
        };
        $pagination = MysqliStore::queryBuckets($buckets, [
            'query' => $query,
            'genre' => $genre,
            'rating' => $rating,
            'year' => $year,
            'user_rating' => $score,
            'score' => $score,
            'sort' => $sort,
            'page' => $page,
            'per_page' => $perPage,
        ]);

        return View::render('pages/search', [
            'title' => $query ? 'Search: ' . $query : 'Discover Movies and TV',
            'metaDescription' => $query ? meta_excerpt('Search results for ' . $query . ' across movies, TV shows, and actors.') : 'Discover movies and TV shows by title, genre, age rating, year, and sort order.',
            'ogTitle' => $query ? 'Search: ' . $query . ' | StreamHIVE' : 'Discover Movies and TV | StreamHIVE',
            'ogDescription' => $query ? meta_excerpt('Search results for ' . $query . ' on StreamHIVE.') : 'Find movies and TV shows with advanced filters.',
            'canonicalUrl' => absolute_url('s' . (!empty($_SERVER['QUERY_STRING'] ?? '') ? '?' . (string)$_SERVER['QUERY_STRING'] : '')),
            'items' => $pagination['items'],
            'total' => $pagination['total'],
            'page' => $pagination['page'],
            'pages' => $pagination['pages'],
            'perPage' => $perPage,
            'query' => $query,
            'type' => $type,
            'genre' => $genre,
            'rating' => $rating,
            'year' => $year,
            'user_rating' => $score,
            'score' => $score,
            'sort' => $sort,
            'genres' => $this->availableGenres(),
            'ratings' => $this->availableRatings(),
            'userRatingOptions' => $this->userRatingFilterOptions(),
        ]);
    }

    private function listing(string $type): string
    {
        $genre = trim((string)($_GET['genre'] ?? ''));
        $rating = trim((string)($_GET['rating'] ?? ''));
        $year = trim((string)($_GET['year'] ?? ''));
        $score = trim((string)($_GET['user_rating'] ?? ($_GET['score'] ?? '')));
        $sort = (string)($_GET['sort'] ?? 'title_asc');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(48, max(6, (int)($_GET['per_page'] ?? 24)));

        $this->prefetchForListing($type, $page, $perPage);

        $store = $type === 'movie' ? $this->repo->movies : $this->repo->tv;
        $pagination = $store->paginated([
            'genre' => $genre,
            'rating' => $rating,
            'year' => $year,
            'user_rating' => $score,
            'score' => $score,
            'sort' => $sort,
            'page' => $page,
            'per_page' => $perPage,
        ]);

        return View::render('pages/listing', [
            'title' => $type === 'movie' ? 'All Movies' : 'All TV Shows',
            'metaDescription' => $type === 'movie' ? 'Browse every saved movie with filters, sorting, and pagination.' : 'Browse every saved TV show with filters, sorting, and pagination.',
            'ogTitle' => $type === 'movie' ? 'All Movies | StreamHIVE' : 'All TV Shows | StreamHIVE',
            'ogDescription' => $type === 'movie' ? 'Browse all movies on StreamHIVE.' : 'Browse all TV shows on StreamHIVE.',
            'canonicalUrl' => absolute_url($type === 'movie' ? 'movies' : 'tv'),
            'heading' => $type === 'movie' ? 'All Movies' : 'All TV Shows',
            'items' => $pagination['items'],
            'total' => $pagination['total'],
            'page' => $pagination['page'],
            'pages' => $pagination['pages'],
            'perPage' => $perPage,
            'type' => $type,
            'genre' => $genre,
            'rating' => $rating,
            'year' => $year,
            'user_rating' => $score,
            'score' => $score,
            'sort' => $sort,
            'genres' => $this->availableGenres($type),
            'ratings' => $this->availableRatings($type),
            'userRatingOptions' => $this->userRatingFilterOptions(),
        ]);
    }



    private function nextEpisode(TmdbClient $tmdb, array $tv, int $seasonNumber, int $episodeNumber): ?array
    {
        $seriesId = (int)($tv['tmdb_id'] ?? 0);
        if ($seriesId < 1 || $seasonNumber < 1 || $episodeNumber < 1) return null;

        $seasonNumbers = [$seasonNumber];
        foreach (($tv['seasons'] ?? []) as $season) {
            $number = (int)($season['season_number'] ?? 0);
            if ($number > $seasonNumber) $seasonNumbers[] = $number;
        }
        $seasonNumbers = array_values(array_unique($seasonNumbers));
        sort($seasonNumbers, SORT_NUMERIC);

        foreach ($seasonNumbers as $candidateSeason) {
            try {
                $seasonData = $tmdb->season($seriesId, (int)$candidateSeason);
            } catch (\Throwable) {
                continue;
            }

            $episodes = $seasonData['episodes'] ?? [];
            usort($episodes, static fn(array $a, array $b): int => ((int)($a['episode_number'] ?? 0)) <=> ((int)($b['episode_number'] ?? 0)));

            foreach ($episodes as $episode) {
                $number = (int)($episode['episode_number'] ?? 0);
                if ($number < 1) continue;
                if (trim((string)($episode['air_date'] ?? '')) === '' || is_future_date((string)($episode['air_date'] ?? ''))) continue;
                if ((int)$candidateSeason === $seasonNumber && $number <= $episodeNumber) continue;

                return [
                    'season_number' => (int)$candidateSeason,
                    'episode' => $episode,
                ];
            }
        }

        return null;
    }



    private function prefetchComingThisYear(int $year): void
    {
        $start = max(
            (new \DateTimeImmutable('tomorrow'))->format('Y-m-d'),
            sprintf('%d-01-01', $year)
        );
        $end = sprintf('%d-12-31', $year);

        if ($start > $end) return;

        $tmdb = new TmdbClient();
        try {
            for ($page = 1; $page <= 2; $page++) {
                $movies = $tmdb->comingMoviesThisYear($start, $end, $page);
                $tv = $tmdb->comingTvThisYear($start, $end, $page);
                $this->importer->prefetchResults($movies['results'] ?? [], 'movie', 20);
                $this->importer->prefetchResults($tv['results'] ?? [], 'tv', 20);
            }
        } catch (\Throwable) {
            // Keep the page working from existing local MySQL data if TMDB is unavailable.
        }
    }

    private function comingItems(array $items, string $type, int $year): array
    {
        $today = new \DateTimeImmutable('today');
        $start = max($today->modify('+1 day'), new \DateTimeImmutable(sprintf('%d-01-01', $year)));
        $end = new \DateTimeImmutable(sprintf('%d-12-31', $year));

        $items = array_values(array_filter($items, static function (array $item) use ($type, $year, $start, $end): bool {
            if (($item['media_type'] ?? $type) !== $type) return false;
            $date = media_release_date($item);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
            if ((int)substr($date, 0, 4) !== $year) return false;
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$dt) return false;
            return $dt >= $start && $dt <= $end;
        }));

        usort($items, static function (array $a, array $b): int {
            $dateCompare = strcmp(media_release_date($a), media_release_date($b));
            if ($dateCompare !== 0) return $dateCompare;
            return ((float)($b['vote_average'] ?? 0)) <=> ((float)($a['vote_average'] ?? 0));
        });

        return $items;
    }

    private function prefetchForListing(string $type, int $page, int $perPage): void
    {
        try {
            $this->importer->prefetchPopular($type, $page, min(20, $perPage));
        } catch (\Throwable) {
            // Listing pages still work from existing local MySQL data if TMDB is unavailable.
        }
    }

    private function prefetchForSearch(string $query, string $type, int $page, int $perPage): void
    {
        if ($query === '') return;
        $limit = min(20, $perPage);
        try {
            if ($type !== 'tv' && $type !== 'person') $this->importer->prefetchSearch($query, 'movie', $page, $limit);
            if ($type !== 'movie' && $type !== 'person') $this->importer->prefetchSearch($query, 'tv', $page, $limit);
            if ($type === 'person') $this->importer->prefetchSearch($query, 'person', $page, $limit);
        } catch (\Throwable) {
            // Search falls back to existing local MySQL data if TMDB is unavailable.
        }
    }

    private function safeCollectionMovies(array $movie): array
    {
        try {
            return $this->importer->collectionMoviesFor($movie);
        } catch (\Throwable) {
            return [];
        }
    }

    private function relatedItems(array $current, string $type, int $limit = 6): array
    {
        $currentId = (string)($current['id'] ?? '');
        $currentTmdbId = (string)($current['tmdb_id'] ?? '');
        $currentSlug = (string)($current['slug'] ?? '');
        $currentTitle = (string)($current['title'] ?? $current['name'] ?? '');
        $currentGenres = array_map('strtolower', $current['genres'] ?? []);
        $bucket = $type === 'movie' ? 'movies' : 'tv';
        $items = MysqliStore::relatedCandidates($bucket, $currentGenres, $currentId ?: $currentTmdbId, $currentSlug, max(80, $limit * 12));
        if (!$items) {
            $items = $type === 'movie' ? $this->repo->movies->all() : $this->repo->tv->all();
        }

        $scored = [];
        foreach ($items as $item) {
            if (!is_released_media($item)) continue;
            $itemId = (string)($item['id'] ?? '');
            $itemTmdbId = (string)($item['tmdb_id'] ?? '');
            $itemSlug = (string)($item['slug'] ?? '');
            if (($currentId !== '' && $itemId === $currentId) || ($currentTmdbId !== '' && $itemTmdbId === $currentTmdbId) || ($currentSlug !== '' && $itemSlug === $currentSlug)) {
                continue;
            }

            $itemTitle = (string)($item['title'] ?? $item['name'] ?? '');
            $titleScore = $this->relatedTitleScore($currentTitle, $itemTitle);

            $itemGenres = array_map('strtolower', $item['genres'] ?? []);
            $sharedGenres = count(array_intersect($currentGenres, $itemGenres));
            $genreScore = $sharedGenres * 10;
            $ratingScore = (float)($item['vote_average'] ?? 0);

            // Sort priority is deliberate: similar titles/franchises first, then genre matches, then quality/date.
            $item['_related_title_score'] = $titleScore;
            $item['_related_genre_score'] = $genreScore;
            $item['_related_score'] = ($titleScore * 1000) + ($genreScore * 100) + $ratingScore;
            $scored[] = $item;
        }

        usort($scored, static function (array $a, array $b): int {
            $title = ((float)($b['_related_title_score'] ?? 0)) <=> ((float)($a['_related_title_score'] ?? 0));
            if ($title !== 0) return $title;

            $genre = ((float)($b['_related_genre_score'] ?? 0)) <=> ((float)($a['_related_genre_score'] ?? 0));
            if ($genre !== 0) return $genre;

            $score = ((float)($b['_related_score'] ?? 0)) <=> ((float)($a['_related_score'] ?? 0));
            if ($score !== 0) return $score;

            return strcmp((string)($b['release_date'] ?? ''), (string)($a['release_date'] ?? ''));
        });

        return array_slice($scored, 0, $limit);
    }

    private function relatedTitleScore(string $currentTitle, string $itemTitle): float
    {
        $current = $this->normaliseRelatedTitle($currentTitle);
        $item = $this->normaliseRelatedTitle($itemTitle);
        if ($current === '' || $item === '') return 0.0;

        $score = 0.0;
        if ($current === $item) $score += 100.0;
        if (str_contains($item, $current) || str_contains($current, $item)) $score += 45.0;

        $currentTokens = $this->relatedTitleTokens($current);
        $itemTokens = $this->relatedTitleTokens($item);
        if (!$currentTokens || !$itemTokens) return $score;

        $shared = array_values(array_intersect($currentTokens, $itemTokens));
        $sharedCount = count($shared);
        if ($sharedCount > 0) {
            $score += $sharedCount * 18.0;
            $score += ($sharedCount / max(1, count($currentTokens))) * 25.0;
            $score += ($sharedCount / max(1, count($itemTokens))) * 10.0;
        }

        $firstCurrent = $currentTokens[0] ?? '';
        $firstItem = $itemTokens[0] ?? '';
        if ($firstCurrent !== '' && $firstCurrent === $firstItem) $score += 20.0;

        return $score;
    }

    private function normaliseRelatedTitle(string $title): string
    {
        $title = strtolower(trim($title));
        $title = preg_replace('/\([^)]*\)/', ' ', $title) ?? $title;
        $title = preg_replace('/[^a-z0-9]+/', ' ', $title) ?? $title;
        return trim(preg_replace('/\s+/', ' ', $title) ?? $title);
    }

    private function relatedTitleTokens(string $title): array
    {
        $stopWords = ['the' => true, 'a' => true, 'an' => true, 'and' => true, 'or' => true, 'of' => true, 'to' => true, 'in' => true, 'for' => true, 'with' => true, 'on' => true, 'at' => true, 'from' => true, 'part' => true, 'season' => true];
        $tokens = [];
        foreach (explode(' ', $title) as $token) {
            $token = trim($token);
            if ($token === '' || isset($stopWords[$token])) continue;
            if (strlen($token) < 3 && !ctype_digit($token)) continue;
            $tokens[$token] = $token;
        }
        return array_values($tokens);
    }

    private function filterItems(array $items, string $query = '', string $genre = '', string $rating = '', string $year = ''): array
    {
        $query = strtolower(trim($query));
        return array_values(array_filter($items, function (array $item) use ($query, $genre, $rating, $year): bool {
            $isPerson = ($item['media_type'] ?? '') === 'person' || isset($item['profile_path']);
            if (!$isPerson && !is_released_media($item)) return false;
            if ($query !== '') {
                $knownFor = [];
                foreach (($item['known_for'] ?? []) as $credit) $knownFor[] = (string)($credit['title'] ?? '');
                foreach (($item['credits'] ?? []) as $credit) $knownFor[] = (string)($credit['title'] ?? '');
                $haystack = strtolower(implode(' ', array_filter([
                    (string)($item['title'] ?? ''),
                    (string)($item['name'] ?? ''),
                    (string)($item['overview'] ?? ''),
                    (string)($item['biography'] ?? ''),
                    (string)($item['known_for_department'] ?? ''),
                    implode(' ', $item['genres'] ?? []),
                    implode(' ', $knownFor),
                ])));
                if (!str_contains($haystack, $query)) return false;
            }
            if (!$isPerson && $genre !== '' && !in_array($genre, $item['genres'] ?? [], true)) return false;
            if (!$isPerson && $rating !== '' && display_age_rating($item['age_rating'] ?? '', $type) !== display_age_rating($rating, $type)) return false;
            if (!$isPerson && $year !== '') {
                $date = (string)($item['release_date'] ?? $item['first_air_date'] ?? '');
                if (substr($date, 0, 4) !== $year) return false;
            }
            if ($isPerson && ($genre !== '' || $rating !== '' || $year !== '')) return false;
            return true;
        }));
    }

    private function sortItems(array $items, string $sort): array
    {
        usort($items, function (array $a, array $b) use ($sort): int {
            return match ($sort) {
                'title_desc' => strnatcasecmp($b['title'] ?? $b['name'] ?? '', $a['title'] ?? $a['name'] ?? ''),
                'date_asc' => strcmp((string)($a['release_date'] ?? ''), (string)($b['release_date'] ?? '')),
                'date_desc' => strcmp((string)($b['release_date'] ?? ''), (string)($a['release_date'] ?? '')),
                'rating_asc' => ((float)($a['vote_average'] ?? 0)) <=> ((float)($b['vote_average'] ?? 0)),
                'rating_desc' => ((float)($b['vote_average'] ?? 0)) <=> ((float)($a['vote_average'] ?? 0)),
                'updated_desc' => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')),
                default => strnatcasecmp($a['title'] ?? $a['name'] ?? '', $b['title'] ?? $b['name'] ?? ''),
            };
        });
        return $items;
    }

    private function paginate(array $items, int $page, int $perPage): array
    {
        $total = count($items);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        return [
            'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    private function availableGenres(?string $type = null): array
    {
        $stores = $type === 'movie' ? [$this->repo->movies] : ($type === 'tv' ? [$this->repo->tv] : [$this->repo->movies, $this->repo->tv]);
        $genres = [];
        foreach ($stores as $store) {
            foreach ($store->distinctGenres() as $genre) {
                $genres[$genre] = $genre;
            }
        }
        natcasesort($genres);
        return array_values($genres);
    }


    private function userRatingFilterOptions(): array
    {
        $options = [];
        for ($i = 0; $i <= 20; $i++) {
            $value = $i / 2;
            $label = rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
            $options[] = ['value' => $label, 'label' => $label . '+'];
        }
        return $options;
    }

    private function availableRatings(?string $type = null): array
    {
        $order = ['U', 'PG', '12', '12A', '15', '18'];
        $stores = $type === 'movie' ? [$this->repo->movies] : ($type === 'tv' ? [$this->repo->tv] : [$this->repo->movies, $this->repo->tv]);
        $ratings = [];
        foreach ($stores as $store) {
            foreach ($store->distinctValues('age_rating') as $rating) {
                $uk = display_age_rating($rating, $type ?? 'movie');
                if ($uk !== '') $ratings[$uk] = true;
            }
        }
        return array_values(array_filter($order, static fn(string $rating): bool => isset($ratings[$rating])));
    }

    private function autoImport(string $type, string $slug): ?array
    {
        $tmdbId = isset($_GET['tmdb_id']) && ctype_digit((string)$_GET['tmdb_id']) ? (int)$_GET['tmdb_id'] : null;

        try {
            return $type === 'movie'
                ? $this->importer->importMovieFromSlug($slug, $tmdbId)
                : $this->importer->importTvFromSlug($slug, $tmdbId);
        } catch (\Throwable) {
            return null;
        }
    }


    private function fetchingPage(string $type, string $slug, string $message): string
    {
        return View::render('pages/fetching-content', [
            'title' => 'Fetching content | StreamHIVE',
            'robots' => 'noindex, follow',
            'metaDescription' => 'This page is being fetched and saved locally.',
            'fetchType' => $type,
            'fetchSlug' => $slug,
            'fetchFallbackUrl' => url(($type === 'tv' ? 'tv/' : 'movies/') . $slug),
            'message' => $message,
        ]);
    }

    private function notFound(): string { http_response_code(404); return View::render('pages/404', [
        'title' => 'Not found',
        'metaDescription' => 'The page you requested could not be found.',
        'robots' => 'noindex, follow',
    ]); }
}
