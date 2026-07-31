-- Add driver visibility control per event entry
-- Photographers can choose: hidden (default), initials only, or full name
-- This prevents accidental public exposure of driver names without permission

ALTER TABLE event_entries
    ADD COLUMN drivers_visibility ENUM('hidden', 'initials', 'full') NOT NULL DEFAULT 'hidden'
        COMMENT 'Controls how driver names appear in public gallery: hidden, initials (M.P.), or full name';
