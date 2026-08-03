# Troubleshooting Guide

Quick reference for common issues and solutions.

## Installation Issues

### "PHP 8.2+ required"
**Symptom:** Installer says your PHP version is too old.

**Solutions:**
- Ask your host to upgrade PHP (most hosts support 8.2+)
- If on shared hosting, there's usually a control panel selector for PHP version
- Check `php -v` to see your current version
- If stuck on PHP 7.x, you cannot use this gallery

### "pdo_mysql extension not found"
**Symptom:** "Missing PHP extension: pdo_mysql"

**Solutions:**
- Ask your host to enable the PDO MySQL extension
- Shared hosting (cPanel): Usually auto-enabled, contact support
- VPS/Linux: `apt-get install php8.2-mysql` or similar for your distro
- Docker: Already included in the image

### "Cannot create config/config.php"
**Symptom:** Install fails at config generation.

**Solutions:**
- Check directory permissions: `chmod 755 config/`
- Ensure you're running installer from the app root: `php install.php`
- If on shared hosting, check with your host about permission issues
- Try uploading files via FTP and running installer via SSH

### "Database connection failed"
**Symptom:** Can't connect to database during install.

**Check:**
- [ ] Database host/IP is correct (usually `localhost` on shared hosting)
- [ ] Database port is correct (usually 3306)
- [ ] Database username exists
- [ ] Database user password is correct (can be blank)
- [ ] Database server is running (shared hosting: usually always on)

**Shared hosting hints:**
- cPanel: Check in "MySQL Databases" to find your host
- Check welcome email from your host for database credentials
- Localhost usually works even if shown as a different hostname

---

## Runtime Issues

### "Internal Server Error (500)"
**Symptom:** Blank white page with 500 error, no specific message.

**Troubleshooting:**
1. Check error log (ask host where logs are located)
2. Run verification: `php verify-setup.php`
3. Most common cause: database connection lost

**If database connection lost:**
- Database may have restarted: refresh the page
- Database credentials changed: update config or database password
- Firewall blocked connection: contact host

**If database is fine:**
- Check config/config.php syntax: `php -l config/config.php`
- Check file permissions: `storage/`, `public/media/d/`, `config/` must be writable
- Check disk space: `df -h` should show available space

### "Database table missing"
**Symptom:** "Error: Table 'photo_gallery.photos' doesn't exist"

**Solution:**
Run the verification script, which will import the schema if missing:
```bash
php verify-setup.php
```

Or import manually:
- cPanel: phpMyAdmin → Import → select `migrations/001_initial_schema.sql`
- SSH: `mysql photo_gallery < migrations/001_initial_schema.sql`

### "Config file not found"
**Symptom:** Application won't start, "config/config.php not found"

**Solution:**
1. Did you run the installer? `php install.php`
2. If not, run it now
3. If yes, check if file exists: `ls config/config.php`
4. If it exists, check permissions: `ls -la config/config.php`
   - Should be readable by the web server (Apache/Nginx user)
   - Try: `chmod 644 config/config.php`

---

## Admin Issues

### "Incorrect password"
**Symptom:** Can't log in with your admin password.

**Reset:**
1. Direct database access (phpMyAdmin or SSH):
   ```sql
   -- Generate new password (only 1 admin needed):
   DELETE FROM admin_users;
   ```
2. Visit `/admin/setup` again to create new admin account
3. If you don't have database access, contact your host

### "TOTP code not working"
**Symptom:** "Invalid TOTP code" even though code is correct.

**Causes:**
1. Server time is wrong — have host check server time
2. TOTP secret was corrupted during save
3. You're using the wrong authenticator app

**Solutions:**
1. Check server time: `date` (should match your clock)
2. If drastically different, contact host to sync NTP
3. If clock is correct, you must disable/re-enable TOTP:
   - Only option: delete admin user and recreate (see above)

### "Export button not working"
**Symptom:** Export returns blank page or error.

**Check:**
- Disk space: `df -h` — derivatives need space to generate CSVs
- Memory: export is memory-intensive with large datasets
  - If 10,000+ orders, export may timeout
  - Ask host to increase PHP memory limit
- File permissions: `public/` must be writable for temp files

---

## Email Issues

### "Email not sending"
**Symptom:** No receipt email after order completion.

**Checklist:**
1. [ ] Check spam folder first (very common!)
2. [ ] Cron is running: `php verify-setup.php`
   - If cron isn't running, emails won't send (they're async)
3. [ ] Email configured: `/admin/settings` → SMTP section
   - If SMTP host is blank, uses `mail()` function
   - If `mail()` isn't enabled, contact host
4. [ ] Test manually: Complete a test order
   - Watch `/admin/audit-log` for email job status

**If cron is running but emails still not sending:**

Check the audit log for error details:
```sql
SELECT action, details, created_at FROM audit_log
WHERE action LIKE '%email%'
ORDER BY created_at DESC LIMIT 10;
```

Common SMTP issues:
- **Wrong password:** For Gmail, use App Password, not account password
- **Port blocked:** If on a corporate network, port 587 may be blocked
  - Try port 25 (unencrypted) or 465 (SSL)
  - Or switch to SendGrid/SES (usually work from anywhere)
- **Authentication failed:** Check username/password in `/admin/settings`
- **Domain reputation:** New domains may hit spam filters
  - Add SPF/DKIM records (ask your email provider how)

### "Emails going to spam"
**Symptom:** Emails send but customers find them in spam/junk.

**Fixes:**
1. Add SPF record for your domain (prevents spoofing)
2. Add DKIM record (proves you sent it)
3. Ask your email provider for setup instructions
4. Use a reputable provider (SendGrid, AWS SES better than `mail()`)

---

## Payment Issues

### "Stripe webhook not working"
**Symptom:** Orders don't complete or customers don't get receipts.

**Verify:**
1. [ ] Webhook secret in `/admin/settings` matches Stripe Dashboard
   - Stripe Dashboard → Webhooks → copy the secret exactly
2. [ ] Webhook URL is correct: `https://yourdomain.com/webhook/stripe`
   - Note: must be HTTPS, must be publicly accessible
3. [ ] Endpoint is receiving events
   - Stripe Dashboard → Webhooks → click → view Recent Attempts
   - Look for response code: should be 200 OK

**If webhook URL returns 404:**
- Check your domain works: `curl https://yourdomain.com/` should work
- Check routing: Is Apache mod_rewrite enabled? (should be)
- Check that the path matches the route in `public/index.php`

**If webhook URL returns 500:**
- Check error log for database connection issue
- Check that orders table is created: `php verify-setup.php`
- Check config/config.php has correct Stripe secret key

### "Stripe test card not working"
**Symptom:** Card gets declined during checkout.

**Check:**
1. [ ] Using test key, not live key (Stripe Dashboard → API Keys → Key starts with `pk_test_`)
2. [ ] Using test card exactly: `4242 4242 4242 4242`
3. [ ] Expiration in future (any future date works: 12/25)
4. [ ] CVC: any 3 digits (e.g., 123)

**For other test scenarios:**
- See Stripe docs: https://stripe.com/docs/testing

---

## Photo/Upload Issues

### "Photos not showing after upload"
**Symptom:** Upload succeeds but photos don't appear in gallery.

**Checklist:**
1. [ ] Event is **published** (Events → Published = Yes)
2. [ ] Photos are **tagged** to event (Tagging → assign to event)
3. [ ] Cron is running (derivatives must be generated)
   - Check: `php verify-setup.php`
   - Manual test: `php app/cron/run.php`
4. [ ] Derivatives generated (check public/media/d/ folder has files)

**If photos are uploaded but not tagged:**
- Go to `/admin/photos/tags`
- Use bulk-assign to tag them to your event
- Cron will generate derivatives asynchronously

### "Derivatives not generating"
**Symptom:** Photos show but are blurry/missing different sizes.

**Check:**
1. [ ] GD library installed: `php -m | grep gd`
   - If not listed, ask host to enable GD
2. [ ] Cron is running: `php verify-setup.php`
   - If not, derivatives won't generate at all
3. [ ] Disk space: `df -h` — must have space for derivatives
4. [ ] Permissions: `public/media/d/` must be writable
   - Try: `chmod 755 public/media/d/`

**Manual test:**
```bash
php app/cron/run.php
```

If this shows errors, derivatives have a problem. Check error output.

### "Upload says "File too large"
**Symptom:** File upload fails with size error.

**Limits:**
- Upload chunk size: 2MB max per request (built-in)
- PHP max upload: usually 128MB (ask host if you need more)
- Total file size: no limit, uploads are chunked
- Resumable: if upload fails, you can restart it

**Solution:**
- Try uploading smaller image (e.g., re-export from Lightroom at lower quality)
- Ask host to increase PHP `upload_max_filesize` in php.ini

---

## Performance Issues

### "Gallery slow / times out"
**Symptom:** Page loading is very slow or times out.

**Causes:**
- [ ] Too many photos on one event page (1000+)
  - Solution: Split into multiple events
- [ ] Derivatives very large (uploading huge originals)
  - Solution: Resize photos before uploading (5-10MB ideal)
- [ ] Cron taking too long (blocking other requests)
  - Solution: ensure you have enough server resources

**Check:**
- Database indexes: `docs/architecture.md` section 1 should have them
- Server resources: ask host about CPU/RAM limits

### "Photos take forever to process"
**Symptom:** After upload, derivatives take hours to generate.

**Check:**
- [ ] Cron is running: `php verify-setup.php`
- [ ] Server CPU/RAM limits: ask host
  - VPS: should have plenty (4GB+ RAM, 2+ CPU cores)
  - Shared hosting: may be slower (but should still work)

**Workaround:**
- Run cron manually: `php app/cron/run.php`
- Run multiple times if queue is large

---

## Database Issues

### "MySQL command not found" on Mac
**Symptom:** Trying to run MySQL commands, but `mysql` command doesn't exist.

**Solution:**
- MAMP's MySQL isn't in your PATH
- Use phpMyAdmin instead (included with MAMP)
- URL: `http://localhost:8888/phpmyadmin`
- Or use adminer (web-based, no setup needed)

### "Database grows too large"
**Symptom:** Storage quota being exceeded.

**Check:**
- [ ] How large is the database? `SHOW TABLE STATUS;` in phpMyAdmin
- [ ] How much space are derivatives using? All sizes are kept for the life of the
  photo, so they grow with the library rather than being pruned
  - Check: `du -sh public/media/d`
- [ ] Are audit logs filling up?
  - Audit logs grow with every action (can be trimmed if needed)

**Solutions:**
1. Archive old events (export + delete)
2. Upgrade storage quota with host
3. Delete old derivatives manually (not recommended unless necessary)

---

## Security Issues

### "Seeing suspicious login attempts"
**Symptom:** Audit log shows failed login attempts from unknown IPs.

**What to do:**
1. Check `/admin/audit-log` for pattern:
   - Many attempts = brute force attack (normal, expected)
   - Different email = someone trying to find valid admin email
2. Your strong password protects you: rate limiting stops brute force
3. Rate limit: 5 login attempts per IP per hour (enforced)

**Optional:**
- Change password if you're concerned

### "What if my account is compromised?"
**Symptoms:** Unauthorized orders, audit log shows strange activity, etc.

**Immediate actions:**
1. [ ] Change your password immediately
2. [ ] Check audit log: see what was accessed
3. [ ] Contact Stripe: report unauthorized charges if any
4. [ ] Check email config: make sure it still uses your email

**Investigation:**
- Review `/admin/audit-log` for the past week
- Look for unusual `export` or `setting_update` actions
- Check `/admin/orders` → recent orders
- If suspicious orders exist, refund them via Stripe Dashboard

### "Can't reset password"
**Symptom:** Locked out of admin account.

**Only option:**
1. Direct database access (SSH or phpMyAdmin):
   ```sql
   DELETE FROM admin_users;
   ```
2. Visit `/admin/setup` to create new admin account
3. If you don't have database access, contact your host

---

## Getting Help

If you get stuck:

1. **Check this guide** — you probably found the answer
2. **Run verify script:** `php verify-setup.php`
3. **Check error logs** — ask host where they're stored
4. **Check audit log:** `/admin/audit-log` shows exactly what failed
5. **GitHub Issues:** https://github.com/hazzaattemptscoding/open-source-gallery

When opening an issue, include:
- Your hosting type (shared hosting, VPS, Docker, local)
- Error message (exact text)
- Steps to reproduce
- Output of `php verify-setup.php`

---

**Built by PowerMedia.** Proven in production since 2024.
