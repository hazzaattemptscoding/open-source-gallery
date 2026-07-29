-- Track original file extension (jpg or png) to preserve format in storage
ALTER TABLE photos ADD COLUMN file_extension CHAR(3) CHARACTER SET ascii DEFAULT 'jpg' NOT NULL AFTER media_type;
