# StreamHIVE V2

StreamHIVE V2 is a PHP movie and TV discovery interface with a premium streaming-service style UI, powered by live TMDB data.

This build is **live-only**: it does not use SQL, does not store movie or TV records in a database, and does not maintain a local media catalogue. Pages resolve and render from TMDB at request time.

> This product uses the TMDB API but is not endorsed, certified, or otherwise approved by TMDB.

---

## Highlights

- Live TMDB movie, TV, season, episode, actor, collection, cast, crew, and recommendation data.
- No MySQL, no import cache, no app-side movie/TV persistence.
- Clean SEO-friendly routes without `.php`.
- Year-aware movie slugs for duplicate titles.
- Premium dark streaming-service UI with TMDB posters/backdrops and Font Awesome icons.
- Movie pages with inline player, Cast & Crew, Collection, and More like this tabs.
- TV pages with Seasons, Cast & Crew, and More like this tabs.
- Season pages with Episodes, Cast & Crew, and More like this tabs.
- Episode pages with inline player, Next Episode, Cast & Crew, and More like this.
- Actor profiles, live search, genre browsing, coming-this-year, profile bookmarks, and recent history.

---

## Requirements

| Requirement | Notes |
|---|---|
| PHP | PHP 8.1+ recommended |
| Web server | Apache with `mod_rewrite`, XAMPP, or PHP built-in server |
| PHP extensions | `curl` recommended for TMDB requests |
| TMDB credentials | v4 Read Access Token preferred, v3 API key supported |
| Database | Not required |

---

## Setup

Clone the project:

```bash
git clone https://github.com/GingerDev0/StreamHIVE-V2.git
cd StreamHIVE-V2
```

Copy the environment template:

```bash
cp .env.example .env
```

Add your TMDB credentials:

```ini
TMDB_BEARER_TOKEN=your_tmdb_v4_read_access_token_here
TMDB_API_KEY=your_tmdb_v3_api_key_here

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

Recommended local path:

```text
C:\xampp\htdocs
```

The root `.htaccess` routes requests into `public/`, and `public/index.php` is the front controller. Make sure Apache rewrite support is enabled, then open:

```text
http://127.0.0.1/
```

### PHP Built-In Server

```bash
php -S 127.0.0.1:8000 -t public
```

Then open:

```text
http://127.0.0.1:8000/
```

---

## Routes

| Route | Description |
|---|---|
| `/` | Home page |
| `/movies` | Movie listing |
| `/movies/{slug}` | Movie detail |
| `/tv` | TV listing |
| `/tv/{slug}` | TV show detail |
| `/tv/{slug}/s01` | Season detail |
| `/tv/{slug}/s01/e01` | Episode detail |
| `/actors` | Actor listing |
| `/actors/{slug}` | Actor profile |
| `/actor/{slug}` | Actor profile alias |
| `/coming-this-year` | Future-dated movies and TV for the current year |
| `/s` | Search page |
| `/s/{query}` | Search page with path query |
| `/profile` | Browser-local bookmarks and recent history |
| `/ajax/live-search` | Navbar live search endpoint |

---

## URL Strategy

Movie slugs include the release year when TMDB provides a release date. This avoids collisions when multiple movies share the same title:

```text
Scary Movie (2000) -> /movies/scary-movie-2000
Scary Movie (2026) -> /movies/scary-movie-2026
```

TV and episode routes use the show slug plus season/episode numbers:

```text
/tv/from
/tv/from/s01
/tv/from/s01/e01
```

The resolver maps these slugs back to the correct TMDB result.

---

## Data Behavior

TMDB is the source of truth.

The app fetches metadata live for:

- Movies and TV shows
- Seasons and episodes
- Cast and crew
- Actors and filmographies
- Recommendations and similar titles
- Movie collections
- Posters, profile images, and backdrops
- Ratings, runtimes, release dates, genres, certifications, and external IDs

The app does **not**:

- Create a MySQL schema
- Persist movie or TV records
- Store a local catalogue
- Run an admin import workflow

Profile bookmarks and recently viewed items are browser-local and use `localStorage`.

---

## Page Behavior

| Page | Main content |
|---|---|
| Movie detail | Centered title hero, poster, metadata, genres, overview, inline player, Cast & Crew, Collection when available, More like this |
| TV detail | Centered title hero, poster, metadata, genres, overview, Seasons, Cast & Crew, More like this |
| Season detail | Season hero, Episodes, Cast & Crew, More like this |
| Episode detail | Centered title hero, inline player, Next Episode, Cast & Crew, More like this |
| Actor detail | Profile, biography, known-for/filmography links |

More like this tabs render up to 12 released items with usable posters. TMDB may return fewer than 12 for some titles.

Public detail pages hide unreleased or undated media where appropriate. `/coming-this-year` is the future-content exception.

---

## Player Behavior

The app builds MultiEmbed-compatible URLs with TMDB IDs first.

The player can be changed in `.env` with `PLAYER_PROVIDER`, or overridden entirely with `PLAYER_MOVIE_URL` and `PLAYER_EPISODE_URL` templates. Built-in presets include `multiembed`, `vidsrc-to`, `vidsrc-cc`, and `embed-su`.

Movie:

```text
https://multiembed.mov/?video_id={tmdb_id}&tmdb=1
```

Episode:

```text
https://multiembed.mov/?video_id={tv_tmdb_id}&tmdb=1&s={season}&e={episode}
```

The app does not host video files. If you deploy or modify this project, you are responsible for ensuring that any embeds, sources, or links you enable comply with applicable law and third-party terms.

---

## Project Structure

```text
app/
  Controllers/
  Core/
  Helpers/
  Models/
  Services/
  Views/

public/
  assets/
  index.php

.env.example
.htaccess
README.md
LICENSE
```

Important files:

| File | Purpose |
|---|---|
| `public/index.php` | Route table and front controller |
| `app/Controllers/MediaController.php` | Movie, TV, season, episode, search, listing, and recommendation logic |
| `app/Controllers/ActorController.php` | Actor profiles and filmographies |
| `app/Services/TmdbClient.php` | TMDB API client |
| `app/Helpers/helpers.php` | URL, slug, image, runtime, rating, genre, and payload helpers |
| `app/Views/partials/related-sidebar.php` | More like this card grid |

---

## UI Notes

The UI is a dark, premium streaming layout with:

- TMDB backdrop-driven hero sections
- Centered hero titles on movie, TV, and episode pages
- Inline player panels
- Tabbed detail sections
- Compact related-style cards
- Cast & Crew cards styled to match More like this cards
- Font Awesome metadata, genre, action, tab, and navigation icons
- Responsive desktop, tablet, and mobile layouts

---

## Environment Variables

| Variable | Required | Description |
|---|---:|---|
| `TMDB_BEARER_TOKEN` | Recommended | TMDB v4 Read Access Token |
| `TMDB_API_KEY` | Optional fallback | TMDB v3 API key |
| `APP_ENV` | Optional | `local` or `production` |
| `APP_DEBUG` | Optional | `true` locally, `false` in production |
| `PLAYER_PROVIDER` | Optional | Built-in player preset: `multiembed`, `vidsrc-to`, `vidsrc-cc`, or `embed-su` |
| `PLAYER_MOVIE_URL` | Optional | Custom movie embed template. Leave blank to use the preset |
| `PLAYER_EPISODE_URL` | Optional | Custom episode embed template. Leave blank to use the preset |

Player URL templates support these placeholders:

| Placeholder | Meaning |
|---|---|
| `{video_id}` | TMDB ID when available, otherwise IMDb ID |
| `{tmdb_id}` | TMDB ID only |
| `{imdb_id}` | IMDb ID only |
| `{tmdb_flag}` | `1` when `{video_id}` is TMDB, `0` when it is IMDb |
| `{season}` | Episode season number |
| `{episode}` | Episode number |

---

## Deployment Notes

Before deploying publicly:

- Set `APP_ENV=production`.
- Set `APP_DEBUG=false`.
- Keep TMDB credentials private.
- Confirm rewrite rules route requests through `public/index.php`.
- Review any third-party player/embed behavior for legal and terms-of-service compliance.
- Add real authentication if you reintroduce admin-only features.

---

## Troubleshooting

### Page returns 404

- The TMDB item could not be resolved from the slug.
- The item is unreleased or missing a valid release/air date.
- Apache rewrite rules are disabled.
- Requests are not reaching `public/index.php`.

### Images are missing

- TMDB has no usable poster/backdrop/profile image for that item.
- Remote TMDB image requests are blocked.
- The item was filtered out because it has no usable poster.

### Recommendations show fewer than 12 items

- TMDB returned fewer usable released recommendations with posters.

### TMDB requests fail

- Check `.env`.
- Check TMDB credentials.
- Confirm PHP can make outbound HTTPS requests.
- Confirm `curl` is enabled if your environment requires it.

---

## Git Hygiene

Do not commit:

- `.env`
- Local credentials
- Runtime caches
- Editor or OS noise

The included `.gitignore` excludes common environment files, runtime database leftovers, storage cache folders, logs, and editor files.

---

## Credits

| Credit | Source |
|---|---|
| Metadata and imagery | TMDB API |
| Icons | Font Awesome |
| Player URL format | MultiEmbed-compatible TMDB embed URLs |
| Original project | GingerDev0 / StreamHIVE V2 |

---

## License

This project is licensed under the MIT License. See `LICENSE` for details.
