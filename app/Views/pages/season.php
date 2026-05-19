<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php
$episodes = $season['episodes'] ?? [];
$seasonPoster = $season['poster_path'] ?? null;
$seasonName = $season['name'] ?? ('Season ' . $seasonNumber);
?>
<div class="glass rounded-4 p-4 text-white season-hero">
  <div class="row g-4 align-items-stretch">
    <div class="col-md-3 col-lg-2">
      <img class="season-hero-poster" src="<?= e(tmdb_img($seasonPoster ?: ($show['poster_path'] ?? null))) ?>" alt="<?= e($seasonName) ?> poster">
    </div>
    <div class="col-md-9 col-lg-10 d-flex flex-column justify-content-center">
      <a class="text-warning text-decoration-none fw-semibold mb-2" href="<?= e(url('tv/'.$show['slug'])) ?>">← <?= e($show['title']) ?></a>
      <h1 class="mb-2"><?= e($seasonName) ?></h1>
      <p class="text-white-50 mb-3"><?= e((string)count($episodes)) ?> episodes<?= !empty($season['air_date']) ? ' · ' . e(format_date($season['air_date'])) : '' ?></p>
      <?php if (!empty($season['overview'])): ?><p class="mb-0"><?= e($season['overview']) ?></p><?php endif; ?>
      <div class="v2-hero-actions season-share-actions mt-3"><?= share_button(($show['title'] ?? 'TV Show') . ' - ' . $seasonName, url('tv/'.$show['slug'].'/s'.str_pad((string)$seasonNumber,2,'0',STR_PAD_LEFT))) ?></div>
    </div>
  </div>
</div>

<div class="row g-4 mt-4 align-items-start">
  <section class="col-lg-8">
    <h2 class="h4 text-white mb-3">Episodes</h2>
    <div class="row g-3 season-episode-grid">
      <?php foreach ($episodes as $ep): ?>
        <?php $episodeNumber = (int)($ep['episode_number'] ?? 0); if ($episodeNumber < 1) continue; $epUrl = url('tv/'.$show['slug'].'/s'.str_pad((string)$seasonNumber,2,'0',STR_PAD_LEFT).'/e'.str_pad((string)$episodeNumber,2,'0',STR_PAD_LEFT)); $epTitle = ($show['title'] ?? 'Show') . ' - S' . str_pad((string)$seasonNumber,2,'0',STR_PAD_LEFT) . 'E' . str_pad((string)$episodeNumber,2,'0',STR_PAD_LEFT) . ' - ' . ($ep['name'] ?? ('Episode '.$episodeNumber)); ?>
        <div class="col-md-6">
          <a class="season-episode-card text-decoration-none js-media-link" href="<?= e($epUrl) ?>" data-fetch-content="<?= (($show['import_status'] ?? '') === 'full') ? '0' : '1' ?>" data-media="<?= media_storage_payload($show, 'episode', $epUrl, $epTitle, 'Episode · Season '.(string)$seasonNumber.' · Episode '.(string)$episodeNumber, tmdb_img($ep['still_path'] ?? ($show['poster_path'] ?? null), 'w500')) ?>">
            <div class="episode-still-wrap">
              <img src="<?= e(tmdb_img($ep['still_path'] ?? null, 'w500')) ?>" alt="<?= e($ep['name'] ?? ('Episode '.$episodeNumber)) ?> still">
              <span class="episode-play"><i class="fa-solid fa-play"></i></span>
            </div>
            <div class="episode-info">
              <div class="episode-number">Episode <?= e((string)$episodeNumber) ?></div>
              <h3><?= e($ep['name'] ?? ('Episode '.$episodeNumber)) ?></h3>
              <p><?= e($ep['overview'] ?? '') ?></p>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <div class="col-lg-4">
    <?php $type = 'tv'; require app_path('app/Views/partials/related-sidebar.php'); ?>
  </div>
</div>
