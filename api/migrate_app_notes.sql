-- Migration: app_notes (timestamped catatan/ulasan journal)
CREATE TABLE IF NOT EXISTS `app_notes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `app_id`     INT NOT NULL,
  `content`    TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_app_notes_app` (`app_id`),
  KEY `idx_app_notes_created_at` (`created_at`),
  CONSTRAINT `fk_app_notes_app`
    FOREIGN KEY (`app_id`) REFERENCES `apps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
