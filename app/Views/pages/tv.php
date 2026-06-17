<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php $tvAgeRating = display_age_rating($item['age_rating'] ?? '', 'tv'); ?>
<?php $visibleCast = array_values(array_filter($item['cast'] ?? [], static fn($actor) => has_media_poster(['poster_path' => $actor['profile_path'] ?? '']))); ?>
<?php
$releasedSeasons = array_values(array_filter($item['seasons'] ?? [], static function (array $season): bool {
    $sn = (int)($season['season_number'] ?? 0);
    return $sn >= 1 && trim((string)($season['air_date'] ?? '')) !== '' && !is_future_date((string)($season['air_date'] ?? ''));
}));
$crewSource = $item['crew'] ?? ($item['credits']['crew'] ?? []);
$crewWantedJobs = ['Creator', 'Director', 'Writer', 'Screenplay', 'Story'];
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
$relatedCount = count(array_values(array_filter($related ?? [], static fn(array $show): bool => has_media_poster($show))));
?>
<div class="js-page-media d-none" data-media="<?= media_storage_payload($item, 'tv', url('tv/'.$item['slug'])) ?>"></div>
<section class="v2-detail-hero v2-tv-detail-hero">
  <div class="v2-detail-backdrop" style="background-image:url('<?= e(tmdb_img($item['backdrop_path'] ?? ($item['poster_path'] ?? null), 'w1280')) ?>')"></div>
  <div class="v2-detail-grid">
    <h1 class="v2-detail-title"><?= e($item['title']) ?></h1>
    <div class="v2-detail-poster-wrap"><img class="v2-detail-poster" src="<?= e(tmdb_img($item['poster_path'] ?? null)) ?>" alt="<?= e($item['title']) ?> poster"></div>
    <div class="v2-detail-copy">
      <div class="v2-chip-row mb-3"><span><i class="fa-solid fa-calendar"></i> <?= e(format_date($item['release_date'] ?? '')) ?></span><?php if (media_runtime($item, 'tv') !== ''): ?><span><i class="fa-regular fa-clock"></i> <?= e(media_runtime($item, 'tv')) ?></span><?php endif; ?><span><i class="fa-solid fa-star"></i> <?= e((string)round((float)($item['vote_average'] ?? 0), 1)) ?></span><?php if ($tvAgeRating !== ''): ?><span><?= e($tvAgeRating) ?></span><?php endif; ?></div>
      <div class="v2-genre-row mb-3"><?= genre_links($item['genres'] ?? [], 'tv', 0, 'genre-link') ?></div>
      <p class="v2-lead"><?= e($item['overview'] ?? '') ?></p>
      <div class="v2-hero-actions">
        <a class="btn btn-warning btn-lg" href="#seasons"><i class="fa-solid fa-layer-group me-2"></i>View seasons</a>
        <button class="btn btn-outline-light btn-lg detail-bookmark js-bookmark-btn" type="button" data-media="<?= media_storage_payload($item, 'tv', url('tv/'.$item['slug'])) ?>"><i class="fa-regular fa-bookmark me-2"></i>Save to profile</button>
        <?= share_button($item['title'] ?? 'TV Show', url('tv/'.$item['slug'])) ?>
      </div>
      <p class="small text-white-50 mb-0 mt-3"><i class="fa-solid fa-circle-play me-1 text-warning"></i>Open a season and choose an episode to launch the player.</p>
    </div>
  </div>
</section>

<section id="seasons" class="movie-detail-tabs-shell actor-credits-shell glass rounded-4 p-3 p-lg-4 mt-4">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <span class="v2-section-eyebrow"><i class="fa-solid fa-layer-group"></i> TV details</span>
      <h2 class="mb-0 text-white fw-black">Details</h2>
    </div>
    <div class="actor-tabs movie-detail-tabs" role="tablist" aria-label="TV detail tabs">
      <button class="actor-tab movie-detail-tab active" type="button" data-movie-detail-tab="seasons" aria-selected="true"><i class="fa-solid fa-layer-group"></i> Seasons <span><?= e((string)count($releasedSeasons)) ?></span></button>
      <button class="actor-tab movie-detail-tab" type="button" data-movie-detail-tab="cast" aria-selected="false"><i class="fa-solid fa-users"></i> Cast & Crew <span><?= e((string)$castCrewCount) ?></span></button>
      <button class="actor-tab movie-detail-tab" type="button" data-movie-detail-tab="recommended" aria-selected="false"><i class="fa-solid fa-layer-group"></i> More like this <span><?= e((string)$relatedCount) ?></span></button>
    </div>
  </div>

  <div class="movie-detail-panel movie-tab-surface tv-seasons-panel active" data-movie-detail-panel="seasons">
    <div class="movie-tab-surface-bg" style="background-image:url('<?= e(tmdb_img($item['backdrop_path'] ?? ($item['poster_path'] ?? null), 'w1280')) ?>')"></div>
    <div class="movie-tab-surface-overlay"></div>
    <div class="movie-tab-surface-inner">
      <div class="v2-section-head compact movie-tab-surface-head"><div><span class="v2-section-eyebrow"><i class="fa-solid fa-layer-group"></i> Episode guide</span><h2>Seasons</h2></div></div>
      <div class="movie-season-grid v2-related-list">
      <?php foreach ($releasedSeasons as $index => $season): $sn=(int)($season['season_number']??0); ?>
        <?php
          $seasonName = (string)($season['name'] ?? 'Season '.$sn);
          $seasonUrl = url('tv/'.$item['slug'].'/s'.str_pad((string)$sn,2,'0',STR_PAD_LEFT));
          $seasonPoster = $season['poster_path'] ?? ($item['poster_path'] ?? null);
          $seasonYear = format_year((string)($season['air_date'] ?? ''));
          $episodeCount = (int)($season['episode_count'] ?? 0);
        ?>
        <a class="v2-related-item v2-related-compact movie-season-card text-decoration-none" href="<?= e($seasonUrl) ?>">
          <span class="v2-related-feature-bg" style="background-image:url('<?= e(tmdb_img($seasonPoster, 'w780')) ?>')"></span>
          <span class="v2-related-rank"><?= e(str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
          <span class="v2-related-poster-wrap">
            <img class="v2-related-poster" src="<?= e(tmdb_img($seasonPoster, 'w185')) ?>" alt="<?= e($seasonName) ?> poster">
            <span class="v2-related-play"><i class="fa-solid fa-list"></i></span>
          </span>
          <span class="v2-related-copy">
            <span class="v2-related-title"><?= e($seasonName) ?></span>
            <span class="v2-related-meta">
              <span><?= e((string)$episodeCount) ?> episodes</span>
              <?php if ($seasonYear !== ''): ?><span><?= e($seasonYear) ?></span><?php endif; ?>
            </span>
            <span class="v2-related-score"><i class="fa-solid fa-tv"></i> Season <?= e((string)$sn) ?></span>
          </span>
          <span class="v2-related-arrow"><i class="fa-solid fa-chevron-right"></i></span>
        </a>
      <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="movie-detail-panel movie-tab-surface movie-cast-panel" data-movie-detail-panel="cast">
    <div class="movie-tab-surface-bg" style="background-image:url('<?= e(tmdb_img($item['backdrop_path'] ?? ($item['poster_path'] ?? null), 'w1280')) ?>')"></div>
    <div class="movie-tab-surface-overlay"></div>
    <div class="movie-tab-surface-inner">
      <div class="v2-section-head compact movie-tab-surface-head"><div><span class="v2-section-eyebrow"><i class="fa-solid fa-users"></i> Talent</span><h2>Cast & Crew</h2></div></div>
      <?php if ($castCrewCount > 0): ?>
      <div class="movie-cast-grid v2-related-list">
        <?php foreach ($visibleCast as $index => $actor): ?>
          <?php $actorName=(string)($actor['name'] ?? 'Unknown actor'); $actorCharacter=trim((string)($actor['character'] ?? '')); $actorUrl=actor_url($actor); ?>
          <a class="v2-related-item v2-related-compact movie-cast-card text-decoration-none js-media-link" href="<?= e($actorUrl) ?>" data-fetch-content="1" data-media="<?= media_storage_payload($actor, 'person', $actorUrl) ?>">
            <span class="v2-related-feature-bg" style="background-image:url('<?= e(tmdb_img($actor['profile_path'] ?? null, 'w500')) ?>')"></span>
            <span class="v2-related-rank"><?= e(str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
            <span class="v2-related-poster-wrap movie-cast-avatar-wrap"><img class="v2-related-poster movie-cast-avatar" src="<?= e(tmdb_img($actor['profile_path'] ?? null, 'w185')) ?>" alt="<?= e($actorName) ?> profile"><span class="v2-related-play"><i class="fa-solid fa-user"></i></span></span>
            <span class="v2-related-copy"><span class="v2-related-title"><?= e($actorName) ?></span><?php if ($actorCharacter !== ''): ?><span class="v2-related-meta"><span>as <?= e($actorCharacter) ?></span></span><?php endif; ?></span>
            <span class="v2-related-arrow"><i class="fa-solid fa-chevron-right"></i></span>
          </a>
        <?php endforeach; ?>
        <?php foreach ($visibleCrew as $crewIndex => $person): ?>
          <?php $crewName=(string)($person['name'] ?? 'Unknown crew'); $crewJobs=implode(' / ', array_values(array_unique(array_map('strval', $person['jobs'] ?? [])))); $crewUrl=actor_url($person); $rank=count($visibleCast)+$crewIndex+1; ?>
          <a class="v2-related-item v2-related-compact movie-cast-card movie-crew-card text-decoration-none js-media-link" href="<?= e($crewUrl) ?>" data-fetch-content="1" data-media="<?= media_storage_payload($person, 'person', $crewUrl) ?>">
            <span class="v2-related-feature-bg" style="background-image:url('<?= e(tmdb_img($person['profile_path'] ?? null, 'w500')) ?>')"></span>
            <span class="v2-related-rank"><?= e(str_pad((string)$rank, 2, '0', STR_PAD_LEFT)) ?></span>
            <span class="v2-related-poster-wrap movie-cast-avatar-wrap"><img class="v2-related-poster movie-cast-avatar" src="<?= e(tmdb_img($person['profile_path'] ?? null, 'w185')) ?>" alt="<?= e($crewName) ?> profile"><span class="v2-related-play"><i class="fa-solid fa-pen-nib"></i></span></span>
            <span class="v2-related-copy"><span class="v2-related-title"><?= e($crewName) ?></span><?php if ($crewJobs !== ''): ?><span class="v2-related-meta"><span><?= e($crewJobs) ?></span></span><?php endif; ?></span>
            <span class="v2-related-arrow"><i class="fa-solid fa-chevron-right"></i></span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="actor-empty-state text-center py-5"><i class="fa-solid fa-users mb-3"></i><h3>No cast or crew images yet</h3><p class="text-white-50 mb-0">TMDB does not have usable profile images for this cast or crew yet.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="movie-detail-panel movie-tab-surface movie-recommended-panel" data-movie-detail-panel="recommended">
    <div class="movie-tab-surface-bg" style="background-image:url('<?= e(tmdb_img($item['backdrop_path'] ?? ($item['poster_path'] ?? null), 'w1280')) ?>')"></div>
    <div class="movie-tab-surface-overlay"></div>
    <div class="movie-tab-surface-inner">
      <div class="v2-section-head compact movie-tab-surface-head"><div><span class="v2-section-eyebrow"><i class="fa-solid fa-layer-group"></i> Recommended</span><h2>More Like This</h2></div></div>
      <div class="detail-recommended-section">
        <?php $type = 'tv'; require app_path('app/Views/partials/related-sidebar.php'); ?>
      </div>
    </div>
  </div>
</section>
