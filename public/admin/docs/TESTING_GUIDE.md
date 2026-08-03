# Testing Guide for Card-o-Bot

This guide will help you test all the features of Card-o-Bot.

## 🧪 Quick Test Pages

### 1. Environment Test
**URL:** `https://yourdomain.com/cardobot/test-env.php`

Tests:
- ✅ .env file loading
- ✅ OpenAI API key retrieval
- ✅ Model configuration
- ✅ API connectivity

**Expected Result:** All tests should pass (green/✅)

---

### 2. Authentication System Test
**URL:** `https://yourdomain.com/cardobot/test-auth.php`

Tests:
- ✅ Session functionality
- ✅ Login status
- ✅ User storage file
- ✅ User directory creation
- ✅ Google OAuth configuration (optional)
- ✅ Username validation

**Expected Result:** All tests should pass (green/✅)

---

### 3. Image Generation Test
**URL:** `https://yourdomain.com/cardobot/test-image.php`

Tests:
- ✅ Image generation API
- ✅ Base64 image handling
- ✅ Loading bar functionality

**Expected Result:** Should generate and display an image

---

## 🔐 Testing Authentication

### Test Regular Login/Registration

1. **Visit Login Page:**
   ```
   https://yourdomain.com/cardobot/login.php
   ```

2. **Test Registration:**
   - Click "Create one" link
   - Enter username: `testuser` (3-50 chars, alphanumeric, underscore, hyphen)
   - Enter password: `test1234` (at least 4 characters)
   - Click "Create Account"
   - **Expected:** Success message, redirected to main app

3. **Test Login:**
   - Logout first (click "Logout" in header)
   - Enter username: `testuser`
   - Enter password: `test1234`
   - Click "Sign In"
   - **Expected:** Redirected to main app, see welcome message

4. **Test Logout:**
   - Click "Logout" button in header
   - **Expected:** Redirected to login page

5. **Test Protected Route:**
   - While logged out, try to visit: `https://yourdomain.com/cardobot/index.php`
   - **Expected:** Redirected to login page

### Test Google OAuth Login

**Prerequisites:** Google OAuth must be configured (see `GOOGLE_OAUTH_SETUP.md`)

1. **Check Configuration:**
   - Visit: `https://yourdomain.com/cardobot/test-auth.php`
   - Look for `google_oauth` test
   - Should show `"configured": true` if set up correctly

2. **Test Google Login:**
   - Visit: `https://yourdomain.com/cardobot/login.php`
   - Click "Sign in with Google" button
   - **Expected:** Redirected to Google login page
   - Sign in with Google account
   - **Expected:** Redirected back, logged in, account auto-created

3. **Test Returning Google User:**
   - Logout
   - Login with Google again (same account)
   - **Expected:** Logged in immediately (no new account created)

---

## 🎨 Testing Image Generation

1. **Visit Test Page:**
   ```
   https://yourdomain.com/cardobot/test-image.php
   ```

2. **Test Image Generation:**
   - Enter a prompt: "A friendly robot character for a trading card, colorful, detailed"
   - Select model: "chatgpt-image-latest" (or default)
   - Select size: "1024x1024"
   - Select quality: "High"
   - Click "Generate Image"
   - **Expected:** 
     - Loading bar appears
     - Progress updates
     - Image displays after generation
     - Success message with image

3. **Test Different Formats:**
   - Try different models (if available)
   - Try different sizes
   - Try different quality settings

---

## 📋 Manual Testing Checklist

### Authentication
- [ ] Can create new account with username/password
- [ ] Can login with username/password
- [ ] Can logout
- [ ] Protected pages redirect to login when not authenticated
- [ ] Session persists across page loads
- [ ] Google login button appears (if configured)
- [ ] Google login works (if configured)
- [ ] Google users auto-created
- [ ] Returning Google users login successfully

### Image Generation
- [ ] Image generation API works
- [ ] Base64 images display correctly
- [ ] Loading bar shows progress
- [ ] Error messages display on failure
- [ ] Different models work (if available)
- [ ] Different sizes work
- [ ] Different quality settings work

### User Management
- [ ] User directories created automatically
- [ ] User data saved correctly
- [ ] Username validation works
- [ ] Password validation works

---

## 🐛 Troubleshooting Tests

### If `test-env.php` fails:
- Check `.env` file exists at `/home/username/private/.env`
- Check `OPENAI_API_KEY` is set in `.env`
- Check file permissions on `.env` file

### If `test-auth.php` fails:
- Check PHP sessions are enabled
- Check `/private/cardobot_users.json` is writable
- Check `user-cards/` directory is writable

### If `test-image.php` fails:
- Check OpenAI API key is valid
- Check API key has image generation permissions
- Check network connectivity
- Check browser console for JavaScript errors

### If Google login doesn't work:
- Check `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in `.env`
- Check redirect URI matches Google Console exactly
- Check OAuth consent screen is configured
- Check app is published or test users are added

---

## 🔍 Debug Mode

To see more detailed error information, check:

1. **PHP Error Logs:**
   - Usually at: `/home/username/logs/error_log`
   - Or check your hosting control panel

2. **Browser Console:**
   - Press F12 → Console tab
   - Look for JavaScript errors

3. **Network Tab:**
   - Press F12 → Network tab
   - Check API request/response details

4. **Test Pages:**
   - All test pages return JSON with detailed information
   - Use browser's "View Source" or JSON formatter to read

---

## ✅ Success Criteria

Your system is ready when:
- ✅ All test pages show green/pass status
- ✅ Can create and login with username/password
- ✅ Can logout successfully
- ✅ Protected routes redirect when not logged in
- ✅ Image generation works and displays images
- ✅ Google login works (if configured)

---

## 📝 Next Steps After Testing

Once all tests pass:
1. ✅ Authentication system is working
2. ⏭️ Next: Build text generation API
3. ⏭️ Then: Build main card creation UI
4. ⏭️ Then: Build drawing system
5. ⏭️ Then: Build card save/load functionality
