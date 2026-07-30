-- Migration: Add photo metadata, EXIF, and watermark customization (SQLite variant)

-- Photo metadata (EXIF, technical specs)
ALTER TABLE photos ADD COLUMN exif_data TEXT;
ALTER TABLE photos ADD COLUMN camera_make VARCHAR(100);
ALTER TABLE photos ADD COLUMN camera_model VARCHAR(100);
ALTER TABLE photos ADD COLUMN lens VARCHAR(100);
ALTER TABLE photos ADD COLUMN focal_length VARCHAR(50);
ALTER TABLE photos ADD COLUMN aperture VARCHAR(20);
ALTER TABLE photos ADD COLUMN shutter_speed VARCHAR(50);
ALTER TABLE photos ADD COLUMN iso INTEGER;

CREATE INDEX idx_camera_make ON photos(camera_make);
CREATE INDEX idx_taken_at ON photos(taken_at);

-- Watermark customization settings
CREATE TABLE watermark_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    position TEXT DEFAULT 'bottom_right',
    opacity DECIMAL(3,2) DEFAULT 0.8,
    text VARCHAR(100),
    enabled INTEGER DEFAULT 1,
    apply_to_sizes VARCHAR(255),
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default watermark settings
INSERT INTO watermark_settings (position, opacity, enabled) VALUES ('bottom_right', 0.8, 1);
