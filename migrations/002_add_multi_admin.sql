-- Migration: Add multi-admin support with role-based access control

-- Create admin roles table
CREATE TABLE admin_roles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default roles
INSERT INTO admin_roles (name, display_name, description) VALUES
    ('admin', 'Full Admin', 'Complete access to all features'),
    ('uploader', 'Uploader', 'Can upload and manage photos, create events'),
    ('viewer', 'Viewer', 'Read-only access to dashboard and reports');

-- Add role_id column to admin_users
ALTER TABLE admin_users ADD COLUMN admin_role_id INT UNSIGNED DEFAULT 1;
ALTER TABLE admin_users ADD CONSTRAINT fk_admin_users_role
    FOREIGN KEY (admin_role_id) REFERENCES admin_roles(id) ON DELETE RESTRICT;

-- Create admin activity log (track who created/deleted other admins).
-- audit_log has no admin_id column (it identifies the actor via the
-- 'actor' enum + entity_type/entity_id, see migrations/001_initial_schema.sql)
-- so target_admin_id has nothing to sit "AFTER" — this previously referenced
-- a column that never existed, which made this ALTER fail unconditionally.
ALTER TABLE audit_log ADD COLUMN target_admin_id INT UNSIGNED NULL AFTER entity_id;
ALTER TABLE audit_log ADD CONSTRAINT fk_audit_log_target_admin
    FOREIGN KEY (target_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL;

-- Index for faster role lookups
CREATE INDEX idx_admin_users_role ON admin_users(admin_role_id);
