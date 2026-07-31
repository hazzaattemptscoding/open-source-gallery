-- Migration: Store named watermark presets alongside the live settings (SQLite variant)
--
-- See 011_add_watermark_presets.sql for why this column exists and why it is a
-- JSON blob rather than its own table.

ALTER TABLE watermark_settings ADD COLUMN presets TEXT NULL;
