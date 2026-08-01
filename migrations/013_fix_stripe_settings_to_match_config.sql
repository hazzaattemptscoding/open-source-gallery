-- Migration: correct the Stripe settings fields to match what the app
-- actually reads.
--
-- The original fields (mode, test_publishable_key, test_secret_key,
-- live_publishable_key, live_secret_key) describe a test-vs-live pair the
-- runtime does not have. app/lib/stripe.php, app/controllers/public/checkout.php
-- and the webhook controller read a single config['stripe']['secret_key'] /
-- ['publishable_key'] / ['webhook_secret'], gated by a single
-- config['stripe']['mode'] ('test'|'live', checked in
-- app/lib/cli/commands.php). Saving through the old field names moved a
-- secret safely out of the database but into a config.php key nothing
-- consumed -- a control that looked like it worked and did nothing, the same
-- shape as the watermark bug fixed in the previous release.
--
-- publishable_key, secret_key, webhook_secret and mode now route through
-- config.php (app/lib/config_store.php's CONFIG_FILE_PATHS), so the `value`
-- column on these rows is display metadata only; the real value is read from
-- config.php at render time.

DELETE FROM settings_registry WHERE category = 'stripe' AND key_name IN ('test_publishable_key', 'test_secret_key', 'live_publishable_key', 'live_secret_key');

INSERT INTO settings_registry (category, key_name, value, type, display_label, help_text, is_advanced, order_by) VALUES
('stripe', 'publishable_key', '', 'string', 'Publishable Key', 'Stripe publishable key for the mode selected above', 0, 20),
('stripe', 'secret_key', '', 'string', 'Secret Key', 'Stripe secret key for the mode selected above (sensitive)', 0, 30),
('stripe', 'webhook_secret', '', 'string', 'Webhook Signing Secret', 'From the Stripe Dashboard webhook endpoint (sensitive)', 1, 40);
