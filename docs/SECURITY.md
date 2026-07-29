# Security Audit Checklist

PowerMedia Gallery implements defense-in-depth across all layers: database, HTTP, authentication, and business logic.

## Authentication & Sessions

- [x] **Admin password**: Argon2id hashing (PHP's `password_hash()` default)
- [x] **Admin 2FA**: TOTP (RFC 6238) with time-window replay guard
- [x] **Session tokens**: Secure HTTP-only cookies with `Secure` flag on HTTPS
- [x] **Session timeout**: Configurable expiry; no sliding windows (explicit re-auth required)
- [x] **CSRF protection**: SameSite=Lax on cart cookie, form-action CSP on POST endpoints

## Data Integrity

### SQL Injection
- [x] **Prepared statements** everywhere; no string interpolation in SQL
- [x] **Parameterized queries**: PDO execute() always used with bound parameters
- [x] **Type casting**: All user input explicitly cast (int, string, bool)

### Cryptography
- [x] **HMAC-SHA256** for cart cookies: signs payload with server key
- [x] **SHA256 hashing** for download tokens: raw tokens never logged, hashes stored
- [x] **Random token generation**: `random_bytes()` for all tokens (download, TOTP secrets)
- [x] **Base64 URL-safe encoding**: Tokens use `-_` not `+/` to avoid ambiguity

### Download Security
- [x] **Token signing**: Server-side validation required; tokens cannot be forged
- [x] **Download cap**: Per-customer limit enforced at download endpoint, not client
- [x] **Expiry validation**: Download links expire after configurable days (default 30)
- [x] **Revocation support**: `revoked` flag allows manual link cancellation
- [x] **Usage tracking**: `download_count` and `last_used_at` for audit trail

## Checkout & Payments

- [x] **Stripe signature validation**: HMAC-SHA256 verification on webhooks
- [x] **Idempotency**: Webhook event IDs deduplicated (no double-charging)
- [x] **Price recalculation**: Cart prices fetched from DB at checkout, never from client
- [x] **Discount immutability**: Applied at checkout time, recalculated fresh each time
- [x] **Email validation**: FILTER_VALIDATE_EMAIL on input, regex on output
- [x] **Rate limiting**: 5 checkout attempts/hour per (email, IP); exponential backoff on retry

## API & HTTP

### Headers
- [x] **Content-Security-Policy**: `default-src 'self'`, img-src allows data: URIs
- [x] **form-action CSP**: Restricted to Stripe Checkout only for sensitive POST
- [x] **X-Frame-Options**: DENY (no framing; prevents clickjacking)
- [x] **X-Content-Type-Options**: nosniff (prevent MIME-sniffing)
- [x] **Referrer-Policy**: strict-origin-when-cross-origin (minimal leakage)
- [x] **Permissions-Policy**: Disable geolocation, microphone, camera, payment, USB
- [x] **X-Permitted-Cross-Domain-Policies**: none (no Flash/Silverlight cross-domain)
- [x] **HSTS**: Strict-Transport-Security with preload directive

### Input Validation
- [x] **Email**: FILTER_VALIDATE_EMAIL on all customer email fields
- [x] **HTTP methods**: Each route enforces correct method (POST /checkout, GET /download)
- [x] **Content-Type**: POST endpoints validate `application/json`
- [x] **Path traversal**: File paths constructed with tokenized IDs, not user input
- [x] **URL bounds**: Regex patterns on path parameters (e.g., token format validation)

## Rate Limiting

- [x] **Download endpoint**: 30 requests/hour per IP (distributed by country)
- [x] **Checkout endpoint**: 5 requests/hour per (email, IP) pair
- [x] **Fixed-window buckets**: 1-hour windows reset hourly (no clock skew exploit)
- [x] **Database-backed**: Using `rate_limits` table with key hashing (SHA256)

## Audit Logging

- [x] **Login events**: Admin login/logout with IP, timestamp, success/failure
- [x] **Checkout initiated**: Order ID, email, item count, total, IP address
- [x] **Download**: Order ID, file count, IP address, user agent
- [x] **Webhook events**: Stripe event type, Stripe event ID, success/failure

All audit logs include:
- Timestamp (UTC)
- Action type
- Resource ID
- Client IP (with X-Forwarded-For support)
- Result (success/failure for login)

## Database

- [x] **Foreign keys**: Enforced in schema; deletes cascade appropriately
- [x] **Transactions**: Multi-row operations (create_order) wrapped in BEGIN/COMMIT
- [x] **Advisory locks**: Cron jobs protected by MySQL GET_LOCK() to prevent overlap
- [x] **Indices**: Performance-critical queries indexed (email lookups, event filters)

## File Handling

- [x] **Storage isolation**: Photos stored outside docroot at `/storage/`
- [x] **No direct access**: Files served via controller, never via web server
- [x] **MIME type validation**: Photos stored with media_type in database
- [x] **Size validation**: Chunked uploads validate chunk size and total size
- [x] **Derivative deletion**: 1600px versions auto-deleted after 7 days (tiering)

## Third-party Integration

### Stripe
- [x] **Webhook signature**: HMAC-SHA256 validation required
- [x] **Publishable key**: Safe to expose; used for client-side redirect only
- [x] **Secret key**: Never logged, never sent to client
- [x] **No PCI scope**: Using Stripe Checkout; never touch card data

### Mail Service
- [x] **Stub implementation**: Falls back to mail() on shared hosting
- [x] **SMTP ready**: Config structure prepared for SwiftMailer integration
- [x] **No credential leakage**: Credentials in config/config.php (gitignored)

## Open Source Hardening

- [x] **Config isolation**: All secrets in gitignored `config/config.php`
- [x] **Parameterized examples**: `config.example.php` has safe defaults
- [x] **No hardcoded values**: Business-specific settings (name, currency, Stripe keys) external
- [x] **Installation docs**: INSTALL.md covers security setup (HTTPS, database permissions)
- [x] **Dependency-free**: No external packages to vet; plain PHP + standard extensions

## Known Limitations

- **Email templates**: Currently plain text; HTML templates can be added
- **SMTP**: Requires SwiftMailer for production SMTP; falls back to mail()
- **Rate limiting**: In-memory bucket window; clock skew on shared hosting could cause issues
- **Cron**: Requires admin to configure; no built-in monitoring dashboard
- **Backups**: Not handled by app; delegate to hosting provider

## Compliance

- [x] **OWASP Top 10**: Mitigates injection, broken auth, XSS, CSRF, sensitive data exposure
- [x] **AGPL-3.0**: Network use (SaaS) compliance; code modifications must be shared
- [x] **PCI compliance**: Out of scope (Stripe Checkout handles payments)
- [x] **GDPR**: Customer email retained only for download purposes; no tracking cookies

## To Audit Further

Run the included security-audit skill:

```bash
claude-code --skill security-audit
```

This will scan for:
- SQL injection vectors
- XSS vulnerabilities in view templates
- OWASP Top 10 issues
- Supply chain / dependency risks
