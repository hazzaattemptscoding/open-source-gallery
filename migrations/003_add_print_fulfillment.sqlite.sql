-- Migration: Add print fulfillment support (SQLite variant)

-- Print providers
CREATE TABLE print_providers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    api_endpoint VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Insert default providers
INSERT INTO print_providers (name, api_endpoint) VALUES
    ('Printful', 'https://api.printful.com'),
    ('Printware', 'https://api.printware.com'),
    ('Local Fulfillment', NULL);

-- Print settings
CREATE TABLE print_settings (
    provider_id INTEGER NOT NULL PRIMARY KEY,
    api_key VARCHAR(255),
    enabled INTEGER DEFAULT 0,
    auto_route INTEGER DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES print_providers (id) ON DELETE CASCADE
);

-- Print orders
CREATE TABLE print_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    provider_id INTEGER NOT NULL,
    external_order_id VARCHAR(255),
    status TEXT DEFAULT 'pending',
    tracking_number VARCHAR(255),
    tracking_url VARCHAR(255),
    estimated_delivery DATE,
    notes TEXT,
    submitted_at DATETIME,
    completed_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (order_id, provider_id),
    FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES print_providers (id) ON DELETE RESTRICT
);

CREATE INDEX idx_print_status ON print_orders(status);

-- Print items (photos that need printing)
CREATE TABLE print_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    print_order_id INTEGER NOT NULL,
    photo_id INTEGER NOT NULL,
    product_type VARCHAR(50),
    quantity INTEGER DEFAULT 1,
    price_pence INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (print_order_id) REFERENCES print_orders (id) ON DELETE CASCADE,
    FOREIGN KEY (photo_id) REFERENCES photos (id) ON DELETE RESTRICT
);

-- Update orders table to support print items
ALTER TABLE orders ADD COLUMN has_print_items INTEGER DEFAULT 0;

-- Index for faster lookups
CREATE INDEX idx_orders_has_print ON orders(has_print_items);
