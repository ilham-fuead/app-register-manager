-- App Manager Configuration Table
-- Stores user preference for root path and other settings.

CREATE TABLE IF NOT EXISTS app_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(255) NOT NULL UNIQUE,
    value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default configuration values if table is empty
INSERT IGNORE INTO app_config (`key`, value) VALUES
    ('root_path', 'C:/laragon/www');