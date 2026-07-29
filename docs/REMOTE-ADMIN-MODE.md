# Remote Admin Mode Architecture

## Overview

Remote admin mode allows the admin panel to run on a **separate server** from the public gallery while maintaining complete code reuse. The default (local) mode is unchanged—everything runs together on one server.

### Use Cases

- **Separation of concerns:** Admin panel isolated from public-facing application
- **Dev environment:** Administer production gallery from your local machine
- **Security:** Admin server can be hidden behind VPN or restricted IP access
- **Deployment flexibility:** Update admin and public separately

### Key Principle

**All functionality is identical.** Remote mode reuses every controller, view, and business logic from the local admin panel. The only differences are:
- Database connection (local filesystem → remote MySQL)
- File operations (local filesystem → SFTP)
- Entry point (public/index.php → admin/index.php)

## Architecture

### Default: Local Mode

```
┌─────────────────────────────────┐
│  Shared Hosting / Single Server │
│                                 │
│  public/index.php ───┐          │
│      ├─ Gallery      │          │
│      └─ Downloads    │          │
│                      ├─ Database│
│  /admin/login ───┐   │          │
│      ├─ Events   │   │          │
│      ├─ Upload   │   │          │
│      ├─ Bulk ops ├──→│          │
│      └─ Settings │   │          │
│                      │          │
│  /storage (files) ───┤          │
│                      │          │
└─────────────────────────────────┘
```

`admin_mode = 'local'` (default) — Everything runs on one server.

### Remote Mode

```
┌──────────────────────────┐      ┌────────────────────────────┐
│  Your Admin Machine      │      │  Production / Shared Host  │
│  (Laptop, Home PC, etc)  │      │                            │
│                          │      │  public/index.php ┐        │
│  admin/index.php ────────┼──────┼──→ Gallery        │        │
│  ├─ Events               │      │    Downloads      │        │
│  ├─ Upload               │      │                   ├─ DB    │
│  ├─ Bulk ops   ──SFTP───┼──────┼──→ /storage (←──) │        │
│  └─ Settings ──MySQL─────┼──────┼──→ Database       │        │
│                          │      │                   │        │
│  config.php (remote)     │      │  config.php      │        │
│  ├─ db_host: prod_ip     │      │  admin_mode:     │        │
│  ├─ sftp_host: prod_ip   │      │  'remote'        │        │
│  └─ storage_path: /...   │      │                  │        │
│                          │      └────────────────────────────┘
└──────────────────────────┘
```

`admin_mode = 'remote'` — Admin runs separately, connects over network.

## Configuration

### Local Mode Config

In `config/config.php`:
```php
'admin_mode' => 'local',  // or omit (default)

// Used by admin:
'db' => [
    'host' => '127.0.0.1',
    'name' => 'photo_gallery',
    // ...
],
```

### Remote Mode Config

**On production server** (`config/config.php`):
```php
'admin_mode' => 'remote',
```

When set to `'remote'`, the public site:
- Returns 404 for any `/admin/*` request
- No admin panel accessible
- Continues to serve gallery normally

**On admin machine** (`config/config.php`):
```php
'admin_mode' => 'remote',

'admin_remote' => [
    // Connect to production database
    'db_host'  => '192.0.2.1',      // Production IP/hostname
    'db_port'  => 3306,
    'db_name'  => 'photo_gallery',
    'db_user'  => 'gallery_remote',
    'db_pass'  => 'secure_password',

    // SFTP for file operations
    'sftp_host'      => '192.0.2.1',
    'sftp_port'      => 22,
    'sftp_user'      => 'your_sftp_user',
    'sftp_pass'      => 'sftp_password',
    'sftp_key_file'  => '',  // Or: '/home/user/.ssh/id_rsa'
    'storage_path'   => '/home/username/public_html/storage',
],
```

## Entry Points

### Local Mode

Browser requests:
- Public site: `https://example.com/`
- Admin: `https://example.com/admin/login`

Entry point: `public/index.php`

### Remote Mode

**Production server** receives only public requests:
```
GET / → public/index.php (serves gallery)
GET /admin/* → 404 (blocked)
```

**Admin machine** (separate):
```
# Development:
php -S localhost:8001 -t admin/

# Visit: http://localhost:8001/
```

Or deploy `admin/` directory to a separate web server:
```
GET / → admin/index.php (serves admin panel)
```

## Code Flow

### Choosing the Database

**Local mode** (`public/index.php` or `admin/index.php`):
```php
require __DIR__ . '/../app/bootstrap.php';
// → loads app/bootstrap.php
// → $pdo = db_connect($config['db']);  // Local MySQL
```

**Remote mode** (`admin/index.php`):
```php
require __DIR__ . '/../app/remote-bootstrap.php';
// → loads app/remote-bootstrap.php
// → if ('remote') { $pdo = create_remote_db_connection($config); }
// → connects to $config['admin_remote']['db_host']
```

### File Operations

All file operations go through the `Storage` class (`app/lib/storage.php`):

```php
$storage = new Storage($config);

// Local mode:
$storage->uploadFile($tmp, 'hires/photo.jpg');
// → Copies to /storage/hires/photo.jpg (local filesystem)

// Remote mode:
$storage->uploadFile($tmp, 'hires/photo.jpg');
// → Uploads via SFTP to /home/username/storage/hires/photo.jpg (production)
```

## Key Libraries

### `app/lib/remote-db.php`

Creates a PDO connection to a remote MySQL database:
```php
$pdo = create_remote_db_connection($config);
```

- Uses PDO (same as local mode)
- Throws exception if connection fails
- Validates connection with test query

### `app/lib/sftp.php`

Wraps phpseclib SFTP operations:
```php
$sftp = new RemoteSFTP($config, $storagePath);
$sftp->uploadFile($local, 'hires/photo.jpg');
$sftp->downloadFile('hires/photo.jpg', $local);
$sftp->deleteFile('hires/photo.jpg');
$sftp->fileExists('hires/photo.jpg');
```

- Supports password or key-based auth
- Recursively creates directories
- Lists remote files
- Throws exceptions on error

**Requires:** `composer require phpseclib/phpseclib`

### `app/lib/storage.php`

Abstracts local vs. remote:
```php
$storage = new Storage($config);
$storage->uploadFile(...);    // Works in both modes
$storage->deleteFile(...);
$storage->fileExists(...);
```

Automatically chooses:
- Local filesystem if `admin_mode = 'local'`
- SFTP if `admin_mode = 'remote'`

## Admin Controllers & Views

All existing controllers and views are reused without modification:
- `app/controllers/admin/*.php` — 100% unchanged
- `app/views/admin/*.php` — 100% unchanged

Both entry points (`public/index.php` for local, `admin/index.php` for remote) load the same controllers and produce identical results.

The admin panel has **zero awareness** of whether it's running locally or remotely.

## Security Considerations

### Local Mode

- Standard shared hosting assumptions
- All credentials in `config/config.php` local
- File uploads to local filesystem
- Same-server cron and webhooks

### Remote Mode

**Prerequisites (host must support):**
1. **Remote MySQL connections** — Most shared hosts don't allow by default. Requires:
   - Host enables the feature
   - Often restricted by IP address (e.g., only from your admin machine)
   - Contact support: "Can we enable remote MySQL connections restricted to IP X.X.X.X?"

2. **SFTP access** — Standard on all hosts
   - Use restricted user account if possible
   - Consider IP-based restrictions

**Best Practices:**
- Store `admin_remote` credentials in `config/config.php` on the admin machine only
- Use SSH key authentication instead of passwords (`sftp_key_file`)
- Restrict admin server's IP address at the host level
- Keep admin machine's `config/config.php` off version control
- Consider VPN/IP whitelist for admin server access

### Network & Performance

- Database queries travel over the network (milliseconds added)
- File uploads/downloads over SFTP (slightly slower than local)
- No encryption required in config transport (SFTP is encrypted)
- MySQL should use TLS if traffic crosses public internet

## Hosting Support Matrix

| Host | Local Mode | Remote Mode |
|------|-----------|------------|
| IONOS shared hosting | ✓ Yes | ? Ask support |
| GoDaddy | ✓ Yes | ? Limited |
| Bluehost | ✓ Yes | ? Check |
| DreamHost | ✓ Yes | ✓ Usually |
| Managed VPS | ✓ Yes | ✓ Yes |
| Docker | ✓ Yes | ✓ Yes |

**Remote mode requires:**
- Remote MySQL connections enabled (rare on shared hosting)
- SFTP/SSH access (standard everywhere)

## Deployment Scenarios

### Scenario 1: Shared Hosting (IONOS)

Use **local mode** (default, 5 minutes to set up).

Only use remote mode if your host explicitly supports remote MySQL connections and you verify they work.

### Scenario 2: Your Laptop → Production

Upload public gallery to shared host with:
```php
'admin_mode' => 'remote',
```

Run admin locally:
```bash
php -S localhost:8001 -t admin/
```

Configure `admin_remote` to point to production server.

### Scenario 3: Staging → Production

Deploy both servers with:
```php
'admin_mode' => 'local',
```

Each server has its own admin panel (staging admin, prod admin separate).

Or: deploy only public gallery to prod, admin to staging:
```bash
# Production
'admin_mode' => 'remote',

# Staging (where admin runs)
'admin_mode' => 'remote',
'admin_remote' => [
    'db_host' => 'prod.example.com',
    // ...
],
```

## Testing Remote Mode Locally

For development without a production server:

```bash
# Terminal 1: Run local gallery server
cd open-source-gallery
php -S localhost:8000 -t public/

# Terminal 2: Run local admin server
php -S localhost:8001 -t admin/

# Configure admin/config.php:
'admin_mode' => 'remote',
'admin_remote' => [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    // Same database as public (localhost works)
    // For SFTP: either use local filesystem or mock SFTP server
],
```

## Fallback & Error Handling

If remote connection fails:
- Database: Exception thrown, 500 response, error logged
- SFTP: Exception thrown, error logged, operation fails gracefully
- Upload form continues to function, stores error message

No data corruption or partial state. Either operation succeeds completely or fails cleanly.

## Future Enhancements

Possible improvements (not implemented):
- Caching layer for database queries
- Batch SFTP operations for performance
- Connection pooling for SFTP
- Automatic failover to local backup
- Encrypted config file format

Current implementation prioritizes simplicity and reliability.

## Troubleshooting

**"Failed to connect to remote database"**
- Verify `db_host`, `db_user`, `db_pass` are correct
- Check host allows remote MySQL from your IP
- Test with MySQL CLI: `mysql -h host -u user -ppass database`

**"SFTP authentication failed"**
- Verify `sftp_host`, `sftp_user`, `sftp_pass` are correct
- Test with SFTP client: `sftp user@host`
- Ensure SSH key file path is correct if using keys

**"phpseclib3 not installed"**
- Run: `composer require phpseclib/phpseclib`
- On shared host: request host install Composer

**"Permission denied" on file uploads**
- Check SFTP user permissions on `storage_path` directory
- Ensure `storage_path` exists and is readable/writable by SFTP user

**"404 for /admin on public server"**
- Verify `config/config.php` has `'admin_mode' => 'remote'` on production
- Public site should return 404 for `/admin/*` routes when remote mode is active

## References

- `INSTALL.md` — Setup instructions for remote mode
- `admin/README.md` — Admin entry point documentation
- `config/config.example.php` — Configuration template
- `app/lib/remote-db.php` — Remote database connection
- `app/lib/sftp.php` — SFTP file operations
- `app/lib/storage.php` — Storage abstraction layer
