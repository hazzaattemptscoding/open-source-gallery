-- PowerMedia Gallery — initial schema
-- Target: MySQL 5.7+/8.0 or MariaDB 10.4+ (IONOS shared hosting).
-- Conventions: InnoDB, utf8mb4; money is integer pence; token columns are
-- ascii_bin (case-sensitive — base62 tokens must not collide case-insensitively);
-- no CHECK constraints (ignored on MySQL 5.7), integrity enforced in PHP + FKs.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Migrations bookkeeping (the runner inserts a row per applied file)
-- ---------------------------------------------------------------------------
CREATE TABLE migrations (
    filename    VARCHAR(120) NOT NULL PRIMARY KEY,
    applied_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Settings: key/value. Defaults for pricing, watermark config, etc.
-- ---------------------------------------------------------------------------
CREATE TABLE settings (
    skey        VARCHAR(64) NOT NULL PRIMARY KEY,
    svalue      TEXT NOT NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Placeholder defaults — Harry sets real prices in the admin before launch.
INSERT INTO settings (skey, svalue) VALUES
    ('default_price_single_pence',  '500'),
    ('default_price_session_pence', '2000'),
    ('default_price_event_pence',   '3500'),
    ('watermark_enabled',           '1'),
    ('watermark_opacity',           '35'),   -- percent
    ('watermark_scale',             '22'),   -- percent of image width
    ('watermark_position',          'bottom-right'),
    ('watermark_min_width',         '800'),  -- derivatives >= this width get the mark
    ('download_link_days',          '30'),
    ('download_cap_multiplier',     '5');    -- max_downloads = items * this

-- ---------------------------------------------------------------------------
-- Admin (single user, but a table keeps auth code normal)
-- ---------------------------------------------------------------------------
CREATE TABLE admin_users (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(190) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,              -- Argon2id
    totp_secret     VARCHAR(64)  NULL,                  -- base32; NULL until enrolled
    totp_enabled    TINYINT(1)   NOT NULL DEFAULT 0,
    totp_last_step  BIGINT       NULL,                  -- last accepted TOTP timestep (replay guard)
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Catalogue: events -> sessions -> photos (+ tags, + CSV entry list)
-- ---------------------------------------------------------------------------
CREATE TABLE events (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug                 VARCHAR(120) NOT NULL UNIQUE,
    title                VARCHAR(190) NOT NULL,
    venue                VARCHAR(190) NOT NULL DEFAULT '',
    event_date           DATE NOT NULL,
    is_published         TINYINT(1) NOT NULL DEFAULT 0,
    cover_photo_id       BIGINT UNSIGNED NULL,           -- FK added after photos exists
    price_single_pence   INT UNSIGNED NOT NULL,          -- copied from defaults at creation
    price_session_pence  INT UNSIGNED NULL,              -- NULL = bundle not offered
    price_event_pence    INT UNSIGNED NULL,              -- NULL = bundle not offered
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_events_published_date (is_published, event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id        INT UNSIGNED NOT NULL,
    slug            VARCHAR(120) NOT NULL,
    name            VARCHAR(190) NOT NULL,
    sort_order      INT NOT NULL DEFAULT 0,
    cover_photo_id  BIGINT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sessions_event_slug (event_id, slug),
    CONSTRAINT fk_sessions_event FOREIGN KEY (event_id) REFERENCES events (id)
        ON DELETE RESTRICT                              -- empty an event deliberately, never by accident
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE photos (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_token       CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    event_id           INT UNSIGNED NOT NULL,           -- denormalised (session implies it) for filter speed
    session_id         INT UNSIGNED NOT NULL,
    status             ENUM('processing','live','hidden','failed') NOT NULL DEFAULT 'processing',
    media_type         ENUM('photo','video') NOT NULL DEFAULT 'photo',
    file_extension     CHAR(3) CHARACTER SET ascii DEFAULT 'jpg' NOT NULL,
    original_filename  VARCHAR(255) NOT NULL,           -- display/reference only, never a path
    width              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    height             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    hires_size_bytes   INT UNSIGNED NOT NULL DEFAULT 0,
    deriv_size_bytes   INT UNSIGNED NOT NULL DEFAULT 0,
    taken_at           DATETIME NULL,                   -- from EXIF
    sort_order         INT NOT NULL DEFAULT 0,
    view_count         INT UNSIGNED NOT NULL DEFAULT 0, -- lightbox opens
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_photos_event_status (event_id, status),
    KEY idx_photos_session_status (session_id, status, sort_order),
    KEY idx_photos_session_media (session_id, media_type, status, sort_order),
    CONSTRAINT fk_photos_event FOREIGN KEY (event_id) REFERENCES events (id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_photos_session FOREIGN KEY (session_id) REFERENCES sessions (id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE events
    ADD CONSTRAINT fk_events_cover FOREIGN KEY (cover_photo_id) REFERENCES photos (id)
        ON DELETE SET NULL;
ALTER TABLE sessions
    ADD CONSTRAINT fk_sessions_cover FOREIGN KEY (cover_photo_id) REFERENCES photos (id)
        ON DELETE SET NULL;

-- Multiple rows per photo: a battle shot tagged kart 23 AND kart 47 appears in
-- both drivers' filtered links. Denormalised (driver/class alongside number) so
-- filters are one join and bulk tagging is a plain insert.
CREATE TABLE photo_tags (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    photo_id     BIGINT UNSIGNED NOT NULL,
    kart_number  VARCHAR(8)   NULL,                     -- '23', '7a' — text, not int
    driver_name  VARCHAR(120) NULL,
    class        VARCHAR(80)  NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tags_photo (photo_id),
    KEY idx_tags_kart (kart_number),
    KEY idx_tags_driver (driver_name),
    KEY idx_tags_class (class),
    CONSTRAINT fk_tags_photo FOREIGN KEY (photo_id) REFERENCES photos (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CSV-imported race entry list; tagging a kart number auto-fills driver/class from here.
CREATE TABLE event_entries (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id     INT UNSIGNED NOT NULL,
    kart_number  VARCHAR(8)   NOT NULL,
    driver_name  VARCHAR(120) NOT NULL DEFAULT '',
    class        VARCHAR(80)  NOT NULL DEFAULT '',      -- same number can race in two classes
    UNIQUE KEY uq_entries (event_id, kart_number, class),
    CONSTRAINT fk_entries_event FOREIGN KEY (event_id) REFERENCES events (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Chunked, resumable uploads (state in DB; chunk bytes staged on disk)
-- ---------------------------------------------------------------------------
CREATE TABLE upload_batches (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id  INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_batches_session FOREIGN KEY (session_id) REFERENCES sessions (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE upload_files (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    batch_id         INT UNSIGNED NOT NULL,
    client_name      VARCHAR(255) NOT NULL,
    size_bytes       INT UNSIGNED NOT NULL,
    chunk_size       INT UNSIGNED NOT NULL,
    chunks_total     SMALLINT UNSIGNED NOT NULL,
    chunks_received  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status           ENUM('uploading','assembling','done','failed') NOT NULL DEFAULT 'uploading',
    error            VARCHAR(255) NULL,
    photo_id         BIGINT UNSIGNED NULL,              -- set once assembled + validated
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_upload_files_batch (batch_id, status),
    CONSTRAINT fk_upload_files_batch FOREIGN KEY (batch_id) REFERENCES upload_batches (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_upload_files_photo FOREIGN KEY (photo_id) REFERENCES photos (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Job queue drained by cron (and the admin browser-assisted drain)
-- ---------------------------------------------------------------------------
CREATE TABLE jobs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    type        VARCHAR(40) NOT NULL,                   -- derivative | zip_build | email | cleanup
    payload     JSON NOT NULL,
    status      ENUM('pending','running','done','failed') NOT NULL DEFAULT 'pending',
    attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    run_after   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_at   DATETIME NULL,
    last_error  TEXT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_jobs_claim (status, run_after)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Commerce: orders, items, download access, logs, bundle zips
-- ---------------------------------------------------------------------------
CREATE TABLE orders (
    id                     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_token           CHAR(22) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    email                  VARCHAR(190) NOT NULL,
    status                 ENUM('pending','paid','failed','expired','refunded','partial_refund')
                               NOT NULL DEFAULT 'pending',
    currency               CHAR(3) NOT NULL DEFAULT 'GBP',
    total_pence            INT UNSIGNED NOT NULL,
    stripe_checkout_id     VARCHAR(255) NULL UNIQUE,
    stripe_payment_intent  VARCHAR(255) NULL,
    paid_at                DATETIME NULL,
    refunded_at            DATETIME NULL,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_orders_email (email),
    KEY idx_orders_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Phase 2 (prints) adds: addresses table + nullable orders.shipping_address_id,
-- order_items.fulfilment_status. Additive ALTERs only; no row rewrites.

CREATE TABLE order_items (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id          INT UNSIGNED NOT NULL,
    item_type         VARCHAR(20) NOT NULL,             -- photo | session_bundle | event_bundle | (print, phase 2)
    photo_id          BIGINT UNSIGNED NULL,
    session_id        INT UNSIGNED NULL,
    event_id          INT UNSIGNED NULL,
    description       VARCHAR(255) NOT NULL,            -- snapshot; survives photo/event deletion
    unit_price_pence  INT UNSIGNED NOT NULL,
    quantity          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    line_total_pence  INT UNSIGNED NOT NULL,
    attrs             JSON NULL,                        -- phase 2: {"size":"A3","finish":"lustre"}
    KEY idx_items_order (order_id),
    KEY idx_items_photo (photo_id),
    KEY idx_items_event (event_id),
    CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_items_photo FOREIGN KEY (photo_id) REFERENCES photos (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_items_session FOREIGN KEY (session_id) REFERENCES sessions (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_items_event FOREIGN KEY (event_id) REFERENCES events (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE download_links (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    token_hash      CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE, -- sha256(raw token)
    expires_at      DATETIME NOT NULL,
    max_downloads   SMALLINT UNSIGNED NOT NULL,
    download_count  SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- counts file fetches, not page views
    revoked         TINYINT(1) NOT NULL DEFAULT 0,
    last_used_at    DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_links_order (order_id),
    CONSTRAINT fk_links_order FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every file fetch, for the audit log and "has the customer downloaded yet".
CREATE TABLE downloads (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id    INT UNSIGNED NOT NULL,
    photo_id    BIGINT UNSIGNED NULL,                   -- NULL = a zip part
    zip_part    SMALLINT UNSIGNED NULL,
    ip          VARBINARY(16) NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_downloads_order (order_id),
    CONSTRAINT fk_downloads_order FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bundle archives, built by cron in time slices, split into ~1 GB parts.
CREATE TABLE order_zips (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id     INT UNSIGNED NOT NULL,
    part_no      SMALLINT UNSIGNED NOT NULL,
    status       ENUM('building','ready','failed') NOT NULL DEFAULT 'building',
    files_total  INT UNSIGNED NOT NULL DEFAULT 0,
    files_added  INT UNSIGNED NOT NULL DEFAULT 0,       -- resume cursor across cron slices
    size_bytes   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_zips_order_part (order_id, part_no),
    CONSTRAINT fk_zips_order FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stripe webhook idempotency: event ID is the primary key; duplicate delivery = no-op.
CREATE TABLE webhook_events (
    id            VARCHAR(255) NOT NULL PRIMARY KEY,    -- Stripe event id (evt_...)
    type          VARCHAR(64) NOT NULL,
    processed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Abuse controls, audit, stats
-- ---------------------------------------------------------------------------
-- Fixed-window rate limiting: one row per bucket+key, reset when the window lapses.
CREATE TABLE rate_limits (
    bucket        VARCHAR(32) NOT NULL,                 -- login | totp | checkout | download
    rl_key        VARCHAR(64) NOT NULL,                 -- ip / account / link id
    window_start  DATETIME NOT NULL,
    hits          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (bucket, rl_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor        ENUM('admin','system','public') NOT NULL,
    action       VARCHAR(64) NOT NULL,                  -- login_ok | event_create | refund | download ...
    entity_type  VARCHAR(32) NULL,
    entity_id    BIGINT UNSIGNED NULL,
    meta         JSON NULL,
    ip           VARBINARY(16) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_created (created_at),
    KEY idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily aggregates; incremented inline with ON DUPLICATE KEY UPDATE (no raw hit log).
-- gallery_views = event/session page loads; photo_views = lightbox opens.
-- Conversion stat = orders containing the event / gallery_views.
CREATE TABLE stats_daily (
    stat_date      DATE NOT NULL,
    event_id       INT UNSIGNED NOT NULL,
    gallery_views  INT UNSIGNED NOT NULL DEFAULT 0,
    photo_views    INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (stat_date, event_id),
    CONSTRAINT fk_stats_event FOREIGN KEY (event_id) REFERENCES events (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO migrations (filename) VALUES ('001_initial_schema.sql');
