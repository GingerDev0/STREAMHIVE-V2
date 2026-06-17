<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php
$episodeNumber = (int)($episode['episode_number'] ?? 0);
$episodeTitle = $episode['name'] ?? 'Episode';
$seasonLabel = 'Season ' . (string)$season;
$episodeLabel = 'Episode ' . (string)$episodeNumber;
$backdrop = $episode['still_path'] ?? ($show['backdrop_path'] ?? ($show['poster_path'] ?? null));
$poster = $show['poster_path'] ?? ($episode['still_path'] ?? null);
$episodeMediaUrl = url('tv/'.$show['slug'].'/s'.str_pad((string)$season,2,'0',STR_PAD_LEFT).'/e'.str_pad((string)$episodeNumber,2,'0',STR_PAD_LEFT));
$episodeCode = 'S' . str_pad((string)$season,2,'0',STR_PAD_LEFT) . 'E' . str_pad((string)$episodeNumber,2,'0',STR_PAD_LEFT);
$episodeMediaTitle = ($show['title'] ?? 'Show') . ' - ' . $episodeTitle . ' (' . $episodeCode . ')';
$episodeMediaMeta = 'Episode · ' . $seasonLabel . ' · ' . $episodeLabel;
$episodePlayerUrl = multiembed_player_url($show, 'episode', (int)$season, $episodeNumber);
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
$hasNextEpisode = !empty($nextEpisode) && !empty($nextEpisode['episode']);
$relatedCount = count(array_values(array_filter($related ?? [], static fn(array $show): bool => has_media_poster($show))));
$defaultTab = 'cast';
?>
<div class="streamhive-js-page-media streamhive-episode-page-media d-none" data-media="<?= media_storage_payload($show, 'episode', $episodeMediaUrl, $episodeMediaTitle, $episodeMediaMeta, tmdb_img($episode['still_path'] ?? ($show['poster_path'] ?? null), 'w500')) ?>"></div>

<section class="streamhive-v2-detail-hero streamhive-episode-detail-hero streamhive-has-inline-player">
  <div class="streamhive-v2-detail-backdrop" style="background-image:url('<?= e(tmdb_img($backdrop, 'w1280')) ?>')"></div>
  <div class="streamhive-v2-detail-grid">
    <h1 class="streamhive-v2-detail-title"><?= e($episodeTitle) ?></h1>
    <div class="streamhive-v2-detail-poster-wrap">
      <img class="streamhive-v2-detail-poster" src="<?= e(tmdb_img($poster)) ?>" alt="<?= e($show['title'] ?? 'Show') ?> poster">
    </div>
    <div class="streamhive-v2-detail-copy">
      <a class="streamhive-v2-back-link" href="<?= e(url('tv/'.$show['slug'])) ?>"><i class="fa-solid fa-arrow-left-long"></i> <?= e($show['title']) ?></a>
      <div class="streamhive-v2-chip-row mb-3">
        <span><i class="fa-solid fa-calendar"></i> <?= e(format_date($episode['air_date'] ?? '')) ?></span>
        <?php if (media_runtime($episode, 'episode') !== ''): ?><span><i class="fa-regular fa-clock"></i> <?= e(media_runtime($episode, 'episode')) ?></span><?php endif; ?>
        <span><i class="fa-solid fa-layer-group"></i> <?= e($seasonLabel) ?></span>
        <span><i class="fa-solid fa-circle-play"></i> <?= e($episodeLabel) ?></span>
      </div>
      <p class="streamhive-v2-lead"><?= e($episode['overview'] ?? '') ?></p>
      <div class="streamhive-v2-hero-actions">
        <a class="btn btn-outline-light btn-lg" href="<?= e(url('tv/'.$show['slug'].'/s'.str_pad((string)$season,2,'0',STR_PAD_LEFT))) ?>"><i class="fa-solid fa-list me-2"></i>View season</a>
        <button class="btn btn-outline-light btn-lg streamhive-detail-bookmark streamhive-js-bookmark-btn" type="button" data-media="<?= media_storage_payload($show, 'episode', $episodeMediaUrl, $episodeMediaTitle, $episodeMediaMeta, tmdb_img($episode['still_path'] ?? ($show['poster_path'] ?? null), 'w500')) ?>"><i class="fa-regular fa-bookmark me-2"></i>Save episode</button>
        <?= share_button($episodeMediaTitle, $episodeMediaUrl) ?>
      </div>
    </div>
    <?php if ($episodePlayerUrl !== '' && $episodeNumber > 0): ?>
    <aside id="watch-player" class="streamhive-v2-inline-player streamhive-episode-inline-player" data-media="<?= media_storage_payload($show, 'episode', $episodeMediaUrl, $episodeMediaTitle, $episodeMediaMeta, tmdb_img($episode['still_path'] ?? ($show['poster_path'] ?? null), 'w500')) ?>">
      <div class="streamhive-v2-inline-player-head"><span><i class="fa-solid fa-circle-play"></i> Now playing</span><strong>Watch Episode</strong></div>
      <div class="streamhive-v2-player-frame streamhive-v2-videasy-frame" style="position: relative; padding-bottom: 56.25%; height: 0;">
        <iframe
          src="<?= e($episodePlayerUrl) ?>"
          title="Episode player"
          style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"
          frameborder="0"
          allowfullscreen></iframe>
      </div>
    </aside>

    <?php if ($hasNextEpisode): ?>
    <?php
      $nextSeasonNumber = (int)($nextEpisode['season_number'] ?? 0);
      $nextEp = $nextEpisode['episode'];
      $nextEpisodeNumber = (int)($nextEp['episode_number'] ?? 0);
    ?>
    <div class="streamhive-v2-inline-next-episode streamhive-v2-inline-next-episode--compact">
      <div class="streamhive-v2-inline-next-head">
        <div>
          <span><i class="fa-solid fa-forward-step"></i> Up next</span>
          <h2>Next Episode</h2>
        </div>
        <a href="<?= e(url('tv/'.$show['slug'].'/s'.str_pad((string)$nextSeasonNumber,2,'0',STR_PAD_LEFT))) ?>">View season <i class="fa-solid fa-arrow-right"></i></a>
      </div>
      <a class="streamhive-season-episode-card streamhive-inline-next-card text-decoration-none" href="<?= e(url('tv/'.$show['slug'].'/s'.str_pad((string)$nextSeasonNumber,2,'0',STR_PAD_LEFT).'/e'.str_pad((string)$nextEpisodeNumber,2,'0',STR_PAD_LEFT))) ?>">
        <div class="streamhive-episode-still-wrap">
          <img src="<?= e(tmdb_img($nextEp['still_path'] ?? null, 'w500')) ?>" alt="<?= e($nextEp['name'] ?? ('Episode '.$nextEpisodeNumber)) ?> still">
          <span class="streamhive-episode-play"><i class="fa-solid fa-play"></i></span>
        </div>
        <div class="streamhive-episode-info">
          <div class="streamhive-episode-number">Season <?= e((string)$nextSeasonNumber) ?> · Episode <?= e((string)$nextEpisodeNumber) ?></div>
          <h3><?= e($nextEp['name'] ?? ('Episode '.$nextEpisodeNumber)) ?></h3>
          <p><?= e($nextEp['overview'] ?? '') ?></p>
        </div>
      </a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</section>



<section class="streamhive-movie-detail-tabs-shell streamhive-actor-credits-shell streamhive-glass rounded-4 p-3 p-lg-4 mt-4">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <span class="streamhive-v2-section-eyebrow"><i class="fa-solid fa-layer-group"></i> Episode details</span>
      <h2 class="mb-0 text-white fw-black">Details</h2>
    </div>
    <div class="streamhive-actor-tabs streamhive-movie-detail-tabs" role="tablist" aria-label="Episode detail tabs">
      <button class="streamhive-actor-tab streamhive-movie-detail-tab <?= $defaultTab === 'cast' ? 'active' : '' ?>" type="button" data-movie-detail-tab="cast" aria-selected="<?= $defaultTab === 'cast' ? 'true' : 'false' ?>"><i class="fa-solid fa-users"></i> Cast & Crew <span><?= e((string)$castCrewCount) ?></span></button>
      <button class="streamhive-actor-tab streamhive-movie-detail-tab" type="button" data-movie-detail-tab="recommended" aria-selected="false"><i class="fa-solid fa-layer-group"></i> More like this <span><?= e((string)$relatedCount) ?></span></button>
    </div>
  </div>

  <div class="streamhive-movie-detail-panel streamhive-movie-tab-surface streamhive-movie-cast-panel <?= $defaultTab === 'cast' ? 'active' : '' ?>" data-movie-detail-panel="cast">
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
