<?php use App\Core\View; require_once app_path('app/Helpers/helpers.php'); ?>
<?php
/** @var array $collectionMovies */
$collectionMovies = array_values(array_filter($collectionMovies ?? [], static function (array $movie): bool {
    $title = trim((string)($movie['title'] ?? $movie['name'] ?? ''));
    if ($title === '') return false;
    if (!has_media_poster($movie)) return false;
    return is_released_media($movie);
}));
$collectionName = trim((string)($collectionMovies[0]['collection_name'] ?? ''));
$collectionBackdrop = tmdb_img($collectionMovies[0]['collection_backdrop_path'] ?? ($collectionMovies[0]['backdrop_path'] ?? null), 'w1280');
?>
<?php if ($collectionMovies): ?>
<section class="v2-section v2-collection-section mt-4 mb-4" aria-label="Movies in this collection">
  <div class="v2-collection-backdrop" style="background-image:url('<?= e($collectionBackdrop) ?>')"></div>
  <div class="v2-collection-overlay"></div>
  <div class="v2-collection-inner">
    <div class="v2-section-head v2-collection-head">
      <div>
        <span class="v2-section-eyebrow"><i class="fa-solid fa-layer-group"></i> <?= e($collectionName !== '' ? $collectionName : 'Collection') ?></span>
        <h2>Movies In This Collection</h2>
      </div>
    </div>
    <div class="splide v2-collection-splide js-collection-splide" aria-label="Movies in this collection">
      <div class="splide__track">
        <ul class="splide__list">
          <?php foreach ($collectionMovies as $movie): ?>
            <?php
              $type = 'movie';
              $movieTitle = (string)($movie['title'] ?? $movie['name'] ?? 'Untitled movie');
              $movieSlug = media_slug($movie + ['title' => $movieTitle], 'movie');
              $movieUrl = url('movies/' . $movieSlug);
              $moviePoster = tmdb_img($movie['poster_path'] ?? null, 'w500');
              $movieYear = substr((string)($movie['release_date'] ?? ''), 0, 4);
              $movieRating = round((float)($movie['vote_average'] ?? 0), 1);
              $movieMediaPayload = media_storage_payload($movie, 'movie', $movieUrl, $movieTitle);
              $fetchAttr = '0';
            ?>
            <li class="splide__slide">
              <a class="home-poster-card js-media-link" href="<?= e($movieUrl) ?>" aria-label="Open <?= e($movieTitle) ?>" data-fetch-content="<?= e($fetchAttr) ?>" data-media='<?= $movieMediaPayload ?>'>
                <img src="<?= e($moviePoster) ?>" class="home-poster-card-img" alt="<?= e($movieTitle) ?> poster">
                <button class="home-bookmark js-bookmark-btn" type="button" aria-label="Save <?= e($movieTitle) ?>" data-media='<?= $movieMediaPayload ?>'><i class="fa-regular fa-bookmark"></i></button>
                <span class="home-poster-play"><i class="fa-solid fa-play"></i></span>
                <span class="home-card-sheen" aria-hidden="true"></span>
                <span class="home-poster-gradient" aria-hidden="true"></span>
                <span class="home-poster-content">
                  <?php if ($movieRating > 0): ?><span class="home-rating-badge"><?= e((string)$movieRating) ?></span><?php endif; ?>
                  <span class="home-poster-title"><?= e($movieTitle) ?></span>
                  <?php if ($movieYear !== ''): ?><span class="home-poster-year"><?= e($movieYear) ?></span><?php endif; ?>
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>
