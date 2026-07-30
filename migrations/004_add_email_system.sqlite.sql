-- Migration: Add email queue and notification system (SQLite variant)

-- Email queue for transactional messages
CREATE TABLE emails (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_text LONGTEXT,
    body_html LONGTEXT,
    template_name VARCHAR(100),
    template_data TEXT,
    purpose TEXT DEFAULT 'notification',
    status TEXT DEFAULT 'pending',
    error_message TEXT,
    retry_count INTEGER DEFAULT 0,
    sent_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_status ON emails(status);
CREATE INDEX idx_recipient ON emails(recipient_email);
CREATE INDEX idx_created ON emails(created_at);

-- Email templates (customizable by admin)
CREATE TABLE email_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(100),
    subject_template VARCHAR(255) NOT NULL,
    body_html_template LONGTEXT NOT NULL,
    body_text_template LONGTEXT,
    variables TEXT,
    enabled INTEGER DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

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
