# Admin User Setup

## 🔑 Admin Credentials

The admin user is configured in the `.env` file (same location as other environment variables).

## 📁 File Location

The `.env` file is located in the `private/` folder (outside `public_html`):

**Path (Production):**
```
/home/username/private/.env
```

## 📝 Required Environment Variables

Add these to your `.env` file:

```env
# Admin Credentials (for all apps)
ADMIN_USERNAME=herbie
ADMIN_PASSWORD=Clarabear#2030ka
```

## 🔒 How It Works

1. **Admin Login Priority**: Admin credentials are checked **first** before regular user accounts
2. **Global Admin**: These credentials work across all apps (cardobot, openai, etc.)
3. **Session-Based**: Once logged in, admin status is stored in the session
4. **No Database**: Admin credentials are stored in `.env` file only (not in user database)

## 🎯 Usage

### Login as Admin

1. Go to `/cardobot/login.php`
2. Enter username: `herbie`
3. Enter password: `Clarabear#2030ka`
4. You'll be logged in as admin with special privileges

### Check Admin Status in Code

```php
require_once __DIR__ . '/includes/auth.php';

if (is_admin()) {
    // Admin-only code here
    echo "You are an admin!";
}
```

## 🔐 Security Notes

- ✅ Admin password is stored in `.env` file (outside public_html)
- ✅ Admin credentials are checked before regular user authentication
- ✅ Admin status is tracked in session (`is_admin` flag)
- ✅ Same credentials work across all apps using this system
- ⚠️ Keep `.env` file secure and never commit it to version control

## 📋 Environment Variables Reference

```env
# Admin Credentials (Global)
ADMIN_USERNAME=herbie
ADMIN_PASSWORD=Clarabear#2030ka

# OpenAI API (for cardobot)
OPENAI_API_KEY=sk-proj-your-actual-api-key-here
OPENAI_IMAGE_MODEL=chatgpt-image-latest
OPENAI_TEXT_MODEL=gpt-5-mini
OPENAI_MAX_TOKENS=150
OPENAI_TEMPERATURE=0.8

# OpenAI Dashboard (for /openai)
OPENAI_DASHBOARD_PASSWORD=Clarabear#2030ka
```

## ✅ Testing

To verify admin login works:

1. Ensure `.env` file has `ADMIN_USERNAME` and `ADMIN_PASSWORD` set
2. Visit `/cardobot/login.php`
3. Login with admin credentials
4. Check that you're logged in and have admin privileges
