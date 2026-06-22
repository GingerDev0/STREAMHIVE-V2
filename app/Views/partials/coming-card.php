<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php
$tabType = $tabType ?? 'movie';
$item = $item ?? [];
$title = (string)($item['title'] ?? 'Untitled');
$date = media_release_date($item);
$prettyDate = format_date($date);
$poster = tmdb_img($item['poster_path'] ?? null, 'w500');
$backdrop = tmdb_img($item['backdrop_path'] ?? ($item['poster_path'] ?? null), !empty($item['backdrop_path']) ? 'w1280' : 'w780');
$rating = round((float)($item['vote_average'] ?? 0), 1);
$genres = array_values(array_filter(array_map('strval', $item['genres'] ?? [])));
$genreIcons = [];
foreach ($genres as $genre) $genreIcons[$genre] = genre_icon($genre);
$overview = trim((string)($item['overview'] ?? ''));
$typeLabel = $tabType === 'tv' ? 'TV Show' : 'Movie';
$tmdbId = (int)($item['tmdb_id'] ?? $item['id'] ?? 0);
?>
<article class="streamhive-coming-card" data-coming-item data-coming-modal
  role="button" tabindex="0" aria-label="View details for <?= e($title) ?>"
  data-tmdb-id="<?= e((string)$tmdbId) ?>"
  data-media-type="<?= e($tabType) ?>"
  data-title="<?= e($title) ?>"
  data-type="<?= e($typeLabel) ?>"
  data-date="<?= e($prettyDate) ?>"
  data-release-date="<?= e($date) ?>"
  data-rating="<?= e($rating > 0 ? (string)$rating : '') ?>"
  data-genres="<?= e(implode(', ', $genres)) ?>"
  data-genre-icons="<?= e(json_encode($genreIcons, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') ?>"
  data-overview="<?= e($overview) ?>"
  data-poster="<?= e($poster) ?>"
  data-backdrop="<?= e($backdrop) ?>">
  <div class="streamhive-coming-poster" aria-label="<?= e($title) ?> is not released yet">
    <img src="<?= e($poster) ?>" alt="<?= e($title) ?> poster" loading="lazy" decoding="async">
    <span class="streamhive-coming-poster-gradient"></span>
    <span class="streamhive-coming-soon-pill"><i class="fa-solid fa-lock"></i> Locked until release</span>
    <span class="streamhive-coming-info-pill"><i class="fa-solid fa-circle-info"></i> Details</span>
    <?php if ($rating > 0): ?><span class="streamhive-coming-rating"><i class="fa-solid fa-star"></i> <?= e((string)$rating) ?></span><?php endif; ?>
  </div>
  <div class="streamhive-coming-copy">
    <span class="streamhive-coming-title"><?= e($title) ?></span>
    <?php if ($prettyDate !== ''): ?><span class="streamhive-coming-date" data-coming-release-date data-release-date="<?= e($date) ?>"><i class="fa-solid fa-calendar-days"></i> <span><?= e($prettyDate) ?></span><small data-coming-release-relative></small></span><?php endif; ?>
    <?php if ($genres): ?><span class="streamhive-coming-genres"><?= genre_links($genres, $tabType, 2, 'streamhive-genre-link streamhive-genre-link-home') ?></span><?php endif; ?>
  </div>
</article>
