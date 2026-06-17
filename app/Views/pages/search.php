<?php require_once app_path('app/Helpers/helpers.php'); use App\Core\View; ?>
<?php
$searchTypeLabel = match ($type) {
    'movie' => 'Movies',
    'tv' => 'TV Shows',
    'person' => 'Actors',
    default => 'Movies + TV',
};
$itemLabel = $type === 'movie' ? 'Movies' : ($type === 'tv' ? 'TV Shows' : ($type === 'person' ? 'Actors' : 'Results'));
$summary = $query !== ''
    ? (string)$total . ' live ' . strtolower($itemLabel) . ' for "' . $query . '"'
    : (string)$total . ' live ' . strtolower($itemLabel);
?>
<div class="streamhive-js-jquery-listing-shell streamhive-jquery-listing-shell" data-jquery-listing="search">
<section class="streamhive-glass rounded-4 p-4 mb-4 text-white">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
      <h1 class="h2 mb-1">Search</h1>
      <p class="text-white-50 mb-0"><?= e($summary) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-light" href="<?= e(url('movies')) ?>"><i class="fa-solid fa-film me-1"></i> Movies</a>
      <a class="btn btn-outline-light" href="<?= e(url('tv')) ?>"><i class="fa-solid fa-tv me-1"></i> TV Shows</a>
    </div>
  </div>

  <form class="row g-2" method="get" action="<?= e(url('s')) ?>">
    <div class="col-md-4">
      <input name="q" value="<?= e($query) ?>" class="form-control" placeholder="Search movies, TV shows, actors">
    </div>
    <div class="col-md-2">
      <select name="type" class="form-select" aria-label="Search type">
        <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>Movies + TV</option>
        <option value="movie" <?= $type === 'movie' ? 'selected' : '' ?>>Movies</option>
        <option value="tv" <?= $type === 'tv' ? 'selected' : '' ?>>TV Shows</option>
        <option value="person" <?= $type === 'person' ? 'selected' : '' ?>>Actors</option>
      </select>
    </div>
    <div class="col-md-2">
      <select name="genre" class="form-select" aria-label="Filter by genre">
        <option value="">All genres</option>
        <?php foreach ($genres as $g): ?><option value="<?= e($g) ?>" <?= $genre === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select name="rating" class="form-select" aria-label="Filter by age rating">
        <option value="">Any age rating</option>
        <?php foreach ($ratings as $r): ?><option value="<?= e($r) ?>" <?= $rating === $r ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select name="user_rating" class="form-select" aria-label="Filter by user rating">
        <option value="">Any TMDB rating</option>
        <?php foreach (($userRatingOptions ?? []) as $option): ?><option value="<?= e((string)$option['value']) ?>" <?= (string)($score ?? '') === (string)$option['value'] ? 'selected' : '' ?>><?= e((string)$option['label']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select name="year" class="form-select" aria-label="Filter by year">
        <option value="">Any year</option>
        <?php for ($y = (int)date('Y'); $y >= 1900; $y--): ?>
          <option value="<?= e((string)$y) ?>" <?= $year === (string)$y ? 'selected' : '' ?>><?= e((string)$y) ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col-md-8">
      <select name="sort" class="form-select" aria-label="Sort results">
        <?php foreach (['title_asc'=>'Title A-Z','title_desc'=>'Title Z-A','date_desc'=>'Newest release','date_asc'=>'Oldest release','rating_desc'=>'Top rated','rating_asc'=>'Lowest rated','updated_desc'=>'Recently updated'] as $value=>$label): ?>
          <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 d-grid">
      <button class="btn btn-warning" type="submit" aria-label="Search"><i class="fa-solid fa-magnifying-glass me-1"></i>Search</button>
    </div>
  </form>
</section>

<?= View::partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage ?? 24, 'itemLabel' => $itemLabel, 'position' => 'top']) ?>

<div class="row g-3">
<?php foreach ($items as $item): echo View::partial('partials/media-card', ['item' => $item, 'type' => $item['media_type'] ?? 'movie', 'allowMissingPoster' => true]); endforeach; ?>
<?php if (!$items): ?><div class="col-12"><div class="streamhive-glass rounded-4 p-4 text-white">No results found. Try a different title, type, genre, year, age rating, or TMDB rating filter.</div></div><?php endif; ?>
</div>

<?= View::partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage ?? 24, 'itemLabel' => $itemLabel]) ?>
</div>
