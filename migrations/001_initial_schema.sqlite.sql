-- PowerMedia Gallery — SQLite initial schema
-- Mirrors MySQL schema from 001_initial_schema.sql
-- Target: SQLite 3.8.0+ (included with PHP PDO sqlite)
-- Conventions: utf8 text, money is integer pence, tokens are case-sensitive

PRAGMA foreign_keys = OFF;
PRAGMA journal_mode = WAL;

-- ---------------------------------------------------------------------------
-- Migrations bookkeeping
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS migrations (
    filename TEXT NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------------------
-- Settings: key/value
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    skey TEXT NOT NULL PRIMARY KEY,
    svalue TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO settings (skey, svalue) VALUES
    ('default_price_single_pence',  '500'),
    ('default_price_session_pence', '2000'),
    ('default_price_event_pence',   '3500'),
    ('watermark_enabled',           '1'),
    ('watermark_opacity',           '35'),
    ('watermark_scale',             '22'),
    ('watermark_position',          'bottom-right'),
    ('watermark_min_width',         '800'),
    ('download_link_days',          '30'),
    ('download_cap_multiplier',     '5');

-- ---------------------------------------------------------------------------
-- Admin user
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    totp_secret TEXT,
    totp_enabled INTEGER NOT NULL DEFAULT 0,
    totp_last_step INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------------------
-- Events and sessions
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    venue TEXT NOT NULL DEFAULT '',
    event_date DATE NOT NULL,
    is_published INTEGER NOT NULL DEFAULT 0,
    cover_photo_id INTEGER,
    price_single_pence INTEGER NOT NULL,
    price_session_pence INTEGER,
    price_event_pence INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cover_photo_id) REFERENCES photos(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_events_published_date ON events(is_published, event_date);
CREATE INDEX IF NOT EXISTS idx_events_published ON events(is_published);

CREATE TABLE IF NOT EXISTS sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id INTEGER NOT NULL,
    slug TEXT NOT NULL,
    name TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    cover_photo_id INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (event_id, slug),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE RESTRICT,
    FOREIGN KEY (cover_photo_id) REFERENCES photos(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------------------------
-- Photos
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_token TEXT NOT NULL UNIQUE,
    event_id INTEGER NOT NULL,
    session_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'processing',
    media_type TEXT NOT NULL DEFAULT 'photo',
    file_extension TEXT DEFAULT 'jpg' NOT NULL,
    original_filename TEXT NOT NULL,
    width INTEGER NOT NULL DEFAULT 0,
    height INTEGER NOT NULL DEFAULT 0,
    hires_size_bytes INTEGER NOT NULL DEFAULT 0,
    deriv_size_bytes INTEGER NOT NULL DEFAULT 0,
    taken_at DATETIME,
    sort_order INTEGER NOT NULL DEFAULT 0,
    view_count INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE RESTRICT,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_photos_event_status ON photos(event_id, status);
CREATE INDEX IF NOT EXISTS idx_photos_session_status ON photos(session_id, status, sort_order);
CREATE INDEX IF NOT EXISTS idx_photos_session_media ON photos(session_id, media_type, status, sort_order);
CREATE INDEX IF NOT EXISTS idx_photos_status_created ON photos(status, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_photos_view_count ON photos(status, view_count DESC, created_at DESC);

-- ---------------------------------------------------------------------------
-- Photo tags
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS photo_tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    photo_id INTEGER NOT NULL,
    kart_number TEXT,
    driver_name TEXT,
    class TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (photo_id, kart_number, driver_name, class),
    FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_tags_photo ON photo_tags(photo_id);
CREATE INDEX IF NOT EXISTS idx_tags_kart ON photo_tags(kart_number);
CREATE INDEX IF NOT EXISTS idx_tags_driver ON photo_tags(driver_name);
CREATE INDEX IF NOT EXISTS idx_tags_class ON photo_tags(class);

-- ---------------------------------------------------------------------------
-- Event entries (race entry list, for auto-filling driver/class from kart number)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS event_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id INTEGER NOT NULL,
    kart_number TEXT NOT NULL,
    driver_name TEXT NOT NULL DEFAULT '',
    class TEXT NOT NULL DEFAULT '',
    UNIQUE (event_id, kart_number, class),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------------
-- Uploads
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS upload_batches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id INTEGER NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS upload_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_id INTEGER NOT NULL,
    client_name TEXT NOT NULL,
    size_bytes INTEGER NOT NULL,
    chunk_size INTEGER NOT NULL,
    chunks_total INTEGER NOT NULL,
    chunks_received INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'uploading',
    error TEXT,
    photo_id INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES upload_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_upload_files_batch ON upload_files(batch_id, status);

-- ---------------------------------------------------------------------------
-- Jobs queue
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL,
    payload TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    attempts INTEGER NOT NULL DEFAULT 0,
    run_after DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_at DATETIME,
    last_error TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_jobs_claim ON jobs(status, run_after);

-- ---------------------------------------------------------------------------
-- Orders and commerce
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_token TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    currency TEXT NOT NULL DEFAULT 'GBP',
    total_pence INTEGER NOT NULL,
    stripe_checkout_id TEXT UNIQUE,
    stripe_payment_intent TEXT,
    paid_at DATETIME,
    refunded_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_orders_email ON orders(email);
CREATE INDEX IF NOT EXISTS idx_orders_status_created ON orders(status, created_at);

CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    item_type TEXT NOT NULL,
    photo_id INTEGER,
    session_id INTEGER,
    event_id INTEGER,
    description TEXT NOT NULL,
    unit_price_pence INTEGER NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    line_total_pence INTEGER NOT NULL,
    attrs TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE SET NULL,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE SET NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_items_order ON order_items(order_id);
CREATE INDEX IF NOT EXISTS idx_items_photo ON order_items(photo_id);
CREATE INDEX IF NOT EXISTS idx_items_event ON order_items(event_id);

CREATE TABLE IF NOT EXISTS download_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    max_downloads INTEGER NOT NULL,
    download_count INTEGER NOT NULL DEFAULT 0,
    revoked INTEGER NOT NULL DEFAULT 0,
    last_used_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_links_order ON download_links(order_id);

CREATE TABLE IF NOT EXISTS downloads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    photo_id INTEGER,
    zip_part INTEGER,
    ip BLOB,
    user_agent TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_downloads_order ON downloads(order_id);

CREATE TABLE IF NOT EXISTS order_zips (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    part_no INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'building',
    files_total INTEGER NOT NULL DEFAULT 0,
    files_added INTEGER NOT NULL DEFAULT 0,
    size_bytes INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (order_id, part_no),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------------
-- Webhooks and audit
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS webhook_events (
    id TEXT NOT NULL PRIMARY KEY,
    type TEXT NOT NULL,
    processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS rate_limits (
    bucket TEXT NOT NULL,
    rl_key TEXT NOT NULL,
    window_start DATETIME NOT NULL,
    hits INTEGER NOT NULL DEFAULT 1,
    PRIMARY KEY (bucket, rl_key)
);

CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor TEXT NOT NULL,
    action TEXT NOT NULL,
    entity_type TEXT,
    entity_id INTEGER,
    meta TEXT,
    ip BLOB,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_log(created_at);
CREATE INDEX IF NOT EXISTS idx_audit_entity ON audit_log(entity_type, entity_id);

-- ---------------------------------------------------------------------------
-- Stats
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stats_daily (
    stat_date DATE NOT NULL,
    event_id INTEGER NOT NULL,
    gallery_views INTEGER NOT NULL DEFAULT 0,
    photo_views INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (stat_date, event_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------------
-- Done
-- ---------------------------------------------------------------------------
PRAGMA foreign_keys = ON;

INSERT OR IGNORE INTO migrations (filename) VALUES ('001_initial_schema.sqlite.sql');
