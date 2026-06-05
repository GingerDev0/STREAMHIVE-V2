<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class TmdbClient
{
    private string $base = 'https://api.themoviedb.org/3';

    public function trending(string $type, string $window = 'week'): array { return $this->get("/trending/{$type}/{$window}"); }
    public function recentMovies(): array { return $this->get('/movie/now_playing'); }
    public function recentTv(): array { return $this->get('/tv/on_the_air'); }
    public function popularMovies(int $page = 1): array { return $this->get('/movie/popular', ['page' => max(1, $page)]); }
    public function popularTv(int $page = 1): array { return $this->get('/tv/popular', ['page' => max(1, $page)]); }
    public function topRatedMovies(int $page = 1): array { return $this->get('/movie/top_rated', ['page' => max(1, $page)]); }
    public function topRatedTv(int $page = 1): array { return $this->get('/tv/top_rated', ['page' => max(1, $page)]); }
    public function findByImdb(string $imdbId): array { return $this->get('/find/' . rawurlencode($imdbId), ['external_source' => 'imdb_id']); }
    public function searchMovie(string $query, int $page = 1): array { return $this->get('/search/movie', ['query' => $query, 'include_adult' => 'false', 'page' => max(1, $page)]); }
    public function searchTv(string $query, int $page = 1): array { return $this->get('/search/tv', ['query' => $query, 'include_adult' => 'false', 'page' => max(1, $page)]); }
    public function searchPerson(string $query, int $page = 1): array { return $this->get('/search/person', ['query' => $query, 'include_adult' => 'false', 'page' => max(1, $page)]); }
    public function movie(int $id): array { return $this->get("/movie/{$id}", ['append_to_response' => 'credits,external_ids,videos,images,release_dates']); }
    public function collection(int $id): array { return $this->get("/collection/{$id}"); }
    public function tv(int $id): array { return $this->get("/tv/{$id}", ['append_to_response' => 'credits,external_ids,videos,images,content_ratings']); }
    public function person(int $id): array { return $this->get("/person/{$id}", ['append_to_response' => 'combined_credits,external_ids']); }
    public function season(int $seriesId, int $season): array { return $this->get("/tv/{$seriesId}/season/{$season}"); }
    public function episode(int $seriesId, int $season, int $episode): array { return $this->get("/tv/{$seriesId}/season/{$season}/episode/{$episode}", ['append_to_response' => 'credits,external_ids']); }
    public function movieGenres(): array { return $this->get('/genre/movie/list')['genres'] ?? []; }
    public function tvGenres(): array { return $this->get('/genre/tv/list')['genres'] ?? []; }

    public function comingMoviesThisYear(string $startDate, string $endDate, int $page = 1): array
    {
        return $this->get('/discover/movie', [
            'include_adult' => 'false',
            'include_video' => 'false',
            'primary_release_date.gte' => $startDate,
            'primary_release_date.lte' => $endDate,
            'sort_by' => 'popularity.desc',
            'page' => max(1, $page),
        ]);
    }

    public function comingTvThisYear(string $startDate, string $endDate, int $page = 1): array
    {
        return $this->get('/discover/tv', [
            'include_adult' => 'false',
            'first_air_date.gte' => $startDate,
            'first_air_date.lte' => $endDate,
            'sort_by' => 'popularity.desc',
            'page' => max(1, $page),
        ]);
    }

    private function get(string $path, array $query = []): array
    {
        $query += ['language' => 'en-US'];
        $key = md5($path . '?' . http_build_query($query));
        $cached = $this->cacheGet($key);
        if ($cached !== null) return $cached;

        if ($apiKey = Config::get('TMDB_API_KEY')) $query['api_key'] = $apiKey;
        $url = $this->base . $path . '?' . http_build_query($query);
        $ch = curl_init($url);
        $headers = ['Accept: application/json'];
        if ($token = Config::get('TMDB_BEARER_TOKEN')) $headers[] = 'Authorization: Bearer ' . $token;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status >= 400) {
            throw new \RuntimeException('TMDB request failed: ' . ($error ?: $status));
        }
        $this->cachePut($key, $body, 3600);
        return json_decode($body, true) ?: [];
    }

    private function cacheGet(string $key): ?array
    {
        try {
            return MysqliStore::tmdbCacheGet($key);
        } catch (\Throwable) {
            // Cache is optional. If MySQL is unavailable, the main request will surface the error.
        }
        return null;
    }

    private function cachePut(string $key, string $body, int $ttl): void
    {
        try {
            MysqliStore::tmdbCachePut($key, $body, $ttl);
        } catch (\Throwable) {
            // Ignore cache-write failures.
        }
    }
}
