-- =============================================================================
-- App Manager — MySQL schema
-- Target: Laragon local MySQL 8.x, charset utf8mb4, collation utf8mb4_unicode_ci
--
-- Notes on column types:
--   * Primary keys are signed INT to match the existing `apps.id` column
--     created by earlier versions of this app. Mixing signed/unsigned in
--     FK relationships is not allowed by MySQL, so we stay signed end-to-end.
--   * Run with:   mysql -u root < api/schema.sql
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `app_manager`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `app_manager`;

-- -----------------------------------------------------------------------------
-- apps — top-level project registry
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `apps` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `path`       VARCHAR(512) NOT NULL,
  `notes`      TEXT NULL,
  `ulasan`     JSON NULL COMMENT 'Work log entries: [{tarikh, kerja}]',
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `is_pinned`  TINYINT(1) NOT NULL DEFAULT 0,
  `pinned_at`  TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_apps_name` (`name`),
  KEY `idx_apps_updated_at` (`updated_at`),
  KEY `idx_apps_pinned` (`is_pinned`, `pinned_at`),
  KEY `idx_apps_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- app_stack — detected tech stack per app (PHP, Node, Python, other)
-- One app can have multiple stacks (e.g. Laravel backend + Vue 3 frontend).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_stack` (
  `id`               INT NOT NULL AUTO_INCREMENT,
  `app_id`           INT NOT NULL,
  `type`             ENUM('php','node','python','other') NOT NULL,
  `framework`        VARCHAR(80) NULL,
  `language_version` VARCHAR(40) NULL,
  `dependencies`     JSON NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_app_stack_type` (`app_id`, `type`),
  KEY `idx_app_stack_type` (`type`),
  CONSTRAINT `fk_app_stack_app`
    FOREIGN KEY (`app_id`) REFERENCES `apps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- app_scm — git remote, branch, last commit, working-tree status
-- One row per app (1:1). Stores the "current" SCM snapshot.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_scm` (
  `id`                    INT NOT NULL AUTO_INCREMENT,
  `app_id`                INT NOT NULL,
  `remote_url`            VARCHAR(512) NULL,
  `branch`                VARCHAR(120) NULL,
  `last_commit_hash`      VARCHAR(64) NULL,
  `last_commit_message`   TEXT NULL,
  `last_commit_author`    VARCHAR(120) NULL,
  `last_commit_date`      DATETIME NULL,
  `status`                ENUM('clean','dirty','no_git') NOT NULL DEFAULT 'no_git',
  `changed_files_count`   INT NOT NULL DEFAULT 0,
  `untracked_files_count` INT NOT NULL DEFAULT 0,
  `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_app_scm_app` (`app_id`),
  KEY `idx_app_scm_status` (`status`),
  KEY `idx_app_scm_branch` (`branch`),
  CONSTRAINT `fk_app_scm_app`
    FOREIGN KEY (`app_id`) REFERENCES `apps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- app_changed_files — files reported by `git status --porcelain`
-- Snapshotted on every scan: cleared + re-inserted per app.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_changed_files` (
  `id`        INT NOT NULL AUTO_INCREMENT,
  `scm_id`    INT NOT NULL,
  `file_path` VARCHAR(1024) NOT NULL,
  `status`    ENUM('A','M','D') NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_app_changed_files_scm` (`scm_id`),
  CONSTRAINT `fk_app_changed_files_scm`
    FOREIGN KEY (`scm_id`) REFERENCES `app_scm` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- app_services — manual registry of third-party services per app
-- Not auto-scanned; user enters in the Detail view.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_services` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `app_id`       INT NOT NULL,
  `service_name` VARCHAR(120) NOT NULL,
  `service_type` ENUM(
    'auth','database','cache','storage','email',
    'payment','sms','api','monitoring','search',
    'queue','cdn','other'
  ) NOT NULL DEFAULT 'other',
  `provider`     VARCHAR(120) NULL,
  `endpoint_url` VARCHAR(512) NULL,
  `notes`        TEXT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_app_services_app` (`app_id`),
  KEY `idx_app_services_type` (`service_type`),
  CONSTRAINT `fk_app_services_app`
    FOREIGN KEY (`app_id`) REFERENCES `apps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- app_scan_log — last scan timestamp per app (used by dashboard "last scan")
-- One row per app, updated on every successful scan.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_scan_log` (
  `app_id`     INT NOT NULL,
  `scanned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `source`     ENUM('manual','auto','cli') NOT NULL DEFAULT 'manual',
  PRIMARY KEY (`app_id`),
  KEY `idx_app_scan_log_scanned_at` (`scanned_at`),
  CONSTRAINT `fk_app_scan_log_app`
    FOREIGN KEY (`app_id`) REFERENCES `apps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- app_notes — timestamped catatan/ulasan journal per app.
-- Each row is an individual note with its own creation and update time.
-- -----------------------------------------------------------------------------
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
