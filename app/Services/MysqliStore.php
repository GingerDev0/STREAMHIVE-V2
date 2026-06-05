<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use mysqli;
use RuntimeException;

final class MysqliStore
{
    private static ?mysqli $db = null;
    private static bool $schemaReady = false;
    private static int $transactionDepth = 0;
    private static array $migratedBuckets = [];
    private static ?bool $fullTextReady = null;

    public function __construct(private readonly string $bucket)
    {
        self::connection();
        $this->migrateJsonBucketOnce();
    }

    public function all(): array
    {
        $rows = self::fetchAll(
            'SELECT payload, created_at, updated_at FROM records WHERE bucket = ? ORDER BY updated_at DESC, auto_id DESC',
            [$this->bucket]
        );

        return array_map(fn(array $row): array => $this->rowToRecord($row), $rows);
    }

    public function find(int|string $id): ?array
    {
        $row = self::fetchOne(
            'SELECT payload, created_at, updated_at FROM records WHERE bucket = ? AND id = ? LIMIT 1',
            [$this->bucket, (string)$id]
        );
        return $row ? $this->rowToRecord($row) : null;
    }

    public function findBy(string $field, mixed $value): ?array
    {
        if ($field === 'id') return $this->find((string)$value);

        if (in_array($field, ['slug', 'tmdb_id', 'imdb_id', 'import_status'], true)) {
            $column = $field === 'tmdb_id' ? 'id' : $field;
            $row = self::fetchOne(
                "SELECT payload, created_at, updated_at FROM records WHERE bucket = ? AND {$column} = ? LIMIT 1",
                [$this->bucket, (string)$value]
            );
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
        $db = self::connection();
        if (self::$transactionDepth > 0) {
            self::$transactionDepth++;
            try {
                return $callback();
            } finally {
                self::$transactionDepth--;
            }
        }

        self::$transactionDepth = 1;
        $db->begin_transaction();
        try {
            $result = $callback();
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        } finally {
            self::$transactionDepth = 0;
        }
    }

    public function idsWithStatus(array $ids, string $status): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids), static fn(string $id): bool => $id !== '')));
        if ($ids === []) return [];

        $found = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $rows = self::fetchAll(
                "SELECT id FROM records WHERE bucket = ? AND import_status = ? AND id IN ({$placeholders})",
                array_merge([$this->bucket, $status], $chunk)
            );
            foreach ($rows as $row) $found[(string)$row['id']] = true;
        }
        return $found;
    }

    public function slugForId(int|string $id): ?string
    {
        $row = self::fetchOne('SELECT slug FROM records WHERE bucket = ? AND id = ? LIMIT 1', [$this->bucket, (string)$id]);
        $slug = (string)($row['slug'] ?? '');
        return $slug !== '' ? $slug : null;
    }

    public function slugExists(string $slug, int|string|null $excludeId = null): bool
    {
        if ($excludeId !== null && (string)$excludeId !== '') {
            return self::scalar(
                'SELECT 1 FROM records WHERE bucket = ? AND slug = ? AND id != ? LIMIT 1',
                [$this->bucket, $slug, (string)$excludeId]
            ) !== null;
        }

        return self::scalar(
            'SELECT 1 FROM records WHERE bucket = ? AND slug = ? LIMIT 1',
            [$this->bucket, $slug]
        ) !== null;
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

        self::execute(
            'INSERT INTO records (bucket, id, slug, title, name, media_type, imdb_id, import_status, release_date, release_year, age_rating, genres_text, search_text, vote_average, popularity, created_at, updated_at, payload)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                slug = VALUES(slug),
                title = VALUES(title),
                name = VALUES(name),
                media_type = VALUES(media_type),
                imdb_id = VALUES(imdb_id),
                import_status = VALUES(import_status),
                release_date = VALUES(release_date),
                release_year = VALUES(release_year),
                age_rating = VALUES(age_rating),
                genres_text = VALUES(genres_text),
                search_text = VALUES(search_text),
                vote_average = VALUES(vote_average),
                popularity = VALUES(popularity),
                updated_at = VALUES(updated_at),
                payload = VALUES(payload)',
            [
                $this->bucket,
                (string)$record['id'],
                (string)($record['slug'] ?? ''),
                (string)($record['title'] ?? ''),
                (string)($record['name'] ?? ''),
                (string)($record['media_type'] ?? $this->mediaTypeForBucket()),
                (string)($record['imdb_id'] ?? ''),
                (string)($record['import_status'] ?? 'full'),
                (string)($record['release_date'] ?? $record['first_air_date'] ?? ''),
                self::extractYear($record),
                self::ukAgeRating($record['age_rating'] ?? ''),
                self::genresText($record),
                self::searchText($record),
                isset($record['vote_average']) ? (float)$record['vote_average'] : null,
                isset($record['popularity']) ? (float)$record['popularity'] : null,
                (string)$record['created_at'],
                (string)$record['updated_at'],
                json_encode($payloadRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    public function delete(int|string $id): bool
    {
        $stmt = self::execute('DELETE FROM records WHERE bucket = ? AND id = ?', [$this->bucket, (string)$id]);
        return $stmt->affected_rows > 0;
    }

    public function count(): int
    {
        return (int)self::scalar('SELECT COUNT(*) FROM records WHERE bucket = ?', [$this->bucket]);
    }

    public function countByStatus(string $status): int
    {
        return (int)self::scalar('SELECT COUNT(*) FROM records WHERE bucket = ? AND import_status = ?', [$this->bucket, $status]);
    }

    public function idsByStatus(string $status, int $limit = 0): array
    {
        $sql = 'SELECT id FROM records WHERE bucket = ? AND import_status = ? ORDER BY updated_at DESC, id ASC';
        if ($limit > 0) $sql .= ' LIMIT ' . max(1, $limit);

        return array_values(array_filter(array_map('strval', array_column(self::fetchAll($sql, [$this->bucket, $status]), 'id'))));
    }

    public static function upcomingInYear(string $bucket, int $year): array
    {
        if (!in_array($bucket, ['movies', 'tv'], true)) return [];

        $today = gmdate('Y-m-d');
        $start = max($today, sprintf('%04d-01-01', $year));
        $end = sprintf('%04d-12-31', $year);
        if ($start > $end) return [];

        $rows = self::fetchAll(
            "SELECT payload, created_at, updated_at
             FROM records
             WHERE bucket = ?
               AND release_date IS NOT NULL
               AND release_date <> ''
               AND release_date > ?
               AND release_date BETWEEN ? AND ?
             ORDER BY release_date ASC, vote_average DESC, title ASC",
            [$bucket, $today, $start, $end]
        );

        return self::rowsToRecords($rows);
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

        $where = ['bucket IN (' . implode(',', array_fill(0, count($buckets), '?')) . ')'];
        $params = $buckets;
        $containsPeople = in_array('people', $buckets, true);
        $mediaOnly = !$containsPeople;

        if ($mediaOnly) {
            $where[] = "(release_date IS NOT NULL AND release_date <> '' AND release_date <= ?)";
            $params[] = gmdate('Y-m-d');
        }

        $query = trim((string)($options['query'] ?? ''));
        $fullTextQuery = '';
        if ($query !== '') {
            $fullTextQuery = self::booleanSearchQuery($query);
            if ($fullTextQuery !== '' && self::fullTextReady()) {
                $where[] = 'MATCH(search_text) AGAINST (? IN BOOLEAN MODE)';
                $params[] = $fullTextQuery;
            } else {
                $where[] = 'search_text LIKE ?';
                $params[] = '%' . strtolower($query) . '%';
            }
        }

        if ($mediaOnly) {
            $genre = trim((string)($options['genre'] ?? ''));
            if ($genre !== '') {
                $where[] = 'genres_text LIKE ?';
                $params[] = '%|' . $genre . '|%';
            }

            $rating = self::ukAgeRating($options['rating'] ?? '');
            if ($rating !== '') {
                $aliases = self::ukAgeRatingAliases($rating);
                $where[] = 'age_rating IN (' . implode(',', array_fill(0, count($aliases), '?')) . ')';
                array_push($params, ...$aliases);
            }

            $userRating = trim((string)($options['user_rating'] ?? ($options['score'] ?? '')));
            if ($userRating !== '' && is_numeric($userRating)) {
                $minimumUserRating = max(0.0, min(10.0, floor(((float)$userRating) * 2) / 2));
                $maximumUserRating = $minimumUserRating + 0.5;
                if ($minimumUserRating >= 10.0) {
                    $where[] = 'COALESCE(vote_average, 0) >= ?';
                    $params[] = 10.0;
                } else {
                    $where[] = '(COALESCE(vote_average, 0) >= ? AND COALESCE(vote_average, 0) < ?)';
                    $params[] = $minimumUserRating;
                    $params[] = $maximumUserRating;
                }
            }

            $year = trim((string)($options['year'] ?? ''));
            if ($year !== '') {
                $where[] = 'release_year = ?';
                $params[] = $year;
            }
        } elseif (trim((string)($options['genre'] ?? '')) !== '' || trim((string)($options['rating'] ?? '')) !== '' || trim((string)($options['user_rating'] ?? '')) !== '' || trim((string)($options['score'] ?? '')) !== '' || trim((string)($options['year'] ?? '')) !== '') {
            return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        }

        $whereSql = implode(' AND ', $where);
        $total = (int)self::scalar('SELECT COUNT(*) FROM records WHERE ' . $whereSql, $params);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $orderBy = self::orderByForSort((string)($options['sort'] ?? 'title_asc'), $containsPeople);
        if ($fullTextQuery !== '' && self::fullTextReady() && (string)($options['sort'] ?? '') === '') {
            $orderBy = 'MATCH(search_text) AGAINST (? IN BOOLEAN MODE) DESC, ' . $orderBy;
            $rows = self::fetchAll(
                'SELECT payload, created_at, updated_at FROM records WHERE ' . $whereSql . ' ORDER BY ' . $orderBy . ' LIMIT ? OFFSET ?',
                array_merge($params, [$fullTextQuery, $perPage, $offset])
            );
        } else {
            $rows = self::fetchAll(
                'SELECT payload, created_at, updated_at FROM records WHERE ' . $whereSql . ' ORDER BY ' . $orderBy . ' LIMIT ? OFFSET ?',
                array_merge($params, [$perPage, $offset])
            );
        }

        return ['items' => self::rowsToRecords($rows), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public static function liveSearch(string $query, int $limit = 6): array
    {
        $query = trim(preg_replace('/\s+/', ' ', $query) ?: '');
        $limit = min(6, max(1, $limit));
        if ($query === '' || (function_exists('mb_strlen') ? mb_strlen($query) : strlen($query)) < 2) return [];

        $fullTextQuery = self::booleanSearchQuery($query);
        $useFullText = $fullTextQuery !== '' && self::fullTextReady();
        $searchWhere = $useFullText ? 'MATCH(search_text) AGAINST (? IN BOOLEAN MODE)' : 'search_text LIKE ?';
        $searchParam = $useFullText ? $fullTextQuery : '%' . strtolower($query) . '%';
        $relevanceSelect = $useFullText ? 'MATCH(search_text) AGAINST (? IN BOOLEAN MODE)' : '0';
        $relevanceOrder = $useFullText ? 'relevance DESC,' : '';
        $params = $useFullText
            ? [$fullTextQuery, $searchParam, gmdate('Y-m-d'), strtolower($query), strtolower($query) . '%', $limit]
            : [$searchParam, gmdate('Y-m-d'), strtolower($query), strtolower($query) . '%', $limit];

        $rows = self::fetchAll(
            "SELECT payload, bucket, import_status, created_at, updated_at,
                    COALESCE(NULLIF(title, ''), NULLIF(name, '')) AS display_title,
                    release_year, vote_average, popularity,
                    {$relevanceSelect} AS relevance
             FROM records
             WHERE bucket IN ('movies', 'tv', 'people')
               AND {$searchWhere}
               AND (bucket = 'people' OR (release_date IS NOT NULL AND release_date <> '' AND release_date <= ?))
             ORDER BY
               CASE
                 WHEN LOWER(COALESCE(NULLIF(title, ''), NULLIF(name, ''))) = ? THEN 0
                 WHEN LOWER(COALESCE(NULLIF(title, ''), NULLIF(name, ''))) LIKE ? THEN 1
                 ELSE 2
               END ASC,
               {$relevanceOrder}
               popularity DESC,
               vote_average DESC,
               display_title ASC
             LIMIT ?",
            $params
        );

        $items = [];
        foreach ($rows as $row) {
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

    public static function randomReleasedFromBucket(string $bucket, int $limit = 10, bool $requirePoster = false, ?float $minimumRating = null): array
    {
        $bucket = trim($bucket);
        $limit = min(50, max(1, $limit));
        if (!in_array($bucket, ['movies', 'tv'], true)) return [];

        $candidateLimit = $requirePoster ? max($limit * 8, 40) : $limit;
        $today = gmdate('Y-m-d');
        $bounds = self::fetchOne(
            "SELECT MIN(auto_id) AS min_id, MAX(auto_id) AS max_id
             FROM records
             WHERE bucket = ?
               AND release_date IS NOT NULL
               AND release_date <> ''
               AND release_date <= ?
               AND (? IS NULL OR vote_average >= ?)",
            [$bucket, $today, $minimumRating, $minimumRating]
        );
        $minId = (int)($bounds['min_id'] ?? 0);
        $maxId = (int)($bounds['max_id'] ?? 0);
        if ($minId < 1 || $maxId < 1) return [];

        $startId = random_int($minId, $maxId);
        $rows = self::fetchAll(
            "SELECT payload, created_at, updated_at
             FROM records
             WHERE bucket = ?
               AND auto_id >= ?
               AND release_date IS NOT NULL
               AND release_date <> ''
               AND release_date <= ?
               AND (? IS NULL OR vote_average >= ?)
             ORDER BY auto_id ASC
             LIMIT ?",
            [$bucket, $startId, $today, $minimumRating, $minimumRating, $candidateLimit]
        );

        if (count($rows) < $candidateLimit) {
            $rows = array_merge($rows, self::fetchAll(
                "SELECT payload, created_at, updated_at
                 FROM records
                 WHERE bucket = ?
                   AND auto_id < ?
                   AND release_date IS NOT NULL
                   AND release_date <> ''
                   AND release_date <= ?
                   AND (? IS NULL OR vote_average >= ?)
                 ORDER BY auto_id ASC
                 LIMIT ?",
                [$bucket, $startId, $today, $minimumRating, $minimumRating, $candidateLimit - count($rows)]
            ));
        }

        $items = [];
        foreach ($rows as $row) {
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

    public static function relatedCandidates(string $bucket, array $genres, string $excludeId = '', string $excludeSlug = '', int $limit = 80): array
    {
        if (!in_array($bucket, ['movies', 'tv'], true)) return [];

        $limit = min(200, max(12, $limit));
        $where = [
            'bucket = ?',
            "release_date IS NOT NULL",
            "release_date <> ''",
            'release_date <= ?',
        ];
        $params = [$bucket, gmdate('Y-m-d')];

        if ($excludeId !== '') {
            $where[] = 'id != ?';
            $params[] = $excludeId;
        }
        if ($excludeSlug !== '') {
            $where[] = 'slug != ?';
            $params[] = $excludeSlug;
        }

        $genreTerms = [];
        foreach (array_slice(array_values(array_unique(array_filter(array_map('strval', $genres)))), 0, 6) as $genre) {
            $genre = trim($genre);
            if ($genre === '') continue;
            $genreTerms[] = 'genres_text LIKE ?';
            $params[] = '%|' . $genre . '|%';
        }

        if ($genreTerms) {
            $where[] = '(' . implode(' OR ', $genreTerms) . ')';
        }

        $rows = self::fetchAll(
            'SELECT payload, created_at, updated_at
             FROM records
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY COALESCE(popularity, 0) DESC, COALESCE(vote_average, 0) DESC, release_date DESC
             LIMIT ?',
            array_merge($params, [$limit])
        );

        return self::rowsToRecords($rows);
    }

    public function distinctValues(string $field): array
    {
        $column = match ($field) {
            'age_rating' => 'age_rating',
            'release_year', 'year' => 'release_year',
            default => null,
        };

        if ($column === null) return [];

        $rows = self::fetchAll("SELECT DISTINCT {$column} AS value FROM records WHERE bucket = ? AND {$column} IS NOT NULL AND {$column} != '' ORDER BY {$column} ASC", [$this->bucket]);
        return array_values(array_filter(array_map(static fn(array $row): string => (string)$row['value'], $rows), static fn(string $value): bool => $value !== ''));
    }

    public function distinctGenres(): array
    {
        $rows = self::fetchAll("SELECT genres_text FROM records WHERE bucket = ? AND genres_text IS NOT NULL AND genres_text != ''", [$this->bucket]);
        $genres = [];
        foreach ($rows as $row) {
            foreach (explode('|', trim((string)$row['genres_text'], '|')) as $genre) {
                $genre = trim($genre);
                if ($genre !== '') $genres[$genre] = $genre;
            }
        }
        natcasesort($genres);
        return array_values($genres);
    }

    public static function connection(): mysqli
    {
        if (self::$db instanceof mysqli) return self::$db;

        if (!extension_loaded('mysqli')) {
            throw new RuntimeException('MySQLi is not enabled. Enable the PHP mysqli extension, then restart Apache/PHP.');
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $host = (string)Config::get('DB_HOST', '127.0.0.1');
        $port = (int)Config::get('DB_PORT', 3306);
        $database = (string)Config::get('DB_DATABASE', '');
        $username = (string)Config::get('DB_USERNAME', '');
        $password = (string)Config::get('DB_PASSWORD', '');

        if ($database === '' || $username === '') {
            throw new RuntimeException('MySQL database credentials are missing. Set DB_HOST, DB_DATABASE, DB_USERNAME, and DB_PASSWORD in .env.');
        }

        self::$db = new mysqli($host, $username, $password, $database, $port);
        self::$db->set_charset('utf8mb4');
        self::ensureSchema();

        return self::$db;
    }

    public static function stats(): array
    {
        self::connection();
        $stats = [
            'path' => self::databaseName(),
            'size_bytes' => null,
            'buckets' => [],
        ];

        foreach (['movies', 'tv', 'people'] as $bucket) {
            $stats['buckets'][$bucket] = (int)self::scalar('SELECT COUNT(*) FROM records WHERE bucket = ?', [$bucket]);
        }

        return $stats;
    }

    public static function databaseName(): string
    {
        return (string)Config::get('DB_DATABASE', 'stream_hive');
    }

    public static function waitUntilRecordReadable(string $bucket, string $slug, int $timeoutMs = 2500): bool
    {
        $deadline = microtime(true) + ($timeoutMs / 1000);
        do {
            try {
                if (self::scalar('SELECT 1 FROM records WHERE bucket = ? AND slug = ? LIMIT 1', [$bucket, $slug]) !== null) return true;
            } catch (\Throwable) {
            }
            usleep(50000);
        } while (microtime(true) < $deadline);

        return false;
    }

    public static function tmdbCacheGet(string $key): ?array
    {
        $body = self::scalar('SELECT response FROM tmdb_cache WHERE cache_key = ? AND expires_at > ? LIMIT 1', [$key, time()]);
        return is_string($body) && $body !== '' ? (json_decode($body, true) ?: []) : null;
    }

    public static function tmdbCachePut(string $key, string $body, int $ttl): void
    {
        self::execute('DELETE FROM tmdb_cache WHERE expires_at <= ?', [time()]);
        self::execute(
            'INSERT INTO tmdb_cache (cache_key, response, expires_at, created_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE response = VALUES(response), expires_at = VALUES(expires_at), created_at = VALUES(created_at)',
            [$key, $body, time() + $ttl, gmdate(DATE_ATOM)]
        );
    }

    private static function ensureSchema(): void
    {
        if (self::$schemaReady) return;

        self::exec(
            'CREATE TABLE IF NOT EXISTS records (
                auto_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                bucket VARCHAR(32) NOT NULL,
                id VARCHAR(64) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                title VARCHAR(512) DEFAULT NULL,
                name VARCHAR(512) DEFAULT NULL,
                media_type VARCHAR(32) DEFAULT NULL,
                imdb_id VARCHAR(64) DEFAULT NULL,
                import_status VARCHAR(32) DEFAULT NULL,
                release_date VARCHAR(32) DEFAULT NULL,
                release_year VARCHAR(8) DEFAULT NULL,
                age_rating VARCHAR(16) DEFAULT NULL,
                genres_text TEXT DEFAULT NULL,
                search_text MEDIUMTEXT DEFAULT NULL,
                vote_average DOUBLE DEFAULT NULL,
                popularity DOUBLE DEFAULT NULL,
                created_at VARCHAR(40) NOT NULL,
                updated_at VARCHAR(40) NOT NULL,
                payload JSON NOT NULL,
                PRIMARY KEY (auto_id),
                UNIQUE KEY uniq_records_bucket_id (bucket, id),
                UNIQUE KEY uniq_records_bucket_slug (bucket, slug),
                KEY idx_records_bucket_updated (bucket, updated_at),
                KEY idx_records_bucket_title (bucket, title),
                KEY idx_records_bucket_name (bucket, name),
                KEY idx_records_bucket_release (bucket, release_date),
                KEY idx_records_bucket_imdb (bucket, imdb_id),
                KEY idx_records_bucket_status (bucket, import_status),
                KEY idx_records_bucket_status_updated (bucket, import_status, updated_at, id),
                KEY idx_records_bucket_year (bucket, release_year),
                KEY idx_records_bucket_year_release (bucket, release_year, release_date),
                KEY idx_records_bucket_rating (bucket, age_rating),
                KEY idx_records_bucket_rating_release (bucket, age_rating, release_date),
                KEY idx_records_bucket_vote (bucket, vote_average),
                KEY idx_records_bucket_popularity (bucket, popularity),
                KEY idx_records_bucket_release_vote_title (bucket, release_date, vote_average, title),
                KEY idx_records_bucket_auto_release_vote (bucket, auto_id, release_date, vote_average)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::ensureRecordIndexes();

        self::exec(
            'CREATE TABLE IF NOT EXISTS schema_meta (
                meta_key VARCHAR(191) NOT NULL PRIMARY KEY,
                meta_value TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::exec(
            'CREATE TABLE IF NOT EXISTS tmdb_cache (
                cache_key VARCHAR(191) NOT NULL PRIMARY KEY,
                response MEDIUMTEXT NOT NULL,
                expires_at INT UNSIGNED NOT NULL,
                created_at VARCHAR(40) NOT NULL,
                KEY idx_tmdb_cache_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::backfillDerivedColumnsOnce();
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

    private static function backfillDerivedColumnsOnce(): void
    {
        $version = (string)(self::scalar("SELECT meta_value FROM schema_meta WHERE meta_key = 'derived_columns_backfilled_v2'") ?? '');
        if ($version === '1') return;

        $needsBackfill = (int)self::scalar('SELECT COUNT(*) FROM records WHERE search_text IS NULL OR release_year IS NULL OR age_rating IS NULL OR genres_text IS NULL');
        if ($needsBackfill < 1) {
            self::execute("INSERT INTO schema_meta (meta_key, meta_value) VALUES ('derived_columns_backfilled_v2', '1') ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)");
            return;
        }

        $rows = self::fetchAll('SELECT bucket, id, payload FROM records WHERE search_text IS NULL OR release_year IS NULL OR age_rating IS NULL OR genres_text IS NULL');
        self::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                $record = json_decode((string)$row['payload'], true);
                if (!is_array($record)) continue;
                self::execute(
                    'UPDATE records SET release_year = ?, age_rating = ?, genres_text = ?, search_text = ?, popularity = ? WHERE bucket = ? AND id = ?',
                    [
                        self::extractYear($record),
                        self::ukAgeRating($record['age_rating'] ?? ''),
                        self::genresText($record),
                        self::searchText($record),
                        isset($record['popularity']) ? (float)$record['popularity'] : null,
                        (string)$row['bucket'],
                        (string)$row['id'],
                    ]
                );
            }
            self::execute("INSERT INTO schema_meta (meta_key, meta_value) VALUES ('derived_columns_backfilled_v2', '1') ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)");
        });
    }

    private static function ensureRecordIndexes(): void
    {
        $indexes = [
            'idx_records_bucket_status_updated' => 'ALTER TABLE records ADD INDEX idx_records_bucket_status_updated (bucket, import_status, updated_at, id)',
            'idx_records_bucket_year_release' => 'ALTER TABLE records ADD INDEX idx_records_bucket_year_release (bucket, release_year, release_date)',
            'idx_records_bucket_rating_release' => 'ALTER TABLE records ADD INDEX idx_records_bucket_rating_release (bucket, age_rating, release_date)',
            'idx_records_bucket_release_vote_title' => 'ALTER TABLE records ADD INDEX idx_records_bucket_release_vote_title (bucket, release_date, vote_average, title)',
            'idx_records_bucket_auto_release_vote' => 'ALTER TABLE records ADD INDEX idx_records_bucket_auto_release_vote (bucket, auto_id, release_date, vote_average)',
            'ft_records_search_text' => 'ALTER TABLE records ADD FULLTEXT INDEX ft_records_search_text (search_text)',
        ];

        foreach ($indexes as $name => $sql) {
            if (self::indexExists('records', $name)) continue;
            try {
                self::exec($sql);
            } catch (\Throwable $e) {
                if ($name !== 'ft_records_search_text') throw $e;
                self::$fullTextReady = false;
            }
        }
    }

    private static function fullTextReady(): bool
    {
        if (self::$fullTextReady !== null) return self::$fullTextReady;
        return self::$fullTextReady = self::indexExists('records', 'ft_records_search_text');
    }

    private static function indexExists(string $table, string $index): bool
    {
        return self::scalar(
            'SELECT 1
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?
             LIMIT 1',
            [$table, $index]
        ) !== null;
    }

    private static function rowsToRecords(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $record = json_decode((string)$row['payload'], true);
            if (!is_array($record)) continue;
            $record['created_at'] = $record['created_at'] ?? $row['created_at'];
            $record['updated_at'] = $record['updated_at'] ?? $row['updated_at'];
            $items[] = $record;
        }
        return $items;
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

    private static function exec(string $sql): void
    {
        if (!self::connection()->query($sql)) {
            throw new RuntimeException(self::connection()->error);
        }
    }

    private static function execute(string $sql, array $params = []): \mysqli_stmt
    {
        $stmt = self::connection()->prepare($sql);
        if (!$stmt) throw new RuntimeException(self::connection()->error);

        if ($params !== []) {
            $types = self::typesFor($params);
            $refs = [];
            foreach ($params as $key => &$value) $refs[$key] = &$value;
            $stmt->bind_param($types, ...$refs);
        }

        $stmt->execute();
        return $stmt;
    }

    private static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::execute($sql, $params);
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    private static function fetchOne(string $sql, array $params = []): ?array
    {
        $rows = self::fetchAll($sql, $params);
        return $rows[0] ?? null;
    }

    private static function scalar(string $sql, array $params = []): mixed
    {
        $row = self::fetchOne($sql, $params);
        if (!$row) return null;
        return reset($row);
    }

    private static function typesFor(array $params): string
    {
        $types = '';
        foreach ($params as $value) {
            $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        }
        return $types;
    }

    private static function orderByForSort(string $sort, bool $containsPeople = false): string
    {
        $nameExpr = $containsPeople ? "COALESCE(NULLIF(name, ''), NULLIF(title, ''), '')" : "COALESCE(NULLIF(title, ''), NULLIF(name, ''), '')";
        return match ($sort) {
            'title_desc' => $nameExpr . ' DESC, auto_id DESC',
            'date_asc' => "COALESCE(release_date, '') ASC, {$nameExpr} ASC",
            'date_desc' => "COALESCE(release_date, '') DESC, {$nameExpr} ASC",
            'rating_asc' => 'COALESCE(vote_average, 0) ASC, ' . $nameExpr . ' ASC',
            'rating_desc' => 'COALESCE(vote_average, 0) DESC, ' . $nameExpr . ' ASC',
            'updated_desc' => 'updated_at DESC, auto_id DESC',
            'popular_desc' => 'COALESCE(popularity, 0) DESC, COALESCE(vote_average, 0) DESC, auto_id DESC',
            default => $nameExpr . ' ASC, auto_id ASC',
        };
    }

    private static function booleanSearchQuery(string $query): string
    {
        $query = strtolower(trim(preg_replace('/\s+/', ' ', $query) ?: ''));
        if ($query === '') return '';

        $terms = preg_split('/[^\pL\pN]+/u', $query) ?: [];
        $terms = array_values(array_unique(array_filter($terms, static fn(string $term): bool => strlen($term) >= 3)));
        if ($terms === []) return '';

        return implode(' ', array_map(static fn(string $term): string => '+' . $term . '*', array_slice($terms, 0, 8)));
    }

    private static function recordHasUsablePoster(array $record): bool
    {
        $poster = trim((string)($record['poster_path'] ?? ''));
        if ($poster === '') return false;

        return !str_contains($poster, 'placeholder.jpg')
            && !str_contains($poster, 'placeholder.svg');
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

    private function mediaTypeForBucket(): string
    {
        return match ($this->bucket) {
            'movies' => 'movie',
            'people' => 'person',
            default => $this->bucket,
        };
    }
}
