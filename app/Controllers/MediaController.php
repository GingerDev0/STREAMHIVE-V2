<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Repository;
use App\Services\TmdbClient;
use App\Services\ImportService;
use App\Services\LiveCatalog;

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
            $movie = $this->liveMovie((string)$params['slug']);
            if (!$movie) $movie = $this->repo->bySlug('movie', $params['slug']);
            if (!$movie) $movie = $this->autoImport('movie', $params['slug']);
            if (!$movie) return $this->notFound();
        } catch (\Throwable) {
            return $this->notFound();
        }
        if (!is_released_media($movie)) return $this->notFound();
        $siteName = site_name();

        return View::render('pages/movie', [
            'title' => ($movie['title'] ?? 'Movie') . ' | Watch Movie',
            'metaDescription' => meta_excerpt($movie['overview'] ?? ('Watch ' . ($movie['title'] ?? 'this movie') . ' with cast, ratings, genres, and recommendations.')),
            'ogTitle' => ($movie['title'] ?? 'Movie') . ' | ' . $siteName,
            'ogDescription' => meta_excerpt($movie['overview'] ?? ''),
            'ogType' => 'video.movie',
            'ogImage' => meta_image($movie['backdrop_path'] ?? ($movie['poster_path'] ?? null)),
            'canonicalUrl' => absolute_url('movies/' . ($movie['slug'] ?? '')),
            'item' => $movie,
            'collectionMovies' => $this->tmdbCollectionMovies($movie),
            'related' => $this->tmdbRecommendations($movie, 'movie'),
        ]);
    }

    private function liveMovie(string $slug): ?array
    {
        $tmdbId = isset($_GET['tmdb_id']) && ctype_digit((string)$_GET['tmdb_id']) ? (int)$_GET['tmdb_id'] : 0;
        if ($tmdbId <= 0) {
            $tmdbId = $this->resolveMovieIdFromSlug($slug);
        }
        if ($tmdbId <= 0) return null;

        $movie = (new TmdbClient())->movie($tmdbId);
        if (!$movie) {
            return null;
        }

        $title = (string)($movie['title'] ?? $movie['original_title'] ?? $slug);
        $releaseDates = $movie['release_dates']['results'] ?? [];
        $movie['tmdb_id'] = (int)($movie['id'] ?? $tmdbId);
        $movie['title'] = $title;
        $movie['slug'] = movie_slug($title, (string)($movie['release_date'] ?? ''));
        $movie['media_type'] = 'movie';
        $movie['genres'] = array_values(array_map(static fn(array $genre): string => (string)($genre['name'] ?? ''), $movie['genres'] ?? []));
        $movie['cast'] = array_slice($movie['credits']['cast'] ?? [], 0, 12);
        $movie['crew'] = $this->creditCrew($movie['credits']['crew'] ?? []);
        $movie['imdb_id'] = $movie['external_ids']['imdb_id'] ?? '';
        $movie['age_rating'] = $this->certification($releaseDates);
        $movie['import_status'] = 'full';

        foreach ($movie['cast'] as &$actor) {
            $actor['media_type'] = 'person';
            $actor['tmdb_id'] = $actor['id'] ?? null;
            $actor['slug'] = slugify((string)($actor['name'] ?? 'actor'));
        }
        unset($actor);

        return $movie;
    }

    private function creditCrew(array $crew, array $extraPeople = []): array
    {
        $wantedJobs = ['Creator', 'Director', 'Writer', 'Screenplay', 'Story'];
        $people = [];

        foreach ($extraPeople as $person) {
            $person['job'] = $person['job'] ?? 'Creator';
            $crew[] = $person;
        }

        foreach ($crew as $person) {
            $job = trim((string)($person['job'] ?? ''));
            if (!in_array($job, $wantedJobs, true)) continue;

            $id = (int)($person['id'] ?? 0);
            $key = $id > 0 ? (string)$id : strtolower((string)($person['name'] ?? ''));
            if ($key === '') continue;

            if (!isset($people[$key])) {
                $person['media_type'] = 'person';
                $person['tmdb_id'] = $id ?: null;
                $person['slug'] = slugify((string)($person['name'] ?? 'crew'));
                $person['jobs'] = [];
                $people[$key] = $person;
            }

            if (!in_array($job, $people[$key]['jobs'], true)) {
                $people[$key]['jobs'][] = $job;
            }
        }

        return array_values($people);
    }

    private function resolveMovieIdFromSlug(string $slug, ?TmdbClient $tmdb = null): int
    {
        $wantedYear = '';
        $querySlug = $slug;
        if (preg_match('~^(.*)-((?:19|20)\d{2})$~', $slug, $matches)) {
            $querySlug = (string)$matches[1];
            $wantedYear = (string)$matches[2];
        }

        $query = trim(str_replace('-', ' ', $querySlug));
        if ($query === '') return 0;

        $tmdb ??= new TmdbClient();
        try {
            $results = $tmdb->searchMovie($query, 1)['results'] ?? [];
        } catch (\Throwable) {
            return 0;
        }

        foreach ($results as $result) {
            $title = (string)($result['title'] ?? $result['original_title'] ?? '');
            $releaseDate = (string)($result['release_date'] ?? '');
            if (movie_slug($title, $releaseDate) === $slug) {
                return (int)($result['id'] ?? 0);
            }
            if ($wantedYear !== '' && slugify($title) === $querySlug && format_year($releaseDate) === $wantedYear) {
                return (int)($result['id'] ?? 0);
            }
        }

        foreach ($results as $result) {
            $title = (string)($result['title'] ?? $result['original_title'] ?? '');
            if ($wantedYear === '' && slugify($title) === $slug) {
                return (int)($result['id'] ?? 0);
            }
        }

        return (int)($results[0]['id'] ?? 0);
    }

    private function certification(array $results): string
    {
        foreach (['GB', 'US'] as $countryCode) {
            foreach ($results as $country) {
                if (($country['iso_3166_1'] ?? '') !== $countryCode) continue;
                foreach (($country['release_dates'] ?? []) as $release) {
                    $cert = trim((string)($release['certification'] ?? ''));
                    if ($cert !== '') return $cert;
                }
            }
        }
        return '';
    }

    private function tmdbCollectionMovies(array $movie): array
    {
        $collection = $movie['belongs_to_collection'] ?? null;
        $collectionId = is_array($collection) ? (int)($collection['id'] ?? 0) : 0;
        if ($collectionId <= 0) return [];

        try {
            $collectionData = (new TmdbClient())->collection($collectionId);
        } catch (\Throwable) {
            return [];
        }

        $collectionName = (string)($collectionData['name'] ?? ($collection['name'] ?? 'Collection'));
        $collectionBackdrop = $collectionData['backdrop_path'] ?? ($collection['backdrop_path'] ?? null);
        $parts = $collectionData['parts'] ?? [];

        usort($parts, static function (array $a, array $b): int {
            return strcmp((string)($a['release_date'] ?? ''), (string)($b['release_date'] ?? ''));
        });

        $movies = [];
        foreach ($parts as $part) {
            $item = $this->normaliseTmdbSummary($part, 'movie');
            $item['collection_id'] = $collectionId;
            $item['collection_name'] = $collectionName;
            $item['collection_backdrop_path'] = $collectionBackdrop;
            $item['import_status'] = 'full';
            if (!is_released_media($item)) continue;
            $movies[] = $item;
        }

        return $movies;
    }

    public function tv(array $params): string
    {
        try {
            $tv = $this->liveTv((string)$params['slug']);
            if (!$tv) $tv = $this->repo->bySlug('tv', $params['slug']);
            if (!$tv) $tv = $this->autoImport('tv', $params['slug']);
            if (!$tv) return $this->notFound();
            if (($tv['import_status'] ?? '') !== 'full') $tv = $this->importer->ensureFull($tv, 'tv');
        } catch (\Throwable) {
            return $this->fetchingPage('tv', (string)$params['slug'], 'This TV show is being fetched from TMDB. Please wait...');
        }
        if (!is_released_media($tv)) return $this->notFound();
        $siteName = site_name();

        return View::render('pages/tv', [
            'title' => ($tv['title'] ?? 'TV Show') . ' | TV Show',
            'metaDescription' => meta_excerpt($tv['overview'] ?? ('Explore episodes, seasons, cast, ratings, and recommendations for ' . ($tv['title'] ?? 'this TV show') . '.')),
            'ogTitle' => ($tv['title'] ?? 'TV Show') . ' | ' . $siteName,
            'ogDescription' => meta_excerpt($tv['overview'] ?? ''),
            'ogType' => 'video.tv_show',
            'ogImage' => meta_image($tv['backdrop_path'] ?? ($tv['poster_path'] ?? null)),
            'canonicalUrl' => absolute_url('tv/' . ($tv['slug'] ?? '')),
            'item' => $tv,
            'related' => $this->tmdbRecommendations($tv, 'tv'),
        ]);
    }

    private function liveTv(string $slug): ?array
    {
        $tmdbId = isset($_GET['tmdb_id']) && ctype_digit((string)$_GET['tmdb_id']) ? (int)$_GET['tmdb_id'] : 0;
        $tmdb = new TmdbClient();
        if ($tmdbId <= 0) {
            $tmdbId = $this->resolveTvIdFromSlug($slug, $tmdb);
        }
        if ($tmdbId <= 0) return null;

        $tv = $tmdb->tv($tmdbId);
        if (!$tv) {
            return null;
        }

        $title = (string)($tv['name'] ?? $tv['original_name'] ?? $slug);
        $tv['tmdb_id'] = (int)($tv['id'] ?? $tmdbId);
        $tv['slug'] = slugify($title);
        $tv['title'] = $title;
        $tv['media_type'] = 'tv';
        $tv['genres'] = array_values(array_map(static fn(array $genre): string => (string)($genre['name'] ?? ''), $tv['genres'] ?? []));
        $tv['cast'] = array_slice($tv['credits']['cast'] ?? [], 0, 12);
        $tv['crew'] = $this->creditCrew($tv['credits']['crew'] ?? [], $tv['created_by'] ?? []);
        $tv['imdb_id'] = $tv['external_ids']['imdb_id'] ?? '';
        $tv['age_rating'] = $this->contentRating($tv['content_ratings']['results'] ?? []);
        $tv['import_status'] = 'full';

        foreach ($tv['cast'] as &$actor) {
            $actor['media_type'] = 'person';
            $actor['tmdb_id'] = $actor['id'] ?? null;
            $actor['slug'] = slugify((string)($actor['name'] ?? 'actor'));
        }
        unset($actor);

        return $tv;
    }

    private function resolveTvIdFromSlug(string $slug, ?TmdbClient $tmdb = null): int
    {
        $query = trim(str_replace('-', ' ', $slug));
        if ($query === '') return 0;

        $tmdb ??= new TmdbClient();
        try {
            $results = $tmdb->searchTv($query, 1)['results'] ?? [];
        } catch (\Throwable) {
            return 0;
        }

        foreach ($results as $result) {
            $title = (string)($result['name'] ?? $result['original_name'] ?? '');
            if (slugify($title) === $slug) {
                return (int)($result['id'] ?? 0);
            }
        }

        return (int)($results[0]['id'] ?? 0);
    }

    private function contentRating(array $results): string
    {
        foreach (['GB', 'US'] as $countryCode) {
            foreach ($results as $country) {
                if (($country['iso_3166_1'] ?? '') !== $countryCode) continue;
                $rating = trim((string)($country['rating'] ?? ''));
                if ($rating !== '') return $rating;
            }
        }
        return '';
    }

    public function season(array $params): string
    {
        $tv = $this->liveTv((string)$params['slug']);
        if (!$tv) $tv = $this->repo->bySlug('tv', $params['slug']);
        if (!$tv) $tv = $this->autoImport('tv', $params['slug']);
        if (!$tv) return $this->notFound();
        if (($tv['import_status'] ?? '') !== 'full') $tv = $this->importer->ensureFull($tv, 'tv');
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
            'ogTitle' => $tv['title'] . ' - Season ' . $seasonNumber . ' | ' . site_name(),
            'ogDescription' => meta_excerpt($season['overview'] ?? ($tv['overview'] ?? '')),
            'ogType' => 'video.tv_show',
            'ogImage' => meta_image($season['poster_path'] ?? ($tv['backdrop_path'] ?? ($tv['poster_path'] ?? null))),
            'canonicalUrl' => absolute_url('tv/' . ($tv['slug'] ?? '') . '/s' . str_pad((string)$seasonNumber, 2, '0', STR_PAD_LEFT)),
            'show' => $tv,
            'season' => $season,
            'seasonNumber' => $seasonNumber,
            'related' => $this->tmdbRecommendations($tv, 'tv'),
        ]);
    }

    public function episode(array $params): string
    {
        $tv = $this->liveTv((string)$params['slug']);
        if (!$tv) $tv = $this->repo->bySlug('tv', $params['slug']);
        if (!$tv) $tv = $this->autoImport('tv', $params['slug']);
        if (!$tv) return $this->notFound();
        if (($tv['import_status'] ?? '') !== 'full') $tv = $this->importer->ensureFull($tv, 'tv');
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
            'related' => $this->tmdbRecommendations($tv, 'tv'),
        ]);
    }

    private function tmdbRecommendations(array $current, string $type, int $limit = 12): array
    {
        $tmdbId = (int)($current['tmdb_id'] ?? $current['id'] ?? 0);
        if ($tmdbId <= 0) return [];

        try {
            $tmdb = new TmdbClient();
            $response = $type === 'movie'
                ? $tmdb->movieRecommendations($tmdbId)
                : $tmdb->tvRecommendations($tmdbId);
            $items = $response['results'] ?? [];

            if (!$items) {
                $response = $type === 'movie'
                    ? $tmdb->movieSimilar($tmdbId)
                    : $tmdb->tvSimilar($tmdbId);
                $items = $response['results'] ?? [];
            }
        } catch (\Throwable) {
            return [];
        }

        $normalised = [];
        foreach ($items as $item) {
            $candidate = $this->normaliseTmdbSummary($item, $type);
            if (!has_media_poster($candidate) || !is_released_media($candidate)) continue;
            $normalised[] = $candidate;
            if (count($normalised) >= $limit) break;
        }

        return $normalised;
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
                if (!LiveCatalog::waitUntilRecordReadable('movies', $finalSlug)) {
                    return ['ok' => false, 'ready' => false, 'message' => 'The movie is still being fetched from TMDB. Please try again.'];
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
                if (!LiveCatalog::waitUntilRecordReadable('tv', $finalSlug)) {
                    return ['ok' => false, 'ready' => false, 'message' => 'The TV show is still being fetched from TMDB. Please try again.'];
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
                if (!LiveCatalog::waitUntilRecordReadable('people', $finalSlug)) {
                    return ['ok' => false, 'ready' => false, 'message' => 'The actor is still being fetched from TMDB. Please try again.'];
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
        $items = $this->liveSearchItems($query, 'all', 1, 6)['items'];
        $results = [];

        foreach ($items as $item) {
            $bucket = (string)($item['_bucket'] ?? '');
            $type = $bucket === 'people' ? 'person' : (($item['media_type'] ?? '') === 'tv' || $bucket === 'tv' ? 'tv' : 'movie');
            $title = (string)($item['title'] ?? $item['name'] ?? 'Untitled');
            $slug = media_slug($item + ['title' => $title], $type);
            $date = media_release_date($item);
            $year = format_year($date);
            $rating = $type === 'person' ? '' : (round((float)($item['vote_average'] ?? 0), 1) ?: '');
            $tmdbId = (int)($item['tmdb_id'] ?? $item['id'] ?? 0);
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
                'tmdb_id' => $tmdbId ?: null,
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

    public function upcomingTrailer(array $params): string
    {
        $type = (string)($_GET['type'] ?? '');
        $tmdbId = (int)($_GET['id'] ?? 0);
        if (!in_array($type, ['movie', 'tv'], true) || $tmdbId < 1) {
            return $this->jsonResponse(['ok' => false, 'trailer' => null]);
        }

        try {
            $tmdb = new TmdbClient();
            $videos = $type === 'tv' ? $tmdb->tvVideos($tmdbId) : $tmdb->movieVideos($tmdbId);
        } catch (\Throwable) {
            return $this->jsonResponse(['ok' => false, 'trailer' => null]);
        }

        $trailer = $this->bestYoutubeTrailer($videos);

        return $this->jsonResponse([
            'ok' => $trailer !== null,
            'trailer' => $trailer,
        ]);
    }

    private function bestYoutubeTrailer(array $videos): ?array
    {
        $candidates = array_values(array_filter($videos, static function (array $video): bool {
            return strtolower((string)($video['site'] ?? '')) === 'youtube'
                && trim((string)($video['key'] ?? '')) !== ''
                && in_array((string)($video['type'] ?? ''), ['Trailer', 'Teaser', 'Clip'], true);
        }));

        if (!$candidates) return null;

        usort($candidates, static function (array $a, array $b): int {
            $rank = static function (array $video): int {
                $official = !empty($video['official']) ? 0 : 10;
                return $official + match ((string)($video['type'] ?? '')) {
                    'Trailer' => 0,
                    'Teaser' => 1,
                    default => 2,
                };
            };

            $rankCompare = $rank($a) <=> $rank($b);
            if ($rankCompare !== 0) return $rankCompare;

            return strcmp((string)($b['published_at'] ?? ''), (string)($a['published_at'] ?? ''));
        });

        $video = $candidates[0];
        $key = trim((string)($video['key'] ?? ''));
        if ($key === '') return null;

        return [
            'name' => (string)($video['name'] ?? 'Trailer'),
            'key' => $key,
            'embed_url' => 'https://www.youtube-nocookie.com/embed/' . rawurlencode($key) . '?rel=0&modestbranding=1',
            'watch_url' => 'https://www.youtube.com/watch?v=' . rawurlencode($key),
        ];
    }

    private function jsonResponse(array $payload): string
    {
        header('Content-Type: application/json; charset=utf-8');
        return (string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }


    public function comingThisYear(array $params): string
    {
        $year = (int)(new \DateTimeImmutable('today'))->format('Y');
        // Coming This Year is the one place future-dated movies/TV should still be visible.
        // Normal listings, search, detail pages, carousels, collections, and episodes remain released-only.
        $movies = [];
        $tvShows = [];
        $today = new \DateTimeImmutable('today');
        $start = max($today->modify('+1 day')->format('Y-m-d'), sprintf('%d-01-01', $year));
        $end = sprintf('%d-12-31', $year);
        if ($start <= $end) {
            try {
                $tmdb = new TmdbClient();
                $movies = $this->collectComingThisYear($tmdb, 'movie', $start, $end, $year);
                $tvShows = $this->collectComingThisYear($tmdb, 'tv', $start, $end, $year);
            } catch (\Throwable) {
                $movies = [];
                $tvShows = [];
            }
        }

        $siteName = site_name();

        return View::render('pages/coming', [
            'title' => 'Coming This Year',
            'metaDescription' => 'Browse movies and TV shows coming later this year, grouped into tabs with quick pagination.',
            'ogTitle' => 'Coming This Year | ' . $siteName,
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
        $sort = (string)($_GET['sort'] ?? 'relevance');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(48, max(6, (int)($_GET['per_page'] ?? 24)));
        $hasLocalFilters = $genre !== '' || $year !== '' || $score !== '';

        $pagination = $this->liveSearchItems($query, $type, $page, $perPage, $hasLocalFilters);
        $filteredItems = $this->filterLiveItems($pagination['items'], $genre, $year, $score);
        $filteredItems = $this->sortItems($filteredItems, $sort);

        if ($hasLocalFilters) {
            $pagination['total'] = count($filteredItems);
            $pagination['pages'] = max(1, (int)ceil($pagination['total'] / $perPage));
        }

        $pagination['items'] = $filteredItems;
        $pagination['items'] = array_slice($pagination['items'], ($page - 1) * $perPage, $perPage);

        $siteName = site_name();

        return View::render('pages/search', [
            'title' => $query ? 'Search: ' . $query : 'Discover Movies and TV',
            'metaDescription' => $query ? meta_excerpt('Search results for ' . $query . ' across movies, TV shows, and actors.') : 'Discover movies and TV shows by title, genre, age rating, year, and sort order.',
            'ogTitle' => $query ? 'Search: ' . $query . ' | ' . $siteName : 'Discover Movies and TV | ' . $siteName,
            'ogDescription' => $query ? meta_excerpt('Search results for ' . $query . ' on ' . $siteName . '.') : 'Find movies and TV shows with advanced filters.',
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
        $perPage = 20;

        $pagination = $this->liveListingItems($type, $page, $genre, $year, $score, $sort, $rating);

        $siteName = site_name();

        return View::render('pages/listing', [
            'title' => $type === 'movie' ? 'All Movies' : 'All TV Shows',
            'metaDescription' => $type === 'movie' ? 'Browse live movie results from TMDB with filters and sorting.' : 'Browse live TV results from TMDB with filters and sorting.',
            'ogTitle' => $type === 'movie' ? 'All Movies | ' . $siteName : 'All TV Shows | ' . $siteName,
            'ogDescription' => $type === 'movie' ? 'Browse all movies on ' . $siteName . '.' : 'Browse all TV shows on ' . $siteName . '.',
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

    private function liveSearchItems(string $query, string $type, int $page, int $perPage, bool $expandForLocalFilters = false): array
    {
        $tmdb = new TmdbClient();
        $items = [];
        $total = 0;
        $totalPages = 1;
        $totalsByType = [];
        $pagesByType = [];
        $tmdbPages = $this->tmdbPageWindow($page, $perPage, $expandForLocalFilters);

        try {
            $requests = [];
            if ($query === '') {
                $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
                foreach ($tmdbPages as $tmdbPage) {
                    if ($type !== 'tv' && $type !== 'person') {
                        $requests['movie:' . $tmdbPage] = [
                            'path' => '/discover/movie',
                            'query' => [
                                'include_adult' => 'false',
                                'include_video' => 'false',
                                'primary_release_date.lte' => $today,
                                'sort_by' => 'popularity.desc',
                                'page' => $tmdbPage,
                            ],
                        ];
                    }
                    if ($type !== 'movie' && $type !== 'person') {
                        $requests['tv:' . $tmdbPage] = [
                            'path' => '/discover/tv',
                            'query' => [
                                'include_adult' => 'false',
                                'first_air_date.lte' => $today,
                                'sort_by' => 'popularity.desc',
                                'page' => $tmdbPage,
                            ],
                        ];
                    }
                }
            } else {
                foreach ($tmdbPages as $tmdbPage) {
                    if ($type !== 'tv' && $type !== 'person') $requests['movie:' . $tmdbPage] = ['path' => '/search/movie', 'query' => ['query' => $query, 'include_adult' => 'false', 'page' => $tmdbPage]];
                    if ($type !== 'movie' && $type !== 'person') $requests['tv:' . $tmdbPage] = ['path' => '/search/tv', 'query' => ['query' => $query, 'include_adult' => 'false', 'page' => $tmdbPage]];
                    if ($type === 'person') $requests['person:' . $tmdbPage] = ['path' => '/search/person', 'query' => ['query' => $query, 'include_adult' => 'false', 'page' => $tmdbPage]];
                }
            }

            foreach ($tmdb->getBatch($requests, 16) as $key => $response) {
                $responseType = explode(':', (string)$key, 2)[0] ?: 'movie';
                $totalsByType[$responseType] = max((int)($totalsByType[$responseType] ?? 0), (int)($response['total_results'] ?? 0));
                $pagesByType[$responseType] = max((int)($pagesByType[$responseType] ?? 1), (int)($response['total_pages'] ?? 1));
                foreach (($response['results'] ?? []) as $item) {
                    $items[] = $this->normaliseTmdbSummary($item, $responseType);
                }
            }
        } catch (\Throwable) {
            $items = [];
            $total = 0;
        }

        $items = array_values(array_filter(
            $this->uniqueTmdbItems($items),
            static fn(array $item): bool => ($item['media_type'] ?? '') === 'person' || is_released_media($item)
        ));
        $total = $query === '' ? array_sum($totalsByType) : count($items);
        $totalPages = $query === ''
            ? ($pagesByType ? max($pagesByType) : 1)
            : max(1, (int)ceil($total / max(1, $perPage)));

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, min(500, $totalPages ?: (int)ceil(count($items) / max(1, $perPage)))),
        ];
    }

    private function liveListingItems(string $type, int $page, string $genre = '', string $year = '', string $score = '', string $sort = 'title_asc', string $rating = ''): array
    {
        $tmdb = new TmdbClient();

        try {
            $filters = $this->listingTmdbFilters($type, $page, $genre, $year, $score, $sort, $rating);
            $response = $type === 'movie' ? $tmdb->discoverMovies($filters) : $tmdb->discoverTv($filters);
            $items = array_map(fn(array $item): array => $this->normaliseTmdbSummary($item, $type), $response['results'] ?? []);
            $items = $this->uniqueTmdbItems($items);
            $total = (int)($response['total_results'] ?? count($items));
            $pages = max(1, min(500, (int)($response['total_pages'] ?? 1)));
        } catch (\Throwable) {
            $items = [];
            $total = 0;
            $pages = 1;
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    private function listingTmdbFilters(string $type, int $page, string $genre, string $year, string $score, string $sort, string $rating): array
    {
        $filters = [
            'page' => min(500, max(1, $page)),
            'sort_by' => $this->tmdbSort($type, $sort),
        ];
        $filters[$type === 'movie' ? 'primary_release_date.lte' : 'first_air_date.lte'] = (new \DateTimeImmutable('today'))->format('Y-m-d');

        if ($genre !== '') {
            $genreId = $this->genreId($type, $genre);
            if ($genreId > 0) $filters['with_genres'] = (string)$genreId;
        }

        if ($year !== '' && ctype_digit($year)) {
            $filters[$type === 'movie' ? 'primary_release_year' : 'first_air_date_year'] = $year;
        }

        if ($score !== '' && is_numeric($score)) {
            $filters['vote_average.gte'] = (string)max(0, min(10, (float)$score));
            $filters['vote_count.gte'] = '10';
        }

        if ($type === 'movie' && $rating !== '') {
            $filters['certification_country'] = 'GB';
            $filters['certification'] = $rating;
        }

        return $filters;
    }

    private function tmdbSort(string $type, string $sort): string
    {
        return match ($sort) {
            'title_desc' => $type === 'movie' ? 'title.desc' : 'name.desc',
            'title_asc' => $type === 'movie' ? 'title.asc' : 'name.asc',
            'date_asc' => $type === 'movie' ? 'primary_release_date.asc' : 'first_air_date.asc',
            'date_desc' => $type === 'movie' ? 'primary_release_date.desc' : 'first_air_date.desc',
            'rating_asc' => 'vote_average.asc',
            'rating_desc' => 'vote_average.desc',
            default => 'popularity.desc',
        };
    }

    private function genreId(string $type, string $name): int
    {
        $name = strtolower(trim($name));
        if ($name === '') return 0;

        try {
            $genres = $type === 'movie' ? (new TmdbClient())->movieGenres() : (new TmdbClient())->tvGenres();
        } catch (\Throwable) {
            return 0;
        }

        foreach ($genres as $genre) {
            if (strtolower((string)($genre['name'] ?? '')) === $name) {
                return (int)($genre['id'] ?? 0);
            }
        }

        return 0;
    }

    private function tmdbPageWindow(int $page, int $perPage, bool $expandForLocalFilters = false): array
    {
        $desiredItems = $expandForLocalFilters
            ? max(500, $perPage * 20, ($page + 1) * $perPage * 6)
            : max(240, $perPage * 10, ($page + 1) * $perPage);
        $pagesNeeded = (int)ceil($desiredItems / 20);
        $lastPage = min(500, $pagesNeeded);

        return range(1, $lastPage);
    }

    private function uniqueTmdbItems(array $items): array
    {
        $seen = [];
        $unique = [];
        foreach ($items as $item) {
            $key = (string)($item['media_type'] ?? 'media') . ':' . (string)($item['tmdb_id'] ?? $item['id'] ?? $item['slug'] ?? '');
            if ($key === ':' || isset($seen[$key])) continue;
            $seen[$key] = true;
            $unique[] = $item;
        }
        return $unique;
    }

    private function normaliseTmdbSummary(array $item, string $type): array
    {
        $id = (int)($item['id'] ?? 0);
        $title = (string)($type === 'person' ? ($item['name'] ?? 'Untitled') : ($item['title'] ?? $item['name'] ?? 'Untitled'));
        $releaseDate = (string)($type === 'tv' ? ($item['first_air_date'] ?? '') : ($item['release_date'] ?? ''));

        $item['tmdb_id'] = $id;
        $item['media_type'] = $type;
        $item['title'] = $title;
        $item['name'] = $item['name'] ?? $title;
        $item['release_date'] = $releaseDate;
        $item['slug'] = $type === 'movie' ? movie_slug($title, $releaseDate) : slugify($title);
        if ($type === 'tv') $item['first_air_date'] = $releaseDate;
        $item['genres'] = $type === 'person' ? [] : $this->genreNames($type, $item['genre_ids'] ?? []);
        $item['import_status'] = 'full';

        return $item;
    }

    private function genreNames(string $type, array $ids): array
    {
        static $maps = [];
        if (!isset($maps[$type])) {
            try {
                $genres = $type === 'movie' ? (new TmdbClient())->movieGenres() : (new TmdbClient())->tvGenres();
                $maps[$type] = [];
                foreach ($genres as $genre) $maps[$type][(int)($genre['id'] ?? 0)] = (string)($genre['name'] ?? '');
            } catch (\Throwable) {
                $maps[$type] = [];
            }
        }

        $names = [];
        foreach ($ids as $id) {
            $name = $maps[$type][(int)$id] ?? '';
            if ($name !== '') $names[] = $name;
        }
        return $names;
    }

    private function filterLiveItems(array $items, string $genre = '', string $year = '', string $score = ''): array
    {
        return array_values(array_filter($items, static function (array $item) use ($genre, $year, $score): bool {
            $genres = $item['genres'] ?? [];
            if ($genre !== '' && !in_array($genre, $genres, true)) return false;

            if (($item['media_type'] ?? '') !== 'person' && !is_released_media($item)) return false;

            $releaseYear = substr(media_release_date($item), 0, 4);
            if ($year !== '' && $releaseYear !== $year) return false;

            $voteAverage = (float)($item['vote_average'] ?? 0);
            if ($score !== '' && ($voteAverage <= 0 || $voteAverage < (float)$score)) return false;

            return true;
        }));
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
            // Keep the page usable if TMDB is temporarily unavailable.
        }
    }

    private function collectComingThisYear(TmdbClient $tmdb, string $type, string $start, string $end, int $year): array
    {
        $cacheKey = $this->comingCacheKey($type, $start, $end);
        $cached = $this->readComingCache($cacheKey);
        if (is_array($cached)) return $cached;

        $firstPage = $type === 'tv'
            ? $tmdb->comingTvThisYear($start, $end, 1)
            : $tmdb->comingMoviesThisYear($start, $end, 1);

        $items = $firstPage['results'] ?? [];
        $totalPages = min(500, max(1, (int)($firstPage['total_pages'] ?? 1)));

        if ($totalPages > 1) {
            $requests = [];
            for ($page = 2; $page <= $totalPages; $page++) {
                $requests[(string)$page] = $type === 'tv'
                    ? TmdbClient::comingTvThisYearRequest($start, $end, $page)
                    : TmdbClient::comingMoviesThisYearRequest($start, $end, $page);
            }

            foreach ($tmdb->getBatch($requests, 8) as $response) {
                foreach (($response['results'] ?? []) as $item) {
                    if (is_array($item)) $items[] = $item;
                }
            }
        }

        $items = array_map(fn(array $item): array => $this->normaliseTmdbSummary($item, $type), $items);

        $items = $this->comingItems($this->uniqueTmdbItems($items), $type, $year);
        $this->writeComingCache($cacheKey, $items);

        return $items;
    }

    private function comingCacheKey(string $type, string $start, string $end): string
    {
        return 'coming-' . preg_replace('/[^a-z0-9_-]/i', '-', $type . '-' . $start . '-' . $end) . '.json';
    }

    private function readComingCache(string $key): ?array
    {
        $file = $this->comingCacheFile($key);
        if ($file === null || !is_file($file)) return null;
        if ((time() - (int)filemtime($file)) > 21600) return null;

        $payload = json_decode((string)file_get_contents($file), true);
        if (!is_array($payload)) return null;

        return $payload;
    }

    private function writeComingCache(string $key, array $items): void
    {
        $file = $this->comingCacheFile($key, true);
        if ($file === null) return;

        @file_put_contents($file, (string)json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function comingCacheFile(string $key, bool $create = false): ?string
    {
        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'streamhive-cache';
        if (!is_dir($dir)) {
            if (!$create || !@mkdir($dir, 0775, true)) return null;
        }
        if (!is_writable($dir)) return null;

        return $dir . DIRECTORY_SEPARATOR . $key;
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
            // Listing pages degrade gracefully if TMDB is unavailable.
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
            // Search degrades gracefully if TMDB is unavailable.
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
        $items = LiveCatalog::relatedCandidates($bucket, $currentGenres, $currentId ?: $currentTmdbId, $currentSlug, max(80, $limit * 12));
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
        if ($sort === 'relevance') {
            return $items;
        }

        usort($items, function (array $a, array $b) use ($sort): int {
            return match ($sort) {
                'title_desc' => strnatcasecmp($b['title'] ?? $b['name'] ?? '', $a['title'] ?? $a['name'] ?? ''),
                'popularity_desc' => ((float)($b['popularity'] ?? 0)) <=> ((float)($a['popularity'] ?? 0)),
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
        $genres = [];
        $tmdb = new TmdbClient();

        try {
            if ($type !== 'tv') {
                foreach ($tmdb->movieGenres() as $genre) $genres[(string)($genre['name'] ?? '')] = (string)($genre['name'] ?? '');
            }
            if ($type !== 'movie') {
                foreach ($tmdb->tvGenres() as $genre) $genres[(string)($genre['name'] ?? '')] = (string)($genre['name'] ?? '');
            }
        } catch (\Throwable) {
            return [];
        }

        unset($genres['']);
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
        return ['U', 'PG', '12', '12A', '15', '18'];
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
            'title' => 'Fetching content | ' . site_name(),
            'robots' => 'noindex, follow',
            'metaDescription' => 'This page is being fetched from TMDB.',
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
