# Movie DB JSON V2

A cinematic PHP movie and TV database powered by the TMDB API, clean Apache routes, and local JSON storage. No SQL database is required: movies, TV shows, actors, seasons, episodes, and indexes are saved into JSON shard files with a maximum of 100 records per file.

> Attribution: This product uses the TMDB API but is not endorsed or certified by TMDB.

## Current V2 features

- Modern streaming-style V2 interface with cinematic heroes, poster grids, polished detail pages, responsive cards, and custom pagination.
- Home page sections for recent/trending movies and recent/trending TV shows.
- Poster overlay cards on the index page, 6 per row on large screens, 12 items per section.
- Movie, TV show, season, episode, and actor pages.
- TV season pages that list all episodes.
- Episode pages include a next-episode card.
- Cast sections and actor profile pages with linked movie/TV credits.
- “More like this” recommendations beside cast sections.
- Genre links that open filtered search results.
- Pretty release dates, for example `16th July 2009`.
- Working local profile page using `localStorage`:
  - Continue Watching
  - Bookmarks
  - Recently Viewed
- Bookmark buttons on media cards using `localStorage`.
- Player embeds using Videasy and TMDB IDs:
  - Movies: `https://player.videasy.net/movie/{tmdb_id}`
  - Episodes: `https://player.videasy.net/tv/{tmdb_id}/{season}/{episode}`
- Coming This Year page with movie/TV tabs and jQuery pagination.
- Collision-safe clean URLs: duplicate movie, TV, and actor slugs automatically get `-2`, `-3`, etc.
- Admin V2 dashboard with better manage/import pages, stats cards, storage shard info, filters, sorting, pagination, previews, and delete actions.
- Proper per-page metadata, canonical URLs, Open Graph tags, Twitter card tags, and sensible robots rules for private/profile/admin pages.
- Footer credit link: Created by [GingerDev](https://github.com/GingerDev0).

## Routes

### Public routes

```text
/
/movies
/movies/movie-name
/tv
/tv/tv-show-name
/tv/tv-show-name/s01
/tv/tv-show-name/s01/e01
/actors/actor-name
/coming-this-year
/s/search+query
/s?q=search+query
/profile
```

### Admin routes

```text
/admin?token=change-this-token
/admin/import?token=change-this-token
/admin/manage/movies?token=change-this-token
/admin/manage/tv?token=change-this-token
/admin/manage/actors?token=change-this-token
```

Write/delete actions are protected by `ADMIN_TOKEN`.

## Search and browsing

Search supports movies, TV shows, and actors.

Available filters include:

- Type: Movies + TV, Movies, TV Shows, Actors
- Genre
- Age rating
- Year
- Sort order
- Pagination

Listing pages are available at:

```text
/movies
/tv
/coming-this-year
```

Pagination uses a compact control with a maximum of 5 visible page numbers:

```text
<< < 1 2 [3] 4 5 > >>
```

First, previous, next, and last links only appear when needed.

## Auto-import and prefetching

The app uses TMDB to keep local JSON data populated automatically.

- Homepage results are prefetched into local JSON before a user clicks them.
- Movie listing pages prefetch popular TMDB movies for the current page.
- TV listing pages prefetch popular TMDB TV shows for the current page.
- Search pages prefetch matching movies, TV shows, and actors before rendering local results.
- Detail pages auto-import or upgrade records when opened.
- Prefetched records are lightweight and marked with `import_status: "prefetched"`.
- Opening a detail page upgrades a prefetched record with full metadata, cast, ratings, seasons, external IDs, and related data.

This gives quick local browsing without attempting to crawl the entire TMDB catalogue in one request.


## Duplicate titles and clean URLs

The app now keeps clean URLs while avoiding collisions for movies, TV shows, and actors with the same title/name.

The first record keeps the normal slug:

```text
/movies/halloween
```

If another movie with the same title is imported later, it gets the next available suffix:

```text
/movies/halloween-2
/movies/halloween-3
```

The same rule applies to TV and actor pages:

```text
/tv/the-office
/tv/the-office-2
/actors/john-smith
/actors/john-smith-2
```

Slugs are assigned once when a record is first saved, stored permanently in JSON, and reused everywhere links are generated. This means links stay stable even if more duplicates are imported later.

## Local JSON storage

Storage lives in the `storage/` directory:

```text
storage/movies/*.json
storage/tv/*.json
storage/people/*.json
storage/indexes/*.json
```

Each shard is capped at 100 records per file.

Make sure the entire `storage/` directory is writable by PHP.

## Metadata saved from TMDB

The importer stores useful fields such as:

- TMDB ID
- IMDb ID where available
- Title/name
- Collision-safe permanent slug
- Overview
- Poster path
- Backdrop path
- Release/air date
- Vote average
- Genres
- Age rating/certification
- Cast
- Seasons and episodes for TV shows
- Actor credits

## IMDb and TMDB imports

The admin import page accepts:

- Full IMDb URLs
- IMDb title IDs such as `tt5433140`
- IMDb person IDs such as `nm0000138`
- TMDB movie/TV/person IDs when provided through the import UI

IMDb IDs are resolved through TMDB where supported.

## Requirements

- PHP 8.1 or newer
- Apache with `mod_rewrite`
- PHP cURL extension
- A TMDB API Read Access Token or TMDB API key

## Metadata and sharing

The layout generates sensible SEO/social metadata for every page:

- Standard `<title>` and meta description tags
- Canonical URLs
- Open Graph title, description, type, URL, and image tags
- Twitter summary-large-image card tags
- Movie, TV, season, episode, actor, listing, search, coming-soon, profile, 404, and admin-specific metadata
- Admin/profile/error pages use noindex-style robots rules where appropriate

Movie, TV, episode, season, and actor pages use TMDB backdrops/posters/profile images for share previews when available.


## How to get TMDB credentials

You need a free TMDB account and either a **v4 API Read Access Token** or a **v3 API key**. This project prefers the v4 read access token, but it can also use the v3 key if needed.

1. Create or log in to your TMDB account.
2. Open your TMDB account settings.
3. Go to the **API** section.
4. Request/register for API access if TMDB asks you to. Use a desktop browser because TMDB notes that API registration is not optimized for mobile.
5. After API access is approved/created, copy the **API Read Access Token**. This is the long v4 bearer token.
6. Paste it into your `.env` file:

```ini
TMDB_BEARER_TOKEN=your_v4_read_access_token_here
```

You may also copy the older v3 API key and add it as a fallback:

```ini
TMDB_API_KEY=your_v3_api_key_here
```

Recommended `.env` setup:

```ini
TMDB_BEARER_TOKEN=your_v4_read_access_token_here
TMDB_API_KEY=your_v3_api_key_here
APP_ENV=local
APP_DEBUG=true
ADMIN_TOKEN=change-this-token
```

Notes:

- Keep your token/key private. Do not commit `.env` to GitHub.
- If requests fail with `401 Unauthorized`, double-check that the bearer token was copied fully and has no extra spaces.
- The token should be pasted without the word `Bearer`; the app adds the authorization header for you.
- TMDB says API keys are created from the API link inside account settings and require agreeing to their terms of use.

## Setup

1. Copy the project folder to your Apache site directory.
2. Use `public/` as the web root, or keep the included root `.htaccess` so requests are routed into `public/`.
3. Copy `.env.example` to `.env`.
4. Add your TMDB credentials.
5. Make `storage/` writable by PHP.
6. Open the site in your browser.

Example `.env`:

```ini
TMDB_BEARER_TOKEN=your_v4_read_access_token
TMDB_API_KEY=optional_v3_key
APP_ENV=local
APP_DEBUG=true
ADMIN_TOKEN=change-this-token
```

## Local development

From the project root:

```bash
php -S localhost:8000 -t public
```

Then open:

```text
http://localhost:8000
```

## XAMPP notes

For XAMPP/Apache:

- Put the project inside `htdocs`, or configure a virtual host.
- Enable `mod_rewrite`.
- Make sure `.htaccess` files are allowed with `AllowOverride All`.
- Keep `storage/` writable.
- Add your TMDB credentials to `.env`.

## Player embeds

Movie pages use:

```text
https://player.videasy.net/movie/{tmdb_id}
```

Episode pages use:

```text
https://player.videasy.net/tv/{tmdb_id}/{season}/{episode}
```

The iframe uses a standard 16:9 wrapper and `allowfullscreen`.

## Profile page

The `/profile` page is browser-local and uses `localStorage`.

It stores:

- Bookmarked items
- Recently viewed items
- Continue watching items

Because this data is stored in the visitor’s browser, it does not require accounts or server-side storage.

## Admin area

Open the admin dashboard with your token:

```text
/admin?token=change-this-token
```

Admin includes:

- Dashboard stats
- Storage shard health
- Recent media
- Import page
- Manage movies
- Manage TV shows
- Manage actors
- Search/filter/sort/pagination
- Preview and delete actions

For production, replace the query-string token with proper authentication.

## Security notes

- Do not commit your real `.env` credentials.
- Keep `APP_DEBUG=false` in production.
- Protect admin routes with real authentication before using publicly.
- Make sure web access to raw storage files is blocked or restricted on production hosting.

## Credits

- Metadata: TMDB API
- UI: Bootstrap 5.3.3 and Font Awesome
- Storage: local JSON shards
