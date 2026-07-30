-- Migration: Comprehensive settings system
--
-- Named settings_registry, not settings: migrations/001_initial_schema.sql
-- already created a `settings` table (a simple skey/svalue key-value store
-- for prices and other single-value config, read by app/lib/orders.php,
-- app/controllers/admin/events.php, and others). This migration's richer
-- category/key_name/type/label shape backs the admin Settings UI
-- (app/lib/settings.php) instead — a different table for a different job,
-- not a replacement for the one 001 created.

-- Core settings (database-managed for easy admin updates)
CREATE TABLE settings_registry (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL,
    key_name VARCHAR(100) NOT NULL,
    value LONGTEXT,
    type ENUM('string', 'integer', 'boolean', 'json', 'text', 'email'),
    display_label VARCHAR(255) NOT NULL,
    help_text LONGTEXT,
    placeholder VARCHAR(255),
    required TINYINT(1) DEFAULT 0,
    is_advanced TINYINT(1) DEFAULT 0,
    order_by INT DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_category_key (category, key_name),
    KEY idx_category (category),
    KEY idx_advanced (is_advanced)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO settings_registry (category, key_name, value, type, display_label, help_text, is_advanced, order_by) VALUES
-- Site Settings
('site', 'name', 'Photo Gallery', 'string', 'Gallery Name', 'Display name for your photo gallery', 0, 10),
('site', 'tagline', '', 'string', 'Tagline', 'Short description shown on homepage', 0, 20),
('site', 'description', '', 'text', 'Site Description', 'Full description for SEO and sharing', 0, 30),
('site', 'contact_email', 'contact@gallery.local', 'email', 'Contact Email', 'Email address customers can reach you at', 0, 40),
('site', 'timezone', 'UTC', 'string', 'Timezone', 'Your timezone (UTC, America/New_York, etc.)', 1, 50),
('site', 'language', 'en', 'string', 'Language', 'Primary language code (en, fr, de, etc.)', 1, 60),

-- Currency Settings
('currency', 'code', 'GBP', 'string', 'Currency Code', 'ISO 4217 code (GBP, USD, EUR, etc.)', 0, 10),
('currency', 'symbol', '£', 'string', 'Currency Symbol', 'Symbol displayed next to prices', 0, 20),
('currency', 'decimal_places', '2', 'integer', 'Decimal Places', 'Number of decimal places for prices', 1, 30),

-- Email Settings
('email', 'enabled', '1', 'boolean', 'Enable Email Notifications', 'Send transactional emails to customers', 0, 10),
('email', 'from_name', 'Photo Gallery', 'string', 'From Name', 'Name shown in email sender', 0, 20),
('email', 'from_address', 'noreply@gallery.local', 'email', 'From Address', 'Email address emails are sent from', 0, 30),
('email', 'smtp_enabled', '0', 'boolean', 'Use SMTP', 'Use SMTP instead of PHP mail()', 1, 40),
('email', 'smtp_host', '', 'string', 'SMTP Host', 'SMTP server hostname', 1, 50),
('email', 'smtp_port', '587', 'integer', 'SMTP Port', 'SMTP server port (usually 587 or 465)', 1, 60),
('email', 'smtp_username', '', 'string', 'SMTP Username', 'SMTP authentication username', 1, 70),
('email', 'smtp_password', '', 'string', 'SMTP Password', 'SMTP authentication password', 1, 80),

-- Stripe Settings
('stripe', 'mode', 'test', 'string', 'Stripe Mode', 'test or live', 0, 10),
('stripe', 'test_publishable_key', 'pk_test_...', 'string', 'Test Publishable Key', 'Stripe test mode publishable key', 0, 20),
('stripe', 'test_secret_key', 'sk_test_...', 'string', 'Test Secret Key', 'Stripe test mode secret key (sensitive)', 0, 30),
('stripe', 'live_publishable_key', '', 'string', 'Live Publishable Key', 'Stripe live mode publishable key', 1, 40),
('stripe', 'live_secret_key', '', 'string', 'Live Secret Key', 'Stripe live mode secret key (sensitive)', 1, 50),

-- Photo Settings
('photos', 'watermark_enabled', '1', 'boolean', 'Enable Watermarks', 'Show watermarks on gallery preview images', 0, 10),
('photos', 'preview_width', '800', 'integer', 'Preview Image Width', 'Width in pixels for gallery preview images', 1, 20),
('photos', 'thumb_width', '200', 'integer', 'Thumbnail Width', 'Width in pixels for thumbnail images', 1, 30),
('photos', 'max_upload_size_mb', '100', 'integer', 'Max Upload Size (MB)', 'Maximum file size for photo uploads', 0, 40),
('photos', 'allowed_formats', 'jpg,jpeg,png,heic', 'string', 'Allowed Formats', 'Comma-separated file extensions (lowercase)', 1, 50),

-- Print Settings
('print', 'enabled', '0', 'boolean', 'Enable Print Fulfillment', 'Allow customers to order printed photos', 0, 10),
('print', 'default_provider', '1', 'integer', 'Default Provider', 'Default print fulfillment provider ID', 0, 20),
('print', 'auto_route_orders', '0', 'boolean', 'Auto-route Orders', 'Automatically send approved orders to provider', 1, 30),

-- Search Settings
('search', 'enabled', '1', 'boolean', 'Enable Search', 'Allow customers to search photos', 0, 10),
('search', 'results_per_page', '20', 'integer', 'Results Per Page', 'Number of search results to show', 1, 20),
('search', 'min_query_length', '2', 'integer', 'Minimum Query Length', 'Minimum characters for search query', 1, 30),

-- API Settings
('api', 'enabled', '1', 'boolean', 'Enable REST API', 'Allow API access to photo data', 0, 10),
('api', 'rate_limit', '1000', 'integer', 'Rate Limit (per hour)', 'API requests per hour per key', 1, 20),
('api', 'documentation_public', '0', 'boolean', 'Public API Docs', 'Show API documentation at /docs/api', 1, 30),

-- Security Settings
('security', 'session_timeout_minutes', '60', 'integer', 'Session Timeout (minutes)', 'Admin session timeout in minutes', 1, 10),
('security', 'password_min_length', '8', 'integer', 'Password Min Length', 'Minimum password length for admins', 1, 20),
('security', 'require_totp', '1', 'boolean', 'Require 2FA', 'Require two-factor authentication for all admins', 0, 30),
('security', 'ip_whitelist', '', 'text', 'IP Whitelist', 'Comma-separated IPs (empty = all allowed)', 1, 40);
