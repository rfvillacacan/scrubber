# Security Policy

## Reporting a Vulnerability

**Please report vulnerabilities privately** to project maintainers first. Do not open public issues for exploitable security findings until a fix is available.

### How to Report

Send your report to the project maintainers with:

- **Affected version/commit**: Specific version or commit hash
- **Reproduction steps**: Clear steps to reproduce the vulnerability
- **Impact assessment**: Expected impact and severity
- **Suggested remediation**: Any suggested fix or mitigation (if available)

### What Happens Next

1. Maintain will acknowledge receipt within 48 hours
2. We will assess the severity and impact
3. We will develop a fix
4. We will coordinate disclosure timeline with you
5. Once fixed, we will release security update with credit

## Security Model Overview

Scrubber is designed as a **local-first** application for sensitive data anonymization. Understanding the security model is critical for proper deployment.

### Core Security Principles

1. **Local Processing** - All scrubbing happens locally in your container
2. **No External Calls** - No API calls or data transmission to external services
3. **Session Isolation** - Each session has isolated storage and optional encryption
4. **Realistic Fake Data** - Generates contextually appropriate fake data, not obvious patterns

### Data Flow Security

```
┌─────────────────────────────────────────────────────────────────┐
│                     User Input (Sensitive)                      │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                   ScrubberEngine (Local)                        │
│  • Pattern matching with priority-based processing              │
│  • Overlap detection prevents double-matching                  │
│  • Label preservation maintains context                        │
│  • Generates realistic fake data                               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Storage (Local SQLite)                       │
│  • Original→Fake mapping stored                                │
│  • Session-scoped (separate per session)                       │
│  • Optional passphrase encryption                              │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│              Scrubbed Output (Safe to Share)                    │
│  • Realistic fake data                                         │
│  • Structure preserved                                         │
│  • Context maintained                                          │
└─────────────────────────────────────────────────────────────────┘
```

## Sensitive Areas

### Placeholder Mapping Storage (`lib/Storage.php`)

**Risk**: Session databases contain original→fake mappings.

**Mitigations**:
- Session databases stored in `data/` directory (gitignored)
- Files auto-deleted after `APP_RETENTION_DAYS` (default: 30)
- Optional passphrase encryption using AES-256-GCM
- Each session has unique random ID (32-character hex)

**Best Practices**:
- Set appropriate `APP_RETENTION_DAYS` for your use case
- Use encryption for highly sensitive sessions
- Regular cleanup of old session files
- Never commit `data/` directory contents

### Ruleset Parsing and Regex Execution (`lib/RulesRegistry.php`)

**Risk**: Malicious patterns could cause ReDoS or unexpected behavior.

**Mitigations**:
- Regex patterns validated with `@preg_match()` before use
- Invalid patterns are rejected and logged
- Only loads files from `rules/` directory
- File reads use PHP's `file_get_contents()` with path validation

**Best Practices**:
- Review custom rules before deployment
- Test rules for performance with large inputs
- Avoid catastrophic backtracking patterns
- Use reasonable quantifiers (avoid unlimited repetition)

### Clipboard and Browser Security (`assets/app.js`)

**Risk**: Clipboard APIs require secure context.

**Mitigations**:
- Clipboard features only work in secure contexts (HTTPS or localhost)
- Graceful fallback - manual copy/paste always available
- No clipboard data stored or transmitted

**Best Practices**:
- Use HTTPS for production deployments
- Use valid TLS certificates (not self-signed for production)
- For LAN access, HTTPS required for clipboard functionality

### Docker/nginx TLS Termination (`docker/nginx/default.conf`)

**Risk**: TLS misconfiguration could expose data.

**Mitigations**:
- TLS termination at nginx layer
- PHP-FPM never exposed directly
- Strong cipher suite configuration
- HTTP Strict Transport Security (HSTS) headers

**Best Practices**:
- Use valid certificates from trusted CA
- Keep nginx updated for security patches
- Configure appropriate cipher suites
- Enable HSTS for production

## Deployment Security

### Production Environment Checklist

- [ ] Set strong `APP_BASIC_AUTH_PASS` (minimum 20 characters, mixed case, numbers, symbols)
- [ ] Set appropriate `APP_RETENTION_DAYS` (lower for shared deployments)
- [ ] Use valid TLS certificates from trusted CA
- [ ] Enable HTTP Basic Auth (`APP_BASIC_AUTH_USER` and `APP_BASIC_AUTH_PASS`)
- [ ] Review and disable unused rulesets
- [ ] Set appropriate file permissions on `data/` directory
- [ ] Configure firewall to limit access
- [ ] Enable Docker health checks
- [ ] Monitor logs for suspicious activity

### Example Production `.env`

```dotenv
APP_UID=1000
APP_GID=1000
HTTPS_PORT=9443
CERT_FULLCHAIN_PATH=/etc/ssl/certs/fullchain.crt
CERT_KEY_PATH=/etc/ssl/private/private.key
APP_RETENTION_DAYS=7
APP_BASIC_AUTH_USER=scrubber_admin
APP_BASIC_AUTH_PASS=change-me-to-a-long-random-password-with-symbols!123#$%
```

### Access Control

**Recommended approach**:

1. **Network Level**: Restrict access to trusted IP ranges via firewall
2. **Application Level**: Enable HTTP Basic Authentication
3. **Session Level**: Use passphrase encryption for sensitive sessions

## Threat Model

### Acceptable Use Cases

✅ **Safe**:
- Sharing logs with AI assistants for troubleshooting
- Anonymizing logs for support tickets
- Redacting sensitive data for documentation
- Testing with production-like data

### Not Acceptable Use Cases

❌ **Unsafe**:
- Permanent data redaction (use dedicated tools)
- Legal document redaction (use certified tools)
- Compliance reporting without verification
- Sharing with parties who shouldn't see structure

## Known Limitations

1. **Structure Preserved**: Scrubbed data maintains original structure. Don't use when structure itself is sensitive.

2. **Reversible**: Original values can be restored from session. Don't use when irreversibility is required.

3. **Deterministic**: Same input always produces same output (within session). Don't use when output randomization is critical.

4. **Local Storage**: Session databases stored on disk. Don't use on shared systems without encryption.

5. **No Perfect Detection**: Some sensitive data may be missed if no rule exists for it. Review rulesets for your use case.

## Security Best Practices

### For Users

1. **Verify Output**: Always review scrubbed output before sharing
2. **Use Encryption**: Enable passphrase encryption for sensitive sessions
3. **Clean Up**: Regularly exit sessions and clear old data
4. **Test First**: Test with sample data before using with real sensitive data
5. **Limit Access**: Only share scrubbed data with trusted parties

### For Operators

1. **Keep Updated**: Regular updates for security patches
2. **Monitor Logs**: Review logs for suspicious patterns
3. **Review Rules**: Audit rulesets for your specific use case
4. **Backup Securely**: If backing up sessions, encrypt the backups
5. **Access Control**: Implement appropriate authentication and authorization

### For Developers

1. **Validate Input**: Always validate user input before processing
2. **Sanitize Output**: Ensure fake data doesn't accidentally leak information
3. **Test Thoroughly**: Test with edge cases and malicious inputs
4. **Document Changes**: Document security-related changes clearly
5. **Report Issues**: Report potential vulnerabilities responsibly

## Compliance Considerations

### Data Privacy

- **GDPR**: Scrubber can help with anonymization, but review your specific use case
- **CCPA**: Similar considerations as GDPR
- **HIPAA**: PHI ruleset included, but verify compliance for your use case

**Important**: Consult legal counsel for compliance requirements. This tool is a technical control, not a compliance solution.

### Logging and Auditing

Current implementation:
- Session creation and access logged
- Scrubbing operations logged with statistics
- No sensitive values logged in clear text
- Logs stored in `data/logs/` (respecting retention)

## Contact

For security-related questions or vulnerability reports:
- Create an issue with the "security" label
- Contact maintainers directly for sensitive issues

Thank you for helping keep Scrubber secure!
