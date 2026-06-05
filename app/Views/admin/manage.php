<?php require_once app_path('app/Helpers/helpers.php'); use App\Core\View; ?>
<?php
$token = (string)($_GET['token'] ?? '');
$typeLabel = $type === 'tv' ? 'TV Shows' : ($type === 'people' ? 'Actors' : 'Movies');
$base = '/admin/manage/' . $type . '?token=' . rawurlencode($token);
?>
<section class="admin-hero admin-panel mb-4">
  <div class="row g-4 align-items-center">
    <div class="col-lg-8">
      <span class="admin-kicker"><i class="fa-solid fa-table-list"></i> Library manager</span>
      <h1 class="admin-title">Manage <?= e($typeLabel) ?></h1>
      <p class="admin-lead mb-0">Search, sort, preview, open, and delete local MySQL records.</p>
    </div>
    <div class="col-lg-4 text-lg-end">
      <a class="btn btn-warning" href="/admin/import?token=<?= e($token) ?>"><i class="fa-solid fa-plus me-2"></i>Import</a>
      <a class="btn btn-outline-light" href="/admin?token=<?= e($token) ?>"><i class="fa-solid fa-gauge-high me-2"></i>Dashboard</a>
    </div>
  </div>
</section>

<section class="admin-panel mb-4">
  <div class="admin-tabs mb-3">
    <a class="<?= $type==='movies'?'active':'' ?>" href="/admin/manage/movies?token=<?= e($token) ?>"><i class="fa-solid fa-film"></i> Movies</a>
    <a class="<?= $type==='tv'?'active':'' ?>" href="/admin/manage/tv?token=<?= e($token) ?>"><i class="fa-solid fa-tv"></i> TV Shows</a>
    <a class="<?= $type==='people'?'active':'' ?>" href="/admin/manage/people?token=<?= e($token) ?>"><i class="fa-solid fa-user-group"></i> Actors</a>
  </div>
  <form class="row g-3 align-items-end" method="get">
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="col-lg-5"><label class="form-label">Search</label><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Title, slug, ID, IMDb ID, or genre"></div>
    <div class="col-md-3 col-lg-2"><label class="form-label">Status</label><select class="form-select" name="status"><option value="all" <?= $status==='all'?'selected':'' ?>>All</option><option value="full" <?= $status==='full'?'selected':'' ?>>Full</option><option value="prefetched" <?= $status==='prefetched'?'selected':'' ?>>Prefetched</option></select></div>
    <div class="col-md-5 col-lg-3"><label class="form-label">Sort</label><select class="form-select" name="sort">
      <?php foreach (['updated_desc'=>'Recently updated','title_asc'=>'Title A-Z','title_desc'=>'Title Z-A','date_desc'=>'Newest release','date_asc'=>'Oldest release','rating_desc'=>'Top rated','rating_asc'=>'Lowest rated'] as $value=>$label): ?>
        <option value="<?= e($value) ?>" <?= $sort===$value?'selected':'' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select></div>
    <div class="col-md-4 col-lg-2 d-grid"><button class="btn btn-warning"><i class="fa-solid fa-filter me-2"></i>Filter</button></div>
  </form>
</section>

<div class="admin-list-head mb-3">
  <div><strong><?= e((string)$total) ?></strong> records found</div>
  <div class="admin-muted">Page <?= e((string)$page) ?> of <?= e((string)$pages) ?></div>
</div>

<?= View::partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage ?? 24, 'itemLabel' => $typeLabel, 'position' => 'top']) ?>

<div class="admin-media-grid">
  <?php foreach ($items as $item): ?>
    <?php
      $titleText = (string)($item['title'] ?? $item['name'] ?? 'Untitled');
      $publicUrl = $type === 'tv' ? url('tv/' . ($item['slug'] ?? slugify($titleText))) : ($type === 'people' ? url('actors/' . ($item['slug'] ?? slugify($titleText))) : url('movies/' . ($item['slug'] ?? slugify($titleText))));
      $date = (string)($item['release_date'] ?? $item['first_air_date'] ?? '');
    ?>
    <article class="admin-media-card">
      <a href="<?= e($publicUrl) ?>" class="admin-media-poster"><img src="<?= e(tmdb_img($item['poster_path'] ?? $item['profile_path'] ?? null, 'w342')) ?>" alt="<?= e($titleText) ?> poster"></a>
      <div class="admin-media-body">
        <div class="d-flex justify-content-between gap-2 align-items-start">
          <h2><a href="<?= e($publicUrl) ?>"><?= e($titleText) ?></a></h2>
          <span class="admin-chip"><?= e((string)($item['import_status'] ?? 'full')) ?></span>
        </div>
        <?php $adminAgeRating = display_age_rating($item['age_rating'] ?? '', $item['media_type'] ?? 'movie'); ?>
        <div class="admin-muted small mb-2"><?= e($date ? format_date($date) : 'No date') ?><?= $adminAgeRating !== '' ? ' · ' . e($adminAgeRating) : '' ?><?= isset($item['vote_average']) ? ' · ' . e((string)round((float)$item['vote_average'], 1)) . ' ★' : '' ?></div>
        <div class="admin-muted small text-truncate mb-3"><?= e((string)($item['slug'] ?? '')) ?></div>
        <div class="d-flex gap-2 mt-auto">
          <a class="btn btn-sm btn-outline-light flex-fill" href="<?= e($publicUrl) ?>"><i class="fa-solid fa-up-right-from-square me-1"></i>Open</a>
          <form method="post" action="/admin/delete/<?= e($type) ?>/<?= e((string)$item['id']) ?>?token=<?= e($token) ?>" onsubmit="return confirm('Delete this record?')">
            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
          </form>
        </div>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<?php if (!$items): ?><section class="admin-panel admin-empty mt-3">No records match those filters.</section><?php endif; ?>

<?= View::partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage ?? 24, 'itemLabel' => $typeLabel]) ?>
