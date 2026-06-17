<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php
$episodes = array_values($season['episodes'] ?? []);
$seasonPoster = $season['poster_path'] ?? null;
$seasonName = $season['name'] ?? ('Season ' . $seasonNumber);
$seasonUrl = url('tv/'.$show['slug'].'/s'.str_pad((string)$seasonNumber,2,'0',STR_PAD_LEFT));
$visibleCast = array_values(array_filter($show['cast'] ?? [], static fn($actor) => has_media_poster(['poster_path' => $actor['profile_path'] ?? ''])));
$crewSource = $show['crew'] ?? ($show['credits']['crew'] ?? []);
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
<div class="streamhive-glass rounded-4 p-4 text-white streamhive-season-hero">
  <div class="row g-4 align-items-stretch">
    <div class="col-md-3 col-lg-2">
      <img class="streamhive-season-hero-poster" src="<?= e(tmdb_img($seasonPoster ?: ($show['poster_path'] ?? null))) ?>" alt="<?= e($seasonName) ?> poster">
    </div>
    <div class="col-md-9 col-lg-10 d-flex flex-column justify-content-center">
      <a class="text-warning text-decoration-none fw-semibold mb-2" href="<?= e(url('tv/'.$show['slug'])) ?>">← <?= e($show['title']) ?></a>
      <h1 class="mb-2"><?= e($seasonName) ?></h1>
      <p class="text-white-50 mb-3"><?= e((string)count($episodes)) ?> episodes<?= !empty($season['air_date']) ? ' · ' . e(format_date($season['air_date'])) : '' ?></p>
      <?php if (!empty($season['overview'])): ?><p class="mb-0"><?= e($season['overview']) ?></p><?php endif; ?>
      <div class="streamhive-v2-hero-actions streamhive-season-share-actions mt-3"><?= share_button(($show['title'] ?? 'TV Show') . ' - ' . $seasonName, $seasonUrl) ?></div>
    </div>
  </div>
</div>

<section class="streamhive-movie-detail-tabs-shell streamhive-actor-credits-shell streamhive-glass rounded-4 p-3 p-lg-4 mt-4">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <span class="streamhive-v2-section-eyebrow"><i class="fa-solid fa-layer-group"></i> Season details</span>
      <h2 class="mb-0 text-white fw-black">Details</h2>
    </div>
    <div class="streamhive-actor-tabs streamhive-movie-detail-tabs" role="tablist" aria-label="Season detail tabs">
      <button class="streamhive-actor-tab streamhive-movie-detail-tab active" type="button" data-movie-detail-tab="episodes" aria-selected="true"><i class="fa-solid fa-list"></i> Episodes <span><?= e((string)count($episodes)) ?></span></button>
      <button class="streamhive-actor-tab streamhive-movie-detail-tab" type="button" data-movie-detail-tab="cast" aria-selected="false"><i class="fa-solid fa-users"></i> Cast & Crew <span><?= e((string)$castCrewCount) ?></span></button>
      <button class="streamhive-actor-tab streamhive-movie-detail-tab" type="button" data-movie-detail-tab="recommended" aria-selected="false"><i class="fa-solid fa-layer-group"></i> More like this <span><?= e((string)$relatedCount) ?></span></button>
    </div>
  </div>

  <div class="streamhive-movie-detail-panel streamhive-movie-tab-surface streamhive-season-episodes-panel active" data-movie-detail-panel="episodes">
    <div class="streamhive-movie-tab-surface-bg" style="background-image:url('<?= e(tmdb_img($season['poster_path'] ?? ($show['backdrop_path'] ?? ($show['poster_path'] ?? null)), 'w1280')) ?>')"></div>
    <div class="streamhive-movie-tab-surface-overlay"></div>
    <div class="streamhive-movie-tab-surface-inner">
      <div class="streamhive-v2-section-head compact streamhive-movie-tab-surface-head"><div><span class="streamhive-v2-section-eyebrow"><i class="fa-solid fa-list"></i> Episode guide</span><h2>Episodes</h2></div></div>
      <div class="streamhive-movie-episode-grid streamhive-v2-related-list">
        <?php foreach ($episodes as $index => $ep): ?>
          <?php
            $episodeNumber = (int)($ep['episode_number'] ?? 0);
            if ($episodeNumber < 1) continue;
            $epUrl = url('tv/'.$show['slug'].'/s'.str_pad((string)$seasonNumber,2,'0',STR_PAD_LEFT).'/e'.str_pad((string)$episodeNumber,2,'0',STR_PAD_LEFT));
            $epName = (string)($ep['name'] ?? ('Episode '.$episodeNumber));
            $epTitle = ($show['title'] ?? 'Show') . ' - S' . str_pad((string)$seasonNumber,2,'0',STR_PAD_LEFT) . 'E' . str_pad((string)$episodeNumber,2,'0',STR_PAD_LEFT) . ' - ' . $epName;
            $epStill = $ep['still_path'] ?? ($show['poster_path'] ?? null);
            $epDate = format_date($ep['air_date'] ?? '');
          ?>
          <a class="streamhive-v2-related-item streamhive-v2-related-compact streamhive-movie-episode-card text-decoration-none streamhive-js-media-link" href="<?= e($epUrl) ?>" data-fetch-content="<?= (($show['import_status'] ?? '') === 'full') ? '0' : '1' ?>" data-media="<?= media_storage_payload($show, 'episode', $epUrl, $epTitle, 'Episode · Season '.(string)$seasonNumber.' · Episode '.(string)$episodeNumber, tmdb_img($epStill, 'w500')) ?>">
            <span class="streamhive-v2-related-feature-bg" style="background-image:url('<?= e(tmdb_img($epStill, 'w780')) ?>')"></span>
            <span class="streamhive-v2-related-rank"><?= e(str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
            <span class="streamhive-v2-related-poster-wrap streamhive-movie-episode-still-wrap">
              <img class="streamhive-v2-related-poster streamhive-movie-episode-still" src="<?= e(tmdb_img($epStill, 'w300')) ?>" alt="<?= e($epName) ?> still">
              <span class="streamhive-v2-related-play"><i class="fa-solid fa-play"></i></span>
            </span>
            <span class="streamhive-v2-related-copy">
              <span class="streamhive-v2-related-title"><?= e($epName) ?></span>
              <span class="streamhive-v2-related-meta"><span>Episode <?= e((string)$episodeNumber) ?></span><?php if ($epDate !== ''): ?><span><?= e($epDate) ?></span><?php endif; ?></span>
              <?php if (!empty($ep['overview'])): ?><span class="streamhive-v2-related-plot"><?= e($ep['overview']) ?></span><?php endif; ?>
            </span>
            <span class="streamhive-v2-related-arrow"><i class="fa-solid fa-chevron-right"></i></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="streamhive-movie-detail-panel streamhive-movie-tab-surface streamhive-movie-cast-panel" data-movie-detail-panel="cast">
    <div class="streamhive-movie-tab-surface-bg" style="background-image:url('<?= e(tmdb_img($show['backdrop_path'] ?? ($show['poster_path'] ?? null), 'w1280')) ?>')"></div>
    <div class="streamhive-movie-tab-surface-overlay"></div>
    <div class="streamhive-movie-tab-surface-inner">
      <div class="streamhive-v2-section-head compact streamhive-movie-tab-surface-head"><div><span class="streamhive-v2-section-eyebrow"><i class="fa-solid fa-users"></i> Talent</span><h2>Cast & Crew</h2></div></div>
      <?php if ($castCrewCount > 0): ?>
      <div class="streamhive-movie-cast-grid streamhive-v2-related-list">
        <?php foreach ($visibleCast as $index => $actor): ?>
          <?php $actorName=(string)($actor['name'] ?? 'Unknown actor'); $actorCharacter=trim((string)($actor['character'] ?? '')); $actorUrl=actor_url($actor); ?>
          <a class="streamhive-v2-related-item streamhive-v2-related-compact streamhive-movie-cast-card text-decoration-none streamhive-js-media-link" href="<?= e($actorUrl) ?>" data-fetch-content="1" data-media="<?= media_storage_payload($actor, 'person', $actorUrl) ?>">
            <span class="streamhive-v2-related-feature-bg" style="background-image:url('<?= e(tmdb_img($actor['profile_path'] ?? null, 'w500')) ?>')"></span>
            <span class="streamhive-v2-related-rank"><?= e(str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
            <span class="streamhive-v2-related-poster-wrap streamhive-movie-cast-avatar-wrap"><img class="streamhive-v2-related-poster streamhive-movie-cast-avatar" src="<?= e(tmdb_img($actor['profile_path'] ?? null, 'w185')) ?>" alt="<?= e($actorName) ?> profile"><span class="streamhive-v2-related-play"><i class="fa-solid fa-user"></i></span></span>
            <span class="streamhive-v2-related-copy"><span class="streamhive-v2-related-title"><?= e($actorName) ?></span><?php if ($actorCharacter !== ''): ?><span class="streamhive-v2-related-meta"><span>as <?= e($actorCharacter) ?></span></span><?php endif; ?></span>
            <span class="streamhive-v2-related-arrow"><i class="fa-solid fa-chevron-right"></i></span>
          </a>
        <?php endforeach; ?>
        <?php foreach ($visibleCrew as $crewIndex => $person): ?>
          <?php $crewName=(string)($person['name'] ?? 'Unknown crew'); $crewJobs=implode(' / ', array_values(array_unique(array_map('strval', $person['jobs'] ?? [])))); $crewUrl=actor_url($person); $rank=count($visibleCast)+$crewIndex+1; ?>
          <a class="streamhive-v2-related-item streamhive-v2-related-compact streamhive-movie-cast-card streamhive-movie-crew-card text-decoration-none streamhive-js-media-link" href="<?= e($crewUrl) ?>" data-fetch-content="1" data-media="<?= media_storage_payload($person, 'person', $crewUrl) ?>">
            <span class="streamhive-v2-related-feature-bg" style="background-image:url('<?= e(tmdb_img($person['profile_path'] ?? null, 'w500')) ?>')"></span>
            <span class="streamhive-v2-related-rank"><?= e(str_pad((string)$rank, 2, '0', STR_PAD_LEFT)) ?></span>
            <span class="streamhive-v2-related-poster-wrap streamhive-movie-cast-avatar-wrap"><img class="streamhive-v2-related-poster streamhive-movie-cast-avatar" src="<?= e(tmdb_img($person['profile_path'] ?? null, 'w185')) ?>" alt="<?= e($crewName) ?> profile"><span class="streamhive-v2-related-play"><i class="fa-solid fa-pen-nib"></i></span></span>
            <span class="streamhive-v2-related-copy"><span class="streamhive-v2-related-title"><?= e($crewName) ?></span><?php if ($crewJobs !== ''): ?><span class="streamhive-v2-related-meta"><span><?= e($crewJobs) ?></span></span><?php endif; ?></span>
            <span class="streamhive-v2-related-arrow"><i class="fa-solid fa-chevron-right"></i></span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="streamhive-actor-empty-state text-center py-5"><i class="fa-solid fa-users mb-3"></i><h3>No cast or crew images yet</h3><p class="text-white-50 mb-0">TMDB does not have usable profile images for this cast or crew yet.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="streamhive-movie-detail-panel streamhive-movie-tab-surface streamhive-movie-recommended-panel" data-movie-detail-panel="recommended">
    <div class="streamhive-movie-tab-surface-bg" style="background-image:url('<?= e(tmdb_img($show['backdrop_path'] ?? ($show['poster_path'] ?? null), 'w1280')) ?>')"></div>
    <div class="streamhive-movie-tab-surface-overlay"></div>
    <div class="streamhive-movie-tab-surface-inner">
      <div class="streamhive-v2-section-head compact streamhive-movie-tab-surface-head"><div><span class="streamhive-v2-section-eyebrow"><i class="fa-solid fa-layer-group"></i> Recommended</span><h2>More Like This</h2></div></div>
      <div class="streamhive-detail-recommended-section">
        <?php $type = 'tv'; require app_path('app/Views/partials/related-sidebar.php'); ?>
      </div>
    </div>
  </div>
</section>
