<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php $moviePlayerUrl = multiembed_player_url($item, 'movie'); ?>
<?php $movieAgeRating = display_age_rating($item['age_rating'] ?? '', 'movie'); ?>
<?php $visibleCast = array_values(array_filter($item['cast'] ?? [], static fn($actor) => has_media_poster(['poster_path' => $actor['profile_path'] ?? '']))); ?>
<?php
$crewSource = $item['crew'] ?? ($item['credits']['crew'] ?? []);
$crewWantedJobs = ['Director', 'Writer', 'Screenplay', 'Story'];
$crewByPerson = [];
foreach ($crewSource as $person) {
    $jobs = array_values(array_filter(array_map('strval', $person['jobs'] ?? [($person['job'] ?? '')]), static fn(string $job): bool => in_array($job, $crewWantedJobs, true)));
    if (!$jobs || !has_media_poster(['poster_path' => $person['profile_path'] ?? ''])) continue;
    $id = (int)($person['id'] ?? $person['tmdb_id'] ?? 0);
    $key = $id > 0 ? (string)$id : strtolower((string)($person['name'] ?? ''));
    if ($key === '') continue;
    $person['jobs'] = $jobs;
    $person['media_type'] = 'person';
    $person['tmdb_id'] = $id ?: ($person['tmdb_id'] ?? null);
    $person['slug'] = $person['slug'] ?? slugify((string)($person['name'] ?? 'crew'));
    if (!isset($crewByPerson[$key])) {
        $crewByPerson[$key] = $person;
    } else {
        $crewByPerson[$key]['jobs'] = array_values(array_unique(array_merge($crewByPerson[$key]['jobs'] ?? [], $jobs)));
    }
}
$visibleCrew = array_values($crewByPerson);
$castCrewCount = count($visibleCast) + count($visibleCrew);
?>
<?php $relatedCount = count(array_values(array_filter($related ?? [], static fn(array $movie): bool => has_media_poster($movie)))); ?>
<?php
$collectionMovies = array_values(array_filter($collectionMovies ?? [], static function (array $movie): bool {
    $title = trim((string)($movie['title'] ?? $movie['name'] ?? ''));
    return $title !== '' && has_media_poster($movie) && is_released_media($movie);
}));
$collectionName = trim((string)($collectionMovies[0]['collection_name'] ?? 'Collection'));
$collectionBackdrop = tmdb_img($collectionMovies[0]['collection_backdrop_path'] ?? ($collectionMovies[0]['backdrop_path'] ?? null), 'w1280');
?>
<div class="streamhive-js-page-media d-none" data-media="<?= media_storage_payload($item, 'movie', url('movies/'.$item['slug'])) ?>"></div>
<section class="streamhive-v2-detail-hero streamhive-has-inline-player streamhive-movie-detail-hero">
  <div class="streamhive-v2-detail-backdrop" style="background-image:url('<?= e(tmdb_img($item['backdrop_path'] ?? ($item['poster_path'] ?? null), 'w1280')) ?>')"></div>
  <div class="streamhive-v2-detail-grid">
    <h1 class="streamhive-v2-detail-title"><?= e($item['title']) ?></h1>
    <div class="streamhive-v2-detail-poster-wrap"><img class="streamhive-v2-detail-poster" src="<?= e(tmdb_img($item['poster_path'] ?? null)) ?>" alt="<?= e($item['title']) ?> poster"></div>
    <div class="streamhive-v2-detail-copy">
      <div class="streamhive-v2-chip-row mb-3"><span><i class="fa-solid fa-calendar"></i> <?= e(format_date($item['release_date'] ?? '')) ?></span><?php if (media_runtime($item, 'movie') !== ''): ?><span><i class="fa-regular fa-clock"></i> <?= e(media_runtime($item, 'movie')) ?></span><?php endif; ?><span><i class="fa-solid fa-star"></i> <?= e((string)round((float)($item['vote_average'] ?? 0), 1)) ?></span><?php if ($movieAgeRating !== ''): ?><span><?= e($movieAgeRating) ?></span><?php endif; ?></div>
      <div class="streamhive-v2-genre-row mb-3"><?= genre_links($item['genres'] ?? [], 'movie', 0, 'streamhive-genre-link') ?></div>
      <p class="streamhive-v2-lead"><?= e($item['overview'] ?? '') ?></p>
      <div class="streamhive-v2-hero-actions">
        <button class="btn btn-outline-light btn-lg streamhive-detail-bookmark streamhive-js-bookmark-btn" type="button" data-media="<?= media_storage_payload($item, 'movie', url('movies/'.$item['slug'])) ?>"><i class="fa-regular fa-bookmark me-2"></i>Save to profile</button>
        <?= share_button($item['title'] ?? 'Movie', url('movies/'.$item['slug'])) ?>
      </div>
    </div>
    <?php if ($moviePlayerUrl !== ''): ?>
    <aside id="watch-player" class="streamhive-v2-inline-player" data-media="<?= media_storage_payload($item, 'movie', url('movies/'.$item['slug'])) ?>">
      <div class="streamhive-v2-inline-player-head"><span><i class="fa-solid fa-circle-play"></i> Now playing</span><strong>Watch Movie</strong></div>
      <div class="streamhive-v2-player-frame streamhive-v2-videasy-frame" style="position: relative; padding-bottom: 56.25%; height: 0;">
        <iframe
          src="<?= e($moviePlayerUrl) ?>"
          title="Movie player"
          style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"
          frameborder="0"
          allowfullscreen></iframe>
      </div>
    </aside>
    <?php endif; ?>
  </div>
</section>


<section class="streamhive-movie-detail-tabs-shell streamhive-actor-credits-shell streamhive-glass rounded-4 p-3 p-lg-4 mt-4" x-data="{ activeDetail: 'cast' }">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <span class="streamhive-v2-section-eyebrow"><i class="fa-solid fa-layer-group"></i> Movie details</span>
      <h2 class="mb-0 text-white fw-black">Details</h2>
    </div>
    <div class="streamhive-actor-tabs streamhive-movie-detail-tabs" role="tablist" aria-label="Movie detail tabs">
      <button class="streamhive-actor-tab streamhive-movie-detail-tab active" :class="{ active: activeDetail === 'cast' }" type="button" data-movie-detail-tab="cast" :aria-selected="activeDetail === 'cast' ? 'true' : 'false'" @click="activeDetail = 'cast'"><i class="fa-solid fa-users"></i> Cast & Crew <span><?= e((string)$castCrewCount) ?></span></button>
      <?php if ($collectionMovies): ?>
      <button class="streamhive-actor-tab streamhive-movie-detail-tab" :class="{ active: activeDetail === 'collection' }" type="button" data-movie-detail-tab="collection" :aria-selected="activeDetail === 'collection' ? 'true' : 'false'" @click="activeDetail = 'collection'"><i class="fa-solid fa-layer-group"></i> Collection <span><?= e((string)count($collectionMovies)) ?></span></button>
      <?php endif; ?>
      <button class="streamhive-actor-tab streamhive-movie-detail-tab" :class="{ active: activeDetail === 'recommended' }" type="button" data-movie-detail-tab="recommended" :aria-selected="activeDetail === 'recommended' ? 'true' : 'false'" @click="activeDetail = 'recommended'"><i class="fa-solid fa-layer-group"></i> More like this <span><?= e((string)$relatedCount) ?></span></button>
    </div>
  </div>

  <div class="streamhive-movie-detail-panel streamhive-movie-tab-surface streamhive-movie-cast-panel active" :class="{ active: activeDetail === 'cast' }" x-show="activeDetail === 'cast'" data-movie-detail-panel="cast">
    <div class="streamhive-movie-tab-surface-bg" style="background-image:url('<?= e(tmdb_img($item['backdrop_path'] ?? ($item['poster_path'] ?? null), 'w1280')) ?>')"></div>
    <div class="streamhive-movie-tab-surface-overlay"></div>
    <div class="streamhive-movie-tab-surface-inner">
      <div class="streamhive-v2-section-head compact streamhive-movie-tab-surface-head">
        <div>
          <span class="streamhive-v2-section-eyebrow"><i class="fa-solid fa-users"></i> Talent</span>
          <h2>Cast & Crew</h2>
        </div>
      </div>
      <?php if ($castCrewCount > 0): ?>
        <div class="streamhive-movie-cast-grid streamhive-v2-related-list">
        <?php foreach ($visibleCast as $index => $actor): ?>
          <?php
            $actorName = (string)($actor['name'] ?? 'Unknown actor');
            $actorCharacter = trim((string)($actor['character'] ?? ''));
            $actorUrl = actor_url($actor);
          ?>
          <a class="streamhive-v2-related-item streamhive-v2-related-compact streamhive-movie-cast-card text-decoration-none streamhive-js-media-link" href="<?= e($actorUrl) ?>" data-fetch-content="1" data-media="<?= media_storage_payload($actor, 'person', $actorUrl) ?>">
            <span class="streamhive-v2-related-feature-bg" style="background-image:url('<?= e(tmdb_img($actor['profile_path'] ?? null, 'w500')) ?>')"></span>
            <span class="streamhive-v2-related-rank"><?= e(str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
            <span class="streamhive-v2-related-poster-wrap streamhive-movie-cast-avatar-wrap">
              <img class="streamhive-v2-related-poster streamhive-movie-cast-avatar" src="<?= e(tmdb_img($actor['profile_path'] ?? null, 'w185')) ?>" alt="<?= e($actorName) ?> profile">
              <span class="streamhive-v2-related-play"><i class="fa-solid fa-user"></i></span>
            </span>
            <span class="streamhive-v2-related-copy">
              <span class="streamhive-v2-related-title"><?= e($actorName) ?></span>
              <?php if ($actorCharacter !== ''): ?>
              <span class="streamhive-v2-related-meta"><span>as <?= e($actorCharacter) ?></span></span>
              <?php endif; ?>
            </span>
            <span class="streamhive-v2-related-arrow"><i class="fa-solid fa-chevron-right"></i></span>
          </a>
        <?php endforeach; ?>
        <?php foreach ($visibleCrew as $crewIndex => $person): ?>
          <?php
            $crewName = (string)($person['name'] ?? 'Unknown crew');
            $crewJobs = implode(' / ', array_values(array_unique(array_map('strval', $person['jobs'] ?? []))));
            $crewUrl = actor_url($person);
            $rank = count($visibleCast) + $crewIndex + 1;
          ?>
          <a class="streamhive-v2-related-item streamhive-v2-related-compact streamhive-movie-cast-card streamhive-movie-crew-card text-decoration-none streamhive-js-media-link" href="<?= e($crewUrl) ?>" data-fetch-content="1" data-media="<?= media_storage_payload($person, 'person', $crewUrl) ?>">
            <span class="streamhive-v2-related-feature-bg" style="background-image:url('<?= e(tmdb_img($person['profile_path'] ?? null, 'w500')) ?>')"></span>
            <span class="streamhive-v2-related-rank"><?= e(str_pad((string)$rank, 2, '0', STR_PAD_LEFT)) ?></span>
            <span class="streamhive-v2-related-poster-wrap streamhive-movie-cast-avatar-wrap">
              <img class="streamhive-v2-related-poster streamhive-movie-cast-avatar" src="<?= e(tmdb_img($person['profile_path'] ?? null, 'w185')) ?>" alt="<?= e($crewName) ?> profile">
              <span class="streamhive-v2-related-play"><i class="fa-solid fa-pen-nib"></i></span>
            </span>
            <span class="streamhive-v2-related-copy">
              <span class="streamhive-v2-related-title"><?= e($crewName) ?></span>
              <?php if ($crewJobs !== ''): ?><span class="streamhive-v2-related-meta"><span><?= e($crewJobs) ?></span></span><?php endif; ?>
            </span>
            <span class="streamhive-v2-related-arrow"><i class="fa-solid fa-chevron-right"></i></span>
          </a>
        <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="streamhive-actor-empty-state text-center py-5">
          <i class="fa-solid fa-users mb-3"></i>
          <h3>No cast or crew images yet</h3>
          <p class="text-white-50 mb-0">TMDB does not have usable profile images for this cast or crew yet.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($collectionMovies): ?>
  <div class="streamhive-movie-detail-panel streamhive-movie-tab-surface streamhive-movie-collection-panel" :class="{ active: activeDetail === 'collection' }" x-show="activeDetail === 'collection'" data-movie-detail-panel="collection">
    <div class="streamhive-movie-tab-surface-bg" style="background-image:url('<?= e($collectionBackdrop) ?>')"></div>
    <div class="streamhive-movie-tab-surface-overlay"></div>
    <div class="streamhive-movie-tab-surface-inner">
      <div class="streamhive-v2-section-head compact streamhive-movie-tab-surface-head">
        <div>
          <span class="streamhive-v2-section-eyebrow"><i class="fa-solid fa-layer-group"></i> <?= e($collectionName !== '' ? $collectionName : 'Collection') ?></span>
          <h2>Movies In This Collection</h2>
        </div>
      </div>
      <div class="streamhive-movie-collection-grid streamhive-v2-related-list">
        <?php foreach ($collectionMovies as $index => $movie): ?>
          <?php
            $movieTitle = (string)($movie['title'] ?? $movie['name'] ?? 'Untitled movie');
            $movieSlug = media_slug($movie + ['title' => $movieTitle], 'movie');
            $movieUrl = url('movies/' . $movieSlug);
            $movieYear = format_year((string)($movie['release_date'] ?? ''));
            $movieRating = round((float)($movie['vote_average'] ?? 0), 1);
            $movieMediaPayload = media_storage_payload($movie, 'movie', $movieUrl, $movieTitle);
            $movieGenres = array_slice(array_values(array_filter(array_map('strval', $movie['genres'] ?? []), static fn(string $genre): bool => trim($genre) !== '')), 0, 3);
            $movieAgeRating = display_age_rating($movie['age_rating'] ?? '', 'movie');
          ?>
          <a class="streamhive-v2-related-item streamhive-v2-related-compact streamhive-movie-collection-card text-decoration-none streamhive-js-media-link" href="<?= e($movieUrl) ?>" aria-label="Open <?= e($movieTitle) ?>" data-fetch-content="0" data-media='<?= $movieMediaPayload ?>'>
            <span class="streamhive-v2-related-feature-bg" style="background-image:url('<?= e(tmdb_img($movie['backdrop_path'] ?? ($movie['poster_path'] ?? null), 'w780')) ?>')"></span>
            <span class="streamhive-v2-related-rank"><?= e(str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
            <span class="streamhive-v2-related-poster-wrap">
              <img class="streamhive-v2-related-poster" src="<?= e(tmdb_img($movie['poster_path'] ?? null, 'w185')) ?>" alt="<?= e($movieTitle) ?> poster">
              <span class="streamhive-v2-related-play"><i class="fa-solid fa-play"></i></span>
            </span>
            <span class="streamhive-v2-related-copy">
              <span class="streamhive-v2-related-title"><?= e($movieTitle) ?></span>
              <span class="streamhive-v2-related-meta">
                <?php if ($movieYear !== ''): ?><span><?= e($movieYear) ?></span><?php endif; ?>
                <?php if ($movieAgeRating !== ''): ?><span><?= e($movieAgeRating) ?></span><?php endif; ?>
              </span>
              <?php if ($movieGenres): ?>
              <span class="streamhive-v2-related-genres">
                <?php foreach ($movieGenres as $movieGenre): ?><span><?= e($movieGenre) ?></span><?php endforeach; ?>
              </span>
              <?php endif; ?>
              <?php if ($movieRating > 0): ?><span class="streamhive-v2-related-score"><i class="fa-solid fa-star"></i> <?= e((string)$movieRating) ?></span><?php endif; ?>
            </span>
            <span class="streamhive-v2-related-arrow"><i class="fa-solid fa-chevron-right"></i></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="streamhive-movie-detail-panel streamhive-movie-tab-surface streamhive-movie-recommended-panel" :class="{ active: activeDetail === 'recommended' }" x-show="activeDetail === 'recommended'" data-movie-detail-panel="recommended">
    <div class="streamhive-movie-tab-surface-bg" style="background-image:url('<?= e(tmdb_img($item['backdrop_path'] ?? ($item['poster_path'] ?? null), 'w1280')) ?>')"></div>
    <div class="streamhive-movie-tab-surface-overlay"></div>
    <div class="streamhive-movie-tab-surface-inner">
      <div class="streamhive-v2-section-head compact streamhive-movie-tab-surface-head">
        <div>
          <span class="streamhive-v2-section-eyebrow"><i class="fa-solid fa-layer-group"></i> Recommended</span>
          <h2>More Like This</h2>
        </div>
      </div>
      <div class="streamhive-detail-recommended-section">
        <?php $type = 'movie'; require app_path('app/Views/partials/related-sidebar.php'); ?>
      </div>
    </div>
  </div>
</section>
