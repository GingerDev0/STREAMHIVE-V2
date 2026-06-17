<section class="streamhive-profile-hero streamhive-glass rounded-5 p-4 p-lg-5 mb-4">
  <div class="row g-4 align-items-center">
    <div class="col-lg-8">
      <span class="streamhive-v2-kicker"><i class="fa-solid fa-user-astronaut"></i> My Profile</span>
      <h1 class="display-5 fw-black mb-3">Your saved movies, shows and watch history.</h1>
      <p class="streamhive-v2-lead mb-0">Bookmarks and recently viewed history are stored in this browser with localStorage, so the page updates instantly without an account.</p>
    </div>
    <div class="col-lg-4">
      <div class="streamhive-profile-stats-grid">
        <div class="streamhive-profile-stat"><strong data-profile-count="bookmarks">0</strong><span>Saved</span></div>
        <div class="streamhive-profile-stat"><strong data-profile-count="recent">0</strong><span>Recent</span></div>
      </div>
    </div>
  </div>
</section>

<section class="streamhive-actor-credits-shell streamhive-profile-tabs-shell streamhive-glass rounded-4 p-3 p-lg-4 mb-4" data-profile-section="bookmarks">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <span class="streamhive-v2-section-eyebrow"><i class="fa-solid fa-bookmark"></i> Saved for later</span>
      <h2 class="mb-0 text-white fw-black">Bookmarks</h2>
    </div>
    <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
      <div class="streamhive-actor-tabs streamhive-profile-tabs" role="tablist" aria-label="Bookmark tabs">
        <button class="streamhive-actor-tab streamhive-profile-filter-tab active" type="button" data-profile-kind="bookmarks" data-profile-type="movie" aria-selected="true"><i class="fa-solid fa-film"></i> Movies <span data-profile-type-count="bookmarks:movie">0</span></button>
        <button class="streamhive-actor-tab streamhive-profile-filter-tab" type="button" data-profile-kind="bookmarks" data-profile-type="tv" aria-selected="false"><i class="fa-solid fa-tv"></i> TV Shows <span data-profile-type-count="bookmarks:tv">0</span></button>
      </div>
      <button class="btn btn-sm btn-outline-light streamhive-js-profile-clear" type="button" data-clear="bookmarks">Clear</button>
    </div>
  </div>

  <div class="streamhive-profile-list-panel active" data-profile-panel="bookmarks:movie">
    <div class="streamhive-profile-grid" data-profile-grid="bookmarks:movie" data-per-page="12"></div>
    <div class="streamhive-profile-empty streamhive-glass rounded-4 p-4" data-profile-empty="bookmarks:movie">Tap the bookmark icon on a movie to save it here.</div>
    <div class="streamhive-profile-pagination mt-4" data-profile-pagination="bookmarks:movie"></div>
  </div>

  <div class="streamhive-profile-list-panel" data-profile-panel="bookmarks:tv">
    <div class="streamhive-profile-grid" data-profile-grid="bookmarks:tv" data-per-page="12"></div>
    <div class="streamhive-profile-empty streamhive-glass rounded-4 p-4" data-profile-empty="bookmarks:tv">Tap the bookmark icon on a TV show or episode to save it here.</div>
    <div class="streamhive-profile-pagination mt-4" data-profile-pagination="bookmarks:tv"></div>
  </div>
</section>

<section class="streamhive-actor-credits-shell streamhive-profile-tabs-shell streamhive-glass rounded-4 p-3 p-lg-4 mb-5" data-profile-section="recent">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <span class="streamhive-v2-section-eyebrow"><i class="fa-solid fa-clock-rotate-left"></i> Recently opened</span>
      <h2 class="mb-0 text-white fw-black">Recently Viewed</h2>
    </div>
    <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
      <div class="streamhive-actor-tabs streamhive-profile-tabs" role="tablist" aria-label="Recently viewed tabs">
        <button class="streamhive-actor-tab streamhive-profile-filter-tab active" type="button" data-profile-kind="recent" data-profile-type="movie" aria-selected="true"><i class="fa-solid fa-film"></i> Movies <span data-profile-type-count="recent:movie">0</span></button>
        <button class="streamhive-actor-tab streamhive-profile-filter-tab" type="button" data-profile-kind="recent" data-profile-type="tv" aria-selected="false"><i class="fa-solid fa-tv"></i> TV Shows <span data-profile-type-count="recent:tv">0</span></button>
        <button class="streamhive-actor-tab streamhive-profile-filter-tab" type="button" data-profile-kind="recent" data-profile-type="person" aria-selected="false"><i class="fa-solid fa-user-group"></i> Actors <span data-profile-type-count="recent:person">0</span></button>
      </div>
      <button class="btn btn-sm btn-outline-light streamhive-js-profile-clear" type="button" data-clear="recent">Clear</button>
    </div>
  </div>

  <div class="streamhive-profile-list-panel active" data-profile-panel="recent:movie">
    <div class="streamhive-profile-grid" data-profile-grid="recent:movie" data-per-page="12"></div>
    <div class="streamhive-profile-empty streamhive-glass rounded-4 p-4" data-profile-empty="recent:movie">Movies you open will show up here.</div>
    <div class="streamhive-profile-pagination mt-4" data-profile-pagination="recent:movie"></div>
  </div>

  <div class="streamhive-profile-list-panel" data-profile-panel="recent:tv">
    <div class="streamhive-profile-grid" data-profile-grid="recent:tv" data-per-page="12"></div>
    <div class="streamhive-profile-empty streamhive-glass rounded-4 p-4" data-profile-empty="recent:tv">TV shows, seasons and episodes you open will show up here.</div>
    <div class="streamhive-profile-pagination mt-4" data-profile-pagination="recent:tv"></div>
  </div>

  <div class="streamhive-profile-list-panel" data-profile-panel="recent:person">
    <div class="streamhive-profile-grid" data-profile-grid="recent:person" data-per-page="12"></div>
    <div class="streamhive-profile-empty streamhive-glass rounded-4 p-4" data-profile-empty="recent:person">Actors you open will show up here.</div>
    <div class="streamhive-profile-pagination mt-4" data-profile-pagination="recent:person"></div>
  </div>
</section>
