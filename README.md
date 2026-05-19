# StreamHIVE V2

<p align="center">
  <a href="https://streamhive.uk/"><img src="https://img.shields.io/badge/Demo-StreamHIVE-7c3aed?style=for-the-badge" alt="StreamHIVE Demo"></a>
  <a href="https://github.com/GingerDev0/StreamHIVE-V2"><img src="https://img.shields.io/badge/GitHub-StreamHIVE--V2-111827?style=for-the-badge&logo=github" alt="GitHub Repository"></a>
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777bb4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.1+">
  <img src="https://img.shields.io/badge/Storage-SQLite-003b57?style=for-the-badge&logo=sqlite&logoColor=white" alt="SQLite">
  <img src="https://img.shields.io/badge/API-TMDB-01b4e4?style=for-the-badge" alt="TMDB API">
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" alt="MIT License">
</p>

<p align="center">
  <strong>A cinematic PHP movie, TV, episode, and actor database powered by TMDB and SQLite.</strong>
</p>

<p align="center">
  <a href="https://streamhive.uk/"><strong>View the StreamHIVE demo</strong></a>
  ·
  <a href="https://github.com/GingerDev0/StreamHIVE-V2"><strong>GitHub repository</strong></a>
</p>

---

## Overview

StreamHIVE V2 is a streaming-app style media database built with PHP, SQLite, TMDB data, Apache routes, AJAX browsing, local profile storage, and a fast local import cache.

The app fetches missing or incomplete movie, TV, episode, season, and actor records from TMDB on demand, stores them locally in SQLite, and then serves detail/listing pages from the local cache for speed.

> This product uses the TMDB API but is not endorsed or certified by TMDB.

---

## Demo

| Name | URL |
|---|---|
| **StreamHIVE** | [https://streamhive.uk/](https://streamhive.uk/) |

---

## Features

| Area | Details |
|---|---|
| **Browsing** | Movie, TV, season, episode, actor, search, profile, and admin pages. |
| **Storage** | SQLite-first cache with automatic TMDB imports, upgrades, and backfills. |
| **Search** | Live navbar search, full search pages, filters, sorting, genre/year/age-rating/user-rating support, and AJAX pagination. |
| **Detail pages** | Metadata, cast, ratings, genres, runtime, seasons, episodes, recommendations, and collection support. |
| **Collections** | Full-width **Movies In This Collection** carousel with collection backdrop and index-style movie cards. |
| **Recommendations** | “More like this” panels ranked by similar titles first, then shared genres. |
| **Player** | MultiEmbed support using TMDB IDs first, with IMDb IDs as fallback where available. |
| **Profile** | Browser-local bookmarks and recently viewed history using `localStorage`. |
| **Admin** | Import tools, prefetched full-import actions, SQLite stats, and movie/TV/actor management. |
| **SEO** | Open Graph and Twitter card metadata. |
| **Slugs** | Clean collision-safe slugs for duplicate titles and actor names. |

---

## Requirements

| Requirement | Version / Notes |
|---|---|
| PHP | `8.1` or newer |
| Server | Apache with `mod_rewrite`, or PHP's built-in server for local development |
| Database | SQLite |
| PHP extensions | `curl`, `pdo_sqlite`, `sqlite3` |
| API credentials | TMDB v4 Read Access Token, or TMDB v3 API key |

---

## Installation

1. Clone or upload the repository to your server.
2. Point your web root to `public/`.
3. If you cannot change the web root, keep the included root `.htaccess`; it routes requests into `public/`.
4. Copy `.env.example` to `.env`.
5. Add your TMDB credentials and change the admin token.
6. Make `storage/` writable by PHP.
7. Open the site in your browser.

```bash
cp .env.example .env
chmod -R 775 storage
```

---

## Environment file

Create a `.env` file in the project root:

```ini
TMDB_BEARER_TOKEN=your_tmdb_v4_read_access_token_here
TMDB_API_KEY=your_tmdb_v3_api_key_here

APP_ENV=local
APP_DEBUG=true
ADMIN_TOKEN=change-this-token

SQLITE_PATH=

SQLITE_JOURNAL_MODE=MEMORY
SQLITE_SYNCHRONOUS=NORMAL
SQLITE_BUSY_TIMEOUT_MS=10000
```

| Setting | Description |
|---|---|
| `TMDB_BEARER_TOKEN` | Preferred TMDB v4 Read Access Token. Paste the token without the word `Bearer`. |
| `TMDB_API_KEY` | Optional TMDB v3 API key fallback. |
| `APP_ENV` | Use `local` for development and `production` for live sites. |
| `APP_DEBUG` | Use `true` locally and `false` in production. |
| `ADMIN_TOKEN` | Query-string token for admin routes. Change this before deployment. |
| `SQLITE_PATH` | Leave blank to use `storage/database.sqlite`, or provide an absolute path. |
| `SQLITE_JOURNAL_MODE` | `MEMORY` avoids slow sidecar writes on some shared hosts. |
| `SQLITE_SYNCHRONOUS` | `NORMAL` is faster; `FULL` is safer but slower. |
| `SQLITE_BUSY_TIMEOUT_MS` | SQLite wait time when the database is busy. |

---

## TMDB credentials

1. Create or log in to a TMDB account.
2. Open account settings.
3. Go to the API section.
4. Create or request API access.
5. Copy the v4 API Read Access Token into `TMDB_BEARER_TOKEN`.
6. Optionally copy the v3 API key into `TMDB_API_KEY` as a fallback.

---

## Local development

From the project root:

```bash
php -S localhost:8000 -t public
```

Then open:

```text
http://localhost:8000
```

---

## Main routes

| Route | Description |
|---|---|
| `/` | Home page |
| `/movies` | Movie listing |
| `/movies/movie-name` | Movie detail page |
| `/tv` | TV listing |
| `/tv/tv-show-name` | TV show detail page |
| `/tv/tv-show-name/s01` | Season detail page |
| `/tv/tv-show-name/s01/e01` | Episode detail page |
| `/actors` | Actor listing |
| `/actors/actor-name` | Actor detail page |
| `/actor/actor-name` | Actor detail alias |
| `/coming-this-year` | Upcoming movies and TV shows for the year |
| `/s` | Search page |
| `/s/search-query` | Search by path |
| `/s?q=search-query` | Search by query string |
| `/profile` | Local bookmarks and recently viewed items |

`/coming-this-year` is the exception to the global released-only filter, so upcoming movies and TV shows can still be listed there.

---

## Admin routes

Use your `ADMIN_TOKEN` query-string value:

| Route | Description |
|---|---|
| `/admin?token=change-this-token` | Admin dashboard |
| `/admin/import?token=change-this-token` | Import tools |
| `/admin/manage/movies?token=change-this-token` | Manage movies |
| `/admin/manage/tv?token=change-this-token` | Manage TV shows |
| `/admin/manage/actors?token=change-this-token` | Manage actors |

The admin area includes stats, imports, prefetched full-import actions, filters, sorting, previews, pagination, and delete actions.

For a public production site, replace the query-string token with proper authentication.

---

## SQLite storage

By default, StreamHIVE creates this database:

```text
storage/database.sqlite
```

You can override it in `.env`:

```ini
SQLITE_PATH=/absolute/path/to/database.sqlite
```

SQLite schema and derived search/filter columns are created automatically. Existing rows are backfilled when needed.

| Optimization | Purpose |
|---|---|
| Transactions | Faster batch upserts. |
| Prepared statements | Reused database statements. |
| Indexed slug checks | Collision-safe slugs with quick lookups. |
| SQL pagination | Pages query only the rows needed for the current page. |
| Verified imports | Missing content is fetched, saved, verified as readable, then redirected. |

---

## Content visibility rules

Across public browsing and detail pages, StreamHIVE hides media that should not appear in the main catalogue yet.

| Media type | Hidden when missing date | Hidden when future dated | Exception |
|---|---:|---:|---|
| Movies | Yes | Yes | `/coming-this-year` allows upcoming movies |
| TV shows | Yes | Yes | `/coming-this-year` allows upcoming TV shows |
| Seasons | Yes | Yes | None |
| Episodes | Yes | Yes | None |
| Actors | No | No | Actors are not filtered by release dates |

---

## Movie collections

Movie detail pages can show a full-width **Movies In This Collection** section when TMDB returns collection data for the movie.

| Collection feature | Behaviour |
|---|---|
| Placement | Appears above the cast/recommended row. |
| Background | Uses the TMDB collection backdrop. |
| Cards | Uses the same movie card format as index/listing pages. |
| Carousel | Uses Splide with normal scrolling and no elastic re-centering. |
| Arrows | Previous and next buttons sit at the far left and far right of the section. |
| Filtering | Future-dated and undated movies are excluded. |

---

## Player embeds

Movies use MultiEmbed with the TMDB ID first:

```text
https://multiembed.mov/?video_id={tmdb_id}&tmdb=1
```

Episodes use:

```text
https://multiembed.mov/?video_id={tv_tmdb_id}&tmdb=1&s={season}&e={episode}
```

If a TMDB ID is unavailable, the app can fall back to IMDb ID URLs.

---

## Auto-import and fetching modal

When a visitor opens a movie, TV show, episode, season, or actor that is missing locally, the app shows a non-dismissible **Fetching content** modal.

| Step | What happens |
|---|---|
| 1 | Visitor opens missing or partial content. |
| 2 | StreamHIVE shows the fetching modal. |
| 3 | The record is imported or upgraded from TMDB. |
| 4 | The data is written to SQLite. |
| 5 | StreamHIVE verifies the record is readable. |
| 6 | The visitor is automatically navigated to the completed page. |

Fully imported local records open immediately.

---

## Search and filtering

| Feature | Supported |
|---|---:|
| Search by title/name | Yes |
| Movies, TV, actors, or combined search | Yes |
| Genre filters | Yes |
| Year filters | Yes |
| Age-rating filters | Yes |
| User rating filters | Yes, separate from age rating, in `0.5` increments from `0+` to `10+`. |
| Sort order | Yes |
| Top and bottom pagination | Yes |
| AJAX listing/search updates | Yes |
| Navbar live search capped to 6 local results | Yes |

---

## Profile page

`/profile` is stored in the visitor's browser with `localStorage`.

| Profile area | Details |
|---|---|
| Bookmarks | Saved movies, TV shows, and actors. |
| Recently viewed | One history entry per item. Opening the same item again moves it to the front. |
| Accounts | No account system required. |

---

## Migrating old JSON storage

If you have older JSON folders such as `storage/movies`, `storage/tv`, or `storage/people`, the app can migrate them into SQLite.

```bash
php scripts/migrate-json-to-sqlite.php
```

After confirming the admin dashboard shows the imported records, the JSON files can be removed or kept as a backup.

---

## GitHub packaging notes

This repository should include source files, `.env.example`, `.gitignore`, `.htaccess` files, and `.gitkeep` files for empty storage folders.

Do not commit:

| File / folder | Reason |
|---|---|
| `.env` | Contains private credentials. |
| `storage/database.sqlite` | Runtime database. |
| `database.sqlite` | Runtime database. |
| `*.sqlite-wal` | SQLite sidecar file. |
| `*.sqlite-shm` | SQLite sidecar file. |
| `*.sqlite-journal` | SQLite sidecar file. |
| `storage/cache` | Runtime cache/import files. |
| `storage/movies` | Legacy/runtime imported content. |
| `storage/tv` | Legacy/runtime imported content. |
| `storage/people` | Legacy/runtime imported content. |

---

## Attribution guard

This build includes a transparent runtime attribution guard. The app checks `app/Views/layouts/app.php` on boot and returns a clear `503 Project attribution required` page if the visible footer attribution is removed or changed.

The footer must keep:

```text
Created by GingerDev
TMDB data powers imports. This product is not endorsed or certified by TMDB.
Project link: https://github.com/GingerDev0/StreamHIVE-V2
```

The visible footer attribution is checked strictly. Changing the creator text, TMDB notice, project-link text, project URL, or removing the GitHub footer navigation link will make the app return `503 Project attribution required`.

Restore the exact required footer attribution to make the site load again.

---

## License

This project is licensed under the MIT License.

```text
MIT License

Copyright (c) 2026 GingerDev

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell  
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:  

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.  

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,  
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE  
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER  
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,  
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE  
SOFTWARE.
```

---

## Production notes

| Task | Recommendation |
|---|---|
| Environment | Set `APP_ENV=production`. |
| Debugging | Set `APP_DEBUG=false`. |
| Admin security | Change `ADMIN_TOKEN` to a strong secret or replace token auth with real authentication. |
| Rewrites | Ensure Apache rewrite rules are enabled. |
| Storage | Ensure `storage/` is writable but not publicly browseable. |
| Secrets | Keep TMDB credentials private. |

---

## Credits

| Credit | Source |
|---|---|
| Metadata and images | TMDB API |
| Player embed format | MultiEmbed |
| UI libraries | Bootstrap 5.3.3, Font Awesome, Splide |
| Storage | SQLite |
| License | MIT |
| Created by | [GingerDev](https://github.com/GingerDev0) |

---

<p align="center">
  <strong>StreamHIVE V2</strong><br>
  <a href="https://streamhive.uk/">Demo</a> · <a href="https://github.com/GingerDev0/StreamHIVE-V2">Repository</a>
</p>
