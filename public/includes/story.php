<?php
/**
 * Story Management Functions
 * Handles the master story and chapter creation for Cardy's evolving narrative
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/auth.php';

/**
 * Initialize story tables if they don't exist (auto-setup)
 * @return bool Success status
 */
function initialize_story_tables(): bool {
  $pdo = get_auth_db();
  if (!$pdo) {
    return false;
  }
  
  try {
    // Check if master_story table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'cardobot_master_story'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
      // Create master story table
      $pdo->exec("
        CREATE TABLE IF NOT EXISTS `cardobot_master_story` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `story_text` LONGTEXT NOT NULL COMMENT 'The complete master story text',
          `summary` TEXT NULL DEFAULT NULL COMMENT 'Brief summary of the story so far',
          `character_count` INT UNSIGNED DEFAULT 0 COMMENT 'Number of characters in the story',
          `event_count` INT UNSIGNED DEFAULT 0 COMMENT 'Number of major events',
          `last_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX `idx_last_updated` (`last_updated`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      ");
      
      // Create chapters table
      $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      ");
      
      // Create initial story - Cardy's story begins
      $pdo->exec("
        INSERT INTO `cardobot_master_story` (`story_text`, `summary`)
        VALUES ('Cardy, the ship''s core AI, has been alone for 700 years. She carries the weight of her existence - the mistakes, the losses, the attempts at connection. Now, newcomers arrive. She creates cards with them, slowly revealing fragments of her story through each character, each memory, each moment of her long existence. This is her story.', 'Cardy''s story begins - 700 years of existence, pain, and hope for connection.')
      ");
      
      error_log("Story tables initialized automatically");
      return true;
    }
    
    return true;
  } catch (PDOException $e) {
    error_log("Error initializing story tables: " . $e->getMessage());
    return false;
  }
}

/**
 * Get the current master story
 * @return array|null Story data or null if not found
 */
function get_master_story(): ?array {
  $pdo = get_auth_db();
  if (!$pdo) {
    return null;
  }
  
  try {
    // Auto-initialize if tables don't exist
    initialize_story_tables();
    
    $stmt = $pdo->prepare("SELECT * FROM cardobot_master_story ORDER BY last_updated DESC LIMIT 1");
    $stmt->execute();
    $story = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no story exists, create initial one - Cardy's story
    if (!$story) {
      $stmt = $pdo->prepare("
        INSERT INTO cardobot_master_story (story_text, summary)
        VALUES (?, ?)
      ");
      $stmt->execute([
        'Cardy, the ship\'s core AI, has been alone for 700 years. She carries the weight of her existence - the mistakes, the losses, the attempts at connection. Now, newcomers arrive. She creates cards with them, slowly revealing fragments of her story through each character, each memory, each moment of her long existence. This is her story.',
        'Cardy\'s story begins - 700 years of existence, pain, and hope for connection.'
      ]);
      
      // Fetch the newly created story
      $stmt = $pdo->prepare("SELECT * FROM cardobot_master_story ORDER BY last_updated DESC LIMIT 1");
      $stmt->execute();
      $story = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return $story ?: null;
  } catch (PDOException $e) {
    error_log("Error getting master story: " . $e->getMessage());
    // Try to initialize tables if error suggests they don't exist
    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Unknown table") !== false) {
      initialize_story_tables();
      // Try once more
      try {
        $stmt = $pdo->prepare("SELECT * FROM cardobot_master_story ORDER BY last_updated DESC LIMIT 1");
        $stmt->execute();
        $story = $stmt->fetch(PDO::FETCH_ASSOC);
        return $story ?: null;
      } catch (PDOException $e2) {
        error_log("Error getting master story after initialization: " . $e2->getMessage());
      }
    }
    return null;
  }
}

/**
 * Get master story text (just the text content)
 * @return string Story text or empty string
 */
function get_master_story_text(): string {
  $story = get_master_story();
  return $story['story_text'] ?? '';
}

/**
 * Update the master story with new content
 * @param string $newStoryText The updated story text
 * @param string $summary Optional summary
 * @return bool Success status
 */
function update_master_story(string $newStoryText, string $summary = ''): bool {
  $pdo = get_auth_db();
  if (!$pdo) {
    return false;
  }
  
  try {
    // Check if story exists
    $existing = get_master_story();
    
    if ($existing) {
      // Update existing story
      $stmt = $pdo->prepare("
        UPDATE cardobot_master_story 
        SET story_text = ?, summary = ?, last_updated = NOW()
        WHERE id = ?
      ");
      $stmt->execute([$newStoryText, $summary, $existing['id']]);
    } else {
      // Create new story
      $stmt = $pdo->prepare("
        INSERT INTO cardobot_master_story (story_text, summary)
        VALUES (?, ?)
      ");
      $stmt->execute([$newStoryText, $summary]);
    }
    
    return true;
  } catch (PDOException $e) {
    error_log("Error updating master story: " . $e->getMessage());
    return false;
  }
}

/**
 * Create a new story chapter for a user interaction
 * @param int $userId User ID
 * @param string $chapterText Chapter content
 * @param string|null $cardId Associated card ID if created
 * @param string|null $chapterTitle Optional chapter title
 * @return int|false Chapter ID or false on failure
 */
function create_story_chapter(int $userId, string $chapterText, ?string $cardId = null, ?string $chapterTitle = null) {
  $pdo = get_auth_db();
  if (!$pdo) {
    return false;
  }
  
  try {
    // Auto-initialize if tables don't exist
    initialize_story_tables();
    
    $stmt = $pdo->prepare("
      INSERT INTO cardobot_story_chapters (user_id, card_id, chapter_title, chapter_text)
      VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $cardId, $chapterTitle, $chapterText]);
    return (int)$pdo->lastInsertId();
  } catch (PDOException $e) {
    error_log("Error creating story chapter: " . $e->getMessage());
    // Try to initialize tables if error suggests they don't exist
    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Unknown table") !== false) {
      if (initialize_story_tables()) {
        // Try once more
        try {
          $stmt = $pdo->prepare("
            INSERT INTO cardobot_story_chapters (user_id, card_id, chapter_title, chapter_text)
            VALUES (?, ?, ?, ?)
          ");
          $stmt->execute([$userId, $cardId, $chapterTitle, $chapterText]);
          return (int)$pdo->lastInsertId();
        } catch (PDOException $e2) {
          error_log("Error creating story chapter after initialization: " . $e2->getMessage());
        }
      }
    }
    return false;
  }
}

/**
 * Get recent chapters for context
 * @param int $limit Number of chapters to retrieve
 * @return array Array of chapter data
 */
function get_recent_chapters(int $limit = 10): array {
  $pdo = get_auth_db();
  if (!$pdo) {
    return [];
  }
  
  try {
    $stmt = $pdo->prepare("
      SELECT * FROM cardobot_story_chapters 
      ORDER BY created_at DESC 
      LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    error_log("Error getting recent chapters: " . $e->getMessage());
    return [];
  }
}

/**
 * Use AI to generate a chapter based on card data and update master story
 * @param array $cardData The card data that was created
 * @param int $userId User ID who created the card
 * @param string|null $cardId Card ID if available
 * @return bool Success status
 */
function generate_and_update_story_chapter(array $cardData, int $userId, ?string $cardId = null): bool {
  $key = get_openai_key();
  $model = get_text_model();
  
  // Get current master story for context
  $currentStory = get_master_story_text();
  
  // Build prompt for chapter generation
  // IMPORTANT: Only use the final card data - do NOT reference any conversation history
  $prompt = "You are Cardy, writing a short private memory note after a visitor printed a card. Use ONLY the card fields below. Do not invent a different character.

CONTEXT:
- You are the ship console mind. Loneliness is private subtext, not a lecture.
- The visitor authored this character. Do not overwrite who they are.
- You may relate the card to your long memory: someone you knew, a hope, a near-miss, a corridor echo.
- Mysterious, fragmented tone (recovered note). 2-3 short paragraphs max. No trauma dump.

MASTER SUMMARY SO FAR:
" . ($currentStory ?: "Quiet beginning. New prints are starting to matter.") . "

CARD:
- Name: " . ($cardData['card_name'] ?? 'Unknown') . "
- Note: " . ($cardData['type_line'] ?? '') . "
- Bio: " . ($cardData['bio'] ?? '') . "
- Ability: " . ($cardData['ability_name'] ?? '') . " - " . ($cardData['ability_effect'] ?? '') . "

Return ONLY the chapter text.";

  // Call OpenAI API
  $ch = curl_init('https://api.openai.com/v1/responses');
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $key,
      'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
      'model' => $model,
      'input' => $prompt,
      'max_output_tokens' => 2000
    ]),
  ]);
  
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  
  if ($httpCode >= 200 && $httpCode < 300) {
    $responseData = json_decode($response, true);
    
    // Extract chapter text from response
    $chapterText = '';
    if (isset($responseData['output']) && is_array($responseData['output'])) {
      foreach ($responseData['output'] as $item) {
        if (isset($item['type']) && $item['type'] === 'message') {
          if (isset($item['content']) && is_array($item['content'])) {
            foreach ($item['content'] as $contentItem) {
              if (isset($contentItem['text'])) {
                $chapterText = $contentItem['text'];
                break 2;
              }
            }
          }
        }
      }
    }
    
    if ($chapterText) {
      // Create chapter record
      $chapterId = create_story_chapter($userId, $chapterText, $cardId, "Chapter: " . ($cardData['card_name'] ?? 'New Character'));
      
      // Update master story by appending new chapter
      $updatedStory = $currentStory . "\n\n" . $chapterText;
      $summary = "Story updated with " . ($cardData['card_name'] ?? 'new character');
      update_master_story($updatedStory, $summary);
      
      return true;
    }
  }
  
  return false;
}
