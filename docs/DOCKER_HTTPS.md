# Run Scrubber via Docker HTTPS

1. Copy env template:

```bash
cp .env.example .env
```

2. Place your TLS files:

```text
certs/fullchain.crt
certs/private.key
```

3. Start containers:

```bash
docker compose up -d --build
```

4. Open the app:

```text
https://localhost:9443
```

5. Verify health endpoint:

```text
https://localhost:9443/healthz.php
```

Notes:
- Clipboard APIs require a secure origin. HTTPS enables button-based clipboard access.
- If port `9443` is in use, set `HTTPS_PORT` in `.env`.
- If cert/key paths differ, set `CERT_FULLCHAIN_PATH` and `CERT_KEY_PATH` in `.env`.
- Optional HTTP Basic auth: set both `APP_BASIC_AUTH_USER` and `APP_BASIC_AUTH_PASS`.
- Session cleanup retention: set `APP_RETENTION_DAYS` (default `30`).
