<?php
require_once app_path('app/Helpers/helpers.php');

$currentPage = max(1, (int)($page ?? 1));
$totalPages = max(1, (int)($pages ?? 1));
$totalItems = max(0, (int)($total ?? 0));
$perPage = max(1, (int)($perPage ?? 24));
$itemLabel = trim((string)($itemLabel ?? 'items'));
$position = (string)($position ?? 'bottom');
$navClass = $position === 'top' ? 'streamhive-pager-shell streamhive-pager-shell-top mt-3 mb-4' : 'streamhive-pager-shell streamhive-pager-shell-bottom mt-5';
$maxVisible = 5;

if ($totalItems < 1 && $totalPages <= 1) {
    return;
}

$visibleFrom = $totalItems > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
$visibleTo = $totalItems > 0 ? min($totalItems, $visibleFrom + $perPage - 1) : 0;

$half = intdiv($maxVisible, 2);
$start = max(1, $currentPage - $half);
$end = min($totalPages, $start + $maxVisible - 1);
$start = max(1, $end - $maxVisible + 1);

$makeUrl = static function (int $targetPage): string {
    $query = $_GET;
    $query['page'] = $targetPage;
    return '?' . http_build_query($query);
};
?>
<nav class="<?= e($navClass) ?>" aria-label="Pagination">
  <div class="streamhive-pager-bar d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3">
    <div class="streamhive-pager-showing">
      <span>Showing</span>
      <strong><?= e((string)$visibleFrom) ?><?= $visibleTo !== $visibleFrom ? '&ndash;' . e((string)$visibleTo) : '' ?></strong>
      <span>of</span>
      <strong><?= e((string)$totalItems) ?></strong>
      <span><?= e($itemLabel) ?></span>
    </div>

    <?php if ($totalPages > 1): ?>
      <ul class="pagination streamhive-pagination-awesome d-none d-sm-inline-flex justify-content-end align-items-center flex-wrap gap-2 mb-0 ms-lg-auto">
        <?php if ($currentPage > 1): ?>
          <li class="page-item streamhive-pager-edge"><a class="page-link" href="<?= e($makeUrl(1)) ?>" aria-label="First page"><i class="fa-solid fa-angles-left" aria-hidden="true"></i></a></li>
          <li class="page-item streamhive-pager-step"><a class="page-link" href="<?= e($makeUrl($currentPage - 1)) ?>" aria-label="Previous page"><i class="fa-solid fa-angle-left" aria-hidden="true"></i></a></li>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
          <li class="page-item streamhive-pager-number <?= $i === $currentPage ? 'active' : '' ?>" <?= $i === $currentPage ? 'aria-current="page"' : '' ?>>
            <a class="page-link" href="<?= e($makeUrl($i)) ?>"><?= e((string)$i) ?></a>
          </li>
        <?php endfor; ?>

        <?php if ($currentPage < $totalPages): ?>
          <li class="page-item streamhive-pager-step"><a class="page-link" href="<?= e($makeUrl($currentPage + 1)) ?>" aria-label="Next page"><i class="fa-solid fa-angle-right" aria-hidden="true"></i></a></li>
          <li class="page-item streamhive-pager-edge"><a class="page-link" href="<?= e($makeUrl($totalPages)) ?>" aria-label="Last page"><i class="fa-solid fa-angles-right" aria-hidden="true"></i></a></li>
        <?php endif; ?>
      </ul>

      <div class="streamhive-pagination-mobile d-flex d-sm-none align-items-center justify-content-center gap-2 w-100">
        <?php if ($currentPage > 1): ?>
          <a class="streamhive-mobile-page-btn" href="<?= e($makeUrl($currentPage - 1)) ?>" aria-label="Previous page"><i class="fa-solid fa-angle-left" aria-hidden="true"></i></a>
        <?php endif; ?>

        <span class="streamhive-mobile-page-current" aria-current="page">
          <span class="streamhive-mobile-page-label">Page</span>
          <strong><?= e((string)$currentPage) ?></strong>
          <span class="streamhive-mobile-page-total">of <?= e((string)$totalPages) ?></span>
        </span>

        <?php if ($currentPage < $totalPages): ?>
          <a class="streamhive-mobile-page-btn" href="<?= e($makeUrl($currentPage + 1)) ?>" aria-label="Next page"><i class="fa-solid fa-angle-right" aria-hidden="true"></i></a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</nav>
