-- Card-o-Bot i18n tables (also auto-created by i18n_ensure_schema())

CREATE TABLE IF NOT EXISTS `cardobot_locales` (
  `code` VARCHAR(32) NOT NULL PRIMARY KEY,
  `name_en` VARCHAR(120) NOT NULL DEFAULT '',
  `name_native` VARCHAR(120) NOT NULL DEFAULT '',
  `status` ENUM('ready','building','rejected') NOT NULL DEFAULT 'building',
  `reject_reason` VARCHAR(255) NULL DEFAULT NULL,
  `catalog_version` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cardobot_ui_strings` (
  `locale` VARCHAR(32) NOT NULL,
  `string_key` VARCHAR(120) NOT NULL,
  `value` TEXT NOT NULL,
  `source` ENUM('seed','ai') NOT NULL DEFAULT 'ai',
  `catalog_version` INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`locale`, `string_key`),
  INDEX `idx_locale` (`locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `cardobot_users` ADD COLUMN IF NOT EXISTS `preferred_locale` VARCHAR(32) NULL DEFAULT NULL;
ALTER TABLE `cardobot_cards` ADD COLUMN IF NOT EXISTS `locale` VARCHAR(32) NULL DEFAULT NULL;
