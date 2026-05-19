<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use PDO;
use RuntimeException;

final class SqliteStore
{
    private static ?PDO $pdo = null;
    private static bool $schemaReady = false;
    private static array $migratedBuckets = [];
    private static array $upsertStatements = [];

    public function __construct(private readonly string $bucket)
    {
        self::pdo();
        $this->migrateJsonBucketOnce();
    }

    public function all(): array
    {
        $stmt = self::pdo()->prepare('SELECT payload, created_at, updated_at FROM records WHERE bucket = :bucket ORDER BY updated_at DESC, rowid DESC');
        $stmt->execute(['bucket' => $this->bucket]);

        $records = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $record = $this->decode((string)$row['payload']);
            $record['created_at'] = $record['created_at'] ?? $row['created_at'];
            $record['updated_at'] = $record['updated_at'] ?? $row['updated_at'];
            $records[] = $record;
        }
        return $records;
    }

    public function find(int|string $id): ?array
    {
        $stmt = self::pdo()->prepare('SELECT payload, created_at, updated_at FROM records WHERE bucket = :bucket AND id = :id LIMIT 1');
        $stmt->execute(['bucket' => $this->bucket, 'id' => (string)$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->rowToRecord($row) : null;
    }

    public function findBy(string $field, mixed $value): ?array
    {
        if ($field === 'id') return $this->find((string)$value);

        if (in_array($field, ['slug', 'tmdb_id', 'imdb_id', 'import_status'], true)) {
            $column = $field === 'tmdb_id' ? 'id' : $field;
            $stmt = self::pdo()->prepare("SELECT payload, created_at, updated_at FROM records WHERE bucket = :bucket AND {$column} = :value LIMIT 1");
            $stmt->execute(['bucket' => $this->bucket, 'value' => (string)$value]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $this->rowToRecord($row);
        }

        foreach ($this->all() as $record) {
            if ((string)($record[$field] ?? '') === (string)$value) return $record;
        }
        return null;
    }

    public function upsert(array $record): void
    {
        $this->upsertPrepared($record, gmdate(DATE_ATOM));
    }

    /**
     * Insert/update many records using one transaction and one reused prepared statement.
     * This is much faster than committing each movie/person/TV row separately.
     */
    public function upsertMany(array $records): int
    {
        if ($records === []) return 0;

        $count = 0;
        $now = gmdate(DATE_ATOM);
        self::transaction(function () use ($records, $now, &$count): void {
            foreach ($records as $record) {
                if (!is_array($record) || !isset($record['id'])) continue;
                $this->upsertPrepared($record, $now);
                $count++;
            }
        });

        return $count;
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) return $callback();

        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function idsWithStatus(array $ids, string $status): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids), static fn(string $id): bool => $id !== '')));
        if ($ids === []) return [];

        $found = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $params = ['bucket' => $this->bucket, 'status' => $status];
            $placeholders = [];
            foreach ($chunk as $i => $id) {
                $key = 'id' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $id;
            }
            $stmt = self::pdo()->prepare('SELECT id FROM records WHERE bucket = :bucket AND import_status = :status AND id IN (' . implode(',', $placeholders) . ')');
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) $found[(string)$id] = true;
        }
        return $found;
    }

    public function slugForId(int|string $id): ?string
    {
        $stmt = self::pdo()->prepare('SELECT slug FROM records WHERE bucket = :bucket AND id = :id LIMIT 1');
        $stmt->execute(['bucket' => $this->bucket, 'id' => (string)$id]);
        $slug = $stmt->fetchColumn();
        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    public function slugExists(string $slug, int|string|null $excludeId = null): bool
    {
        if ($excludeId !== null && (string)$excludeId !== '') {
            $stmt = self::pdo()->prepare('SELECT 1 FROM records WHERE bucket = :bucket AND slug = :slug AND id != :id LIMIT 1');
            $stmt->execute(['bucket' => $this->bucket, 'slug' => $slug, 'id' => (string)$excludeId]);
        } else {
            $stmt = self::pdo()->prepare('SELECT 1 FROM records WHERE bucket = :bucket AND slug = :slug LIMIT 1');
            $stmt->execute(['bucket' => $this->bucket, 'slug' => $slug]);
        }
        return (bool)$stmt->fetchColumn();
    }

    private function upsertPrepared(array $record, string $now): void
    {
        if (!isset($record['id'])) throw new \InvalidArgumentException('Record requires id');

        $record['created_at'] = $record['created_at'] ?? $now;
        $record['updated_at'] = $now;
        if (($record['media_type'] ?? $this->mediaTypeForBucket()) !== 'person') {
            $record['age_rating'] = self::ukAgeRating($record['age_rating'] ?? '');
        }

        $payloadRecord = $record;
        unset($payloadRecord['created_at'], $payloadRecord['updated_at']);

        $stmt = self::upsertStatement();
        $stmt->execute([
            'bucket' => $this->bucket,
            'id' => (string)$record['id'],
            'slug' => (string)($record['slug'] ?? ''),
            'title' => (string)($record['title'] ?? ''),
            'name' => (string)($record['name'] ?? ''),
            'media_type' => (string)($record['media_type'] ?? $this->mediaTypeForBucket()),
            'imdb_id' => (string)($record['imdb_id'] ?? ''),
            'import_status' => (string)($record['import_status'] ?? 'full'),
            'release_date' => (string)($record['release_date'] ?? $record['first_air_date'] ?? ''),
            'release_year' => self::extractYear($record),
            'age_rating' => self::ukAgeRating($record['age_rating'] ?? ''),
            'genres_text' => self::genresText($record),
            'search_text' => self::searchText($record),
            'vote_average' => isset($record['vote_average']) ? (float)$record['vote_average'] : null,
            'popularity' => isset($record['popularity']) ? (float)$record['popularity'] : null,
            'created_at' => (string)$record['created_at'],
            'updated_at' => (string)$record['updated_at'],
            'payload' => json_encode($payloadRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    private static function upsertStatement(): \PDOStatement
    {
        $pdoId = spl_object_id(self::pdo());
        if (isset(self::$upsertStatements[$pdoId])) return self::$upsertStatements[$pdoId];

        return self::$upsertStatements[$pdoId] = self::pdo()->prepare(
            'INSERT INTO records (bucket, id, slug, title, name, media_type, imdb_id, import_status, release_date, release_year, age_rating, genres_text, search_text, vote_average, popularity, created_at, updated_at, payload)
             VALUES (:bucket, :id, :slug, :title, :name, :media_type, :imdb_id, :import_status, :release_date, :release_year, :age_rating, :genres_text, :search_text, :vote_average, :popularity, :created_at, :updated_at, :payload)
             ON CONFLICT(bucket, id) DO UPDATE SET
                slug = excluded.slug,
                title = excluded.title,
                name = excluded.name,
                media_type = excluded.media_type,
                imdb_id = excluded.imdb_id,
                import_status = excluded.import_status,
                release_date = excluded.release_date,
                release_year = excluded.release_year,
                age_rating = excluded.age_rating,
                genres_text = excluded.genres_text,
                search_text = excluded.search_text,
                vote_average = excluded.vote_average,
                popularity = excluded.popularity,
                created_at = records.created_at,
                updated_at = excluded.updated_at,
                payload = excluded.payload
             WHERE records.payload IS NOT excluded.payload
                OR records.slug IS NOT excluded.slug
                OR records.import_status IS NOT excluded.import_status'
        );
    }

    public function delete(int|string $id): bool
    {
        $stmt = self::pdo()->prepare('DELETE FROM records WHERE bucket = :bucket AND id = :id');
        $stmt->execute(['bucket' => $this->bucket, 'id' => (string)$id]);
        return $stmt->rowCount() > 0;
    }

    public function count(): int
    {
        $stmt = self::pdo()->prepare('SELECT COUNT(*) FROM records WHERE bucket = :bucket');
        $stmt->execute(['bucket' => $this->bucket]);
        return (int)$stmt->fetchColumn();
    }

    public function countByStatus(string $status): int
    {
        $stmt = self::pdo()->prepare('SELECT COUNT(*) FROM records WHERE bucket = :bucket AND import_status = :status');
        $stmt->execute(['bucket' => $this->bucket, 'status' => $status]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Return IDs for records that still need a full import. Keeping this query
     * ID-only avoids decoding lots of JSON before the importer fetches full data.
     */
    public function idsByStatus(string $status, int $limit = 0): array
    {
        $sql = 'SELECT id FROM records WHERE bucket = :bucket AND import_status = :status ORDER BY updated_at DESC, id ASC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }

        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(['bucket' => $this->bucket, 'status' => $status]);
        return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    }


    /**
     * Return future-dated movies/TV for the Coming This Year page.
     * This intentionally bypasses the global released-only filters used by
     * normal listings/search/detail routes, while still requiring a real date.
     */
    public static function upcomingInYear(string $bucket, int $year): array
    {
        if (!in_array($bucket, ['movies', 'tv'], true)) return [];

        $today = gmdate('Y-m-d');
        $start = max($today, sprintf('%04d-01-01', $year));
        $end = sprintf('%04d-12-31', $year);
        if ($start > $end) return [];

        $stmt = self::pdo()->prepare(
            'SELECT payload, created_at, updated_at
             FROM records
             WHERE bucket = :bucket
               AND release_date IS NOT NULL
               AND release_date <> \'\'
               AND release_date > :today
               AND release_date BETWEEN :start AND :end
             ORDER BY release_date ASC, vote_average DESC, title COLLATE NOCASE ASC'
        );
        $stmt->execute([
            'bucket' => $bucket,
            'today' => $today,
            'start' => $start,
            'end' => $end,
        ]);

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $record = json_decode((string)$row['payload'], true);
            if (!is_array($record)) continue;
            $record['created_at'] = $record['created_at'] ?? $row['created_at'];
            $record['updated_at'] = $record['updated_at'] ?? $row['updated_at'];
            $items[] = $record;
        }
        return $items;
    }

    public function paginated(array $options): array
    {
        return self::queryBuckets([$this->bucket], $options);
    }

    public static function queryBuckets(array $buckets, array $options): array
    {
        $buckets = array_values(array_unique(array_filter(array_map('strval', $buckets))));
        if ($buckets === []) return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];

        $page = max(1, (int)($options['page'] ?? 1));
        $perPage = min(96, max(1, (int)($options['per_page'] ?? 24)));
        $offset = ($page - 1) * $perPage;

        $params = [];
        $bucketPlaceholders = [];
        foreach ($buckets as $i => $bucket) {
            $key = ':bucket' . $i;
            $bucketPlaceholders[] = $key;
            $params[$key] = $bucket;
        }

        $where = ['bucket IN (' . implode(',', $bucketPlaceholders) . ')'];
        $containsPeople = in_array('people', $buckets, true);
        $mediaOnly = !$containsPeople;
        if ($mediaOnly) {
            $where[] = "(release_date IS NOT NULL AND release_date <> '' AND release_date <= :today)";
            $params[':today'] = gmdate('Y-m-d');
        }

        $query = trim((string)($options['query'] ?? ''));
        if ($query !== '') {
            $where[] = 'search_text LIKE :query';
            $params[':query'] = '%' . strtolower($query) . '%';
        }

        if ($mediaOnly) {
            $genre = trim((string)($options['genre'] ?? ''));
            if ($genre !== '') {
                $where[] = 'genres_text LIKE :genre';
                $params[':genre'] = '%|' . $genre . '|%';
            }

            $rating = self::ukAgeRating($options['rating'] ?? '');
            if ($rating !== '') {
                $aliases = self::ukAgeRatingAliases($rating);
                $ratingPlaceholders = [];
                foreach ($aliases as $i => $alias) {
                    $key = ':rating' . $i;
                    $ratingPlaceholders[] = $key;
                    $params[$key] = $alias;
                }
                $where[] = 'age_rating IN (' . implode(',', $ratingPlaceholders) . ')';
            }

            $userRating = trim((string)($options['user_rating'] ?? ($options['score'] ?? '')));
            if ($userRating !== '' && is_numeric($userRating)) {
                $minimumUserRating = max(0.0, min(10.0, floor(((float)$userRating) * 2) / 2));
                $maximumUserRating = $minimumUserRating + 0.5;
                if ($minimumUserRating >= 10.0) {
                    $where[] = 'COALESCE(vote_average, 0) >= :user_rating_min';
                    $params[':user_rating_min'] = '10.0';
                } else {
                    $where[] = '(COALESCE(vote_average, 0) >= :user_rating_min AND COALESCE(vote_average, 0) < :user_rating_max)';
                    $params[':user_rating_min'] = number_format($minimumUserRating, 1, '.', '');
                    $params[':user_rating_max'] = number_format($maximumUserRating, 1, '.', '');
                }
            }

            $year = trim((string)($options['year'] ?? ''));
            if ($year !== '') {
                $where[] = 'release_year = :year';
                $params[':year'] = $year;
            }
        } elseif (trim((string)($options['genre'] ?? '')) !== '' || trim((string)($options['rating'] ?? '')) !== '' || (trim((string)($options['user_rating'] ?? '')) !== '' || trim((string)($options['score'] ?? '')) !== '') || trim((string)($options['year'] ?? '')) !== '') {
            return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        }

        $whereSql = implode(' AND ', $where);
        $pdo = self::pdo();

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM records WHERE ' . $whereSql);
        foreach ($params as $key => $value) $countStmt->bindValue($key, $value, PDO::PARAM_STR);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $orderBy = self::orderByForSort((string)($options['sort'] ?? 'title_asc'), $containsPeople);
        $stmt = $pdo->prepare('SELECT payload, created_at, updated_at FROM records WHERE ' . $whereSql . ' ORDER BY ' . $orderBy . ' LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) $stmt->bindValue($key, $value, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $record = json_decode((string)$row['payload'], true);
            if (!is_array($record)) continue;
            $record['created_at'] = $record['created_at'] ?? $row['created_at'];
            $record['updated_at'] = $record['updated_at'] ?? $row['updated_at'];
            $items[] = $record;
        }

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }


    public static function liveSearch(string $query, int $limit = 6): array
    {
        $query = trim(preg_replace('/\s+/', ' ', $query) ?: '');
        $limit = min(6, max(1, $limit));
        if ($query === '' || (function_exists('mb_strlen') ? mb_strlen($query) : strlen($query)) < 2) return [];

        $pdo = self::pdo();
        $today = gmdate('Y-m-d');
        $like = '%' . strtolower($query) . '%';
        $prefix = strtolower($query) . '%';
        $exact = strtolower($query);

        $stmt = $pdo->prepare(
            "SELECT payload, bucket, import_status, created_at, updated_at,
                    COALESCE(NULLIF(title, ''), NULLIF(name, '')) AS display_title,
                    release_year, vote_average, popularity
             FROM records
             WHERE bucket IN ('movies', 'tv', 'people')
               AND search_text LIKE :like
               AND (bucket = 'people' OR (release_date IS NOT NULL AND release_date <> '' AND release_date <= :today))
             ORDER BY
               CASE
                 WHEN lower(COALESCE(NULLIF(title, ''), NULLIF(name, ''))) = :exact THEN 0
                 WHEN lower(COALESCE(NULLIF(title, ''), NULLIF(name, ''))) LIKE :prefix THEN 1
                 ELSE 2
               END ASC,
               popularity DESC,
               vote_average DESC,
               display_title COLLATE NOCASE ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':like', $like, PDO::PARAM_STR);
        $stmt->bindValue(':today', $today, PDO::PARAM_STR);
        $stmt->bindValue(':exact', $exact, PDO::PARAM_STR);
        $stmt->bindValue(':prefix', $prefix, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $record = json_decode((string)$row['payload'], true);
            if (!is_array($record)) continue;
            $record['created_at'] = $record['created_at'] ?? $row['created_at'];
            $record['updated_at'] = $record['updated_at'] ?? $row['updated_at'];
            $record['_bucket'] = (string)$row['bucket'];
            $record['_import_status'] = (string)($row['import_status'] ?? '');
            $items[] = $record;
        }
        return $items;
    }

    /**
     * Return random released records from a bucket. Used by the homepage
     * carousel so it can rotate through the full local movie database rather
     * than only the latest TMDB trending/recent API results.
     */
    public static function randomReleasedFromBucket(string $bucket, int $limit = 10, bool $requirePoster = false, ?float $minimumRating = null): array
    {
        $bucket = trim($bucket);
        $limit = min(50, max(1, $limit));
        if (!in_array($bucket, ['movies', 'tv'], true)) return [];

        $pdo = self::pdo();
        $today = gmdate('Y-m-d');

        // Pull a larger random candidate set when a poster is required because
        // poster_path lives in the JSON payload. We then apply the poster guard
        // after decoding without affecting normal listings/search results.
        $candidateLimit = $requirePoster ? max($limit * 8, 40) : $limit;

        $stmt = $pdo->prepare(
            "SELECT payload, created_at, updated_at
             FROM records
             WHERE bucket = :bucket
               AND (release_date IS NOT NULL AND release_date <> '' AND release_date <= :today)
               AND (:minimum_rating IS NULL OR COALESCE(vote_average, 0) >= :minimum_rating)
             ORDER BY RANDOM()
             LIMIT :limit"
        );
        $stmt->bindValue(':bucket', $bucket, PDO::PARAM_STR);
        $stmt->bindValue(':today', $today, PDO::PARAM_STR);
        if ($minimumRating === null) {
            $stmt->bindValue(':minimum_rating', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':minimum_rating', $minimumRating);
        }
        $stmt->bindValue(':limit', $candidateLimit, PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $record = json_decode((string)$row['payload'], true);
            if (!is_array($record)) continue;
            if ($requirePoster && !self::recordHasUsablePoster($record)) continue;
            $record['created_at'] = $record['created_at'] ?? $row['created_at'];
            $record['updated_at'] = $record['updated_at'] ?? $row['updated_at'];
            $items[] = $record;
            if (count($items) >= $limit) break;
        }

        return $items;
    }

    private static function recordHasUsablePoster(array $record): bool
    {
        $poster = trim((string)($record['poster_path'] ?? ''));
        if ($poster === '') return false;

        return !str_contains($poster, 'placeholder.jpg')
            && !str_contains($poster, 'placeholder.svg');
    }

    public function distinctValues(string $field): array
    {
        $column = match ($field) {
            'age_rating' => 'age_rating',
            'release_year', 'year' => 'release_year',
            default => null,
        };

        if ($column === null) return [];
        $stmt = self::pdo()->prepare("SELECT DISTINCT {$column} AS value FROM records WHERE bucket = :bucket AND {$column} IS NOT NULL AND {$column} != '' ORDER BY {$column} COLLATE NOCASE ASC");
        $stmt->execute(['bucket' => $this->bucket]);
        return array_values(array_filter(array_map(static fn(array $row): string => (string)$row['value'], $stmt->fetchAll(PDO::FETCH_ASSOC)), static fn(string $value): bool => $value !== ''));
    }

    public function distinctGenres(): array
    {
        $stmt = self::pdo()->prepare("SELECT genres_text FROM records WHERE bucket = :bucket AND genres_text IS NOT NULL AND genres_text != ''");
        $stmt->execute(['bucket' => $this->bucket]);
        $genres = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            foreach (explode('|', trim((string)$row['genres_text'], '|')) as $genre) {
                $genre = trim($genre);
                if ($genre !== '') $genres[$genre] = $genre;
            }
        }
        natcasesort($genres);
        return array_values($genres);
    }

    public static function connection(): PDO
    {
        return self::pdo();
    }

    public static function databasePath(): string
    {
        $configured = (string)Config::get('SQLITE_PATH', '');
        return $configured !== '' ? $configured : storage_path('database.sqlite');
    }

    public static function stats(): array
    {
        $pdo = self::pdo();
        $path = self::databasePath();
        $stats = [
            'path' => $path,
            'size_bytes' => is_file($path) ? filesize($path) : 0,
            'buckets' => [],
        ];

        foreach (['movies', 'tv', 'people'] as $bucket) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM records WHERE bucket = :bucket');
            $stmt->execute(['bucket' => $bucket]);
            $stats['buckets'][$bucket] = (int)$stmt->fetchColumn();
        }
        return $stats;
    }

    private static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;

        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('SQLite is not enabled. Enable the PHP pdo_sqlite and sqlite3 extensions, then restart Apache/PHP.');
        }

        $path = self::databasePath();
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        self::$pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        self::applyPragmas();

        self::ensureSchema();
        return self::$pdo;
    }


    /**
     * SQLite settings are tuned for this app's mostly single-writer cache workflow.
     *
     * WAL writes to database.sqlite-wal and DELETE/TRUNCATE writes to
     * database.sqlite-journal. On some hosts those sidecar files are the slowest part
     * of fetching/importing content. Default to MEMORY journaling so rollback data
     * stays in RAM instead of creating a -wal or -journal file on disk.
     *
     * For maximum crash safety, set SQLITE_JOURNAL_MODE=DELETE and
     * SQLITE_SYNCHRONOUS=FULL in .env. For this movie-cache app, MEMORY + NORMAL is
     * the faster default and avoids the slow sidecar files the UI was waiting on.
     */
    private static function applyPragmas(): void
    {
        $journalMode = strtoupper(trim((string)Config::get('SQLITE_JOURNAL_MODE', 'MEMORY')));
        $allowedModes = ['DELETE', 'TRUNCATE', 'PERSIST', 'MEMORY', 'WAL', 'OFF'];
        if (!in_array($journalMode, $allowedModes, true)) $journalMode = 'MEMORY';

        $synchronous = strtoupper(trim((string)Config::get('SQLITE_SYNCHRONOUS', 'NORMAL')));
        $allowedSync = ['OFF', 'NORMAL', 'FULL', 'EXTRA'];
        if (!in_array($synchronous, $allowedSync, true)) $synchronous = 'NORMAL';

        $busyTimeout = max(1000, min(30000, (int)Config::get('SQLITE_BUSY_TIMEOUT_MS', 10000)));

        self::$pdo->exec('PRAGMA journal_mode = ' . $journalMode);
        self::$pdo->exec('PRAGMA synchronous = ' . $synchronous);
        self::$pdo->exec('PRAGMA busy_timeout = ' . $busyTimeout);
        self::$pdo->exec('PRAGMA foreign_keys = ON');
        self::$pdo->exec('PRAGMA temp_store = MEMORY');
        self::$pdo->exec('PRAGMA cache_size = -20000');
        self::$pdo->exec('PRAGMA mmap_size = 268435456');
    }

    public static function waitUntilRecordReadable(string $bucket, string $slug, int $timeoutMs = 2500): bool
    {
        $deadline = microtime(true) + ($timeoutMs / 1000);
        $stmt = self::pdo()->prepare("SELECT 1 FROM records WHERE bucket = :bucket AND slug = :slug LIMIT 1");

        do {
            try {
                $stmt->execute(['bucket' => $bucket, 'slug' => $slug]);
                if ((bool)$stmt->fetchColumn()) return true;
            } catch (\Throwable) {
                // A short-lived lock immediately after importing should not send users
                // to a 500 page. Keep retrying briefly, then let the caller decide.
            }
            usleep(50000);
        } while (microtime(true) < $deadline);

        return false;
    }

    private static function ensureSchema(): void
    {
        if (self::$schemaReady) return;

        self::$pdo->exec(
            'CREATE TABLE IF NOT EXISTS records (
                bucket TEXT NOT NULL,
                id TEXT NOT NULL,
                slug TEXT NOT NULL,
                title TEXT DEFAULT NULL,
                name TEXT DEFAULT NULL,
                media_type TEXT DEFAULT NULL,
                imdb_id TEXT DEFAULT NULL,
                import_status TEXT DEFAULT NULL,
                release_date TEXT DEFAULT NULL,
                release_year TEXT DEFAULT NULL,
                age_rating TEXT DEFAULT NULL,
                genres_text TEXT DEFAULT NULL,
                search_text TEXT DEFAULT NULL,
                vote_average REAL DEFAULT NULL,
                popularity REAL DEFAULT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                payload TEXT NOT NULL,
                PRIMARY KEY (bucket, id)
            )'
        );
        $columns = array_map(static fn(array $row): string => (string)$row['name'], self::$pdo->query('PRAGMA table_info(records)')->fetchAll(PDO::FETCH_ASSOC));
        if (!in_array('imdb_id', $columns, true)) self::$pdo->exec('ALTER TABLE records ADD COLUMN imdb_id TEXT DEFAULT NULL');
        if (!in_array('release_year', $columns, true)) self::$pdo->exec('ALTER TABLE records ADD COLUMN release_year TEXT DEFAULT NULL');
        if (!in_array('age_rating', $columns, true)) self::$pdo->exec('ALTER TABLE records ADD COLUMN age_rating TEXT DEFAULT NULL');
        if (!in_array('genres_text', $columns, true)) self::$pdo->exec('ALTER TABLE records ADD COLUMN genres_text TEXT DEFAULT NULL');
        if (!in_array('search_text', $columns, true)) self::$pdo->exec('ALTER TABLE records ADD COLUMN search_text TEXT DEFAULT NULL');
        if (!in_array('popularity', $columns, true)) self::$pdo->exec('ALTER TABLE records ADD COLUMN popularity REAL DEFAULT NULL');

        self::$pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_meta (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )'
        );
        self::backfillDerivedColumnsOnce();

        self::$pdo->exec(
            'CREATE TABLE IF NOT EXISTS tmdb_cache (
                cache_key TEXT PRIMARY KEY,
                response TEXT NOT NULL,
                expires_at INTEGER NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_tmdb_cache_expires ON tmdb_cache(expires_at)');

        self::$pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_records_bucket_slug ON records(bucket, slug)');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_records_bucket_updated ON records(bucket, updated_at)');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_records_bucket_title ON records(bucket, title)');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_records_bucket_name ON records(bucket, name)');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_records_bucket_release ON records(bucket, release_date)');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_records_bucket_imdb ON records(bucket, imdb_id)');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_records_bucket_status ON records(bucket, import_status)');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_records_bucket_year ON records(bucket, release_year)');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_records_bucket_rating ON records(bucket, age_rating)');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_records_bucket_vote ON records(bucket, vote_average)');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_records_bucket_popularity ON records(bucket, popularity)');
        self::$schemaReady = true;
    }

    private function migrateJsonBucketOnce(): void
    {
        if (isset(self::$migratedBuckets[$this->bucket])) return;
        self::$migratedBuckets[$this->bucket] = true;

        if ($this->count() > 0) return;

        $dir = storage_path($this->bucket);
        $files = is_dir($dir) ? (glob($dir . '/*.json') ?: []) : [];
        if (!$files) return;

        sort($files, SORT_NATURAL);
        $batch = [];
        foreach ($files as $file) {
            $rows = json_decode((string)file_get_contents($file), true);
            if (!is_array($rows)) continue;
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['id'])) $batch[] = $row;
                if (count($batch) >= 500) {
                    $this->upsertMany($batch);
                    $batch = [];
                }
            }
        }
        if ($batch) $this->upsertMany($batch);
    }

    private function rowToRecord(array $row): array
    {
        $record = $this->decode((string)$row['payload']);
        $record['created_at'] = $record['created_at'] ?? $row['created_at'];
        $record['updated_at'] = $record['updated_at'] ?? $row['updated_at'];
        return $record;
    }

    private function decode(string $json): array
    {
        $record = json_decode($json, true);
        if (!is_array($record)) return [];
        if (($record['media_type'] ?? '') !== 'person' && array_key_exists('age_rating', $record)) {
            $record['age_rating'] = self::ukAgeRating($record['age_rating']);
        }
        return $record;
    }

    private static function orderByForSort(string $sort, bool $containsPeople = false): string
    {
        $nameExpr = $containsPeople ? "COALESCE(NULLIF(name, ''), NULLIF(title, ''), '')" : "COALESCE(NULLIF(title, ''), NULLIF(name, ''), '')";
        return match ($sort) {
            'title_desc' => $nameExpr . ' COLLATE NOCASE DESC, rowid DESC',
            'date_asc' => "COALESCE(release_date, '') ASC, {$nameExpr} COLLATE NOCASE ASC",
            'date_desc' => "COALESCE(release_date, '') DESC, {$nameExpr} COLLATE NOCASE ASC",
            'rating_asc' => 'COALESCE(vote_average, 0) ASC, ' . $nameExpr . ' COLLATE NOCASE ASC',
            'rating_desc' => 'COALESCE(vote_average, 0) DESC, ' . $nameExpr . ' COLLATE NOCASE ASC',
            'updated_desc' => 'updated_at DESC, rowid DESC',
            'popular_desc' => 'COALESCE(popularity, 0) DESC, COALESCE(vote_average, 0) DESC, rowid DESC',
            default => $nameExpr . ' COLLATE NOCASE ASC, rowid ASC',
        };
    }


    private static function ukAgeRating(int|string|null $rating): string
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

    private static function ukAgeRatingAliases(string $ukRating): array
    {
        return match ($ukRating) {
            'U' => ['U', 'G', 'TV-G', 'TV-Y'],
            'PG' => ['PG', 'TV-PG', 'TV-Y7', 'TV-Y7-FV'],
            '12' => ['12'],
            '12A' => ['12A', 'PG-13'],
            '15' => ['15', 'R', 'TV-14', 'M'],
            '18' => ['18', 'NC-17', 'X', 'TV-MA'],
            default => [],
        };
    }

    private static function extractYear(array $record): string
    {
        $date = (string)($record['release_date'] ?? $record['first_air_date'] ?? '');
        return preg_match('/^\d{4}/', $date, $m) ? $m[0] : '';
    }

    private static function genresText(array $record): string
    {
        $genres = [];
        foreach (($record['genres'] ?? []) as $genre) {
            if (is_array($genre)) $genre = (string)($genre['name'] ?? '');
            $genre = trim((string)$genre);
            if ($genre !== '') $genres[strtolower($genre)] = $genre;
        }
        return $genres ? '|' . implode('|', array_values($genres)) . '|' : '';
    }

    private static function searchText(array $record): string
    {
        $parts = [
            (string)($record['title'] ?? ''),
            (string)($record['name'] ?? ''),
            (string)($record['overview'] ?? ''),
            (string)($record['biography'] ?? ''),
            (string)($record['known_for_department'] ?? ''),
        ];
        foreach (($record['genres'] ?? []) as $genre) $parts[] = is_array($genre) ? (string)($genre['name'] ?? '') : (string)$genre;
        foreach (($record['known_for'] ?? []) as $credit) if (is_array($credit)) $parts[] = (string)($credit['title'] ?? $credit['name'] ?? '');
        foreach (($record['credits'] ?? []) as $credit) if (is_array($credit)) $parts[] = (string)($credit['title'] ?? $credit['name'] ?? '');
        return strtolower(trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($parts)))));
    }

    private static function backfillDerivedColumnsOnce(): void
    {
        $version = (string)(self::$pdo->query("SELECT value FROM schema_meta WHERE key = 'derived_columns_backfilled_v2'")->fetchColumn() ?: '');
        if ($version === '1') return;

        $needsBackfill = (int)self::$pdo->query("SELECT COUNT(*) FROM records WHERE search_text IS NULL OR release_year IS NULL OR age_rating IS NULL OR genres_text IS NULL")->fetchColumn();
        if ($needsBackfill < 1) {
            self::$pdo->exec("INSERT INTO schema_meta (key, value) VALUES ('derived_columns_backfilled_v2', '1') ON CONFLICT(key) DO UPDATE SET value = excluded.value");
            return;
        }

        $stmt = self::$pdo->query("SELECT bucket, id, payload FROM records WHERE search_text IS NULL OR release_year IS NULL OR age_rating IS NULL OR genres_text IS NULL");
        $update = self::$pdo->prepare('UPDATE records SET release_year = :release_year, age_rating = :age_rating, genres_text = :genres_text, search_text = :search_text, popularity = :popularity WHERE bucket = :bucket AND id = :id');

        self::$pdo->beginTransaction();
        try {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $record = json_decode((string)$row['payload'], true);
                if (!is_array($record)) continue;
                $update->execute([
                    'release_year' => self::extractYear($record),
                    'age_rating' => self::ukAgeRating($record['age_rating'] ?? ''),
                    'genres_text' => self::genresText($record),
                    'search_text' => self::searchText($record),
                    'popularity' => isset($record['popularity']) ? (float)$record['popularity'] : null,
                    'bucket' => (string)$row['bucket'],
                    'id' => (string)$row['id'],
                ]);
            }
            self::$pdo->exec("INSERT INTO schema_meta (key, value) VALUES ('derived_columns_backfilled_v2', '1') ON CONFLICT(key) DO UPDATE SET value = excluded.value");
            self::$pdo->commit();
        } catch (\Throwable $e) {
            self::$pdo->rollBack();
            throw $e;
        }
    }

    private function mediaTypeForBucket(): string
    {
        return match ($this->bucket) {
            'movies' => 'movie',
            'people' => 'person',
            default => $this->bucket,
        };
    }
}
