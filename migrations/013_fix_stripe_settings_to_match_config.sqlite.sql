-- Migration: correct Stripe settings fields to match config.php (SQLite
-- variant). See the .sql file for why: the old test/live pair does not match
-- what app/lib/stripe.php and the checkout/webhook controllers actually read.

DELETE FROM settings_registry WHERE category = 'stripe' AND key_name IN ('test_publishable_key', 'test_secret_key', 'live_publishable_key', 'live_secret_key');

INSERT INTO settings_registry (category, key_name, value, type, display_label, help_text, is_advanced, order_by) VALUES
('stripe', 'publishable_key', '', 'string', 'Publishable Key', 'Stripe publishable key for the mode selected above', 0, 20),
('stripe', 'secret_key', '', 'string', 'Secret Key', 'Stripe secret key for the mode selected above (sensitive)', 0, 30),
('stripe', 'webhook_secret', '', 'string', 'Webhook Signing Secret', 'From the Stripe Dashboard webhook endpoint (sensitive)', 1, 40);
