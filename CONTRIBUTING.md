# Contributing

Thanks for helping improve StreamHIVE V2.

## Local Setup

1. Copy `.env.example` to `.env`.
2. Add your own TMDB credentials.
3. Run with Apache/XAMPP or:

```bash
php -S 127.0.0.1:8000 -t public
```

## Before Opening a PR

- Do not commit `.env`, API keys, logs, caches, local databases, or generated runtime files.
- Run PHP syntax checks on changed PHP files:

```bash
php -l path/to/file.php
```

- If you change JavaScript, run:

```bash
node --check public/assets/js/app.js
```

- Keep changes focused and describe the route or UI behavior you tested.

## Notes

This app uses live TMDB data. Avoid committing scraped data, cached API responses, or local media catalogues.
