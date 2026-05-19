<?php

declare(strict_types=1);

function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function asset(string $path): string { return '/assets/' . ltrim($path, '/'); }
function url(string $path): string { return '/' . ltrim($path, '/'); }

function absolute_url(string $path = ''): string {
    $path = trim($path);
    if ($path !== '' && preg_match('~^https?://~i', $path)) return $path;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if ($path === '') {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return $scheme . '://' . $host . $uri;
    }
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}
function current_url(): string { return absolute_url(''); }
function meta_excerpt(?string $text, int $length = 160): string {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$text)) ?: '');
    if ($text === '') return '';
    if (function_exists('mb_strlen') && mb_strlen($text) > $length) {
        return rtrim((string)mb_substr($text, 0, $length - 1)) . '…';
    }
    if (strlen($text) > $length) return rtrim(substr($text, 0, $length - 1)) . '…';
    return $text;
}
function meta_image(?string $path, string $size = 'w1280'): string { return absolute_url(tmdb_img($path, $size)); }
function tmdb_img(?string $path, string $size = 'w500'): string {
    return $path ? "https://image.tmdb.org/t/p/{$size}{$path}" : '/assets/img/placeholder.jpg';
}
function slugify(string $text): string {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim((string)$text, '-');
    $text = strtolower($text);
    return $text ?: 'item';
}
function media_url(array $item): string {
    if (($item['media_type'] ?? '') === 'tv') return url('tv/' . $item['slug']);
    return url('movies/' . $item['slug']);
}
function actor_url(array $person): string { return url('actors/' . $person['slug']); }


function share_button(string $title, string $url, string $label = 'Share'): string {
    $title = trim($title) !== '' ? $title : 'StreamHIVE';
    $url = absolute_url($url);
    return '<button class="btn btn-outline-light btn-lg v2-share-open js-share-open" type="button" data-share-title="' . e($title) . '" data-share-url="' . e($url) . '"><i class="fa-solid fa-share-nodes me-2"></i>' . e($label) . '</button>';
}


function multiembed_player_url(array $item, string $type = 'movie', ?int $season = null, ?int $episode = null): string {
    $tmdbId = (int)($item['tmdb_id'] ?? $item['id'] ?? 0);
    $imdbId = trim((string)($item['imdb_id'] ?? ''));

    // Prefer TMDB IDs because the local database always stores them, while IMDb IDs can be missing,
    // especially for TV shows/episodes. Fall back to IMDb only when TMDB is unavailable.
    if ($tmdbId > 0) {
        $params = [
            'video_id' => (string)$tmdbId,
            'tmdb' => '1',
        ];
    } elseif ($imdbId !== '') {
        $params = ['video_id' => $imdbId];
    } else {
        return '';
    }

    if ($type === 'episode') {
        $season = max(1, (int)$season);
        $episode = max(1, (int)$episode);
        $params['s'] = (string)$season;
        $params['e'] = (string)$episode;
    }

    return 'https://multiembed.mov/?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}




function uk_age_rating(int|string|null $rating, string $type = ''): string {
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

function display_age_rating(int|string|null $rating, string $type = ''): string {
    return uk_age_rating($rating, $type);
}

function media_release_date(array $item): string {
    return trim((string)($item['release_date'] ?? $item['first_air_date'] ?? $item['air_date'] ?? ''));
}
function is_future_date(?string $date): bool {
    $date = trim((string)$date);
    if ($date === '') return false;
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dt) return false;
    $today = new DateTimeImmutable('today');
    return $dt > $today;
}
function is_released_media(array $item): bool {
    return !is_future_date(media_release_date($item));
}

function format_date(?string $date): string {
    $date = trim((string)$date);
    if ($date === '') return '';
    $dt = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$dt) return $date;
    $day = (int)$dt->format('j');
    if ($day >= 11 && $day <= 13) {
        $suffix = 'th';
    } else {
        $suffix = match ($day % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
    return $day . $suffix . ' ' . $dt->format('F Y');
}


function format_runtime(int|float|string|array|null $minutes): string {
    if (is_array($minutes)) {
        $minutes = array_values(array_filter(array_map('intval', $minutes), static fn(int $value): bool => $value > 0))[0] ?? 0;
    }

    $minutes = (int)$minutes;
    if ($minutes <= 0) return '';

    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;
    $parts = [];

    if ($hours > 0) {
        $parts[] = $hours . ' ' . ($hours === 1 ? 'hour' : 'hours');
    }

    if ($mins > 0) {
        $parts[] = $mins . ' ' . ($mins === 1 ? 'min' : 'mins');
    }

    return implode(' ', $parts);
}

function media_runtime(array $item, string $type = 'movie'): string {
    if ($type === 'tv') {
        return format_runtime($item['episode_run_time'] ?? $item['runtime'] ?? null);
    }

    return format_runtime($item['runtime'] ?? null);
}

function format_bytes(int|float $bytes): string {
    $bytes = max(0, (float)$bytes);
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return ($i === 0 ? (string)(int)$bytes : number_format($bytes, 1)) . ' ' . $units[$i];
}

function format_year(?string $date): string {
    $date = trim((string)$date);
    if ($date === '') return '';
    $dt = DateTime::createFromFormat('!Y-m-d', $date);
    return $dt ? $dt->format('Y') : substr($date, 0, 4);
}

function search_url(array $params = []): string {
    $query = http_build_query(array_filter($params, static fn($v) => $v !== null && $v !== ''));
    return url('s') . ($query ? '?' . $query : '');
}
function genre_url(string $genre, ?string $type = null): string {
    return search_url(['genre' => $genre, 'type' => $type]);
}
function genre_links(array $genres, ?string $type = null, int $limit = 0, string $class = 'genre-link'): string {
    $genres = array_values(array_filter(array_map('strval', $genres), static fn($g) => trim($g) !== ''));
    if ($limit > 0) $genres = array_slice($genres, 0, $limit);
    if (!$genres) return '<span class="text-white-50">Genre TBA</span>';
    $links = [];
    foreach ($genres as $genre) {
        $links[] = '<a class="' . e($class) . '" href="' . e(genre_url($genre, $type)) . '">' . e($genre) . '</a>';
    }
    return implode('<span class="genre-separator">, </span>', $links);
}


function media_storage_payload(array $item, string $type, ?string $href = null, ?string $titleOverride = null, ?string $metaOverride = null, ?string $posterOverride = null): string {
    $title = $titleOverride ?: (string)($item['title'] ?? $item['name'] ?? 'Untitled');
    $date = (string)($item['release_date'] ?? $item['first_air_date'] ?? '');
    $year = format_year($date);
    $slug = (string)($item['slug'] ?? slugify($title));
    $href = $href ?: ($type === 'person' ? url('actors/' . $slug) : ($type === 'tv' ? url('tv/' . $slug) : url('movies/' . $slug)));
    $posterSource = $type === 'person' ? ($item['profile_path'] ?? null) : ($item['poster_path'] ?? null);
    $poster = $posterOverride ?: tmdb_img($posterSource);
    $typeText = $type === 'person' ? 'Actor' : ($type === 'tv' ? 'TV Show' : 'Movie');
    $meta = $metaOverride ?: trim(implode(' · ', array_filter([
        $typeText,
        $year,
        !empty($item['age_rating']) && $type !== 'person' ? display_age_rating($item['age_rating'], $type) : null,
    ])));
    $payload = [
        'type' => $type,
        'tmdb_id' => $item['tmdb_id'] ?? $item['id'] ?? null,
        'slug' => $slug,
        'title' => $title,
        'url' => $href,
        'poster' => $poster,
        'backdrop' => tmdb_img($item['backdrop_path'] ?? $posterSource, 'w780'),
        'year' => $year,
        'rating' => round((float)($item['vote_average'] ?? 0), 1),
        'meta' => $meta,
    ];
    return htmlspecialchars((string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
}
