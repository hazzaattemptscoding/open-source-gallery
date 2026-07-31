-- Migration: Store named watermark presets alongside the live settings.
--
-- The presets admin UI (app/controllers/admin/watermarks.php) read and wrote
-- watermark_settings.presets from the day it shipped, but no migration ever
-- created the column. Every save, load, and delete threw and was swallowed by
-- the controller's catch, so the feature looked present and did nothing.
--
-- One JSON column rather than a presets table: a preset is a frozen copy of the
-- five settings columns beside it, there is no per-preset row to relate to
-- anything, and the whole set is read and rewritten together on every change.

ALTER TABLE watermark_settings ADD COLUMN presets LONGTEXT NULL;
