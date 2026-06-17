<?php require_once app_path('app/Helpers/helpers.php'); ?>
<section class="streamhive-glass rounded-4 p-4 p-lg-5 text-white text-center">
  <div
    data-auto-fetch-content="1"
    data-fetch-type="<?= e((string)($fetchType ?? 'movie')) ?>"
    data-fetch-slug="<?= e((string)($fetchSlug ?? '')) ?>"
    data-fetch-fallback-url="<?= e((string)($fetchFallbackUrl ?? current_url())) ?>"
  ></div>
  <div class="streamhive-fetch-spinner mx-auto mb-4"><i class="fa-solid fa-cloud-arrow-down"></i></div>
  <h1 class="h3 mb-2">Fetching content</h1>
  <p class="text-white-50 mb-0"><?= e((string)($message ?? 'This content is being fetched from TMDB. Please wait...')) ?></p>
  <div class="streamhive-fetch-progress mt-4 mx-auto" aria-hidden="true" style="max-width:28rem"><span></span></div>
</section>
