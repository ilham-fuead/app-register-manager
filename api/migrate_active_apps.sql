-- Migration: add is_active column to apps (toggle active/inactive per app)
-- Portable MySQL 8 version (no MariaDB-only IF NOT EXISTS syntax).

USE `app_manager`;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME = 'apps'
                     AND COLUMN_NAME = 'is_active');

SET @sql = IF(@col_exists = 0,
              'ALTER TABLE apps ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER notes',
              'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME = 'apps'
                     AND INDEX_NAME = 'idx_apps_active');

SET @sql = IF(@idx_exists = 0,
              'CREATE INDEX idx_apps_active ON apps (is_active)',
              'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;