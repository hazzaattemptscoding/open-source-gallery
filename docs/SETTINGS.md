# Settings Guide

All gallery configuration is managed through the admin settings panel at `/admin/settings`. Settings are organized by category with two modes: **Basic** (essential settings) and **Advanced** (power user options).

## Basic Settings (Getting Started)

### Site Settings

**Gallery Name**
- Your photo gallery's display name
- Shown in browser title, header, and branding
- Example: "PowerMedia Photography"

**Tagline**
- Short descriptive text about your gallery
- Optional but recommended for brand identity
- Example: "Motorsport Photography at Its Best"

**Site Description**
- Longer description for SEO and social sharing
- Shows when your site is shared on social media
- Use relevant keywords for better search visibility

**Contact Email**
- Email address customers can reach you at
- Displayed in footer and contact forms
- Use monitored email address

### Currency Settings

**Currency Code**
- ISO 4217 code for your pricing currency
- Examples: GBP (British Pound), USD (US Dollar), EUR (Euro), JPY (Japanese Yen)
- Cannot be changed after you have orders

**Currency Symbol**
- Symbol displayed next to all prices (£, $, €, etc.)
- Appears in cart, checkout, and order confirmation

### Email Settings

**Enable Email Notifications**
- Turn on/off all transactional emails to customers
- Recommended: ON for good customer experience
- When OFF: No order confirmations, receipts, or shipping updates

**From Name**
- Name shown as email sender
- Example: "PowerMedia Gallery Support"
- Appears as "From: PowerMedia Gallery Support <noreply@...>"

**From Address**
- Email address emails are sent from
- Use a noreply@ address or no-reply@yourdomain.com
- Must be able to receive bounce notifications

### Stripe Payment Settings

**Stripe Mode**
- `test` - Test transactions (no real charges)
- `live` - Real customer payments (CAREFUL: this is live money)

**Test Publishable Key**
- Get from Stripe Dashboard > Developers > API Keys
- Safe to share publicly (used in checkout forms)

**Test Secret Key**
- Keep this SECRET - never share or commit to Git
- Used for backend processing
- Stripe requires this for payment webhooks

### Photo Settings

**Enable Watermarks**
- Show watermark on gallery preview images (800px and larger)
- Watermark not applied to purchased files
- Recommended: ON to protect preview images

**Preview Image Width**
- Size of gallery preview images in pixels
- Larger = more detail, longer load times
- Recommended: 800px

**Max Upload Size**
- Maximum file size for photo uploads in MB
- Your server may have limits (check hosting provider)
- Recommended: 100MB for high-res photos

**Allowed Formats**
- File types customers can upload
- Common: jpg, jpeg, png, heic
- Add others as needed (but jpg/png recommended)

### Search Settings

**Enable Search**
- Allow customers to search photos by filename, tags, etc.
- Recommended: ON for galleries with 50+ photos
- OFF: Hides search box from public site

### API Settings

**Enable REST API**
- Allow third-party apps to access your photos via API
- Required if using mobile apps or integrations
- Recommended: ON if you have integrations

---

## Advanced Settings (Power Users)

### Advanced Site Settings

**Timezone**
- Your timezone for displaying times in admin panel
- Examples: UTC, America/New_York, Europe/London, Asia/Tokyo
- Affects when scheduled tasks run

**Language**
- Primary language code (currently unused, for future localization)
- Options: en (English), fr (French), de (German), etc.

### Advanced Email Settings

**Use SMTP**
- Use SMTP server instead of PHP's mail() function
- Recommended: YES if hosting provider's mail() is unreliable

**SMTP Host**
- Mail server hostname
- Example: smtp.gmail.com, mail.yourdomain.com

**SMTP Port**
- Mail server port
- Common: 587 (TLS), 465 (SSL), 25 (unencrypted)
- Recommended: 587

**SMTP Username / Password**
- Authentication for SMTP server
- Keep password secure in database (not config.php)

### Advanced Security Settings

**Session Timeout**
- Minutes of inactivity before admin session expires
- Default: 60 minutes
- Shorter = more secure but requires more logins

**Password Minimum Length**
- Minimum characters required for admin passwords
- Default: 8 characters
- Increase for higher security

**Require Two-Factor Authentication**
- Force all admins to use 2FA
- Recommended: YES for security-conscious galleries
- Affects all admin accounts

**IP Whitelist**
- Restrict admin access to specific IP addresses
- Leave empty to allow all IPs
- Comma-separated: 192.168.1.1,192.168.1.2
- Useful for office-only galleries

### Advanced Photo Settings

**Thumbnail Width**
- Size of thumbnail images in pixels
- Smaller = faster loading, less detail
- Default: 200px

### Advanced Print Settings

**Auto-route Orders**
- Automatically send approved orders to print provider
- If OFF: Orders must be manually submitted
- Recommended: ON if you trust the provider

### Advanced API Settings

**Rate Limit**
- Maximum API requests per hour per key
- Default: 1000 requests/hour
- Increase for high-volume integrations

**Public API Docs**
- Show API documentation at /docs/api
- Recommended: OFF unless you're public about having an API

### Advanced Currency Settings

**Decimal Places**
- Number of decimal places for prices
- Default: 2 (£1.50, $29.99)
- Some currencies use 0 or 3

---

## Managing Settings via CLI

You can also manage settings from the command line:

```bash
# List all settings in a category
php app/cli.php settings:list email

# Get a specific setting
php app/cli.php settings:get stripe mode

# Set a setting value
php app/cli.php settings:set stripe mode live
```

---

## Important Notes

⚠️ **Live Mode Stripe Keys**
- Before switching to live mode, ensure you've:
  - Tested thoroughly with test mode
  - Configured real Stripe webhook secrets
  - Set up proper error handling
  - Reviewed your privacy policy

⚠️ **Email Configuration**
- Test your email settings before relying on them
- Check spam folder for test emails
- Verify "From" address is valid for your domain

⚠️ **Security**
- Never share your Stripe secret key
- Use HTTPS (not HTTP) in production
- Keep admin passwords strong
- Enable two-factor authentication
- Review IP whitelist regularly

---

## Troubleshooting Settings

**Emails not sending?**
1. Check "Enable Email Notifications" is ON
2. Test SMTP configuration if using SMTP
3. Check spam folder for test emails
4. Review error logs in admin panel

**Stripe payments failing?**
1. Verify you're using correct mode (test vs live)
2. Check API keys are current and not rotated
3. Review Stripe dashboard for errors
4. Ensure webhook secret is configured

**Photos not uploading?**
1. Check "Max Upload Size" is large enough
2. Verify file format is in "Allowed Formats"
3. Check server disk space hasn't run out
4. Review error message in admin panel

---

## Next Steps

- **Customize Email Templates**: Personalize order confirmation, receipt, and shipping emails
- **Configure Stripe**: Set up Stripe account and add your API keys
- **Enable Features**: Decide which features (print, API, search) you need
- **Set Watermarks**: Customize watermark position and opacity for your gallery

For more help, see docs/TROUBLESHOOTING.md
