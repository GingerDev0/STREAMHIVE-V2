<?php use App\Core\View; require_once app_path('app/Helpers/helpers.php');
$heroMovies = array_values($heroMovies ?? []);
$heroBackground = $heroMovies[0] ?? ($moviesTrending[0] ?? $moviesRecent[0] ?? null);
?>
<section class="v2-detail-hero v2-home-hero-carousel mb-4" aria-label="Featured random movies">
  <?php if ($heroBackground): ?><div class="v2-detail-backdrop v2-home-hero-backdrop" style="background-image:url('<?= e(tmdb_img($heroBackground['backdrop_path'] ?? ($heroBackground['poster_path'] ?? null), 'w1280')) ?>')"></div><?php endif; ?>
  <div class="v2-home-hero-shell">
    <?php if ($heroMovies): ?>
    <div class="splide v2-home-splide js-home-hero-splide" aria-label="Random movie spotlight carousel">
      <div class="splide__track">
        <ul class="splide__list">
          <?php foreach ($heroMovies as $movie):
            $movieTitle = (string)($movie['title'] ?? $movie['name'] ?? 'Untitled movie');
            $movieSlug = (string)($movie['slug'] ?? slugify($movieTitle));
            $movieUrl = url('movies/' . $movieSlug);
            $moviePoster = tmdb_img($movie['poster_path'] ?? ($movie['backdrop_path'] ?? null), 'w500');
            $movieBackdrop = tmdb_img($movie['backdrop_path'] ?? ($movie['poster_path'] ?? null), 'w1280');
            $movieDate = format_date((string)($movie['release_date'] ?? ''));
            $movieRuntime = media_runtime($movie, 'movie');
            $movieRating = round((float)($movie['vote_average'] ?? 0), 1);
            $movieGenres = is_array($movie['genres'] ?? null) ? array_slice($movie['genres'], 0, 3) : [];
            $movieOverview = meta_excerpt((string)($movie['overview'] ?? 'Discover this movie in StreamHIVE.'), 190);
            $movieMediaPayload = media_storage_payload($movie, 'movie', $movieUrl, $movieTitle);
          ?>
          <li class="splide__slide">
            <article class="v2-home-hero-slide" data-hero-backdrop="<?= e($movieBackdrop) ?>">
              <div class="v2-detail-grid v2-home-hero-grid">
                <a class="v2-detail-poster-wrap v2-home-hero-poster-link js-media-link" href="<?= e($movieUrl) ?>" data-fetch-content="1" data-media='<?= $movieMediaPayload ?>' aria-label="Watch <?= e($movieTitle) ?>">
                  <img class="v2-detail-poster" src="<?= e($moviePoster) ?>" alt="<?= e($movieTitle) ?> poster" loading="lazy">
                  <span class="v2-play-float v2-home-hero-play"><i class="fa-solid fa-play"></i></span>
                </a>

                <div class="v2-detail-copy v2-home-hero-copy">
                  <span class="v2-kicker"><i class="fa-solid fa-film"></i> Movie spotlight</span>
                  <h2><?= e($movieTitle) ?></h2>
                  <div class="v2-chip-row mb-3">
                    <?php if ($movieDate): ?><span><i class="fa-solid fa-calendar"></i> <?= e($movieDate) ?></span><?php endif; ?>
                    <?php if ($movieRuntime): ?><span><i class="fa-regular fa-clock"></i> <?= e($movieRuntime) ?></span><?php endif; ?>
                    <?php if ($movieRating > 0): ?><span><i class="fa-solid fa-star"></i> <?= e((string)$movieRating) ?></span><?php endif; ?>
                  </div>
                  <?php if ($movieGenres): ?><div class="v2-genre-row mb-3"><?= genre_links($movieGenres, 'movie', 3) ?></div><?php endif; ?>
                  <p class="v2-lead v2-home-hero-overview"><?= e($movieOverview) ?></p>
                  <div class="v2-hero-actions">
                    <a class="btn btn-warning btn-lg js-media-link" href="<?= e($movieUrl) ?>" data-fetch-content="1" data-media='<?= $movieMediaPayload ?>'><i class="fa-solid fa-play me-2"></i>Watch Now</a>
                  </div>
                </div>
              </div>
            </article>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>


<?php foreach ([['Recent Movies',$moviesRecent,'movie','fa-fire'],['Trending Movies',$moviesTrending,'movie','fa-arrow-trend-up'],['Recent TV Shows',$tvRecent,'tv','fa-satellite-dish'],['Trending TV Shows',$tvTrending,'tv','fa-bolt']] as [$heading,$items,$type,$icon]): ?>
<section class="v2-section mb-5">
  <div class="v2-section-head">
    <div><span class="v2-section-eyebrow"><i class="fa-solid <?= e($icon) ?>"></i> <?= e($type === 'tv' ? 'Series' : 'Cinema') ?></span><h2><?= e($heading) ?></h2></div>
    <a href="<?= e($type === 'tv' ? url('tv') : url('movies')) ?>">View all <i class="fa-solid fa-arrow-right"></i></a>
  </div>
  <div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-2 g-lg-3 home-card-grid v2-home-grid"><?php foreach (array_slice($items, 0, 12) as $item) { $variant = 'home'; echo View::partial('partials/media-card', compact('item','type','variant')); } ?></div>
</section>
<?php endforeach; ?>
