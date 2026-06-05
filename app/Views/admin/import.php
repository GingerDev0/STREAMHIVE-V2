<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php $token = (string)($_GET['token'] ?? $_POST['token'] ?? ''); ?>
<section class="admin-hero admin-panel mb-4">
  <div class="row g-4 align-items-center">
    <div class="col-lg-8">
      <span class="admin-kicker"><i class="fa-solid fa-cloud-arrow-down"></i> Import center</span>
      <h1 class="admin-title">Import from IMDb or TMDB</h1>
      <p class="admin-lead mb-0">Paste an IMDb URL, IMDb ID, or TMDB shortcut and the app will pull the matching movie, TV show, or person into local MySQL.</p>
    </div>
    <div class="col-lg-4 text-lg-end">
      <a class="btn btn-outline-light" href="/admin?token=<?= e($token) ?>"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
  </div>
</section>

<div class="row g-4">
  <div class="col-lg-7">
    <section class="admin-panel">
      <?php if($message): ?><div class="alert alert-success admin-alert"><i class="fa-solid fa-circle-check me-2"></i><?= e($message) ?></div><?php endif; ?>
      <?php if($error): ?><div class="alert alert-danger admin-alert"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="admin-import-form">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label class="form-label fw-bold">IMDb URL / IMDb ID / TMDB shortcut</label>
        <div class="admin-input-wrap">
          <i class="fa-solid fa-link"></i>
          <input class="form-control form-control-lg" name="input" value="<?= e($input ?? '') ?>" placeholder="https://www.imdb.com/title/tt0111161/ or tmdb:tv:1399" required autofocus>
        </div>
        <button class="btn btn-warning btn-lg mt-3"><i class="fa-solid fa-download me-2"></i>Import now</button>
      </form>
    </section>
  </div>
  <div class="col-lg-5">
    <section class="admin-panel h-100">
      <div class="admin-section-head"><div><span class="admin-kicker"><i class="fa-solid fa-lightbulb"></i> Accepted formats</span><h2>Examples</h2></div></div>
      <div class="admin-example-list">
        <code>https://www.imdb.com/title/tt0111161/</code>
        <code>tt0944947</code>
        <code>nm0000138</code>
        <code>tmdb:movie:299534</code>
        <code>tmdb:tv:1399</code>
      </div>
    </section>
  </div>
</div>

<?php if ($record): ?>
  <?php $titleText = (string)($record['title'] ?? $record['name'] ?? 'Imported item'); ?>
  <section class="admin-panel mt-4">
    <div class="admin-section-head"><div><span class="admin-kicker"><i class="fa-solid fa-sparkles"></i> Import result</span><h2><?= e($titleText) ?></h2></div></div>
    <div class="admin-result-card">
      <img src="<?= e(tmdb_img($record['poster_path'] ?? null, 'w342')) ?>" alt="<?= e($titleText) ?> poster">
      <div>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <span class="admin-chip">ID <?= e((string)($record['id'] ?? '')) ?></span>
          <?php if (!empty($record['imdb_id'])): ?><span class="admin-chip">IMDb <?= e((string)$record['imdb_id']) ?></span><?php endif; ?>
          <?php $adminAgeRating = display_age_rating($record['age_rating'] ?? '', $record['media_type'] ?? 'movie'); if ($adminAgeRating !== ''): ?><span class="admin-chip"><?= e($adminAgeRating) ?></span><?php endif; ?>
          <span class="admin-chip"><?= e((string)round((float)($record['vote_average'] ?? 0), 1)) ?> ★</span>
        </div>
        <p class="admin-muted mb-3"><?= e((string)($record['overview'] ?? 'No overview available.')) ?></p>
        <div class="d-flex gap-2 flex-wrap">
          <a class="btn btn-outline-light btn-sm" href="/admin/import?token=<?= e($token) ?>">Import another</a>
          <a class="btn btn-warning btn-sm" href="<?= e(!empty($record['first_air_date']) ? url('tv/' . ($record['slug'] ?? slugify($titleText))) : url('movies/' . ($record['slug'] ?? slugify($titleText)))) ?>">View page</a>
        </div>
      </div>
    </div>
  </section>
<?php endif; ?>
