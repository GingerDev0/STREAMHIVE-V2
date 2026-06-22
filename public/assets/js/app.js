document.documentElement.classList.add('streamhive-js-ready');


(() => {
  const KEYS = {
    bookmarks: 'StreamHIVE.bookmarks.v1',
    recent: 'StreamHIVE.recent.v1'
  };
  const LEGACY_PREFIX = ['movie', 'DB'].join('');
  const LEGACY_KEYS = {
    bookmarks: `${LEGACY_PREFIX}.bookmarks.v1`,
    recent: `${LEGACY_PREFIX}.recent.v1`
  };
  const LIMITS = { bookmarks: 80, recent: 36 };

  const read = (key) => {
    try {
      if (!localStorage.getItem(KEYS[key]) && localStorage.getItem(LEGACY_KEYS[key])) {
        localStorage.setItem(KEYS[key], localStorage.getItem(LEGACY_KEYS[key]) || '[]');
      }
      return JSON.parse(localStorage.getItem(KEYS[key]) || '[]');
    }
    catch { return []; }
  };
  const write = (key, items) => localStorage.setItem(KEYS[key], JSON.stringify(items.slice(0, LIMITS[key] || 50)));
  const parseMedia = (el) => {
    try { return JSON.parse(el?.dataset?.media || '{}'); }
    catch { return {}; }
  };
  const mediaKey = (item) => {
    const type = item.type || 'media';
    const id = type === 'episode'
      ? (item.url || item.slug || item.title || 'unknown')
      : (item.tmdb_id || item.slug || item.url || item.title || 'unknown');
    return `${type}:${id}`;
  };
  const normalize = (item) => ({
    type: item.type || 'media',
    tmdb_id: item.tmdb_id || null,
    slug: item.slug || '',
    title: item.title || 'Untitled',
    url: item.url || '#',
    poster: item.poster || '/assets/img/placeholder.jpg',
    backdrop: item.backdrop || item.poster || '/assets/img/placeholder.jpg',
    year: item.year || '',
    rating: item.rating || '',
    meta: item.meta || '',
    savedAt: item.savedAt || Date.now(),
    lastWatchedAt: item.lastWatchedAt || Date.now(),
    viewedAt: item.viewedAt || Date.now()
  });
  const upsert = (key, item, timeField) => {
    if (!item || !item.title) return;
    const media = normalize({ ...item, [timeField]: Date.now() });
    const k = mediaKey(media);
    const items = read(key).filter((entry) => mediaKey(entry) !== k);
    items.unshift(media);
    write(key, items);
  };
  const removeBookmark = (item) => {
    const k = mediaKey(item);
    write('bookmarks', read('bookmarks').filter((entry) => mediaKey(entry) !== k));
  };
  const hasBookmark = (item) => read('bookmarks').some((entry) => mediaKey(entry) === mediaKey(item));

  const setBookmarkButtonState = (button, saved) => {
    button.classList.toggle('streamhive-is-saved', saved);
    button.setAttribute('aria-pressed', saved ? 'true' : 'false');
    const icon = button.querySelector('i');
    if (icon) {
      icon.classList.toggle('fa-solid', saved);
      icon.classList.toggle('fa-regular', !saved);
    }
    if (button.classList.contains('streamhive-detail-bookmark')) {
      const text = button.childNodes[button.childNodes.length - 1];
      if (text && text.nodeType === Node.TEXT_NODE) text.nodeValue = saved ? ' Saved to profile' : (button.textContent.includes('episode') ? ' Save episode' : ' Save to profile');
    }
  };

  const syncBookmarkButtons = () => {
    document.querySelectorAll('.streamhive-js-bookmark-btn[data-media]').forEach((button) => {
      setBookmarkButtonState(button, hasBookmark(parseMedia(button)));
    });
  };

  document.addEventListener('click', (event) => {
    const button = event.target.closest('.streamhive-js-bookmark-btn[data-media]');
    if (!button) return;
    event.preventDefault();
    event.stopPropagation();
    const item = parseMedia(button);
    if (hasBookmark(item)) removeBookmark(item);
    else upsert('bookmarks', item, 'savedAt');
    syncBookmarkButtons();
    renderProfile();
  });

  document.addEventListener('click', (event) => {
    const link = event.target.closest('.streamhive-js-media-link[data-media]');
    if (!link || event.target.closest('.streamhive-js-bookmark-btn')) return;
    upsert('recent', parseMedia(link), 'viewedAt');
  });


  const parseContentPath = (href) => {
    let url;
    try { url = new URL(href, window.location.origin); } catch { return null; }
    if (url.origin !== window.location.origin) return null;
    const path = url.pathname.replace(/\/+$/, '') || '/';
    let match;
    const withTmdbId = (params) => {
      const tmdbId = url.searchParams.get('tmdb_id');
      if (tmdbId && /^\d+$/.test(tmdbId)) params.tmdb_id = tmdbId;
      return params;
    };
    if ((match = path.match(/^\/movies\/([^\/]+)$/))) return withTmdbId({ type: 'movie', slug: decodeURIComponent(match[1]) });
    if ((match = path.match(/^\/actors?\/([^\/]+)$/))) return withTmdbId({ type: 'person', slug: decodeURIComponent(match[1]) });
    if ((match = path.match(/^\/tv\/([^\/]+)\/s(\d{1,2})\/e(\d{1,3})$/))) return withTmdbId({ type: 'episode', slug: decodeURIComponent(match[1]), season: String(parseInt(match[2], 10)), episode: String(parseInt(match[3], 10)) });
    if ((match = path.match(/^\/tv\/([^\/]+)\/s(\d{1,2})$/))) return withTmdbId({ type: 'season', slug: decodeURIComponent(match[1]), season: String(parseInt(match[2], 10)) });
    if ((match = path.match(/^\/tv\/([^\/]+)$/))) return withTmdbId({ type: 'tv', slug: decodeURIComponent(match[1]) });
    return null;
  };

  const modalMessage = document.querySelector('[data-fetch-modal-message]');
  const fetchModalElement = document.getElementById('contentFetchModal');
  let fetchModal = null;
  const forceShowFetchModal = () => {
    if (!fetchModalElement) return;
    fetchModalElement.classList.add('show');
    fetchModalElement.removeAttribute('aria-hidden');
    fetchModalElement.setAttribute('aria-modal', 'true');
    fetchModalElement.setAttribute('role', 'dialog');
    fetchModalElement.style.display = 'block';
    document.body.classList.add('modal-open');
    fetchModalElement.style.zIndex = '2147483001';
    const dialog = fetchModalElement.querySelector('.modal-dialog');
    if (dialog) dialog.style.zIndex = '2147483002';
    const content = fetchModalElement.querySelector('.modal-content');
    if (content) content.style.zIndex = '2147483002';
    if (!document.querySelector('.modal-backdrop.streamhive-fetch-modal-backdrop')) {
      const backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop fade show streamhive-fetch-modal-backdrop';
      backdrop.style.zIndex = '2147483000';
      document.body.appendChild(backdrop);
    } else {
      document.querySelectorAll('.modal-backdrop.streamhive-fetch-modal-backdrop').forEach((backdrop) => {
        backdrop.style.zIndex = '2147483000';
      });
    }
  };
  const showFetchModal = (message) => {
    if (modalMessage) modalMessage.textContent = message || 'This title is being added now. Please wait...';
    if (!fetchModalElement) return;
    forceShowFetchModal();
    if (window.bootstrap && window.bootstrap.Modal) {
      fetchModal = fetchModal || bootstrap.Modal.getOrCreateInstance(fetchModalElement, { backdrop: 'static', keyboard: false });
      fetchModal.show();
    }
  };
  const setFetchError = (message) => {
    forceShowFetchModal();
    if (modalMessage) modalMessage.innerHTML = `${escapeHtml(message || 'Fetching failed. Please try again.')}<br><button class="btn btn-sm btn-warning mt-3" type="button" onclick="window.location.reload()">Reload</button>`;
  };
  const buildFetchUrl = (endpoint, params) => {
    const qs = new URLSearchParams(params);
    return `${endpoint}?${qs.toString()}`;
  };
  const contentLabel = (type) => type === 'person' ? 'actor page' : (type === 'episode' ? 'episode' : (type === 'season' ? 'season' : (type === 'tv' ? 'TV show' : 'movie')));

  const runContentFetch = async (params, fallbackUrl, options = {}) => {
    const markedNeedsFetch = options.markedNeedsFetch === true;
    const link = options.link || null;

    showFetchModal(`This ${contentLabel(params.type)} is being fetched from TMDB. Please wait...`);

    try {
      const statusResponse = await fetch(buildFetchUrl('/ajax/content-status', params), { headers: { Accept: 'application/json' } });
      if (!statusResponse.ok) { window.location.href = fallbackUrl; return; }
      const status = await statusResponse.json();
      if (status.ok && status.ready && !markedNeedsFetch) {
        window.location.href = status.url || fallbackUrl;
        return;
      }
      if (status.ok && status.ready && markedNeedsFetch) {
        if (link) link.dataset.fetchContent = '0';
        window.location.href = status.url || fallbackUrl;
        return;
      }

      const ensureResponse = await fetch(buildFetchUrl('/ajax/ensure-content', params), { headers: { Accept: 'application/json' } });
      if (!ensureResponse.ok) { window.location.href = fallbackUrl; return; }
      const ensured = await ensureResponse.json();
      if (ensured.ok && ensured.ready && ensured.url) {
        window.location.href = ensured.url;
        return;
      }
      const failure = ensured.message || 'Opening the page now...';
      if (/SQLite is still finishing|still finishing the write|was saved/i.test(failure)) {
        showFetchModal('Finalising the TMDB response...');
        setTimeout(() => runContentFetch(params, fallbackUrl, { markedNeedsFetch: true, link }), 700);
      } else if (/not available yet|not released yet/i.test(failure)) {
        setFetchError(failure);
      } else {
        showFetchModal('Opening the page now...');
        window.location.href = fallbackUrl;
      }
    } catch (error) {
      showFetchModal('Opening the page now...');
      window.location.href = fallbackUrl;
    }
  };

  document.addEventListener('click', async (event) => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.target.closest('.streamhive-js-bookmark-btn')) return;
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (link.target && link.target !== '_self') return;

    const params = parseContentPath(link.getAttribute('href') || '');
    if (!params) return;

    const markedNeedsFetch = link.dataset.fetchContent === '1';
    const markedReady = link.dataset.fetchContent === '0';
    if (markedReady) return;
    if (!markedNeedsFetch && !link.classList.contains('streamhive-js-media-link')) return;

    event.preventDefault();
    upsert('recent', parseMedia(link), 'viewedAt');

    // Listing/search/profile/cast cards can point at lightweight prefetched records.
    // Show the locked modal before any network checks so users get immediate feedback.
    await runContentFetch(params, link.href, { markedNeedsFetch, link });
  });

  const autoFetch = document.querySelector('[data-auto-fetch-content="1"]');
  if (autoFetch) {
    const params = {
      type: autoFetch.dataset.fetchType || 'movie',
      slug: autoFetch.dataset.fetchSlug || ''
    };
    if (autoFetch.dataset.fetchSeason) params.season = autoFetch.dataset.fetchSeason;
    if (autoFetch.dataset.fetchEpisode) params.episode = autoFetch.dataset.fetchEpisode;
    const autoUrl = new URL(autoFetch.dataset.fetchFallbackUrl || window.location.href, window.location.origin);
    const autoTmdbId = autoUrl.searchParams.get('tmdb_id');
    if (autoTmdbId && /^\d+$/.test(autoTmdbId)) params.tmdb_id = autoTmdbId;
    if (params.slug) runContentFetch(params, autoFetch.dataset.fetchFallbackUrl || window.location.href, { markedNeedsFetch: true });
  }

  const pageMedia = document.querySelector('.streamhive-js-page-media[data-media]');
  if (pageMedia) upsert('recent', parseMedia(pageMedia), 'viewedAt');


  const escapeHtml = (value) => String(value || '').replace(/[&<>'"]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[ch]));
  const typeLabel = (type) => type === 'person' ? 'Actor' : (type === 'tv' ? 'TV' : (type === 'episode' ? 'Episode' : 'Movie'));
  const renderCard = (item, section) => {
    const saved = hasBookmark(item);
    const meta = item.meta || [typeLabel(item.type), item.year].filter(Boolean).join(' · ');
    return `<article class="streamhive-profile-card">
      <a href="${escapeHtml(item.url)}" class="streamhive-profile-card-link streamhive-js-media-link" data-media='${escapeHtml(JSON.stringify(item))}'>
        <img src="${escapeHtml(item.poster)}" alt="${escapeHtml(item.title)} poster" loading="lazy">
        <span class="streamhive-profile-card-gradient"></span>
        <span class="streamhive-profile-type">${escapeHtml(typeLabel(item.type))}</span>
        ${item.rating ? `<span class="streamhive-profile-rating"><i class="fa-solid fa-star"></i> ${escapeHtml(item.rating)}</span>` : ''}
        <span class="streamhive-profile-card-copy"><strong>${escapeHtml(item.title)}</strong><small>${escapeHtml(meta)}</small></span>
      </a>
      <button class="streamhive-profile-remove ${section === 'bookmarks' ? 'streamhive-js-bookmark-btn' : 'streamhive-js-profile-remove'} ${saved ? 'streamhive-is-saved' : ''}" type="button" data-section="${escapeHtml(section)}" data-media='${escapeHtml(JSON.stringify(item))}' aria-label="${section === 'bookmarks' ? 'Remove bookmark' : 'Remove item'}"><i class="${section === 'bookmarks' || saved ? 'fa-solid fa-bookmark' : 'fa-solid fa-xmark'}"></i></button>
    </article>`;
  };

  const profileBucket = (item) => {
    const type = String(item && item.type ? item.type : '').toLowerCase();
    if (type === 'person' || type === 'actor') return 'person';
    if (type === 'tv' || type === 'episode' || type === 'season') return 'tv';
    return 'movie';
  };

  const profileLabel = (bucket) => bucket === 'person' ? 'Actors' : (bucket === 'tv' ? 'TV Shows' : 'Movies');
  const profilePageState = {};
  const hasProfileReleaseDate = (item) => {
    const bucket = profileBucket(item);
    if (bucket === 'person') return true;
    return !!String(item?.year || '').trim();
  };

  const filteredProfileItems = (section, bucket) => read(section).filter((item) => profileBucket(item) === bucket && hasProfileReleaseDate(item));

  const renderProfilePager = (section, bucket, current, pages, total, start, end) => {
    const footer = document.querySelector(`[data-profile-pagination="${section}:${bucket}"]`);
    if (!footer) return;
    if (pages <= 1) {
      footer.innerHTML = '';
      return;
    }
    const visibleFrom = start + 1;
    const visibleTo = Math.min(total, end);
    footer.innerHTML = `<div class="streamhive-actor-pager-bar streamhive-profile-pager-bar">
      <div class="streamhive-pager-showing"><span>Showing</span><strong>${visibleFrom}${visibleTo !== visibleFrom ? '&ndash;' + visibleTo : ''}</strong><span>of</span><strong>${total}</strong><span>${escapeHtml(profileLabel(bucket))}</span></div>
      <div class="streamhive-actor-pager-actions streamhive-profile-pager-actions">
        ${current > 1 ? `<button type="button" class="streamhive-actor-page-btn streamhive-profile-page-btn" data-profile-page="${section}:${bucket}" data-page="${current - 1}" aria-label="Previous page"><i class="fa-solid fa-angle-left"></i></button>` : ''}
        <span class="streamhive-actor-page-current">Page <strong>${current}</strong> of ${pages}</span>
        ${current < pages ? `<button type="button" class="streamhive-actor-page-btn streamhive-profile-page-btn" data-profile-page="${section}:${bucket}" data-page="${current + 1}" aria-label="Next page"><i class="fa-solid fa-angle-right"></i></button>` : ''}
      </div>
    </div>`;
  };

  const renderProfilePanel = (section, bucket, page = null) => {
    const key = `${section}:${bucket}`;
    const grid = document.querySelector(`[data-profile-grid="${key}"]`);
    const empty = document.querySelector(`[data-profile-empty="${key}"]`);
    if (!grid) return;

    const perPage = parseInt(grid.dataset.perPage || '12', 10) || 12;
    const items = filteredProfileItems(section, bucket);
    const pages = Math.max(1, Math.ceil(items.length / perPage));
    const current = Math.min(Math.max(1, page || profilePageState[key] || 1), pages);
    profilePageState[key] = current;
    const start = (current - 1) * perPage;
    const end = start + perPage;

    grid.classList.add('streamhive-is-profile-loading');
    window.setTimeout(() => {
      grid.innerHTML = items.slice(start, end).map((item) => renderCard(item, section)).join('');
      grid.classList.remove('streamhive-is-profile-loading');
      if (empty) empty.classList.toggle('d-none', items.length > 0);
      renderProfilePager(section, bucket, current, pages, items.length, start, end);
    }, 70);
  };

  const activeProfileBucket = (section) => {
    const active = document.querySelector(`.streamhive-profile-filter-tab.active[data-profile-kind="${section}"]`);
    return active?.dataset?.profileType || 'movie';
  };

  function renderProfile() {
    const root = document.querySelector('[data-profile-section]');
    if (!root) return;

    ['bookmarks', 'recent'].forEach((section) => {
      const items = read(section);
      document.querySelectorAll(`[data-profile-count="${section}"]`).forEach((count) => { count.textContent = String(items.length); });
      ['movie', 'tv', 'person'].forEach((bucket) => {
        const count = filteredProfileItems(section, bucket).length;
        document.querySelectorAll(`[data-profile-type-count="${section}:${bucket}"]`).forEach((node) => { node.textContent = String(count); });
      });
      ['movie', 'tv', 'person'].forEach((bucket) => renderProfilePanel(section, bucket));
    });
  }

  document.addEventListener('click', (event) => {
    const tab = event.target.closest('.streamhive-profile-filter-tab[data-profile-kind][data-profile-type]');
    if (!tab) return;
    const section = tab.dataset.profileKind || 'bookmarks';
    const bucket = tab.dataset.profileType || 'movie';
    document.querySelectorAll(`.streamhive-profile-filter-tab[data-profile-kind="${section}"]`).forEach((button) => {
      const active = button.dataset.profileType === bucket;
      button.classList.toggle('active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll(`[data-profile-panel^="${section}:"]`).forEach((panel) => {
      const active = panel.dataset.profilePanel === `${section}:${bucket}`;
      panel.classList.toggle('active', active);
      panel.style.display = active ? '' : 'none';
    });
    profilePageState[`${section}:${bucket}`] = profilePageState[`${section}:${bucket}`] || 1;
    renderProfilePanel(section, bucket, profilePageState[`${section}:${bucket}`]);
  });

  document.addEventListener('click', (event) => {
    const pager = event.target.closest('.streamhive-profile-page-btn[data-profile-page][data-page]');
    if (!pager) return;
    const [section, bucket] = String(pager.dataset.profilePage || 'bookmarks:movie').split(':');
    const page = parseInt(pager.dataset.page || '1', 10) || 1;
    renderProfilePanel(section, bucket, page);
    const panel = document.querySelector(`[data-profile-panel="${section}:${bucket}"]`);
    if (panel && window.jQuery) {
      window.jQuery('html, body').animate({ scrollTop: Math.max(0, window.jQuery(panel).offset().top - 120) }, 220);
    }
  });

  document.addEventListener('click', (event) => {
    const clear = event.target.closest('.streamhive-js-profile-clear[data-clear]');
    if (!clear) return;
    write(clear.dataset.clear, []);
    syncBookmarkButtons();
    renderProfile();
  });

  document.addEventListener('click', (event) => {
    const remove = event.target.closest('.streamhive-js-profile-remove[data-section][data-media]');
    if (!remove) return;
    const section = remove.dataset.section;
    const item = parseMedia(remove);
    const key = mediaKey(item);
    write(section, read(section).filter((entry) => mediaKey(entry) !== key));
    renderProfile();
  });

  window.addEventListener('storage', () => { syncBookmarkButtons(); renderProfile(); });
  window.StreamHIVERefresh = () => { syncBookmarkButtons(); renderProfile(); };
  syncBookmarkButtons();
  renderProfile();
})();

/* Actor profile tabs + jQuery pagination */
(function ($) {
  if (!$ || !$('.streamhive-actor-tab[data-actor-tab]').length) return;

  const renderActorPage = function (type, page) {
    const $grid = $('[data-actor-grid="' + type + '"]');
    const $footer = $('[data-actor-pagination="' + type + '"]');
    if (!$grid.length || !$footer.length) return;

    const perPage = parseInt($grid.data('per-page'), 10) || 12;
    const $items = $grid.find('[data-actor-credit-item]');
    const total = $items.length;
    const pages = Math.max(1, Math.ceil(total / perPage));
    const current = Math.min(Math.max(1, page || 1), pages);
    const start = (current - 1) * perPage;
    const end = start + perPage;

    $items.hide().slice(start, end).fadeIn(140);

    if (pages <= 1) {
      $footer.empty();
      return;
    }

    const visibleFrom = start + 1;
    const visibleTo = Math.min(total, end);
    let html = '<div class="streamhive-actor-pager-bar">';
    const label = type === 'tv' ? 'TV Shows' : (type === 'production' ? 'In Production' : 'Movies');
    html += '<div class="streamhive-pager-showing"><span>Showing</span><strong>' + visibleFrom + (visibleTo !== visibleFrom ? '&ndash;' + visibleTo : '') + '</strong><span>of</span><strong>' + total + '</strong><span>' + label + '</span></div>';
    html += '<div class="streamhive-actor-pager-actions">';
    if (current > 1) html += '<button type="button" class="streamhive-actor-page-btn" data-actor-page="' + type + '" data-page="' + (current - 1) + '" aria-label="Previous page"><i class="fa-solid fa-angle-left"></i></button>';
    html += '<span class="streamhive-actor-page-current">Page <strong>' + current + '</strong> of ' + pages + '</span>';
    if (current < pages) html += '<button type="button" class="streamhive-actor-page-btn" data-actor-page="' + type + '" data-page="' + (current + 1) + '" aria-label="Next page"><i class="fa-solid fa-angle-right"></i></button>';
    html += '</div></div>';
    $footer.html(html);
  };

  const activateActorTab = function (type) {
    $('.streamhive-actor-tab').removeClass('active').attr('aria-selected', 'false');
    $('.streamhive-actor-tab[data-actor-tab="' + type + '"]').addClass('active').attr('aria-selected', 'true');
    $('.streamhive-actor-credit-panel').removeClass('active').hide();
    $('[data-actor-panel="' + type + '"]').addClass('active').fadeIn(140);
    renderActorPage(type, 1);
  };

  $(document).on('click', '.streamhive-actor-tab[data-actor-tab]', function () {
    activateActorTab($(this).attr('data-actor-tab'));
  });

  $(document).on('click', '.streamhive-actor-page-btn[data-actor-page][data-page]', function () {
    const type = $(this).data('actor-page');
    const page = parseInt($(this).data('page'), 10) || 1;
    renderActorPage(type, page);
    const $panel = $('[data-actor-panel="' + type + '"]');
    if ($panel.length) $('html, body').animate({ scrollTop: Math.max(0, $panel.offset().top - 120) }, 220);
  });

  $('.streamhive-actor-credit-panel').hide();
  activateActorTab($('.streamhive-actor-tab.active').attr('data-actor-tab') || 'movie');
})(window.jQuery);

/* Movie detail tabs */
(function ($) {
  if (!$ || !$('.streamhive-movie-detail-tabs-shell').length) return;

  const activateMovieDetailTab = function (type) {
    $('.streamhive-movie-detail-tab').removeClass('active').attr('aria-selected', 'false');
    $('.streamhive-movie-detail-tab[data-movie-detail-tab="' + type + '"]').addClass('active').attr('aria-selected', 'true');
    $('.streamhive-movie-detail-panel').removeClass('active').hide();
    $('[data-movie-detail-panel="' + type + '"]').addClass('active').fadeIn(140);
  };

  $(document).on('click', '.streamhive-movie-detail-tab[data-movie-detail-tab]', function () {
    activateMovieDetailTab($(this).attr('data-movie-detail-tab'));
  });

  $('.streamhive-movie-detail-panel').hide();
  activateMovieDetailTab($('.streamhive-movie-detail-tab.active').attr('data-movie-detail-tab') || 'cast');
})(window.jQuery);

/* Coming this year tabs + jQuery pagination */
(function ($) {
  if (!$ || !$('.streamhive-coming-tabs-shell').length) return;

  const renderComingFooter = function (type, current, pages, total, perPage) {
    const $footer = $('[data-coming-pagination="' + type + '"]');
    if (!$footer.length) return;

    if (pages <= 1) {
      $footer.empty();
      return;
    }

    const start = (current - 1) * perPage;
    const visibleFrom = start + 1;
    const visibleTo = Math.min(total, start + perPage);
    const label = type === 'tv' ? 'TV Shows' : 'Movies';
    let html = '<div class="streamhive-actor-pager-bar streamhive-coming-pager-bar">';
    html += '<div class="streamhive-pager-showing"><span>Showing</span><strong>' + visibleFrom + (visibleTo !== visibleFrom ? '&ndash;' + visibleTo : '') + '</strong><span>of</span><strong>' + total + '</strong><span>' + label + '</span></div>';
    html += '<div class="streamhive-actor-pager-actions streamhive-coming-pager-actions">';
    if (current > 1) html += '<button type="button" class="streamhive-actor-page-btn streamhive-coming-page-btn" data-coming-page="' + type + '" data-page="' + (current - 1) + '" aria-label="Previous page"><i class="fa-solid fa-angle-left"></i></button>';
    html += '<span class="streamhive-actor-page-current">Page <strong>' + current + '</strong> of ' + pages + '</span>';
    if (current < pages) html += '<button type="button" class="streamhive-actor-page-btn streamhive-coming-page-btn" data-coming-page="' + type + '" data-page="' + (current + 1) + '" aria-label="Next page"><i class="fa-solid fa-angle-right"></i></button>';
    html += '</div></div>';
    $footer.html(html);
  };

  const renderComingPage = function (type, page) {
    const $grid = $('[data-coming-grid="' + type + '"]');
    const $footer = $('[data-coming-pagination="' + type + '"]');
    if (!$grid.length || !$footer.length) return;

    const perPage = parseInt($grid.data('per-page'), 10) || 18;
    const total = parseInt($grid.attr('data-total') || $grid.data('total'), 10) || $grid.find('[data-coming-item]').length;
    const pages = Math.max(1, Math.ceil(total / perPage));
    const current = Math.min(Math.max(1, page || 1), pages);
    const loadedPage = parseInt($grid.attr('data-loaded-page') || $grid.data('loaded-page'), 10) || 1;

    renderComingFooter(type, current, pages, total, perPage);
    if (loadedPage === current) {
      return;
    }

    $grid.addClass('streamhive-is-loading').html('<div class="streamhive-coming-grid-loading"><i class="fa-solid fa-spinner fa-spin"></i></div>');
    $.getJSON('/ajax/coming-this-year-items', { type: type, page: current, per_page: perPage })
      .done(function (payload) {
        if (!payload || !payload.ok) return;
        $grid.attr('data-loaded-page', payload.page || current);
        $grid.attr('data-total', payload.total || total);
        $grid.html(payload.html || '');
        renderComingFooter(type, payload.page || current, payload.pages || pages, payload.total || total, perPage);
      })
      .fail(function () {
        $grid.html('<div class="streamhive-coming-empty text-center py-5"><i class="fa-solid fa-triangle-exclamation mb-3"></i><h3>Could not load this page</h3><p class="text-white-50 mb-0">Please try again in a moment.</p></div>');
      })
      .always(function () {
        $grid.removeClass('streamhive-is-loading');
      });
  };

  const activateComingTab = function (type) {
    $('.streamhive-coming-tab').removeClass('active').attr('aria-selected', 'false');
    $('.streamhive-coming-tab[data-coming-tab="' + type + '"]').addClass('active').attr('aria-selected', 'true');
    $('.streamhive-coming-panel').removeClass('active').hide();
    $('[data-coming-panel="' + type + '"]').addClass('active').fadeIn(140);
    renderComingPage(type, 1);
  };

  const setText = function (selector, value, fallback) {
    const text = String(value || fallback || '').trim();
    const $target = $(selector);
    $target.text(text).toggle(text !== '');
  };

  const setComingChip = function (selector, iconClass, value) {
    const text = String(value || '').trim();
    const $target = $(selector);
    if (!text) {
      $target.empty().hide();
      return;
    }
    $target.html('<i class="fa-solid ' + iconClass + '"></i><span>' + $('<div>').text(text).html() + '</span>').show();
  };

  const resetComingTrailer = function () {
    $('[data-coming-info-trailer]').attr('hidden', true).removeClass('streamhive-is-loading streamhive-has-trailer');
    $('[data-coming-info-trailer-frame]').empty();
    $('[data-coming-info-trailer-link]').attr('href', '#');
  };

  const loadComingTrailer = function (type, tmdbId, title) {
    tmdbId = parseInt(tmdbId, 10) || 0;
    if (!tmdbId || (type !== 'movie' && type !== 'tv')) return;

    const $trailer = $('[data-coming-info-trailer]');
    const $frame = $('[data-coming-info-trailer-frame]');
    const $link = $('[data-coming-info-trailer-link]');
    $trailer.removeAttr('hidden').addClass('streamhive-is-loading').removeClass('streamhive-has-trailer');
    $frame.html('<div class="streamhive-coming-info-trailer-loading"><i class="fa-solid fa-spinner fa-spin"></i></div>');

    $.getJSON('/ajax/upcoming-trailer', { type: type, id: tmdbId })
      .done(function (payload) {
        const trailer = payload && payload.trailer ? payload.trailer : null;
        if (!trailer || !trailer.embed_url) {
          resetComingTrailer();
          return;
        }

        const safeTitle = $('<div>').text((trailer.name || title || 'Trailer')).html();
        $frame.html('<iframe src="' + trailer.embed_url + '" title="' + safeTitle + '" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>');
        $link.attr('href', trailer.watch_url || trailer.embed_url);
        $trailer.removeClass('streamhive-is-loading').addClass('streamhive-has-trailer').removeAttr('hidden');
      })
      .fail(function () {
        resetComingTrailer();
      });
  };

  const openComingModal = function (card) {
    const $card = $(card);
    const modalEl = document.getElementById('comingInfoModal');
    if (!modalEl) return;

    const title = String($card.data('title') || 'Untitled');
    const poster = String($card.data('poster') || '/assets/img/placeholder.jpg');
    const backdrop = String($card.data('backdrop') || poster);
    const overview = String($card.data('overview') || 'No synopsis has been added yet.');
    const genres = String($card.data('genres') || '').split(',').map(function (genre) { return genre.trim(); }).filter(Boolean);
    const genreIcons = $card.data('genre-icons') || {};
    const rating = String($card.data('rating') || '').trim();
    const mediaType = String($card.data('media-type') || '');
    const tmdbId = $card.data('tmdb-id');

    resetComingTrailer();
    $('[data-coming-info-title]').text(title);
    $('[data-coming-info-poster]').attr('src', poster).attr('alt', title + ' poster');
    $('[data-coming-info-backdrop]').css('background-image', 'url("' + backdrop.replace(/"/g, '%22') + '")');
    $('[data-coming-info-overview]').text(overview);
    setText('[data-coming-info-type]', $card.data('type'), 'Movie');
    $('[data-coming-info-type-icon]')
      .removeClass('fa-film fa-tv')
      .addClass(mediaType === 'tv' ? 'fa-tv' : 'fa-film');
    setComingChip('[data-coming-info-date]', 'fa-calendar-days', $card.data('date'));
    setComingChip('[data-coming-info-rating]', 'fa-star', rating);
    $('[data-coming-info-genres]').html(genres.map(function (genre) {
      const safeGenre = $('<div>').text(genre).html();
      const safeIcon = $('<div>').text(genreIcons[genre] || 'fa-tag').html();
      const genreUrl = '/s?genre=' + encodeURIComponent(genre) + (mediaType ? '&type=' + encodeURIComponent(mediaType) : '');
      return '<a href="' + genreUrl + '"><i class="fa-solid ' + safeIcon + '"></i><span>' + safeGenre + '</span></a>';
    }).join('')).toggle(genres.length > 0);
    loadComingTrailer(mediaType, tmdbId, title);

    if (window.bootstrap && window.bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: false }).show();
    }
  };

  let comingModalScrollY = 0;
  const lockComingPageScroll = function () {
    comingModalScrollY = window.scrollY || window.pageYOffset || 0;
    $('body').css({
      position: 'fixed',
      top: '-' + comingModalScrollY + 'px',
      left: '0',
      right: '0',
      width: '100%'
    }).addClass('streamhive-coming-modal-scroll-locked');
  };

  const unlockComingPageScroll = function () {
    $('body').removeClass('streamhive-coming-modal-scroll-locked').css({
      position: '',
      top: '',
      left: '',
      right: '',
      width: ''
    });
    window.scrollTo(0, comingModalScrollY || 0);
  };

  $(document).on('click', '.streamhive-coming-tab[data-coming-tab]', function () {
    activateComingTab($(this).attr('data-coming-tab'));
  });

  $(document).on('click', '[data-coming-modal]', function (event) {
    if ($(event.target).closest('a, button').length) return;
    openComingModal(this);
  });

  $(document).on('keydown', '[data-coming-modal]', function (event) {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    if ($(event.target).closest('a, button').length) return;
    event.preventDefault();
    openComingModal(this);
  });

  $('#comingInfoModal').on('show.bs.modal', function () {
    lockComingPageScroll();
  }).on('shown.bs.modal', function () {
    $('.modal-backdrop:not(.streamhive-fetch-modal-backdrop)').remove();
  }).on('hidden.bs.modal', function () {
    $('.modal-backdrop.streamhive-coming-info-backdrop-layer').removeClass('streamhive-coming-info-backdrop-layer');
    unlockComingPageScroll();
  });

  $(document).on('click', '.streamhive-coming-page-btn[data-coming-page][data-page]', function () {
    const type = $(this).data('coming-page');
    const page = parseInt($(this).data('page'), 10) || 1;
    renderComingPage(type, page);
    const $panel = $('[data-coming-panel="' + type + '"]');
    if ($panel.length) $('html, body').animate({ scrollTop: Math.max(0, $panel.offset().top - 120) }, 220);
  });

  $('.streamhive-coming-panel').hide();
  activateComingTab($('.streamhive-coming-tab.active').attr('data-coming-tab') || 'movie');
})(window.jQuery);

/* jQuery listings: AJAX filters + pagination for /movies, /tv and /s */
(function ($) {
  if (!$) return;

  const shellSelector = '.streamhive-js-jquery-listing-shell[data-jquery-listing]';
  const supportedPath = function () {
    const path = window.location.pathname.replace(/\/+$/, '') || '/';
    return path === '/movies' || path === '/tv' || path === '/s' || path.indexOf('/s/') === 0;
  };

  const cleanParams = function (params) {
    const cleaned = new URLSearchParams();
    params.forEach(function (pair) {
      if (pair.name === 'page') return;
      if (pair.value === null || String(pair.value).trim() === '') return;
      cleaned.append(pair.name, pair.value);
    });
    return cleaned;
  };

  const setListingLoading = function ($shell, loading) {
    $shell.toggleClass('streamhive-is-loading', !!loading);
    $shell.attr('aria-busy', loading ? 'true' : 'false');
  };

  const refreshListingBehaviours = function () {
    if (window.StreamHIVERefresh) window.StreamHIVERefresh();
  };

  const replaceListingFromHtml = function (html, url, push) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const $incoming = $(doc).find(shellSelector).first();
    const $current = $(shellSelector).first();

    if (!$incoming.length || !$current.length) {
      window.location.href = url;
      return;
    }

    const incomingTitle = $(doc).find('title').text();
    $current.replaceWith($incoming);
    if (incomingTitle) document.title = incomingTitle;
    if (push) window.history.pushState({ jqueryListing: true }, incomingTitle || document.title, url);
    refreshListingBehaviours();

    const $newShell = $(shellSelector).first();
    if ($newShell.length) {
      $('html, body').animate({ scrollTop: Math.max(0, $newShell.offset().top - 110) }, 220);
    }
  };

  const loadListing = function (url, push) {
    if (!supportedPath()) {
      window.location.href = url;
      return;
    }

    const $shell = $(shellSelector).first();
    if (!$shell.length) {
      window.location.href = url;
      return;
    }

    setListingLoading($shell, true);
    $.ajax({
      url: url,
      method: 'GET',
      dataType: 'html',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).done(function (html) {
      replaceListingFromHtml(html, url, push !== false);
    }).fail(function () {
      window.location.href = url;
    }).always(function () {
      setListingLoading($(shellSelector).first(), false);
    });
  };

  $(document).on('submit', shellSelector + ' form[method="get"]', function (event) {
    event.preventDefault();
    const form = this;
    const action = form.getAttribute('action') || window.location.pathname;
    const target = new URL(action, window.location.origin);
    const params = cleanParams($(form).serializeArray());
    const qs = params.toString();
    const url = target.pathname + (qs ? '?' + qs : '');
    loadListing(url, true);
  });

  $(document).on('click', shellSelector + ' .streamhive-pager-shell a[href], ' + shellSelector + ' .streamhive-pagination-mobile a[href]', function (event) {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const href = this.getAttribute('href') || '';
    const url = new URL(href, window.location.href);
    if (url.origin !== window.location.origin) return;
    event.preventDefault();
    loadListing(url.pathname + url.search, true);
  });

  $(document).on('change', shellSelector + ' select', function () {
    const $form = $(this).closest('form');
    if ($form.length) $form.trigger('submit');
  });

  window.addEventListener('popstate', function () {
    if ($(shellSelector).length && supportedPath()) loadListing(window.location.pathname + window.location.search, false);
  });
})(window.jQuery);

/* Navbar live search: capped at 6 clickable results */
(function ($) {
  if (!$) return;

  const $form = $('.streamhive-js-live-search-form').first();
  const $input = $form.find('.streamhive-js-live-search-input').first();
  const $results = $form.find('.streamhive-js-live-search-results').first();
  if (!$form.length || !$input.length || !$results.length) return;

  const escapeHtml = function (value) {
    return String(value || '').replace(/[&<>'"]/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[ch];
    });
  };

  let timer = null;
  let request = null;
  let activeIndex = -1;
  let lastQuery = '';

  const hideResults = function () {
    $results.removeClass('streamhive-is-open').empty();
    $input.attr('aria-expanded', 'false');
    activeIndex = -1;
  };

  const setActive = function (index) {
    const $items = $results.find('.streamhive-v2-live-search-item');
    if (!$items.length) return;
    activeIndex = Math.max(0, Math.min(index, $items.length - 1));
    $items.removeClass('is-active').attr('aria-selected', 'false');
    $items.eq(activeIndex).addClass('is-active').attr('aria-selected', 'true');
  };

  const renderResults = function (payload) {
    const items = Array.isArray(payload && payload.results) ? payload.results.slice(0, 6) : [];
    const query = String(payload && payload.query ? payload.query : '').trim();

    if (!query || query !== lastQuery) return;

    if (!items.length) {
      $results.html('<div class="streamhive-v2-live-search-empty">No local matches yet. Press Enter to search/discover.</div>');
      $results.addClass('streamhive-is-open');
      $input.attr('aria-expanded', 'true');
      activeIndex = -1;
      return;
    }

    let html = '<div class="streamhive-v2-live-search-list">';
    items.forEach(function (item, index) {
      const mediaJson = escapeHtml(JSON.stringify(item.media || {}));
      const rating = item.rating ? '<span><i class="fa-solid fa-star"></i> ' + escapeHtml(item.rating) + '</span>' : '';
      html += '<a class="streamhive-v2-live-search-item streamhive-js-media-link" role="option" aria-selected="false" data-live-search-index="' + index + '" data-fetch-content="' + escapeHtml(item.fetch_content || '0') + '" data-media=\'' + mediaJson + '\' href="' + escapeHtml(item.url || '#') + '">';
      html += '<span class="streamhive-v2-live-search-poster"><img src="' + escapeHtml(item.poster || '/assets/img/placeholder.jpg') + '" alt="' + escapeHtml(item.title || 'Result') + ' poster" loading="lazy"></span>';
      html += '<span class="streamhive-v2-live-search-copy"><strong>' + escapeHtml(item.title || 'Untitled') + '</strong><small><span>' + escapeHtml(item.meta || item.type_label || 'Result') + '</span>' + rating + '</small></span>';
      html += '<span class="streamhive-v2-live-search-arrow"><i class="fa-solid fa-arrow-right"></i></span>';
      html += '</a>';
    });
    html += '</div>';
    html += '<a class="streamhive-v2-live-search-all" href="' + escapeHtml(payload.search_url || ('/s?q=' + encodeURIComponent(query))) + '">View all results for <strong>' + escapeHtml(query) + '</strong></a>';

    $results.html(html).addClass('streamhive-is-open');
    $input.attr('aria-expanded', 'true');
    activeIndex = -1;
  };

  const search = function () {
    const query = String($input.val() || '').trim();
    lastQuery = query;

    if (query.length < 2) {
      hideResults();
      return;
    }

    if (request && request.readyState !== 4) request.abort();
    $results.html('<div class="streamhive-v2-live-search-loading"><span></span> Searching...</div>').addClass('streamhive-is-open');
    $input.attr('aria-expanded', 'true');

    request = $.ajax({
      url: '/ajax/live-search',
      method: 'GET',
      dataType: 'json',
      data: { q: query },
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).done(renderResults).fail(function (xhr) {
      if (xhr && xhr.statusText === 'abort') return;
      hideResults();
    });
  };

  $input.on('input', function () {
    clearTimeout(timer);
    timer = setTimeout(search, 160);
  });

  $input.on('focus', function () {
    if ($results.children().length && String($input.val() || '').trim().length >= 2) {
      $results.addClass('streamhive-is-open');
      $input.attr('aria-expanded', 'true');
    }
  });

  $input.on('keydown', function (event) {
    const $items = $results.find('.streamhive-v2-live-search-item');
    if (!$results.hasClass('streamhive-is-open') || !$items.length) {
      if (event.key === 'Escape') hideResults();
      return;
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setActive(activeIndex + 1);
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      setActive(activeIndex <= 0 ? $items.length - 1 : activeIndex - 1);
    } else if (event.key === 'Enter' && activeIndex >= 0) {
      event.preventDefault();
      $items.eq(activeIndex).trigger('click');
      window.location.href = $items.eq(activeIndex).attr('href');
    } else if (event.key === 'Escape') {
      hideResults();
    }
  });

  $(document).on('mousemove', '.streamhive-v2-live-search-item', function () {
    const index = parseInt($(this).data('live-search-index'), 10);
    if (!Number.isNaN(index)) setActive(index);
  });

  $(document).on('mousedown', function (event) {
    if (!$(event.target).closest('.streamhive-js-live-search-form').length) hideResults();
  });

  $form.on('submit', function () {
    hideResults();
  });
})(window.jQuery);

/* Share bar modal: popular apps + copyable page link */
(function ($) {
  if (!$) return;

  const $backdrop = $('.streamhive-js-share-backdrop').first();
  const $bar = $('.streamhive-js-share-bar').first();
  if (!$backdrop.length || !$bar.length) return;

  const $urlInput = $backdrop.find('.streamhive-js-share-url').first();
  const $copy = $backdrop.find('.streamhive-js-share-copy').first();
  const $native = $backdrop.find('.streamhive-js-share-native').first();
  const $whatsapp = $backdrop.find('.streamhive-js-share-whatsapp').first();
  const $facebook = $backdrop.find('.streamhive-js-share-facebook').first();
  const $x = $backdrop.find('.streamhive-js-share-x').first();
  const $telegram = $backdrop.find('.streamhive-js-share-telegram').first();
  const $reddit = $backdrop.find('.streamhive-js-share-reddit').first();
  const $email = $backdrop.find('.streamhive-js-share-email').first();

  const siteName = String(document.body?.dataset?.siteName || 'StreamHIVE').trim() || 'StreamHIVE';
  let shareTitle = document.title || siteName;
  let shareUrl = window.location.href;

  const encode = encodeURIComponent;

  const setLinks = function () {
    const text = shareTitle + ' ' + shareUrl;
    $urlInput.val(shareUrl);
    $whatsapp.attr('href', 'https://wa.me/?text=' + encode(text));
    $facebook.attr('href', 'https://www.facebook.com/sharer/sharer.php?u=' + encode(shareUrl));
    $x.attr('href', 'https://twitter.com/intent/tweet?text=' + encode(shareTitle) + '&url=' + encode(shareUrl));
    $telegram.attr('href', 'https://t.me/share/url?url=' + encode(shareUrl) + '&text=' + encode(shareTitle));
    $reddit.attr('href', 'https://www.reddit.com/submit?url=' + encode(shareUrl) + '&title=' + encode(shareTitle));
    $email.attr('href', 'mailto:?subject=' + encode(shareTitle) + '&body=' + encode(shareUrl));
  };

  const closeShare = function () {
    $backdrop.removeClass('streamhive-is-open').attr('aria-hidden', 'true');
    $('body').removeClass('share-bar-open');
  };

  const openShare = function (button) {
    const $button = $(button || []);
    shareTitle = String($button.data('share-title') || document.title || siteName).trim();
    shareUrl = String($button.data('share-url') || window.location.href).trim();
    try { shareUrl = new URL(shareUrl, window.location.href).href; } catch (error) { shareUrl = window.location.href; }
    setLinks();
    const modalTitle = 'Share ' + (shareTitle || 'this page');
    $('#shareBarTitle').text(modalTitle);
    $copy.html('<i class="fa-regular fa-copy"></i> Copy').removeClass('is-copied');
    $backdrop.addClass('streamhive-is-open').attr('aria-hidden', 'false');
    $('body').addClass('share-bar-open');
    setTimeout(function () { $urlInput.trigger('focus').trigger('select'); }, 80);
  };

  $(document).on('click', '.streamhive-js-share-open', function (event) {
    event.preventDefault();
    openShare(this);
  });

  $backdrop.on('mousedown touchstart', function (event) {
    if (!$(event.target).closest('.streamhive-js-share-bar').length) closeShare();
  });

  $backdrop.find('.streamhive-js-share-close').on('click', function () { closeShare(); });

  $native.on('click', function (event) {
    if (navigator.share) {
      event.preventDefault();
      navigator.share({ title: shareTitle, url: shareUrl }).catch(function () {});
    }
  });

  $copy.on('click', function () {
    const done = function () {
      $copy.html('<i class="fa-solid fa-check"></i> Copied').addClass('is-copied');
      setTimeout(function () { $copy.html('<i class="fa-regular fa-copy"></i> Copy').removeClass('is-copied'); }, 1500);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(shareUrl).then(done).catch(function () {
        $urlInput.trigger('select');
        document.execCommand('copy');
        done();
      });
    } else {
      $urlInput.trigger('select');
      document.execCommand('copy');
      done();
    }
  });

  $(document).on('keydown', function (event) {
    if (event.key === 'Escape' && $backdrop.hasClass('streamhive-is-open')) closeShare();
  });
})(window.jQuery);


/* Home hero Splide carousel */
(function () {
  if (typeof window.Splide === 'undefined') return;

  const hero = document.querySelector('.streamhive-js-home-hero-splide');
  if (!hero) return;

  const shell = hero.closest('.streamhive-v2-home-hero-carousel');
  const backdrop = shell ? shell.querySelector('.streamhive-v2-home-hero-backdrop') : null;

  const updateBackdrop = function (splide) {
    const slide = splide.Components.Slides.getAt(splide.index);
    const node = slide && slide.slide ? slide.slide.querySelector('.streamhive-v2-home-hero-slide') : null;
    const image = node ? node.getAttribute('data-hero-backdrop') : '';
    if (backdrop && image) backdrop.style.backgroundImage = "url('" + image.replace(/'/g, "%27") + "')";
  };

  const splide = new Splide(hero, {
    type: 'loop',
    perPage: 1,
    perMove: 1,
    gap: '1rem',
    speed: 650,
    interval: 6500,
    autoplay: true,
    pauseOnHover: true,
    pauseOnFocus: true,
    arrows: true,
    pagination: true,
    keyboard: true,
    drag: true,
    reducedMotion: {
      speed: 0,
      rewindSpeed: 0,
      autoplay: 'pause'
    }
  });

  splide.on('mounted move moved', function () { updateBackdrop(splide); });
  splide.mount();
})();

/* Detail page collection carousel */
(function () {
  if (typeof window.Splide === 'undefined') return;

  document.querySelectorAll('.streamhive-js-collection-splide').forEach(function (carousel) {
    if (carousel.classList.contains('is-mounted')) return;

    const splide = new Splide(carousel, {
      type: 'slide',
      autoWidth: true,
      perMove: 1,
      gap: '1rem',
      focus: 0,
      trimSpace: false,
      pagination: false,
      arrows: true,
      drag: true,
      keyboard: true,
      waitForTransition: false,
      flickPower: 450,
      breakpoints: {
        575: { gap: '.75rem' }
      }
    });

    const updateCollectionOverflow = function () {
      const track = carousel.querySelector('.splide__track');
      const list = carousel.querySelector('.splide__list');
      if (!track || !list) return;
      const isOverflowing = list.scrollWidth > track.clientWidth + 2;
      carousel.classList.toggle('streamhive-is-collection-overflowing', isOverflowing);
    };

    splide.on('mounted resized updated refresh', updateCollectionOverflow);
    splide.mount();
    window.setTimeout(updateCollectionOverflow, 80);
    carousel.classList.add('is-mounted');
  });
})();
