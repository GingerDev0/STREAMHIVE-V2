<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Repository;

final class ImportService
{
    public function __construct(private ?Repository $repo = null, private ?TmdbClient $tmdb = null) {}

    public function ensureFull(array $record, string $type): array { return $record; }
    public function importMovieFromSlug(string $slug, ?int $tmdbId = null): ?array { return null; }
    public function importTvFromSlug(string $slug, ?int $tmdbId = null): ?array { return null; }
    public function importPersonFromSlug(string $slug): ?array { return null; }
    public function prefetchPopular(string $type, int $page = 1, int $limit = 20): int { return 0; }
    public function prefetchSearch(string $query, string $type, int $page = 1, int $limit = 20): int { return 0; }
    public function prefetchResults(array $results, string $type, int $limit = 20): int { return 0; }
    public function collectionMoviesFor(array $movie): array { return []; }
}
