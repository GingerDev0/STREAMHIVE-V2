<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\View;
use App\Models\Repository;
use App\Services\ImportService;
use App\Services\MysqliStore;

final class AdminController
{
    private Repository $repo;

    public function __construct()
    {
        $this->repo = new Repository();
    }

    public function dashboard(): string
    {
        $this->guard();

        $movies = $this->repo->movies->all();
        $tv = $this->repo->tv->all();
        $people = $this->repo->people->all();
        $allMedia = array_merge(
            array_map(static fn(array $item): array => $item + ['admin_type' => 'movies'], $movies),
            array_map(static fn(array $item): array => $item + ['admin_type' => 'tv'], $tv),
            array_map(static fn(array $item): array => $item + ['admin_type' => 'people'], $people)
        );

        usort($allMedia, static fn(array $a, array $b): int => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));

        $prefetchedBreakdown = [
            'movies' => $this->repo->movies->countByStatus('prefetched'),
            'tv' => $this->repo->tv->countByStatus('prefetched'),
            'people' => $this->repo->people->countByStatus('prefetched'),
        ];

        return View::render('admin/dashboard', [
            'title' => 'Admin Dashboard',
            'metaDescription' => 'Private admin dashboard for managing StreamHIVE content.',
            'robots' => 'noindex, nofollow',
            'movieCount' => count($movies),
            'tvCount' => count($tv),
            'peopleCount' => count($people),
            'prefetchedCount' => array_sum($prefetchedBreakdown),
            'prefetchedBreakdown' => $prefetchedBreakdown,
            'missingRatingCount' => count(array_filter($allMedia, static fn(array $item): bool => trim((string)($item['age_rating'] ?? '')) === '')),
            'recentItems' => array_slice($allMedia, 0, 8),
            'storageStats' => $this->storageStats(),
            'bulkImportResult' => $this->bulkImportResultFromQuery(),
        ]);
    }

    public function import(): string
    {
        $this->guard();

        $message = null;
        $error = null;
        $record = null;
        $input = trim((string)($_POST['input'] ?? ''));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $record = (new ImportService())->importInput($input);
                $message = 'Imported “' . ($record['title'] ?? $record['name'] ?? $record['id'] ?? 'item') . '” successfully.';
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return View::render('admin/import', compact('message', 'error', 'record', 'input') + [
            'title' => 'Import Media',
            'metaDescription' => 'Private import tool for StreamHIVE content.',
            'robots' => 'noindex, nofollow',
        ]);
    }

    public function manage(array $params): string
    {
        $this->guard();

        $type = (string)($params['type'] ?? 'movies');
        if (!in_array($type, ['movies', 'tv', 'people'], true)) {
            $type = 'movies';
        }

        $q = trim((string)($_GET['q'] ?? ''));
        $status = (string)($_GET['status'] ?? 'all');
        $sort = (string)($_GET['sort'] ?? 'updated_desc');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 24;

        $items = $this->repo->store($type)->all();

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $items = array_values(array_filter($items, static function (array $item) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    (string)($item['id'] ?? ''),
                    (string)($item['title'] ?? $item['name'] ?? ''),
                    (string)($item['slug'] ?? ''),
                    implode(' ', (array)($item['genres'] ?? [])),
                    (string)($item['imdb_id'] ?? ''),
                ])));
                return str_contains($haystack, $needle);
            }));
        }

        if ($status !== 'all') {
            $items = array_values(array_filter($items, static fn(array $item): bool => (string)($item['import_status'] ?? 'full') === $status));
        }

        usort($items, static function (array $a, array $b) use ($sort): int {
            $titleA = (string)($a['title'] ?? $a['name'] ?? '');
            $titleB = (string)($b['title'] ?? $b['name'] ?? '');
            return match ($sort) {
                'title_asc' => strcasecmp($titleA, $titleB),
                'title_desc' => strcasecmp($titleB, $titleA),
                'rating_desc' => ((float)($b['vote_average'] ?? 0)) <=> ((float)($a['vote_average'] ?? 0)),
                'rating_asc' => ((float)($a['vote_average'] ?? 0)) <=> ((float)($b['vote_average'] ?? 0)),
                'date_desc' => strcmp((string)($b['release_date'] ?? $b['first_air_date'] ?? ''), (string)($a['release_date'] ?? $a['first_air_date'] ?? '')),
                'date_asc' => strcmp((string)($a['release_date'] ?? $a['first_air_date'] ?? ''), (string)($b['release_date'] ?? $b['first_air_date'] ?? '')),
                default => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')),
            };
        });

        $total = count($items);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $items = array_slice($items, ($page - 1) * $perPage, $perPage);

        return View::render('admin/manage', compact('type', 'items', 'q', 'status', 'sort', 'page', 'pages', 'total', 'perPage') + [
            'title' => 'Manage ' . ucfirst($type),
            'metaDescription' => 'Private management page for StreamHIVE content.',
            'robots' => 'noindex, nofollow',
        ]);
    }

    public function importPrefetched(): string
    {
        $this->guard();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin?token=' . urlencode((string)($_GET['token'] ?? $_POST['token'] ?? '')));
            return '';
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
        $scope = (string)($_POST['scope'] ?? 'all');
        $limit = max(0, (int)($_POST['limit'] ?? 0));

        $buckets = match ($scope) {
            'movies' => ['movies' => 'movie'],
            'tv' => ['tv' => 'tv'],
            'people', 'actors' => ['people' => 'person'],
            default => ['movies' => 'movie', 'tv' => 'tv', 'people' => 'person'],
        };

        $importer = new ImportService();
        $imported = ['movies' => 0, 'tv' => 0, 'people' => 0];
        $failed = 0;
        $remainingLimit = $limit;

        foreach ($buckets as $bucket => $kind) {
            $take = $remainingLimit > 0 ? $remainingLimit : 0;
            $ids = $this->repo->store($bucket)->idsByStatus('prefetched', $take);

            foreach ($ids as $id) {
                try {
                    match ($kind) {
                        'movie' => $importer->importMovie((int)$id),
                        'tv' => $importer->importTv((int)$id),
                        'person' => $importer->importPerson((int)$id),
                    };
                    $imported[$bucket]++;
                } catch (\Throwable) {
                    $failed++;
                }

                if ($remainingLimit > 0) {
                    $remainingLimit--;
                    if ($remainingLimit <= 0) break 2;
                }
            }
        }

        $params = [
            'token' => $token,
            'bulk_imported_movies' => $imported['movies'],
            'bulk_imported_tv' => $imported['tv'],
            'bulk_imported_people' => $imported['people'],
            'bulk_failed' => $failed,
        ];

        header('Location: /admin?' . http_build_query($params));
        return '';
    }

    public function delete(array $params): string
    {
        $this->guard();
        $type = (string)($params['type'] ?? 'movies');
        $this->repo->store($type)->delete($params['id']);
        header('Location: /admin/manage/' . $type . '?token=' . urlencode($_GET['token'] ?? ''));
        return '';
    }

    private function guard(): void
    {
        $token = Config::get('ADMIN_TOKEN', 'change-this-token');
        if ($token && ($_GET['token'] ?? $_POST['token'] ?? '') !== $token) {
            http_response_code(403);
            exit('Forbidden: add ?token=YOUR_ADMIN_TOKEN');
        }
    }

    private function bulkImportResultFromQuery(): ?array
    {
        $keys = ['bulk_imported_movies', 'bulk_imported_tv', 'bulk_imported_people', 'bulk_failed'];
        foreach ($keys as $key) {
            if (isset($_GET[$key])) {
                return [
                    'movies' => max(0, (int)($_GET['bulk_imported_movies'] ?? 0)),
                    'tv' => max(0, (int)($_GET['bulk_imported_tv'] ?? 0)),
                    'people' => max(0, (int)($_GET['bulk_imported_people'] ?? 0)),
                    'failed' => max(0, (int)($_GET['bulk_failed'] ?? 0)),
                ];
            }
        }

        return null;
    }

    private function storageStats(): array
    {
        $db = MysqliStore::stats();
        $stats = [];
        foreach (['movies', 'tv', 'people'] as $bucket) {
            $rows = (int)($db['buckets'][$bucket] ?? 0);
            $stats[$bucket] = [
                'files' => 1,
                'rows' => $rows,
                'capacity' => max(1, $rows),
                'percent' => $rows > 0 ? 100 : 0,
                'database_path' => $db['path'] ?? 'stream_hive',
                'size_bytes' => $db['size_bytes'] ?? 0,
            ];
        }
        return $stats;
    }
}
