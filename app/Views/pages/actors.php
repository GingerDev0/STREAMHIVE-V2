<?php require_once app_path('app/Helpers/helpers.php'); use App\Core\View; ?>
<section class="glass rounded-4 p-4 mb-4 text-white">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
      <h1 class="h2 mb-1">Actors</h1>
      <p class="text-white-50 mb-0"><?= e((string)$total) ?> actor pages</p>
    </div>
    <a class="btn btn-outline-light" href="<?= e(url('s?type=person')) ?>"><i class="fa-solid fa-magnifying-glass me-1"></i> Search actors</a>
  </div>
  <form class="row g-2" method="get">
    <div class="col-md-8"><input name="q" value="<?= e($query) ?>" class="form-control" placeholder="Search actors"></div>
    <div class="col-md-2"><select name="sort" class="form-select">
      <?php foreach (['name_asc'=>'Name A-Z','name_desc'=>'Name Z-A','updated_desc'=>'Recently updated'] as $value=>$label): ?>
        <option value="<?= e($value) ?>" <?= $sort===$value?'selected':'' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-warning"><i class="fa-solid fa-filter me-1"></i>Filter</button></div>
  </form>
</section>

<?= View::partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage ?? 24, 'itemLabel' => 'Actors', 'position' => 'top']) ?>

<div class="row g-3">
  <?php foreach ($items as $item): echo View::partial('partials/media-card', ['item'=>$item, 'type'=>'person']); endforeach; ?>
  <?php if (!$items): ?><div class="col-12"><div class="glass rounded-4 p-4 text-white">No actors found.</div></div><?php endif; ?>
</div>

<?= View::partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage ?? 24, 'itemLabel' => 'Actors']) ?>
