# Path Verification Checklist

Use this checklist to verify all paths and URLs are correct after directory reorganization.

## ✅ Verification Steps

### 1. Test Dashboard (`admin/test-dashboard.php`)

**Check:**
- [ ] Page loads without errors
- [ ] CSS loads correctly (check browser console)
- [ ] All test endpoints resolve correctly:
  - `tests/test-env.php` → Should load from `admin/tests/test-env.php`
  - `tests/test-auth.php` → Should load from `admin/tests/test-auth.php`
  - `tests/test-db.php` → Should load from `admin/tests/test-db.php`
  - `tests/test-google-oauth.php` → Should load from `admin/tests/test-google-oauth.php`
  - `tests/test-image.php` → Should load from `admin/tests/test-image.php`
- [ ] Manual test links work:
  - `../login.php` → Should go to root `login.php`
  - `../index.php` → Should go to root `index.php`
  - `../logout.php` → Should go to root `logout.php`
- [ ] JavaScript fetch calls work (check browser console for 404s)

**How to Test:**
1. Visit `/admin/test-dashboard.php`
2. Open browser console (F12)
3. Check for any 404 errors
4. Verify all tests load and display results

### 2. Admin Dashboard (`admin/dashboard.php`)

**Check:**
- [ ] All links work:
  - `/admin/users.php` ✅
  - `/admin/database.php` ✅
  - `/admin/status.php` ✅
  - `/admin/test-dashboard.php` ✅
  - `/admin/check-users.php` ✅
  - `/admin/tests/test-image.php` ✅
  - `/admin/database/setup.php` ✅
- [ ] Navigation links work:
  - Back to App → `/index.php` ✅
  - Logout → `/logout.php` ✅

**How to Test:**
1. Visit `/admin/dashboard.php`
2. Click each link and verify it loads correctly
3. Check browser console for any errors

### 3. Test Files in `admin/tests/`

**Check each test file:**
- [ ] `test-env.php` - Loads without errors, returns JSON
- [ ] `test-auth.php` - Loads without errors, returns JSON
- [ ] `test-db.php` - Loads without errors, returns JSON
- [ ] `test-google-oauth.php` - Loads without errors, returns JSON
- [ ] `test-image.php` - Page loads, CSS loads, API call works
- [ ] `test-profile.php` - Loads without errors, link to profile.php works
- [ ] `test-google-callback-debug.php` - Loads without errors

**How to Test:**
1. Visit each test file directly (e.g., `/admin/tests/test-env.php`)
2. For JSON tests, verify valid JSON is returned
3. For HTML tests, verify page loads and displays correctly
4. Check browser console for errors

### 4. Test Image Generation (`admin/tests/test-image.php`)

**Check:**
- [ ] Page loads correctly
- [ ] CSS loads (check browser console)
- [ ] API call works: `fetch('../../api/generate-image.php')`
  - Should resolve to `/api/generate-image.php` from root
- [ ] Form submission works
- [ ] Image displays after generation

**How to Test:**
1. Visit `/admin/tests/test-image.php`
2. Fill in form and submit
3. Check browser Network tab to verify API call goes to correct URL
4. Verify image displays

### 5. Admin Pages Navigation

**Check each admin page:**
- [ ] `admin/users.php` - All navigation links work
- [ ] `admin/database.php` - All navigation links work
- [ ] `admin/status.php` - All navigation links work
- [ ] `admin/check-users.php` - Displays correctly

**Navigation Links to Check:**
- Dashboard link → `/admin/dashboard.php`
- App link → `/index.php`
- Logout link → `/logout.php`

### 6. Database Scripts

**Check:**
- [ ] `admin/database/setup.php` - Loads and works correctly
- [ ] `admin/database/migrate-from-json.php` - Loads and works correctly
- [ ] All require paths are correct

### 7. JavaScript Fetch Calls

**Check:**
- [ ] Test dashboard fetch calls use relative paths correctly
- [ ] Test image fetch uses `../../api/generate-image.php` correctly
- [ ] No 404 errors in browser console

### 8. CSS and Asset Paths

**Check:**
- [ ] All pages load CSS correctly (no 404s)
- [ ] `get_asset_path()` function works correctly
- [ ] Images load correctly (if any are used)

## Common Issues to Watch For

1. **404 Errors:**
   - Check browser console for failed requests
   - Verify paths are relative to current file location

2. **JavaScript Fetch Errors:**
   - Verify relative paths are correct from the page's location
   - Check Network tab in browser DevTools

3. **CSS Not Loading:**
   - Verify `get_asset_path()` returns correct value
   - Check that CSS file exists at expected location

4. **PHP Include Errors:**
   - Verify `__DIR__` paths are correct
   - Check that included files exist

## Quick Test Script

Visit `/admin/verify-paths.php` to automatically check many paths.

## Expected Path Structure

```
From admin/test-dashboard.php:
- tests/test-*.php → admin/tests/test-*.php ✅
- ../login.php → login.php ✅

From admin/tests/test-*.php:
- ../../includes/* → includes/* ✅
- ../../api/* → api/* ✅
- ../../profile.php → profile.php ✅

From admin/*.php:
- ../includes/* → includes/* ✅
- tests/test-*.php → admin/tests/test-*.php ✅
```

## Final Verification

After checking all items above:
- [ ] No 404 errors in browser console
- [ ] All links work correctly
- [ ] All tests load and run
- [ ] All admin pages accessible
- [ ] Navigation works on all pages
