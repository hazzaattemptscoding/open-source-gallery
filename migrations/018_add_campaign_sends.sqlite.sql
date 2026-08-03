-- Migration: campaign send tracking and published_at (SQLite variant).
--
-- See 018_add_campaign_sends.sql for why published_at backfills to NULL rather
-- than to created_at, and why the unique key on campaign_sends is the thing
-- that prevents duplicate sends rather than an application-level check.

ALTER TABLE events ADD COLUMN published_at TEXT NULL;

CREATE TABLE IF NOT EXISTS campaign_sends (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    campaign     TEXT NOT NULL,
    contact_id   INTEGER NOT NULL,
    subject_key  TEXT NOT NULL,
    sent_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (campaign, contact_id, subject_key),
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_campaign_sent ON campaign_sends (campaign, sent_at);

-- Admin toggles, one per campaign.
--
-- Both default to '0'. An install upgrading into this code must not start
-- emailing its customers because a migration ran; switching a campaign on is a
-- deliberate act. get_setting() would fall back to the default anyway, but
-- registering them is what makes them appear in Settings as switches the admin
-- can actually see and use.
INSERT INTO settings_registry
    (category, key_name, value, type, display_label, help_text, required, is_advanced, order_by)
VALUES
    ('campaigns', 'gallery_live_enabled', '0', 'boolean',
     'Email when a gallery goes live',
     'Tells everyone who opted in that a newly published gallery is online. Only sent for galleries published in the last 72 hours.',
     0, 0, 10),
    ('campaigns', 'abandoned_cart_enabled', '0', 'boolean',
     'Email about unfinished checkouts',
     'Reminds someone who started a checkout and did not pay, once, between 24 and 96 hours afterwards. Only sent to people who separately opted in to marketing, and never to someone who has since bought something.',
     0, 0, 20);
