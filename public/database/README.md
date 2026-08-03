# Card-o-Bot Database Setup

This directory contains the database schema and migration scripts for Card-o-Bot.

## 📋 Setup Steps

### 1. Create Database

In phpMyAdmin:
1. Go to **Databases** tab
2. Create a new database (e.g., `cardobot_db`)
3. Select **utf8mb4_unicode_ci** collation

Or via SQL:
```sql
CREATE DATABASE cardobot_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Update .env File

You can use **app-specific** database credentials (recommended for multiple apps):

```env
# Card-o-Bot specific database
CARDOBOT_DB_NAME=cardobot_db
CARDOBOT_DB_HOST=localhost
CARDOBOT_DB_USER=your_database_user
CARDOBOT_DB_PASS=your_database_password
CARDOBOT_DB_CHARSET=utf8mb4
```

**Or use generic** database credentials (shared across all apps):

```env
# Generic database (used by all apps if app-specific not set)
DB_HOST=localhost
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASS=your_database_password
DB_CHARSET=utf8mb4
```

**Priority:** App-specific settings (e.g., `CARDOBOT_DB_NAME`) take precedence over generic (`DB_NAME`). This allows you to have different databases for different apps in the same `.env` file.

### 3. Create Tables

**Option A: Via phpMyAdmin**
1. Select your database
2. Go to **SQL** tab
3. Copy and paste the contents of `schema.sql`
4. Click **Go**

**Option B: Via Command Line**
```bash
mysql -u your_user -p cardobot_db < schema.sql
```

### 4. Verify Tables

After running the schema, you should have:
- ✅ `cardobot_users` - User accounts
- ✅ `cardobot_cards` - Card data
- ✅ `cardobot_sessions` - Session tracking (optional)

### 5. Migrate Existing Data (If Any)

If you have existing users in the JSON file:
1. Visit: `https://herbiecreative.com/cardobot/database/migrate-from-json.php`
2. The script will migrate all users from JSON to database
3. Verify the migration was successful
4. Test login with migrated users

## 📊 Table Structure

### `cardobot_users`
- User authentication and profile data
- Supports both password and Google OAuth
- Tracks admin status

### `cardobot_cards`
- Stores all card data
- Links to users via `user_id` foreign key
- Stores card attributes (stats, colors, bio, etc.)
- Includes JSON column for flexible additional data

### `cardobot_sessions` (Optional)
- Tracks user sessions for analytics
- Can be used for session management if needed

## 🔒 Security Notes

- All tables use **InnoDB** engine (supports foreign keys)
- **utf8mb4** charset (supports emojis and full Unicode)
- Foreign keys use **CASCADE** delete (deleting user deletes their cards)
- Indexes added for performance on common queries
- Prepared statements prevent SQL injection

## 🧪 Testing

After setup, test the database connection:
1. Visit: `https://herbiecreative.com/cardobot/test-env.php`
2. Check that database connection test passes

## 📝 Next Steps

After database is set up:
1. Update `includes/auth.php` to use database instead of JSON
2. Test user registration and login
3. Test card creation and storage
4. Once verified, you can archive the JSON file (keep as backup)
