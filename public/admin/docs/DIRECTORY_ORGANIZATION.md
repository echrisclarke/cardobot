# Card-o-Bot Directory Organization

Complete guide to the directory structure, path references, and organization principles.

## Current Directory Structure

```
cardobot/
├── admin/                          # All admin functionality
│   ├── dashboard.php               # Main admin entry point
│   ├── users.php                   # User management
│   ├── database.php                # Database browser
│   ├── status.php                  # System status
│   ├── test-dashboard.php          # Main test dashboard (in admin root)
│   ├── check-users.php             # Quick user check utility
│   ├── tests/                      # All test files
│   │   ├── test-env.php           # Environment & API tests
│   │   ├── test-auth.php          # Authentication tests
│   │   ├── test-db.php            # Database tests
│   │   ├── test-google-oauth.php  # Google OAuth tests
│   │   ├── test-image.php         # Image generation test
│   │   ├── test-profile.php       # Profile page test
│   │   └── test-google-callback-debug.php
│   ├── docs/                       # All documentation
│   │   ├── ADMIN_SETUP.md
│   │   ├── TESTING_GUIDE.md
│   │   ├── GOOGLE_OAUTH_SETUP.md
│   │   ├── DATABASE_GUIDE.md
│   │   ├── IMPLEMENTATION_PLAN.md
│   │   └── ...
│   └── database/                   # Database scripts (in admin)
│       ├── setup.php
│       ├── migrate-from-json.php
│       ├── schema.sql
│       └── *.md
├── api/                            # API endpoints
│   ├── generate-image.php          # Image generation API
│   └── google-callback.php         # Google OAuth callback
├── assets/                         # Static assets
│   ├── css/
│   │   ├── base.css
│   │   └── variables.css
│   ├── img/                        # Images
│   └── js/                         # JavaScript files
├── includes/                       # Shared PHP includes
│   ├── auth.php                    # Authentication functions
│   ├── env.php                     # Environment loader
│   ├── google-auth.php             # Google OAuth functions
│   └── cards.php                   # Card-related functions
├── user-cards/                     # User card storage (created at runtime)
├── index.php                       # Main application entry
├── login.php                       # Login page
├── logout.php                      # Logout handler
├── profile.php                     # User profile page
└── link-account.php                # Account linking page
```

## Path Reference Guide

### From `admin/test-dashboard.php`:
- To includes: `__DIR__ . '/../includes/...'` (1 level up)
- To tests: `tests/test-*.php` (relative)
- To root pages: `../page.php` (1 level up)

### From `admin/tests/*.php`:
- To includes: `__DIR__ . '/../../includes/...'` (2 levels up)
- To API: `../../api/...` (2 levels up)
- To root pages: `../../page.php` (2 levels up)

### From `admin/*.php`:
- To includes: `__DIR__ . '/../includes/...'` (1 level up)
- To tests: `tests/test-*.php` (relative)
- To root pages: `../page.php` (1 level up)

### Asset Paths:
- Use: `get_asset_path()` function (handles domain-aware paths automatically)
- This function returns the correct base path based on current domain

## Access Control Summary

**Public Pages:**
- `login.php`
- `logout.php`
- `api/google-callback.php` (OAuth callback)

**Auth Required (Logged-in Users):**
- `index.php`
- `profile.php`
- `link-account.php`

**Admin Only:**
- All `/admin/*` pages
- All `/admin/tests/*` pages
- `admin/database/setup.php`
- `admin/database/migrate-from-json.php`

## File Organization Principles

1. **Admin functionality** → `/admin/`
2. **Tests** → `/admin/tests/` (test-dashboard.php is in admin root)
3. **Documentation** → `/admin/docs/`
4. **Shared code** → `/includes/`
5. **API endpoints** → `/api/`
6. **Static assets** → `/assets/`
7. **Database scripts** → `/admin/database/`

## Path Fixes Completed

All paths have been updated to match the current directory organization:

### Test Dashboard (`admin/test-dashboard.php`)
- ✅ Fixed `require_once` path: `__DIR__ . '/../includes/auth.php'`
- ✅ Updated all test endpoints to point to `tests/` subdirectory
- ✅ Updated manual test links to use `../` for root pages
- ✅ Updated documentation reference to `docs/TESTING_GUIDE.md`

### Admin Dashboard (`admin/dashboard.php`)
- ✅ Updated test dashboard link: `/admin/test-dashboard.php`
- ✅ Updated database setup link: `/admin/database/setup.php`

### Test Files in `admin/tests/`
All test files have been updated to use correct paths:
- ✅ All use `__DIR__ . '/../../includes/...'` (2 levels up)
- ✅ API paths updated to `../../api/`
- ✅ Profile paths updated to `../../profile.php`

### Admin Utility Files
- ✅ `check-users.php`: Uses `__DIR__ . '/../includes/...'` (1 level up)

## Verification Checklist

- [x] All test files are in `/admin/tests/`
- [x] Test dashboard is in `/admin/test-dashboard.php`
- [x] All admin utility files are in `/admin/`
- [x] All documentation is in `/admin/docs/`
- [x] Admin dashboard links work correctly
- [x] Test dashboard loads and runs all tests
- [x] All test files can find their includes
- [x] All asset paths (CSS, images) work correctly
- [x] No broken links or 404 errors
