-- Add driver visibility control per event entry (SQLite version)
-- SQLite doesn't support ENUM, so we use TEXT and validate in the application

ALTER TABLE event_entries
    ADD COLUMN drivers_visibility TEXT NOT NULL DEFAULT 'hidden';

-- SQLite doesn't have ALTER TABLE ... ADD CONSTRAINT directly on existing columns,
-- so validation happens in application code. Valid values: 'hidden', 'initials', 'full'
