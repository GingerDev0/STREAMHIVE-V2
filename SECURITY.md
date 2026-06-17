# Security Policy

## Supported Versions

Security fixes should target the current `main` branch unless the repository owner states otherwise.

## Reporting a Vulnerability

Please do not open a public issue for secrets, credential exposure, authentication bypasses, or other sensitive findings.

Report privately to the repository owner using GitHub private vulnerability reporting if enabled, or another private contact method listed on the repository profile.

## Secret Handling

- Never commit `.env`.
- Rotate TMDB credentials immediately if they are exposed.
- Set `APP_DEBUG=false` in production.
- Review third-party embed providers before deploying publicly.
