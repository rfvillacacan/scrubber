# Contributing to Scrubber

Thank you for your interest in contributing to Scrubber! This document covers how to contribute effectively.

## Development Workflow

1. Create a branch from `main`
2. Make focused, atomic changes
3. Run checks before opening PR
4. Document your changes clearly
5. Keep secrets, certs, and runtime data out of commits

## Pre-Commit Checks

Run these commands before committing:

```bash
# PHP syntax check
php -l index.php
find lib -name '*.php' -print0 | xargs -0 -n1 php -l

# Docker Compose validation
docker compose config

# JSON validation (for new rules)
for file in rules/*.json; do
    python3 -m json.tool "$file" > /dev/null && echo "✓ $file" || echo "✗ $file"
done
```

## Adding New Rules

The Scrubber engine is **JSON-driven** - no PHP code changes are required to add new detection rules. All configuration is done through JSON files in the `rules/` directory.

### Rule File Structure

Create a new `.scrubrules.json` file in the `rules/` directory:

```json
{
    "ruleset_id": "MY_RULESET",
    "version": "1.0.0",
    "description": "Brief description of what this ruleset detects",
    "author": "Your Name or Team",
    "priority_base": 750,
    "rules": [
        {
            "id": "UNIQUE_RULE_ID",
            "enabled": true,
            "priority": 100,
            "pattern": "\\b(?:LABEL)\\s*[:=]\\s*([A-Z0-9-]{6,20})\\b",
            "flags": "i",
            "validation": null,
            "generator": "string",
            "cache_type": "local",
            "data_type": "my_type",
            "skip_length_adjust": false
        }
    ]
}
```

### Rule Fields Reference

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | string | ✅ | Unique identifier for this rule (snake_case recommended) |
| `enabled` | boolean | ✅ | Whether this rule is active |
| `priority` | integer | ✅ | Rule priority (0-500). Added to `priority_base` for final priority |
| `pattern` | string | ✅ | Regex pattern. **Use capturing groups** `(...)` for label preservation |
| `flags` | string | ❌ | Regex flags (e.g., "i" for case-insensitive, "" for none) |
| `validation` | string\|null | ❌ | Validator function name (e.g., "luhn", "jwt_structure", "ipv4_strict") |
| `generator` | string | ❌ | DataGenerator method (defaults to "string") |
| `cache_type` | string | ❌ | "global" for consistency, "local" for unique (defaults to "local") |
| `data_type` | string | ❌ | Data type for global caching (defaults to rule id) |
| `skip_length_adjust` | boolean | ❌ | Skip fake value length adjustment (defaults to false) |

### Priority System

Rules are processed in priority order (highest first). The final priority is calculated as:

```
Final Priority = priority_base + rule_priority
```

**Current hierarchy:**

| Ruleset | Base Priority | Focus |
|---------|---------------|-------|
| PCI | 1000 | Payment card data |
| FINANCE | 900 | Banking identifiers |
| TOKENS | 900 | Credentials, tokens, secrets |
| GENERAL_SENSITIVE | 950 | General sensitive data |
| PHI | 850 | Protected health information |
| BANKING | 850 | Banking-specific patterns |
| PII | 800 | Personal identifiable information |
| CLOUD | 700 | Cloud and DevOps identifiers |
| NETWORK | 700 | Network infrastructure |
| CORP | 600 | Corporate confidential data |

**Guidelines:**
- Set `priority_base` between 500-1000 based on sensitivity
- Set individual `priority` based on specificity (higher = more specific)
- Higher priority rules process first to prevent overlapping matches

### Label Preservation (Capturing Groups)

**CRITICAL**: Use capturing groups to preserve labels and context.

**Bad pattern (replaces entire match):**
```json
{
    "pattern": "\\b(?:REQUEST-ID|TRACE-ID)\\s*[:=]\\s*[A-F0-9-]{16,}\\b"
}
```
Result: `Request-ID: abc123` → `fed456` (label lost!)

**Good pattern (captures only value):**
```json
{
    "pattern": "\\b(?:REQUEST-ID|TRACE-ID)\\s*[:=]\\s*([A-F0-9-]{16,})\\b"
    //                                                 ^^^^^^^^^^^^^   ← CAPTURING GROUP
}
```
Result: `Request-ID: abc123` → `Request-ID: fed456` (label preserved!)

**Rules for capturing groups:**
1. Use non-capturing groups `(?:...)` for label/keyword patterns
2. Use capturing groups `(...)` for the actual value to replace
3. Nested capturing groups are supported - first group is used for replacement

### Choosing the Right Generator

Available generators in `lib/DataGenerator.php`:

| Generator | Output Example | Best For |
|-----------|---------------|----------|
| `email` | `user_3a2f@example.com` | Email addresses |
| `uuid` | `8ec42f64-146f-616b-6d8d-c2abe4a0e941` | UUIDs, trace IDs, request IDs |
| `ipv4` | `217.89.45.112` | IPv4 addresses |
| `cidr` | `172.16.0.0/12` | CIDR notation |
| `phoneNumber` | `+1 (783) 6743-9208` | Phone numbers |
| `personName` | `Jane Smith` | Person names |
| `customerId` | `CUST-4d2f28` | Customer IDs (maintains prefix if any) |
| `accountId` | `ACC-12345678` | Account IDs |
| `password` | `Xy9@bB2$kL` | Passwords |
| `jwt` | `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...` | JWT tokens |
| `bearerToken` | `tok_abc123xyz789` | Bearer tokens |
| `amount` | `1250.00` | Financial amounts |
| `iban` | `GB82WEST12345698765432` | IBANs (ALL CAPS, length preserved) |
| `s3Bucket` | `s3://my-fake-bucket/path` | S3 bucket URIs (preserves s3://) |
| `dockerRegistry` | `registry.corp.internal:5000/service:alpine` | Docker registries (preserves ports, versions) |
| `creditCard` | `4532015112830366` | Credit card numbers (Luhn-valid) |
| `databaseName` | `production_db` | Database names |
| `username` | `admin_user` | Usernames |
| `string` | `aB3xY7zQ9` | Generic alphanumeric with smart format matching |

**Guidelines:**
- Use the most specific generator for your data type
- If no specific generator exists, use `string`
- Generators maintain format where possible (UUIDs, emails, etc.)
- The `string` generator uses smart format matching by default

### Smart Format Matching

The `string` generator (and other generators when appropriate) uses **smart format matching** to preserve original value characteristics:

- **Case preservation**: Original letter case is maintained (UPPER, lower, MixedCase)
- **Character type preservation**: Letters, digits, special chars in same positions
- **Length matching**: Output length matches input length (unless `skip_length_adjust: true`)
- **Special character preservation**: Punctuation, symbols, structural chars maintained

This approach ensures fake data looks realistic while maintaining the original structure, making it ideal for:
- Mixed-case identifiers (CustomerID, orderId, etc.)
- Structured strings (API-KEY-123, prod_db_v2, etc.)
- Values with special formatting (account-123_ABC, etc.)

### Entropy-Based Detection

For Docker registries and other cloud/DevOps patterns, the system uses **entropy-based detection**:

- **Shannon entropy calculation**: Measures randomness to detect secrets
- **Secret-like values**: High-entropy patterns (API keys, tokens, hashes) are scrubbed
- **Technical context**: Low-entropy values (common registries, known containers) are preserved
- **Business sensitivity**: Values containing business terms (payment, billing, etc.) are flagged

This value-based approach is more reliable than maintaining hardcoded lists of known values.

### Global vs Local Caching

**Global caching (`cache_type: "global"`):**
- Same value across entire document → same fake value
- Used for: emails, IPs, UUIDs, customer IDs, etc.
- Requires `data_type` field to group same types across rules

```json
{
    "generator": "email",
    "cache_type": "global",
    "data_type": "email"
}
```

Example: Multiple occurrences of `john@example.com` throughout document all become `account_3a2f@example.com`.

**Local caching (`cache_type: "local"`):**
- Same value might get different fake values in different contexts
- Used for: passwords, API keys, tokens, sensitive strings
- Each rule maintains its own cache

```json
{
    "generator": "string",
    "cache_type": "local"
}
```

Example: `Password: Secret123` and `DB_PASS: Secret123` might get different fake values.

### Validation Functions

Available validators in `lib/Validator.php`:

| Validator | Purpose |
|-----------|---------|
| `luhn` | Credit card number validation |
| `jwt_structure` | JWT token structure validation |
| `ipv4_strict` | Strict IPv4 validation (0-255 per octet) |
| `ipv6_strict` | Strict IPv6 validation |
| `cidr_strict` | CIDR notation validation |
| `iban` | IBAN validation |
| `routing_aba` | ABA routing number validation |

Usage:
```json
{
    "pattern": "\\b(\\d{13,19})\\b",
    "validation": "luhn",
    "generator": "creditCard"
}
```

### Complete Rule Example

Here's a complete, well-structured rule for detecting custom transaction IDs:

```json
{
    "ruleset_id": "TRANSACTION",
    "version": "1.0.0",
    "description": "Transaction and payment identifiers",
    "author": "Security Team",
    "priority_base": 880,
    "rules": [
        {
            "id": "TRANSACTION_ID",
            "enabled": true,
            "priority": 150,
            "pattern": "\\b(?:TRANS(?:ACTION)?|TXN)\\s*(?:ID)?\\s*[:=]\\s*(TXN-[A-Z0-9]{12})\\b",
            "flags": "i",
            "validation": null,
            "generator": "string",
            "cache_type": "global",
            "data_type": "transaction_id",
            "skip_length_adjust": true
        },
        {
            "id": "PAYMENT_REF",
            "enabled": true,
            "priority": 140,
            "pattern": "\\b(?:PAYMENT REFERENCE|PAYMENT REF|PMT REF)\\s*[:=]\\s*([A-Z0-9]{8,16})\\b",
            "flags": "i",
            "validation": null,
            "generator": "string",
            "cache_type": "global",
            "data_type": "payment_ref",
            "skip_length_adjust": true
        }
    ]
}
```

### Testing Your Rules

After creating or modifying rules:

1. **Restart the container:**
   ```bash
   docker compose restart app
   ```

2. **Verify rules load:**
   ```bash
   docker exec scrubber-app php -r '
   require_once "lib/Logger.php";
   require_once "lib/RulesRegistry.php";
   $logger = new Logger("php://stderr", "ERROR");
   $registry = new RulesRegistry("/var/www/html/rules", $logger);
   echo "Rules loaded: " . count($registry->getRules()) . PHP_EOL;
   '
   ```

3. **Test scrubbing:**
   - Open the web UI
   - Paste test data containing your pattern
   - Verify label is preserved and fake data is appropriate

4. **Test consistency:**
   - Use the same value multiple times in test data
   - Verify all occurrences get the same fake value (if using `global` cache)

### Common Mistakes to Avoid

1. **Forgot capturing groups:**
   ```json
   // ❌ Bad - replaces label too
   {"pattern": "\\bEmail:\\s*\\S+@\\S+\\b"}

   // ✅ Good - only replaces email
   {"pattern": "\\bEmail:\\s*(\\S+@\\S+)\\b"}
   ```

2. **Wrong priority causing conflicts:**
   ```json
   // ❌ Bad - generic pattern has high priority
   {"id": "GENERIC_NUM", "priority": 500, "pattern": "\\b\\d+\\b"}

   // ✅ Good - specific pattern has high priority
   {"id": "CREDIT_CARD", "priority": 200, "pattern": "\\b\\d{13,19}\\b", "validation": "luhn"}
   ```

3. **Not using cache_type correctly:**
   ```json
   // ❌ Bad - passwords shouldn't use global cache
   {"generator": "password", "cache_type": "global"}

   // ✅ Good - passwords use local cache
   {"generator": "password", "cache_type": "local"}
   ```

## Pull Request Guidelines

### Title Format

Use clear, descriptive titles:

```
Add ruleset for detecting XYZ tokens
Fix label preservation in ABC pattern
Update priority for credit card detection
Add new generator for phone numbers
```

### Description Template

```markdown
## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Documentation update
- [ ] Performance improvement

## Description
Brief description of what changed and why.

## Testing
- [ ] Tested locally with Docker
- [ ] Added/updated test cases
- [ ] Verified JSON syntax
- [ ] Checked for conflicts with existing rules

## Checklist
- [ ] PHP syntax valid
- [ ] JSON valid (if adding rules)
- [ ] No secrets/certs committed
- [ ] Documentation updated
```

## Code Style

- **PHP**: Follow PSR-12 coding standard
- **JSON**: 2-space indentation, trailing commas, double quotes
- **Comments**: Add comments for complex regex patterns
- **Naming**: Use `UPPER_SNAKE_CASE` for rule IDs, `lowerCamelCase` for PHP methods

## Reporting Issues

When reporting issues, include:

1. **Version**: `docker exec scrubber-app php -r 'echo file_get_contents("/var/www/html/VERSION") . PHP_EOL;'`
2. **Steps to reproduce**: Clear reproduction steps
3. **Expected behavior**: What should happen
4. **Actual behavior**: What actually happened
5. **Sample data**: Sanitized input/output showing the issue

## Security Considerations

- **Never commit** real session databases, logs, certificates, or `.env` files
- **Sanitize test data** before including in issues/PRs
- **Use scrubbed output** when sharing logs publicly
- **Report security vulnerabilities** privately (see SECURITY.md)

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
