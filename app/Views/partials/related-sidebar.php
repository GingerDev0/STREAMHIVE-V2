<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php $relatedType = ($type ?? 'movie') === 'tv' ? 'tv' : 'movie'; ?>
<aside class="v2-related-panel h-100">
  <div class="v2-related-head">
    <div>
      <span class="v2-related-eyebrow"><i class="fa-solid fa-layer-group"></i> Recommended</span>
      <h2>More like this</h2>
    </div>
    <?php if (!empty($related)): ?>
      <span class="v2-related-count"><?= e((string)count($related)) ?></span>
    <?php endif; ?>
  </div>

  <?php if (empty($related)): ?>
    <div class="v2-related-empty">
      <i class="fa-solid fa-clapperboard"></i>
      <p>Import more <?= e($relatedType === 'tv' ? 'TV shows' : 'movies') ?> to build recommendations.</p>
    </div>
  <?php else: ?>
    <div class="v2-related-list">
      <?php foreach ($related as $index => $rel): ?>
        <?php
          $relType = $relatedType;
          $relTitle = $rel['title'] ?? ($rel['name'] ?? 'Untitled');
          $relLink = $relType === 'tv'
            ? url('tv/' . ($rel['slug'] ?? slugify($relTitle)))
            : url('movies/' . ($rel['slug'] ?? slugify($relTitle)));
          $relRating = round((float)($rel['vote_average'] ?? 0), 1);
          $relYear = format_year((string)($rel['release_date'] ?? ($rel['first_air_date'] ?? '')));
        ?>
        <a class="v2-related-item text-decoration-none js-media-link" href="<?= e($relLink) ?>" data-fetch-content="1" data-media="<?= media_storage_payload($rel, $relType, $relLink) ?>">
          <span class="v2-related-rank"><?= e(str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
          <span class="v2-related-poster-wrap">
            <img class="v2-related-poster" src="<?= e(tmdb_img($rel['poster_path'] ?? null, 'w185')) ?>" alt="<?= e($relTitle) ?> poster" loading="lazy">
            <span class="v2-related-play"><i class="fa-solid fa-play"></i></span>
          </span>
          <span class="v2-related-copy">
            <span class="v2-related-title"><?= e($relTitle) ?></span>
            <span class="v2-related-meta">
              <?php if ($relYear !== ''): ?><span><?= e($relYear) ?></span><?php endif; ?>
              <?php $relAgeRating = display_age_rating($rel['age_rating'] ?? '', $rel['media_type'] ?? 'movie'); if ($relAgeRating !== ''): ?><span><?= e($relAgeRating) ?></span><?php endif; ?>
            </span>
            <span class="v2-related-score"><i class="fa-solid fa-star"></i> <?= e((string)$relRating) ?></span>
          </span>
          <span class="v2-related-arrow"><i class="fa-solid fa-chevron-right"></i></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</aside>
