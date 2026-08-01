-- Migration: remove settings with no reachable consumer.
--
-- site.tagline and site.description: no code anywhere reads either --
-- confirmed by grepping every controller and view for a consumer, not just
-- absence from a prior list. There is no public footer or homepage subtitle
-- slot for a tagline, and no meta-description fallback wired to the
-- description field either (the SEO layer builds its own per-page
-- description, see app/lib/seo.php).
--
-- api.rate_limit and api.documentation_public: app/lib/api.php's
-- create_api_key() has no $rateLimit parameter and is never called from
-- anywhere in the app -- there is no API key creation path at all, so a
-- default rate limit setting has nothing to apply to. documentation_public
-- has no /docs route to gate. Both describe an API key management feature
-- that was scaffolded (the api_keys table has an unused rate_limit column)
-- but never built; real API rate limiting and key management is future
-- work, not a wiring task.
--
-- print.default_provider and print.auto_route_orders: print orders carry
-- their own provider_id from creation, there is no "assign incoming orders
-- to a default provider" step anywhere in app/controllers/admin/print_orders.php
-- for either of these to control.

DELETE FROM settings_registry WHERE category = 'site' AND key_name IN ('tagline', 'description');
DELETE FROM settings_registry WHERE category = 'api' AND key_name IN ('rate_limit', 'documentation_public');
DELETE FROM settings_registry WHERE category = 'print' AND key_name IN ('default_provider', 'auto_route_orders');
