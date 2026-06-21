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
function movie_slug(string $title, ?string $releaseDate = null): string {
    $slug = slugify($title);
    $year = format_year($releaseDate);
    return $year !== '' ? $slug . '-' . $year : $slug;
}
function media_slug(array $item, ?string $type = null): string {
    $type ??= (($item['media_type'] ?? '') === 'tv' ? 'tv' : (($item['media_type'] ?? '') === 'person' ? 'person' : 'movie'));
    $title = (string)($item['title'] ?? $item['name'] ?? 'untitled');

    if ($type === 'movie') {
        $releaseDate = (string)($item['release_date'] ?? '');
        $expected = movie_slug($title, $releaseDate);
        $existing = trim((string)($item['slug'] ?? ''));

        if ($existing !== '' && ($releaseDate === '' || $existing !== slugify($title))) {
            return $existing;
        }

        return $expected;
    }

    return (string)($item['slug'] ?? slugify($title));
}
function media_url(array $item): string {
    $slug = media_slug($item);
    if (($item['media_type'] ?? '') === 'tv') return url('tv/' . $slug);
    return url('movies/' . $slug);
}
function actor_url(array $person): string {
    $slug = (string)($person['slug'] ?? slugify((string)($person['name'] ?? 'actor')));
    return url('actors/' . $slug);
}


function site_name(): string {
    $name = trim((string)\App\Core\Config::get('SITE_NAME', 'StreamHIVE'));
    return $name !== '' ? $name : 'StreamHIVE';
}

function app_version(): string {
    $path = app_path('version.txt');
    if (!is_file($path)) return '';
    return trim((string)@file_get_contents($path));
}

function github_version(): string {
    $cacheDir = app_path('storage/cache');
    $localVersion = app_version();
    $cacheKey = preg_replace('/[^A-Za-z0-9_.-]/', '_', $localVersion) ?: 'unknown';
    $cacheFile = $cacheDir . '/version-check-v2-' . $cacheKey . '.json';
    $cacheTtl = 60;

    if (is_file($cacheFile) && (time() - (int)@filemtime($cacheFile)) < $cacheTtl) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($cached)) return trim((string)($cached['version'] ?? ''));
    }

    $cacheBust = rawurlencode($localVersion . '-' . (string)time());
    $url = 'https://api.github.com/repos/GingerDev0/StreamHIVE/contents/version.txt?ref=main&streamhive_version=' . $cacheBust;
    $version = '';
    $decodeVersion = static function (string $body): string {
        $body = trim($body);
        $data = json_decode($body, true);
        if (is_array($data) && isset($data['content'])) {
            $decoded = base64_decode((string)preg_replace('/\s+/', '', (string)$data['content']), true);
            if (is_string($decoded)) return trim($decoded);
        }
        return $body;
    };

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_USERAGENT => 'StreamHIVE-Version-Check',
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (is_string($body) && $status >= 200 && $status < 300) $version = $decodeVersion($body);
    } else {
        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'header' => "User-Agent: StreamHIVE-Version-Check\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if (is_string($body)) $version = $decodeVersion($body);
    }

    if ($version !== '') {
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
        @file_put_contents($cacheFile, json_encode(['version' => $version, 'checked_at' => time()]));
    }

    return $version;
}

function version_is_newer(string $candidate, string $current): bool {
    $candidate = trim($candidate);
    $current = trim($current);
    return $candidate !== '' && $current !== '' && version_compare($candidate, $current, '>');
}

function version_update_available(): bool {
    $localVersion = app_version();
    $githubVersion = github_version();
    return version_is_newer($githubVersion, $localVersion);
}

function share_button(string $title, string $url, string $label = 'Share'): string {
    $title = trim($title) !== '' ? $title : site_name();
    $url = absolute_url($url);
    return '<button class="btn btn-outline-light btn-lg streamhive-v2-share-open streamhive-js-share-open" type="button" data-share-title="' . e($title) . '" data-share-url="' . e($url) . '"><i class="fa-solid fa-share-nodes me-2"></i>' . e($label) . '</button>';
}


function player_template_value(string $template, array $values): string {
    return strtr($template, array_combine(
        array_map(static fn(string $key): string => '{' . $key . '}', array_keys($values)),
        array_values($values)
    ) ?: []);
}

function player_templates(string $provider): array {
    return match (strtolower(trim($provider))) {
        'vidsrc-to', 'vidsrcto' => [
            'movie' => 'https://vidsrc.to/embed/movie/{tmdb_id}',
            'episode' => 'https://vidsrc.to/embed/tv/{tmdb_id}/{season}/{episode}',
        ],
        'vidsrc-cc', 'vidsrccc' => [
            'movie' => 'https://vidsrc.cc/v2/embed/movie/{tmdb_id}',
            'episode' => 'https://vidsrc.cc/v2/embed/tv/{tmdb_id}/{season}/{episode}',
        ],
        'embed-su', 'embedsu' => [
            'movie' => 'https://embed.su/embed/movie/{tmdb_id}',
            'episode' => 'https://embed.su/embed/tv/{tmdb_id}/{season}/{episode}',
        ],
        default => [
            'movie' => 'https://multiembed.mov/?video_id={video_id}&tmdb={tmdb_flag}',
            'episode' => 'https://multiembed.mov/?video_id={video_id}&tmdb={tmdb_flag}&s={season}&e={episode}',
        ],
    };
}

function multiembed_player_url(array $item, string $type = 'movie', ?int $season = null, ?int $episode = null): string {
    $tmdbId = (int)($item['tmdb_id'] ?? $item['id'] ?? 0);
    $imdbId = trim((string)($item['imdb_id'] ?? ''));

    $provider = (string)\App\Core\Config::get('PLAYER_PROVIDER', 'multiembed');
    $templates = player_templates($provider);
    $movieTemplate = trim((string)\App\Core\Config::get('PLAYER_MOVIE_URL', '')) ?: $templates['movie'];
    $episodeTemplate = trim((string)\App\Core\Config::get('PLAYER_EPISODE_URL', '')) ?: $templates['episode'];

    if ($tmdbId > 0) {
        $videoId = (string)$tmdbId;
        $tmdbFlag = '1';
    } elseif ($imdbId !== '') {
        $videoId = $imdbId;
        $tmdbFlag = '0';
    } else {
        return '';
    }

    $season = max(1, (int)$season);
    $episode = max(1, (int)$episode);
    $values = [
        'video_id' => rawurlencode($videoId),
        'tmdb_id' => $tmdbId > 0 ? (string)$tmdbId : '',
        'imdb_id' => rawurlencode($imdbId),
        'tmdb_flag' => $tmdbFlag,
        'season' => (string)$season,
        'episode' => (string)$episode,
    ];

    if ($type === 'episode') {
        return player_template_value($episodeTemplate, $values);
    }

    return player_template_value($movieTemplate, $values);
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
function has_release_date(array $item): bool {
    return media_release_date($item) !== '';
}
function is_released_media(array $item): bool {
    $date = media_release_date($item);
    return $date !== '' && !is_future_date($date);
}
function has_media_poster(array $item): bool {
    $poster = trim((string)($item['poster_path'] ?? ''));
    return $poster !== ''
        && !str_contains($poster, 'placeholder.jpg')
        && !str_contains($poster, 'placeholder.svg');
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
function genre_icon(string $genre): string {
    $key = strtolower(trim($genre));
    return match ($key) {
        'action', 'action & adventure', 'adventure' => 'fa-person-running',
        'animation' => 'fa-wand-magic-sparkles',
        'comedy' => 'fa-face-laugh',
        'crime' => 'fa-fingerprint',
        'documentary' => 'fa-video',
        'drama' => 'fa-masks-theater',
        'family', 'kids' => 'fa-people-roof',
        'fantasy', 'sci-fi & fantasy' => 'fa-hat-wizard',
        'history' => 'fa-landmark',
        'horror' => 'fa-ghost',
        'music', 'musical' => 'fa-music',
        'mystery' => 'fa-magnifying-glass',
        'news' => 'fa-newspaper',
        'reality' => 'fa-camera',
        'romance' => 'fa-heart',
        'science fiction', 'sci-fi' => 'fa-rocket',
        'soap' => 'fa-comment-dots',
        'talk' => 'fa-microphone',
        'thriller' => 'fa-bolt',
        'tv movie' => 'fa-tv',
        'war', 'war & politics' => 'fa-shield-halved',
        'western' => 'fa-hat-cowboy',
        default => 'fa-tag',
    };
}
function genre_links(array $genres, ?string $type = null, int $limit = 0, string $class = 'streamhive-genre-link'): string {
    $genres = array_values(array_filter(array_map('strval', $genres), static fn($g) => trim($g) !== ''));
    if ($limit > 0) $genres = array_slice($genres, 0, $limit);
    if (!$genres) return '<span class="text-white-50">Genre TBA</span>';
    $links = [];
    foreach ($genres as $genre) {
        $links[] = '<a class="' . e($class) . '" href="' . e(genre_url($genre, $type)) . '"><i class="fa-solid ' . e(genre_icon($genre)) . '" aria-hidden="true"></i><span>' . e($genre) . '</span></a>';
    }
    return implode('<span class="streamhive-genre-separator">, </span>', $links);
}


function media_storage_payload(array $item, string $type, ?string $href = null, ?string $titleOverride = null, ?string $metaOverride = null, ?string $posterOverride = null): string {
    $title = $titleOverride ?: (string)($item['title'] ?? $item['name'] ?? 'Untitled');
    $date = (string)($item['release_date'] ?? $item['first_air_date'] ?? '');
    $year = format_year($date);
    $slug = media_slug($item + ['title' => $title], $type);
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
