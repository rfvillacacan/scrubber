# Changelog

## v2.2.0 - 2026-02-24
- Added `/healthz.php` endpoint for container and external health monitoring.
- Added Docker `web` service healthcheck using the health endpoint.
- Added optional deployment hardening controls:
  - HTTP Basic auth (`APP_BASIC_AUTH_USER`, `APP_BASIC_AUTH_PASS`)
  - Session retention cleanup (`APP_RETENTION_DAYS`)
- Added auth regression script (`tests/auth_regression.sh`) and CI coverage.
- Updated docs with production `.env` template and hardening guidance.

## v2.1.0 - 2026-02-24
- Dockerized public edition with HTTPS support for browser clipboard API compatibility.
