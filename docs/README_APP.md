# Scrubber - About / Readme

Version: 2.3.0 (Realistic Fake Data Edition)
Updated: February 26, 2026

## What This App Does

Scrubber anonymizes sensitive values before sharing text externally, then restores original values later using session mappings. Unlike simple redaction tools, Scrubber generates **realistic fake data** that preserves structure and context - making it ideal for troubleshooting with AI assistants.

### Core Workflow

1. **Scrub Phase**: Paste raw sensitive text and click `Scrub`
   - Sensitive values are replaced with contextually appropriate fake data
   - Labels like "Request-ID:", "Email:", "IP:" are preserved
   - Same values throughout document get same fake value (consistency)

2. **Share**: Copy scrubbed output and send to external tools (AI, support, forums)

3. **Restore Phase**: Paste response and click `Restore`
   - Original values are restored from session mappings
   - Verify with `Quick Test` before using real data

## Example: Before and After

### Input (Sensitive)
```
Error processing transaction. Request-ID: abc123def456,
Customer: CUST-884422, Email: john@example.com,
IP: 192.168.1.100, Phone: +1-555-123-4567
Source Account: 123456789012
```

### Output (Safe to Share)
```
Error processing transaction. Request-ID: fed987-1234-5678-90ab-cdef12345678,
Customer: CUST-4d2f28, Email: account_3a2f@example.com,
IP: 217.89.45.112, Phone: +1 (783) 6743-9208
Source Account: 987654321098
```

### Key Benefits

- **Structure preserved** - Valid log format, parseable by tools
- **Context maintained** - Labels remain for troubleshooting
- **Consistent mapping** - Same email/IP/ID always gets same fake value
- **Realistic data** - Fake data looks real, not obvious placeholders
- **Reversible** - Original values can be restored from session

## How Scrubbing Works

### 1. Pattern Detection

The application uses JSON-configured regex patterns to detect sensitive data:

```
Email pattern: \b([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})\b
UUID pattern:  \b([0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})\b
IP pattern:    \b((?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))\b
```

### 2. Label Preservation (Capturing Groups)

Notice the parentheses `(...)` in patterns above - these are **capturing groups**. They ensure only the value is replaced, not the label.

**Without capturing group (BAD):**
```
Pattern: \bRequest-ID:\s*[A-F0-9-]{16,}\b
Input:  "Request-ID: abc123"
Output: "fed456"  ← Label lost!
```

**With capturing group (GOOD):**
```
Pattern: \bRequest-ID:\s*([A-F0-9-]{16,})\b
Input:  "Request-ID: abc123"
Output: "Request-ID: fed456"  ← Label preserved!
```

### 3. Fake Data Generation

Detected values are replaced with realistic fake data based on data type:

| Data Type | Original | Fake Output | Generator |
|-----------|----------|-------------|-----------|
| Email | `john@example.com` | `account_3a2f@example.com` | email |
| UUID | `abc123def456` | `fed987-1234-5678-90ab-cdef12345678` | uuid |
| IPv4 | `192.168.1.100` | `217.89.45.112` | ipv4 |
| Phone | `+1-555-123-4567` | `+1 (783) 6743-9208` | phoneNumber |
| Customer ID | `CUST-884422` | `CUST-4d2f28` | customerId |
| IBAN | `GB29NWBK60161331926819` | `GB82WEST12345698765432` | iban |
| S3 Bucket | `s3://prod-bucket/data` | `s3://fake-bucket/Xyz9` | s3Bucket |
| Docker Registry | `registry.corp.internal:5000/billing:2.14.7` | `registry.corp.internal:5000/payment-service:2.14.7` | dockerRegistry |
| JWT | `eyJhbG...` | `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...` | jwt |

### 4. Consistency (Global Caching)

Same value = same fake value throughout entire document:

```
Input:  "Contact: john@example.com, CC: john@example.com"
Output: "Contact: account_3a2f@example.com, CC: account_3a2f@example.com"
                              ↑ Same fake value                          ↑
```

This works because we use **global caching** by data type. The hash of the original value determines the fake value.

### 5. Smart Format Matching

The `string` generator uses **smart format matching** to analyze and preserve original value characteristics:

**What it preserves:**
- **Case**: UPPER case, lower case, MixedCase all maintained
- **Character types**: Letters, digits, special chars in same positions
- **Length**: Output matches input length (for realistic appearance)
- **Structure**: Dashes, dots, slashes, and other special chars preserved

**Examples:**
```
Original:  Customer-123_ABC
Fake:      Client-987_XYZ
           ↑ Mixed case preserved

Original:  s3://prod-data-bucket/logs/
Fake:      s3://fake-log-bucket/Xyz9/
           ↑ Special chars maintained
```

### 6. Technical Context Preservation

The system intelligently preserves **non-sensitive technical context** using entropy analysis:

**What's preserved (low-entropy, common patterns):**
- **Protocols**: `s3://`, `https://`, `docker://` (standards, not secrets)
- **Ports**: `:5000/`, `:443/`, `:8080/` (network configuration)
- **Versions**: `:alpine`, `:v1.2.3`, `:latest` (software versions)
- **Common registries**: `registry.corp.internal`, `docker.io`, `gcr.io` (infrastructure)
- **Known containers**: `redis`, `postgres`, `nginx` (public images)

**What's scrubbed (high-entropy or business-sensitive):**
- **Secrets**: API keys, tokens, passwords, hashes (high entropy)
- **Business data**: Container names with `payment`, `billing`, `customer` (business terms)
- **Private registries**: Custom domain names with business significance

**Entropy-based detection:**
- Calculates Shannon entropy to measure randomness
- High entropy (>3.5 bits/char) = likely a secret
- Business term detection identifies sensitive container names
- Value-based approach, not hardcoded lists

## Security Model

### Local-First Architecture

- **Runs locally** in your Docker container
- **Session mappings** stored in `data/session_<id>.sqlite`
- **No external dependencies** or API calls
- **Encryption available** - Sessions can be password-protected

### Data Flow

```
Your Sensitive Text
        ↓
   [Scrub Engine]
        ↓
Realistic Fake Data  ← Safe to share with AI, support, forums
        ↓
   [Store Mapping]   ← Original→Fake mapping stored in SQLite
        ↓
   Share Externally
        ↓
   [Restore Engine]  ← Restores original from mapping
        ↓
Original Sensitive Text
```

### Clipboard Security

Browser clipboard APIs require a **secure context**:
- ✅ `https://` (recommended)
- ✅ `http://localhost` (works)
- ✅ `http://127.0.0.1` (works)
- ❌ `http://192.168.1.x` (clipboard blocked)

For LAN access, use HTTPS to keep copy/paste buttons functional.

### Session Isolation

Each session has:
- Unique 32-character session ID
- Isolated SQLite database
- Optional passphrase encryption
- Separate from other sessions

Sessions are stored in `data/` directory and cleaned up based on `APP_RETENTION_DAYS` setting.

## Quick Start

### Docker HTTPS Run (Recommended)

1. **Copy environment file:**

```bash
cp .env.example .env
```

2. **Place TLS certificate and key:**

```text
certs/fullchain.crt
certs/private.key
```

Use Let's Encrypt, self-signed, or your organization's certificate.

3. **Start containers:**

```bash
docker compose up -d --build
```

4. **Open in browser:**

```text
https://localhost:9443
```

Accept the self-signed certificate warning if using a self-signed cert.

### Health Check

Monitor container health:

```bash
curl https://localhost:9443/healthz.php
```

Expected response: `{"status":"healthy"}`

## Project Structure

```
scrubber/
├── index.php              # Main app endpoint + UI shell
├── assets/
│   ├── app.js             # Frontend JavaScript
│   └── style.css          # Styling
├── lib/
│   ├── ScrubberEngine.php # Generic scrubbing engine (JSON-driven)
│   ├── RulesRegistry.php  # Loads rules from JSON files
│   ├── DataGenerator.php  # Generates realistic fake data
│   ├── Storage.php        # Session mapping storage
│   ├── Validator.php      # Pattern validation (luhn, jwt, etc.)
│   └── Logger.php         # Logging utility
├── rules/                 # Bundled rulesets (JSON configuration)
│   ├── pii.scrubrules.json      # Personal identifiable information
│   ├── tokens.scrubrules.json   # Credentials, tokens, secrets
│   ├── finance.scrubrules.json  # Banking and financial data
│   ├── pci.scrubrules.json      # Payment card data
│   ├── network.scrubrules.json  # Network infrastructure
│   ├── cloud.scrubrules.json    # Cloud and DevOps identifiers
│   ├── corp.scrubrules.json     # Corporate confidential data
│   ├── phi.scrubrules.json      # Protected health information
│   ├── general.scrubrules.json  # General sensitive data
│   └── banking.scrubrules.json  # Banking-specific patterns
├── data/                  # Session DB and logs (gitignored)
├── docker/
│   ├── nginx/
│   │   └── default.conf   # nginx configuration
│   ├── php/
│   │   ├── Dockerfile     # PHP-FPM image
│   │   └── php.ini        # PHP configuration
│   └── compose.yml        # Docker Compose configuration
├── docs/                  # Documentation
│   ├── README_APP.md      # This file
│   └── DOCKER_HTTPS.md    # Docker HTTPS setup details
├── .env.example           # Environment template
├── docker-compose.yml     # Docker Compose wrapper
└── README.md              # Main project README
```

## Operations

### Check Container Status

```bash
docker compose ps
```

### Follow Logs

```bash
docker compose logs -f web app
```

### Stop Services

```bash
docker compose down
```

### Restart Services

```bash
docker compose restart
```

## Configuration

### Environment Variables

Edit `.env` file to configure:

| Variable | Default | Description |
|----------|---------|-------------|
| `HTTPS_PORT` | `9443` | HTTPS port for web UI |
| `CERT_FULLCHAIN_PATH` | `./certs/fullchain.crt` | TLS certificate path |
| `CERT_KEY_PATH` | `./certs/private.key` | TLS private key path |
| `APP_RETENTION_DAYS` | `30` | Days to keep session files |
| `APP_BASIC_AUTH_USER` | (none) | Optional HTTP Basic Auth username |
| `APP_BASIC_AUTH_PASS` | (none) | Optional HTTP Basic Auth password |
| `APP_UID` | `1000` | PHP-FPM user ID |
| `APP_GID` | `1000` | PHP-FPM group ID |

### Custom Rules

Add custom detection rules without touching code:

1. Create new file in `rules/` directory: `myrules.scrubrules.json`
2. Add your rules following the JSON structure:

```json
{
    "ruleset_id": "MYRULES",
    "version": "1.0.0",
    "description": "My custom detection rules",
    "author": "Security",
    "priority_base": 750,
    "rules": [
        {
            "id": "CUSTOM_PATTERN",
            "enabled": true,
            "priority": 100,
            "pattern": "\\b(?:MY_LABEL)\\s*[:=]\\s*([A-Z0-9-]{6,20})\\b",
            "flags": "i",
            "validation": null,
            "generator": "string",
            "cache_type": "local",
            "data_type": "custom"
        }
    ]
}
```

3. Restart containers: `docker compose restart`

### Enabling/Disabling Rulesets

Edit the `enabledMap` in `index.php` or use environment variable:

```php
$enabledMap = [
    'PII' => true,      // Enabled
    'TOKENS' => true,   // Enabled
    'MYRULES' => false, // Disabled
];
```

## Troubleshooting

### Clipboard Buttons Not Working

**Cause**: Browser requires secure context for clipboard API.

**Solution**: Use HTTPS or access via `localhost`:

```bash
# Self-signed cert for localhost
openssl req -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 365 -nodes
```

### Session Not Found

**Cause**: Session ID doesn't exist or was cleaned up.

**Solution**: Use "Resume Session" with correct session ID, or start new session.

### Rules Not Loading

**Cause**: Invalid JSON syntax in rule file.

**Solution**: Validate JSON:

```bash
docker exec scrubber-app php -r '
$data = json_decode(file_get_contents("/var/www/html/rules/myrules.json"));
echo json_last_error_msg() . PHP_EOL;
'
```

## Best Practices

1. **Use descriptive session names** when saving for later reference
2. **Encrypt sensitive sessions** with a passphrase for additional security
3. **Regular cleanup** - old sessions are auto-deleted based on `APP_RETENTION_DAYS`
4. **Test scrubbing** - use "Quick Test" to verify before sharing
5. **HTTPS for LAN** - enable HTTPS for clipboard functionality on network access
6. **Backup custom rules** - keep your custom rules in version control

## Support

For container setup details, see `docs/DOCKER_HTTPS.md`.

For development and contributing, see `CONTRIBUTING.md`.

For security considerations, see `SECURITY.md`.
