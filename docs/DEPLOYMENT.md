# Deployment Guide

Checklist and guide for deploying PowerMedia Gallery to production.

## Pre-Deployment (Before You Go Live)

### Security Checklist

- [ ] **HTTPS enabled** — Install SSL certificate (Let's Encrypt is free)
  - Verify: `https://yourdomain.com` works, redirects from `http://`
  - Certificate auto-renewal configured (if using cPanel, auto-renew is default)

- [ ] **Admin password strong** — At least 16 characters, mixed case/numbers/symbols
  - Change during setup if you used a temporary password

- [ ] **Two-factor authentication enabled** — TOTP 2FA on admin account
  - Test: Log out, log in, verify TOTP prompt appears
  - Save backup codes in a safe place

- [ ] **Security keys set** — Both should be 32+ random characters
  - `config/config.php`: `security.hmac_key` (for signing cookies & download links)
  - `config/config.php`: `security.cron_secret` (for cron access)
  - If using install.php, these were auto-generated ✓

- [ ] **Stripe keys configured** — Use live keys, not test keys
  - Go to `/admin/settings` or `config/config.php`
  - Verify webhook endpoint: `https://yourdomain.com/webhook/stripe`
  - Test webhook: Send test event from Stripe Dashboard (Events → Test Webhook)

- [ ] **Email working** — Test by completing a test order
  - Check both inbox and spam folder
  - If not received, verify SMTP config or ask host to enable `mail()`
  - See [docs/EMAIL.md](EMAIL.md) for troubleshooting

- [ ] **Cron configured** — Set up automatic background jobs
  - Option A (shared hosting): Configure cron in cPanel to call URL
    - URL: `https://yourdomain.com/cron/{security.cron_secret}`
    - Interval: Every 5 minutes
  - Option B (VPS/Linux): Add cron job
    - Command: `*/5 * * * * php /path/to/app/cron/run.php`
    - Verify: Check `photos` table for derivatives being generated

### Performance & Storage

- [ ] **Database backups automated** — Before launch, set up regular backups
  - Shared hosting: Use cPanel → Backup or contact host
  - VPS: Set up `mysqldump` via cron, or use managed backup service

- [ ] **Storage space monitored** — Understand your limits
  - Shared hosting: ~200GB typical limit
  - Derivatives auto-cleanup after 7 days (saves 40% of storage)
  - Monitor: Check `storage/` folder size regularly

- [ ] **Database optimized** — If hosting high-traffic event
  - Check: `docs/architecture.md` section 1 (indexes on orders, photos, downloads)
  - All indexes should be in place from `migrations/001_initial_schema.sql`

### Operational Readiness

- [ ] **You understand the admin flows** — Can you:
  - [ ] Create an event?
  - [ ] Upload photos?
  - [ ] Tag photos?
  - [ ] Publish an event?
  - [ ] View sales dashboard?
  - [ ] Export data for compliance?

- [ ] **You have a communication plan** — If something breaks:
  - [ ] Know your host's support contact (phone/email/ticket)
  - [ ] Have notes on installation method (Docker, MAMP, shared hosting)
  - [ ] Know where logs are stored (and how to access them)

- [ ] **You've tested the full customer flow**
  - [ ] Visit home page → see gallery
  - [ ] Click photo → lightbox opens
  - [ ] Add photo to cart → cart updates
  - [ ] Proceed to checkout → Stripe form appears
  - [ ] Complete order with test card `4242 4242 4242 4242` → receipt email received
  - [ ] Download link works → original file downloads (unwatermarked)

### Documentation & Compliance

- [ ] **Terms of Service posted** — Let customers know your refund policy
- [ ] **Privacy Policy posted** — Explain: customer data is stored for orders, audit logs contain IPs
- [ ] **Data retention policy** — How long do you keep customer data after refund?
  - Recommended: At least 1 year for tax/payment records
  - Download links expire after 30 days (enforced in code)

- [ ] **You understand GDPR** — If you have EU customers:
  - Customer IPs logged in audit trail (for security)
  - Download links are personal and audit-logged
  - Implement data deletion on request: see [docs/SECURITY.md](SECURITY.md)

## Go-Live (Day One)

1. **Verify everything one more time:**
   ```bash
   php verify-setup.php
   ```
   Output should show all ✓ checks passing.

2. **Test a live order:**
   - Create a test event and upload a photo
   - Complete a full checkout (Stripe will use test card, no actual charge)
   - Verify receipt email arrives within 5 seconds
   - Download the file

3. **Announce to your audience** — But keep the first orders small
   - Start with a small test event (10-20 photos)
   - Get feedback on the experience
   - Scale up after confirming everything works

4. **Monitor the first 24 hours:**
   - Check `/admin/stats` for orders flowing in
   - Check `/admin/audit-log` for any errors
   - Monitor inbox for email bounces (spam)
   - Watch your server's disk space

## Post-Launch (Ongoing)

### Daily
- [ ] Check for new orders in `/admin/stats`
- [ ] Spot-check a download link is working

### Weekly
- [ ] Review `/admin/audit-log` for anything unusual
- [ ] Check server disk space
- [ ] Test that cron is running: look for recent derivative generation in database

### Monthly
- [ ] Export orders and photos for personal record (`/admin/export`)
- [ ] Review rate-limiting stats (look for attack attempts in audit log)
- [ ] Test backup restore process (verify backups are working)

### Before Each Major Event
- [ ] Test with a small batch of photos
- [ ] Verify cron is running (watch derivatives process)
- [ ] Check that email is working (send test order)
- [ ] Confirm Stripe live keys are in place (not test keys)

## Scaling & Growth

### If you hit storage limits:
1. Archive old events (export photos/metadata, delete from gallery)
2. Upgrade hosting plan (increase storage quota)
3. Consider CDN for photo delivery (not built-in, but compatible)

### If you hit traffic limits:
1. Check `/admin/audit-log` for DoS/brute-force attacks → report to host
2. Upgrade to VPS or dedicated server (shared hosting has CPU limits)
3. Add caching (not built-in, but nginx/CloudFlare can help)

### If you need more features:
- See `README.md` "What's Left" section
- Open an issue on GitHub for feature requests
- Consider hiring a developer familiar with plain PHP

## Troubleshooting Launch Issues

### Orders not being created
- [ ] Stripe webhook secret in `/admin/settings` matches Stripe Dashboard
- [ ] Webhook URL is correct: `https://yourdomain.com/webhook/stripe`
- [ ] Test webhook from Stripe Dashboard: does it create an order?

### Cron not running
- [ ] Verify setup: `php verify-setup.php` should show cron status
- [ ] Check cPanel cron log or hosting control panel
- [ ] Test manually: Visit `https://yourdomain.com/cron/{secret}` in browser
- [ ] If manual works but cron doesn't: host may have cron disabled

### Email not sending
- [ ] Test manually: complete an order, check inbox + spam
- [ ] Verify config: `/admin/settings` shows SMTP host (or blank for mail())
- [ ] If using SMTP: try a different provider (Gmail, SendGrid, SES)
- [ ] Ask host: is `mail()` function enabled? Some hosts disable it for security

### Derivatives not generating
- [ ] Check cron is running (see above)
- [ ] Check permissions: `storage/hires/` and `public/media/d/` must be writable
- [ ] Check disk space: derivatives need storage
- [ ] Manual test: `php app/cron/run.php` should generate derivatives

### Photos not showing on public gallery
- [ ] Check event is **published** (Events → Published toggle)
- [ ] Check photos are **tagged** (Tagging → assign photos to event)
- [ ] Check derivatives generated (see above)
- [ ] Clear browser cache (Ctrl+Shift+Delete)

## Support & Help

If something breaks:

1. **Check the logs:**
   - Error log: Ask host where logs are (usually in `/logs/` or cPanel)
   - Application log: Check for PHP errors

2. **Verify setup:**
   ```bash
   php verify-setup.php
   ```

3. **Check audit log:**
   - `/admin/audit-log` shows if webhooks are failing
   - Look for `webhook_verify_failed` entries

4. **Ask for help:**
   - GitHub Issues: https://github.com/hazzaattemptscoding/open-source-gallery
   - Make sure you mention: hosting type, error message, what you were doing

---

**Built by PowerMedia.** Used in production since 2024.  
**License:** AGPL-3.0 (see LICENSE file)
