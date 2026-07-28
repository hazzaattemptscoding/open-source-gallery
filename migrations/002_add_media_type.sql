-- Adds video support to the existing photos table rather than a parallel
-- videos table: videos reuse the same upload/token/storage pipeline, and
-- the public gallery only needs to query them separately from the photo
-- grid (CLAUDE.md: "Videos live in their own section, never mixed into
-- the photo grid's load order"). Additive ALTER, no data rewrite.
ALTER TABLE photos
    ADD COLUMN media_type ENUM('photo','video') NOT NULL DEFAULT 'photo' AFTER status;

CREATE INDEX idx_photos_session_media ON photos (session_id, media_type, status, sort_order);
