# Backup and Restore

Automated backups run on a configurable cron schedule. Each backup includes a database dump and an archive of the `storage/hires/` directory containing all original uploaded photos.

## Backup Location

Backups are stored in `storage/backups/` with filenames:
- `database-YYYY-MM-DD-HH-MM-SS.sql` — database dump
- `storage-hires-YYYY-MM-DD-HH-MM-SS.tar.gz` — photo archive

Backups older than 7 days are automatically deleted.

## Scheduling Backups

To run backups daily via cron, add a job to the `jobs` table:

```php
$pdo->prepare('
    INSERT INTO jobs (type, payload, status, run_after)
    VALUES (?, ?, ?, CURRENT_TIMESTAMP)
')->execute(['backup', json_encode([]), 'pending']);
```

Or manually trigger via admin panel (future enhancement).

## Manual Backup

Create a backup outside the cron system:

```bash
# Database dump
mysqldump --single-transaction --quick gallery > backup-$(date +%s).sql

# Photo archive
tar -czf storage-hires-$(date +%s).tar.gz -C storage/ hires
```

## Restore from Backup

### Prerequisites

- Access to the database host (MySQL credentials or SQLite access)
- Read access to backup files in `storage/backups/` or a manual backup location
- Shell access or admin panel (future)

### Restore Database

```bash
# MySQL
mysql gallery < storage/backups/database-YYYY-MM-DD-HH-MM-SS.sql

# SQLite
sqlite3 storage/gallery.db < storage/backups/database-YYYY-MM-DD-HH-MM-SS.sql
```

### Restore Photos

```bash
# Extract to original location
tar -xzf storage/backups/storage-hires-YYYY-MM-DD-HH-MM-SS.tar.gz -C storage/

# Verify permissions (should be readable by web server)
chmod -R u+r,g+r storage/hires/
```

### Restore Derivatives (Optional)

Derivatives (thumbnails, mobile sizes) are regenerated on first access. To rebuild them immediately:

1. Clear the `derivatives/` directory: `rm -rf storage/derivatives/*`
2. Queue derivative jobs: Insert rows into `jobs` table with `type='derivative'` and photo IDs in payload
3. Run cron drain to process the queue

Or visit the admin health page and select "Regenerate derivatives" (future enhancement).

## Backup Integrity

After restore, verify:

1. **Database**: Check that recent orders and sessions exist
   ```sql
   SELECT COUNT(*) FROM orders;
   SELECT COUNT(*) FROM sessions;
   ```

2. **Photos**: Spot-check a few hires files exist and are readable
   ```bash
   ls -la storage/hires/*/original.* | head -10
   ```

3. **Derivatives**: If cleared, visit the gallery to trigger regeneration. Check that thumbnails load.

## Backup Retention

Backups are kept for 7 days by default. Modify the retention period in `app/lib/backup.php`, function `cleanup_old_backups()`:

```php
cleanup_old_backups($backupDir, 7 * 24 * 60 * 60); // 7 days in seconds
```

Change to e.g. `30 * 24 * 60 * 60` for 30-day retention.

## Troubleshooting

**Backups not running:**
- Check that cron is executing (see docs/architecture.md § 5)
- Verify `storage/backups/` is writable: `touch storage/backups/test.txt && rm storage/backups/test.txt`
- Check error logs for "backup:" messages

**Restore fails:**
- Ensure database credentials are correct and the user has CREATE/DROP table permissions
- For large databases, increase MySQL `max_allowed_packet`: `SET GLOBAL max_allowed_packet = 256M;`
- Verify backup file is not corrupted: `gzip -t storage/backups/*.tar.gz`

**Permission errors after restore:**
- Set correct ownership: `chown -R www-data:www-data storage/`
- Set readable permissions: `chmod -R 755 storage/`
