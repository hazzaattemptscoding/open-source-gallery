-- Migration: Per-photo price override (SQLite variant)

ALTER TABLE photos ADD COLUMN price_pence INTEGER NULL;
