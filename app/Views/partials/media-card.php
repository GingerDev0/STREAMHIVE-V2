<?php require_once app_path('app/Helpers/helpers.php');
$type = $type ?? (($item['media_type'] ?? '') === 'person' ? 'person' : (($item['media_type'] ?? '') === 'tv' ? 'tv' : 'movie'));
$variant = $variant ?? 'poster';
$allowMissingPoster = (bool)($allowMissingPoster ?? false);
if (!$allowMissingPoster && ($type === 'movie' || $type === 'tv') && !has_media_poster($item)) {
    return;
}
$title = $item['title'] ?? $item['name'] ?? 'Untitled';
$slug = media_slug($item + ['title' => $title], $type);
$link = $type === 'person' ? url('actors/' . $slug) : ($type === 'tv' ? url('tv/' . $slug) : url('movies/' . $slug));
$rating = round((float)($item['vote_average'] ?? 0), 1);
$genres = array_values(array_filter(array_map('strval', $item['genres'] ?? [])));
$genreText = genre_links($genres, $type, 2, 'streamhive-genre-link streamhive-genre-link-home');
$ageRating = display_age_rating($item['age_rating'] ?? '', $type);
$overview = trim((string)($item['overview'] ?? $item['biography'] ?? '')) ?: 'No overview available yet.';
$importStatus = (string)($item['import_status'] ?? '');
$isFetchReady = $importStatus === 'full';
if ($type === 'movie' || $type === 'tv') {
    $isFetchReady = $isFetchReady && !empty($item['cast']);
}
if ($type === 'person') {
    $isFetchReady = $isFetchReady && array_key_exists('biography', $item);
}
$fetchAttr = $isFetchReady ? '0' : '1';
?>
<?php if ($type === 'person'): ?>
<?php
$knownFor = [];
foreach (($item['known_for'] ?? []) as $credit) $knownFor[] = $credit['title'] ?? '';
foreach (($item['credits'] ?? []) as $credit) $knownFor[] = $credit['title'] ?? '';
$knownFor = array_values(array_unique(array_filter(array_map('strval', $knownFor))));
$profile = tmdb_img($item['profile_path'] ?? null, 'w500');
?>
<div class="col-6 col-md-3 col-xl-2">
  <a class="card streamhive-media-card h-100 text-decoration-none streamhive-person-result-card streamhive-js-media-link" href="<?= e($link) ?>" data-fetch-content="<?= e($fetchAttr) ?>" data-media="<?= media_storage_payload($item, $type, $link) ?>">
    <img src="<?= e($profile) ?>" class="card-img-top" alt="<?= e($title) ?> profile">
    <div class="card-body">
      <h3 class="h6 card-title text-white mb-1"><?= e($title) ?></h3>
      <div class="small text-white-50"><?= e((string)($item['known_for_department'] ?? 'Actor')) ?></div>
      <?php if ($knownFor): ?><div class="small text-warning mt-1"><?= e(implode(' · ', array_slice($knownFor, 0, 2))) ?></div><?php endif; ?>
    </div>
  </a>
</div>
<?php elseif ($variant === 'home'): ?>
<?php
$year = substr((string)($item['release_date'] ?? $item['first_air_date'] ?? ''), 0, 4);
$homePoster = tmdb_img($item['poster_path'] ?? null, 'w500');
?>
<div class="col">
  <a class="streamhive-home-poster-card streamhive-js-media-link" href="<?= e($link) ?>" aria-label="Open <?= e($title) ?>" data-fetch-content="<?= e($fetchAttr) ?>" data-media="<?= media_storage_payload($item, $type, $link) ?>">
    <img src="<?= e($homePoster) ?>" class="streamhive-home-poster-card-img" alt="<?= e($title) ?> poster">
    <button class="streamhive-home-bookmark streamhive-js-bookmark-btn" type="button" aria-label="Save <?= e($title) ?>" data-media="<?= media_storage_payload($item, $type, $link) ?>"><i class="fa-regular fa-bookmark"></i></button>
    <span class="streamhive-home-poster-play"><i class="fa-solid fa-play"></i></span>
    <span class="streamhive-home-card-sheen" aria-hidden="true"></span>
    <span class="streamhive-home-poster-gradient" aria-hidden="true"></span>
    <span class="streamhive-home-poster-content">
      <span class="streamhive-home-rating-badge"><?= e((string)$rating) ?></span>
      <span class="streamhive-home-poster-title"><?= e($title) ?></span>
      <?php if ($year !== ''): ?><span class="streamhive-home-poster-year"><?= e($year) ?></span><?php endif; ?>
    </span>
  </a>
</div>
<?php else: ?>
<div class="col-6 col-md-3 col-xl-2">
  <a class="card streamhive-media-card streamhive-listing-media-card h-100 text-decoration-none streamhive-js-media-link" href="<?= e($link) ?>" data-fetch-content="<?= e($fetchAttr) ?>" data-media="<?= media_storage_payload($item, $type, $link) ?>">
    <span class="streamhive-listing-poster-wrap">
      <img src="<?= e(tmdb_img($item['poster_path'] ?? null)) ?>" class="card-img-top" alt="<?= e($title) ?>">
      <button class="streamhive-listing-bookmark streamhive-home-bookmark streamhive-js-bookmark-btn" type="button" aria-label="Save <?= e($title) ?>" data-media="<?= media_storage_payload($item, $type, $link) ?>"><i class="fa-regular fa-bookmark"></i></button>
      <span class="streamhive-listing-play streamhive-home-poster-play"><i class="fa-solid fa-play"></i></span>
      <span class="streamhive-listing-poster-gradient" aria-hidden="true"></span>
    </span>
    <div class="card-body">
      <h3 class="h6 card-title text-white mb-1"><?= e($title) ?></h3>
      <?php $runtimeText = media_runtime($item, $type); ?>
      <div class="small streamhive-listing-card-meta text-white-50">
        <span class="streamhive-listing-card-rating"><i class="fa-solid fa-star"></i> <?= e((string)$rating) ?></span>
        <?php if ($runtimeText !== ''): ?>
          <span class="streamhive-listing-card-runtime"><i class="fa-regular fa-clock"></i> <?= e($runtimeText) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </a>
</div>
<?php endif; ?>
