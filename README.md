# StreamHIVE

A cinematic PHP movie, TV, episode, and actor database powered by the TMDB API and local SQLite storage. The app keeps a fast local cache in SQLite, fetches missing content from TMDB on demand, and uses clean Apache routes for a streaming-app style browsing experience.

> This product uses the TMDB API but is not endorsed or certified by TMDB.

## Features

- Netflix-style dark UI with movie, TV, season, episode, actor, search, profile, and admin pages.
- SQLite-first storage with automatic TMDB imports and upgrades.
- Fast SQL-level pagination, filtering, sorting, and search for listing pages.
- Live navbar search with up to 6 clickable local results.
- AJAX filtering and pagination on `/movies`, `/tv`, and `/s`.
- Blocking fetching-content modal for missing or partially imported content.
- Movie, TV, episode, and actor metadata, cast, ratings, genres, seasons, and recommendations.
- “More like this” recommendations ranked by similar names first, then shared genres.
- Runtime display such as `1 hour 30 mins` on movie, TV, episode, and card views where available.
- MultiEmbed player support using TMDB IDs first, then IMDb IDs as fallback.
- Browser-local profile page using `localStorage` for bookmarks and recently viewed items.
- Admin dashboard for imports, SQLite stats, and managing movies, TV shows, and actors.
- Clean collision-safe slugs for duplicate titles/names.
- SEO/social metadata with Open Graph and Twitter card tags.

## Requirements

- PHP 8.1 or newer
- Apache with `mod_rewrite`, or PHP's built-in server for local development
- PHP extensions:
  - `curl`
  - `pdo_sqlite`
  - `sqlite3`
- A TMDB API v4 Read Access Token, or a TMDB v3 API key

## Installation

1. Clone or upload this repository to your server.
2. Point your web root to `public/`.
   - If you cannot change the web root, keep the included root `.htaccess`; it routes requests into `public/`.
3. Copy `.env.example` to `.env`.
4. Add your TMDB credentials and change the admin token.
5. Make `storage/` writable by PHP.
6. Open the site in your browser.

Example:

```bash
cp .env.example .env
chmod -R 775 storage
```

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

Notes:

- Do not commit your real `.env` file.
- Leave `SQLITE_PATH=` blank to use `storage/database.sqlite`.
- `SQLITE_JOURNAL_MODE=MEMORY` avoids slow `database.sqlite-wal` and `database.sqlite-journal` sidecar writes on hosts where those files are slow.
- For a more crash-safe but slower setup, use `SQLITE_JOURNAL_MODE=DELETE` and `SQLITE_SYNCHRONOUS=FULL`.
- In production, set `APP_DEBUG=false`.

## TMDB credentials

This app prefers the TMDB v4 API Read Access Token.

1. Create or log in to a TMDB account.
2. Open account settings.
3. Go to the API section.
4. Create/request API access.
5. Copy the v4 API Read Access Token into `TMDB_BEARER_TOKEN`.
6. Optionally copy the v3 API key into `TMDB_API_KEY` as a fallback.

Paste the bearer token without the word `Bearer`; the app adds the header automatically.

## Local development

From the project root:

```bash
php -S localhost:8000 -t public
```

Then open:

```text
http://localhost:8000
```

## Main routes

```text
/
/movies
/movies/movie-name
/tv
/tv/tv-show-name
/tv/tv-show-name/s01
/tv/tv-show-name/s01/e01
/actors
/actors/actor-name
/actor/actor-name
/coming-this-year
/s
/s/search-query
/s?q=search-query
/profile
```

## Admin routes

Use your `ADMIN_TOKEN` query string value:

```text
/admin?token=change-this-token
/admin/import?token=change-this-token
/admin/manage/movies?token=change-this-token
/admin/manage/tv?token=change-this-token
/admin/manage/actors?token=change-this-token
```

The admin area includes stats, imports, filters, sorting, previews, pagination, and delete actions. For a public production site, replace the query-string token with proper authentication.

## SQLite storage

By default, the app creates:

```text
storage/database.sqlite
```

You can override it in `.env`:

```ini
SQLITE_PATH=/absolute/path/to/database.sqlite
```

SQLite schema and derived search/filter columns are created automatically. Existing rows are backfilled when needed.

The app is tuned for a single-writer cache workflow:

- Batch upserts use transactions.
- Prepared statements are reused.
- Slug checks use indexed lookups.
- Pages query only the rows needed for the current page.
- Missing content is fetched, saved, verified as readable, then redirected.

## Player embeds

Movies use MultiEmbed with TMDB ID first:

```text
https://multiembed.mov/?video_id={tmdb_id}&tmdb=1
```

Episodes use:

```text
https://multiembed.mov/?video_id={tv_tmdb_id}&tmdb=1&s={season}&e={episode}
```

If a TMDB ID is unavailable, the app can fall back to IMDb ID URLs.

## Auto-import and fetching modal

When a visitor opens a movie, TV show, episode, season, or actor that is missing locally, the app shows a non-dismissible “Fetching content” modal. It imports or upgrades the record from TMDB, writes it to SQLite, waits until the record is readable, then navigates automatically.

Fully imported local records open immediately.

## Search and filtering

The app supports:

- Search by title/name
- Movies, TV, actors, or combined search
- Genre filters
- Year filters
- Age-rating filters
- Sort order
- Top and bottom pagination
- AJAX updates without full-page reloads on listings/search
- Navbar live search capped to 6 local results

## Profile page

`/profile` is stored in the visitor’s browser with `localStorage`.

It includes:

- Bookmarks
- Recently viewed items
- One history entry per item; opening it again moves it to the front

No account system is required.

## Migrating old JSON storage

If you have older JSON folders such as `storage/movies`, `storage/tv`, or `storage/people`, the app can migrate them into SQLite.

Manual migration:

```bash
php scripts/migrate-json-to-sqlite.php
```

After confirming the admin dashboard shows the imported records, the JSON files can be removed or kept as a backup.

## GitHub packaging notes

This repository should include source files, `.env.example`, `.gitignore`, `.htaccess` files, and `.gitkeep` files for empty storage folders.

Do not commit:

- `.env`
- `storage/database.sqlite`
- `database.sqlite`
- SQLite sidecar files such as `*.sqlite-wal`, `*.sqlite-shm`, or `*.sqlite-journal`
- Runtime cache/import files in `storage/cache`, `storage/movies`, `storage/tv`, or `storage/people`


## Attribution guard

This build includes a transparent runtime attribution guard. The app checks `app/Views/layouts/app.php` on boot and returns a clear `503 Project attribution required` page if the visible footer attribution is removed or changed.

The footer must keep:

```text
Created by GingerDev
TMDB data powers imports. This product is not endorsed or certified by TMDB.
Project link: https://github.com/GingerDev0/StreamHIVE-V2
```

The visible footer attribution is checked strictly: changing the creator text, TMDB notice, project-link text, project URL, or removing the GitHub footer navigation link will make the app return `503 Project attribution required`. Restore the exact required footer attribution to make the site load again.

## Production notes

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Change `ADMIN_TOKEN` to a strong secret or replace token auth with real authentication.
- Ensure Apache rewrite rules are enabled.
- Ensure `storage/` is writable but not publicly browseable.
- Keep TMDB credentials private.

## Credits

- Metadata and images: TMDB API
- Player embed format: MultiEmbed
- UI libraries: Bootstrap 5.3.3 and Font Awesome
- Storage: SQLite
- Created by [GingerDev](https://github.com/GingerDev0)
