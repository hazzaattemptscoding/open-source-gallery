-- Migration: Add REST API and advanced reporting

INSERT INTO migrations (filename, applied_at) VALUES ('007_add_api_and_reporting.sql', CURRENT_TIMESTAMP);

-- API keys for integrations
CREATE TABLE api_keys (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    key_hash VARCHAR(255) UNIQUE NOT NULL,
    admin_id INT UNSIGNED NOT NULL,
    permissions JSON,
    rate_limit INT DEFAULT 1000,
    last_used_at DATETIME,
    enabled TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_api_keys_admin FOREIGN KEY (admin_id)
        REFERENCES admin_users (id) ON DELETE CASCADE,
    KEY idx_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API usage logging
CREATE TABLE api_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT UNSIGNED,
    endpoint VARCHAR(255),
    method VARCHAR(10),
    status_code INT,
    response_time_ms INT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_api_key (api_key_id),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer analytics (for advanced reporting)
CREATE TABLE customer_analytics (
    customer_email VARCHAR(255) PRIMARY KEY,
    total_spent_pence INT DEFAULT 0,
    order_count INT DEFAULT 0,
    photo_count INT DEFAULT 0,
    first_order_at DATETIME,
    last_order_at DATETIME,
    average_order_value_pence INT,
    lifetime_value_pence INT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ltv (lifetime_value_pence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cohort analysis (monthly customer cohorts)
CREATE TABLE cohorts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cohort_month DATE NOT NULL,
    customers_acquired INT DEFAULT 0,
    revenue_pence INT DEFAULT 0,
    retention_month_1 INT,
    retention_month_3 INT,
    retention_month_6 INT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cohort_month (cohort_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
