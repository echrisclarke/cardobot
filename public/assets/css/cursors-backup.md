# Retro Cursor Definitions - Backup Reference

This file contains backup definitions of all retro cursor SVGs used in the Card-o-Bot console interface.

## Cursor Types

### 1. Terminal/Default Cursor
**CSS Variable:** `--cursor-retro-terminal`  
**Fallback:** `crosshair`  
**Description:** Vertical line with horizontal base - used for general content areas

**SVG Code:**
```svg
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
  <rect x="0" y="0" width="2" height="20" fill="#5ED2F0"/>
  <rect x="2" y="18" width="6" height="2" fill="#5ED2F0"/>
</svg>
```

**Data URL:**
```
data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><rect x="0" y="0" width="2" height="20" fill="%235ED2F0"/><rect x="2" y="18" width="6" height="2" fill="%235ED2F0"/></svg>
```

**CSS Usage:**
```css
cursor: var(--cursor-retro-terminal);
```

---

### 2. Link Cursor (Unified with Button)
**CSS Variable:** `--cursor-retro-link`  
**Fallback:** `pointer`  
**Description:** Simple hand with pointing finger - used for links and buttons (unified cursor)

**SVG Code:**
```svg
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
  <rect x="2" y="10" width="2" height="8" fill="#5ED2F0"/>
  <rect x="4" y="12" width="2" height="6" fill="#5ED2F0"/>
  <rect x="6" y="14" width="2" height="4" fill="#5ED2F0"/>
  <rect x="8" y="16" width="4" height="2" fill="#5ED2F0"/>
  <rect x="10" y="12" width="2" height="4" fill="#5ED2F0"/>
  <rect x="12" y="10" width="2" height="2" fill="#5ED2F0"/>
  <rect x="14" y="4" width="2" height="8" fill="#5ED2F0"/>
  <rect x="16" y="2" width="2" height="2" fill="#5ED2F0"/>
  <rect x="16" y="6" width="2" height="2" fill="#5ED2F0"/>
</svg>
```

**Data URL:**
```
data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><rect x="2" y="10" width="2" height="8" fill="%235ED2F0"/><rect x="4" y="12" width="2" height="6" fill="%235ED2F0"/><rect x="6" y="14" width="2" height="4" fill="%235ED2F0"/><rect x="8" y="16" width="4" height="2" fill="%235ED2F0"/><rect x="10" y="12" width="2" height="4" fill="%235ED2F0"/><rect x="12" y="10" width="2" height="2" fill="%235ED2F0"/><rect x="14" y="4" width="2" height="8" fill="%235ED2F0"/><rect x="16" y="2" width="2" height="2" fill="%235ED2F0"/><rect x="16" y="6" width="2" height="2" fill="%235ED2F0"/></svg>
```

**CSS Usage:**
```css
cursor: var(--cursor-retro-link);
```

---

### 3. Button Cursor (Unified with Link)
**CSS Variable:** `--cursor-retro-button`  
**Fallback:** `pointer`  
**Description:** Same as link cursor - unified hand with pointing finger for buttons and interactive elements

**Note:** This cursor now uses `var(--cursor-retro-link)` to ensure consistency between links and buttons.

**CSS Usage:**
```css
cursor: var(--cursor-retro-button);
```

---

### 3. Text Cursor
**CSS Variable:** `--cursor-retro-text`  
**Fallback:** `text`  
**Description:** Simple vertical line - used for text input fields

**SVG Code:**
```svg
<svg xmlns="http://www.w3.org/2000/svg" width="16" height="20" viewBox="0 0 16 20">
  <rect x="0" y="0" width="2" height="20" fill="#5ED2F0"/>
</svg>
```

**Data URL:**
```
data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="20" viewBox="0 0 16 20"><rect x="0" y="0" width="2" height="20" fill="%235ED2F0"/></svg>
```

**CSS Usage:**
```css
cursor: var(--cursor-retro-text);
```

---

### 5. Select Cursor
**CSS Variable:** `--cursor-retro-select`  
**Fallback:** `crosshair`  
**Description:** Crosshair with center dot - used for select elements

**SVG Code:**
```svg
<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">
  <rect x="9" y="0" width="2" height="20" fill="#5ED2F0"/>
  <rect x="0" y="9" width="20" height="2" fill="#5ED2F0"/>
  <circle cx="9" cy="9" r="1.5" fill="#5ED2F0"/>
</svg>
```

**Data URL:**
```
data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><rect x="9" y="0" width="2" height="20" fill="%235ED2F0"/><rect x="0" y="9" width="20" height="2" fill="%235ED2F0"/><circle cx="9" cy="9" r="1.5" fill="%235ED2F0"/></svg>
```

**CSS Usage:**
```css
cursor: var(--cursor-retro-select);
```

---

### 6. Default Cursor
**CSS Variable:** `--cursor-retro-default`  
**Fallback:** `default`  
**Description:** Terminal style with default fallback - used for non-interactive elements

**SVG Code:**
```svg
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
  <rect x="0" y="0" width="2" height="20" fill="#5ED2F0"/>
  <rect x="2" y="18" width="6" height="2" fill="#5ED2F0"/>
</svg>
```

**Data URL:**
```
data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><rect x="0" y="0" width="2" height="20" fill="%235ED2F0"/><rect x="2" y="18" width="6" height="2" fill="%235ED2F0"/></svg>
```

**CSS Usage:**
```css
cursor: var(--cursor-retro-default);
```

---

## Color Reference

All cursors use the color `#5ED2F0` which corresponds to:
- **CSS Variable:** `--color-secondary-light`
- **RGB:** `rgb(94, 210, 240)`
- **URL Encoded:** `%235ED2F0` (for use in data URLs)

---

## Notes

- All cursors are embedded as SVG data URLs, so they don't depend on external assets
- The color `#5ED2F0` is URL-encoded as `%235ED2F0` in data URLs
- Fallback cursors are provided for browser compatibility
- Cursor definitions are centralized in `variables.css` as CSS custom properties
- This backup file serves as documentation and reference

---

## Last Updated

Created: 2024
Purpose: Backup reference for retro cursor definitions used in Card-o-Bot console interface
