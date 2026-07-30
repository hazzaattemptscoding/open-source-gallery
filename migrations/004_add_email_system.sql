-- Migration: Add email queue and notification system


-- Email queue for transactional messages
CREATE TABLE emails (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_text LONGTEXT,
    body_html LONGTEXT,
    template_name VARCHAR(100),
    template_data JSON,
    purpose ENUM('order_confirmation', 'receipt', 'shipping_update', 'notification', 'admin_alert'),
    status ENUM('pending', 'sent', 'failed', 'bounced'),
    error_message TEXT,
    retry_count INT UNSIGNED DEFAULT 0,
    sent_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_recipient (recipient_email),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email templates (customizable by admin)
CREATE TABLE email_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(100),
    subject_template VARCHAR(255) NOT NULL,
    body_html_template LONGTEXT NOT NULL,
    body_text_template LONGTEXT,
    variables TEXT,
    enabled TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default email templates
INSERT INTO email_templates (name, display_name, subject_template, body_html_template, body_text_template, variables) VALUES
('order_confirmation', 'Order Confirmation',
'Thank you for your order {{order_id}}',
'<h1>Thank you for your order</h1><p>Order #{{order_id}} for {{customer_name}}</p><p>Total: {{currency}} {{total}}</p>',
'Thank you for your order\n\nOrder #{{order_id}}\nTotal: {{currency}} {{total}}',
'["order_id", "customer_name", "total", "currency"]'),

('receipt', 'Order Receipt',
'Receipt for order {{order_id}}',
'<h1>Order Receipt</h1><p>Thank you {{customer_name}}!</p><p>Download your files here: {{download_link}}</p>',
'Order Receipt\n\nDownload: {{download_link}}',
'["order_id", "customer_name", "download_link"]'),

('shipping_update', 'Shipping Update',
'Your print order {{order_id}} has shipped',
'<h1>Shipping Update</h1><p>Your order has shipped!</p><p>Tracking: {{tracking_number}}</p><p><a href="{{tracking_url}}">Track package</a></p>',
'Your order {{order_id}} has shipped\nTracking: {{tracking_number}}\nURL: {{tracking_url}}',
'["order_id", "tracking_number", "tracking_url"]');
