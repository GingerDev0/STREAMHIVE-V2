<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php
$siteName = site_name();
$pageTitleRaw = trim((string)($title ?? $siteName));
$pageTitle = str_contains($pageTitleRaw, $siteName) ? $pageTitleRaw : $pageTitleRaw . ' | ' . $siteName;
$pageDescription = meta_excerpt((string)($metaDescription ?? 'Explore trending movies and TV shows with cast, episodes, ratings, genres, and instant playback in a bold cinematic layout.'), 165);
$canonicalUrl = (string)($canonicalUrl ?? current_url());
$ogTitle = (string)($ogTitle ?? $pageTitle);
$ogDescription = meta_excerpt((string)($ogDescription ?? $pageDescription), 200);
$ogType = (string)($ogType ?? 'website');
$siteLogo = asset('img/logo.png');
$ogImage = (string)($ogImage ?? absolute_url($siteLogo));
$robots = (string)($robots ?? 'index, follow');
$currentPath = '/' . trim((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
if ($currentPath !== '/') $currentPath = rtrim($currentPath, '/');
$genreNavItems = [
    'Action', 'Adventure', 'Animation', 'Comedy', 'Crime', 'Documentary',
    'Drama', 'Family', 'Fantasy', 'Horror', 'Mystery', 'Romance',
    'Science Fiction', 'Thriller', 'War', 'Western',
];
$isGenreNavActive = $currentPath === '/s';
$browseNavItems = [
    ['href' => '/movies', 'label' => 'Movies', 'icon' => 'fa-film', 'description' => 'Browse the movie library', 'match' => 'prefix'],
    ['href' => '/tv', 'label' => 'TV Shows', 'icon' => 'fa-tv', 'description' => 'Browse series and episodes', 'match' => 'prefix'],
    ['href' => '/actors', 'label' => 'Actors', 'icon' => 'fa-user-group', 'description' => 'Find cast and filmographies', 'match' => 'prefixes', 'prefixes' => ['/actors', '/actor']],
];
$navItems = [
    ['href' => '/', 'label' => 'Home', 'icon' => 'fa-house', 'match' => 'exact'],
    ['href' => '/coming-this-year', 'label' => 'Upcoming', 'icon' => 'fa-calendar-days', 'match' => 'exact'],
    ['href' => '/profile', 'label' => 'My Profile', 'icon' => 'fa-user-astronaut', 'match' => 'prefix'],
];
$isNavActive = static function (array $item) use ($currentPath): bool {
    $href = (string)$item['href'];
    return match ($item['match'] ?? 'exact') {
        'prefix' => $currentPath === $href || str_starts_with($currentPath, $href . '/'),
        'prefixes' => array_reduce($item['prefixes'] ?? [], static fn(bool $active, string $prefix): bool => $active || $currentPath === $prefix || str_starts_with($currentPath, $prefix . '/'), false),
        default => $currentPath === $href,
    };
};
$isBrowseNavActive = array_reduce($browseNavItems, static fn(bool $active, array $item): bool => $active || $isNavActive($item), false);
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
  <link href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css" rel="stylesheet">
  <link href="<?= e(asset('css/app.css') . '?v=' . (string)@filemtime(public_path('assets/css/app.css'))) ?>" rel="stylesheet">
</head>
<body class="v2-body" data-site-name="<?= e($siteName) ?>">
<div class="v2-orb v2-orb-one"></div>
<div class="v2-orb v2-orb-two"></div>
<nav class="navbar navbar-expand-lg navbar-dark v2-nav sticky-top">
  <div class="container-fluid px-3 px-lg-4">
    <a class="navbar-brand v2-brand" href="/">
      <span class="v2-brand-mark"><img src="<?= e($siteLogo) ?>" alt="<?= e($siteName) ?> logo"></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav mx-lg-auto mb-2 mb-lg-0 v2-nav-pills">
        <?php foreach ($navItems as $index => $navItem): $active = $isNavActive($navItem); ?>
        <li class="nav-item"><a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= e((string)$navItem['href']) ?>"<?= $active ? ' aria-current="page"' : '' ?>><i class="fa-solid <?= e((string)$navItem['icon']) ?>"></i> <?= e((string)$navItem['label']) ?></a></li>
        <?php if ($index === 0): ?>
        <li class="nav-item dropdown v2-genre-dropdown v2-browse-dropdown">
          <a class="nav-link dropdown-toggle<?= $isBrowseNavActive ? ' active' : '' ?>" href="/movies" id="browseNavDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false"<?= $isBrowseNavActive ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid fa-compass"></i> Browse
          </a>
          <div class="dropdown-menu v2-genre-menu v2-browse-menu dropdown-menu-dark" aria-labelledby="browseNavDropdown">
            <?php foreach ($browseNavItems as $browseNavItem): ?>
            <a class="dropdown-item<?= $isNavActive($browseNavItem) ? ' active' : '' ?>" href="<?= e((string)$browseNavItem['href']) ?>">
              <i class="fa-solid <?= e((string)$browseNavItem['icon']) ?>"></i>
              <span><strong><?= e((string)$browseNavItem['label']) ?></strong><small><?= e((string)$browseNavItem['description']) ?></small></span>
            </a>
            <?php endforeach; ?>
          </div>
        </li>
        <li class="nav-item dropdown v2-genre-dropdown">
          <a class="nav-link dropdown-toggle<?= $isGenreNavActive ? ' active' : '' ?>" href="/s" id="genreNavDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false"<?= $isGenreNavActive ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid fa-tags"></i> Genres
          </a>
          <div class="dropdown-menu v2-genre-menu dropdown-menu-dark" aria-labelledby="genreNavDropdown">
            <a class="dropdown-item v2-genre-menu-all" href="<?= e(url('s')) ?>"><i class="fa-solid fa-sliders"></i><span>All genres</span></a>
            <div class="dropdown-divider"></div>
            <div class="v2-genre-menu-grid">
              <?php foreach ($genreNavItems as $genreNavItem): ?>
              <a class="dropdown-item" href="<?= e(genre_url($genreNavItem)) ?>"><i class="fa-solid <?= e(genre_icon($genreNavItem)) ?>"></i><span><?= e($genreNavItem) ?></span></a>
              <?php endforeach; ?>
            </div>
          </div>
        </li>
        <?php endif; ?>
        <?php endforeach; ?>
      </ul>
      <form class="v2-nav-search js-live-search-form" action="/s" method="get" role="search" autocomplete="off">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input name="q" class="js-live-search-input" placeholder="Search the universe..." autocomplete="off" aria-label="Search movies, TV shows and actors" aria-expanded="false" aria-controls="navLiveSearchResults">
        <button type="submit">Go</button>
        <div class="v2-live-search-results js-live-search-results" id="navLiveSearchResults" role="listbox" aria-label="Live search results"></div>
      </form>
    </div>
  </div>
</nav>
<main class="container-fluid px-3 px-lg-4 py-4 v2-main"> <?= $content ?> </main>
<footer class="v2-footer pro-footer">
  <div class="pro-footer-shell">
    <div class="pro-footer-inner">
      <div class="pro-footer-brand-block">
        <a class="pro-footer-brand" href="/" aria-label="<?= e($siteName) ?> home">
          <span class="pro-footer-brand-mark"><img src="<?= e($siteLogo) ?>" alt="<?= e($siteName) ?> logo"></span>
        </a>
        <p>Find something worth watching without digging through the noise.</p>
      </div>

      <div class="pro-footer-links">
        <nav class="pro-footer-group" aria-label="Browse">
          <h2>Browse</h2>
          <a href="/movies"><i class="fa-solid fa-film"></i> Movies</a>
          <a href="/tv"><i class="fa-solid fa-tv"></i> TV Shows</a>
          <a href="/actors"><i class="fa-solid fa-user-group"></i> Actors</a>
          <a href="/s"><i class="fa-solid fa-compass"></i> Discover</a>
        </nav>

        <div class="pro-footer-group pro-footer-note">
          <p>Created by <a href="https://github.com/GingerDev0" target="_blank" rel="noopener noreferrer">GingerDev</a></p>
        </div>
      </div>
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
<script src="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/js/splide.min.js"></script>
<script src="<?= e(asset('js/app.js') . '?v=' . (string)@filemtime(public_path('assets/js/app.js'))) ?>"></script>
</body></html>
