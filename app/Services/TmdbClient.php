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
    public function discoverMovies(array $filters = []): array { return $this->get('/discover/movie', $filters + ['include_adult' => 'false', 'include_video' => 'false']); }
    public function discoverTv(array $filters = []): array { return $this->get('/discover/tv', $filters + ['include_adult' => 'false']); }
    public function findByImdb(string $imdbId): array { return $this->get('/find/' . rawurlencode($imdbId), ['external_source' => 'imdb_id']); }
    public function searchMovie(string $query, int $page = 1): array { return $this->get('/search/movie', ['query' => $query, 'include_adult' => 'false', 'page' => max(1, $page)]); }
    public function searchTv(string $query, int $page = 1): array { return $this->get('/search/tv', ['query' => $query, 'include_adult' => 'false', 'page' => max(1, $page)]); }
    public function searchPerson(string $query, int $page = 1): array { return $this->get('/search/person', ['query' => $query, 'include_adult' => 'false', 'page' => max(1, $page)]); }
    public function movie(int $id): array { return $this->get("/movie/{$id}", ['append_to_response' => 'credits,external_ids,videos,images,release_dates']); }
    public function movieRecommendations(int $id, int $page = 1): array { return $this->get("/movie/{$id}/recommendations", ['page' => max(1, $page)]); }
    public function movieSimilar(int $id, int $page = 1): array { return $this->get("/movie/{$id}/similar", ['page' => max(1, $page)]); }
    public function collection(int $id): array { return $this->get("/collection/{$id}"); }
    public function tv(int $id): array { return $this->get("/tv/{$id}", ['append_to_response' => 'credits,external_ids,videos,images,content_ratings']); }
    public function tvRecommendations(int $id, int $page = 1): array { return $this->get("/tv/{$id}/recommendations", ['page' => max(1, $page)]); }
    public function tvSimilar(int $id, int $page = 1): array { return $this->get("/tv/{$id}/similar", ['page' => max(1, $page)]); }
    public function person(int $id): array { return $this->get("/person/{$id}", ['append_to_response' => 'combined_credits,external_ids']); }
    public function season(int $seriesId, int $season): array { return $this->get("/tv/{$seriesId}/season/{$season}"); }
    public function episode(int $seriesId, int $season, int $episode): array { return $this->get("/tv/{$seriesId}/season/{$season}/episode/{$episode}", ['append_to_response' => 'credits,external_ids']); }
    public function movieGenres(): array { return $this->get('/genre/movie/list')['genres'] ?? []; }
    public function tvGenres(): array { return $this->get('/genre/tv/list')['genres'] ?? []; }

    public function comingMoviesThisYear(string $startDate, string $endDate, int $page = 1): array
    {
        $request = self::comingMoviesThisYearRequest($startDate, $endDate, $page);
        return $this->get($request['path'], $request['query']);
    }

    public static function comingMoviesThisYearRequest(string $startDate, string $endDate, int $page = 1): array
    {
        return [
            'path' => '/discover/movie',
            'query' => [
                'include_adult' => 'false',
                'include_video' => 'false',
                'primary_release_date.gte' => $startDate,
                'primary_release_date.lte' => $endDate,
                'sort_by' => 'popularity.desc',
                'with_original_language' => 'en',
                'page' => max(1, $page),
            ],
        ];
    }

    public function discoverHeroMovies(string $startDate, string $endDate, int $page = 1): array
    {
        return $this->get('/discover/movie', [
            'include_adult' => 'false',
            'include_video' => 'false',
            'primary_release_date.gte' => $startDate,
            'primary_release_date.lte' => $endDate,
            'sort_by' => 'popularity.desc',
            'vote_average.gte' => 8,
            'with_original_language' => 'en',
            'without_genres' => '10770',
            'page' => max(1, $page),
        ]);
    }

    public function comingTvThisYear(string $startDate, string $endDate, int $page = 1): array
    {
        $request = self::comingTvThisYearRequest($startDate, $endDate, $page);
        return $this->get($request['path'], $request['query']);
    }

    public static function comingTvThisYearRequest(string $startDate, string $endDate, int $page = 1): array
    {
        return [
            'path' => '/discover/tv',
            'query' => [
                'include_adult' => 'false',
                'first_air_date.gte' => $startDate,
                'first_air_date.lte' => $endDate,
                'sort_by' => 'popularity.desc',
                'with_original_language' => 'en',
                'page' => max(1, $page),
            ],
        ];
    }

    public function getBatch(array $requests, int $concurrency = 8): array
    {
        $results = [];
        $queue = [];

        foreach ($requests as $key => $request) {
            $queue[] = [
                'key' => $key,
                'path' => (string)($request['path'] ?? ''),
                'query' => (array)($request['query'] ?? []),
            ];
        }

        foreach (array_chunk($queue, max(1, $concurrency)) as $chunk) {
            $multi = curl_multi_init();
            $handles = [];

            foreach ($chunk as $request) {
                $ch = curl_init($this->url($request['path'], $request['query']));
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => $this->headers(),
                    CURLOPT_TIMEOUT => 15,
                ]);
                curl_multi_add_handle($multi, $ch);
                $handles[] = ['handle' => $ch, 'key' => $request['key']];
            }

            do {
                $status = curl_multi_exec($multi, $running);
                if ($running) curl_multi_select($multi, 1.0);
            } while ($running && $status === CURLM_OK);

            foreach ($handles as $entry) {
                $ch = $entry['handle'];
                $body = curl_multi_getcontent($ch);
                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);

                if ($body === false || $status >= 400) {
                    curl_multi_remove_handle($multi, $ch);
                    curl_close($ch);
                    continue;
                }

                $results[$entry['key']] = json_decode($body, true) ?: [];
                curl_multi_remove_handle($multi, $ch);
                curl_close($ch);
            }

            curl_multi_close($multi);
        }

        return $results;
    }

    private function get(string $path, array $query = []): array
    {
        $ch = curl_init($this->url($path, $query));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->headers(),
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status >= 400) {
            throw new \RuntimeException('TMDB request failed: ' . ($error ?: $status));
        }
        return json_decode($body, true) ?: [];
    }

    private function url(string $path, array $query = []): string
    {
        $query += ['language' => 'en-US'];
        if ($apiKey = Config::get('TMDB_API_KEY')) $query['api_key'] = $apiKey;
        return $this->base . $path . '?' . http_build_query($query);
    }

    private function headers(): array
    {
        $headers = ['Accept: application/json'];
        if ($token = Config::get('TMDB_BEARER_TOKEN')) $headers[] = 'Authorization: Bearer ' . $token;
        return $headers;
    }
}
