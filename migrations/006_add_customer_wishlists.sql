-- Migration: Add customer wishlists and favorites

INSERT INTO migrations (filename, applied_at) VALUES ('006_add_customer_wishlists.sql', CURRENT_TIMESTAMP);

-- Customer wishlists (persistent via signed tokens)
CREATE TABLE wishlists (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    customer_token VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255),
    is_default TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_customer_token (customer_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos in wishlists
CREATE TABLE wishlist_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    wishlist_id INT UNSIGNED NOT NULL,
    photo_id BIGINT UNSIGNED NOT NULL,
    notes TEXT,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_wishlist_items_wishlist FOREIGN KEY (wishlist_id)
        REFERENCES wishlists (id) ON DELETE CASCADE,
    CONSTRAINT fk_wishlist_items_photo FOREIGN KEY (photo_id)
        REFERENCES photos (id) ON DELETE CASCADE,
    UNIQUE KEY uk_wishlist_photo (wishlist_id, photo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_photo_id ON wishlist_items(photo_id);
