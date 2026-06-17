<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php
$relatedType = ($type ?? 'movie') === 'tv' ? 'tv' : 'movie';
$related = array_slice(array_values(array_filter($related ?? [], static fn(array $item): bool => has_media_poster($item))), 0, 12);
?>
<aside class="streamhive-v2-related-panel h-100">
  <div class="streamhive-v2-related-head">
    <div>
      <span class="streamhive-v2-related-eyebrow"><i class="fa-solid fa-layer-group"></i> Recommended</span>
      <h2>More like this</h2>
    </div>
    <?php if (!empty($related)): ?>
      <span class="streamhive-v2-related-count"><?= e((string)count($related)) ?></span>
    <?php endif; ?>
  </div>

  <?php if (empty($related)): ?>
    <div class="streamhive-v2-related-empty">
      <i class="fa-solid fa-clapperboard"></i>
      <p>No TMDB recommendations available for this title yet.</p>
    </div>
  <?php else: ?>
    <div class="streamhive-v2-related-list">
      <?php foreach ($related as $index => $rel): ?>
        <?php
          $relType = $relatedType;
          $relTitle = $rel['title'] ?? ($rel['name'] ?? 'Untitled');
          $relSlug = media_slug($rel + ['title' => $relTitle], $relType);
          $relLink = $relType === 'tv'
            ? url('tv/' . $relSlug)
            : url('movies/' . $relSlug);
          $relRating = round((float)($rel['vote_average'] ?? 0), 1);
          $relYear = format_year((string)($rel['release_date'] ?? ($rel['first_air_date'] ?? '')));
          $relGenres = array_slice(array_values(array_filter(array_map('strval', $rel['genres'] ?? []), static fn(string $genre): bool => trim($genre) !== '')), 0, 3);
        ?>
        <a class="streamhive-v2-related-item streamhive-v2-related-compact text-decoration-none streamhive-js-media-link" href="<?= e($relLink) ?>" data-fetch-content="0" data-media="<?= media_storage_payload($rel, $relType, $relLink) ?>">
          <span class="streamhive-v2-related-feature-bg" style="background-image:url('<?= e(tmdb_img($rel['backdrop_path'] ?? ($rel['poster_path'] ?? null), 'w780')) ?>')"></span>
          <span class="streamhive-v2-related-rank"><?= e(str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
          <span class="streamhive-v2-related-poster-wrap">
            <img class="streamhive-v2-related-poster" src="<?= e(tmdb_img($rel['poster_path'] ?? null, 'w185')) ?>" alt="<?= e($relTitle) ?> poster">
            <span class="streamhive-v2-related-play"><i class="fa-solid fa-play"></i></span>
          </span>
          <span class="streamhive-v2-related-copy">
            <span class="streamhive-v2-related-title"><?= e($relTitle) ?></span>
            <span class="streamhive-v2-related-meta">
              <?php if ($relYear !== ''): ?><span><?= e($relYear) ?></span><?php endif; ?>
              <?php $relAgeRating = display_age_rating($rel['age_rating'] ?? '', $rel['media_type'] ?? 'movie'); if ($relAgeRating !== ''): ?><span><?= e($relAgeRating) ?></span><?php endif; ?>
            </span>
            <?php if ($relGenres): ?>
            <span class="streamhive-v2-related-genres">
              <?php foreach ($relGenres as $relGenre): ?><span><?= e($relGenre) ?></span><?php endforeach; ?>
            </span>
            <?php endif; ?>
            <span class="streamhive-v2-related-score"><i class="fa-solid fa-star"></i> <?= e((string)$relRating) ?></span>
          </span>
          <span class="streamhive-v2-related-arrow"><i class="fa-solid fa-chevron-right"></i></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</aside>
