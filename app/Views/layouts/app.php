<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php
$siteName = 'Movie DB';
$pageTitleRaw = trim((string)($title ?? 'Movie DB V2'));
$pageTitle = str_contains($pageTitleRaw, $siteName) ? $pageTitleRaw : $pageTitleRaw . ' | ' . $siteName;
$pageDescription = meta_excerpt((string)($metaDescription ?? 'Explore trending movies and TV shows with cast, episodes, ratings, genres, and instant playback in a bold cinematic layout.'), 165);
$canonicalUrl = (string)($canonicalUrl ?? current_url());
$ogTitle = (string)($ogTitle ?? $pageTitle);
$ogDescription = meta_excerpt((string)($ogDescription ?? $pageDescription), 200);
$ogType = (string)($ogType ?? 'website');
$ogImage = (string)($ogImage ?? absolute_url(asset('img/placeholder.svg')));
$robots = (string)($robots ?? 'index, follow');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDescription) ?>">
  <meta name="robots" content="<?= e($robots) ?>">
  <meta name="theme-color" content="#080d18">
  <link rel="canonical" href="<?= e($canonicalUrl) ?>">

  <meta property="og:site_name" content="<?= e($siteName) ?>">
  <meta property="og:type" content="<?= e($ogType) ?>">
  <meta property="og:title" content="<?= e($ogTitle) ?>">
  <meta property="og:description" content="<?= e($ogDescription) ?>">
  <meta property="og:url" content="<?= e($canonicalUrl) ?>">
  <meta property="og:image" content="<?= e($ogImage) ?>">
  <meta property="og:image:alt" content="<?= e($ogTitle) ?>">

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($ogTitle) ?>">
  <meta name="twitter:description" content="<?= e($ogDescription) ?>">
  <meta name="twitter:image" content="<?= e($ogImage) ?>">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body class="v2-body">
<div class="v2-orb v2-orb-one"></div>
<div class="v2-orb v2-orb-two"></div>
<nav class="navbar navbar-expand-lg navbar-dark v2-nav sticky-top">
  <div class="container-fluid px-3 px-lg-4">
    <a class="navbar-brand v2-brand" href="/">
      <span class="v2-brand-mark"><i class="fa-solid fa-bolt"></i></span>
      <span><strong>Movie DB</strong><small>V2</small></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav mx-lg-auto mb-2 mb-lg-0 v2-nav-pills">
        <li class="nav-item"><a class="nav-link" href="/"><i class="fa-solid fa-house"></i> Home</a></li>
        <li class="nav-item"><a class="nav-link" href="/movies"><i class="fa-solid fa-film"></i> Movies</a></li>
        <li class="nav-item"><a class="nav-link" href="/tv"><i class="fa-solid fa-tv"></i> TV Shows</a></li>
        <li class="nav-item"><a class="nav-link" href="/actors"><i class="fa-solid fa-user-group"></i> Actors</a></li>
        <li class="nav-item"><a class="nav-link" href="/s"><i class="fa-solid fa-sliders"></i> Discover</a></li>
        <li class="nav-item"><a class="nav-link" href="/coming-this-year"><i class="fa-solid fa-calendar-days"></i> Coming This Year</a></li>
        <li class="nav-item"><a class="nav-link" href="/profile"><i class="fa-solid fa-user-astronaut"></i> My Profile</a></li>
      </ul>
      <form class="v2-nav-search js-live-search-form" action="/s" method="get" role="search" autocomplete="off">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input name="q" class="js-live-search-input" placeholder="Search the universe..." autocomplete="off" aria-label="Search movies, TV shows and actors" aria-expanded="false" aria-controls="navLiveSearchResults">
        <button type="submit">Go</button>
        <div class="v2-live-search-results js-live-search-results" id="navLiveSearchResults" role="listbox" aria-label="Live search results"></div>
      </form>
      <a class="v2-admin-link" href="/admin?token=change-this-token" title="Admin"><i class="fa-solid fa-gear"></i></a>
    </div>
  </div>
</nav>
<main class="container-fluid px-3 px-lg-4 py-4 v2-main"> <?= $content ?> </main>
<footer class="v2-footer pro-footer">
  <div class="pro-footer-shell">
    <div class="pro-footer-inner">
      <a class="pro-footer-brand" href="/" aria-label="Movie DB home">
        <span class="pro-footer-brand-mark"><i class="fa-solid fa-film"></i></span>
        <span><strong>Movie DB</strong><small>V2</small></span>
      </a>

      <div class="pro-footer-copy">
        <p>Created by <a href="https://github.com/GingerDev0" target="_blank" rel="noopener noreferrer">GingerDev</a></p>
        <p>TMDB data powers imports. This product is not endorsed or certified by TMDB.</p>
        <p>Project link: <a href="https://github.com/GingerDev0/Movie-DB-V2" target="_blank" rel="noopener noreferrer">https://github.com/GingerDev0/Movie-DB-V2</a></p>
      </div>

      <nav class="pro-footer-actions" aria-label="Footer links">
        <a href="/movies"><i class="fa-solid fa-film"></i> Movies</a>
        <a href="/tv"><i class="fa-solid fa-tv"></i> TV Shows</a>
        <a href="/actors"><i class="fa-solid fa-user-group"></i> Actors</a>
        <a href="/s"><i class="fa-solid fa-compass"></i> Discover</a>
        <a href="https://github.com/GingerDev0/Movie-DB-V2" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-github"></i> GitHub</a>
      </nav>
    </div>
  </div>
</footer>


<div class="v2-share-backdrop js-share-backdrop" aria-hidden="true">
  <div class="v2-share-bar js-share-bar" role="dialog" aria-modal="true" aria-labelledby="shareBarTitle">
    <div class="v2-share-bar-head">
      <div>
        <span><i class="fa-solid fa-share-nodes"></i> Share</span>
        <h2 id="shareBarTitle">Share this page</h2>
      </div>
      <button class="v2-share-close js-share-close" type="button" aria-label="Close share options"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="v2-share-url-row">
      <input class="js-share-url" type="text" readonly value="" aria-label="Shareable link">
      <button class="js-share-copy" type="button"><i class="fa-regular fa-copy"></i> Copy</button>
    </div>
    <div class="v2-share-actions" aria-label="Popular sharing apps">
      <a class="js-share-native v2-share-action v2-share-native" href="#"><i class="fa-solid fa-arrow-up-from-bracket"></i><span>Share</span></a>
      <a class="js-share-whatsapp v2-share-action" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp"></i><span>WhatsApp</span></a>
      <a class="js-share-facebook v2-share-action" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-facebook-f"></i><span>Facebook</span></a>
      <a class="js-share-x v2-share-action" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-x-twitter"></i><span>X</span></a>
      <a class="js-share-telegram v2-share-action" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-telegram"></i><span>Telegram</span></a>
      <a class="js-share-reddit v2-share-action" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-reddit-alien"></i><span>Reddit</span></a>
      <a class="js-share-email v2-share-action"><i class="fa-regular fa-envelope"></i><span>Email</span></a>
    </div>
  </div>
</div>

<div class="modal fade fetch-modal" id="contentFetchModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content fetch-modal-content">
      <div class="modal-body text-center p-4 p-lg-5">
        <div class="fetch-spinner mx-auto mb-4"><i class="fa-solid fa-cloud-arrow-down"></i></div>
        <h2 class="h4 mb-2">Fetching content</h2>
        <p class="text-white-50 mb-0" data-fetch-modal-message>This title is being added now. Please wait...</p>
        <div class="fetch-progress mt-4" aria-hidden="true"><span></span></div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body></html>
