<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Repository;
use App\Services\ImportService;

final class ActorController
{

    public function index(array $params): string
    {
        $repo = new Repository();
        $query = trim((string)($_GET['q'] ?? ''));
        $sort = (string)($_GET['sort'] ?? 'name_asc');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(48, max(6, (int)($_GET['per_page'] ?? 24)));

        $items = $repo->people->all();
        if ($query !== '') {
            $needle = strtolower($query);
            $items = array_values(array_filter($items, static function (array $actor) use ($needle): bool {
                $knownFor = [];
                foreach (($actor['known_for'] ?? []) as $credit) $knownFor[] = (string)($credit['title'] ?? '');
                foreach (($actor['credits'] ?? []) as $credit) $knownFor[] = (string)($credit['title'] ?? '');
                $haystack = strtolower(implode(' ', array_filter([
                    (string)($actor['name'] ?? ''),
                    (string)($actor['biography'] ?? ''),
                    (string)($actor['known_for_department'] ?? ''),
                    implode(' ', $knownFor),
                ])));
                return str_contains($haystack, $needle);
            }));
        }

        usort($items, static function (array $a, array $b) use ($sort): int {
            return match ($sort) {
                'name_desc' => strnatcasecmp((string)($b['name'] ?? ''), (string)($a['name'] ?? '')),
                'updated_desc' => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')),
                default => strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')),
            };
        });

        $total = count($items);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $items = array_slice($items, ($page - 1) * $perPage, $perPage);

        return View::render('pages/actors', [
            'title' => 'Actors',
            'metaDescription' => 'Browse saved actor pages and filmographies.',
            'ogTitle' => 'Actors | StreamHIVE',
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

    public function show(array $params): string
    {
        $repo = new Repository();
        $importer = new ImportService($repo);
        $slug = (string)($params['slug'] ?? '');
        $actor = $repo->bySlug('people', $slug);
        $tmdbId = isset($_GET['tmdb_id']) && ctype_digit((string)$_GET['tmdb_id']) ? (int)$_GET['tmdb_id'] : null;

        $isFull = $actor
            && (($actor['import_status'] ?? '') === 'full')
            && array_key_exists('biography', $actor);

        if (!$isFull) {
            return View::render('pages/fetching-content', [
                'title' => 'Fetching actor | StreamHIVE',
                'robots' => 'noindex, follow',
                'metaDescription' => 'This actor page is being fetched and saved locally.',
                'fetchType' => 'person',
                'fetchSlug' => $slug,
                'fetchFallbackUrl' => url('actors/' . $slug . ($tmdbId ? ('?tmdb_id=' . $tmdbId) : '')),
                'message' => 'This actor page is being fetched and saved locally. Please wait...',
            ]);
        }

        return View::render('pages/actor', [
            'title' => ($actor['name'] ?? 'Actor') . ' | Filmography',
            'metaDescription' => meta_excerpt(($actor['biography'] ?? '') ?: ('Browse movies and TV shows featuring ' . ($actor['name'] ?? 'this actor') . '.')),
            'ogTitle' => ($actor['name'] ?? 'Actor') . ' | StreamHIVE',
            'ogDescription' => meta_excerpt(($actor['biography'] ?? '') ?: ('Movies and TV shows featuring ' . ($actor['name'] ?? 'this actor') . '.')),
            'ogType' => 'profile',
            'ogImage' => meta_image($actor['profile_path'] ?? null, 'w500'),
            'canonicalUrl' => absolute_url('actors/' . ($actor['slug'] ?? '')),
            'actor' => $actor,
        ]);
    }
}
