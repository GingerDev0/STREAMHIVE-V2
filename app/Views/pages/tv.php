<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php $tvAgeRating = display_age_rating($item['age_rating'] ?? '', 'tv'); ?>
<div class="js-page-media d-none" data-media="<?= media_storage_payload($item, 'tv', url('tv/'.$item['slug'])) ?>"></div>
<section class="v2-detail-hero v2-tv-detail-hero">
  <div class="v2-detail-backdrop" style="background-image:url('<?= e(tmdb_img($item['backdrop_path'] ?? ($item['poster_path'] ?? null), 'w1280')) ?>')"></div>
  <div class="v2-detail-grid">
    <div class="v2-detail-poster-wrap"><img class="v2-detail-poster" src="<?= e(tmdb_img($item['poster_path'] ?? null)) ?>" alt="<?= e($item['title']) ?> poster"></div>
    <div class="v2-detail-copy">
      <span class="v2-kicker"><i class="fa-solid fa-tv"></i> TV Show</span>
      <h1><?= e($item['title']) ?></h1>
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

<div class="row g-4 mt-4 align-items-start">
  <section id="seasons" class="col-lg-8">
    <div class="v2-section-head compact"><div><span class="v2-section-eyebrow"><i class="fa-solid fa-layer-group"></i> Episode guide</span><h2>Seasons</h2></div></div>
    <div class="row g-3 mb-4 season-card-grid">
    <?php foreach (($item['seasons'] ?? []) as $season): $sn=(int)($season['season_number']??0); if($sn<1 || is_future_date((string)($season['air_date'] ?? ''))) continue; ?>
      <div class="col-sm-6 col-xl-4">
        <a class="season-card text-decoration-none" href="<?= e(url('tv/'.$item['slug'].'/s'.str_pad((string)$sn,2,'0',STR_PAD_LEFT))) ?>">
          <div class="season-poster-wrap">
            <img src="<?= e(tmdb_img($season['poster_path'] ?? ($item['poster_path'] ?? null))) ?>" alt="<?= e($season['name'] ?? 'Season '.$sn) ?> poster">
            <span class="season-view-badge"><i class="fa-solid fa-list me-1"></i> View episodes</span>
          </div>
          <div class="season-card-body">
            <h3><?= e($season['name'] ?? 'Season '.$sn) ?></h3>
            <p><?= e((string)($season['episode_count'] ?? 0)) ?> episodes<?= !empty($season['air_date']) ? ' · ' . e(format_year((string)$season['air_date'])) : '' ?></p>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
    </div>

    <div class="v2-section-head compact"><div><span class="v2-section-eyebrow"><i class="fa-solid fa-users"></i> Talent</span><h2>Cast</h2></div></div>
    <div class="row g-3">
    <?php foreach (($item['cast'] ?? []) as $actor): ?>
      <div class="col-6 col-md-4 col-xl-3"><a class="card media-card v2-person-card text-decoration-none h-100 js-media-link" href="<?= e(actor_url($actor)) ?>" data-fetch-content="1" data-media="<?= media_storage_payload($actor, 'person', actor_url($actor)) ?>"><img class="card-img-top" src="<?= e(tmdb_img($actor['profile_path'] ?? null)) ?>" alt="<?= e($actor['name']) ?>"><div class="card-body"><div class="text-white fw-semibold"><?= e($actor['name']) ?></div><small class="text-white-50"><?= e($actor['character'] ?? '') ?></small></div></a></div>
    <?php endforeach; ?>
    </div>
  </section>
  <div class="col-lg-4 v2-sticky-side">
    <?php $type = 'tv'; require app_path('app/Views/partials/related-sidebar.php'); ?>
  </div>
</div>
