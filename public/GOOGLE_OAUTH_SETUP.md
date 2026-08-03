# Google OAuth Setup Guide

This guide will help you set up Google login for Card-o-Bot.

## 📋 Prerequisites

- A Google account
- Access to Google Cloud Console
- Access to your server's `.env` file

## 🔧 Step-by-Step Setup

### 1. Create a Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Click "Select a project" → "New Project"
3. Enter project name: "Card-o-Bot" (or any name you prefer)
4. Click "Create"

### 2. Configure OAuth Consent Screen

1. In the left sidebar, go to **APIs & Services** → **OAuth consent screen**
2. Choose **External** (unless you have a Google Workspace)
3. Click **Create**
4. Fill in the required information:
   - **App name**: Card-o-Bot
   - **User support email**: Your email
   - **Developer contact information**: Your email
5. Click **Save and Continue**

**Note:** If you don't see a "Scopes" page after step 5, that's okay! The basic scopes (`email`, `profile`, `openid`) are often added automatically. You can:
   - Continue through the setup (click **Save and Continue** on any remaining pages)
   - Or manually add scopes later by going back to **OAuth consent screen** → **Scopes** tab

6. **If Scopes page appears:** Click **Add or Remove Scopes**
   - Select: `email`, `profile`, `openid`
   - Click **Update** → **Save and Continue**
7. On **Test users** page (if shown), click **Save and Continue**
8. On **Summary** page, click **Back to Dashboard**

**To manually add scopes later (if needed):**
1. Go to **APIs & Services** → **OAuth consent screen**
2. Click the **Scopes** tab
3. Click **Add or Remove Scopes**
4. Add: `email`, `profile`, `openid`
5. Click **Update** → **Save**

### 3. Create OAuth 2.0 Credentials

1. Go to **APIs & Services** → **Credentials**
2. Click **+ CREATE CREDENTIALS** → **OAuth client ID**
3. Choose **Web application**
4. Fill in:
   - **Name**: Card-o-Bot Web Client
   
   - **Authorized JavaScript origins** (optional, but recommended):
     ```
     https://herbiecreative.com
     https://cardobot.com
     https://www.cardobot.com
     ```
     **Note:** These are the base domains (no paths). This is optional but recommended for security.
   
   - **Authorized redirect URIs** (required): Add ALL of these:
     ```
     https://herbiecreative.com/cardobot/api/google-callback.php
     https://cardobot.com/api/google-callback.php
     https://www.cardobot.com/api/google-callback.php
     ```
     **Note:** 
     - `cardobot.com` already points to the `/cardobot` directory, so it uses `/api/google-callback.php` (no `/cardobot` prefix needed)
     - Both `cardobot.com` and `www.cardobot.com` need to be added separately
     - All URLs must use `https://` (not `http://`)
     
     (Add each URI on a separate line, or click **+ ADD URI** for each one)
5. Click **Create**
6. **IMPORTANT**: Copy the **Client ID** and **Client Secret** - you'll need these!
   
   **Note:** It may take 5 minutes to a few hours for settings to take effect after saving.

### 4. Add Credentials to .env File

1. Open your `.env` file (located at `/home4/herbiecr/private/.env` - outside `public_html` for security)
2. Add these lines:

```env
# Google OAuth Configuration
GOOGLE_CLIENT_ID=your-client-id-here.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret-here
```

3. Save the file

### 5. Test the Setup

1. Visit: `https://herbiecreative.com/cardobot/login.php` or `https://cardobot.com/login.php`
2. You should see a "Sign in with Google" button
3. Click it and test the login flow

## ✅ Verification

If everything is set up correctly:
- ✅ "Sign in with Google" button appears on login page
- ✅ Clicking it redirects to Google login
- ✅ After Google login, you're redirected back and logged in
- ✅ New users are automatically created
- ✅ Returning users are logged in automatically

## 🔒 Security Notes

- **Never commit** your `.env` file to version control
- Keep your **Client Secret** secure
- The redirect URI must **exactly match** what you configured in Google Cloud Console
- Use **HTTPS** in production (required by Google OAuth)

## 🐛 Troubleshooting

### Button doesn't appear
- Check that `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` are in `.env`
- Verify the values are correct (no extra spaces)

### "Redirect URI mismatch" error
- Ensure the redirect URI in Google Console **exactly matches** one of:
  - `https://herbiecreative.com/cardobot/api/google-callback.php` (includes `/cardobot` path)
  - `https://cardobot.com/api/google-callback.php` (no `/cardobot` path - domain already points there)
  - `https://www.cardobot.com/api/google-callback.php` (www variant - also no `/cardobot` path)
- Check for trailing slashes, http vs https, etc.
- Make sure **ALL THREE** redirect URIs are added to Authorized redirect URIs in Google Console
- The system automatically detects which domain is being used and generates the correct redirect URI

### "Access blocked" error
- If your app is in "Testing" mode, add test users in OAuth consent screen
- Or publish your app (if ready for production)

### Users not being created
- Check database connection (run `test-db.php` to verify)
- Check that `cardobot_users` table exists and is accessible
- Check PHP error logs for details
- Verify database credentials in `.env` file

## 📝 Notes

- Google login is **optional** - regular username/password login still works
- Users can choose either login method
- Google users don't need passwords
- Username is auto-generated from email or Google ID

---

## 🛡️ ModSecurity on the `cardobot.com` addon domain

**Symptom:** After Google redirects back to `https://cardobot.com/api/google-callback.php?...&code=...&scope=...`, Bluehost returns:

> Not Acceptable! An appropriate representation of the requested resource could not be found on this server. This error was generated by Mod_Security.

**Cause:** Bluehost's ModSecurity rules **340162 / 340163** ("Remote File Injection Attack") flag the OAuth `scope=...https%3A%2F%2Fwww.googleapis.com%2F...` query parameter as a remote-URL injection. The `herbiecreative.com/cardobot/...` path was whitelisted at the host level in the past; the `cardobot.com` addon-domain was not.

**Fix shipped in the repo:** [`api/.htaccess`](api/.htaccess) calls `SecRuleRemoveById 340162 340163` scoped to `google-callback.php`, extending the whitelist to the `cardobot.com` path.

**If that file alone doesn't fix it** (Bluehost can disable `SecRuleRemoveById` overrides in `.htaccess`), open a support ticket asking them to extend the same mod_security whitelist they already applied to `herbiecreative.com/cardobot/api/google-callback.php` to `cardobot.com/api/google-callback.php` and `www.cardobot.com/api/google-callback.php`.

## OAuth consent — Testing vs. Published

While the OAuth consent screen status is **Testing**, only addresses listed under **Test users** can complete sign-in. Google will otherwise show its own "Access blocked: Card-o-Bot has not completed the Google verification process" page **before** redirecting back.

- **To add test users**: Google Cloud Console → APIs & Services → OAuth consent screen → Test users → ADD USERS.
- **To publish**: OAuth consent screen → PUBLISH APP. For the `openid email profile` scopes Card-o-Bot uses, no formal verification is required.

> **Heads-up on symptom confusion:** "Not Acceptable!" is a **mod_security** block from Bluehost **after** Google redirects back successfully. An "Access blocked" page with Google branding **before** the redirect is a **Testing / unverified-user** issue. Different problems, different fixes.
