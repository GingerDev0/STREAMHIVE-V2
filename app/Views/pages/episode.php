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
?>
<div class="js-page-media episode-page-media d-none" data-media="<?= media_storage_payload($show, 'episode', $episodeMediaUrl, $episodeMediaTitle, $episodeMediaMeta, tmdb_img($episode['still_path'] ?? ($show['poster_path'] ?? null), 'w500')) ?>"></div>

<section class="v2-detail-hero episode-detail-hero has-inline-player">
  <div class="v2-detail-backdrop" style="background-image:url('<?= e(tmdb_img($backdrop, 'w1280')) ?>')"></div>
  <div class="v2-detail-grid">
    <div class="v2-detail-poster-wrap">
      <img class="v2-detail-poster" src="<?= e(tmdb_img($poster)) ?>" alt="<?= e($show['title'] ?? 'Show') ?> poster">
    </div>
    <div class="v2-detail-copy">
      <a class="v2-back-link" href="<?= e(url('tv/'.$show['slug'])) ?>"><i class="fa-solid fa-arrow-left-long"></i> <?= e($show['title']) ?></a>
      <span class="v2-kicker"><i class="fa-solid fa-tv"></i> <?= e($seasonLabel) ?> · <?= e($episodeLabel) ?></span>
      <h1><?= e($episodeTitle) ?></h1>
      <div class="v2-chip-row mb-3">
        <span><i class="fa-solid fa-calendar"></i> <?= e(format_date($episode['air_date'] ?? '')) ?></span>
        <?php if (media_runtime($episode, 'episode') !== ''): ?><span><i class="fa-regular fa-clock"></i> <?= e(media_runtime($episode, 'episode')) ?></span><?php endif; ?>
        <span><i class="fa-solid fa-layer-group"></i> <?= e($seasonLabel) ?></span>
        <span><i class="fa-solid fa-circle-play"></i> <?= e($episodeLabel) ?></span>
      </div>
      <p class="v2-lead"><?= e($episode['overview'] ?? '') ?></p>
      <div class="v2-hero-actions">
        <a class="btn btn-outline-light btn-lg" href="<?= e(url('tv/'.$show['slug'].'/s'.str_pad((string)$season,2,'0',STR_PAD_LEFT))) ?>"><i class="fa-solid fa-list me-2"></i>View season</a>
        <button class="btn btn-outline-light btn-lg detail-bookmark js-bookmark-btn" type="button" data-media="<?= media_storage_payload($show, 'episode', $episodeMediaUrl, $episodeMediaTitle, $episodeMediaMeta, tmdb_img($episode['still_path'] ?? ($show['poster_path'] ?? null), 'w500')) ?>"><i class="fa-regular fa-bookmark me-2"></i>Save episode</button>
        <?= share_button($episodeMediaTitle, $episodeMediaUrl) ?>
      </div>
    </div>
    <?php if ($episodePlayerUrl !== '' && $episodeNumber > 0): ?>
    <aside id="watch-player" class="v2-inline-player episode-inline-player" data-media="<?= media_storage_payload($show, 'episode', $episodeMediaUrl, $episodeMediaTitle, $episodeMediaMeta, tmdb_img($episode['still_path'] ?? ($show['poster_path'] ?? null), 'w500')) ?>">
      <div class="v2-inline-player-head"><span><i class="fa-solid fa-circle-play"></i> Now playing</span><strong>Watch Episode</strong></div>
      <div class="v2-player-frame v2-videasy-frame" style="position: relative; padding-bottom: 56.25%; height: 0;">
        <iframe
          src="<?= e($episodePlayerUrl) ?>"
          title="Episode player"
          style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"
          frameborder="0"
          allowfullscreen></iframe>
      </div>
    </aside>

    <?php if (!empty($nextEpisode) && !empty($nextEpisode['episode'])): ?>
    <?php
      $nextSeasonNumber = (int)($nextEpisode['season_number'] ?? 0);
      $nextEp = $nextEpisode['episode'];
      $nextEpisodeNumber = (int)($nextEp['episode_number'] ?? 0);
    ?>
    <div class="v2-inline-next-episode v2-inline-next-episode--compact">
      <div class="v2-inline-next-head">
        <div>
          <span><i class="fa-solid fa-forward-step"></i> Up next</span>
          <h2>Next Episode</h2>
        </div>
        <a href="<?= e(url('tv/'.$show['slug'].'/s'.str_pad((string)$nextSeasonNumber,2,'0',STR_PAD_LEFT))) ?>">View season <i class="fa-solid fa-arrow-right"></i></a>
      </div>
      <a class="season-episode-card inline-next-card text-decoration-none" href="<?= e(url('tv/'.$show['slug'].'/s'.str_pad((string)$nextSeasonNumber,2,'0',STR_PAD_LEFT).'/e'.str_pad((string)$nextEpisodeNumber,2,'0',STR_PAD_LEFT))) ?>">
        <div class="episode-still-wrap">
          <img src="<?= e(tmdb_img($nextEp['still_path'] ?? null, 'w500')) ?>" alt="<?= e($nextEp['name'] ?? ('Episode '.$nextEpisodeNumber)) ?> still">
          <span class="episode-play"><i class="fa-solid fa-play"></i></span>
        </div>
        <div class="episode-info">
          <div class="episode-number">Season <?= e((string)$nextSeasonNumber) ?> · Episode <?= e((string)$nextEpisodeNumber) ?></div>
          <h3><?= e($nextEp['name'] ?? ('Episode '.$nextEpisodeNumber)) ?></h3>
          <p><?= e($nextEp['overview'] ?? '') ?></p>
        </div>
      </a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<div class="row g-4 mt-4 align-items-start">
  <section class="col-lg-8">
    <div class="v2-section-head compact"><div><span class="v2-section-eyebrow"><i class="fa-solid fa-users"></i> Talent</span><h2>Cast</h2></div></div>
    <div class="row g-3">
    <?php foreach (($show['cast'] ?? []) as $actor): ?>
      <div class="col-6 col-md-4 col-xl-3"><a class="card media-card v2-person-card text-decoration-none h-100 js-media-link" href="<?= e(actor_url($actor)) ?>" data-fetch-content="1" data-media="<?= media_storage_payload($actor, 'person', actor_url($actor)) ?>"><img class="card-img-top" src="<?= e(tmdb_img($actor['profile_path'] ?? null)) ?>" alt="<?= e($actor['name']) ?>"><div class="card-body"><div class="text-white fw-semibold"><?= e($actor['name']) ?></div><small class="text-white-50"><?= e($actor['character'] ?? '') ?></small></div></a></div>
    <?php endforeach; ?>
    </div>
  </section>
  <div class="col-lg-4 v2-sticky-side">
    <?php $type = 'tv'; require app_path('app/Views/partials/related-sidebar.php'); ?>
  </div>
</div>
