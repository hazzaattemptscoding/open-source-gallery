-- Migration: Add multi-admin support with role-based access control (SQLite variant)

-- Create admin roles table
CREATE TABLE admin_roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Insert default roles
INSERT INTO admin_roles (name, display_name, description) VALUES
    ('admin', 'Full Admin', 'Complete access to all features'),
    ('uploader', 'Uploader', 'Can upload and manage photos, create events'),
    ('viewer', 'Viewer', 'Read-only access to dashboard and reports');

-- Add role_id column to admin_users
ALTER TABLE admin_users ADD COLUMN admin_role_id INTEGER DEFAULT 1;

-- Create admin activity log target
ALTER TABLE audit_log ADD COLUMN target_admin_id INTEGER NULL;

-- Index for faster role lookups
CREATE INDEX idx_admin_users_role ON admin_users(admin_role_id);
