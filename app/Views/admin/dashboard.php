<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php $token = (string)($_GET['token'] ?? ''); ?>
<section class="admin-hero admin-panel mb-4">
  <div class="row g-4 align-items-center">
    <div class="col-lg-8">
      <span class="admin-kicker"><i class="fa-solid fa-shield-halved"></i> Control room</span>
      <h1 class="admin-title">Admin Dashboard</h1>
      <p class="admin-lead mb-0">Import, review, search, and clean up your local movie and TV library from one polished workspace.</p>
    </div>
    <div class="col-lg-4">
      <div class="admin-actions">
        <a class="btn btn-warning w-100" href="/admin/import?token=<?= e($token) ?>"><i class="fa-solid fa-cloud-arrow-down me-2"></i>Import media</a>
        <form method="post" action="/admin/import-prefetched?token=<?= e($token) ?>" onsubmit="return confirm('Import every prefetched movie, TV show, and actor now? This can take a while if there are lots of TMDB records.');">
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <input type="hidden" name="scope" value="all">
          <button class="btn btn-danger w-100" type="submit" <?= $prefetchedCount > 0 ? '' : 'disabled' ?>><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Import all prefetched</button>
        </form>
        <a class="btn btn-outline-light w-100" href="/admin/manage/movies?token=<?= e($token) ?>"><i class="fa-solid fa-film me-2"></i>Manage movies</a>
        <a class="btn btn-outline-light w-100" href="/admin/manage/tv?token=<?= e($token) ?>"><i class="fa-solid fa-tv me-2"></i>Manage TV shows</a>
      </div>
    </div>
  </div>
</section>

<?php if (!empty($bulkImportResult)): ?>
  <?php $bulkTotal = (int)$bulkImportResult['movies'] + (int)$bulkImportResult['tv'] + (int)$bulkImportResult['people']; ?>
  <div class="alert alert-success admin-alert mb-4">
    <i class="fa-solid fa-circle-check me-2"></i>
    Imported <?= e((string)$bulkTotal) ?> prefetched records
    <span class="admin-muted">(<?= e((string)$bulkImportResult['movies']) ?> movies, <?= e((string)$bulkImportResult['tv']) ?> TV shows, <?= e((string)$bulkImportResult['people']) ?> actors<?= (int)$bulkImportResult['failed'] > 0 ? ', ' . e((string)$bulkImportResult['failed']) . ' failed' : '' ?>).</span>
  </div>
<?php endif; ?>

<section class="admin-panel mb-4">
  <div class="admin-section-head">
    <div>
      <span class="admin-kicker"><i class="fa-solid fa-wand-magic-sparkles"></i> Prefetched records</span>
      <h2>Import everything that needs full details</h2>
    </div>
  </div>
  <div class="row g-3 align-items-stretch">
    <?php foreach ([['movies', 'Movies', 'fa-film'], ['tv', 'TV Shows', 'fa-tv'], ['people', 'Actors', 'fa-user-group']] as $prefetchStat): ?>
      <?php $prefetchKey = $prefetchStat[0]; $prefetchCount = (int)($prefetchedBreakdown[$prefetchKey] ?? 0); ?>
      <div class="col-md-4">
        <div class="admin-storage-card h-100">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <strong><i class="fa-solid <?= e($prefetchStat[2]) ?> me-2"></i><?= e($prefetchStat[1]) ?></strong>
            <span class="admin-chip"><?= e((string)$prefetchCount) ?></span>
          </div>
          <form method="post" action="/admin/import-prefetched?token=<?= e($token) ?>" onsubmit="return confirm('Import all prefetched <?= e(strtolower($prefetchStat[1])) ?> now?');">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="scope" value="<?= e($prefetchKey) ?>">
            <button class="btn btn-sm btn-outline-light w-100" type="submit" <?= $prefetchCount > 0 ? '' : 'disabled' ?>>Import <?= e($prefetchStat[1]) ?></button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <form class="mt-3" method="post" action="/admin/import-prefetched?token=<?= e($token) ?>" onsubmit="return confirm('Import every prefetched movie, TV show, and actor now?');">
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <input type="hidden" name="scope" value="all">
    <button class="btn btn-warning" type="submit" <?= $prefetchedCount > 0 ? '' : 'disabled' ?>><i class="fa-solid fa-download me-2"></i>Import all prefetched records</button>
    <span class="admin-muted small ms-2">Imports full TMDB details for all prefetched movies, TV shows, and actors.</span>
  </form>
</section>

<div class="row g-3 mb-4">
  <?php foreach ([['Movies',$movieCount,'fa-film','/admin/manage/movies'], ['TV Shows',$tvCount,'fa-tv','/admin/manage/tv'], ['Actors',$peopleCount,'fa-user-group','/admin/manage/people'], ['Needs full import',$prefetchedCount,'fa-wand-magic-sparkles','/admin/manage/movies?status=prefetched']] as $stat): ?>
    <div class="col-sm-6 col-xl-3">
      <a class="admin-stat-card" href="<?= e($stat[3] . (str_contains($stat[3], '?') ? '&' : '?') . 'token=' . rawurlencode($token)) ?>">
        <span class="admin-stat-icon"><i class="fa-solid <?= e($stat[2]) ?>"></i></span>
        <span class="admin-stat-value"><?= e((string)$stat[1]) ?></span>
        <span class="admin-stat-label"><?= e($stat[0]) ?></span>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <div class="col-xl-8">
    <section class="admin-panel h-100">
      <div class="admin-section-head">
        <div>
          <span class="admin-kicker"><i class="fa-solid fa-clock-rotate-left"></i> Latest changes</span>
          <h2>Recently Updated</h2>
        </div>
        <a class="btn btn-sm btn-outline-light" href="/admin/import?token=<?= e($token) ?>">Add new</a>
      </div>
      <div class="admin-recent-list">
        <?php foreach ($recentItems as $item): ?>
          <?php
            $type = (string)($item['admin_type'] ?? 'movies');
            $titleText = (string)($item['title'] ?? $item['name'] ?? 'Untitled');
            $publicUrl = $type === 'tv' ? url('tv/' . ($item['slug'] ?? slugify($titleText))) : ($type === 'people' ? url('actors/' . ($item['slug'] ?? slugify($titleText))) : url('movies/' . ($item['slug'] ?? slugify($titleText))));
          ?>
          <div class="admin-recent-row">
            <img src="<?= e(tmdb_img($item['poster_path'] ?? null, 'w185')) ?>" alt="<?= e($titleText) ?> poster">
            <div class="min-w-0">
              <a class="admin-recent-title" href="<?= e($publicUrl) ?>"><?= e($titleText) ?></a>
              <div class="admin-muted small"><?= e($type === 'people' ? 'ACTOR' : strtoupper($type === 'tv' ? 'TV' : 'Movie')) ?><?= !empty($item['updated_at']) ? ' · Updated ' . e(format_date(substr((string)$item['updated_at'], 0, 10))) : '' ?></div>
            </div>
            <span class="admin-chip ms-auto"><?= e((string)round((float)($item['vote_average'] ?? 0), 1)) ?> ★</span>
          </div>
        <?php endforeach; ?>
        <?php if (empty($recentItems)): ?><div class="admin-empty">No media has been imported yet.</div><?php endif; ?>
      </div>
    </section>
  </div>
  <div class="col-xl-4">
    <section class="admin-panel h-100">
      <div class="admin-section-head"><div><span class="admin-kicker"><i class="fa-solid fa-database"></i> SQLite</span><h2>Storage Health</h2></div></div>
      <div class="d-flex flex-column gap-3">
        <?php foreach ($storageStats as $name => $stat): ?>
          <div class="admin-storage-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <strong><?= e(ucfirst($name)) ?></strong>
              <span class="admin-muted small"><?= e((string)$stat['rows']) ?> records</span>
            </div>
            <div class="admin-progress"><span style="width: <?= e((string)$stat['percent']) ?>%"></span></div>
            <div class="admin-muted small mt-2"><?= e(format_bytes((int)($stat['size_bytes'] ?? 0))) ?> SQLite database</div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="admin-note mt-3"><i class="fa-solid fa-circle-info me-2"></i>SQLite is now the primary local database. Existing JSON shards are imported automatically the first time the app starts.</div>
    </section>
  </div>
</div>
