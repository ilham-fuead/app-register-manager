-- Migration: add ulasan JSON column to apps for work log entries
-- Portable MySQL 8 version (no MariaDB-only IF NOT EXISTS syntax).
-- Each ulasan entry: { "tarikh": "2026-08-14", "kerja": "Deployment fix" }

USE `app_manager`;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'apps'
                   AND COLUMN_NAME = 'ulasan');

SET @sql = IF(@col_exists = 0,
              'ALTER TABLE apps ADD COLUMN ulasan JSON NULL AFTER is_active',
              'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;