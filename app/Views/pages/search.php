<?php require_once app_path('app/Helpers/helpers.php'); use App\Core\View; ?>
<?php
$typeOptions = [
    'all' => ['label' => 'All', 'icon' => 'fa-compass'],
    'movie' => ['label' => 'Movies', 'icon' => 'fa-film'],
    'tv' => ['label' => 'TV Shows', 'icon' => 'fa-tv'],
    'person' => ['label' => 'Actors', 'icon' => 'fa-user-group'],
];
$sortOptions = [
    'relevance' => 'Best match',
    'popularity_desc' => 'Most popular',
    'rating_desc' => 'Top rated',
    'date_desc' => 'Newest release',
    'date_asc' => 'Oldest release',
    'title_asc' => 'Title A-Z',
    'title_desc' => 'Title Z-A',
];
$itemLabel = match ($type) {
    'movie' => 'Movies',
    'tv' => 'TV Shows',
    'person' => 'Actors',
    default => 'Results',
};
$baseParams = [
    'q' => $query,
    'genre' => $genre,
    'year' => $year,
    'user_rating' => $score,
    'sort' => $sort,
];
$activeFilters = array_filter([$query, $genre, $year, $score], static fn($value): bool => trim((string)$value) !== '');
$showingFrom = $total > 0 ? (($page - 1) * ($perPage ?? 24)) + 1 : 0;
$showingTo = $total > 0 ? min((int)$total, $showingFrom + (int)($perPage ?? 24) - 1) : 0;
$typeSummary = match ($type) {
    'movie' => 'movies',
    'tv' => 'TV shows',
    'person' => 'actors',
    default => 'movies, TV shows and actors',
};
$filterSummary = array_values(array_filter([$genre, $year], static fn($value): bool => trim((string)$value) !== ''));
if ($query !== '') {
    $summary = 'Results for "' . $query . '"';
} elseif ($filterSummary) {
    $summary = 'Explore ' . implode(' ', $filterSummary) . ' ' . $typeSummary;
} else {
    $summary = 'Explore popular ' . $typeSummary;
}
?>
<div class="streamhive-js-jquery-listing-shell streamhive-jquery-listing-shell streamhive-search-page" data-jquery-listing="search" hx-boost="true" hx-target="this" hx-select=".streamhive-js-jquery-listing-shell" hx-swap="outerHTML" hx-push-url="true">
  <section class="streamhive-search-hero">
    <div class="streamhive-search-hero-copy">
      <span class="streamhive-v2-kicker"><i class="fa-solid fa-magnifying-glass"></i> Discover</span>
      <h1><?= e($summary) ?></h1>
      <div class="streamhive-search-stats" aria-label="Search result summary">
        <span><strong><?= e(number_format((int)$total)) ?></strong> <?= e(strtolower($itemLabel)) ?></span>
        <?php if ($total > 0): ?><span><strong><?= e((string)$showingFrom) ?>-<?= e((string)$showingTo) ?></strong> showing</span><?php endif; ?>
        <?php if ($activeFilters): ?><span><strong><?= e((string)count($activeFilters)) ?></strong> filters</span><?php endif; ?>
      </div>
    </div>
    <div class="streamhive-search-type-tabs" aria-label="Result type">
      <?php foreach ($typeOptions as $value => $option):
        $typeParams = array_filter($baseParams + ['type' => $value], static fn($v): bool => $v !== null && $v !== '');
        $typeParams['type'] = $value;
      ?>
        <a class="streamhive-search-type-tab<?= $type === $value ? ' active' : '' ?>" href="<?= e(search_url($typeParams)) ?>"<?= $type === $value ? ' aria-current="page"' : '' ?>>
          <i class="fa-solid <?= e($option['icon']) ?>"></i>
          <span><?= e($option['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="streamhive-search-controls" aria-label="Search filters">
    <form method="get" action="<?= e(url('s')) ?>">
      <input type="hidden" name="type" value="<?= e($type) ?>">
      <label class="streamhive-search-field streamhive-search-field-query">
        <span>Search</span>
        <input name="q" value="<?= e($query) ?>" class="form-control" placeholder="Movie, TV show, or actor">
      </label>
      <label class="streamhive-search-field">
        <span>Genre</span>
        <select name="genre" class="form-select" aria-label="Filter by genre">
          <option value="">All genres</option>
          <?php foreach ($genres as $g): ?><option value="<?= e($g) ?>" <?= $genre === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="streamhive-search-field">
        <span>Year</span>
        <select name="year" class="form-select" aria-label="Filter by year">
          <option value="">Any year</option>
          <?php for ($y = (int)date('Y'); $y >= 1900; $y--): ?>
            <option value="<?= e((string)$y) ?>" <?= $year === (string)$y ? 'selected' : '' ?>><?= e((string)$y) ?></option>
          <?php endfor; ?>
        </select>
      </label>
      <label class="streamhive-search-field">
        <span>Score</span>
        <select name="user_rating" class="form-select" aria-label="Filter by user rating">
          <option value="">Any score</option>
          <?php foreach (($userRatingOptions ?? []) as $option): ?><option value="<?= e((string)$option['value']) ?>" <?= (string)($score ?? '') === (string)$option['value'] ? 'selected' : '' ?>><?= e((string)$option['label']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="streamhive-search-field">
        <span>Sort</span>
        <select name="sort" class="form-select" aria-label="Sort results">
          <?php foreach ($sortOptions as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="streamhive-search-actions">
        <button class="btn btn-warning" type="submit"><i class="fa-solid fa-magnifying-glass"></i><span>Search</span></button>
        <a class="btn btn-outline-light" href="<?= e(url('s')) ?>"><i class="fa-solid fa-rotate-left"></i><span>Reset</span></a>
      </div>
    </form>
  </section>

  <section class="streamhive-search-results" aria-label="Search results">
    <div class="streamhive-search-results-head">
      <div>
        <span class="streamhive-v2-section-eyebrow"><i class="fa-solid fa-layer-group"></i> <?= e($itemLabel) ?></span>
        <h2><?= e(number_format((int)$total)) ?> results</h2>
      </div>
      <?php if ($pages > 1): ?><span class="streamhive-search-page-count">Page <?= e((string)$page) ?> of <?= e((string)$pages) ?></span><?php endif; ?>
    </div>

    <?= View::partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage ?? 24, 'itemLabel' => $itemLabel, 'position' => 'top']) ?>

    <?php if ($items): ?>
      <div class="row g-3 streamhive-search-grid">
        <?php foreach ($items as $item): echo View::partial('partials/media-card', ['item' => $item, 'type' => $item['media_type'] ?? 'movie', 'allowMissingPoster' => true]); endforeach; ?>
      </div>
    <?php else: ?>
      <div class="streamhive-search-empty">
        <i class="fa-solid fa-magnifying-glass"></i>
        <h3>No results found</h3>
        <p>Try another title, genre, year, score, or result type.</p>
      </div>
    <?php endif; ?>

    <?= View::partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage ?? 24, 'itemLabel' => $itemLabel]) ?>
  </section>
</div>
