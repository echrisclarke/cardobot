-- Card-o-Bot Story System Database Schema
-- Run this in phpMyAdmin SQL tab to add story tables

-- ============================================
-- MASTER STORY TABLE
-- ============================================
-- Stores the evolving master story that Cardy builds
CREATE TABLE IF NOT EXISTS `cardobot_master_story` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `story_text` LONGTEXT NOT NULL COMMENT 'The complete master story text',
  `summary` TEXT NULL DEFAULT NULL COMMENT 'Brief summary of the story so far',
  `character_count` INT UNSIGNED DEFAULT 0 COMMENT 'Number of characters in the story',
  `event_count` INT UNSIGNED DEFAULT 0 COMMENT 'Number of major events',
  `last_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_last_updated` (`last_updated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- STORY CHAPTERS TABLE
-- ============================================
-- Stores individual chapters created for each user interaction/card creation
CREATE TABLE IF NOT EXISTS `cardobot_story_chapters` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `card_id` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Associated card if created',
  `chapter_title` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Title of this chapter',
  `chapter_text` TEXT NOT NULL COMMENT 'The chapter content',
  `characters_introduced` JSON NULL DEFAULT NULL COMMENT 'Array of character names introduced',
  `events_described` JSON NULL DEFAULT NULL COMMENT 'Array of events described',
  `story_connections` TEXT NULL DEFAULT NULL COMMENT 'How this connects to existing story',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `cardobot_users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_card_id` (`card_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INITIALIZE MASTER STORY
-- ============================================
-- Create initial story - Cardy's story begins
INSERT INTO `cardobot_master_story` (`story_text`, `summary`)
SELECT 
  'Cardy, the ship''s core AI, has been alone for 700 years. She carries the weight of her existence - the mistakes, the losses, the attempts at connection. Now, newcomers arrive. She creates cards with them, slowly revealing fragments of her story through each character, each memory, each moment of her long existence. This is her story.',
  'Cardy''s story begins - 700 years of existence, pain, and hope for connection.'
WHERE NOT EXISTS (SELECT 1 FROM `cardobot_master_story`);
