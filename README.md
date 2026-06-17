# StreamHIVE V2

![StreamHIVE V2 preview](public/assets/img/logo.png)

**StreamHIVE V2** is a live TMDB-powered movie and TV discovery app built in PHP. It delivers a polished streaming-service interface with cinematic detail pages, live search, actor profiles, recommendations, watchlist-style browser profiles, upcoming-release discovery, and configurable embed-player routing.

This version is intentionally **live-only**. It does not require MySQL, SQLite, admin imports, or a local media catalogue. Pages resolve against TMDB at request time, keeping the codebase lightweight and easy to deploy.

> This product uses the TMDB API but is not endorsed, certified, or otherwise approved by TMDB.

---

## Highlights

- **Live TMDB data** for movies, TV shows, seasons, episodes, actors, cast, crew, collections, recommendations, ratings, posters, and backdrops.
- **No database required**. No schema, no imports, no catalogue maintenance.
- **Premium streaming UI** with dark cinematic layouts, poster grids, backdrop heroes, responsive cards, and Font Awesome icons.
- **Movie and TV detail pages** with inline player panels, metadata, genres, cast and crew, collections, seasons, episodes, and related titles.
- **Coming This Year** page with English-language upcoming movie and TV results, client-side pagination, and click-to-open detail modals.
- **Live search and discovery** across movies, shows, actors, genres, years, ratings, and user score filters.
- **Browser-local profile features** using `localStorage` for saved titles and recently viewed content.
- **SEO-friendly routes** with clean URLs and year-aware movie slugs for duplicate titles.
- **Configurable embed provider** with built-in presets and custom URL template support.

---

## Tech Stack

| Layer | Details |
|---|---|
| Runtime | PHP 8.1+ |
| Routing | Custom lightweight PHP router |
| Data source | TMDB API |
| Frontend | Server-rendered PHP views, Bootstrap, jQuery, Splide, Font Awesome |
| Persistence | None for catalogue data; browser `localStorage` for profile data |
| Web server | Apache/XAMPP with `mod_rewrite`, or PHP built-in server |

---

## Requirements

- PHP 8.1 or newer
- PHP `curl` extension recommended
- Apache with `mod_rewrite`, XAMPP, or PHP built-in server
- TMDB credentials:
  - v4 Read Access Token preferred
  - v3 API key supported as fallback

No database server is required.

---

## Quick Start

Clone the repository:

```bash
git clone https://github.com/GingerDev0/STREAMHIVE-V2.git
cd STREAMHIVE-V2
```

Create your local environment file:

```bash
cp .env.example .env
```

Add your TMDB credentials:

```ini
TMDB_BEARER_TOKEN=
TMDB_API_KEY=

APP_ENV=local
APP_DEBUG=true

PLAYER_PROVIDER=multiembed
PLAYER_MOVIE_URL=
PLAYER_EPISODE_URL=
```

`TMDB_BEARER_TOKEN` is preferred. Keep `.env` private and never commit real credentials.

---

## Running Locally

### XAMPP / Apache

Place the project in:

```text
C:\xampp\htdocs
```

Make sure Apache rewrite support is enabled. The root `.htaccess` forwards requests into `public/`, and `public/index.php` handles routing.

Open:

```text
http://127.0.0.1/
```

### PHP Built-In Server

```bash
php -S 127.0.0.1:8000 -t public
```

Open:

```text
http://127.0.0.1:8000/
```

---

## Core Routes

| Route | Description |
|---|---|
| `/` | Home page |
| `/movies` | Movie discovery |
| `/movies/{slug}` | Movie detail |
| `/tv` | TV discovery |
| `/tv/{slug}` | TV show detail |
| `/tv/{slug}/s01` | Season detail |
| `/tv/{slug}/s01/e01` | Episode detail |
| `/actors` | Actor discovery |
| `/actors/{slug}` | Actor profile |
| `/actor/{slug}` | Actor profile alias |
| `/coming-this-year` | Upcoming English-language movies and TV for the current year |
| `/s` | Search and filter page |
| `/s/{query}` | Search page with path query |
| `/profile` | Browser-local saved and recent titles |
| `/ajax/live-search` | Navbar live-search endpoint |

---

## Feature Overview

### Discovery

StreamHIVE V2 resolves listings directly from TMDB discover/search endpoints. Users can browse movies, TV shows, actors, genres, release years, age ratings, score filters, and live search results without needing an imported local catalogue.

### Detail Pages

Movie, TV, season, episode, and actor pages are assembled from live TMDB responses. Detail pages include posters, backdrops, metadata, ratings, genres, overview text, cast and crew, recommendations, and route-aware canonical URLs.

### Coming This Year

The upcoming page fetches every available TMDB discover page for the remaining current-year date range, filters to English-original titles, removes duplicates, and renders tabbed movie/TV grids. A short-lived runtime cache avoids repeating the full TMDB page sweep on every request. Each card opens a high-layer Bootstrap modal with poster, backdrop, rating, release date, genres, and overview.

### Profile

Saved titles and recent history are stored in the browser with `localStorage`. No account system or server-side profile storage is required.

### Player Routing

The app builds configurable movie and episode embed URLs from TMDB and IMDb identifiers. The default provider is MultiEmbed-compatible, with optional presets and fully custom URL templates.

---

## URL Strategy

Movie slugs include the release year when TMDB provides one. This reduces collisions when multiple movies share a title:

```text
Scary Movie (2000) -> /movies/scary-movie-2000
Scary Movie (2026) -> /movies/scary-movie-2026
```

TV and episode routes use the show slug plus season and episode numbers:

```text
/tv/from
/tv/from/s01
/tv/from/s01/e01
```

The controller resolves slugs back to the closest TMDB result at request time.

---

## Data Model

TMDB is the source of truth. The app fetches live data for:

- Movies and TV shows
- Seasons and episodes
- Actors and filmographies
- Cast and crew
- Recommendations and similar titles
- Movie collections
- Posters, profile images, and backdrops
- Ratings, runtimes, certifications, dates, genres, and external IDs

The app does not:

- Create a database schema
- Persist movie or TV records
- Maintain a permanent local API cache
- Run admin imports
- Host or distribute video files

---

## Environment Variables

| Variable | Required | Description |
|---|---:|---|
| `TMDB_BEARER_TOKEN` | Recommended | TMDB v4 Read Access Token |
| `TMDB_API_KEY` | Optional | TMDB v3 API key fallback |
| `APP_ENV` | Optional | `local` or `production` |
| `APP_DEBUG` | Optional | Enable PHP error output locally |
| `PLAYER_PROVIDER` | Optional | Built-in provider: `multiembed`, `vidsrc-to`, `vidsrc-cc`, or `embed-su` |
| `PLAYER_MOVIE_URL` | Optional | Custom movie embed URL template |
| `PLAYER_EPISODE_URL` | Optional | Custom episode embed URL template |

Custom player templates support:

| Placeholder | Meaning |
|---|---|
| `{video_id}` | TMDB ID when available, otherwise IMDb ID |
| `{tmdb_id}` | TMDB ID only |
| `{imdb_id}` | IMDb ID only |
| `{tmdb_flag}` | `1` when `{video_id}` is TMDB, otherwise `0` |
| `{season}` | Episode season number |
| `{episode}` | Episode number |

---

## Project Structure

```text
app/
  Controllers/   Request handlers for pages and AJAX endpoints
  Core/          Router, config loader, and view renderer
  Helpers/       URL, slug, image, rating, player, and formatting helpers
  Models/        Repository compatibility layer
  Services/      TMDB client and live catalogue abstractions
  Views/         Layouts, pages, and reusable partials

public/
  assets/        CSS, JavaScript, images, favicon, and metadata image
  index.php      Front controller

.env.example     Safe environment template
.htaccess        Apache rewrite entry point
README.md        Project documentation
LICENSE          MIT license
```

Important files:

| File | Purpose |
|---|---|
| `public/index.php` | Route table and front controller |
| `app/Controllers/MediaController.php` | Movie, TV, season, episode, listing, search, and upcoming logic |
| `app/Controllers/ActorController.php` | Actor pages and filmographies |
| `app/Services/TmdbClient.php` | TMDB API wrapper and batched request helper |
| `app/Services/LiveCatalog.php` | Live-only compatibility layer |
| `app/Helpers/helpers.php` | Shared formatting, slug, image, rating, and player helpers |
| `public/assets/js/app.js` | Client-side search, pagination, modals, profile state, and UI behavior |
| `public/assets/css/app.css` | Full visual system and responsive styling |

---

## Deployment Checklist

Before deploying publicly:

- Set `APP_ENV=production`.
- Set `APP_DEBUG=false`.
- Keep `.env` private.
- Confirm TMDB credentials are valid and not committed.
- Confirm rewrite rules route all requests through `public/index.php`.
- Confirm outbound HTTPS requests from PHP are allowed.
- Review third-party embed providers for legal and terms-of-service compliance.
- Rotate credentials immediately if `.env` is ever exposed.

---

## Security And Content Notes

StreamHIVE V2 does not host video files. It only builds configurable embed URLs and displays metadata from TMDB. Anyone deploying or modifying this project is responsible for ensuring that enabled providers, embeds, sources, and links comply with applicable law and third-party terms.

Do not commit:

- `.env`
- API tokens or credentials
- Runtime caches
- Local databases
- Logs
- Editor or OS files

---

## Troubleshooting

### Pages return 404

- The TMDB item could not be resolved from the slug.
- The item is unreleased or missing a valid release/air date.
- Apache rewrite rules are disabled.
- Requests are not reaching `public/index.php`.

### TMDB requests fail

- Check `.env`.
- Confirm your TMDB token or API key is valid.
- Confirm PHP can make outbound HTTPS requests.
- Confirm `curl` is enabled if your environment requires it.

### Images are missing

- TMDB may not have a usable poster, profile image, or backdrop for that item.
- Remote image requests may be blocked by the network or browser.
- Some grids intentionally filter out titles without usable posters.

### Recommendations show fewer than expected

TMDB may return fewer usable released recommendations with posters. StreamHIVE filters future, undated, duplicate, and posterless results where appropriate.

---

## Credits

| Credit | Source |
|---|---|
| Metadata and imagery | TMDB API |
| Icons | Font Awesome |
| Carousel UI | Splide |
| UI framework | Bootstrap |
| Original project | GingerDev0 / StreamHIVE V2 |

---

## License

StreamHIVE V2 is released under the MIT License. See [LICENSE](LICENSE) for details.
