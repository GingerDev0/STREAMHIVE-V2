<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php
  $movies = array_values(array_filter($movies ?? [], static fn(array $item): bool => has_media_poster($item)));
  $tvShows = array_values(array_filter($tvShows ?? [], static fn(array $item): bool => has_media_poster($item)));
  $comingBackdropPool = array_values(array_filter(array_merge($movies ?? [], $tvShows ?? []), static function (array $item): bool {
      return !empty($item['backdrop_path']) || !empty($item['poster_path']);
  }));
  $comingHeroItem = $comingBackdropPool ? $comingBackdropPool[array_rand($comingBackdropPool)] : null;
  $comingHeroBackdrop = $comingHeroItem ? tmdb_img($comingHeroItem['backdrop_path'] ?? ($comingHeroItem['poster_path'] ?? null), !empty($comingHeroItem['backdrop_path']) ? 'w1280' : 'w780') : '';
?>
<section class="coming-hero glass rounded-4 overflow-hidden mb-4<?= $comingHeroBackdrop !== '' ? ' has-backdrop' : '' ?>"<?= $comingHeroBackdrop !== '' ? ' style="--coming-hero-bg:url(' . e($comingHeroBackdrop) . ')"' : '' ?>>
  <?php if ($comingHeroBackdrop !== ''): ?><div class="coming-hero-backdrop" aria-hidden="true"></div><?php endif; ?>
  <div class="coming-hero-glow"></div>
  <div class="coming-hero-inner">
    <span class="v2-kicker"><i class="fa-solid fa-calendar-days"></i> Coming this year</span>
    <h1>Coming in <?= e((string)$year) ?></h1>
    <p>Upcoming movies and TV shows scheduled for this year. These titles are listed for discovery only and unlock when their release date arrives.</p>
    <div class="coming-hero-stats">
      <span><strong><?= e((string)count($movies)) ?></strong> Movies</span>
      <span><strong><?= e((string)count($tvShows)) ?></strong> TV Shows</span>
    </div>
  </div>
</section>

<section class="coming-tabs-shell glass rounded-4 p-3 p-lg-4">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <span class="v2-section-eyebrow"><i class="fa-solid fa-clock"></i> Release calendar</span>
      <h2 class="mb-0 text-white fw-black">Upcoming releases</h2>
    </div>
    <div class="coming-tabs" role="tablist" aria-label="Coming this year tabs">
      <button class="coming-tab active" type="button" data-coming-tab="movie"><i class="fa-solid fa-film"></i> Movies <span><?= e((string)count($movies)) ?></span></button>
      <button class="coming-tab" type="button" data-coming-tab="tv"><i class="fa-solid fa-tv"></i> TV Shows <span><?= e((string)count($tvShows)) ?></span></button>
    </div>
  </div>

  <?php foreach ([['movie', 'Movies', $movies], ['tv', 'TV Shows', $tvShows]] as [$tabType, $tabLabel, $items]): ?>
    <div class="coming-panel <?= $tabType === 'movie' ? 'active' : '' ?>" data-coming-panel="<?= e($tabType) ?>">
      <?php if ($items): ?>
        <div class="coming-grid" data-coming-grid="<?= e($tabType) ?>" data-per-page="18">
          <?php foreach ($items as $item):
            $title = (string)($item['title'] ?? 'Untitled');
            $date = media_release_date($item);
            $prettyDate = format_date($date);
            $poster = tmdb_img($item['poster_path'] ?? null, 'w500');
            $backdrop = tmdb_img($item['backdrop_path'] ?? ($item['poster_path'] ?? null), !empty($item['backdrop_path']) ? 'w1280' : 'w780');
            $rating = round((float)($item['vote_average'] ?? 0), 1);
            $genres = array_values(array_filter(array_map('strval', $item['genres'] ?? [])));
            $overview = trim((string)($item['overview'] ?? ''));
            $typeLabel = $tabType === 'tv' ? 'TV Show' : 'Movie';
          ?>
            <article class="coming-card" data-coming-item data-coming-modal
              role="button" tabindex="0" aria-label="View details for <?= e($title) ?>"
              data-title="<?= e($title) ?>"
              data-type="<?= e($typeLabel) ?>"
              data-date="<?= e($prettyDate) ?>"
              data-rating="<?= e($rating > 0 ? (string)$rating : '') ?>"
              data-genres="<?= e(implode(', ', $genres)) ?>"
              data-overview="<?= e($overview) ?>"
              data-poster="<?= e($poster) ?>"
              data-backdrop="<?= e($backdrop) ?>">
              <div class="coming-poster" aria-label="<?= e($title) ?> is not released yet">
                <img src="<?= e($poster) ?>" alt="<?= e($title) ?> poster">
                <span class="coming-poster-gradient"></span>
                <span class="coming-soon-pill"><i class="fa-solid fa-lock"></i> Locked until release</span>
                <span class="coming-info-pill"><i class="fa-solid fa-circle-info"></i> Details</span>
                <?php if ($rating > 0): ?><span class="coming-rating"><i class="fa-solid fa-star"></i> <?= e((string)$rating) ?></span><?php endif; ?>
              </div>
              <div class="coming-copy">
                <span class="coming-title"><?= e($title) ?></span>
                <?php if ($prettyDate !== ''): ?><span class="coming-date"><i class="fa-solid fa-calendar-days"></i> <?= e($prettyDate) ?></span><?php endif; ?>
                <?php if ($genres): ?><span class="coming-genres"><?= genre_links($genres, $tabType, 2, 'genre-link genre-link-home') ?></span><?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <div class="coming-footer mt-4" data-coming-pagination="<?= e($tabType) ?>"></div>
      <?php else: ?>
        <div class="coming-empty text-center py-5">
          <i class="fa-solid <?= $tabType === 'movie' ? 'fa-film' : 'fa-tv' ?> mb-3"></i>
          <h3>No upcoming <?= e(strtolower($tabLabel)) ?> found yet</h3>
          <p class="text-white-50 mb-0">TMDB results will appear here after imports/prefetching have data for this year.</p>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>

<div class="modal fade coming-info-modal" id="comingInfoModal" tabindex="-1" aria-hidden="true" aria-labelledby="comingInfoTitle" data-bs-backdrop="false">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content coming-info-modal-content">
      <button type="button" class="coming-info-close" data-bs-dismiss="modal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
      <div class="coming-info-backdrop" data-coming-info-backdrop></div>
      <div class="coming-info-body">
        <div class="coming-info-poster"><img src="/assets/img/placeholder.jpg" alt="" data-coming-info-poster></div>
        <div class="coming-info-copy">
          <span class="coming-info-type" data-coming-info-type>Movie</span>
          <h2 id="comingInfoTitle" data-coming-info-title>Title</h2>
          <div class="coming-info-meta">
            <span data-coming-info-date></span>
            <span data-coming-info-rating></span>
          </div>
          <p data-coming-info-overview></p>
          <div class="coming-info-genres" data-coming-info-genres></div>
        </div>
      </div>
    </div>
  </div>
</div>
