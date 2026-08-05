-- Card-o-Bot active chat session persistence (also auto-created by cardy_ensure_chat_sessions_schema())

CREATE TABLE IF NOT EXISTS `cardobot_chat_sessions` (
  `user_id` INT UNSIGNED NOT NULL PRIMARY KEY,
  `session_id` VARCHAR(64) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  INDEX `idx_session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
