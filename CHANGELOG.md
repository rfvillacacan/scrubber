# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Major Architecture Update**: JSON-driven rule configuration system
  - All detection patterns and generation logic now configured in JSON files
  - No PHP code changes required to add new rules
  - New rule configuration fields: `generator`, `cache_type`, `data_type`, `skip_length_adjust`

### Changed
- **Scrubbing Behavior**: Replaced placeholder system with realistic fake data generation
  - Old: `[[[SCRUB_TOKENS_EMAIL_...@dummy.local]]]`
  - New: `account_3a2f@example.com` (contextually appropriate)
- **Label Preservation**: All rules now use capturing groups to preserve labels
  - Labels like "Request-ID:", "Email:", "IP:" remain intact
  - Only the value portion is replaced
- **Caching System**: Implemented global and local caching
  - Global: Same value always maps to same fake value (emails, IPs, UUIDs)
  - Local: Unique fake values per occurrence (passwords, tokens)

### Fixed
- Consistency issue where same value could get different replacements
- Labels being removed during scrubbing process
- Over-broad patterns matching parts of other values

### Updated
- **Documentation**: Complete rewrite to reflect new architecture
  - README.md: New architecture explanation with examples
  - docs/README_APP.md: Detailed usage and troubleshooting guide
  - CONTRIBUTING.md: Comprehensive rule creation guide
  - SECURITY.md: Updated security model and threat analysis
- **All Rule Files**: Updated 10 rulesets with new configuration fields
  - `rules/pii.scrubrules.json`
  - `rules/tokens.scrubrules.json`
  - `rules/finance.scrubrules.json`
  - `rules/pci.scrubrules.json`
  - `rules/network.scrubrules.json`
  - `rules/cloud.scrubrules.json`
  - `rules/corp.scrubrules.json`
  - `rules/phi.scrubrules.json`
  - `rules/general.scrubrules.json`
  - `rules/banking.scrubrules.json`

## [2.2.0] - 2026-02-24

### Added
- `/healthz.php` endpoint for container and external health monitoring
- Docker `web` service healthcheck using the health endpoint
- Optional deployment hardening controls:
  - HTTP Basic auth (`APP_BASIC_AUTH_USER`, `APP_BASIC_AUTH_PASS`)
  - Session retention cleanup (`APP_RETENTION_DAYS`)
- Auth regression script (`tests/auth_regression.sh`) and CI coverage
- Production `.env` template and hardening guidance in documentation

## [2.1.0] - 2026-02-24

### Added
- Dockerized public edition with HTTPS support for browser clipboard API compatibility
- TLS/SSL configuration for secure browser context
- nginx container for HTTP/HTTPS handling
- PHP-FPM container with optimized configuration
- Environment-based configuration system
- Session encryption support with passphrase

### Changed
- Architecture from standalone PHP to containerized deployment
- Clipboard features now require secure context (HTTPS or localhost)

## [2.0.0] - 2026-02-20

### Added
- Reversible sensitive data scrubbing
- Session-based mapping storage (SQLite)
- Restore functionality to recover original values
- Ruleset-based detection system
- Priority-based rule processing
- Web UI for scrubbing and restoring

### Security
- Local-first architecture (no external API calls)
- Session isolation with unique IDs
- Optional passphrase encryption

## [1.0.0] - Initial Release

### Added
- Basic pattern matching for sensitive data
- Simple replacement with placeholders
- Core detection rulesets (PII, TOKENS, etc.)
- Command-line interface

---

## Version History Summary

| Version | Date | Key Changes |
|---------|------|-------------|
| Unreleased | TBD | JSON-driven rules, realistic fake data, label preservation |
| 2.2.0 | 2026-02-24 | Health checks, hardening options, auth regression tests |
| 2.1.0 | 2026-02-24 | Docker HTTPS deployment, browser clipboard support |
| 2.0.0 | 2026-02-20 | Reversible scrubbing, session storage, restore functionality |
| 1.0.0 | - | Initial release with basic placeholder replacement |

---

## Migration Guide

### From 2.2.x to Unreleased (JSON-Driven Architecture)

**Breaking Changes:**
- Scrubbed output format changed (no more placeholders like `[[[SCRUB_...]]]`)
- Old sessions are **not compatible** with new version
- Rule configuration format changed

**Migration Steps:**

1. **Backup existing sessions:**
   ```bash
   cp -r data/ data.backup/
   ```

2. **Update containers:**
   ```bash
   docker compose down
   docker compose up -d --build
   ```

3. **Test with sample data:**
   - Verify labels are preserved
   - Check that same values get consistent replacements
   - Confirm fake data looks realistic

4. **Create new sessions:**
   - Old sessions cannot be restored with new version
   - Start fresh sessions after update

**Rule File Updates:**

If you have custom rule files, update them with new fields:

```json
{
    "id": "MY_RULE",
    "enabled": true,
    "priority": 100,
    "pattern": "\\bLabel:\\s*([A-Z0-9-]+)\\b",
    "flags": "i",
    "validation": null,
    "generator": "string",
    "cache_type": "local",
    "data_type": "my_type",
    "skip_length_adjust": false
}
```

Key additions:
- Use capturing groups `(...)` in pattern
- Add `generator` field
- Add `cache_type` ("global" or "local")
- Add `data_type` for global caching
- Add `skip_length_adjust` if needed

### From 2.1.x to 2.2.0

**No breaking changes.**

**New Features:**
- Health check endpoint at `/healthz.php`
- HTTP Basic authentication support
- Configurable session retention

**Configuration:**
Add to `.env`:
```dotenv
APP_BASIC_AUTH_USER=your_user
APP_BASIC_AUTH_PASS=your_password
APP_RETENTION_DAYS=30
```

### From 2.0.x to 2.1.0

**Breaking Changes:**
- Application now runs in Docker containers
- Configuration via environment variables instead of config files
- Clipboard features require HTTPS (or localhost)

**Migration Steps:**

1. **Install Docker and Docker Compose**

2. **Create environment file:**
   ```bash
   cp .env.example .env
   ```

3. **Configure TLS certificates:**
   ```bash
   # Place certificates in certs/
   certs/fullchain.crt
   certs/private.key
   ```

4. **Start containers:**
   ```bash
   docker compose up -d --build
   ```

5. **Access application:**
   ```
   https://localhost:9443
   ```

---

## Support

For issues, questions, or contributions:
- Check documentation in `docs/` directory
- Review contributing guidelines in `CONTRIBUTING.md`
- Report issues via GitHub Issues
- Security vulnerabilities: See `SECURITY.md`
