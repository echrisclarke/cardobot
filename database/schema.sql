-- Card-o-Bot Database Schema
-- Run this in phpMyAdmin SQL tab to create all tables

-- ============================================
-- 1. USERS TABLE
-- ============================================
-- Stores user authentication and profile data
CREATE TABLE IF NOT EXISTS `cardobot_users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NULL DEFAULT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `google_id` VARCHAR(255) NULL DEFAULT NULL,
  `name` VARCHAR(255) NULL DEFAULT NULL,
  `given_name` VARCHAR(255) NULL DEFAULT NULL,
  `family_name` VARCHAR(255) NULL DEFAULT NULL,
  `picture` VARCHAR(500) NULL DEFAULT NULL,
  `auth_method` ENUM('password', 'google') NOT NULL DEFAULT 'password',
  `is_admin` BOOLEAN NOT NULL DEFAULT FALSE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_username` (`username`),
  INDEX `idx_google_id` (`google_id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_auth_method` (`auth_method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. CARDS TABLE
-- ============================================
-- Stores card data (metadata and attributes)
CREATE TABLE IF NOT EXISTS `cardobot_cards` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `card_id` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` INT UNSIGNED NOT NULL,
  `type` ENUM('BOT', 'CRITTER') NOT NULL DEFAULT 'BOT',
  `image_url` VARCHAR(500) NULL DEFAULT NULL,
  `drawing_data` LONGTEXT NULL DEFAULT NULL COMMENT 'Base64 encoded canvas data',
  `nickname` VARCHAR(100) NULL DEFAULT NULL,
  `bio` TEXT NULL DEFAULT NULL,
  `power` VARCHAR(255) NULL DEFAULT NULL,
  `ability` TEXT NULL DEFAULT NULL,
  `hp` INT UNSIGNED NULL DEFAULT NULL,
  `att` INT UNSIGNED NULL DEFAULT NULL,
  `str` INT UNSIGNED NULL DEFAULT NULL,
  `los` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Line of Sight',
  `con` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Constitution',
  `npo` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NPO stat',
  `hue` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Color hue (0-360)',
  `saturation` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Color saturation (0-100)',
  `lightness` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Color lightness (0-100)',
  `attributes_json` JSON NULL DEFAULT NULL COMMENT 'Additional attributes as JSON',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `cardobot_users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_card_id` (`card_id`),
  INDEX `idx_type` (`type`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_user_type` (`user_id`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. SESSIONS TABLE (Optional - for tracking)
-- ============================================
-- Optional: Track user sessions (PHP handles sessions, but this is for analytics)
CREATE TABLE IF NOT EXISTS `cardobot_sessions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL DEFAULT NULL,
  `session_id` VARCHAR(128) NOT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` VARCHAR(500) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `cardobot_users`(`id`) ON DELETE CASCADE,
  INDEX `idx_session_id` (`session_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. IMAGE TASKS (durable paint jobs)
-- ============================================
CREATE TABLE IF NOT EXISTS `cardobot_image_tasks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `task_id` VARCHAR(64) NOT NULL UNIQUE,
  `user_id` INT UNSIGNED NOT NULL,
  `session_id` VARCHAR(64) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'generating',
  `source` VARCHAR(32) NULL DEFAULT NULL,
  `prompt` TEXT NULL,
  `image_url` VARCHAR(500) NULL DEFAULT NULL,
  `error` TEXT NULL,
  `visual_json` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `cardobot_users`(`id`) ON DELETE CASCADE,
  INDEX `idx_task_user` (`user_id`),
  INDEX `idx_task_session` (`session_id`),
  INDEX `idx_task_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. OPTIONAL EMBEDDING INDEX (ML sidecar may also store vectors on disk)
-- ============================================
CREATE TABLE IF NOT EXISTS `cardobot_card_embeddings` (
  `card_id` VARCHAR(50) NOT NULL PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `model` VARCHAR(100) NOT NULL,
  `dim` INT UNSIGNED NOT NULL,
  `vector_json` JSON NOT NULL,
  `text_fingerprint` VARCHAR(64) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `cardobot_users`(`id`) ON DELETE CASCADE,
  INDEX `idx_embed_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- NOTES:
-- ============================================
-- 1. All tables use InnoDB engine for foreign key support
-- 2. utf8mb4 charset supports full Unicode (emojis, etc.)
-- 3. Foreign keys use CASCADE delete (deleting user deletes their cards)
-- 4. Indexes added for common query patterns
-- 5. Timestamps track creation and modification times
-- 6. JSON column for flexible additional attributes
-- 7. NULL values allowed for optional fields (Google OAuth users don't have passwords)
-- 8. ML sidecar can index embeddings without this table (file store under /data/ml)
