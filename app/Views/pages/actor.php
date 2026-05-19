<?php
require_once app_path('app/Helpers/helpers.php');

$credits = array_values($actor['credits'] ?? []);
$creditDate = static fn(array $credit): string => media_release_date($credit);
$sortNewToOld = static function (array $a, array $b) use ($creditDate): int {
    $dateA = $creditDate($a);
    $dateB = $creditDate($b);
    if ($dateA === $dateB) return strnatcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
    if ($dateA === '') return 1;
    if ($dateB === '') return -1;
    return strcmp($dateB, $dateA);
};

usort($credits, $sortNewToOld);
$productionCredits = array_values(array_filter($credits, static fn(array $credit): bool => is_future_date(media_release_date($credit))));
$releasedCredits = array_values(array_filter($credits, static fn(array $credit): bool => !is_future_date(media_release_date($credit))));
$movieCredits = array_values(array_filter($releasedCredits, static fn(array $credit): bool => ($credit['media_type'] ?? 'movie') === 'movie'));
$tvCredits = array_values(array_filter($releasedCredits, static fn(array $credit): bool => ($credit['media_type'] ?? '') === 'tv'));
$birthday = format_date($actor['birthday'] ?? null);
$profile = tmdb_img($actor['profile_path'] ?? null, 'w500');
?>
<section class="actor-hero glass rounded-4 overflow-hidden mb-4">
  <div class="actor-hero-bg" style="background-image:url('<?= e($profile) ?>')"></div>
  <div class="row g-0 align-items-stretch actor-hero-grid">
    <div class="col-lg-3 col-md-4">
      <div class="actor-profile-poster">
        <img src="<?= e($profile) ?>" alt="<?= e($actor['name']) ?> profile">
      </div>
    </div>
    <div class="col-lg-9 col-md-8">
      <div class="actor-hero-copy">
        <span class="v2-kicker"><i class="fa-solid fa-star"></i> Actor profile</span>
        <h1><?= e($actor['name']) ?></h1>
        <div class="actor-meta-row">
          <?php if ($birthday !== ''): ?><span><i class="fa-solid fa-cake-candles"></i> <?= e($birthday) ?></span><?php endif; ?>
          <?php if (!empty($actor['place_of_birth'])): ?><span><i class="fa-solid fa-location-dot"></i> <?= e($actor['place_of_birth']) ?></span><?php endif; ?>
          <?php if (!empty($actor['known_for_department'])): ?><span><i class="fa-solid fa-clapperboard"></i> <?= e($actor['known_for_department']) ?></span><?php endif; ?>
        </div>
        <p class="actor-bio"><?= e((string)($actor['biography'] ?? 'Biography coming soon.')) ?></p>
        <div class="actor-stat-row">
          <span><strong><?= e((string)count($movieCredits)) ?></strong> Movies</span>
          <span><strong><?= e((string)count($tvCredits)) ?></strong> TV Shows</span>
          <?php if ($productionCredits): ?><span><strong><?= e((string)count($productionCredits)) ?></strong> In Production</span><?php endif; ?>
          <span><strong><?= e((string)count($credits)) ?></strong> Credits</span>
        </div>
        <div class="v2-hero-actions actor-share-actions mt-3">
          <?= share_button($actor['name'] ?? 'Actor profile', actor_url($actor)) ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="actor-credits-shell glass rounded-4 p-3 p-lg-4">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <span class="v2-section-eyebrow"><i class="fa-solid fa-layer-group"></i> Filmography</span>
      <h2 class="mb-0 text-white fw-black">Movies & TV Shows</h2>
    </div>
    <div class="actor-tabs" role="tablist" aria-label="Actor credits tabs">
      <button class="actor-tab active" type="button" data-actor-tab="movie"><i class="fa-solid fa-film"></i> Movies <span><?= e((string)count($movieCredits)) ?></span></button>
      <button class="actor-tab" type="button" data-actor-tab="tv"><i class="fa-solid fa-tv"></i> TV Shows <span><?= e((string)count($tvCredits)) ?></span></button>
      <?php if ($productionCredits): ?><button class="actor-tab" type="button" data-actor-tab="production"><i class="fa-solid fa-screwdriver-wrench"></i> In Production <span><?= e((string)count($productionCredits)) ?></span></button><?php endif; ?>
    </div>
  </div>

  <?php foreach ([['movie', 'Movies', $movieCredits], ['tv', 'TV Shows', $tvCredits], ['production', 'In Production', $productionCredits]] as [$tabType, $tabLabel, $items]): ?>
    <?php if ($tabType === 'production' && !$items) continue; ?>
    <div class="actor-credit-panel <?= $tabType === 'movie' ? 'active' : '' ?>" data-actor-panel="<?= e($tabType) ?>">
      <?php if ($items): ?>
        <div class="actor-credit-grid" data-actor-grid="<?= e($tabType) ?>" data-per-page="12">
          <?php foreach ($items as $credit):
              $title = (string)($credit['title'] ?? 'Untitled');
              $date = media_release_date($credit);
              $prettyDate = format_date($date);
              $isFuture = is_future_date($date);
              $href = media_url($credit);
              $creditType = (($credit['media_type'] ?? '') === 'tv') ? 'tv' : 'movie';
              $creditMedia = media_storage_payload($credit, $creditType, $href);
              $character = trim((string)($credit['character'] ?? ''));
              $poster = tmdb_img($credit['poster_path'] ?? null, 'w500');
          ?>
            <article class="actor-credit-card <?= $isFuture ? 'is-production' : '' ?>" data-actor-credit-item>
              <?php if ($isFuture): ?>
                <div class="actor-credit-poster actor-credit-poster-disabled" aria-label="<?= e($title) ?> is not released yet">
                  <img src="<?= e($poster) ?>" alt="<?= e($title) ?> poster" loading="lazy">
                  <span class="actor-credit-production-badge"><i class="fa-solid fa-clock"></i> Coming soon</span>
                </div>
              <?php else: ?>
                <a href="<?= e($href) ?>" class="actor-credit-poster js-media-link" aria-label="Open <?= e($title) ?>" data-fetch-content="1" data-media="<?= $creditMedia ?>">
                  <img src="<?= e($poster) ?>" alt="<?= e($title) ?> poster" loading="lazy">
                  <span class="actor-credit-play"><i class="fa-solid fa-play"></i></span>
                </a>
              <?php endif; ?>
              <div class="actor-credit-copy">
                <?php if ($isFuture): ?>
                  <span class="actor-credit-title actor-credit-title-static"><?= e($title) ?></span>
                <?php else: ?>
                  <a href="<?= e($href) ?>" class="actor-credit-title js-media-link" data-fetch-content="1" data-media="<?= $creditMedia ?>"><?= e($title) ?></a>
                <?php endif; ?>
                <?php if ($prettyDate !== ''): ?><span class="actor-credit-date"><i class="fa-solid fa-calendar-days"></i> <?= e($prettyDate) ?></span><?php endif; ?>
                <?php if ($character !== ''): ?><span class="actor-credit-character">as <?= e($character) ?></span><?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <div class="actor-credit-footer mt-4" data-actor-pagination="<?= e($tabType) ?>"></div>
      <?php else: ?>
        <div class="actor-empty-state text-center py-5">
          <i class="fa-solid <?= $tabType === 'movie' ? 'fa-film' : 'fa-tv' ?> mb-3"></i>
          <h3>No <?= e($tabLabel) ?> yet</h3>
          <p class="text-white-50 mb-0">More credits will appear here as they are imported.</p>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
