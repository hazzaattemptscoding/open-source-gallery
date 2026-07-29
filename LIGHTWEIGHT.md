# Lightweight Setup (No External Dependencies)

The whole point of self-hosted software is simplicity. This gallery now works with **zero database setup required**.

## The Problem

Before: "I want to preview this gallery."
Requirements: Docker OR MySQL server running OR shared hosting with control panel access

After: "I want to preview this gallery."
Requirements: PHP 8.2 (that's it)

```bash
php -S localhost:8080 -t public/
```

Done. That's the entire setup. No MySQL. No config file. No installer. Just a working gallery.

---

## How It Works

### SQLite (Default)

SQLite is a single-file database embedded in PHP. It works everywhere PHP works:
- macOS, Windows, Linux
- Shared hosting
- Docker containers
- Raspberry Pi
- Your laptop in a cafe

The database is just a file: `storage/gallery.sqlite`

**Advantages:**
- Zero setup
- Fast enough for thousands of photos
- Persistent (data survives server restarts)
- Can be backed up like any file
- Can be deleted and recreated anytime

**Limitations:**
- Good for <100 concurrent users
- Not suitable for heavily replicated clusters
- But honestly, you're probably self-hosted and don't need that

---

## Three Ways to Run

### 1. Instant (SQLite) — 10 seconds

```bash
php -S localhost:8080 -t public/
```

Your gallery is live.

### 2. Proper (Docker) — 30 seconds

```bash
docker-compose up
```

Apache, PHP, database, everything containerized.

### 3. Production (MySQL) — 5 minutes

```bash
php install.php
# Choose option 2 (MySQL)
# Answer prompts, done
```

MySQL is faster for huge galleries (10k+ photos) and better for shared hosting.

---

## From Local to Production

### Scenario 1: Deploy from SQLite to SQLite (Easy)

```bash
# Local: create your gallery
php -S localhost:8080 -t public/

# When happy, backup the database
cp storage/gallery.sqlite storage/gallery.sqlite.backup

# Upload to shared hosting:
# - Upload all files via SFTP
# - Set directory permissions: chmod 755 storage/
# - Visit yourdomain.com
# Done.
```

### Scenario 2: Test with SQLite, Go Live with MySQL (Flexible)

```bash
# Local: preview with SQLite
php -S localhost:8080 -t public/
# ... fill in some sample photos and tagging ...

# Production hosting: switch to MySQL
php install.php
# Choose option 2 (MySQL)
# Upload files
# Done.
```

Data is not transferred (photos, tags stay in your local SQLite), but the code and structure are identical.

### Scenario 3: Export as Static HTML (Archival)

```bash
# Create standalone HTML gallery
php generate-static.php output/

# Upload output/ to any static host
# - GitHub Pages
# - Netlify  
# - Vercel
# - AWS S3
# - Your shared hosting (even if it doesn't support PHP)
# Visitors never touch a database, ultra-fast
```

---

## Why This Matters

**The barrier to trying self-hosted software shouldn't be infrastructure.** 

You want to try a gallery before committing? 

```bash
php -S localhost:8080 -t public/
```

Now try it. No excuses, no setup, no "you need to install MySQL first."

You have a gallery with 100 photos and want to preserve it forever without paying anyone?

```bash
php generate-static.php archive/
```

Now you have pure HTML. Stick it on GitHub, burn it to a USB drive, email it to a friend. It works forever, no dependencies.

---

## Performance Notes

### SQLite
- Handles 1,000+ photos easily
- Can do 10-100 concurrent requests
- Fine for galleries under moderate traffic
- Better than MySQL for <5GB of data

### MySQL
- Scales to millions of photos  
- Better with heavy concurrent load
- Better on shared hosting if  it's already running
- Overkill for personal or small professional galleries

### Static HTML
- Infinitely fast (no server-side work)
- Can handle unlimited traffic
- Perfect for "published" galleries that don't change often
- Can be cached/CDN'd globally

---

## Migration Path

Start light, scale up only if you need to:

```
SQLite (dev)
    ↓
SQLite (production)
    ↓
MySQL (production) [if needed]
    ↓
Static HTML export (archival)
```

At each step, your photos and tags come with you. The code is the same.

---

## Technology Stack Now

- **PHP 8.2+** (required)
- **SQLite** (comes with PHP, zero config)
- OR **MySQL 5.7+** (optional, for large deployments)
- **Vanilla HTML/CSS/JavaScript** (no build tools, no Node)

No external dependencies. No package managers. No "npm install" drama. Code you can read and modify yourself.

---

## You Own Your Data

All your photos, tags, orders, everything lives in a database *you control*, on *your server*, in a *file you can backup*.

Not someone else's cloud. Not locked into anyone's API. Just you and your data.

---

## FAQ

**Q: Is SQLite really as good as MySQL?**  
For galleries under heavy traffic, MySQL is better. For most photographers, SQLite is faster and simpler.

**Q: Can I switch from SQLite to MySQL later?**  
Not automatically (different schemas). But you'd just:
1. Export photos + tags from SQLite
2. Create new gallery in MySQL
3. Re-import (script would be simple)

**Q: What if I lose my SQLite file?**  
Same risk as losing your MySQL server. Backup `storage/gallery.sqlite` like any important file. That's it.

**Q: Can I store the database on Dropbox/Google Drive?**  
Don't do that (SQLite has locking issues on cloud sync). Keep it on the server.

**Q: Will SQLite slow down with thousands of photos?**  
No. It's still fast. It'll hit performance limits around 100k-1M photos depending on the query, but you'll scale to MySQL by then.

---

## Simple Rules

1. **Start with SQLite.** Zero friction, zero config.
2. **Backup your database file regularly.** Like any file.
3. **If you need to scale, switch to MySQL.** Code stays the same.
4. **If you want archival, export to static HTML.** Zero dependencies forever.

That's it.
