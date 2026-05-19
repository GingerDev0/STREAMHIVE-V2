<?php require_once app_path('app/Helpers/helpers.php'); use App\Core\View; ?>
<div class="js-jquery-listing-shell jquery-listing-shell" data-jquery-listing="<?= e($type === 'movie' ? 'movies' : 'tv') ?>">
<section class="glass rounded-4 p-4 mb-4 text-white">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div><h1 class="h2 mb-1"><?= e($heading) ?></h1><p class="text-white-50 mb-0"><?= e((string)$total) ?> saved <?= $type === 'movie' ? 'movies' : 'TV shows' ?></p></div>
    <a class="btn btn-outline-light" href="<?= e(url('s')) ?>"><i class="fa-solid fa-magnifying-glass me-1"></i> Advanced search</a>
  </div>
  <form class="row g-2" method="get">
    <div class="col-md-2"><select name="genre" class="form-select"><option value="">All genres</option><?php foreach ($genres as $g): ?><option value="<?= e($g) ?>" <?= $genre===$g?'selected':'' ?>><?= e($g) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select name="rating" class="form-select"><option value="">Any age rating</option><?php foreach ($ratings as $r): ?><option value="<?= e($r) ?>" <?= $rating===$r?'selected':'' ?>><?= e($r) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select name="user_rating" class="form-select" aria-label="Filter by user rating"><option value="">Any TMDB rating</option><?php foreach (($userRatingOptions ?? []) as $option): ?><option value="<?= e((string)$option['value']) ?>" <?= (string)($score ?? '') === (string)$option['value'] ? 'selected' : '' ?>><?= e((string)$option['label']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2">
      <select name="year" class="form-select" aria-label="Filter by year">
        <option value="">Any year</option>
        <?php for ($y = (int)date('Y'); $y >= 1900; $y--): ?>
          <option value="<?= e((string)$y) ?>" <?= $year === (string)$y ? 'selected' : '' ?>><?= e((string)$y) ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col-md-2"><select name="sort" class="form-select">
      <?php foreach (['title_asc'=>'Title A-Z','title_desc'=>'Title Z-A','date_desc'=>'Newest release','date_asc'=>'Oldest release','rating_desc'=>'Top rated','rating_asc'=>'Lowest rated','updated_desc'=>'Recently updated'] as $value=>$label): ?>
        <option value="<?= e($value) ?>" <?= $sort===$value?'selected':'' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-warning"><i class="fa-solid fa-filter me-1"></i>Filter</button></div>
  </form>
</section>

<?= View::partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage ?? 24, 'itemLabel' => $type === 'movie' ? 'Movies' : 'TV Shows', 'position' => 'top']) ?>

<div class="row g-3">
<?php foreach ($items as $item): $cardType = $item['media_type'] ?? $type; echo View::partial('partials/media-card', ['item'=>$item, 'type'=>$cardType]); endforeach; ?>
<?php if (!$items): ?><div class="col-12"><div class="glass rounded-4 p-4 text-white">No results found.</div></div><?php endif; ?>
</div>

<?= View::partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage ?? 24, 'itemLabel' => $type === 'movie' ? 'Movies' : 'TV Shows']) ?>
</div>
