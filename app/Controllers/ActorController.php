<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Repository;
use App\Services\ImportService;
use App\Services\TmdbClient;

final class ActorController
{

    public function index(array $params): string
    {
        $query = trim((string)($_GET['q'] ?? ''));
        $sort = (string)($_GET['sort'] ?? 'popularity_desc');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(48, max(6, (int)($_GET['per_page'] ?? 24)));

        $pagination = $this->livePeopleItems($query, $page, $perPage);
        $items = $pagination['items'];

        usort($items, static function (array $a, array $b) use ($sort): int {
            return match ($sort) {
                'popularity_desc' => ((float)($b['popularity'] ?? 0)) <=> ((float)($a['popularity'] ?? 0)),
                'name_desc' => strnatcasecmp((string)($b['name'] ?? ''), (string)($a['name'] ?? '')),
                'updated_desc' => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')),
                default => strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')),
            };
        });

        $total = $pagination['total'];
        $pages = $pagination['pages'];
        $page = min($page, $pages);
        $items = array_slice($items, ($page - 1) * $perPage, $perPage);

        $siteName = site_name();

        return View::render('pages/actors', [
            'title' => 'Actors',
            'metaDescription' => 'Browse actor pages and filmographies from TMDB.',
            'ogTitle' => 'Actors | ' . $siteName,
            'ogDescription' => 'Browse actors and open filmography pages.',
            'canonicalUrl' => absolute_url('actors'),
            'items' => $items,
            'query' => $query,
            'sort' => $sort,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
        ]);
    }

    private function livePeopleItems(string $query, int $page, int $perPage): array
    {
        $tmdbPages = range(1, min(500, (int)ceil(max(240, ($page + 1) * $perPage) / 20)));
        $requests = [];

        foreach ($tmdbPages as $tmdbPage) {
            $requests['person:' . $tmdbPage] = $query === ''
                ? ['path' => '/person/popular', 'query' => ['page' => $tmdbPage]]
                : ['path' => '/search/person', 'query' => ['query' => $query, 'include_adult' => 'false', 'page' => $tmdbPage]];
        }

        $items = [];
        $total = 0;
        $pages = 1;

        try {
            foreach ((new TmdbClient())->getBatch($requests, 16) as $response) {
                $total = max($total, (int)($response['total_results'] ?? 0));
                $pages = max($pages, (int)($response['total_pages'] ?? 1));
                foreach (($response['results'] ?? []) as $person) {
                    $items[] = $this->normalisePersonSummary($person);
                }
            }
        } catch (\Throwable) {
            $items = [];
        }

        return [
            'items' => $this->uniquePeople($items),
            'total' => $total ?: count($items),
            'pages' => max(1, min(500, $pages)),
        ];
    }

    private function normalisePersonSummary(array $person): array
    {
        $name = (string)($person['name'] ?? 'Untitled');
        $knownFor = [];

        foreach (($person['known_for'] ?? []) as $credit) {
            $title = (string)($credit['title'] ?? $credit['name'] ?? '');
            if ($title === '') continue;
            $credit['title'] = $title;
            $knownFor[] = $credit;
        }

        $person['tmdb_id'] = (int)($person['id'] ?? 0);
        $person['media_type'] = 'person';
        $person['title'] = $name;
        $person['name'] = $name;
        $person['slug'] = slugify($name);
        $person['known_for'] = $knownFor;

        return $person;
    }

    private function uniquePeople(array $items): array
    {
        $seen = [];
        $unique = [];

        foreach ($items as $item) {
            $key = (string)($item['tmdb_id'] ?? $item['id'] ?? $item['slug'] ?? '');
            if ($key === '' || isset($seen[$key])) continue;
            $seen[$key] = true;
            $unique[] = $item;
        }

        return $unique;
    }

    public function show(array $params): string
    {
        $repo = new Repository();
        $importer = new ImportService($repo);
        $slug = (string)($params['slug'] ?? '');
        $tmdbId = isset($_GET['tmdb_id']) && ctype_digit((string)$_GET['tmdb_id']) ? (int)$_GET['tmdb_id'] : null;
        if (!$tmdbId) $tmdbId = $this->resolvePersonIdFromSlug($slug);
        $actor = $tmdbId ? $this->liveActor($tmdbId, $slug) : $repo->bySlug('people', $slug);

        $isFull = $actor
            && (($actor['import_status'] ?? '') === 'full')
            && array_key_exists('biography', $actor);

        if (!$isFull) {
            return View::render('pages/fetching-content', [
                'title' => 'Fetching actor | ' . site_name(),
                'robots' => 'noindex, follow',
                'metaDescription' => 'This actor page is being fetched from TMDB.',
                'fetchType' => 'person',
                'fetchSlug' => $slug,
                'fetchFallbackUrl' => url('actors/' . $slug),
                'message' => 'This actor page is being fetched from TMDB. Please wait...',
            ]);
        }

        return View::render('pages/actor', [
            'title' => ($actor['name'] ?? 'Actor') . ' | Filmography',
            'metaDescription' => meta_excerpt(($actor['biography'] ?? '') ?: ('Browse movies and TV shows featuring ' . ($actor['name'] ?? 'this actor') . '.')),
            'ogTitle' => ($actor['name'] ?? 'Actor') . ' | ' . site_name(),
            'ogDescription' => meta_excerpt(($actor['biography'] ?? '') ?: ('Movies and TV shows featuring ' . ($actor['name'] ?? 'this actor') . '.')),
            'ogType' => 'profile',
            'ogImage' => meta_image($actor['profile_path'] ?? null, 'w500'),
            'canonicalUrl' => absolute_url('actors/' . ($actor['slug'] ?? '')),
            'actor' => $actor,
        ]);
    }

    private function liveActor(int $tmdbId, string $slug): ?array
    {
        try {
            $actor = (new TmdbClient())->person($tmdbId);
        } catch (\Throwable) {
            return null;
        }

        $name = (string)($actor['name'] ?? $slug);
        $actor['tmdb_id'] = (int)($actor['id'] ?? $tmdbId);
        $actor['slug'] = slugify($name);
        $actor['media_type'] = 'person';
        $actor['import_status'] = 'full';

        $credits = [];
        foreach (($actor['combined_credits']['cast'] ?? []) as $credit) {
            $type = (string)($credit['media_type'] ?? 'movie');
            if (!in_array($type, ['movie', 'tv'], true)) continue;
            $title = (string)($credit['title'] ?? $credit['name'] ?? '');
            if ($title === '') continue;
            $credit['title'] = $title;
            $credit['tmdb_id'] = (int)($credit['id'] ?? 0);
            $credit['slug'] = $type === 'movie'
                ? movie_slug($title, (string)($credit['release_date'] ?? ''))
                : slugify($title);
            $credits[] = $credit;
        }
        $actor['credits'] = $credits;
        $actor['known_for'] = array_slice($credits, 0, 8);

        return $actor;
    }

    private function resolvePersonIdFromSlug(string $slug): int
    {
        $query = trim(str_replace('-', ' ', $slug));
        if ($query === '') return 0;

        try {
            $results = (new TmdbClient())->searchPerson($query, 1)['results'] ?? [];
        } catch (\Throwable) {
            return 0;
        }

        foreach ($results as $result) {
            $name = (string)($result['name'] ?? '');
            if (slugify($name) === $slug) {
                return (int)($result['id'] ?? 0);
            }
        }

        return (int)($results[0]['id'] ?? 0);
    }
}
