# Remote NAS Fulfillment Setup

For advanced users with a home NAS and always-on machine (Raspberry Pi, old laptop, etc.), you can store original photo files on the NAS and keep only preview images on the IONOS server.

## When to Use This

**Use NAS fulfillment if:**
- You have a home NAS (QNAP, Synology, etc) with hundreds of GB of original photos
- You have an always-on machine that can run the poller script (Raspberry Pi, etc)
- Your internet connection is stable enough for SFTP file transfers
- You want to minimize storage costs on the paid IONOS hosting

**Don't use NAS fulfillment if:**
- You have a small gallery (< 50 GB of originals)
- You're using shared hosting without reliable outbound SFTP access
- Your home network isn't stable
- You want instant downloads (NAS mode waits for WoL + transfer)

## Architecture

When `storage_mode` is set to `remote-nas`:

1. **Gallery preview images** (400px, 800px watermarked) stay on IONOS for fast display
2. **Original files** stay on home NAS, never uploaded to IONOS
3. On purchase, a **fulfillment job** is queued
4. **Poller script** on your always-on machine polls IONOS every 60-90 seconds
5. Poller **sends Wake-on-LAN** magic packet to wake the NAS
6. **NAS agent script** (your responsibility) detects wake event and pushes original files to IONOS over SFTP
7. IONOS **packages files** and emails customer a signed download link
8. After download or 72 hours, **files auto-delete** to free space

## Step 1: Enable Remote NAS Mode

Edit `config/config.php`:

```php
'storage_mode' => 'remote-nas',
```

The first time you enable this, run setup wizard to generate the poller API token.

## Step 2: Set Up Poller Script

The poller script runs on your always-on machine (Raspberry Pi, old laptop, etc). It must:
- Have PHP 8.1+ installed
- Have network access to IONOS (outbound HTTPS)
- Be able to send WoL packets on your local network

### 2a. Copy poller script

```bash
# On your always-on machine
scp gallery_owner@ionos.example.com:gallery/tools/poller.php ~/gallery-poller/poller.php
cd ~/gallery-poller
```

### 2b. Configure poller

Edit `poller.php` at the top:

```php
$SERVER_URL = 'https://gallery.example.com';      // Your IONOS server
$POLLER_TOKEN = 'token-from-setup-wizard';         // From setup wizard
$NAS_MAC_ADDRESS = 'aa:bb:cc:dd:ee:ff';            // Your NAS's MAC address
$NAS_BROADCAST = '192.168.1.255';                  // Your subnet broadcast
```

To find your NAS MAC address:
```bash
# On any machine on your network
arp -a | grep -i synology    # or qnap, etc
```

To find subnet broadcast, check your router. Common ones:
- Class C private: `192.168.x.255`
- Class A private: `10.0.0.255`

### 2c. Run poller

```bash
# Test run
php poller.php

# Or set up as systemd service
# Create /etc/systemd/system/gallery-poller.service:
[Unit]
Description=Gallery NAS Fulfillment Poller
After=network.target

[Service]
Type=simple
User=poller_user
ExecStart=/usr/bin/php /home/poller_user/gallery-poller/poller.php
Restart=always

[Install]
WantedBy=multi-user.target

# Enable and start
systemctl enable gallery-poller
systemctl start gallery-poller
```

Logs go to `poller.log` in the same directory.

## Step 3: Set Up NAS Agent Script

When the poller sends WoL and the NAS wakes up, your NAS needs a script that:
1. Detects it just woke up (or runs on a schedule)
2. Finds the requested original photo files
3. SFTP copies them to `storage/fulfillment_temp/` on IONOS

This is your responsibility — exact steps depend on your NAS model.

### Example for Synology

On your Synology NAS, create a scheduled task (Control Panel > Task Scheduler):

**Task name:** Gallery Fulfillment Sync

**Schedule:** Every 5 minutes (or on wake event if available)

**User:** root

**Script:**
```bash
#!/bin/bash

# Directories
NAS_ORIGINALS="/volume1/photos/gallery_originals"
IONOS_TEMP_STAGING="/mnt/ionos/storage/fulfillment_temp"

# SFTP credentials (set these in Synology environment)
SFTP_HOST="gallery.example.com"
SFTP_USER="fulfillment_agent"
SFTP_PASS="your-sftp-password"

# Find any pending fulfillment requests (you'd implement this via API or polling)
# For now, sync all recent photos

# Mount IONOS via SFTP (or sshfs, NFS, etc)
if ! mountpoint -q "$IONOS_TEMP_STAGING"; then
    sshfs $SFTP_USER@$SFTP_HOST:storage/fulfillment_temp $IONOS_TEMP_STAGING
fi

# Sync files (example: photos from last 24 hours)
find "$NAS_ORIGINALS" -mtime -1 -type f -print0 | \
  rsync --files-from=- --from0 "$NAS_ORIGINALS/" "$IONOS_TEMP_STAGING/"

# Umount when done
umount "$IONOS_TEMP_STAGING"
```

For QNAP, Unraid, or other NAS, the concept is the same: create a task that copies files from your NAS to the IONOS `storage/fulfillment_temp/` directory over SFTP.

### SFTP Access

You'll need SFTP credentials on the IONOS server. Create a dedicated user:

```bash
# On IONOS server, as admin
useradd fulfillment_agent -d /tmp -s /usr/sbin/nologin
chmod 700 /home/gallery/storage/fulfillment_temp
chown fulfillment_agent:fulfillment_agent /home/gallery/storage/fulfillment_temp
```

## Step 4: Verify Setup

1. **Check poller is running:**
   ```bash
   ps aux | grep poller.php
   tail poller.log
   ```

2. **Test WoL packet:**
   - Make NAS go to sleep
   - Watch poller logs
   - NAS should wake up when next job arrives

3. **Test file transfer:**
   - Manually place a file in `storage/fulfillment_temp/` from your NAS
   - Verify it appears on IONOS

4. **Test end-to-end:**
   - Buy a photo from the gallery
   - Check `/admin` for fulfillment job status
   - Wait for poller to pick it up
   - Verify download link is emailed

## Monitoring & Troubleshooting

### Poller not picking up jobs

```bash
tail -f poller.log
```

Check:
- Is poller script running? `ps aux | grep poller`
- Does `poller.php` have internet access to IONOS? `ping gallery.example.com`
- Is `$POLLER_TOKEN` correct? Regenerate in setup wizard if unsure

### NAS not waking up

- Test WoL manually: `wakeonlan aa:bb:cc:dd:ee:ff`
- Verify NAS has WoL enabled (check NAS BIOS/settings)
- Verify `$NAS_BROADCAST` is correct for your subnet
- Some networks block WoL — check router settings

### Files not transferring

- Test SFTP connection manually: `sftp fulfillment_agent@gallery.example.com`
- Check NAS agent script logs for errors
- Verify `storage/fulfillment_temp/` exists and is writable

### Stalled jobs

If a job doesn't complete within 15 minutes, admin gets an alert email. Check:
1. Is poller running? Restart if needed
2. Did NAS wake up? Check NAS logs
3. Did SFTP transfer work? Check both poller and NAS logs
4. Is firewall blocking SFTP? Check IONOS firewall rules

### Manual file upload

If automation fails, you can manually SFTP files to `storage/fulfillment_temp/` and the system will pick them up and email the customer.

## Costs & Performance

**Storage costs:**
- Preview images: ~2-5 MB per photo on IONOS
- Originals: Stay on NAS (no IONOS cost)
- Temp files: Auto-deleted after download or 72h

**Download speed:**
- Fast: Customer gets link within 15 min of purchase (WoL + SFTP transfer time)
- Slow: If NAS is asleep, add WoL wake time (typically < 2 min)

**Best practices:**
- Keep NAS in sleep mode normally (saves power)
- Poller automatically wakes it when needed
- Set NAS to sleep after 30 min of inactivity
- Monitor IONOS temp directory (should stay < 1 GB)

## Security Notes

- Poller token is API-only, grants no other access
- SFTP user can only write to `fulfillment_temp/`, not other directories
- Download links are time-limited and one-time use
- Files auto-delete — no permanent storage of originals on IONOS

## Disabling Remote NAS Mode

If you decide to switch back to local storage:

1. Edit `config/config.php`:
   ```php
   'storage_mode' => 'local',
   ```

2. Upload all original files to `storage/hires/` on IONOS

3. Existing fulfillment jobs will be ignored (poller won't see them)

4. New orders will use local downloads immediately
