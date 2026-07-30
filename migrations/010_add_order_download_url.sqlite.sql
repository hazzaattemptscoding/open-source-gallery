-- Migration: Persist the download link URL on the order itself (SQLite variant)

ALTER TABLE orders ADD COLUMN download_url VARCHAR(500) NULL;
