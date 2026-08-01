-- Migration: remove settings that never controlled anything, and the
-- watermark keys the derivative pipeline no longer reads.
--
-- photos.preview_width and photos.thumb_width described a single-preview /
-- single-thumbnail model that predates the current fixed three-tier
-- responsive derivative system (400/800/1600, hardcoded throughout the public
-- views' src/srcset and this app's own /media/d/{token}-{width}.jpg
-- convention). Wiring these two settings to anything real would either do
-- nothing or desync that scheme, so they are removed rather than pretended
-- into working. See app/lib/derivatives.php for the real size list.
--
-- The `settings` table's watermark_* rows (watermark_enabled, watermark_opacity,
-- watermark_scale, watermark_position, watermark_min_width) were, until this
-- release, what get_watermark_settings() actually read -- while the dedicated
-- Watermarks admin page (app/controllers/admin/watermarks.php) wrote to a
-- completely different table, watermark_settings. The two were never
-- connected: editing watermark settings through the page built for exactly
-- that purpose changed nothing about a generated image. That is now fixed;
-- get_watermark_settings() reads watermark_settings, which is the table the
-- admin UI has always written. These rows are dead weight now that nothing
-- reads them.

DELETE FROM settings_registry WHERE category = 'photos' AND key_name IN ('preview_width', 'thumb_width');
DELETE FROM settings WHERE skey IN ('watermark_enabled', 'watermark_opacity', 'watermark_scale', 'watermark_position', 'watermark_min_width');
