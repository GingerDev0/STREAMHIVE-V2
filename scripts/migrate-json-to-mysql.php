<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\Repository;

$repo = new Repository();
$buckets = [
    'movies' => $repo->movies,
    'tv' => $repo->tv,
    'people' => $repo->people,
];

foreach ($buckets as $bucket => $store) {
    $dir = storage_path($bucket);
    $files = is_dir($dir) ? (glob($dir . '/*.json') ?: []) : [];
    sort($files, SORT_NATURAL);
    $count = 0;

    foreach ($files as $file) {
        $rows = json_decode((string)file_get_contents($file), true);
        if (!is_array($rows)) continue;
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['id'])) continue;
            $store->upsert($row);
            $count++;
        }
    }

    echo sprintf("%s: migrated %d JSON records into MySQL%s", $bucket, $count, PHP_EOL);
}

echo 'MySQL database: ' . App\Services\MysqliStore::databaseName() . PHP_EOL;
