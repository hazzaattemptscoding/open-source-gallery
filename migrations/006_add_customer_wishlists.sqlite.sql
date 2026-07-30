-- Migration: Add customer wishlists and favorites (SQLite variant)

-- Customer wishlists (persistent via signed tokens)
CREATE TABLE wishlists (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_token VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255),
    is_default INTEGER DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_customer_token ON wishlists(customer_token);

-- Photos in wishlists
CREATE TABLE wishlist_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    wishlist_id INTEGER NOT NULL,
    photo_id INTEGER NOT NULL,
    notes TEXT,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (wishlist_id, photo_id),
    FOREIGN KEY (wishlist_id) REFERENCES wishlists (id) ON DELETE CASCADE,
    FOREIGN KEY (photo_id) REFERENCES photos (id) ON DELETE CASCADE
);

CREATE INDEX idx_photo_id ON wishlist_items(photo_id);
