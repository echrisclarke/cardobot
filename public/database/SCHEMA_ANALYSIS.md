# Database Schema Analysis

## ✅ Current Schema Coverage

### **Core Features (Fully Covered)**

1. **User Authentication** ✅
   - `cardobot_users` table covers:
     - Password authentication
     - Google OAuth authentication
     - Admin users
     - User profiles (name, email, picture)

2. **Card Creation** ✅
   - `cardobot_cards` table covers:
     - Card type (BOT/CRITTER)
     - All card attributes (nickname, bio, power, ability)
     - All stats (HP, ATT, STR, LOS, CON, NPO)
     - Colors (hue, saturation, lightness)
     - Image URL (where card image is stored)
     - Drawing data (base64 canvas data)
     - Flexible JSON column for additional attributes

3. **Card Management** ✅
   - View cards: Query by `user_id`
   - Edit cards: Update existing records
   - Delete cards: DELETE with CASCADE
   - Card timestamps: `created_at`, `modified_at`

4. **Session Tracking** ✅
   - `cardobot_sessions` table for analytics

## ⚠️ Potential Future Use Cases (Not Currently Covered)

### **1. Card Collections/Decks**
If users want to organize cards into decks:
```sql
CREATE TABLE `cardobot_decks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `cardobot_users`(`id`) ON DELETE CASCADE
);

CREATE TABLE `cardobot_deck_cards` (
  `deck_id` INT UNSIGNED NOT NULL,
  `card_id` INT UNSIGNED NOT NULL,
  `order` INT UNSIGNED DEFAULT 0,
  `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`deck_id`, `card_id`),
  FOREIGN KEY (`deck_id`) REFERENCES `cardobot_decks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`card_id`) REFERENCES `cardobot_cards`(`id`) ON DELETE CASCADE
);
```

### **2. Card Sharing/Public Gallery**
If cards can be shared publicly:
```sql
ALTER TABLE `cardobot_cards` 
  ADD COLUMN `is_public` BOOLEAN DEFAULT FALSE,
  ADD COLUMN `share_token` VARCHAR(64) NULL UNIQUE,
  ADD INDEX `idx_is_public` (`is_public`);
```

### **3. Card Versions/History**
If you want to track edit history:
```sql
CREATE TABLE `cardobot_card_versions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `card_id` INT UNSIGNED NOT NULL,
  `version` INT UNSIGNED NOT NULL,
  `data_json` JSON NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`card_id`) REFERENCES `cardobot_cards`(`id`) ON DELETE CASCADE,
  INDEX `idx_card_version` (`card_id`, `version`)
);
```

### **4. Card Tags/Categories**
If users want to tag cards:
```sql
CREATE TABLE `cardobot_tags` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `color` VARCHAR(7) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `cardobot_card_tags` (
  `card_id` INT UNSIGNED NOT NULL,
  `tag_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`card_id`, `tag_id`),
  FOREIGN KEY (`card_id`) REFERENCES `cardobot_cards`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `cardobot_tags`(`id`) ON DELETE CASCADE
);
```

### **5. API Usage Tracking**
If you want to track OpenAI API usage per user:
```sql
CREATE TABLE `cardobot_api_usage` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `endpoint` VARCHAR(50) NOT NULL,
  `model` VARCHAR(50) NULL,
  `tokens_used` INT UNSIGNED NULL,
  `cost` DECIMAL(10, 6) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `cardobot_users`(`id`) ON DELETE SET NULL,
  INDEX `idx_user_date` (`user_id`, `created_at`)
);
```

### **6. Card Favorites/Bookmarks**
If users can favorite cards:
```sql
ALTER TABLE `cardobot_cards` 
  ADD COLUMN `favorite_count` INT UNSIGNED DEFAULT 0;

CREATE TABLE `cardobot_favorites` (
  `user_id` INT UNSIGNED NOT NULL,
  `card_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `card_id`),
  FOREIGN KEY (`user_id`) REFERENCES `cardobot_users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`card_id`) REFERENCES `cardobot_cards`(`id`) ON DELETE CASCADE
);
```

### **7. Card Comments/Ratings**
If there's social features:
```sql
CREATE TABLE `cardobot_comments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `card_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `comment` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`card_id`) REFERENCES `cardobot_cards`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `cardobot_users`(`id`) ON DELETE CASCADE,
  INDEX `idx_card_id` (`card_id`)
);

CREATE TABLE `cardobot_ratings` (
  `card_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`card_id`, `user_id`),
  FOREIGN KEY (`card_id`) REFERENCES `cardobot_cards`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `cardobot_users`(`id`) ON DELETE CASCADE
);
```

## 📊 Current Schema Assessment

### **Strengths:**
✅ Covers all planned features from IMPLEMENTATION_PLAN.md
✅ Flexible JSON column for future attributes
✅ Proper foreign keys and indexes
✅ Supports both password and Google OAuth
✅ Timestamps for audit trail
✅ CASCADE deletes for data integrity

### **Potential Gaps:**
⚠️ No card organization (decks/collections)
⚠️ No sharing/public gallery features
⚠️ No version history
⚠️ No tagging system
⚠️ No API usage tracking
⚠️ No social features (comments, ratings, favorites)

## 🎯 Recommendation

**For MVP/Initial Release:** ✅ **Current schema is sufficient**

The current schema covers all features in the implementation plan:
- User authentication ✅
- Card creation ✅
- Card management (view, edit, delete) ✅
- Drawing system ✅

**For Future Enhancements:** Add tables as needed

Only add additional tables when you actually need the features. Don't over-engineer upfront.

## 🔄 Migration Path

If you need to add features later:
1. Create new tables (don't modify existing ones)
2. Use ALTER TABLE for simple additions (like `is_public` flag)
3. Keep backward compatibility
4. Run migrations during low-traffic periods
