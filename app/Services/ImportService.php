<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Repository;

final class ImportService
{
    public function __construct(private readonly Repository $repo = new Repository(), private readonly TmdbClient $tmdb = new TmdbClient()) {}

    public function importInput(string $input, ?string $forceType = null): array
    {
        $parsed = ImdbParser::parse($input);
        if ($parsed['primary']) {
            $found = $this->tmdb->findByImdb($parsed['primary']);
            if (!empty($found['movie_results'])) return $this->importMovie((int)$found['movie_results'][0]['id']);
            if (!empty($found['tv_results'])) return $this->importTv((int)$found['tv_results'][0]['id']);
            if (!empty($found['person_results'])) return $this->importPerson((int)$found['person_results'][0]['id']);
        }
        if (preg_match('/^tmdb:(movie|tv|person):(\d+)$/i', trim($input), $m)) {
            return match (strtolower($m[1])) {
                'movie' => $this->importMovie((int)$m[2]),
                'tv' => $this->importTv((int)$m[2]),
                'person' => $this->importPerson((int)$m[2]),
            };
        }
        throw new \InvalidArgumentException('No supported IMDb/TMDB ID found. Try tt1234567, nm1234567, or tmdb:movie:123.');
    }

    public function importMovie(int $id): array
    {
        $data = $this->tmdb->movie($id);
        $record = $this->normalizeMedia($data, 'movie');
        $record['import_status'] = 'full';
        $people = $this->castRecordsToUpsert($data['credits']['cast'] ?? [], $record);

        SqliteStore::transaction(function () use ($record, $people): void {
            $this->repo->movies->upsert($record);
            if ($people) $this->repo->people->upsertMany($people);
        });

        return $record;
    }

    public function importTv(int $id): array
    {
        $data = $this->tmdb->tv($id);
        $record = $this->normalizeMedia($data, 'tv');
        $record['import_status'] = 'full';
        $people = $this->castRecordsToUpsert($data['credits']['cast'] ?? [], $record);

        SqliteStore::transaction(function () use ($record, $people): void {
            $this->repo->tv->upsert($record);
            if ($people) $this->repo->people->upsertMany($people);
        });

        return $record;
    }

    public function importPerson(int $id): array
    {
        $data = $this->tmdb->person($id);
        $person = $this->normalizePerson($data);
        $media = $this->syncPersonCreditMedia($person);

        SqliteStore::transaction(function () use ($person, $media): void {
            if (!empty($media['movies'])) $this->repo->movies->upsertMany($media['movies']);
            if (!empty($media['tv'])) $this->repo->tv->upsertMany($media['tv']);
            $this->repo->people->upsert($person);
        });

        return $person;
    }



    public function prefetchResults(array $results, string $type, int $limit = 20): int
    {
        $results = array_values(array_filter(array_slice($results, 0, max(0, $limit)), static fn(array $row): bool => !empty($row['id'])));
        if (!$results) return 0;

        $store = $type === 'person' ? $this->repo->people : ($type === 'movie' ? $this->repo->movies : $this->repo->tv);
        $fullIds = $store->idsWithStatus(array_map(static fn(array $row): string => (string)$row['id'], $results), 'full');

        $records = [];
        foreach ($results as $result) {
            if (isset($fullIds[(string)$result['id']])) continue;
            try {
                $records[] = $type === 'person'
                    ? $this->normalizeLightPerson($result)
                    : $this->normalizeLightMedia($result, $type);
            } catch (\Throwable) {
                continue;
            }
        }

        return $records ? $store->upsertMany($records) : 0;
    }

    public function prefetchResult(array $result, string $type): ?array
    {
        $id = (int)($result['id'] ?? 0);
        if ($id <= 0) return null;

        $store = $type === 'person' ? $this->repo->people : ($type === 'movie' ? $this->repo->movies : $this->repo->tv);
        $existing = $store->find($id);
        if (($existing['import_status'] ?? '') === 'full') return $existing;

        $record = $type === 'person'
            ? $this->normalizeLightPerson($result)
            : $this->normalizeLightMedia($result, $type);
        $store->upsert($record);
        return $record;
    }

    public function ensureFull(array $record, string $type): array
    {
        if ($type === 'person') {
            $credits = array_values($record['credits'] ?? []);
            $creditsHaveDates = !$credits || array_reduce($credits, static fn(bool $ok, array $credit): bool => $ok && array_key_exists('release_date', $credit), true);
            if (($record['import_status'] ?? '') === 'full' && array_key_exists('biography', $record) && $creditsHaveDates) return $record;
            $id = (int)($record['tmdb_id'] ?? $record['id'] ?? 0);
            return $id > 0 ? $this->importPerson($id) : $record;
        }

        if (($record['import_status'] ?? '') === 'full' && !empty($record['cast'])) return $record;
        $id = (int)($record['tmdb_id'] ?? $record['id'] ?? 0);
        if ($id <= 0) return $record;
        return $type === 'movie' ? $this->importMovie($id) : $this->importTv($id);
    }

    public function prefetchPopular(string $type, int $page = 1, int $limit = 20): int
    {
        $response = $type === 'movie' ? $this->tmdb->popularMovies($page) : $this->tmdb->popularTv($page);
        return $this->prefetchResults($response['results'] ?? [], $type, $limit);
    }

    public function prefetchSearch(string $query, string $type, int $page = 1, int $limit = 20): int
    {
        if (trim($query) === '') return 0;
        $response = match ($type) {
            'movie' => $this->tmdb->searchMovie($query, $page),
            'tv' => $this->tmdb->searchTv($query, $page),
            'person' => $this->tmdb->searchPerson($query, $page),
            default => ['results' => []],
        };
        return $this->prefetchResults($response['results'] ?? [], $type, $limit);
    }

    public function importMovieFromSlug(string $slug, ?int $tmdbId = null): ?array
    {
        if ($tmdbId !== null && $tmdbId > 0) {
            return $this->importMovie($tmdbId);
        }

        $match = $this->findBestSearchResult($this->tmdb->searchMovie($this->slugToQuery($slug))['results'] ?? [], $slug, 'movie');
        return $match ? $this->importMovie((int)$match['id']) : null;
    }

    public function importTvFromSlug(string $slug, ?int $tmdbId = null): ?array
    {
        if ($tmdbId !== null && $tmdbId > 0) {
            return $this->importTv($tmdbId);
        }

        $match = $this->findBestSearchResult($this->tmdb->searchTv($this->slugToQuery($slug))['results'] ?? [], $slug, 'tv');
        return $match ? $this->importTv((int)$match['id']) : null;
    }

    public function importPersonFromSlug(string $slug, ?int $tmdbId = null): ?array
    {
        if ($tmdbId !== null && $tmdbId > 0) {
            return $this->importPerson($tmdbId);
        }

        $match = $this->findBestSearchResult($this->tmdb->searchPerson($this->slugToQuery($slug))['results'] ?? [], $slug, 'person');
        return $match ? $this->importPerson((int)$match['id']) : null;
    }

    private function slugToQuery(string $slug): string
    {
        return trim(str_replace('-', ' ', urldecode($slug)));
    }

    private function findBestSearchResult(array $results, string $slug, string $type): ?array
    {
        if (!$results) return null;

        foreach ($results as $result) {
            $title = $type === 'movie' ? ($result['title'] ?? $result['original_title'] ?? '') : ($result['name'] ?? $result['original_name'] ?? '');
            if ($title && slugify($title) === $slug) return $result;
        }

        return $results[0] ?? null;
    }

    private function castRecordsToUpsert(array $cast, array $media): array
    {
        $records = [];
        foreach (array_slice($cast, 0, 20) as $member) {
            if (empty($member['id'])) continue;

            $person = $this->repo->people->find((int)$member['id']);
            if (!$person) {
                try { $person = $this->normalizePerson($this->tmdb->person((int)$member['id'])); }
                catch (\Throwable) { continue; }
            }

            $person['credits'] = $person['credits'] ?? [];
            $key = $media['media_type'] . ':' . $media['id'];
            $person['credits'][$key] = [
                'id' => $media['id'],
                'media_type' => $media['media_type'],
                'title' => $media['title'],
                'slug' => $media['slug'],
                'poster_path' => $media['poster_path'] ?? null,
                'character' => $member['character'] ?? null,
                'release_date' => $media['release_date'] ?? null,
            ];
            $person['known_for'] = array_values($person['credits']);
            $records[] = $person;
        }
        return $records;
    }


    private function normalizeLightPerson(array $data): array
    {
        $name = $data['name'] ?? 'Unknown';
        $knownFor = [];
        foreach (($data['known_for'] ?? []) as $credit) {
            $type = $credit['media_type'] ?? null;
            if (!in_array($type, ['movie', 'tv'], true)) continue;
            $title = $type === 'movie' ? ($credit['title'] ?? '') : ($credit['name'] ?? '');
            if ($title === '') continue;
            $knownFor[] = [
                'id' => $credit['id'] ?? null,
                'media_type' => $type,
                'title' => $title,
                'slug' => $this->uniqueSlug($title, $type, (int)($credit['id'] ?? 0)),
                'poster_path' => $credit['poster_path'] ?? null,
                'release_date' => $type === 'movie' ? ($credit['release_date'] ?? null) : ($credit['first_air_date'] ?? null),
            ];
        }

        return [
            'id' => (int)$data['id'],
            'tmdb_id' => (int)$data['id'],
            'imdb_id' => null,
            'media_type' => 'person',
            'name' => $name,
            'slug' => $this->uniqueSlug($name, 'person', (int)$data['id']),
            'biography' => '',
            'birthday' => null,
            'place_of_birth' => null,
            'profile_path' => $data['profile_path'] ?? null,
            'known_for_department' => $data['known_for_department'] ?? null,
            'known_for' => $knownFor,
            'credits' => [],
            'import_status' => 'prefetched',
        ];
    }

    private function normalizeLightMedia(array $data, string $type): array
    {
        $title = $type === 'movie'
            ? ($data['title'] ?? $data['original_title'] ?? 'Untitled')
            : ($data['name'] ?? $data['original_name'] ?? 'Untitled');
        $date = $type === 'movie' ? ($data['release_date'] ?? null) : ($data['first_air_date'] ?? null);
        $genres = $this->genresFromLightResult($data, $type);

        return [
            'id' => (int)$data['id'],
            'tmdb_id' => (int)$data['id'],
            'imdb_id' => null,
            'media_type' => $type,
            'title' => $title,
            'slug' => $this->uniqueSlug($title, $type, (int)$data['id']),
            'overview' => $data['overview'] ?? '',
            'poster_path' => $data['poster_path'] ?? null,
            'backdrop_path' => $data['backdrop_path'] ?? null,
            'release_date' => $date,
            'vote_average' => $data['vote_average'] ?? null,
            'age_rating' => '',
            'runtime' => null,
            'episode_run_time' => [],
            'genres' => $genres,
            'cast' => [],
            'seasons' => [],
            'import_status' => 'prefetched',
        ];
    }

    private function genresFromLightResult(array $data, string $type): array
    {
        if (!empty($data['genres']) && is_array($data['genres'])) {
            return array_values(array_filter(array_map(fn($g) => is_array($g) ? ($g['name'] ?? '') : (string)$g, $data['genres'])));
        }

        $ids = array_map('intval', $data['genre_ids'] ?? []);
        if (!$ids) return [];

        try {
            $genreRows = $type === 'movie' ? $this->tmdb->movieGenres() : $this->tmdb->tvGenres();
        } catch (\Throwable) {
            return [];
        }

        $map = [];
        foreach ($genreRows as $row) {
            if (!empty($row['id']) && !empty($row['name'])) $map[(int)$row['id']] = (string)$row['name'];
        }

        $names = [];
        foreach ($ids as $id) if (isset($map[$id])) $names[] = $map[$id];
        return $names;
    }

    private function normalizeMedia(array $data, string $type): array
    {
        $title = $type === 'movie' ? ($data['title'] ?? $data['original_title'] ?? 'Untitled') : ($data['name'] ?? $data['original_name'] ?? 'Untitled');
        $date = $type === 'movie' ? ($data['release_date'] ?? null) : ($data['first_air_date'] ?? null);
        return [
            'id' => (int)$data['id'], 'tmdb_id' => (int)$data['id'], 'imdb_id' => $data['external_ids']['imdb_id'] ?? null,
            'media_type' => $type, 'title' => $title, 'slug' => $this->uniqueSlug($title, $type, (int)$data['id']), 'overview' => $data['overview'] ?? '',
            'poster_path' => $data['poster_path'] ?? null, 'backdrop_path' => $data['backdrop_path'] ?? null,
            'release_date' => $date, 'vote_average' => $data['vote_average'] ?? null,
            'age_rating' => $this->ageRating($data, $type),
            'runtime' => $type === 'movie' ? ($data['runtime'] ?? null) : null,
            'episode_run_time' => $type === 'tv' ? array_values(array_filter(array_map('intval', $data['episode_run_time'] ?? []), static fn(int $runtime): bool => $runtime > 0)) : [],
            'genres' => array_values(array_filter(array_map(fn($g) => $g['name'] ?? '', $data['genres'] ?? []))),
            'cast' => array_map(fn($c) => ['id'=>$c['id']??null,'name'=>$c['name']??'','slug'=>$this->uniqueSlug((string)($c['name']??''), 'person', (int)($c['id']??0)),'character'=>$c['character']??'','profile_path'=>$c['profile_path']??null], array_slice($data['credits']['cast'] ?? [], 0, 16)),
            'seasons' => $type === 'tv' ? ($data['seasons'] ?? []) : [],
        ];
    }


    private function uniqueSlug(string $title, string $type, int $id = 0): string
    {
        $base = slugify($title);
        if ($base === '') $base = $type . ($id > 0 ? '-' . $id : '');

        $store = $type === 'person' ? $this->repo->people : ($type === 'tv' ? $this->repo->tv : $this->repo->movies);

        if ($id > 0) {
            $existingSlug = $store->slugForId($id);
            if ($existingSlug) return $existingSlug;
        }

        if (!$store->slugExists($base, $id > 0 ? $id : null)) return $base;

        $suffix = 2;
        do {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        } while ($store->slugExists($candidate, $id > 0 ? $id : null));

        return $candidate;
    }

    /**
     * Make sure actor credits have local slugs and prepare lightweight media records.
     * Returns records for one batched SQLite transaction instead of writing each credit one by one.
     */
    private function syncPersonCreditMedia(array &$person): array
    {
        $media = ['movies' => [], 'tv' => []];

        foreach ($person['credits'] ?? [] as $key => $credit) {
            $type = (string)($credit['media_type'] ?? '');
            if (!in_array($type, ['movie', 'tv'], true)) continue;

            $id = (int)($credit['id'] ?? 0);
            if ($id <= 0) continue;

            $store = $type === 'movie' ? $this->repo->movies : $this->repo->tv;
            $existingSlug = $store->slugForId($id);
            if ($existingSlug) {
                $person['credits'][$key]['slug'] = $existingSlug;
                continue;
            }

            $title = (string)($credit['title'] ?? 'Untitled');
            $record = [
                'id' => $id,
                'tmdb_id' => $id,
                'imdb_id' => null,
                'media_type' => $type,
                'title' => $title,
                'slug' => $this->uniqueSlug($title, $type, $id),
                'overview' => '',
                'poster_path' => $credit['poster_path'] ?? null,
                'backdrop_path' => null,
                'release_date' => $credit['release_date'] ?? null,
                'vote_average' => null,
                'age_rating' => '',
                'runtime' => null,
                'episode_run_time' => [],
                'genres' => [],
                'cast' => [],
                'seasons' => [],
                'import_status' => 'prefetched',
            ];
            $media[$type === 'movie' ? 'movies' : 'tv'][] = $record;
            $person['credits'][$key]['slug'] = $record['slug'];
        }

        $person['known_for'] = array_values($person['credits'] ?? []);
        return $media;
    }


    private function ageRating(array $data, string $type): string
    {
        if ($type === 'movie') {
            $countries = ['GB', 'US'];
            foreach ($countries as $country) {
                foreach (($data['release_dates']['results'] ?? []) as $result) {
                    if (($result['iso_3166_1'] ?? '') !== $country) continue;
                    foreach (($result['release_dates'] ?? []) as $release) {
                        $cert = trim((string)($release['certification'] ?? ''));
                        if ($cert !== '') return $this->ukAgeRating($cert);
                    }
                }
            }
            return '';
        }

        foreach (['GB', 'US'] as $country) {
            foreach (($data['content_ratings']['results'] ?? []) as $result) {
                if (($result['iso_3166_1'] ?? '') === $country) {
                    $rating = trim((string)($result['rating'] ?? ''));
                    if ($rating !== '') return $this->ukAgeRating($rating);
                }
            }
        }
        return '';
    }


    private function ukAgeRating(int|string|null $rating): string
    {
        $value = strtoupper(trim((string)$rating));
        if ($value === '' || in_array($value, ['NR','N/R','NOT RATED','UNRATED','TBC','TBD','N/A','NA'], true)) return '';
        $value = str_replace(['_', '.'], ['-', ''], $value);
        $value = preg_replace('/\s+/', '', $value) ?: $value;

        return match ($value) {
            'U', 'G', 'TV-G', 'TV-Y' => 'U',
            'PG', 'TV-PG', 'TV-Y7', 'TV-Y7-FV' => 'PG',
            '12' => '12',
            '12A', 'PG-13' => '12A',
            '15', 'R', 'TV-14', 'M' => '15',
            '18', 'NC-17', 'X', 'TV-MA' => '18',
            default => in_array($value, ['U','PG','12','12A','15','18'], true) ? $value : '',
        };
    }

    private function normalizePerson(array $data): array
    {
        $name = $data['name'] ?? 'Unknown';
        $credits = [];
        foreach (($data['combined_credits']['cast'] ?? []) as $c) {
            $type = $c['media_type'] ?? null;
            if (!in_array($type, ['movie','tv'], true)) continue;
            $title = $type === 'movie' ? ($c['title'] ?? '') : ($c['name'] ?? '');
            if (!$title) continue;
            $credits[$type . ':' . ($c['id'] ?? uniqid())] = ['id'=>$c['id']??null, 'media_type'=>$type, 'title'=>$title, 'slug'=>$this->uniqueSlug($title, $type, (int)($c['id'] ?? 0)), 'poster_path'=>$c['poster_path']??null, 'character'=>$c['character']??null, 'release_date'=>($type === 'movie' ? ($c['release_date'] ?? null) : ($c['first_air_date'] ?? null))];
        }
        return [
            'id' => (int)$data['id'], 'tmdb_id' => (int)$data['id'], 'imdb_id' => $data['external_ids']['imdb_id'] ?? null,
            'media_type' => 'person', 'name' => $name, 'slug' => $this->uniqueSlug($name, 'person', (int)$data['id']), 'biography' => $data['biography'] ?? '', 'birthday' => $data['birthday'] ?? null,
            'place_of_birth' => $data['place_of_birth'] ?? null, 'profile_path' => $data['profile_path'] ?? null,
            'known_for_department' => $data['known_for_department'] ?? null, 'known_for' => array_values($credits), 'credits' => $credits,
            'import_status' => 'full',
        ];
    }
}
