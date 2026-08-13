-- Migration: add pinned columns to apps (pin any card to top)
-- Portable MySQL 8 version (no MariaDB-only IF NOT EXISTS syntax).

USE `app_manager`;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME = 'apps'
                     AND COLUMN_NAME = 'is_pinned');

SET @sql = IF(@col_exists = 0,
              'ALTER TABLE apps ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER notes',
              'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME = 'apps'
                     AND COLUMN_NAME = 'pinned_at');

SET @sql = IF(@col_exists = 0,
              'ALTER TABLE apps ADD COLUMN pinned_at TIMESTAMP NULL DEFAULT NULL AFTER is_pinned',
              'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME = 'apps'
                     AND INDEX_NAME = 'idx_apps_pinned');

SET @sql = IF(@idx_exists = 0,
              'CREATE INDEX idx_apps_pinned ON apps (is_pinned, pinned_at)',
              'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
