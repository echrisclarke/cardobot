# Card-o-Bot Database Guide

Complete reference guide for working with the Card-o-Bot database.

---

## 📋 Table of Contents

1. [Database Overview](#database-overview)
2. [Setup & Installation](#setup--installation)
3. [Database Structure](#database-structure)
4. [Connection & Configuration](#connection--configuration)
5. [Common Queries](#common-queries)
6. [Best Practices](#best-practices)
7. [Migration & Maintenance](#migration--maintenance)
8. [Troubleshooting](#troubleshooting)
9. [Future Enhancements](#future-enhancements)

---

## 🗄️ Database Overview

### **Database Name**
Configurable via `DB_NAME` in `.env` file (any name you choose)

### **Engine**
MySQL/MariaDB with InnoDB engine

### **Character Set**
`utf8mb4_unicode_ci` (supports emojis and full Unicode)

### **Tables**
- `cardobot_users` - User accounts and authentication
- `cardobot_cards` - Card data and attributes
- `cardobot_sessions` - Session tracking (optional)

---

## 🚀 Setup & Installation

### **Initial Setup**

1. **Set Database Name in .env**
   Add to `.env`:
   ```env
   DB_NAME=your_database_name
   ```
   
   The setup script will create it automatically, or create manually:
   ```sql
   CREATE DATABASE your_database_name 
     CHARACTER SET utf8mb4 
     COLLATE utf8mb4_unicode_ci;
   ```

2. **Run Setup Script**
   - Via browser: `https://herbiecreative.com/cardobot/database/setup.php`
   - Via CLI: `php cardobot/database/setup.php`
   - Or manually: Run `schema.sql` in phpMyAdmin

3. **Verify Tables**
   ```sql
   SHOW TABLES;
   -- Should show: cardobot_users, cardobot_cards, cardobot_sessions
   ```

### **Environment Configuration**

Update `.env` file with **app-specific** credentials (recommended):

```env
# Card-o-Bot specific database
CARDOBOT_DB_NAME=your_database_name
CARDOBOT_DB_HOST=localhost
CARDOBOT_DB_USER=your_database_user
CARDOBOT_DB_PASS=your_database_password
CARDOBOT_DB_CHARSET=utf8mb4
```

**Or use generic** credentials (shared across apps):

```env
# Generic database (used if app-specific not set)
DB_HOST=localhost
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASS=your_database_password
DB_CHARSET=utf8mb4
```

**Multiple Apps:** You can have different databases for different apps in the same `.env`:
```env
# Card-o-Bot
CARDOBOT_DB_NAME=cardobot_db
CARDOBOT_DB_USER=herbiecr
CARDOBOT_DB_PASS=password1

# ROG (another app)
ROG_DB_NAME=rog_db
ROG_DB_USER=herbiecr
ROG_DB_PASS=password1

# Generic fallback (if app-specific not set)
DB_NAME=default_db
DB_USER=herbiecr
DB_PASS=password1
```

---

## 📊 Database Structure

### **1. cardobot_users**

**Purpose:** User accounts and authentication

**Columns:**
| Column | Type | Description |
|--------|------|-------------|
| `id` | INT UNSIGNED | Primary key, auto-increment |
| `username` | VARCHAR(50) | Unique username |
| `password_hash` | VARCHAR(255) | Bcrypt password hash (NULL for Google users) |
| `email` | VARCHAR(255) | User email (optional) |
| `google_id` | VARCHAR(255) | Google OAuth ID (NULL for password users) |
| `name` | VARCHAR(255) | Full name |
| `given_name` | VARCHAR(255) | First name |
| `family_name` | VARCHAR(255) | Last name |
| `picture` | VARCHAR(500) | Profile picture URL |
| `auth_method` | ENUM | 'password' or 'google' |
| `is_admin` | BOOLEAN | Admin status (default: FALSE) |
| `created_at` | TIMESTAMP | Account creation time |
| `last_login` | TIMESTAMP | Last login time |
| `updated_at` | TIMESTAMP | Last update time (auto) |

**Indexes:**
- `idx_username` - Fast username lookups
- `idx_google_id` - Fast Google OAuth lookups
- `idx_email` - Fast email lookups
- `idx_auth_method` - Filter by auth method

**Example Data:**
```sql
INSERT INTO cardobot_users 
  (username, password_hash, auth_method, is_admin, created_at)
VALUES 
  ('herbie', '$2y$10$...', 'password', TRUE, NOW());
```

---

### **2. cardobot_cards**

**Purpose:** Card data and attributes

**Columns:**
| Column | Type | Description |
|--------|------|-------------|
| `id` | INT UNSIGNED | Primary key, auto-increment |
| `card_id` | VARCHAR(50) | Unique card identifier (e.g., "card-1234567890") |
| `user_id` | INT UNSIGNED | Foreign key to `cardobot_users.id` |
| `type` | ENUM | 'BOT' or 'CRITTER' |
| `image_url` | VARCHAR(500) | Path to card image file |
| `drawing_data` | LONGTEXT | Base64 encoded canvas drawing data |
| `nickname` | VARCHAR(100) | Card nickname |
| `bio` | TEXT | Card biography text |
| `power` | VARCHAR(255) | Card power name |
| `ability` | TEXT | Card ability description |
| `hp` | INT UNSIGNED | Hit Points stat |
| `att` | INT UNSIGNED | Attack stat |
| `str` | INT UNSIGNED | Strength stat |
| `los` | INT UNSIGNED | Line of Sight stat |
| `con` | INT UNSIGNED | Constitution stat |
| `npo` | INT UNSIGNED | NPO stat |
| `hue` | INT UNSIGNED | Color hue (0-360) |
| `saturation` | INT UNSIGNED | Color saturation (0-100) |
| `lightness` | INT UNSIGNED | Color lightness (0-100) |
| `attributes_json` | JSON | Additional flexible attributes |
| `created_at` | TIMESTAMP | Card creation time |
| `modified_at` | TIMESTAMP | Last modification time (auto) |

**Indexes:**
- `idx_user_id` - Fast user card queries
- `idx_card_id` - Fast card lookups
- `idx_type` - Filter by card type
- `idx_created_at` - Sort by creation date
- `idx_user_type` - Combined user + type queries

**Foreign Keys:**
- `user_id` → `cardobot_users.id` (CASCADE delete)

**Example Data:**
```sql
INSERT INTO cardobot_cards 
  (card_id, user_id, type, nickname, bio, hp, att, str, los, con, npo, hue, saturation, lightness)
VALUES 
  ('card-1234567890', 1, 'BOT', 'RoboBot', 'A friendly robot', 100, 75, 80, 60, 90, 50, 195, 65, 40);
```

---

### **3. cardobot_sessions**

**Purpose:** Session tracking for analytics (optional)

**Columns:**
| Column | Type | Description |
|--------|------|-------------|
| `id` | INT UNSIGNED | Primary key, auto-increment |
| `user_id` | INT UNSIGNED | Foreign key to `cardobot_users.id` (NULL for guests) |
| `session_id` | VARCHAR(128) | PHP session ID |
| `ip_address` | VARCHAR(45) | User IP (supports IPv6) |
| `user_agent` | VARCHAR(500) | Browser user agent |
| `created_at` | TIMESTAMP | Session start time |
| `last_activity` | TIMESTAMP | Last activity time (auto) |
| `expires_at` | TIMESTAMP | Session expiration time |

**Indexes:**
- `idx_session_id` - Fast session lookups
- `idx_user_id` - User session queries
- `idx_expires_at` - Cleanup expired sessions

---

## 🔌 Connection & Configuration

### **PHP Database Connection**

**Pattern (from `/ROG`):**
```php
<?php
require_once __DIR__ . '/../includes/env.php';

function get_db_connection() {
    $env = load_env();
    
    $host = $env['DB_HOST'] ?? 'localhost';
    $dbname = $env['DB_NAME'] ?? '';
    $username = $env['DB_USER'] ?? '';
    $password = $env['DB_PASS'] ?? $env['DB_PASSWORD'] ?? '';
    $charset = $env['DB_CHARSET'] ?? 'utf8mb4';
    
    try {
        $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        return new PDO($dsn, $username, $password, $options);
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}
```

**Usage:**
```php
$pdo = get_db_connection();
if (!$pdo) {
    die("Database connection failed");
}
```

---

## 📝 Common Queries

### **User Queries**

#### Get User by Username
```php
$stmt = $pdo->prepare("SELECT * FROM cardobot_users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();
```

#### Get User by Google ID
```php
$stmt = $pdo->prepare("SELECT * FROM cardobot_users WHERE google_id = ?");
$stmt->execute([$googleId]);
$user = $stmt->fetch();
```

#### Create New User
```php
$stmt = $pdo->prepare("
    INSERT INTO cardobot_users 
    (username, password_hash, auth_method, created_at)
    VALUES (?, ?, 'password', NOW())
");
$stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
$userId = $pdo->lastInsertId();
```

#### Update Last Login
```php
$stmt = $pdo->prepare("
    UPDATE cardobot_users 
    SET last_login = NOW() 
    WHERE id = ?
");
$stmt->execute([$userId]);
```

#### Check if Username Exists
```php
$stmt = $pdo->prepare("SELECT COUNT(*) FROM cardobot_users WHERE username = ?");
$stmt->execute([$username]);
$exists = $stmt->fetchColumn() > 0;
```

---

### **Card Queries**

#### Get All Cards for User
```php
$stmt = $pdo->prepare("
    SELECT * FROM cardobot_cards 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$cards = $stmt->fetchAll();
```

#### Get Card by Card ID
```php
$stmt = $pdo->prepare("SELECT * FROM cardobot_cards WHERE card_id = ?");
$stmt->execute([$cardId]);
$card = $stmt->fetch();
```

#### Create New Card
```php
$stmt = $pdo->prepare("
    INSERT INTO cardobot_cards 
    (card_id, user_id, type, nickname, bio, hp, att, str, los, con, npo, hue, saturation, lightness, image_url, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->execute([
    $cardId, $userId, $type, $nickname, $bio, 
    $hp, $att, $str, $los, $con, $npo,
    $hue, $saturation, $lightness, $imageUrl
]);
```

#### Update Card
```php
$stmt = $pdo->prepare("
    UPDATE cardobot_cards 
    SET nickname = ?, bio = ?, hp = ?, att = ?, str = ?, los = ?, con = ?, npo = ?,
        hue = ?, saturation = ?, lightness = ?, modified_at = NOW()
    WHERE card_id = ? AND user_id = ?
");
$stmt->execute([
    $nickname, $bio, $hp, $att, $str, $los, $con, $npo,
    $hue, $saturation, $lightness, $cardId, $userId
]);
```

#### Delete Card
```php
$stmt = $pdo->prepare("DELETE FROM cardobot_cards WHERE card_id = ? AND user_id = ?");
$stmt->execute([$cardId, $userId]);
```

#### Get Card Count for User
```php
$stmt = $pdo->prepare("SELECT COUNT(*) FROM cardobot_cards WHERE user_id = ?");
$stmt->execute([$userId]);
$count = $stmt->fetchColumn();
```

#### Get Cards by Type
```php
$stmt = $pdo->prepare("
    SELECT * FROM cardobot_cards 
    WHERE user_id = ? AND type = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$userId, $type]);
$cards = $stmt->fetchAll();
```

#### Search Cards by Nickname
```php
$stmt = $pdo->prepare("
    SELECT * FROM cardobot_cards 
    WHERE user_id = ? AND nickname LIKE ? 
    ORDER BY created_at DESC
");
$stmt->execute([$userId, "%{$searchTerm}%"]);
$cards = $stmt->fetchAll();
```

---

### **Advanced Queries**

#### Get User with Card Count
```php
$stmt = $pdo->prepare("
    SELECT u.*, COUNT(c.id) as card_count
    FROM cardobot_users u
    LEFT JOIN cardobot_cards c ON u.id = c.user_id
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->execute([$userId]);
$user = $stmt->fetch();
```

#### Get Recent Cards (All Users)
```php
$stmt = $pdo->query("
    SELECT c.*, u.username
    FROM cardobot_cards c
    JOIN cardobot_users u ON c.user_id = u.id
    ORDER BY c.created_at DESC
    LIMIT 20
");
$recentCards = $stmt->fetchAll();
```

#### Get Cards Created in Date Range
```php
$stmt = $pdo->prepare("
    SELECT * FROM cardobot_cards 
    WHERE user_id = ? 
    AND created_at BETWEEN ? AND ?
    ORDER BY created_at DESC
");
$stmt->execute([$userId, $startDate, $endDate]);
$cards = $stmt->fetchAll();
```

#### Get Most Active Users
```php
$stmt = $pdo->query("
    SELECT u.username, COUNT(c.id) as card_count
    FROM cardobot_users u
    JOIN cardobot_cards c ON u.id = c.user_id
    GROUP BY u.id
    ORDER BY card_count DESC
    LIMIT 10
");
$activeUsers = $stmt->fetchAll();
```

---

## ✅ Best Practices

### **1. Always Use Prepared Statements**

❌ **BAD:**
```php
$query = "SELECT * FROM cardobot_users WHERE username = '{$username}'";
$stmt = $pdo->query($query);
```

✅ **GOOD:**
```php
$stmt = $pdo->prepare("SELECT * FROM cardobot_users WHERE username = ?");
$stmt->execute([$username]);
```

### **2. Use Transactions for Multiple Operations**

```php
$pdo->beginTransaction();
try {
    // Insert card
    $stmt = $pdo->prepare("INSERT INTO cardobot_cards ...");
    $stmt->execute([...]);
    
    // Update user stats
    $stmt = $pdo->prepare("UPDATE cardobot_users SET ...");
    $stmt->execute([...]);
    
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}
```

### **3. Handle Errors Gracefully**

```php
try {
    $stmt = $pdo->prepare("SELECT * FROM cardobot_users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Handle not found
        return null;
    }
    
    return $user;
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    return null;
}
```

### **4. Use Appropriate Data Types**

- Use `INT UNSIGNED` for IDs and counts (no negative values)
- Use `VARCHAR` with appropriate length limits
- Use `TEXT` for longer content (bio, ability)
- Use `LONGTEXT` for very large data (drawing_data)
- Use `JSON` for flexible structured data
- Use `ENUM` for fixed value sets (type, auth_method)
- Use `TIMESTAMP` for dates (auto-updates)

### **5. Index Frequently Queried Columns**

Already indexed:
- `username`, `google_id`, `email` (users)
- `user_id`, `card_id`, `type` (cards)

Add indexes if you frequently query:
- `created_at` (already indexed)
- `modified_at` (if needed)
- Custom search fields

### **6. Clean Up Old Data**

```php
// Delete expired sessions
$stmt = $pdo->prepare("
    DELETE FROM cardobot_sessions 
    WHERE expires_at < NOW()
");
$stmt->execute();

// Delete cards older than X days (if needed)
$stmt = $pdo->prepare("
    DELETE FROM cardobot_cards 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)
");
$stmt->execute();
```

---

## 🔄 Migration & Maintenance

### **Backup Database**

```bash
# Via command line
mysqldump -u username -p cardobot_db > backup_$(date +%Y%m%d).sql

# Via phpMyAdmin
# Export → Select database → Go
```

### **Restore Database**

```bash
# Via command line
mysql -u username -p cardobot_db < backup_20250115.sql

# Via phpMyAdmin
# Import → Choose file → Go
```

### **Add New Column**

```sql
ALTER TABLE cardobot_cards 
  ADD COLUMN `new_field` VARCHAR(255) NULL 
  AFTER `existing_field`;
```

### **Add New Index**

```sql
ALTER TABLE cardobot_cards 
  ADD INDEX `idx_new_field` (`new_field`);
```

### **Migrate from JSON**

Run: `https://herbiecreative.com/cardobot/database/migrate-from-json.php`

---

## 🔧 Troubleshooting

### **Connection Issues**

**Error: "Access denied"**
- Check `.env` file credentials
- Verify database user has permissions
- Check `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`

**Error: "Unknown database"**
- Database doesn't exist
- Run: `CREATE DATABASE cardobot_db;`

**Error: "Table doesn't exist"**
- Run setup script: `setup.php`
- Or manually run `schema.sql`

### **Performance Issues**

**Slow Queries:**
- Check if indexes are being used: `EXPLAIN SELECT ...`
- Add indexes for frequently queried columns
- Limit result sets: `LIMIT 20`

**Large Result Sets:**
- Use pagination
- Limit fields: `SELECT id, username` not `SELECT *`

### **Data Integrity Issues**

**Foreign Key Violations:**
- Check if referenced user exists
- Use transactions for related operations
- Check CASCADE delete settings

**Duplicate Entries:**
- Check UNIQUE constraints
- Use `INSERT IGNORE` or `ON DUPLICATE KEY UPDATE`

---

## 🚀 Future Enhancements

See `SCHEMA_ANALYSIS.md` for potential future tables:
- Card collections/decks
- Public sharing
- Version history
- Tags/categories
- API usage tracking
- Social features

---

## 📚 Additional Resources

- **Schema File:** `schema.sql`
- **Setup Script:** `setup.php`
- **Migration Script:** `migrate-from-json.php`
- **Schema Analysis:** `SCHEMA_ANALYSIS.md`
- **README:** `README.md`

---

## 🔐 Security Notes

1. **Always use prepared statements** (prevents SQL injection)
2. **Never expose database credentials** (use `.env` file)
3. **Validate user input** before database operations
4. **Use transactions** for critical operations
5. **Set proper file permissions** on `.env` file (0600)
6. **Regular backups** of database
7. **Monitor error logs** for database issues

---

**Last Updated:** 2025-01-15
**Database Version:** 1.0
**Maintained By:** Card-o-Bot Development Team
