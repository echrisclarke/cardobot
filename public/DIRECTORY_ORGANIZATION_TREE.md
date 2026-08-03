# Card-o-Bot Directory Organization Tree

## Final Structure

```
cardobot/
├── admin/                          # All admin functionality
│   ├── dashboard.php               # Main admin entry point
│   ├── users.php                   # User management
│   ├── database.php                # Database browser
│   ├── status.php                  # System status
│   ├── test-dashboard.php          # Main test dashboard (in admin root)
│   ├── check-users.php            # Quick user check utility
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
├── database/                       # Database scripts
│   ├── setup.php                   # Database setup
│   ├── migrate-from-json.php       # Migration script
│   ├── schema.sql                  # Database schema
│   └── *.md                        # Database documentation
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
├── link-account.php                # Account linking page
└── *.md                            # Root-level documentation
```

## Path Reference Guide

### From `/admin/tests/` to root includes:
- Use: `__DIR__ . '/../../includes/...'` (go up 2 levels)

### From `/admin/` to root includes:
- Use: `__DIR__ . '/../includes/...'` (go up 1 level)

### From `/admin/tests/` to root assets:
- Use: `get_asset_path()` function (handles domain-aware paths)

### From `/admin/tests/` to API:
- Use: `'../../api/...'` (relative path)

### From `/admin/tests/` to root pages:
- Use: `'../../page.php'` (relative path)

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
- `database/setup.php`
- `database/migrate-from-json.php`

## File Organization Principles

1. **Admin functionality** → `/admin/`
2. **Tests** → `/admin/tests/`
3. **Documentation** → `/admin/docs/` or root level
4. **Shared code** → `/includes/`
5. **API endpoints** → `/api/`
6. **Static assets** → `/assets/`
7. **Database scripts** → `/database/`
