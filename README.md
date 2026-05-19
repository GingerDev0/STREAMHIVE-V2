# MDBV2 — Movie & TV Database

A fast, cinematic PHP movie database powered by **TMDB**, **SQLite**, and a premium streaming-style UI.

MDBV2 lets you browse movies, TV shows, seasons, episodes, actors, recommendations, profiles, and admin imports from a lightweight PHP app. Missing content is fetched from TMDB on demand, cached locally in SQLite, and served quickly from then on.

> This product uses the TMDB API but is not endorsed or certified by TMDB.

---

## Highlights

- **Streaming-app UI** inspired by modern premium services.
- **SQLite-first storage** with automatic schema setup and local caching.
- **On-demand TMDB fetching** for missing movies, TV shows, episodes, seasons, and actors.
- **Fetching content modal** that blocks navigation while missing records are imported.
- **Fast listing pages** with SQL-level filtering, sorting, search, and pagination.
- **AJAX-powered `/movies`, `/tv`, and `/s` pages** with top and bottom pagination.
- **Live navbar search** capped at 6 clickable local results.
- **Movie, TV, episode, actor, season, search, profile, and admin pages**.
- **More like this recommendations** ranked by similar titles first, then shared genres.
- **Runtime display** such as `1 hour 30 mins`.
- **MultiEmbed player integration** using TMDB IDs first, with IMDb fallback.
- **Browser-local profile page** for bookmarks and recently viewed items.
- **Admin dashboard** for imports, database stats, and content management.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.1+ |
| Database | SQLite via PDO |
| Frontend | HTML, CSS, JavaScript, jQuery |
| API | TMDB |
| Web Server | Apache with `mod_rewrite`, or PHP built-in server |
| Player Embed | MultiEmbed |

---

## Requirements

Before installing, make sure your environment has:

- PHP **8.1 or newer**
- Apache with `mod_rewrite`, or PHP's built-in server for local development
- PHP extensions:
  - `curl`
  - `pdo_sqlite`
  - `sqlite3`
- A TMDB API credential:
  - TMDB v4 Read Access Token, recommended
  - or TMDB v3 API key as fallback

---

## Installation

Clone or upload the project, then configure your environment.

```bash
git clone https://github.com/your-username/your-repo-name.git
cd your-repo-name
cp .env.example .env
chmod -R 775 storage
```

Then edit `.env` and add your TMDB credentials.

For Apache hosting, point your web root to:

```text
public/
```

If your host does not allow changing the web root, keep the included root `.htaccess`. It forwards requests into `public/`.

---

## Environment Setup

Your `.env` should look like this:

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

### Important `.env` notes

- Do **not** commit your real `.env` file.
- Leave `SQLITE_PATH=` blank to use the default SQLite database path.
- Change `ADMIN_TOKEN` before deploying.
- Set `APP_DEBUG=false` in production.
- `SQLITE_JOURNAL_MODE=MEMORY` avoids slow `database.sqlite-wal` and `database.sqlite-journal` sidecar writes on hosts where disk sidecar files are slow.

For safer but slower SQLite writes, use:

```ini
SQLITE_JOURNAL_MODE=DELETE
SQLITE_SYNCHRONOUS=FULL
```

---

## TMDB Credentials

MDBV2 works best with a TMDB v4 Read Access Token.

1. Create or log into a TMDB account.
2. Open your TMDB account settings.
3. Go to the API section.
4. Request or create API access.
5. Copy the **v4 Read Access Token** into `TMDB_BEARER_TOKEN`.
6. Optionally add the v3 API key to `TMDB_API_KEY`.

Paste only the token value. Do not include the word `Bearer`; the app adds the authorization header automatically.

---

## Local Development

Run the app locally with PHP's built-in server:

```bash
php -S localhost:8000 -t public
```

Then open:

```text
http://localhost:8000
```

---

## Main Routes

| Route | Description |
|---|---|
| `/` | Home page |
| `/movies` | Movie listings |
| `/movies/movie-name` | Movie detail page |
| `/tv` | TV show listings |
| `/tv/show-name` | TV show detail page |
| `/tv/show-name/s01` | Season page |
| `/tv/show-name/s01/e01` | Episode page |
| `/actors` | Actor listings |
| `/actors/actor-name` | Actor detail page |
| `/actor/actor-name` | Actor detail alias |
| `/coming-this-year` | Upcoming/current-year content |
| `/s` | Search page |
| `/s?q=query` | Search results |
| `/profile` | Local profile, bookmarks, and history |

---

## Admin Routes

Admin routes use your `ADMIN_TOKEN` as a query string value.

```text
/admin?token=change-this-token
/admin/import?token=change-this-token
/admin/manage/movies?token=change-this-token
/admin/manage/tv?token=change-this-token
/admin/manage/actors?token=change-this-token
```

The admin area includes:

- SQLite stats
- Import tools
- Movie, TV, and actor management
- Filtering and sorting
- Pagination
- Preview links
- Delete actions

For a public production site, replace query-string token access with proper authentication.

---

## SQLite Storage

By default, MDBV2 stores its database at:

```text
storage/database.sqlite
```

You can override this with:

```ini
SQLITE_PATH=/absolute/path/to/database.sqlite
```

SQLite setup is automatic. The app creates the required tables, indexes, and derived search/filter columns when needed.

### Performance features

MDBV2 is tuned for a single-writer SQLite cache workflow:

- SQL-level pagination instead of loading every row.
- SQL-level search, year, rating, genre, and sort filters.
- Batch upserts wrapped in transactions.
- Reused prepared statements for multiple writes.
- Indexed slug lookups.
- Avoids rewriting unchanged records.
- Read-after-write verification before redirecting after imports.

---

## Fetching Missing Content

When a user opens content that is not fully available locally, MDBV2 shows a non-dismissible **Fetching content** modal.

The app then:

1. Requests the missing data from TMDB.
2. Saves or updates the record in SQLite.
3. Verifies the saved record is readable.
4. Automatically redirects to the finished page.

This prevents broken pages and avoids users manually refreshing after imports.

---

## Search and Filtering

MDBV2 supports:

- Movie, TV, actor, and combined search
- Live navbar search capped to 6 local results
- AJAX listing updates without full reloads
- Genre filters
- Year dropdowns
- Age rating filters
- Sort order controls
- Top and bottom pagination
- Browser back/forward support for AJAX listing changes

---

## Player Embeds

MDBV2 uses MultiEmbed for playback links.

Movies prefer TMDB IDs:

```text
https://multiembed.mov/?video_id={tmdb_id}&tmdb=1
```

Episodes use the parent TV TMDB ID with season and episode numbers:

```text
https://multiembed.mov/?video_id={tv_tmdb_id}&tmdb=1&s={season_number}&e={episode_number}
```

If a TMDB ID is unavailable, the app can fall back to IMDb ID URLs.

---

## Recommendations

The **More like this** section is ranked by:

1. Similar title or name
2. Shared genres
3. Rating and release date fallback

The panel is styled separately from the player and uses its own scrolling behavior on larger screens.

---

## Profile Page

The `/profile` page uses the visitor's browser `localStorage`.

It includes:

- Bookmarked items
- Recently viewed items
- One history entry per item
- Latest visit moves an item back to the front

No account system is required.

---

## Migrating Older JSON Storage

If you have an older version that used JSON folders such as:

```text
storage/movies
storage/tv
storage/people
```

run:

```bash
php scripts/migrate-json-to-sqlite.php
```

After confirming the admin dashboard shows the imported records, you can keep the old JSON files as backup or remove them.

---

## Recommended GitHub Files

Commit these:

```text
app/
public/
scripts/
storage/.gitkeep
storage/cache/.gitkeep
storage/indexes/.gitkeep
storage/movies/.gitkeep
storage/people/.gitkeep
storage/tv/.gitkeep
.env.example
.gitignore
.htaccess
README.md
```

Do **not** commit these:

```text
.env
storage/database.sqlite
database.sqlite
*.sqlite-wal
*.sqlite-shm
*.sqlite-journal
storage/cache/*
storage/movies/*.json
storage/tv/*.json
storage/people/*.json
```

---

## Production Checklist

Before going live:

- Set `APP_ENV=production`.
- Set `APP_DEBUG=false`.
- Replace `ADMIN_TOKEN` with a strong secret.
- Make sure Apache rewrite rules are enabled.
- Point the web root to `public/`.
- Make sure `storage/` is writable by PHP.
- Keep `.env` outside version control.
- Consider replacing token-based admin access with real authentication.

---

## Troubleshooting

### Pages show 404

Make sure Apache rewrite rules are enabled and that either:

- your web root points to `public/`, or
- the root `.htaccess` file is present.

### SQLite database is not created

Check that PHP can write to `storage/`:

```bash
chmod -R 775 storage
```

### Fetching content is slow

Use the faster SQLite settings:

```ini
SQLITE_JOURNAL_MODE=MEMORY
SQLITE_SYNCHRONOUS=NORMAL
SQLITE_BUSY_TIMEOUT_MS=10000
```

Also make sure your server is not blocking outbound requests to TMDB.

### Admin routes do not work

Check that the `token` query string matches your `.env` value:

```text
/admin?token=your-admin-token
```

---

## Credits

- Metadata provided by TMDB.
- Player embeds powered by MultiEmbed.
- UI and application code built for MDBV2.

---

## Disclaimer

MDBV2 is a personal movie and TV database/cache application. Make sure your usage of third-party APIs, metadata, images, and embeds complies with their respective terms of service.
