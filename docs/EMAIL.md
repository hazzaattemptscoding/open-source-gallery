# Email Configuration

PowerMedia Gallery sends transactional emails for order receipts and refund confirmations. Emails are rendered from professional HTML templates and can be delivered via `mail()` (shared hosting) or SMTP (VPS/dedicated).

## Default Setup (Shared Hosting)

By default, emails use the server's `mail()` function, which relies on the host's sendmail configuration:

```php
// config/config.php
'smtp' => [
    'host' => '',  // Leave empty to use mail()
    'port' => 587,
    'user' => '',
    'pass' => '',
    'from_email' => 'noreply@yourdomain.com',
    'from_name' => 'Your Gallery Name',
],
```

Most shared hosts (IONOS, GoDaddy, etc.) have `mail()` pre-configured. Test by uploading a photo and completing a test order—you should receive a receipt email.

**Troubleshooting `mail()` on shared hosting:**
- Email not sent? Check your host's spam folder first
- Host blocks outbound mail? Contact support to enable it (some hosts disable it by default)
- Want to be sure? Use SMTP instead (see below)

## Advanced Setup (SMTP)

If `mail()` doesn't work or you want guaranteed delivery, configure SMTP:

```php
// config/config.php
'smtp' => [
    'host' => 'smtp.gmail.com',        // Your SMTP server
    'port' => 587,                      // 587 (STARTTLS) or 465 (SSL)
    'user' => 'your-email@gmail.com',   // SMTP username
    'pass' => 'your-app-password',      // SMTP password (not account password!)
    'from_email' => 'noreply@yourdomain.com',
    'from_name' => 'Your Gallery Name',
],
```

### SMTP Providers

**Gmail:**
1. Enable 2-Factor Authentication on your account
2. Go to [Google Account Security](https://myaccount.google.com/security)
3. Create an "App Password" for Mail
4. Use that password in the config (not your account password)

```php
'smtp' => [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'user' => 'your-email@gmail.com',
    'pass' => 'your-16-char-app-password',
    'from_email' => 'your-email@gmail.com',
    'from_name' => 'Your Gallery',
],
```

**SendGrid:**
1. Create a SendGrid account
2. Create an API key (unique SMTP password)
3. Use username `apikey`

```php
'smtp' => [
    'host' => 'smtp.sendgrid.net',
    'port' => 587,
    'user' => 'apikey',
    'pass' => 'SG.your-sendgrid-api-key',
    'from_email' => 'noreply@yourdomain.com',
    'from_name' => 'Your Gallery',
],
```

**AWS SES (Amazon Simple Email Service):**
```php
'smtp' => [
    'host' => 'email-smtp.eu-west-1.amazonaws.com',  // Use your region
    'port' => 587,
    'user' => 'your-ses-username',
    'pass' => 'your-ses-password',
    'from_email' => 'verified-sender@yourdomain.com',
    'from_name' => 'Your Gallery',
],
```

**Mailgun:**
```php
'smtp' => [
    'host' => 'smtp.mailgun.org',
    'port' => 587,
    'user' => 'postmaster@yourdomain.com',
    'pass' => 'your-mailgun-password',
    'from_email' => 'noreply@yourdomain.com',
    'from_name' => 'Your Gallery',
],
```

## Testing Email Configuration

After updating `config/config.php`, test by:

1. **Log in to admin panel** → `/admin`
2. **Create a test event** (Events → Create)
3. **Upload a test photo** and tag it
4. **Set a price** on the photo
5. **Publish the event** (Events → Edit → Published: Yes)
6. **Go to public gallery** and add photo to cart
7. **Checkout with test card:**
   - Card: `4242 4242 4242 4242`
   - Exp: Any future date (e.g., `12/25`)
   - CVC: Any 3 digits (e.g., `123`)
8. **Check your email** (subject: "Your order receipt")

Receipt should arrive within 5 seconds. If not:
- Check spam folder
- Check server error logs: `php verify-setup.php`
- Verify config syntax: `php -l config/config.php`

## Email Templates

Email templates are HTML files in `app/templates/emails/`:

- `receipt.html` — Order confirmation (sent after successful Stripe payment)
- `refund.html` — Refund notification (sent when a refund is processed)

To customize:
1. Edit the template file
2. Keep the `<?php echo e(...); ?>` tags for safe variable substitution
3. Restart cron or trigger a new order to test

**Available variables in receipt.html:**
- `$siteName` — Gallery name
- `$supportEmail` — Contact email
- `$orderNumber` — Order ID
- `$orderDate` — Purchase date
- `$items` — Array of photos purchased
- `$currencyCode` — Currency (e.g., "GBP")
- `$total` — Formatted total price
- `$downloadLink` — Download URL (valid 30 days)

**Available variables in refund.html:**
- `$siteName` — Gallery name
- `$supportEmail` — Contact email
- `$orderNumber` — Order ID
- `$refundWord` — "refund" or "partial refund"

## How Email Delivery Works

1. **User completes checkout** → Order created in database
2. **Stripe webhook received** → Email job queued (`jobs` table)
3. **Cron runs every 5 minutes** → Picks up pending jobs
4. **Email rendered from template** → All variables populated
5. **Email sent via mail() or SMTP** → Delivered to customer
6. **Job removed from queue** → On success
7. **On failure**, retried 3 times with exponential backoff (1min, 2min, 4min)

Failed jobs after 3 retries are marked `failed` in the database. Check with:
```sql
SELECT * FROM jobs WHERE status = 'failed' ORDER BY created_at DESC;
```

## Security

- **Email credentials stored in config** — Keep `config/config.php` readable only by the web server
- **SMTP passwords not in git** — `config/` is in `.gitignore`
- **Customer data in headers** — Email addresses and download links are audit-logged
- **No HTML injection** — All template variables escaped via `e()` helper

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Emails not sent | Check cron is running: `php verify-setup.php` |
| "SMTP connection failed" | Verify host, port, username, password. Test with telnet: `telnet smtp.gmail.com 587` |
| "AUTH LOGIN failed" | Check SMTP credentials. For Gmail, use App Password, not account password |
| Emails in spam | Add SPF/DKIM records for your domain (see provider docs) |
| Template renders blank | Check `app/templates/emails/receipt.html` exists and is readable |

## Disabling Email (Development)

To queue jobs but not send (useful for testing without needing SMTP):

```php
// Stub in app/lib/email.php
function send_email(array $config, string $to, string $subject, string $body, bool $isHtml = false): bool {
    return true;  // Pretend success without sending
}
```

Then restart cron and jobs will complete without errors.
