-- Remove stale cleanup jobs that are no longer processed.
-- Cleanup job type was deleted in Stage 2.4.2 (derivatives no longer queue
-- cleanup after generation, 1600px previews are kept indefinitely).
DELETE FROM jobs WHERE type = 'cleanup';
