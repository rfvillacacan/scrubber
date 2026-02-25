# Scrubber

Scrubber is a local-first PHP application for reversible sensitive-data redaction.

## Features
- Scrub sensitive values into deterministic placeholders
- Restore placeholders back to original values
- Session-scoped local storage (SQLite)
- Ruleset-based detection with enable/disable controls
- HTTPS Docker deployment for browser clipboard compatibility

## Quick Start (Docker)
1. Create env file:

```bash
cp .env.example .env
```

2. Provide TLS cert and key:

```text
certs/fullchain.crt
certs/private.key
```

3. Start:

```bash
docker compose up -d --build
```

4. Open:

```text
https://localhost:9443
```

Health check endpoint:

```text
https://localhost:9443/healthz.php
```

## Project Layout
- `index.php` - main endpoint and UI shell
- `assets/` - frontend JavaScript and CSS
- `lib/` - core PHP engine and storage code
- `rules/` - bundled rulesets
- `docs/` - documentation and in-app readme source
- `docker/` - nginx and php-fpm container setup

## Security Notes
- Do not commit real session databases, logs, certs, or `.env` files.
- Clipboard features require a secure browser context (`https://` or trusted localhost).
- Optional HTTP Basic auth is available via `APP_BASIC_AUTH_USER` and `APP_BASIC_AUTH_PASS`.
- Session file retention can be tuned with `APP_RETENTION_DAYS` (default: `30`).

## Production Env Template
Use this as a baseline `.env` for public or shared deployment:

```dotenv
APP_UID=1000
APP_GID=1000
HTTPS_PORT=9443
CERT_FULLCHAIN_PATH=./certs/fullchain.crt
CERT_KEY_PATH=./certs/private.key
APP_RETENTION_DAYS=14
APP_BASIC_AUTH_USER=change-me
APP_BASIC_AUTH_PASS=change-me-long-random-secret
```

Minimum recommendations:
- Use a long random `APP_BASIC_AUTH_PASS`.
- Keep `APP_RETENTION_DAYS` low for shared deployments.
- Never commit `.env` or real certificate/key files.

## License
MIT. See `LICENSE`.
