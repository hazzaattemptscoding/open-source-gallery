-- Migration: model a driver's identity properly, so "find my photos" can work.
--
-- The problem this solves
-- -----------------------
-- A kart number is not unique within an event. #7 in Cadet and #7 in Senior X30
-- are two different people. Until now the only place a number lived was
-- photo_tags.kart_number, a free-text column alongside a free-text class, and
-- the public gallery filtered on each independently. Filtering to kart 7
-- therefore returned both drivers' photos mixed together, and there was no
-- object anywhere in the schema that represented "this specific driver at this
-- specific event". Every generic gallery tool gets this wrong; a motorsport
-- gallery should not.
--
-- The real identifier is (event, class, number). These tables make that explicit.
--
-- Why photo_entrants is a join table and not another tag column
-- ------------------------------------------------------------
-- Attribution has a confidence and a provenance. A number read by OCR at 0.62
-- is not the same claim as one a human confirmed, and the difference has to
-- survive in the data or there is no way to build a review queue, let alone
-- correct a mistake later. Collapsing this into photo_tags would throw that
-- away, which is exactly the mistake that made the existing tag data
-- unreviewable.
--
-- photo_tags is deliberately left alone. It keeps doing what it is good at:
-- free-text venue, weather and editorial keywords.
--
-- Why entrants carry an opaque share_token
-- ----------------------------------------
-- The personal page is meant to be shared: a parent posting the link into a
-- WhatsApp group is the main way these galleries spread. That makes the URL a
-- published surface, so it must not contain a name, and it must not be
-- guessable by counting upwards. A random token is durable, shareable, and
-- readable only by someone who was given it. See docs/PRIVACY-DESIGN.md.
--
-- Note there is no captured_at column added here: photos.taken_at already
-- exists and is populated from EXIF, so this uses that rather than adding a
-- second column meaning the same thing.

-- ---------------------------------------------------------------------------
-- Classes: "Cadet", "Junior X30", "Senior Rotax" within one event.
-- ---------------------------------------------------------------------------
CREATE TABLE classes (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id    INT UNSIGNED NOT NULL,
    name        VARCHAR(80) NOT NULL,
    slug        VARCHAR(80) NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_classes_event_slug (event_id, slug),
    KEY idx_classes_event (event_id),
    CONSTRAINT fk_classes_event FOREIGN KEY (event_id) REFERENCES events (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Entrants: one row per driver per class per event. This is the thing a
-- visitor is actually looking for when they type a kart number.
--
-- driver_name and team are stored because the entry list provides them and the
-- admin tagging screen, the review queue and sales reporting all need them.
-- They are never rendered on a public page.
-- ---------------------------------------------------------------------------
CREATE TABLE entrants (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id     INT UNSIGNED NOT NULL,
    class_id     INT UNSIGNED NOT NULL,
    number       VARCHAR(8) NOT NULL,              -- '23', '7a': text, not int
    driver_name  VARCHAR(120) NOT NULL DEFAULT '', -- admin-only, never public
    team         VARCHAR(120) NOT NULL DEFAULT '',
    -- Nullable on purpose. A token is minted by PHP with random_bytes(), never
    -- by SQL, so a row inserted by the backfill below starts with no token and
    -- one is filled in by mint_missing_entrant_share_tokens() on the next cron
    -- run. A NULL token cannot be reached: find_entrant_by_token() requires 16
    -- hex characters before it queries at all, so the gap fails closed.
    -- UNIQUE permits many NULLs in both MySQL and SQLite, so the constraint
    -- still holds for every token that exists.
    share_token  CHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_entrants_identity (event_id, class_id, number),
    UNIQUE KEY uq_entrants_share_token (share_token),
    -- The lookup the find-me flow runs on every search: "which entrants at this
    -- event carry this number?" Leading with event_id and number lets one index
    -- serve both the single-class hit and the disambiguation case.
    KEY idx_entrants_lookup (event_id, number),
    KEY idx_entrants_class (class_id),
    CONSTRAINT fk_entrants_event FOREIGN KEY (event_id) REFERENCES events (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_entrants_class FOREIGN KEY (class_id) REFERENCES classes (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- photo_entrants: which driver appears in which photo, and how sure we are.
--
-- source records where the claim came from:
--   ocr         a number was read off the kart by the detection pipeline
--   manual      a human said so (admin tagging, or a visitor pressing "That's me")
--   propagated  inferred from a neighbouring frame in the same burst
--   livery      matched on helmet or kart livery rather than a visible number
--
-- confidence is 0..1. Anything below the review threshold stays out of the
-- confirmed set and is offered to the visitor as "are there more of me?".
-- ---------------------------------------------------------------------------
CREATE TABLE photo_entrants (
    photo_id     BIGINT UNSIGNED NOT NULL,
    entrant_id   INT UNSIGNED NOT NULL,
    source       VARCHAR(16) NOT NULL DEFAULT 'manual',
    confidence   DECIMAL(4,3) NOT NULL DEFAULT 1.000,
    reviewed_at  DATETIME NULL,                    -- non-null once a human confirmed
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (photo_id, entrant_id),
    -- The personal page's main query: every photo for one entrant. Confidence
    -- is in the index so the confirmed/unconfirmed split is served from it
    -- without touching the table.
    KEY idx_pe_entrant (entrant_id, confidence),
    KEY idx_pe_review (entrant_id, reviewed_at),
    CONSTRAINT fk_pe_photo FOREIGN KEY (photo_id) REFERENCES photos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pe_entrant FOREIGN KEY (entrant_id) REFERENCES entrants (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Sessions gain the shape the sport actually has.
--
-- class_id is nullable: a practice session is often open to every class, and an
-- existing install's sessions predate this column entirely. NULL means "all
-- classes", which is also the correct answer for those legacy rows.
-- ---------------------------------------------------------------------------
ALTER TABLE sessions ADD COLUMN class_id INT UNSIGNED NULL;
ALTER TABLE sessions ADD COLUMN type VARCHAR(16) NOT NULL DEFAULT 'other';
ALTER TABLE sessions ADD COLUMN start_time DATETIME NULL;
ALTER TABLE sessions ADD COLUMN end_time DATETIME NULL;
ALTER TABLE sessions ADD CONSTRAINT fk_sessions_class FOREIGN KEY (class_id)
    REFERENCES classes (id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------------
-- Backfill, so existing installs are not left with three empty tables.
--
-- event_entries already holds (event_id, kart_number, class) from the CSV
-- importer, which is exactly the identity being modelled, so it seeds both
-- classes and entrants. photo_tags then seeds photo_entrants wherever a tag
-- matches an entrant on number and class.
--
-- Tags that do not match an entry are left behind deliberately rather than
-- inventing an entrant from them: a typo'd tag should surface in the review
-- queue, not silently become a driver who was never entered.
--
-- share_token is deliberately left NULL here, and minted by PHP afterwards.
--
-- The first version of this migration generated it in SQL, as
-- SUBSTRING(MD5(UUID()), 1, 16). That is not an unguessable token. MySQL's
-- UUID() is a version 1 UUID: a timestamp and the host's MAC address, not a
-- random number. MD5 of it is a deterministic function of two things an
-- attacker can narrow down, so a backfilled entrant's token would have been
-- meaningfully weaker than one minted by random_bytes() while looking
-- identical. That token is the only thing protecting a personal page, and the
-- people behind those pages are frequently children.
--
-- Hashing the row's own identity would have been worse still: (event_id,
-- class_id, number) is two small integers and a short numeric string, so anyone
-- could enumerate plausible identities offline and compute every token.
--
-- So no token is generated in SQL at all. mint_missing_entrant_share_tokens()
-- in app/lib/entrants.php fills them in from random_bytes() on the next cron
-- run, which is the same source every token minted after this point uses.
-- One generator, one place to audit it.
-- ---------------------------------------------------------------------------
-- Grouped by the slug, not by the class text, and this matters: two different
-- spellings in one organiser's CSV ("Junior X30" and "Junior/X30") normalise to
-- the same slug. A DISTINCT on the raw text treats them as two classes, both
-- then hit UNIQUE (event_id, slug), and the whole migration aborts partway
-- through on a perfectly ordinary entry list. Grouping on the slug collapses
-- them into the one class they actually are; MIN() just picks a display name.
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
    NULL
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
    1.000,
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
