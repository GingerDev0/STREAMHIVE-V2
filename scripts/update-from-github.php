<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$repo = 'GingerDev0/StreamHIVE';
$branch = 'main';
$rawVersionUrl = "https://raw.githubusercontent.com/{$repo}/{$branch}/version.txt";
$archiveUrl = "https://codeload.github.com/{$repo}/tar.gz/refs/heads/{$branch}";
$args = array_flip(array_slice($argv, 1));
$force = isset($args['--force']);
$dryRun = isset($args['--dry-run']);
$checkOnly = isset($args['--check-only']);
$quiet = isset($args['--quiet']);

$log = static function (string $message) use ($quiet): void {
    if (!$quiet) echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
};

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$readVersion = static function (string $path): string {
    return is_file($path) ? trim((string)@file_get_contents($path)) : '';
};

$isNewerVersion = static function (string $candidate, string $current): bool {
    $candidate = trim($candidate);
    $current = trim($current);
    return $candidate !== '' && $current !== '' && version_compare($candidate, $current, '>');
};

$httpGet = static function (string $url, string $target = ''): string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'StreamHIVE-Updater',
        ];
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if (!is_string($body) || $status < 200 || $status >= 300) {
            throw new RuntimeException('Download failed: ' . ($error ?: 'HTTP ' . $status));
        }
        if ($target !== '') {
            if (@file_put_contents($target, $body) === false) {
                throw new RuntimeException('Could not write download: ' . $target);
            }
            return '';
        }
        return $body;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 60,
            'header' => "User-Agent: StreamHIVE-Updater\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if (!is_string($body)) throw new RuntimeException('Download failed: ' . $url);
    if ($target !== '' && @file_put_contents($target, $body) === false) {
        throw new RuntimeException('Could not write download: ' . $target);
    }
    return $target === '' ? $body : '';
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!file_exists($path)) return;
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    $items = scandir($path) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $removeTree($path . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($path);
};

$extractTarGz = static function (string $archive, string $destination) use ($fail): void {
    if (class_exists(PharData::class)) {
        try {
            $tarPath = preg_replace('/\.gz$/', '', $archive) ?: ($archive . '.tar');
            if (is_file($tarPath)) @unlink($tarPath);
            $phar = new PharData($archive);
            $phar->decompress();
            $tar = new PharData($tarPath);
            $tar->extractTo($destination, null, true);
            @unlink($tarPath);
            return;
        } catch (Throwable) {
            // Fall through to system tar when available.
        }
    }

    if (!function_exists('exec')) {
        $fail('Could not extract archive. Enable PharData gzip support or the tar command.');
    }

    $command = 'tar -xzf ' . escapeshellarg($archive) . ' -C ' . escapeshellarg($destination);
    exec($command, $output, $code);
    if ($code !== 0) {
        $fail('Could not extract archive with tar.');
    }
};

$shouldSkip = static function (string $relative): bool {
    $path = str_replace('\\', '/', $relative);
    $base = basename($path);

    if ($path === '.env' || str_starts_with($path, '.env.local') || str_ends_with($path, '.local')) return true;
    if (preg_match('/^\.env\..*\.local$/', $path)) return true;
    if (in_array($base, ['.git'], true)) return true;
    foreach (['.git/', 'storage/', 'vendor/', 'node_modules/'] as $prefix) {
        if (str_starts_with($path, $prefix)) return true;
    }
    return false;
};

$copyTree = static function (string $source, string $destination, string $backupRoot, bool $dryRun) use (&$copyTree, $shouldSkip, $log): array {
    $copied = 0;
    $backedUp = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $sourcePath = $item->getPathname();
        $relative = ltrim(str_replace('\\', '/', substr($sourcePath, strlen($source))), '/');
        if ($relative === '' || $shouldSkip($relative)) continue;

        $targetPath = $destination . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if ($item->isDir()) {
            if (!$dryRun && !is_dir($targetPath)) @mkdir($targetPath, 0775, true);
            continue;
        }

        $copied++;
        if ($dryRun) {
            $log('Would update ' . $relative);
            continue;
        }

        if (is_file($targetPath)) {
            $backupPath = $backupRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $backupDir = dirname($backupPath);
            if (!is_dir($backupDir)) @mkdir($backupDir, 0775, true);
            if (@copy($targetPath, $backupPath)) $backedUp++;
        }

        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) @mkdir($targetDir, 0775, true);
        if (!@copy($sourcePath, $targetPath)) {
            throw new RuntimeException('Could not copy ' . $relative);
        }
    }

    return ['copied' => $copied, 'backed_up' => $backedUp];
};

$lockDir = $root . DIRECTORY_SEPARATOR . 'storage';
$lockFile = $lockDir . DIRECTORY_SEPARATOR . 'update.lock';
if (!is_dir($lockDir)) @mkdir($lockDir, 0775, true);
$lockHandle = @fopen($lockFile, 'c');
if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    $fail('Another update is already running.');
}

try {
    $localVersion = $readVersion($root . DIRECTORY_SEPARATOR . 'version.txt');
    $remoteVersion = trim($httpGet($rawVersionUrl));

    if ($remoteVersion === '') {
        $fail('Remote version is empty.');
    }

    $log('Installed version: ' . ($localVersion !== '' ? $localVersion : 'unknown'));
    $log('GitHub version: ' . $remoteVersion);

    if (!$force && $localVersion !== '' && !$isNewerVersion($remoteVersion, $localVersion)) {
        $log('Already up to date.');
        exit(0);
    }

    if ($checkOnly) {
        $log('Update available.');
        exit(0);
    }

    $tmpRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'streamhive-update-' . bin2hex(random_bytes(4));
    $extractRoot = $tmpRoot . DIRECTORY_SEPARATOR . 'extract';
    $archivePath = $tmpRoot . DIRECTORY_SEPARATOR . 'source.tar.gz';
    $backupRoot = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'update-backups' . DIRECTORY_SEPARATOR . date('Ymd-His') . '-' . ($localVersion !== '' ? $localVersion : 'unknown');

    if (!@mkdir($extractRoot, 0775, true) && !is_dir($extractRoot)) {
        $fail('Could not create temp directory.');
    }

    $log('Downloading update archive...');
    $httpGet($archiveUrl, $archivePath);

    $log('Extracting update archive...');
    $extractTarGz($archivePath, $extractRoot);

    $children = array_values(array_filter(scandir($extractRoot) ?: [], static fn(string $item): bool => $item !== '.' && $item !== '..'));
    $sourceDir = $children ? $extractRoot . DIRECTORY_SEPARATOR . $children[0] : '';
    if ($sourceDir === '' || !is_dir($sourceDir)) {
        $fail('Could not locate extracted source directory.');
    }

    $log($dryRun ? 'Dry run: checking file updates...' : 'Applying update...');
    $result = $copyTree($sourceDir, $root, $backupRoot, $dryRun);
    $removeTree($tmpRoot);
    if (!$dryRun) @unlink($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'version-check.json');

    $log(($dryRun ? 'Dry run complete. ' : 'Update complete. ') . $result['copied'] . ' files processed, ' . $result['backed_up'] . ' backups written.');
    if (!$dryRun) $log('Backup path: ' . $backupRoot);
} catch (Throwable $e) {
    $fail($e->getMessage());
} finally {
    if (isset($lockHandle) && is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}
