<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php $moviePlayerUrl = multiembed_player_url($item, 'movie'); ?>
<?php $movieAgeRating = display_age_rating($item['age_rating'] ?? '', 'movie'); ?>
<div class="js-page-media d-none" data-media="<?= media_storage_payload($item, 'movie', url('movies/'.$item['slug'])) ?>"></div>
<section class="v2-detail-hero has-inline-player movie-detail-hero">
  <div class="v2-detail-backdrop" style="background-image:url('<?= e(tmdb_img($item['backdrop_path'] ?? ($item['poster_path'] ?? null), 'w1280')) ?>')"></div>
  <div class="v2-detail-grid">
    <div class="v2-detail-poster-wrap"><img class="v2-detail-poster" src="<?= e(tmdb_img($item['poster_path'] ?? null)) ?>" alt="<?= e($item['title']) ?> poster"></div>
    <div class="v2-detail-copy">
      <span class="v2-kicker"><i class="fa-solid fa-film"></i> Movie</span>
      <h1><?= e($item['title']) ?></h1>
      <div class="v2-chip-row mb-3"><span><i class="fa-solid fa-calendar"></i> <?= e(format_date($item['release_date'] ?? '')) ?></span><?php if (media_runtime($item, 'movie') !== ''): ?><span><i class="fa-regular fa-clock"></i> <?= e(media_runtime($item, 'movie')) ?></span><?php endif; ?><span><i class="fa-solid fa-star"></i> <?= e((string)round((float)($item['vote_average'] ?? 0), 1)) ?></span><?php if ($movieAgeRating !== ''): ?><span><?= e($movieAgeRating) ?></span><?php endif; ?></div>
      <div class="v2-genre-row mb-3"><?= genre_links($item['genres'] ?? [], 'movie', 0, 'genre-link') ?></div>
      <p class="v2-lead"><?= e($item['overview'] ?? '') ?></p>
      <div class="v2-hero-actions">
        <button class="btn btn-outline-light btn-lg detail-bookmark js-bookmark-btn" type="button" data-media="<?= media_storage_payload($item, 'movie', url('movies/'.$item['slug'])) ?>"><i class="fa-regular fa-bookmark me-2"></i>Save to profile</button>
        <?= share_button($item['title'] ?? 'Movie', url('movies/'.$item['slug'])) ?>
      </div>
    </div>
    <?php if ($moviePlayerUrl !== ''): ?>
    <aside id="watch-player" class="v2-inline-player" data-media="<?= media_storage_payload($item, 'movie', url('movies/'.$item['slug'])) ?>">
      <div class="v2-inline-player-head"><span><i class="fa-solid fa-circle-play"></i> Now playing</span><strong>Watch Movie</strong></div>
      <div class="v2-player-frame v2-videasy-frame" style="position: relative; padding-bottom: 56.25%; height: 0;">
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

<div class="row g-4 mt-4 align-items-start">
  <section class="col-lg-8">
    <div class="v2-section-head compact"><div><span class="v2-section-eyebrow"><i class="fa-solid fa-users"></i> Talent</span><h2>Cast</h2></div></div>
    <div class="row g-3">
    <?php foreach (($item['cast'] ?? []) as $actor): ?>
      <div class="col-6 col-md-4 col-xl-3"><a class="card media-card v2-person-card text-decoration-none h-100 js-media-link" href="<?= e(actor_url($actor)) ?>" data-fetch-content="1" data-media="<?= media_storage_payload($actor, 'person', actor_url($actor)) ?>"><img class="card-img-top" src="<?= e(tmdb_img($actor['profile_path'] ?? null)) ?>" alt="<?= e($actor['name']) ?>"><div class="card-body"><div class="text-white fw-semibold"><?= e($actor['name']) ?></div><small class="text-white-50"><?= e($actor['character'] ?? '') ?></small></div></a></div>
    <?php endforeach; ?>
    </div>
  </section>
  <div class="col-lg-4 v2-sticky-side">
    <?php $type = 'movie'; require app_path('app/Views/partials/related-sidebar.php'); ?>
  </div>
</div>
