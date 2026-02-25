# Scrubber - About / Readme

Version: 2.1.0 (Public Docker Edition)
Updated: February 24, 2026

## What This App Does
Scrubber redacts sensitive values before sharing text externally, then restores original values later using session mappings.

Core flow:
1. Paste raw sensitive text and click `Scrub`.
2. Copy scrubbed output and send to external tools.
3. Paste response and click `Restore`.
4. Verify with `Quick Test`.

## Security Model
- Runs locally.
- Session mappings are stored in `data/session_<id>.sqlite`.
- Clipboard APIs depend on browser security context.
- For LAN access, use HTTPS to keep copy/paste buttons functional.

## Docker HTTPS Run (Primary)
1. Copy env file:

```bash
cp .env.example .env
```

2. Place TLS certificate and key:

```text
certs/fullchain.crt
certs/private.key
```

3. Start containers:

```bash
docker compose up -d --build
```

4. Open:

```text
https://localhost:9443
```

## Project Structure
- `index.php`: main app endpoint + UI shell
- `assets/`: frontend JS/CSS
- `lib/`: scrub engine, storage, validation, logging
- `rules/`: enabled scrub rulesets
- `data/`: session DB and logs
- `docker/`: nginx + php-fpm container configs

## Operations
- Check status: `docker compose ps`
- Follow logs: `docker compose logs -f web app`
- Stop: `docker compose down`

## Notes
- If cert paths differ, update `.env`:
  - `CERT_FULLCHAIN_PATH`
  - `CERT_KEY_PATH`
- If `9443` is busy, change `HTTPS_PORT` in `.env`.
- Optional auth gate: set `APP_BASIC_AUTH_USER` and `APP_BASIC_AUTH_PASS` in `.env`.
- Session file cleanup retention is controlled by `APP_RETENTION_DAYS` (default `30`).

For container setup details, see `docs/DOCKER_HTTPS.md`.
