# Card-o-Bot v2.0: Modern Implementation Plan

## 🎯 Project Goals

Convert Card-o-Bot to a modern, industry-standard application that:
- Uses **only OpenAI models** (DALL-E for images, GPT for text)
- Provides **user authentication** (login/logout)
- Enables **card management** (create, view, edit, delete)
- Maintains **same visual assets** and core functionality
- Removes all **Cardy** and **purchase** functionality
- Follows **modern web standards** and best practices

---

## 📁 Directory Structure

**Simplified, industry-standard structure:**

```
cardobot/
├── index.php                    # Main application
├── login.php                    # Login page
├── logout.php                   # Logout handler
├── drawing.php                  # Drawing canvas (p5.js)
│
├── api/                         # API endpoints (or use api-*.php in root)
│   ├── generate-image.php
│   ├── generate-text.php
│   ├── save-card.php
│   ├── load-card.php
│   ├── delete-card.php
│   └── list-cards.php
│
├── includes/                    # Shared utilities
│   ├── env.php                  # Environment loader
│   └── auth.php                 # Authentication
│
├── assets/                      # Static files
│   ├── css/
│   │   └── main.css
│   ├── js/
│   │   ├── app.js
│   │   └── drawing.js
│   └── images/                  # Copy from cardobot/images/
│
└── user-cards/                  # Storage (outside web root if possible)
    └── {username}/
        ├── {card-id}.json
        └── {card-id}.png
```

**Note:** This follows the pattern of `/openai` and `/treewatering` - simple, flat structure with minimal nesting.

---

## 🔑 Environment Configuration (.env)

### **File Location**

The `.env` file is stored **outside** `public_html` for security:

**Production Server Path:**
```
/home/username/private/.env
```

**PHP Access Pattern (from `/openai`):**
```php
$envPath = dirname($_SERVER['DOCUMENT_ROOT']) . '/private/.env';
```

This goes **one level up** from `public_html` to access the `private/` directory.

### **.env File Structure**

The `.env` file contains all credentials and configuration:

```env
# ============================================
# OpenAI API Configuration
# ============================================
OPENAI_API_KEY=sk-proj-your-actual-api-key-here

# Dashboard Security (for /openai dashboard)
OPENAI_DASHBOARD_PASSWORD=your-password-here

# Dashboard Caching (optional, default: 600 seconds)
OPENAI_MODEL_LIST_CACHE_SECONDS=600

# ============================================
# Database Configuration (if needed)
# ============================================
DB_HOST=localhost
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASSWORD=your_database_password
DB_CHARSET=utf8mb4

# ============================================
# Card-o-Bot Model Configuration
# ============================================
# Image generation model
# Recommended: chatgpt-image-latest (auto-updates to latest model)
# Alternatives: gpt-image-1.5, gpt-image-1, dall-e-3, dall-e-2
OPENAI_IMAGE_MODEL=chatgpt-image-latest

# Text generation model (for card bios)
# Latest options: gpt-5.2-pro, gpt-5.2, gpt-5.1, gpt-5-mini, gpt-4.1, gpt-4o, gpt-4o-mini
# Recommended: gpt-5-mini (cost-effective) or gpt-5.2 (best quality)
OPENAI_TEXT_MODEL=gpt-5-mini

# Optional: Maximum tokens for text generation (default: 150)
OPENAI_MAX_TOKENS=150

# Optional: Temperature for creativity (0.0-2.0, default: 0.8)
OPENAI_TEMPERATURE=0.8

# ============================================
# Optional: AI Features
# ============================================
USE_AI_CAPTIONS=true
CACHE_CAPTIONS=true
CACHE_EXPIRY_DAYS=365
FALLBACK_TO_FILENAME=true
```

### **How to Access from PHP**

**Pattern (from `/openai/env.php`):**
```php
// Load .env file
function load_env(): array {
  $path = dirname($_SERVER['DOCUMENT_ROOT']) . '/private/.env';
  
  if (!file_exists($path)) {
    // Handle error
    return [];
  }
  
  return parse_ini_file($path, false, INI_SCANNER_RAW);
}

// Get OpenAI API key
function get_openai_key(): string {
  $env = load_env();
  return $env['OPENAI_API_KEY'] ?? '';
}

// Get database credentials
function get_db_credentials(): array {
  $env = load_env();
  return [
    'host' => $env['DB_HOST'] ?? 'localhost',
    'database' => $env['DB_NAME'] ?? '',
    'username' => $env['DB_USER'] ?? '',
    'password' => $env['DB_PASSWORD'] ?? '',
    'charset' => $env['DB_CHARSET'] ?? 'utf8mb4'
  ];
}
```

### **Server Access**

**Via FTP/SSH:**
- Navigate to: `/home/username/private/.env`
- Edit using: `nano .env` or `vim .env` (via SSH)
- Or download/edit/upload via FTP client

**Security:**
- ✅ File is **outside** `public_html` - cannot be accessed via web browser
- ✅ Should be in `.gitignore` - never commit to version control
- ✅ Use `parse_ini_file()` to read - handles comments and formatting
- ✅ No spaces around `=` sign in `.env` file

**For Card-o-Bot:**
- Uses same `.env` file as `/openai` directory
- Requires: `OPENAI_API_KEY` (minimum)
- Optional: Database credentials if storing cards in database
- All access via `includes/env.php` (production path only)

---

## 🔧 Technical Architecture

### **Backend (PHP)**
- **Environment:** Uses `/openai/env.php` pattern for `.env` loading
  - Path: `dirname($_SERVER['DOCUMENT_ROOT']) . '/private/.env'`
  - Production-only (no local development paths)
  - Same `.env` file as `/openai` directory
- **Authentication:** Session-based login/logout system
- **API Endpoints:** RESTful JSON API for all operations
- **Storage:** File-based (JSON + images) per user directory
- **Security:** Input validation, path traversal protection, prepared statements
- **File Types:** All pages use `.php` extension (no `.html` files)

### **Frontend (Modern JavaScript)**
- **Framework:** Vanilla JavaScript (ES6+ modules)
- **Drawing:** p5.js (kept from original)
- **UI:** Modern CSS Grid/Flexbox, responsive design
- **State Management:** Simple state object pattern
- **API Communication:** Fetch API with async/await

### **OpenAI Integration**

**Image Generation:**
- **Recommended Models (in order of preference):**
  1. `chatgpt-image-latest` - **Recommended** - Automatically uses latest image model (always up-to-date)
  2. `gpt-image-1.5` - Latest specific version (Dec 2025)
  3. `gpt-image-1` - Previous generation
  4. `dall-e-3` - Stable, reliable fallback
  5. `dall-e-2` - Legacy fallback
- **Endpoint:** `/v1/images/generations`
- **Parameters:** `model`, `prompt`, `size` (1024x1024, 1792x1024, 1024x1792), `quality` (low/medium/high/auto)
- **Note:** Use `chatgpt-image-latest` for automatic updates, or specific version for consistency

**Text Generation (Card Bios):**
- **Recommended Models (in order of preference - based on live model list):**
  1. `gpt-5.2-pro` - Latest premium model (Dec 2025) - Best quality
  2. `gpt-5.2` - Latest standard model (Dec 2025) - Great quality
  3. `gpt-5.1` - Previous generation (Nov 2025) - Excellent quality
  4. `gpt-5-mini` - Cost-effective, fast (Aug 2025) - **Recommended default**
  5. `gpt-4.1` - Stable option (Apr 2025)
  6. `gpt-4o` - Reliable fallback
  7. `gpt-4o-mini` - Budget option
  8. `o3-pro` or `o3-mini` - For complex reasoning tasks (if needed)
- **Endpoint:** `/v1/chat/completions` (NOT `/v1/responses`)
- **Parameters:** `model`, `messages` (array with role/content), `max_tokens`, `temperature`
- **Error Handling:** Graceful fallbacks and user feedback
- **Note:** Use `/openai` dashboard to verify model availability before deployment

**Note:** The `/openai` dashboard fetches live model list from `/v1/models` - use it to verify available models.

**API Implementation Pattern (from `/openai`):**
```php
// Image Generation
$ch = curl_init('https://api.openai.com/v1/images/generations');
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    'Authorization: Bearer ' . $key,
    'Content-Type: application/json',
  ],
  CURLOPT_POSTFIELDS => json_encode([
    'model' => 'chatgpt-image-latest', // Auto-updates to latest image model
    'prompt' => $prompt,
    'size' => '1024x1024',
    'quality' => 'high'
  ]),
]);

// Text Generation (Chat Completions)
$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    'Authorization: Bearer ' . $key,
    'Content-Type: application/json',
  ],
  CURLOPT_POSTFIELDS => json_encode([
    'model' => 'gpt-5-mini', // or 'gpt-5.2' for better quality
    'messages' => [
      ['role' => 'system', 'content' => 'You are a creative card bio writer.'],
      ['role' => 'user', 'content' => $prompt]
    ],
    'max_tokens' => 150,
    'temperature' => 0.8
  ]),
]);
```

---

## 📋 Feature Breakdown

### **1. User Authentication**
- **Login:** Username + password (or PIN system)
- **Session Management:** PHP sessions with secure cookies
- **Logout:** Clear session and redirect
- **Protected Routes:** Middleware to check authentication

### **2. Card Creation**
- **Step 1:** User selects card type (BOT/CRITTER)
- **Step 2:** System generates:
  - AI image via DALL-E (based on prompts)
  - AI bio text via GPT
- **Step 3:** User customizes:
  - Draws artwork on canvas
  - Edits text fields (nickname, power, ability, bio)
  - Adjusts colors (HSL sliders)
  - Sets card stats
- **Step 4:** Save card to user's collection

### **3. Card Management**
- **View Deck:** Gallery of all user's cards
- **Edit Card:** Load existing card, modify, save
- **Delete Card:** Remove card from collection
- **Card Details:** View full card with all attributes

### **4. Drawing System**
- **Technology:** p5.js canvas embedded in PHP page (`drawing.php`)
- **Features:** Undo/redo, brush controls, color picker
- **Enhancements:** Touch support, performance optimizations
- **Export:** PNG/JPEG export functionality

---

## 🔄 Migration Strategy

### **Phase 1: Foundation**
1. ✅ Create directory structure
2. ✅ Create basic `index.php` skeleton
3. ⏭️ Set up `env.php` (based on `/openai` pattern, production-only)
4. ⏭️ Implement authentication system

### **Phase 2: Core Features**
1. Implement OpenAI API endpoints (image + text generation)
2. Create drawing system using p5.js (modernized, PHP-based)
3. Build card creation workflow
4. Implement save/load functionality

### **Phase 3: UI/UX**
1. Design modern, clean interface
2. Copy necessary images from `cardobot/images/`
3. Create responsive CSS
4. Build card gallery view

### **Phase 4: Polish**
1. Add edit card functionality
2. Implement delete functionality
3. Error handling and user feedback
4. Performance optimization
5. Testing and bug fixes

---

## 🎨 Design Principles

### **Modern UI Standards**
- **Clean Layout:** Minimal, focused interface
- **Responsive:** Mobile-first design
- **Accessibility:** ARIA labels, keyboard navigation
- **Performance:** Lazy loading, optimized assets
- **Feedback:** Loading states, error messages, success confirmations

### **Color Scheme**
- Keep original teal/pink accents if desired
- Or adopt modern neutral palette with accent colors
- High contrast for readability

### **Typography**
- System fonts (system-ui, sans-serif) for performance
- Or modern web font (Inter, Roboto, etc.)

---

## 🔐 Security Considerations

1. **Authentication:** Secure session management
2. **Input Validation:** All user inputs sanitized
3. **Path Traversal:** Prevent directory traversal attacks
4. **File Upload:** Validate file types and sizes
5. **CSRF Protection:** Token-based form protection
6. **API Keys:** Never exposed to frontend
7. **Error Messages:** Don't leak sensitive information

---

## 📊 Data Structure

### **Card JSON Format**
```json
{
  "id": "card-1234567890",
  "username": "user123",
  "type": "BOT",
  "created": "2025-01-15T10:30:00Z",
  "modified": "2025-01-15T10:30:00Z",
  "image_url": "user-cards/user123/card-1234567890.png",
  "drawing_data": "base64_encoded_canvas_data",
  "attributes": {
    "nickname": "RoboBot",
    "bio": "AI-generated bio text...",
    "power": "Laser Vision",
    "ability": "Shoots lasers from eyes",
    "stats": {
      "HP": 100,
      "ATT": 75,
      "STR": 80,
      "LOS": 60,
      "CON": 90,
      "NPO": 50
    },
    "colors": {
      "hue": 195,
      "saturation": 65,
      "lightness": 40
    }
  }
}
```

---

## 🚀 Next Steps

1. ✅ Create directory structure
2. ✅ Create `index.php` skeleton
3. ⏭️ Set up environment loader
4. ⏭️ Implement authentication
5. ⏭️ Create OpenAI API endpoints
6. ⏭️ Build drawing system
7. ⏭️ Implement card creation workflow
8. ⏭️ Build UI components
9. ⏭️ Test and refine

---

## 📝 Notes

- **File Standards:** All files use `.php` extension (no `.html` files)
- **Simple Structure:** Flat, minimal nesting - follows `/openai` and `/treewatering` patterns
- **Industry Standard:** Common PHP app structure: root files, `includes/`, `assets/`, `api/`
- **OpenAI Only:** DALL-E for images, GPT for text generation (no RunwayML)
- **No Legacy Code:** Don't mimic old `/cardobot` structure - build fresh and simple
- **Images:** Copy necessary images from `cardobot/images/` to `cardobot/assets/images/`
- **Drawing System:** Single `drawing.php` file with embedded p5.js (not a directory)
- **No Purchase Features:** Removed all buying/selling functionality
- **Focus:** Simplicity, security, and user experience
