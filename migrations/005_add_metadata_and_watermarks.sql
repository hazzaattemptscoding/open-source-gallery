-- Migration: Add photo metadata, EXIF, and watermark customization


-- Photo metadata (EXIF, technical specs)
ALTER TABLE photos ADD COLUMN exif_data JSON AFTER original_filename;
ALTER TABLE photos ADD COLUMN camera_make VARCHAR(100);
ALTER TABLE photos ADD COLUMN camera_model VARCHAR(100);
ALTER TABLE photos ADD COLUMN lens VARCHAR(100);
ALTER TABLE photos ADD COLUMN focal_length VARCHAR(50);
ALTER TABLE photos ADD COLUMN aperture VARCHAR(20);
ALTER TABLE photos ADD COLUMN shutter_speed VARCHAR(50);
ALTER TABLE photos ADD COLUMN iso INT UNSIGNED;
-- taken_at already exists (migrations/001_initial_schema.sql) — this used
-- to re-add it, which fails outright on any database that already has 001
-- applied, i.e. every real install.

CREATE INDEX idx_camera_make ON photos(camera_make);
CREATE INDEX idx_taken_at ON photos(taken_at);

-- Watermark customization settings
CREATE TABLE watermark_settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    position ENUM('bottom_right', 'bottom_left', 'bottom_center', 'center') DEFAULT 'bottom_right',
    opacity DECIMAL(3,2) DEFAULT 0.8,
    text VARCHAR(100),
    enabled TINYINT(1) DEFAULT 1,
    apply_to_sizes VARCHAR(255),
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default watermark settings
INSERT INTO watermark_settings (position, opacity, enabled) VALUES ('bottom_right', 0.8, 1);
