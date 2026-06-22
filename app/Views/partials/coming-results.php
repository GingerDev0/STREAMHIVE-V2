<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php
$items = array_values($items ?? []);
$tabType = in_array((string)($tabType ?? 'movie'), ['movie', 'tv'], true) ? (string)$tabType : 'movie';
$tabLabel = (string)($tabLabel ?? ($tabType === 'tv' ? 'TV Shows' : 'Movies'));
$comingPerPage = max(1, (int)($comingPerPage ?? 18));
$comingTotal = max(0, (int)($comingTotal ?? count($items)));
$comingPage = max(1, (int)($comingPage ?? 1));
$comingPages = max(1, (int)ceil($comingTotal / $comingPerPage));
$comingPage = min($comingPage, $comingPages);
$comingVisibleFrom = $comingTotal > 0 ? (($comingPage - 1) * $comingPerPage) + 1 : 0;
$comingVisibleTo = min($comingTotal, $comingVisibleFrom + count($items) - 1);
$comingEndpoint = '/ajax/coming-this-year-items?type=' . rawurlencode($tabType) . '&per_page=' . rawurlencode((string)$comingPerPage) . '&fragment=1&page=';
$comingTarget = '#coming-results-' . preg_replace('/[^a-z0-9_-]/i', '', $tabType);
?>
<div class="streamhive-coming-results" id="coming-results-<?= e($tabType) ?>" data-coming-results="<?= e($tabType) ?>" data-page="<?= e((string)$comingPage) ?>" data-total="<?= e((string)$comingTotal) ?>" data-per-page="<?= e((string)$comingPerPage) ?>">
  <div class="streamhive-coming-grid" data-coming-grid="<?= e($tabType) ?>">
    <?php foreach ($items as $item): ?>
      <?= \App\Core\View::partial('partials/coming-card', ['item' => $item, 'tabType' => $tabType]) ?>
    <?php endforeach; ?>
  </div>
  <div class="streamhive-coming-footer mt-4" data-coming-pagination="<?= e($tabType) ?>">
    <?php if ($comingPages > 1): ?>
      <div class="streamhive-actor-pager-bar streamhive-coming-pager-bar">
        <div class="streamhive-pager-showing">
          <span>Showing</span>
          <strong><?= e((string)$comingVisibleFrom) ?><?= $comingVisibleTo !== $comingVisibleFrom ? '&ndash;' . e((string)$comingVisibleTo) : '' ?></strong>
          <span>of</span>
          <strong><?= e((string)$comingTotal) ?></strong>
          <span><?= e($tabLabel) ?></span>
        </div>
        <div class="streamhive-actor-pager-actions streamhive-coming-pager-actions">
          <?php if ($comingPage > 1): ?>
            <button type="button" class="streamhive-actor-page-btn streamhive-coming-page-btn" hx-get="<?= e($comingEndpoint . ($comingPage - 1)) ?>" hx-target="<?= e($comingTarget) ?>" hx-swap="outerHTML" hx-disabled-elt="this" aria-label="Previous page"><i class="fa-solid fa-angle-left"></i></button>
          <?php endif; ?>
          <span class="streamhive-actor-page-current streamhive-coming-page-current">Page <strong><?= e((string)$comingPage) ?></strong> of <?= e((string)$comingPages) ?></span>
          <?php if ($comingPage < $comingPages): ?>
            <button type="button" class="streamhive-actor-page-btn streamhive-coming-page-btn" hx-get="<?= e($comingEndpoint . ($comingPage + 1)) ?>" hx-target="<?= e($comingTarget) ?>" hx-swap="outerHTML" hx-disabled-elt="this" aria-label="Next page"><i class="fa-solid fa-angle-right"></i></button>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
