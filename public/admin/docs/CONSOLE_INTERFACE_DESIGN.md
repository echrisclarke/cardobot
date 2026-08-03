# Card-o-Bot Console Interface Design

## Overview

All user-facing pages (login, profile, main app) will be displayed inside a retro computer console interface, creating an immersive "working on the Card-o-Bot machine" experience. Admin pages remain unchanged with their standard interface.

**Note:** This console will be built entirely with HTML and CSS, recreating the visual design from `Console-Mobile-12.png`. We are NOT using the image file itself - we're recreating it with code for better scalability, responsiveness, and customization.

## Visual Design

### Console Structure

The console consists of **layered divs** creating a 3D effect:

1. **Console Container** (`#all_cardobotConsole` or `.cardobot-console`)
   - Main wrapper for entire console
   - Positioned absolutely or as block with margin auto
   - Responsive width/height

2. **Console Background** (`#console_back` or `.console-back`)
   - Full width/height of container
   - Drop shadow effects: `drop-shadow(5vw 5vw 1vw rgba(87, 199, 206, .1)) drop-shadow(.3vh .3vh .3vh rgba(60,60,60,.5))`
   - Z-index: behind everything

3. **Yellow Border Layer** (`#yellow_cs` or `.console-yellow-border`)
   - Background: `rgba(255,230,190,1)` (yellow/beige accent)
   - Border: `.5vh solid rgba(70,70,70,1)`
   - Border-radius: `5vh` (very rounded)
   - Width: `96%`, Height: `100%`
   - Z-index: 2
   - Pointer-events: none

4. **Gray Layer** (`#gray_cs` or `.console-gray-layer`)
   - Background: `var(--color-dark-gray)` or `rgba(120,120,120, 1)`
   - Border: `.4vh solid var(--color-dark)`
   - Border-radius: `4vh`
   - Width: `80%`, Height: `100%`
   - Position: `right: 0` (aligned to right)
   - Z-index: 1
   - Pointer-events: none

5. **Screen Layers** (Two layers for depth)
   - **Screen Layer A** (`#screena_cs` or `.console-screen-dark`)
     - Background: `var(--color-dark-dark)` (very dark)
     - Border: `.5vh solid var(--color-border-gray)`
     - Border-radius: `4vh`
     - Width: `95%`, Height: `100%`
     - Position: `right: 0`
     - Z-index: 4
   
   - **Screen Layer B** (`#screenb_cs` or `.console-screen-light`)
     - Background: `var(--color-dark-light)` (lighter gray)
     - Border: `.5vh solid var(--color-border-gray)`
     - Border-radius: `4vh`
     - Width: `100%`, Height: `100%`
     - Z-index: 3

6. **Screen Container** (`#screen_cs` or `.console-screen`)
   - Contains all actual content
   - Width: `88%`, Height: `92%`
   - Positioned with margins for centering
   - Overflow: hidden
   - Z-index: 5+ (above screen layers)

7. **Background** (Desktop Only)
   - **Uses actual image file** (not CSS gradients): `background4b.jpg` from `/assets/img/`
   - Image path variable: `var(--image-path-bg-4)` which is `url("../img/background4b.jpg")`
   - Fixed attachment, cover size
   - No-repeat, center center
   - Sky with clouds and landscape (from image file)

### Color Palette

**Note:** These colors already exist in `variables.css` and should be reused:

```css
/* From existing variables.css - REUSE THESE */
--color-light-darker: rgb(203, 183, 155);    /* Beige - Main machine body */
--color-accent-beige: rgb(255, 229, 192);    /* Yellow accent border */
--color-dark-dark: rgb(40, 40, 40);          /* Dark screen background */
--color-dark-light: rgb(90, 90, 90);         /* Lighter screen layer */
--color-dark-lighter: rgb(100, 100, 100);     /* Gray layer (close to rgba(120,120,120,1)) */
--color-dark: rgb(68, 71, 70);               /* Dark borders (rgba(70,70,70,1) or rgba(50,50,50,1)) */
--color-secondary-light: rgb(94, 210, 240);  /* Light blue text (rgba(94, 210, 240, .95)) */
--color-primary: rgb(224, 126, 140);         /* Pink text */
--color-text-light: rgb(255, 238, 199);      /* Light text (rgba(255, 230, 190, 1)) */
```

**Actual colors from old CSS:**
- Yellow border: `rgba(255,230,190,1)` or `rgba(255, 229, 192, 1)` ✅ Matches `--color-accent-beige`
- Beige: `rgba(203, 183, 155, 1.00)` ✅ Matches `--color-light-darker`
- Dark screen: `rgba(40,40,40, 1)` ✅ Matches `--color-dark-dark`
- Medium screen: `rgba(90,90,90, 1)` ✅ Matches `--color-dark-light`
- Gray layer: `rgba(120,120,120, 1)` ✅ Now `--color-dark-gray` (added to variables.css)
- Border: `rgba(70,70,70,1)` or `rgba(50,50,50,1)` ✅ Matches `--color-dark` and `--color-border-gray`
- Text blue: `rgba(94, 210, 240, .95)` ✅ Matches `--color-secondary-light`
- Text yellow: `rgba(255, 230, 190, 1)` ✅ Matches `--color-text-light`
- Input background: `rgba(80, 120, 120, .85)` ✅ Now `--color-console-input-bg` (added to variables.css)
- Input border: `rgba(99, 195, 177, .75)` ✅ Now `--color-console-input-border` (added to variables.css)
- Drop shadows: Various teal/cyan shadows ✅ Now `--color-console-shadow-*` variables (added to variables.css)

## HTML Structure

### Base Layout

**Note:** We are recreating the `Console-Mobile-12.png` design with pure HTML/CSS. The structure matches the visual layers seen in the reference image:

```html
<body class="console-page">
    <!-- Console Container (beige outer frame) -->
    <div class="cardobot-console">
        <!-- Console Title -->
        <div class="console-title">CARD-O-BOT</div>
        
        <!-- Decorative Screws (4 corners) -->
        <div class="console-screw console-screw-top-left">●</div>
        <div class="console-screw console-screw-top-right">●</div>
        <div class="console-screw console-screw-bottom-left">●</div>
        <div class="console-screw console-screw-bottom-right">●</div>
        
        <!-- Inner Dark Border (around screen) -->
        <div class="console-inner-border"></div>
        
        <!-- Gray Strip (right edge) -->
        <div class="console-gray-strip"></div>
        
        <!-- Screen Area (dark charcoal, contains content) -->
        <div class="console-screen">
            <div class="console-screen-content">
                <!-- All page content goes here -->
                <!-- Login form, profile, cards, etc. -->
            </div>
        </div>
    </div>
</body>
```

**Z-Index Order (bottom to top):**
1. `.cardobot-console` (base container with beige background)
2. `.console-inner-border` (dark gray border)
3. `.console-gray-strip` (right edge strip)
4. `.console-screen` (dark screen area)
5. `.console-screen-content` (actual content)
6. `.console-title` (text overlay)
7. `.console-screw` (decorative elements on top)

## CSS Implementation

### 3D Effects

Use CSS to create depth:

1. **Box Shadows**
   - Multiple layered shadows
   - Inset shadows for screen depth
   - Outset shadows for machine body

2. **Borders**
   - Multiple border layers
   - Light borders on top/left (highlight)
   - Dark borders on bottom/right (shadow)
   - Creates 3D embossed effect

3. **Gradients**
   - Subtle gradients on machine body
   - Screen gradient for depth
   - Background sky gradient

### Console Container

```css
.cardobot-console {
  /* Desktop: Fixed size, centered like a physical device */
  position: relative;
  width: 600px; /* Fixed width on desktop */
  height: 900px; /* Fixed height on desktop */
  max-width: 90vw; /* Safety limit */
  max-height: 95vh; /* Safety limit */
  margin: 2rem auto; /* Centered horizontally */
  
  /* Beige outer frame (machine body) */
  background: var(--color-light-darker); /* rgb(203, 183, 155) */
  border-radius: 2rem; /* Fixed rounded corners on desktop */
  
  /* 3D Effect - Highlights and shadows */
  box-shadow: 
    /* Inner shadow for depth */
    inset -2px -2px 4px rgba(0, 0, 0, 0.2),
    inset 2px 2px 4px rgba(255, 255, 255, 0.3),
    /* Drop shadow on right side */
    8px 8px 16px var(--color-console-shadow-dark),
    4px 4px 8px var(--color-console-shadow-teal);
  
  /* Padding for inner elements - fixed on desktop */
  padding: 2rem;
  box-sizing: border-box;
}

/* Mobile: Responsive width and height */
@media (max-width: 768px) {
  .cardobot-console {
    width: 100vw;
    height: 100vh;
    max-width: 100vw;
    max-height: 100vh;
    margin: 0;
    border-radius: 0; /* No rounded corners on mobile */
    padding: clamp(1rem, 3vw, 1.5rem); /* Responsive padding */
  }
}

.console-title {
  position: absolute;
  top: 1rem; /* Fixed on desktop */
  left: 50%;
  transform: translateX(-50%);
  font-family: var(--font-family-retro); /* Pixelated font */
  font-size: 1rem; /* Fixed on desktop */
  color: var(--color-text-light); /* Light gray/off-white */
  letter-spacing: 2px;
  z-index: 10;
  text-transform: uppercase;
  pointer-events: none;
}

@media (max-width: 768px) {
  .console-title {
    top: clamp(0.75rem, 2vw, 1rem);
    font-size: clamp(0.625rem, 2vw, 0.875rem);
  }
}

.console-screw {
  position: absolute;
  width: 0.625rem; /* Fixed on desktop */
  height: 0.625rem;
  background: var(--color-dark);
  border-radius: 50%;
  z-index: 10;
  pointer-events: none;
}

.console-screw::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 20%;
  right: 20%;
  height: 1px;
  background: var(--color-dark-light);
  transform: translateY(-50%);
}

.console-screw-top-left {
  top: 0.875rem; /* Fixed on desktop */
  left: 0.875rem;
}

.console-screw-top-right {
  top: 0.875rem;
  right: 0.875rem;
}

.console-screw-bottom-left {
  bottom: 0.875rem;
  left: 0.875rem;
}

.console-screw-bottom-right {
  bottom: 0.875rem;
  right: 0.875rem;
}

@media (max-width: 768px) {
  .console-screw {
    width: clamp(0.5rem, 1.5vw, 0.625rem);
    height: clamp(0.5rem, 1.5vw, 0.625rem);
  }
  
  .console-screw-top-left,
  .console-screw-top-right {
    top: clamp(0.75rem, 2vw, 1rem);
  }
  
  .console-screw-top-left,
  .console-screw-bottom-left {
    left: clamp(0.75rem, 2vw, 1rem);
  }
  
  .console-screw-top-right,
  .console-screw-bottom-right {
    right: clamp(0.75rem, 2vw, 1rem);
  }
  
  .console-screw-bottom-left,
  .console-screw-bottom-right {
    bottom: clamp(0.75rem, 2vw, 1rem);
  }
}

.console-inner-border {
  position: absolute;
  top: 4rem; /* Fixed on desktop */
  left: 1.5rem;
  right: 0.75rem; /* Extend slightly to right for 3D effect */
  bottom: 1.5rem;
  background: transparent;
  border: 0.5rem solid var(--color-dark); /* rgb(68, 71, 70) */
  border-radius: 1.5rem;
  z-index: 2;
  pointer-events: none;
}

.console-gray-strip {
  position: absolute;
  top: 4rem;
  right: 0.5rem;
  width: 0.625rem;
  bottom: 1.5rem;
  background: var(--color-dark-gray); /* rgb(120, 120, 120) */
  border-radius: 0 0.5rem 0.5rem 0;
  z-index: 3;
  pointer-events: none;
}

.console-screen {
  position: absolute;
  top: 5rem; /* Fixed on desktop */
  left: 2rem;
  right: 2.5rem; /* Account for gray strip */
  bottom: 2rem;
  
  /* Dark charcoal screen */
  background: var(--color-dark-dark); /* rgb(40, 40, 40) */
  border-radius: 1rem;
  
  /* Recessed effect with inner shadow */
  box-shadow: 
    inset 0 2px 4px rgba(0, 0, 0, 0.5),
    inset 0 1px 2px rgba(0, 0, 0, 0.7);
  
  /* Inner border for depth */
  border: 0.125rem solid var(--color-border-gray); /* rgb(50, 50, 50) */
  
  z-index: 4;
  overflow: hidden;
  box-sizing: border-box;
}

.console-screen-content {
  width: 100%;
  height: 100%;
  padding: 1.5rem; /* Fixed on desktop */
  overflow-y: auto;
  overflow-x: hidden;
  box-sizing: border-box;
}

/* Mobile: Responsive sizing */
@media (max-width: 768px) {
  .console-inner-border {
    top: clamp(3rem, 6vw, 4rem);
    left: clamp(1rem, 2vw, 1.5rem);
    right: clamp(0.5rem, 1vw, 0.75rem);
    bottom: clamp(1rem, 2vw, 1.5rem);
    border-width: clamp(0.25rem, 0.5vw, 0.5rem);
    border-radius: clamp(1rem, 2vw, 1.5rem);
  }
  
  .console-gray-strip {
    top: clamp(3rem, 6vw, 4rem);
    right: clamp(0.25rem, 0.5vw, 0.5rem);
    width: clamp(0.5rem, 1vw, 0.75rem);
    bottom: clamp(1rem, 2vw, 1.5rem);
    border-radius: 0 clamp(0.5rem, 1vw, 0.75rem) clamp(0.5rem, 1vw, 0.75rem) 0;
  }
  
  .console-screen {
    top: clamp(3.5rem, 7vw, 5rem);
    left: clamp(1.5rem, 3vw, 2rem);
    right: clamp(2rem, 4vw, 2.5rem);
    bottom: clamp(1.5rem, 3vw, 2rem);
    border-radius: clamp(0.75rem, 1.5vw, 1rem);
    border-width: clamp(0.125rem, 0.25vw, 0.25rem);
  }
  
  .console-screen-content {
    padding: clamp(1rem, 2vw, 1.5rem);
  }
}
```

### Screen Content Area

```css
.console-screen-content {
  /* Content goes inside .console-screen */
  width: 100%;
  height: 100%;
  padding: clamp(1vh, 2vh, 3vh);
  overflow-y: auto;
  overflow-x: hidden;
  
  /* Text colors from old CSS */
  color: var(--color-secondary-light); /* rgba(94, 210, 240, .95) */
  font-family: var(--font-family-primary); /* "Roboto", "Open-sans", sans-serif */
  font-weight: bold;
  letter-spacing: 2px;
  font-size: clamp(1.25vh, 1.5vh, 2vh);
}

.console-screen-content h5 {
  color: var(--color-accent-beige); /* rgba(255, 229, 192, 1) */
}

.console-screen-content p {
  color: var(--color-secondary-light); /* rgba(94, 210, 240, .95) or rgba(180, 230, 240, 1) */
}
```

### Background (Desktop)

**Note:** The old Card-o-Bot uses **actual image files** (not CSS gradients). Background images are stored in `/assets/img/` directory. We should use the same approach:

```css
.console-page {
  /* Background image from old CSS - actual image file */
  background: var(--image-path-bg-4); /* url("../img/background4b.jpg") */
  background-repeat: var(--bg-image-repeat); /* no-repeat */
  background-position: var(--bg-image-position); /* center center */
  background-attachment: var(--bg-image-attachment); /* fixed */
  background-size: var(--bg-image-size); /* cover */
  
  /* Vendor prefixes for older browsers */
  -webkit-background-size: cover;
  -moz-background-size: cover;
  -o-background-size: cover;
}
```

**Available background images from `/assets/img/` directory:**
- `background1.jpg` → `--image-path-bg-1`: `url("../img/background1.jpg")`
- `background2.jpg` → `--image-path-bg-2`: `url("../img/background2.jpg")`
- `background3b.jpg` → `--image-path-bg-3`: `url("../img/background3b.jpg")`
- `background4b.jpg` → `--image-path-bg-4`: `url("../img/background4b.jpg")` ← Used in old CSS

**Important:** These are actual `.jpg` image files, not CSS-generated gradients. The images contain the sky, clouds, and landscape artwork.

## Responsive Behavior

### Desktop (> 768px)

- **Console has fixed dimensions**: 600px width × 900px height
- Console is centered horizontally on page (like a physical device)
- Background visible around console
- Console sits "on top" of background
- All decorative elements visible at fixed sizes
- Fixed padding, borders, and spacing
- Rounded corners (2rem border-radius)

### Mobile (≤ 768px)

- **Console fills viewport**: 100vw width × 100vh height
- No background (or minimal)
- Console becomes the device itself
- Full-screen immersive experience
- All dimensions become responsive (using clamp())
- No rounded corners (border-radius: 0)
- Responsive padding, borders, and spacing
- Screen area takes most space

```css
@media (max-width: 768px) {
  .cardobot-console {
    max-width: 100%;
    margin: 0;
    border-radius: 0;
    min-height: 100vh;
    padding: 1rem;
  }
  
  .console-background {
    display: none; /* Or minimal */
  }
  
  .console-header,
  .console-buttons,
  .console-footer {
    /* Simplified or hidden on mobile */
  }
  
  .console-screen {
    min-height: calc(100vh - 200px);
  }
}
```

## Content Integration

### Page Structure

All user pages will follow this pattern:

```php
<?php
// ... existing PHP code ...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- ... head content ... -->
    <link rel="stylesheet" href="<?php echo $assetPath; ?>/assets/css/base.css">
</head>
<body class="console-page">
    <div class="console-background"></div>
    
    <div class="cardobot-console">
        <div class="console-header">
            <span class="console-title">CARD-O-BOT</span>
            <span class="console-screw">●</span>
            <span class="console-screw">●</span>
        </div>
        
        <div class="console-screen">
            <!-- Existing page content here -->
            <!-- Login form, profile tabs, card collection, etc. -->
        </div>
        
        <div class="console-buttons">
            <!-- Decorative buttons -->
        </div>
        
        <div class="console-footer">
            <!-- Deck unit, cable, etc. -->
        </div>
    </div>
</body>
</html>
```

### Pages to Update

1. **`index.php`** - Main app
2. **`login.php`** - Login/registration
3. **`profile.php`** - User profile
4. **`link-account.php`** - Account linking

### Pages to Leave Unchanged

- All files in `/admin/` directory
- All files in `/api/` directory (they're endpoints, not pages)

## Form Styling

### Input Fields

**From old CSS:** Inputs have dark backgrounds with colored borders and drop shadows:

```css
.console-screen input,
.console-screen textarea,
.console-screen select {
  font-family: var(--font-family-primary); /* "Roboto", "Open-sans", sans-serif */
  background-color: var(--color-console-input-bg); /* rgba(80, 120, 120, .85) */
  border: clamp(0.15vh, 0.2vh, 0.2vh) solid var(--color-console-input-border); /* rgba(99, 195, 177, .75) */
  border-radius: clamp(0.8vh, 1vh, 1vh);
  padding: clamp(0.8vh, 1vh, 1vh) clamp(1.5vh, 2vh, 2vh);
  color: var(--color-accent-beige); /* rgba(255, 230, 190, 1) */
  font-size: clamp(1.5vh, 2vh, 2vh);
  font-weight: bold;
  letter-spacing: 2px;
  
  /* Drop shadow effect - using variables */
  filter: drop-shadow(6px 0px .5px var(--color-console-shadow-cyan)) 
          drop-shadow(5vw -.5vw .2vw var(--color-console-shadow-teal-light));
  -webkit-filter: drop-shadow(6px 0px .5px var(--color-console-shadow-cyan)) 
                  drop-shadow(5vw -.5vw .2vw var(--color-console-shadow-teal-light));
}

.console-screen input::placeholder {
  color: var(--color-secondary-light); /* rgba(94, 210, 240, .95) */
  font-size: clamp(1.5vh, 2vh, 2vh);
  font-weight: bold;
  letter-spacing: 2px;
}

.console-screen input:hover,
.console-screen input:focus {
  filter: drop-shadow(6px 0px .5px var(--color-console-shadow-cyan)) 
          brightness(120%) 
          drop-shadow(5vw -.5vw .2vw var(--color-console-shadow-teal-light));
  -webkit-filter: drop-shadow(6px 0px .5px var(--color-console-shadow-cyan)) 
                  brightness(120%) 
                  drop-shadow(5vw -.5vw .2vw var(--color-console-shadow-teal-light));
}
```

## Implementation Steps

### Phase 1: CSS Foundation ✅ COMPLETE
1. ✅ **Verify existing variables** in `variables.css` (all console colors already exist!)
2. ✅ Create console base styles in `base.css` using existing variables
3. ✅ Add background image styles (using `--image-path-bg-4`)
4. ✅ Test layered 3D effects (beige frame, inner border, gray strip, screen layers)

**Status:** All console CSS has been added to `base.css` with:
- Console container (fixed 600px × 900px on desktop, 100vw × 100vh on mobile)
- Console title with retro font
- Decorative screws (4 corners)
- Inner dark border
- Gray strip (right edge)
- Screen area with recessed 3D effect
- Screen content container
- Full mobile responsive styles

### Phase 2: HTML Structure ✅ COMPLETE
1. ✅ Create console wrapper component
2. ✅ Update `index.php` with console structure
3. ✅ Update `login.php` with console structure
4. ✅ Update `profile.php` with console structure
5. ✅ Update `link-account.php` with console structure

**Status:** All user-facing pages now have console HTML structure:
- Console container with title, screws, borders, and screen
- All page content wrapped in `.console-screen-content`
- Header/navigation moved inside console screen
- Forms and content properly contained

### Phase 3: Content Styling ⏳ PENDING
1. ⏳ Style forms for console screen
2. ⏳ Update buttons for console aesthetic
3. ⏳ Adjust typography for retro feel
4. ⏳ Ensure all content fits within screen area

**Status:** Waiting for HTML structure implementation.

### Phase 4: Responsive Design ✅ COMPLETE
1. ✅ Implement mobile console styles
2. ⏳ Test on various screen sizes (needs browser testing)
3. ✅ Adjust decorative elements for mobile
4. ⏳ Ensure usability on touch devices (needs browser testing)

**Status:** Mobile styles implemented with responsive `clamp()` values. Testing needed.

### Phase 5: Polish ⏳ PENDING
1. ⏳ Add subtle animations
2. ⏳ Refine 3D effects (basic 3D effects implemented, may need refinement)
3. ⏳ Test color contrast
4. ⏳ Ensure accessibility

**Status:** Basic 3D effects implemented. Polish phase comes after HTML structure is complete.

## Technical Considerations

### Performance
- Use CSS gradients instead of images where possible
- Optimize background rendering
- Consider `will-change` for animations
- Test on lower-end devices

### Accessibility
- Maintain color contrast ratios
- Ensure keyboard navigation works
- Screen reader compatibility
- Focus indicators visible

### Browser Compatibility
- Test CSS gradients
- Test box-shadow support
- Test border-radius
- Fallbacks for older browsers

## File Organization

### New Files
- `assets/css/console.css` - Console-specific styles (optional, or add to base.css)

### Modified Files
- ✅ `assets/css/variables.css` - **No changes needed!** All console colors already exist
- ✅ `assets/css/base.css` - **COMPLETE** - Console styles added using existing variables
- ✅ `index.php` - **COMPLETE** - Content wrapped in console structure
- ✅ `login.php` - **COMPLETE** - Content wrapped in console structure
- ✅ `profile.php` - **COMPLETE** - Content wrapped in console structure
- ✅ `link-account.php` - **COMPLETE** - Content wrapped in console structure

## Design Principles

1. **Immersive Experience** - Users feel like they're using the actual Card-o-Bot machine
2. **Retro Aesthetic** - Pixelated fonts, 3D effects, retro color palette
3. **Responsive First** - Mobile becomes the device, desktop shows the device on background
4. **Content First** - Console enhances content, doesn't obstruct it
5. **Performance** - Smooth rendering, no lag
6. **Accessibility** - Usable by everyone, regardless of ability

## Next Steps

1. ✅ Review and approve this design document
2. ✅ Create console CSS variables (all exist in variables.css)
3. ✅ Build console CSS styles (complete in base.css)
4. ✅ Implement 3D effects (box-shadows, inset borders, layered elements)
5. ⏳ **NEXT:** Build console HTML structure and update pages one by one
6. ⏳ Test and refine

## Current Status Summary

**✅ COMPLETED:**
- CSS Foundation (Phase 1) - All console styles in `base.css`
- HTML Structure (Phase 2) - All pages wrapped in console structure
- Responsive Design (Phase 4) - Mobile styles implemented
- CSS Variables - All console colors exist in `variables.css`

**⏳ NEXT STEPS:**
- Content Styling (Phase 3) - Style forms, buttons, and typography for console aesthetic
- Polish (Phase 5) - Add animations, refine 3D effects, test and refine
