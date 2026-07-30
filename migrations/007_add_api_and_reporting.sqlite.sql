-- Migration: Add REST API and advanced reporting (SQLite variant)

-- API keys for integrations
CREATE TABLE api_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    key_hash VARCHAR(255) UNIQUE NOT NULL,
    admin_id INTEGER NOT NULL,
    permissions TEXT,
    rate_limit INTEGER DEFAULT 1000,
    last_used_at DATETIME,
    enabled INTEGER DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users (id) ON DELETE CASCADE
);

CREATE INDEX idx_enabled ON api_keys(enabled);

-- API usage logging
CREATE TABLE api_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    api_key_id INTEGER,
    endpoint VARCHAR(255),
    method VARCHAR(10),
    status_code INTEGER,
    response_time_ms INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_api_key ON api_logs(api_key_id);
CREATE INDEX idx_api_logs_created ON api_logs(created_at);

-- Customer analytics (for advanced reporting)
CREATE TABLE customer_analytics (
    customer_email VARCHAR(255) PRIMARY KEY,
    total_spent_pence INTEGER DEFAULT 0,
    order_count INTEGER DEFAULT 0,
    photo_count INTEGER DEFAULT 0,
    first_order_at DATETIME,
    last_order_at DATETIME,
    average_order_value_pence INTEGER,
    lifetime_value_pence INTEGER,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_ltv ON customer_analytics(lifetime_value_pence);

-- Cohort analysis (monthly customer cohorts)
CREATE TABLE cohorts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cohort_month DATE NOT NULL,
    customers_acquired INTEGER DEFAULT 0,
    revenue_pence INTEGER DEFAULT 0,
    retention_month_1 INTEGER,
    retention_month_3 INTEGER,
    retention_month_6 INTEGER,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (cohort_month)
);
