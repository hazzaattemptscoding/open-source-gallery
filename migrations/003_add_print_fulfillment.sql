-- Migration: Add print fulfillment support

INSERT INTO migrations (filename, applied_at) VALUES ('003_add_print_fulfillment.sql', CURRENT_TIMESTAMP);

-- Print providers
CREATE TABLE print_providers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    api_endpoint VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default providers
INSERT INTO print_providers (name, api_endpoint) VALUES
    ('Printful', 'https://api.printful.com'),
    ('Printware', 'https://api.printware.com'),
    ('Local Fulfillment', NULL);

-- Print settings
CREATE TABLE print_settings (
    provider_id INT UNSIGNED NOT NULL,
    api_key VARCHAR(255),
    enabled TINYINT(1) DEFAULT 0,
    auto_route TINYINT(1) DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (provider_id),
    CONSTRAINT fk_print_settings_provider FOREIGN KEY (provider_id)
        REFERENCES print_providers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Print orders
CREATE TABLE print_orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    provider_id INT UNSIGNED NOT NULL,
    external_order_id VARCHAR(255),
    status ENUM('pending','submitted','processing','shipped','delivered','cancelled'),
    tracking_number VARCHAR(255),
    tracking_url VARCHAR(255),
    estimated_delivery DATE,
    notes TEXT,
    submitted_at DATETIME,
    completed_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_print_orders_order FOREIGN KEY (order_id)
        REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_print_orders_provider FOREIGN KEY (provider_id)
        REFERENCES print_providers (id) ON DELETE RESTRICT,
    UNIQUE KEY uk_print_orders (order_id, provider_id),
    KEY idx_print_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Print items (photos that need printing)
CREATE TABLE print_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    print_order_id INT UNSIGNED NOT NULL,
    photo_id BIGINT UNSIGNED NOT NULL,
    product_type VARCHAR(50),
    quantity INT UNSIGNED DEFAULT 1,
    price_pence INT UNSIGNED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_print_items_order FOREIGN KEY (print_order_id)
        REFERENCES print_orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_print_items_photo FOREIGN KEY (photo_id)
        REFERENCES photos (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update orders table to support print items
ALTER TABLE orders ADD COLUMN has_print_items TINYINT(1) DEFAULT 0;

-- Index for faster lookups
CREATE INDEX idx_orders_has_print ON orders(has_print_items);
