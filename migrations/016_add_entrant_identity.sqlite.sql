-- Migration: model a driver's identity properly (SQLite variant).
--
-- See 016_add_entrant_identity.sql for why these tables exist, why
-- photo_entrants carries source and confidence instead of being another tag
-- column, and why entrants carry an opaque share_token.
--
-- Differences forced by SQLite, none of which change the meaning:
--
--  * Indexes are separate CREATE INDEX statements rather than inline KEY.
--  * confidence is REAL. SQLite has no DECIMAL; it accepts the keyword but
--    stores it with numeric affinity anyway, so REAL is the honest spelling.
--  * sessions.class_id gets no foreign key. SQLite cannot add a constraint to
--    an existing table, and doing it properly would mean rebuilding the table
--    and copying every row. The column is written only by admin code that has
--    the class in hand, and MySQL is the production target where the real
--    constraint applies.
--  * share_token uses randomblob() rather than UUID(). Same 16 hex characters,
--    same unguessability, and it is a better source of randomness than MySQL's.

CREATE TABLE IF NOT EXISTS classes (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id    INTEGER NOT NULL,
    name        TEXT NOT NULL,
    slug        TEXT NOT NULL,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (event_id, slug),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_classes_event ON classes (event_id);

CREATE TABLE IF NOT EXISTS entrants (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id     INTEGER NOT NULL,
    class_id     INTEGER NOT NULL,
    number       TEXT NOT NULL,
    driver_name  TEXT NOT NULL DEFAULT '',   -- admin-only, never public
    team         TEXT NOT NULL DEFAULT '',
    share_token  TEXT NOT NULL,
    created_at   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (event_id, class_id, number),
    UNIQUE (share_token),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_entrants_lookup ON entrants (event_id, number);
CREATE INDEX IF NOT EXISTS idx_entrants_class ON entrants (class_id);

CREATE TABLE IF NOT EXISTS photo_entrants (
    photo_id     INTEGER NOT NULL,
    entrant_id   INTEGER NOT NULL,
    source       TEXT NOT NULL DEFAULT 'manual',
    confidence   REAL NOT NULL DEFAULT 1.0,
    reviewed_at  TEXT NULL,
    created_at   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (photo_id, entrant_id),
    FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE,
    FOREIGN KEY (entrant_id) REFERENCES entrants(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_pe_entrant ON photo_entrants (entrant_id, confidence);
CREATE INDEX IF NOT EXISTS idx_pe_review ON photo_entrants (entrant_id, reviewed_at);

ALTER TABLE sessions ADD COLUMN class_id INTEGER NULL;
ALTER TABLE sessions ADD COLUMN type TEXT NOT NULL DEFAULT 'other';
ALTER TABLE sessions ADD COLUMN start_time TEXT NULL;
ALTER TABLE sessions ADD COLUMN end_time TEXT NULL;

-- Backfill from the entry list and the existing tags. See the MySQL file for
-- why unmatched tags are deliberately left behind rather than inventing an
-- entrant for them.
-- Grouped by slug rather than by the raw class text. See the MySQL file: two
-- spellings that normalise to one slug would otherwise violate UNIQUE
-- (event_id, slug) and abort the migration on an ordinary entry list.
INSERT INTO classes (event_id, name, slug, sort_order)
SELECT
    ee.event_id,
    MIN(ee.class),
    LOWER(REPLACE(REPLACE(TRIM(ee.class), ' ', '-'), '/', '-')) AS class_slug,
    0
FROM event_entries ee
WHERE ee.class <> ''
GROUP BY ee.event_id, class_slug;

INSERT INTO entrants (event_id, class_id, number, driver_name, team, share_token)
SELECT
    ee.event_id,
    c.id,
    ee.kart_number,
    ee.driver_name,
    '',
    LOWER(HEX(RANDOMBLOB(8)))
FROM event_entries ee
JOIN classes c
  ON c.event_id = ee.event_id
 AND c.slug = LOWER(REPLACE(REPLACE(TRIM(ee.class), ' ', '-'), '/', '-'))
WHERE ee.class <> '';

INSERT INTO photo_entrants (photo_id, entrant_id, source, confidence, reviewed_at)
SELECT DISTINCT
    pt.photo_id,
    e.id,
    'manual',
    1.0,
    CURRENT_TIMESTAMP
FROM photo_tags pt
JOIN photos p ON p.id = pt.photo_id
JOIN classes c
  ON c.event_id = p.event_id
 AND c.slug = LOWER(REPLACE(REPLACE(TRIM(pt.class), ' ', '-'), '/', '-'))
JOIN entrants e
  ON e.event_id = p.event_id
 AND e.class_id = c.id
 AND e.number = pt.kart_number
WHERE pt.kart_number IS NOT NULL
  AND pt.kart_number <> ''
  AND pt.class IS NOT NULL
  AND pt.class <> '';
