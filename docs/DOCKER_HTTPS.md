# Deploy Scrubber with Docker

This guide covers both **HTTP** (for local development) and **HTTPS** (for production and clipboard features) deployment options.

## HTTP Deployment (Simpler - No Certificates Required)

Use this option for local development or when you don't need browser clipboard features.

### Step 1: Create Environment File

```bash
cp .env.example .env
```

### Step 2: Configure HTTP Port (Optional)

Edit `.env` to set your preferred HTTP port:

```bash
HTTP_PORT=8080              # Default: 8080
APP_RETENTION_DAYS=30       # Days to keep sessions
APP_BASIC_AUTH_USER=        # Leave empty to disable
APP_BASIC_AUTH_PASS=        # Leave empty to disable
```

### Step 3: Start Containers

```bash
docker compose up -d --build
```

### Step 4: Verify Deployment

```bash
# Check container status
docker compose ps

# Test health endpoint
curl http://localhost:8080/healthz.php
```

Expected output: `{"status":"ok"}`

### Step 5: Access the Application

```
http://localhost:8080
```

## HTTPS Deployment (Required for Clipboard Features)

Use this option for production deployment or when you need browser clipboard API access.

### Step 1: Create Environment File

```bash
cp .env.example .env
```

### Step 2: Provide TLS Certificates

Place your SSL certificates in the `certs/` directory:

```bash
mkdir -p certs
# Copy your certificates to:
certs/fullchain.crt
certs/private.key
```

**For development/testing, generate self-signed certificates:**

```bash
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout certs/private.key \
  -out certs/fullchain.crt \
  -subj "/C=US/ST=State/L=City/O=Organization/CN=localhost"
```

### Step 3: Configure HTTPS Port (Optional)

Edit `.env` to set your preferred HTTPS port and certificate paths:

```bash
HTTPS_PORT=9443                         # Default: 9443
CERT_FULLCHAIN_PATH=./certs/fullchain.crt
CERT_KEY_PATH=./certs/private.key
APP_RETENTION_DAYS=30                   # Days to keep sessions
APP_BASIC_AUTH_USER=                    # Leave empty to disable
APP_BASIC_AUTH_PASS=                    # Leave empty to disable
```

### Step 4: Start Containers

```bash
docker compose up -d --build
```

### Step 5: Verify Deployment

```bash
# Check container status
docker compose ps

# Test health endpoint (use -k for self-signed certs)
curl -k https://localhost:9443/healthz.php
```

Expected output: `{"status":"ok"}`

### Step 6: Access the Application

```
https://localhost:9443
```

If using self-signed certificates, accept the security warning in your browser.

## Useful Docker Commands

```bash
# View application logs
docker compose logs -f app
docker compose logs -f web

# Stop the application
docker compose down

# Restart the application
docker compose restart

# Rebuild after code changes
docker compose up -d --build

# Check container health
docker compose ps
```

## Configuration Notes

### HTTP vs HTTPS Considerations

- **HTTP**: Simpler setup, no certificates required, suitable for local development
  - Browser clipboard features will not work
  - Access at `http://localhost:8080`

- **HTTPS**: Required for browser clipboard API access, recommended for production
  - Requires SSL/TLS certificates
  - Enables button-based clipboard copy operations
  - Access at `https://localhost:9443`

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `HTTP_PORT` | `8080` | HTTP port (for HTTP deployment) |
| `HTTPS_PORT` | `9443` | HTTPS port (for HTTPS deployment) |
| `CERT_FULLCHAIN_PATH` | `./certs/fullchain.crt` | Path to SSL certificate chain |
| `CERT_KEY_PATH` | `./certs/private.key` | Path to SSL private key |
| `APP_RETENTION_DAYS` | `30` | Days to keep session data |
| `APP_BASIC_AUTH_USER` | (empty) | Basic auth username (leave empty to disable) |
| `APP_BASIC_AUTH_PASS` | (empty) | Basic auth password (leave empty to disable) |
| `APP_UID` | `1000` | User ID for application process |
| `APP_GID` | `1000` | Group ID for application process |

### Security Recommendations

For public or shared deployments:

1. **Enable Basic Authentication**: Set strong values for `APP_BASIC_AUTH_USER` and `APP_BASIC_AUTH_PASS`
2. **Reduce Retention Period**: Set `APP_RETENTION_DAYS` to a low value (e.g., `7` or `14`)
3. **Use Valid Certificates**: Use proper SSL certificates from a trusted CA in production
4. **Never Commit Sensitive Files**: Exclude `.env`, `certs/`, and `data/` from version control

### Troubleshooting

**Port already in use:**
```bash
# Change the port in .env
HTTP_PORT=8081  # or HTTPS_PORT=9444

# Then restart
docker compose down
docker compose up -d --build
```

**Permission errors with certificates:**
```bash
chmod 644 certs/fullchain.crt
chmod 600 certs/private.key
```

**Containers not starting:**
```bash
# Check logs for errors
docker compose logs app
docker compose logs web
```
