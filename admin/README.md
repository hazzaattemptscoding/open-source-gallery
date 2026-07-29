# Admin Panel (Remote Mode)

This directory is the entry point when running the admin panel in **remote mode** — on a separate server from your public gallery.

## In Local Mode (Default)

If you're using `admin_mode = 'local'` (the default), the admin panel runs on the same server as your public gallery. You don't use this directory; instead, access admin at `/admin/` on your regular domain.

## In Remote Mode

If you've set `admin_mode = 'remote'`, this `admin/index.php` becomes your admin entry point:

```bash
# Option 1: Development (using PHP built-in server)
php -S localhost:8001 -t admin/

# Option 2: Production (deploy admin/ directory to a separate web server)
# Then access via: http://admin.yourdomain.com/
```

### Requirements for Remote Mode

1. **phpseclib** — For SFTP file access. Install with:
   ```bash
   composer require phpseclib/phpseclib
   ```

2. **config/config.php** — Must have `admin_mode = 'remote'` and valid `admin_remote` settings pointing to your production database and SFTP server

3. **Remote MySQL access** — Your hosting provider must allow connections from your admin machine's IP address

4. **SFTP access** — To upload and manage files on the production server

### Setup Steps

See **[INSTALL.md - Advanced: Remote Admin Mode](../INSTALL.md#advanced-remote-admin-mode-optional)** for detailed setup instructions.

## Code Reuse

The admin panel here reuses all the same controllers and views from `app/controllers/admin/` and `app/views/admin/`. The only difference is:
- Connects to a remote database (instead of local)
- Manages files via SFTP (instead of local filesystem)
- All other functionality is identical to local mode

## Security Notes

- SFTP credentials in `config/config.php` should be restricted to read-only or file-upload-only access
- Keep `config/config.php` safe — it contains database and SFTP credentials
- Consider using SSH key authentication instead of passwords (set `sftp_key_file` in config)
- The public gallery server returns 404 for any `/admin/*` requests when in remote mode
