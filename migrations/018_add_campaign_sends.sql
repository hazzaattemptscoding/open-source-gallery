-- Migration: campaign send tracking, and when a gallery actually went live.
--
-- Two things the nudge campaigns cannot work without.
--
-- events.published_at
-- -------------------
-- The schema recorded whether an event is published but never when it became
-- published. "Tell people the gallery is live" needs the moment it went live:
-- without it there is no way to distinguish a gallery published an hour ago
-- from one published last season, and the first cron run after deploying the
-- campaign would email everybody about every gallery that has ever existed.
--
-- Backfilled to NULL rather than to created_at on purpose. NULL means "we do
-- not know when this went live", and the campaign scanner deliberately skips
-- those. Guessing a date here is how an install with two years of archived
-- events sends two years of "your gallery is live" emails on upgrade.
--
-- campaign_sends
-- --------------
-- Cron runs every five minutes. Every campaign scan therefore re-examines the
-- same galleries and the same abandoned orders it saw five minutes ago, so
-- without a record of what has already gone out, a single published gallery
-- would generate an email every five minutes forever.
--
-- The unique key is the guard, not the application logic: the INSERT is what
-- claims the right to send, so two overlapping cron runs cannot both decide to
-- send the same message. Application-level "have we sent this?" checks lose
-- that race; a unique constraint does not.
--
-- subject_key identifies what the message is about ('event:12', 'order:481')
-- rather than being a foreign key, because different campaigns are about
-- different kinds of thing and a single nullable FK per kind would grow a
-- column every time a campaign is added.

ALTER TABLE events ADD COLUMN published_at DATETIME NULL;

CREATE TABLE campaign_sends (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    campaign     VARCHAR(32) NOT NULL,      -- 'gallery_live' | 'abandoned_cart'
    contact_id   INT UNSIGNED NOT NULL,
    subject_key  VARCHAR(64) NOT NULL,      -- 'event:12', 'order:481'
    sent_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- The whole point of this table. One campaign, one recipient, one subject,
    -- once, enforced by the database rather than by hopeful application code.
    UNIQUE KEY uq_campaign_send (campaign, contact_id, subject_key),
    KEY idx_campaign_sent (campaign, sent_at),
    CONSTRAINT fk_campaign_sends_contact FOREIGN KEY (contact_id) REFERENCES contacts (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
