<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php
  $movies = array_values(array_filter($movies ?? [], static fn(array $item): bool => has_media_poster($item)));
  $tvShows = array_values(array_filter($tvShows ?? [], static fn(array $item): bool => has_media_poster($item)));
  $comingBackdropPool = array_values(array_filter(array_merge($movies ?? [], $tvShows ?? []), static function (array $item): bool {
      return !empty($item['backdrop_path']) || !empty($item['poster_path']);
  }));
  $comingHeroItem = $comingBackdropPool ? $comingBackdropPool[array_rand($comingBackdropPool)] : null;
  $comingHeroBackdrop = $comingHeroItem ? tmdb_img($comingHeroItem['backdrop_path'] ?? ($comingHeroItem['poster_path'] ?? null), !empty($comingHeroItem['backdrop_path']) ? 'w1280' : 'w780') : '';
  $comingPerPage = 18;
?>
<section class="streamhive-coming-hero streamhive-glass rounded-4 overflow-hidden mb-4<?= $comingHeroBackdrop !== '' ? ' has-backdrop' : '' ?>"<?= $comingHeroBackdrop !== '' ? ' style="--coming-hero-bg:url(' . e($comingHeroBackdrop) . ')"' : '' ?>>
  <?php if ($comingHeroBackdrop !== ''): ?><div class="streamhive-coming-hero-backdrop" aria-hidden="true"></div><?php endif; ?>
  <div class="streamhive-coming-hero-glow"></div>
  <div class="streamhive-coming-hero-inner">
    <span class="streamhive-v2-kicker"><i class="fa-solid fa-calendar-days"></i> Coming this year</span>
    <h1>Coming in <?= e((string)$year) ?></h1>
    <p>Upcoming movies and TV shows scheduled for this year. These titles are listed for discovery only and unlock when their release date arrives.</p>
    <div class="streamhive-coming-hero-stats">
      <span><strong><?= e((string)count($movies)) ?></strong> Movies</span>
      <span><strong><?= e((string)count($tvShows)) ?></strong> TV Shows</span>
    </div>
  </div>
</section>

<section class="streamhive-coming-tabs-shell streamhive-glass rounded-4 p-3 p-lg-4">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <span class="streamhive-v2-section-eyebrow"><i class="fa-solid fa-clock"></i> Release calendar</span>
      <h2 class="mb-0 text-white fw-black">Upcoming releases</h2>
    </div>
    <div class="streamhive-coming-tabs" role="tablist" aria-label="Coming this year tabs">
      <button class="streamhive-coming-tab active" type="button" data-coming-tab="movie"><i class="fa-solid fa-film"></i> Movies <span><?= e((string)count($movies)) ?></span></button>
      <button class="streamhive-coming-tab" type="button" data-coming-tab="tv"><i class="fa-solid fa-tv"></i> TV Shows <span><?= e((string)count($tvShows)) ?></span></button>
    </div>
  </div>

  <?php foreach ([['movie', 'Movies', $movies], ['tv', 'TV Shows', $tvShows]] as [$tabType, $tabLabel, $items]): ?>
    <div class="streamhive-coming-panel <?= $tabType === 'movie' ? 'active' : '' ?>" data-coming-panel="<?= e($tabType) ?>">
      <?php if ($items): ?>
        <?php
          $comingTotal = count($items);
          $comingPages = max(1, (int)ceil($comingTotal / $comingPerPage));
          $comingVisibleTo = min($comingTotal, $comingPerPage);
        ?>
        <div class="streamhive-coming-grid" data-coming-grid="<?= e($tabType) ?>" data-per-page="<?= e((string)$comingPerPage) ?>" data-total="<?= e((string)count($items)) ?>" data-loaded-page="1">
          <?php foreach (array_slice($items, 0, $comingPerPage) as $item): ?>
            <?= \App\Core\View::partial('partials/coming-card', ['item' => $item, 'tabType' => $tabType]) ?>
          <?php endforeach; ?>
        </div>
        <div class="streamhive-coming-footer mt-4" data-coming-pagination="<?= e($tabType) ?>">
          <?php if ($comingPages > 1): ?>
            <div class="streamhive-actor-pager-bar streamhive-coming-pager-bar">
              <div class="streamhive-pager-showing">
                <span>Showing</span>
                <strong>1<?= $comingVisibleTo !== 1 ? '&ndash;' . e((string)$comingVisibleTo) : '' ?></strong>
                <span>of</span>
                <strong><?= e((string)$comingTotal) ?></strong>
                <span><?= e($tabLabel) ?></span>
              </div>
              <div class="streamhive-actor-pager-actions streamhive-coming-pager-actions">
                <span class="streamhive-actor-page-current">Page <strong>1</strong> of <?= e((string)$comingPages) ?></span>
                <button type="button" class="streamhive-actor-page-btn streamhive-coming-page-btn" data-coming-page="<?= e($tabType) ?>" data-page="2" aria-label="Next page"><i class="fa-solid fa-angle-right"></i></button>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="streamhive-coming-empty text-center py-5">
          <i class="fa-solid <?= $tabType === 'movie' ? 'fa-film' : 'fa-tv' ?> mb-3"></i>
          <h3>No upcoming <?= e(strtolower($tabLabel)) ?> found yet</h3>
          <p class="text-white-50 mb-0">TMDB results will appear here after imports/prefetching have data for this year.</p>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>

<div class="modal fade streamhive-coming-info-modal" id="comingInfoModal" tabindex="-1" aria-hidden="true" aria-labelledby="comingInfoTitle" data-bs-backdrop="false">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content streamhive-coming-info-modal-content">
      <div class="streamhive-coming-info-modal-header">
        <div class="streamhive-coming-info-header">
          <span class="streamhive-coming-info-type"><i class="fa-solid fa-film" data-coming-info-type-icon></i><span data-coming-info-type>Movie</span></span>
          <h2 id="comingInfoTitle" data-coming-info-title>Title</h2>
        </div>
        <button type="button" class="streamhive-coming-info-close" data-bs-dismiss="modal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="streamhive-coming-info-backdrop" data-coming-info-backdrop></div>
      <div class="streamhive-coming-info-body">
        <div class="streamhive-coming-info-poster"><img src="/assets/img/placeholder.jpg" alt="" data-coming-info-poster></div>
        <div class="streamhive-coming-info-copy">
          <div class="streamhive-coming-info-details">
            <div class="streamhive-coming-info-meta">
              <span data-coming-info-date></span>
              <span data-coming-info-rating></span>
            </div>
            <p data-coming-info-overview></p>
            <div class="streamhive-coming-info-genres" data-coming-info-genres></div>
          </div>
          <div class="streamhive-coming-info-trailer" data-coming-info-trailer hidden>
            <div class="streamhive-coming-info-trailer-head">
              <span><i class="fa-brands fa-youtube"></i> Trailer</span>
              <a href="#" target="_blank" rel="noopener noreferrer" data-coming-info-trailer-link>Open on YouTube</a>
            </div>
            <div class="streamhive-coming-info-trailer-frame" data-coming-info-trailer-frame></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
