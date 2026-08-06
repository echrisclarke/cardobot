/**
 * Card Viewer + Ink Deck apps for Card-o-Bot.
 */
(function (global) {
  'use strict';

  const SWATCHES = [
    '#222222', '#646464', '#ffffff', '#e07e8c', '#5ed2f0',
    '#95f5e3', '#f9bbaa', '#ffe5c0', '#7a5cff', '#3d8b40',
  ];

  function hexToRgb(hex) {
    const m = String(hex || '').trim().match(/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i);
    if (!m) return null;
    let h = m[1];
    if (h.length === 3) h = h.split('').map((c) => c + c).join('');
    return {
      r: parseInt(h.slice(0, 2), 16),
      g: parseInt(h.slice(2, 4), 16),
      b: parseInt(h.slice(4, 6), 16),
    };
  }

  function rgbToHex(r, g, b) {
    const c = (n) => Math.max(0, Math.min(255, Math.round(n))).toString(16).padStart(2, '0');
    return '#' + c(r) + c(g) + c(b);
  }

  function rgbToHsv(r, g, b) {
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const d = max - min;
    let h = 0;
    if (d > 0) {
      if (max === r) h = ((g - b) / d) % 6;
      else if (max === g) h = (b - r) / d + 2;
      else h = (r - g) / d + 4;
      h *= 60;
      if (h < 0) h += 360;
    }
    const s = max === 0 ? 0 : d / max;
    return { h, s, v: max };
  }

  function hsvToRgb(h, s, v) {
    const c = v * s;
    const x = c * (1 - Math.abs(((h / 60) % 2) - 1));
    const m = v - c;
    let r = 0, g = 0, b = 0;
    if (h < 60) { r = c; g = x; }
    else if (h < 120) { r = x; g = c; }
    else if (h < 180) { g = c; b = x; }
    else if (h < 240) { g = x; b = c; }
    else if (h < 300) { r = x; b = c; }
    else { r = c; b = x; }
    return {
      r: Math.round((r + m) * 255),
      g: Math.round((g + m) * 255),
      b: Math.round((b + m) * 255),
    };
  }

  function extractPaletteFromImage(img, count) {
    const want = count || 8;
    if (!img || !img.width) return [];
    try {
      const size = 56;
      const canvas = document.createElement('canvas');
      canvas.width = size;
      canvas.height = size;
      const ctx = canvas.getContext('2d', { willReadFrequently: true });
      if (!ctx) return [];
      ctx.drawImage(img, 0, 0, size, size);
      const data = ctx.getImageData(0, 0, size, size).data;
      const buckets = new Map();
      for (let i = 0; i < data.length; i += 4) {
        if (data[i + 3] < 180) continue;
        const r = data[i];
        const g = data[i + 1];
        const b = data[i + 2];
        const max = Math.max(r, g, b);
        const min = Math.min(r, g, b);
        if (max < 22 || min > 248) continue;
        // Favor mid/high chroma so ink picks feel useful.
        if ((max - min) < 12 && max > 40 && max < 220) continue;
        const qr = r >> 4;
        const qg = g >> 4;
        const qb = b >> 4;
        const key = (qr << 8) | (qg << 4) | qb;
        let slot = buckets.get(key);
        if (!slot) {
          slot = { r: 0, g: 0, b: 0, n: 0 };
          buckets.set(key, slot);
        }
        slot.r += r;
        slot.g += g;
        slot.b += b;
        slot.n += 1;
      }
      const ranked = Array.from(buckets.values())
        .filter((s) => s.n >= 3)
        .map((s) => ({
          r: Math.round(s.r / s.n),
          g: Math.round(s.g / s.n),
          b: Math.round(s.b / s.n),
          n: s.n,
        }))
        .sort((a, b) => b.n - a.n);

      const picked = [];
      ranked.forEach((c) => {
        if (picked.length >= want) return;
        const tooClose = picked.some((p) => {
          const dr = p.r - c.r;
          const dg = p.g - c.g;
          const db = p.b - c.b;
          return (dr * dr + dg * dg + db * db) < 1400;
        });
        if (!tooClose) picked.push(c);
      });
      return picked.map((c) => rgbToHex(c.r, c.g, c.b));
    } catch (e) {
      return [];
    }
  }

  // Card-o-Bot ink tool glyphs (Herb icons + console SVGs).
  const INK_ICONS = {
    brush: 'pen.svg',
    eraser: 'eraser.svg',
    hand: 'hand.svg',
    brushes: 'tip.svg',
    inkcolor: 'color.svg',
    hud: 'size.svg',
    undo: 'undo.png',
    redo: 'redo.png',
    zoomout: 'zoom-out.png',
    zoomin: 'zoom-in.png',
    resetzoom: 'fit.svg',
    layers: 'layers.svg',
    tint: 'tint.svg',
    flip: 'flip.svg',
    draw: 'pen.svg',
    save: 'save.svg',
    download: 'download.svg',
    'new-layer': 'new-layer.png',
    clear: 'clear.svg',
    trash: 'trash.png',
    eye: 'eye.svg',
    'eye-off': 'eye-off.svg',
  };

  class CardViewer {
    constructor(opts) {
      this.assetBase = opts.assetBase || '';
      this.apiBase = opts.apiBase || '';
      this.onClose = opts.onClose || function () {};
      this.onCreditChange = opts.onCreditChange || null;
      this.onSave = opts.onSave || null;
      this.mode = 'viewer';
      this.flipped = false;
      this.tilt = { x: 0, y: 0 };
      this.scale = 1;
      this.pan = { x: 0, y: 0 };
      this.concept = {};
      this.stats = {};
      this.artUrl = '';
      this.sessionId = '';
      this.backVariant = 0;
      this.backHue = 195;
      this.backSat = 40;
      this.backLight = 45;
      this._frontHsl = { h: 195, s: 65, l: 40 };
      this._backHsl = { h: 195, s: 40, l: 45 };
      this._colorFace = 'front';
      this.brushes = [];
      this.activeBrushId = 'hard-round';
      this.brushColor = '#646464';
      this.brushOpacity = 1;
      this._pickerHsv = rgbToHsv(100, 100, 100);
      this._artSwatches = [];
      this.activeTool = 'brush';
      this.studio = null;
      this._pointers = new Map();
      this._reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      this._spaceDown = false;
      this._build();
      this._loadBrushes();
    }

    _build() {
      this.el = document.createElement('div');
      this.el.className = 'cob-app-shell';
      this.el.innerHTML = `
        <div class="cob-app-bar">
          <button type="button" class="cob-app-btn" data-act="back">Back</button>
          <div class="cob-app-title" data-title>CARD VIEWER</div>
          <div class="cob-app-status" data-status></div>
          <button type="button" class="cob-app-btn primary" data-act="done">Done</button>
        </div>
        <div class="cob-stage">
          <div class="cob-rail cob-rail-left" data-rail-left></div>
          <div class="cob-rail cob-rail-right" data-rail-right></div>
          <aside class="cob-back-picker" data-back-picker aria-label="Card back designs">
            <div class="cob-back-picker-head">Backs</div>
            <div class="cob-back-picker-list" data-back-list></div>
          </aside>
          <div class="cob-card-viewport" data-viewport>
            <div class="cob-card-inner" data-inner>
              <div class="cob-face cob-face-front" data-front></div>
              <div class="cob-face cob-face-back" data-back>
                <div class="cob-back-tint" data-back-tint></div>
                <img class="cob-back-art" data-back-art alt="Card back">
              </div>
            </div>
          </div>
        </div>
        <div class="cob-hud" data-hud>
          <div class="cob-hud-row">
            <span class="cob-hud-label">SIZE</span>
            <input type="range" min="1" max="48" value="8" data-brush-size>
            <span class="cob-hud-val" data-size-val>8</span>
          </div>
          <div class="cob-hud-row">
            <span class="cob-hud-label">OPACITY</span>
            <input type="range" min="5" max="100" value="100" data-brush-opacity>
            <span class="cob-hud-val" data-opacity-val>100%</span>
          </div>
          <button type="button" class="cob-app-btn" data-act="close-hud">Close</button>
        </div>
        <div class="cob-popover cob-popover-color" data-popover-color>
          <div class="cob-color-head">
            <h3>Ink color</h3>
            <span class="cob-color-chip" data-color-preview style="background:#646464"></span>
          </div>
          <div class="cob-picker" data-picker>
            <div class="cob-picker-sv" data-sv tabindex="0" role="slider" aria-label="Saturation and brightness">
              <div class="cob-picker-sv-cursor" data-sv-cursor></div>
            </div>
            <div class="cob-picker-hue" data-hue-bar tabindex="0" role="slider" aria-label="Hue">
              <div class="cob-picker-hue-cursor" data-hue-cursor></div>
            </div>
            <div class="cob-picker-meta">
              <label class="cob-picker-hex">
                <span class="cob-hud-label">HEX</span>
                <input type="text" data-hex maxlength="7" spellcheck="false" value="#646464" autocomplete="off">
              </label>
              <div class="cob-picker-rgb">
                <label>R <input type="number" data-rgb-r min="0" max="255" step="1" value="100"></label>
                <label>G <input type="number" data-rgb-g min="0" max="255" step="1" value="100"></label>
                <label>B <input type="number" data-rgb-b min="0" max="255" step="1" value="100"></label>
              </div>
            </div>
          </div>
          <div class="cob-swatch-block">
            <span class="cob-hud-label">Presets</span>
            <div class="cob-swatches" data-swatches></div>
          </div>
          <div class="cob-swatch-block" data-art-block hidden>
            <span class="cob-hud-label">From art</span>
            <div class="cob-swatches cob-swatches-art" data-art-swatches></div>
          </div>
          <button type="button" class="cob-app-btn" data-act="close-popovers">Close</button>
        </div>
        <div class="cob-sheet cob-panel" data-sheet-color>
          <h3>Card tint</h3>
          <label>Face
            <select data-color-face>
              <option value="front">Front</option>
              <option value="back">Back</option>
            </select>
          </label>
          <label>Hue <input type="range" min="0" max="360" value="195" data-hue></label>
          <label>Sat <input type="range" min="0" max="100" value="65" data-sat></label>
          <label>Light <input type="range" min="0" max="100" value="40" data-light></label>
          <label class="cob-check">
            <input type="checkbox" data-show-credit checked>
            Show username on card
          </label>
          <button type="button" class="cob-app-btn" data-act="close-sheet">Close</button>
        </div>
        <div class="cob-sheet cob-panel" data-sheet-brushes>
          <h3>Brush tips</h3>
          <div class="cob-brush-grid" data-brush-grid></div>
          <button type="button" class="cob-app-btn" data-act="close-sheet">Close</button>
        </div>
        <div class="cob-sheet cob-panel cob-layers-panel" data-sheet-layers>
          <div class="cob-layers-head">
            <h3>Layers</h3>
            <button type="button" class="cob-icon-act" data-act="close-sheet" title="Close" aria-label="Close">×</button>
          </div>
          <div class="cob-layer-list" data-layer-list></div>
          <div class="cob-layers-toolbar">
            <button type="button" class="cob-icon-act" data-act="new-layer" title="New layer" aria-label="New layer">
              <img src="" alt="" data-ink-icon="new-layer">
            </button>
            <button type="button" class="cob-icon-act" data-act="clear-layer" title="Clear layer" aria-label="Clear layer">
              <img src="" alt="" data-ink-icon="clear">
            </button>
            <button type="button" class="cob-icon-act danger" data-act="trash-layers" title="Delete all layers" aria-label="Delete all layers">
              <img src="" alt="" data-ink-icon="trash">
            </button>
          </div>
        </div>
        <div class="cob-dock" data-dock></div>
      `;
      const screen = document.querySelector('.console-screen');
      (screen || document.body).appendChild(this.el);
      if (!screen) this.el.classList.add('cob-app-shell--fallback');

      this.viewport = this.el.querySelector('[data-viewport]');
      this.inner = this.el.querySelector('[data-inner]');
      this.front = this.el.querySelector('[data-front]');
      this.backArt = this.el.querySelector('[data-back-art]');
      this.backTint = this.el.querySelector('[data-back-tint]');
      this.backPicker = this.el.querySelector('[data-back-picker]');
      this.titleEl = this.el.querySelector('[data-title]');
      this.statusEl = this.el.querySelector('[data-status]');
      this.dock = this.el.querySelector('[data-dock]');
      this.railLeft = this.el.querySelector('[data-rail-left]');
      this.railRight = this.el.querySelector('[data-rail-right]');
      this.hud = this.el.querySelector('[data-hud]');
      this.popoverColor = this.el.querySelector('[data-popover-color]');
      this.sheetColor = this.el.querySelector('[data-sheet-color]');
      this.sheetBrushes = this.el.querySelector('[data-sheet-brushes]');
      this.sheetLayers = this.el.querySelector('[data-sheet-layers]');

      this._renderBackPicker();
      this._renderSwatches();
      this._wireColorPicker();
      this._wireInkIconImgs(this.el);
      this._setBrushColor(this.brushColor);

      this.el.addEventListener('click', (e) => {
        const act = e.target.closest('[data-act]');
        if (!act) return;
        this._action(act.getAttribute('data-act'));
      });

      this.el.querySelectorAll('[data-hue],[data-sat],[data-light]').forEach((el) => {
        el.addEventListener('input', () => this._onColorInput(false));
        el.addEventListener('change', () => this._onColorInput(false));
      });
      const faceSel = this.el.querySelector('[data-color-face]');
      if (faceSel) {
        faceSel.addEventListener('change', () => this._onFaceSelect());
      }
      const creditToggle = this.el.querySelector('[data-show-credit]');
      if (creditToggle) {
        creditToggle.addEventListener('change', () => this._onCreditToggle());
      }

      const sizeEl = this.el.querySelector('[data-brush-size]');
      const opacityEl = this.el.querySelector('[data-brush-opacity]');
      if (sizeEl) {
        sizeEl.addEventListener('input', () => {
          const v = +sizeEl.value;
          this.el.querySelector('[data-size-val]').textContent = String(v);
          const eng = this.studio && this.studio.getEngine();
          if (eng) eng.setSize(v);
        });
      }
      if (opacityEl) {
        opacityEl.addEventListener('input', () => {
          const v = +opacityEl.value;
          this.brushOpacity = v / 100;
          this.el.querySelector('[data-opacity-val]').textContent = v + '%';
          const eng = this.studio && this.studio.getEngine();
          if (eng) eng.setOpacity(this.brushOpacity);
        });
      }

      this._bindGestures();
      this._renderChrome();
    }

    _renderBackPicker() {
      const backs = (global.CardobotLayout && global.CardobotLayout.BACKS) || [];
      const list = this.backPicker.querySelector('[data-back-list]') || this.backPicker;
      list.innerHTML = backs.map((file, i) =>
        `<button type="button" class="cob-back-thumb${i === this.backVariant ? ' active' : ''}" data-back-idx="${i}" title="Back ${i + 1}">
          <img src="${this.assetBase}/assets/img/cardbacks/${file}" alt="">
        </button>`
      ).join('');
      list.querySelectorAll('[data-back-idx]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          this._setBackVariant(+btn.getAttribute('data-back-idx'));
        });
      });
    }

    _setBackVariant(idx) {
      const backs = (global.CardobotLayout && global.CardobotLayout.BACKS) || [];
      if (!backs.length) return;
      const n = backs.length;
      let next = Number.isFinite(+idx) ? Math.round(+idx) : 0;
      if (next < 0 || next >= n) next = ((next % n) + n) % n;
      this.backVariant = next;
      this.backArt.src = this.assetBase + '/assets/img/cardbacks/' + backs[this.backVariant];
      this.backPicker.querySelectorAll('.cob-back-thumb').forEach((el) => {
        el.classList.toggle('active', +el.getAttribute('data-back-idx') === this.backVariant);
      });
    }

    _renderSwatches() {
      this._fillSwatchWrap(this.el.querySelector('[data-swatches]'), SWATCHES);
    }

    _fillSwatchWrap(wrap, colors) {
      if (!wrap) return;
      const list = Array.isArray(colors) ? colors : [];
      wrap.innerHTML = list.map((c) =>
        `<button type="button" class="cob-swatch" data-swatch="${c}" style="background:${c}" aria-label="${c}"></button>`
      ).join('');
      wrap.querySelectorAll('[data-swatch]').forEach((btn) => {
        btn.addEventListener('click', () => this._setBrushColor(btn.getAttribute('data-swatch')));
      });
      this._markActiveSwatches();
    }

    _markActiveSwatches() {
      const cur = String(this.brushColor || '').toLowerCase();
      this.el.querySelectorAll('[data-swatch]').forEach((btn) => {
        btn.classList.toggle('is-active', String(btn.getAttribute('data-swatch') || '').toLowerCase() === cur);
      });
    }

    _wireColorPicker() {
      const sv = this.el.querySelector('[data-sv]');
      const hueBar = this.el.querySelector('[data-hue-bar]');
      const hexEl = this.el.querySelector('[data-hex]');
      const rEl = this.el.querySelector('[data-rgb-r]');
      const gEl = this.el.querySelector('[data-rgb-g]');
      const bEl = this.el.querySelector('[data-rgb-b]');

      const bindDrag = (el, onMove) => {
        if (!el) return;
        const move = (e) => {
          if (e.cancelable) e.preventDefault();
          onMove(e);
        };
        const up = () => {
          window.removeEventListener('pointermove', move);
          window.removeEventListener('pointerup', up);
          window.removeEventListener('pointercancel', up);
        };
        el.addEventListener('pointerdown', (e) => {
          if (e.button != null && e.button !== 0) return;
          el.setPointerCapture && el.setPointerCapture(e.pointerId);
          move(e);
          window.addEventListener('pointermove', move, { passive: false });
          window.addEventListener('pointerup', up);
          window.addEventListener('pointercancel', up);
        });
      };

      bindDrag(sv, (e) => {
        const rect = sv.getBoundingClientRect();
        const x = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        const y = Math.max(0, Math.min(1, (e.clientY - rect.top) / rect.height));
        this._pickerHsv.s = x;
        this._pickerHsv.v = 1 - y;
        this._applyPickerHsv(true);
      });

      bindDrag(hueBar, (e) => {
        const rect = hueBar.getBoundingClientRect();
        const x = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        this._pickerHsv.h = x * 360;
        this._applyPickerHsv(true);
      });

      if (hexEl) {
        hexEl.addEventListener('change', () => {
          let v = String(hexEl.value || '').trim();
          if (v && v[0] !== '#') v = '#' + v;
          const rgb = hexToRgb(v);
          if (rgb) this._setBrushColor(rgbToHex(rgb.r, rgb.g, rgb.b));
          else this._syncPickerFields();
        });
        hexEl.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') hexEl.blur();
        });
      }

      const onRgb = () => {
        const r = Math.max(0, Math.min(255, +rEl.value || 0));
        const g = Math.max(0, Math.min(255, +gEl.value || 0));
        const b = Math.max(0, Math.min(255, +bEl.value || 0));
        this._setBrushColor(rgbToHex(r, g, b));
      };
      [rEl, gEl, bEl].forEach((el) => {
        if (!el) return;
        el.addEventListener('change', onRgb);
        el.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') el.blur();
        });
      });
    }

    _applyPickerHsv(pushEngine) {
      const { h, s, v } = this._pickerHsv;
      const rgb = hsvToRgb(h, s, v);
      const hex = rgbToHex(rgb.r, rgb.g, rgb.b);
      this.brushColor = hex;
      this._paintPickerChrome();
      this._syncPickerFields();
      this._markActiveSwatches();
      if (pushEngine) {
        const eng = this.studio && this.studio.getEngine();
        if (eng) eng.setColor(hex);
      }
    }

    _paintPickerChrome() {
      const { h, s, v } = this._pickerHsv;
      const hueRgb = hsvToRgb(h, 1, 1);
      const hueHex = rgbToHex(hueRgb.r, hueRgb.g, hueRgb.b);
      const sv = this.el.querySelector('[data-sv]');
      const svCursor = this.el.querySelector('[data-sv-cursor]');
      const hueCursor = this.el.querySelector('[data-hue-cursor]');
      const preview = this.el.querySelector('[data-color-preview]');
      if (sv) {
        sv.style.background =
          'linear-gradient(to top, #000, transparent),'
          + 'linear-gradient(to right, #fff, ' + hueHex + ')';
      }
      if (svCursor) {
        svCursor.style.left = (s * 100).toFixed(2) + '%';
        svCursor.style.top = ((1 - v) * 100).toFixed(2) + '%';
      }
      if (hueCursor) {
        hueCursor.style.left = ((h / 360) * 100).toFixed(2) + '%';
      }
      if (preview) preview.style.background = this.brushColor;
    }

    _syncPickerFields() {
      const rgb = hexToRgb(this.brushColor) || { r: 100, g: 100, b: 100 };
      const hexEl = this.el.querySelector('[data-hex]');
      const rEl = this.el.querySelector('[data-rgb-r]');
      const gEl = this.el.querySelector('[data-rgb-g]');
      const bEl = this.el.querySelector('[data-rgb-b]');
      if (hexEl && document.activeElement !== hexEl) hexEl.value = this.brushColor;
      if (rEl && document.activeElement !== rEl) rEl.value = String(rgb.r);
      if (gEl && document.activeElement !== gEl) gEl.value = String(rgb.g);
      if (bEl && document.activeElement !== bEl) bEl.value = String(rgb.b);
    }

    async _refreshArtSwatches() {
      const block = this.el.querySelector('[data-art-block]');
      const wrap = this.el.querySelector('[data-art-swatches]');
      let img = this.studio && this.studio.artImage;
      if (!img && this.artUrl) {
        try {
          img = await new Promise((resolve, reject) => {
            const el = new Image();
            el.crossOrigin = 'anonymous';
            el.onload = () => resolve(el);
            el.onerror = reject;
            el.src = this.artUrl;
          });
        } catch (e) {
          img = null;
        }
      }
      if (!img) {
        const frontImg = this.front && this.front.querySelector('img');
        if (frontImg && frontImg.naturalWidth) img = frontImg;
      }
      this._artSwatches = extractPaletteFromImage(img, 8);
      this._fillSwatchWrap(wrap, this._artSwatches);
      if (block) block.hidden = this._artSwatches.length === 0;
    }

    _inkIcon(key) {
      const file = INK_ICONS[key] || 'pen.svg';
      return this.assetBase + '/assets/img/ink-tools/' + file;
    }

    _wireInkIconImgs(root) {
      (root || this.el).querySelectorAll('[data-ink-icon]').forEach((img) => {
        const key = img.getAttribute('data-ink-icon');
        img.src = this._inkIcon(key);
        img.decoding = 'async';
        img.draggable = false;
      });
    }

    _setBrushColor(color) {
      const rgb = hexToRgb(color);
      if (!rgb) return;
      const hex = rgbToHex(rgb.r, rgb.g, rgb.b);
      this.brushColor = hex;
      this._pickerHsv = rgbToHsv(rgb.r, rgb.g, rgb.b);
      this._paintPickerChrome();
      this._syncPickerFields();
      this._markActiveSwatches();
      const eng = this.studio && this.studio.getEngine();
      if (eng) eng.setColor(hex);
    }

    async _loadBrushes() {
      try {
        const res = await fetch(this.assetBase + '/assets/brushes/brushes.json');
        const data = await res.json();
        this.brushes = data.brushes || [];
      } catch (e) {
        this.brushes = [{ id: 'hard-round', name: 'Hard Round', tip: 'tip-hard-round.png', spacing: 0.12, sizePressure: true, flow: 1, baseSize: 6 }];
      }
      this._renderBrushGrid();
    }

    _renderBrushGrid() {
      const grid = this.el.querySelector('[data-brush-grid]');
      grid.innerHTML = '';
      this.brushes.forEach((b) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'cob-brush-card' + (b.id === this.activeBrushId ? ' active' : '');
        btn.innerHTML = `<img src="${this.assetBase}/assets/brushes/${b.tip}" alt=""><div>${b.name}</div>`;
        btn.addEventListener('click', () => this._selectBrush(b));
        grid.appendChild(btn);
      });
    }

    async _selectBrush(b) {
      this.activeBrushId = b.id;
      this._renderBrushGrid();
      const eng = this.studio && this.studio.getEngine();
      if (eng) {
        await eng.setBrushPreset(b, this.assetBase + '/assets/brushes/' + b.tip);
        if (b.baseSize) {
          eng.setSize(b.baseSize);
          const sizeEl = this.el.querySelector('[data-brush-size]');
          if (sizeEl) {
            sizeEl.value = String(Math.round(b.baseSize));
            this.el.querySelector('[data-size-val]').textContent = String(Math.round(b.baseSize));
          }
        }
        eng.setColor(this.brushColor);
        eng.setOpacity(this.brushOpacity);
      }
      this._closeSheets();
    }

    _iconBtn(id, title, tone, needsMenu, showLabel) {
      const t = tone || 'teal';
      const menu = needsMenu ? ' cob-tool-has-menu' : '';
      const label = (needsMenu || showLabel) ? ' cob-tool-show-label' : '';
      const src = this._inkIcon(id === 'draw' ? 'brush' : id);
      return `<button type="button" class="cob-tool-btn cob-tone-${t}${menu}${label}" data-dock="${id}" title="${title}" aria-label="${title}">`
        + `<img class="ico-img" src="${src}" alt="" draggable="false">`
        + `<span class="cob-tool-caption">${title}</span>`
        + `</button>`;
    }

    _renderChrome() {
      const draw = this.mode === 'draw';
      this.el.classList.toggle('is-draw-mode', draw);
      this.el.classList.toggle('is-viewer-mode', !draw);
      this.el.classList.toggle('is-flipped', this.flipped);

      // Icon chips in Card-o-Bot tones; captions only on menu / primary tools.
      if (draw) {
        this.railLeft.innerHTML = [
          this._iconBtn('brush', 'Ink', 'mint', false, true),
          this._iconBtn('eraser', 'Erase', 'pink', false, true),
          this._iconBtn('hand', 'Hand', 'beige', false, true),
          this._iconBtn('brushes', 'Tips', 'teal', true),
          this._iconBtn('inkcolor', 'Color', 'pink', true),
          this._iconBtn('hud', 'Size', 'mint', true),
          this._iconBtn('undo', 'Undo', 'beige'),
          this._iconBtn('redo', 'Redo', 'beige'),
          this._iconBtn('zoomout', 'Out', 'teal'),
          this._iconBtn('zoomin', 'In', 'teal'),
          this._iconBtn('resetzoom', 'Fit', 'mint'),
        ].join('');
        this.railRight.innerHTML = [
          this._iconBtn('layers', 'Layers', 'beige', true),
          this._iconBtn('tint', 'Tint', 'pink', true),
        ].join('');
        this.dock.innerHTML = '';
      } else {
        this.railLeft.innerHTML = '';
        this.railRight.innerHTML = '';
        this.dock.innerHTML = [
          this._iconBtn('flip', 'Flip', 'mint'),
          this._iconBtn('draw', 'Draw', 'teal', false, true),
          this._iconBtn('tint', 'Tint', 'pink', true),
          this._iconBtn('save', 'Save', 'beige'),
          this._iconBtn('download', 'Get', 'mint'),
        ].join('');
      }

      this.el.querySelectorAll('[data-dock]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          this._dock(btn.getAttribute('data-dock'));
        });
      });
      this._wireInkIconImgs(this.el);
      this._syncToolActive();
      if (!draw) {
        this.hud.classList.remove('open', 'pinned');
        this._closeSheets();
        this._closePopovers();
      }
    }

    _syncToolActive() {
      this.el.querySelectorAll('[data-dock]').forEach((btn) => {
        const id = btn.getAttribute('data-dock');
        const on = id === this.activeTool;
        btn.classList.toggle('active', on && (id === 'brush' || id === 'eraser' || id === 'hand'));
      });
      this.viewport.classList.toggle('is-panning', this.mode === 'draw' && this.activeTool === 'hand');
    }

    async open(payload) {
      const screen = document.querySelector('.console-screen');
      if (screen && this.el.parentNode !== screen) {
        screen.appendChild(this.el);
      }
      this.el.classList.toggle('cob-app-shell--fallback', !screen);

      this.sessionId = payload.sessionId || '';
      this.concept = payload.concept || {};
      this.stats = payload.stats || {};
      this.artUrl = payload.artUrl || '';
      this.mode = payload.mode === 'draw' ? 'draw' : 'viewer';
      this.flipped = false;
      this.scale = 1;
      this.pan = { x: 0, y: 0 };
      this.activeTool = 'brush';

      if (payload.backVariant != null) {
        const backs = (global.CardobotLayout && global.CardobotLayout.BACKS) || [];
        let v = +payload.backVariant || 0;
        if (backs.length) v = Math.max(0, Math.min(backs.length - 1, Math.round(v)));
        this.backVariant = v;
      }
      if (payload.backHsl) {
        this.backHue = +payload.backHsl.hue || this.backHue;
        this.backSat = +payload.backHsl.saturation || this.backSat;
        this.backLight = +payload.backHsl.lightness || this.backLight;
      }
      this._backHsl = { h: this.backHue, s: this.backSat, l: this.backLight };

      this.front.innerHTML = '';
      this.studio = null;
      if (payload.compositeUrl) {
        const img = document.createElement('img');
        img.src = payload.compositeUrl;
        img.alt = 'Saved card';
        img.style.cssText = 'width:100%;height:100%;object-fit:fill;display:block;';
        this.front.appendChild(img);
      } else {
        const host = document.createElement('div');
        this.front.appendChild(host);
        this.studio = new global.CardobotStudio({
          root: host,
          assetBase: this.assetBase,
          frameUrl: this.assetBase + '/assets/img/01_Card.png',
          bgUrl: this.assetBase + '/assets/img/01_CardBGtexture.png',
          hideTools: true,
        });
        await this.studio.setConcept(this.concept, this.stats);
        if (this.artUrl) {
          try {
            await this.studio.setArt(this.artUrl);
          } catch (e) {
            console.warn('Card art failed to load', e);
          }
        }
        if (payload.hsl && Number.isFinite(+payload.hsl.hue)) {
          this.studio.setHsl(+payload.hsl.hue, +payload.hsl.saturation, +payload.hsl.lightness, true);
          this.studio.lockUserTint(true);
        }
        if (payload.drawingData) {
          try {
            await this.studio.loadDrawingData(payload.drawingData);
          } catch (e) {
            console.warn('Drawing layers failed to load', e);
          }
        }
        this._wireEnginePan();
        this._syncColorSlidersFromStudio();
        this._syncCreditToggle();
        this._setBrushColor(this.brushColor);
        const eng = this.studio.getEngine();
        if (eng) eng.setOpacity(this.brushOpacity);
      }

      this._setBackVariant(this.backVariant);
      this._applyBackTint();
      this._applyTransform();
      this._refreshArtSwatches();

      this.el.classList.add('open');
      document.body.classList.add('cob-app-open');
      this._setMode(this.mode);
    }

    _wireEnginePan() {
      const eng = this.studio && this.studio.getEngine();
      if (!eng) return;
      eng.onPan = (dx, dy) => {
        this.pan.x += dx;
        this.pan.y += dy;
        this._applyTransform();
      };
    }

    close() {
      this.el.classList.remove('open');
      document.body.classList.remove('cob-app-open');
      this._closeSheets();
      this._closePopovers();
      const studioArt = this.studio && (this.studio.artUrl || (this.studio.artEl && this.studio.artEl.src));
      this.onClose({
        sessionId: this.sessionId,
        artUrl: this.artUrl || studioArt || '',
        drawing: this.studio ? this.studio.getDrawingData() : null,
        hsl: this.studio ? this.studio.getHsl() : null,
        backVariant: this.backVariant,
        backHsl: { hue: this.backHue, saturation: this.backSat, lightness: this.backLight },
      });
    }

    _setMode(mode) {
      this.mode = mode;
      this.titleEl.textContent = mode === 'draw' ? 'INK DECK' : 'CARD VIEWER';
      if (this.studio) {
        this.studio.setDrawingEnabled(mode === 'draw');
      }
      this.viewport.classList.toggle('is-drawing', mode === 'draw');
      if (mode === 'draw') {
        this.flipped = false;
        this.viewport.classList.remove('flipped');
        this.tilt = { x: 0, y: 0 };
        this._pointers.clear();
        this.activeTool = 'brush';
        const eng = this.studio && this.studio.getEngine();
        if (eng) eng.setTool('brush');
        const b = this.brushes.find((x) => x.id === this.activeBrushId) || this.brushes[0];
        if (b) this._selectBrush(b);
      }
      this._renderChrome();
      this._applyTransform();
      this._updateBackPickerVisibility();
    }

    _updateBackPickerVisibility() {
      this.backPicker.classList.toggle('open', this.mode === 'viewer' && this.flipped);
    }

    _action(act) {
      if (act === 'back') {
        this.close();
        return;
      }
      if (act === 'done') {
        if (this.mode === 'draw') this._setMode('viewer');
        else this.close();
        return;
      }
      if (act === 'close-sheet') {
        this._closeSheets();
        return;
      }
      if (act === 'close-popovers') {
        this._closePopovers();
        return;
      }
      if (act === 'close-hud') {
        this.hud.classList.remove('open', 'pinned');
        return;
      }
      if (act === 'new-layer') {
        const eng = this.studio && this.studio.getEngine();
        if (eng) { eng.addLayer(); this._renderLayers(); }
        return;
      }
      if (act === 'clear-layer') {
        const eng = this.studio && this.studio.getEngine();
        if (eng) eng.clearActive();
        return;
      }
      if (act === 'trash-layers') {
        const eng = this.studio && this.studio.getEngine();
        if (eng) { eng.deleteAllLayers(); this._renderLayers(); }
      }
    }

    async _dock(id) {
      const eng = this.studio && this.studio.getEngine();
      if (id === 'flip') {
        if (this.mode === 'draw') return;
        this.flipped = !this.flipped;
        this.viewport.classList.toggle('flipped', this.flipped);
        this.el.classList.toggle('is-flipped', this.flipped);
        this._updateBackPickerVisibility();
        return;
      }
      if (id === 'draw') {
        this._setMode('draw');
        return;
      }
      if (id === 'color' || id === 'tint') {
        this.hud.classList.remove('open', 'pinned');
        this._closePopovers();
        this._openSheet(this.sheetColor);
        return;
      }
      if (id === 'brushes') {
        this.hud.classList.remove('open', 'pinned');
        this._closePopovers();
        this._renderBrushGrid();
        this._openSheet(this.sheetBrushes);
        return;
      }
      if (id === 'layers') {
        this.hud.classList.remove('open', 'pinned');
        this._closePopovers();
        this._renderLayers();
        this._openSheet(this.sheetLayers);
        return;
      }
      if (id === 'inkcolor') {
        this.hud.classList.remove('open', 'pinned');
        this._closeSheets();
        const opening = !this.popoverColor.classList.contains('open');
        this.popoverColor.classList.toggle('open', opening);
        if (opening) {
          this._setBrushColor(this.brushColor);
          this._refreshArtSwatches();
        }
        return;
      }
      if (id === 'hud') {
        this._closeSheets();
        this._closePopovers();
        const on = !this.hud.classList.contains('open');
        this.hud.classList.toggle('pinned', on);
        this.hud.classList.toggle('open', on);
        return;
      }
      if (id === 'brush') {
        this.activeTool = 'brush';
        if (eng) eng.setTool('brush');
        this._syncToolActive();
        return;
      }
      if (id === 'eraser') {
        this.activeTool = 'eraser';
        if (eng) eng.setTool('eraser');
        this._syncToolActive();
        return;
      }
      if (id === 'hand') {
        this.activeTool = 'hand';
        if (eng) eng.setTool('hand');
        this._wireEnginePan();
        this._syncToolActive();
        this.viewport.classList.add('is-panning');
        return;
      }
      if (id === 'undo' && eng) { eng.undo(); return; }
      if (id === 'redo' && eng) { eng.redo(); return; }
      if (id === 'zoomin') { this.scale = Math.min(3.5, this.scale * 1.2); this._applyTransform(); return; }
      if (id === 'zoomout') { this.scale = Math.max(0.6, this.scale / 1.2); this._applyTransform(); return; }
      if (id === 'resetzoom') { this.scale = 1; this.pan = { x: 0, y: 0 }; this._applyTransform(); return; }
      if (id === 'save' && typeof this.onSave === 'function') {
        await this.onSave({ download: false, studio: this.studio, sessionId: this.sessionId, viewer: this });
        if (this.statusEl) this.statusEl.textContent = 'Saved';
        return;
      }
      if (id === 'download' && typeof this.onSave === 'function') {
        await this.onSave({ download: true, studio: this.studio, sessionId: this.sessionId, viewer: this });
      }
    }

    _openSheet(sheet) {
      if (!sheet) return;
      this._closeSheets();
      sheet.classList.add('open');
    }

    _closeSheets() {
      [this.sheetColor, this.sheetBrushes, this.sheetLayers].forEach((s) => {
        if (s) s.classList.remove('open');
      });
    }

    _closePopovers() {
      if (this.popoverColor) this.popoverColor.classList.remove('open');
    }

    _renderLayers() {
      const list = this.el.querySelector('[data-layer-list]');
      const eng = this.studio && this.studio.getEngine();
      list.innerHTML = '';
      if (!eng) return;
      // Top of stack first (Procreate / Photoshop feel).
      const layers = eng.getLayerList().slice().reverse();
      layers.forEach((l) => {
        const row = document.createElement('div');
        row.className = 'cob-layer-item' + (l.active ? ' is-active' : '') + (l.visible ? '' : ' is-hidden');
        const thumb = eng.getLayerThumb ? eng.getLayerThumb(l.index, 44) : '';
        row.innerHTML = `
          <button type="button" class="cob-layer-vis" title="${l.visible ? 'Hide' : 'Show'}" aria-label="${l.visible ? 'Hide layer' : 'Show layer'}">
            <img src="${this._inkIcon(l.visible ? 'eye' : 'eye-off')}" alt="" draggable="false">
          </button>
          <button type="button" class="cob-layer-main" title="Select ${l.name}">
            <span class="cob-layer-thumb">${thumb ? `<img src="${thumb}" alt="">` : ''}</span>
            <span class="cob-layer-meta">
              <span class="cob-layer-name">${l.name}</span>
              <span class="cob-layer-sub">${l.active ? 'Active' : 'Tap to edit'}</span>
            </span>
          </button>`;
        row.querySelector('.cob-layer-main').addEventListener('click', () => {
          eng.setActive(l.index);
          this._renderLayers();
        });
        row.querySelector('.cob-layer-vis').addEventListener('click', (e) => {
          e.stopPropagation();
          eng.toggleVisibility(l.index);
          this._renderLayers();
        });
        list.appendChild(row);
      });
    }

    _stashCurrentFaceSliders() {
      const h = +this.el.querySelector('[data-hue]').value;
      const s = +this.el.querySelector('[data-sat]').value;
      const l = +this.el.querySelector('[data-light]').value;
      if (this._colorFace === 'front') {
        this._frontHsl = { h, s, l };
      } else {
        this._backHsl = { h, s, l };
        this.backHue = h;
        this.backSat = s;
        this.backLight = l;
      }
    }

    _applySlidersFromFace(face) {
      const src = face === 'back' ? this._backHsl : this._frontHsl;
      this.el.querySelector('[data-hue]').value = String(src.h);
      this.el.querySelector('[data-sat]').value = String(src.s);
      this.el.querySelector('[data-light]').value = String(src.l);
    }

    _onFaceSelect() {
      this._stashCurrentFaceSliders();
      const face = this.el.querySelector('[data-color-face]').value;
      this._colorFace = face;
      this._applySlidersFromFace(face);
    }

    _syncColorSlidersFromStudio() {
      if (!this.studio) return;
      const hsl = this.studio.getHsl();
      this._frontHsl = { h: hsl.hue, s: hsl.saturation, l: hsl.lightness };
      this._colorFace = 'front';
      const face = this.el.querySelector('[data-color-face]');
      if (face) face.value = 'front';
      this._applySlidersFromFace('front');
    }

    _syncCreditToggle() {
      const el = this.el.querySelector('[data-show-credit]');
      if (!el) return;
      const on = this.studio
        ? this.studio.getShowCredit()
        : (this.concept && this.concept.show_credit !== false);
      el.checked = !!on;
    }

    _onCreditToggle() {
      const el = this.el.querySelector('[data-show-credit]');
      if (!el) return;
      const on = !!el.checked;
      if (this.studio) this.studio.setShowCredit(on);
      if (this.concept) this.concept.show_credit = on;
      if (typeof this.onCreditChange === 'function') this.onCreditChange(on);
    }

    _onColorInput() {
      const face = this.el.querySelector('[data-color-face]').value;
      this._colorFace = face;
      const h = +this.el.querySelector('[data-hue]').value;
      const s = +this.el.querySelector('[data-sat]').value;
      const l = +this.el.querySelector('[data-light]').value;
      if (face === 'front' && this.studio) {
        this._frontHsl = { h, s, l };
        this.studio.setHsl(h, s, l, true);
      } else {
        this._backHsl = { h, s, l };
        this.backHue = h;
        this.backSat = s;
        this.backLight = l;
        this._applyBackTint();
      }
    }

    _applyBackTint() {
      this.backTint.style.background = `hsl(${this.backHue} ${this.backSat}% ${this.backLight}%)`;
    }

    _applyTransform() {
      const tiltX = this._reduced || this.mode === 'draw' ? 0 : this.tilt.x;
      const tiltY = this._reduced || this.mode === 'draw' ? 0 : this.tilt.y;
      this.viewport.style.transform =
        `translate(${this.pan.x}px, ${this.pan.y}px) scale(${this.scale}) rotateX(${tiltX}deg) rotateY(${tiltY}deg)`;
    }

    _isUiChrome(target) {
      if (!target || !target.closest) return false;
      return !!(
        target.closest('.cob-rail')
        || target.closest('.cob-dock')
        || target.closest('.cob-hud')
        || target.closest('.cob-popover')
        || target.closest('.cob-sheet')
        || target.closest('.cob-panel')
        || target.closest('.cob-app-bar')
        || target.closest('.cob-back-picker')
        || target.closest('[data-dock]')
        || target.closest('[data-act]')
      );
    }

    _bindGestures() {
      let dragging = false;
      let last = null;
      let pinchStart = null;
      const stage = this.el.querySelector('.cob-stage');

      // Capture phase so pinch works over ink; never steal tool/panel clicks.
      const onDown = (e) => {
        if (this._isUiChrome(e.target)) return;
        this._pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        const onInk = !!(e.target.closest && e.target.closest('.drawing-stage'));
        const panMode = this.activeTool === 'hand' || this._spaceDown;
        // Ink / hand-on-ink: drawing engine owns the pointer (paint or pan).
        if (this.mode === 'draw' && onInk) {
          if (this._pointers.size >= 2) {
            const eng = this.studio && this.studio.getEngine();
            if (eng) eng.drawing = false;
          } else {
            return;
          }
        }
        if (this.mode === 'draw' && !panMode && !onInk) {
          // Empty stage chrome around the card: ignore single-finger draw-mode drag.
          if (this._pointers.size < 2) return;
        }
        try { this.viewport.setPointerCapture(e.pointerId); } catch (_) { /* */ }
        if (this._pointers.size === 1) {
          dragging = true;
          last = { x: e.clientX, y: e.clientY };
        }
      };

      const onMove = (e) => {
        if (!this._pointers.has(e.pointerId)) return;
        this._pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

        if (this._pointers.size === 2) {
          const eng = this.studio && this.studio.getEngine();
          if (eng) eng.drawing = false;
          const pts = [...this._pointers.values()];
          const dist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
          const mid = { x: (pts[0].x + pts[1].x) / 2, y: (pts[0].y + pts[1].y) / 2 };
          if (!pinchStart) pinchStart = { dist, mid, scale: this.scale, pan: { ...this.pan } };
          else if (pinchStart.dist > 0) {
            this.scale = Math.min(3.5, Math.max(0.6, pinchStart.scale * (dist / pinchStart.dist)));
            this.pan.x = pinchStart.pan.x + (mid.x - pinchStart.mid.x);
            this.pan.y = pinchStart.pan.y + (mid.y - pinchStart.mid.y);
            this._applyTransform();
          }
          return;
        }

        if (!dragging || !last) return;
        const dx = e.clientX - last.x;
        const dy = e.clientY - last.y;
        last = { x: e.clientX, y: e.clientY };
        // Pan only with hand tool or space-hold (not every draw-mode drag).
        if (this.activeTool === 'hand' || this._spaceDown) {
          this.pan.x += dx;
          this.pan.y += dy;
          this._applyTransform();
          return;
        }
        if (this.mode !== 'draw' && !this._reduced) {
          this.tilt.y = Math.max(-12, Math.min(12, this.tilt.y + dx * 0.08));
          this.tilt.x = Math.max(-10, Math.min(10, this.tilt.x - dy * 0.08));
          this._applyTransform();
        }
      };

      const end = (e) => {
        this._pointers.delete(e.pointerId);
        if (this._pointers.size < 2) pinchStart = null;
        if (this._pointers.size === 0) {
          dragging = false;
          last = null;
          if (this.mode !== 'draw' && !this._reduced) {
            this.tilt = { x: 0, y: 0 };
            this._applyTransform();
          }
        }
      };

      stage.addEventListener('pointerdown', onDown, true);
      stage.addEventListener('pointermove', onMove, true);
      stage.addEventListener('pointerup', end, true);
      stage.addEventListener('pointercancel', end, true);

      window.addEventListener('keydown', (e) => {
        if (!this.el.classList.contains('open')) return;
        const mod = e.ctrlKey || e.metaKey;
        const eng = this.studio && this.studio.getEngine();

        if (e.code === 'Space') {
          if (this.mode === 'draw') {
            e.preventDefault();
            this._spaceDown = true;
            if (eng) eng.setSpacePan(true);
            return;
          }
          e.preventDefault();
          this._dock('flip');
          return;
        }
        if (e.code === 'Enter' && this.mode !== 'draw') {
          e.preventDefault();
          this._dock('flip');
          return;
        }
        if (e.key === 'Escape') {
          if (this.sheetColor.classList.contains('open')
            || this.sheetBrushes.classList.contains('open')
            || this.sheetLayers.classList.contains('open')
            || this.popoverColor.classList.contains('open')) {
            this._closeSheets();
            this._closePopovers();
            return;
          }
          this.close();
          return;
        }
        if (mod && (e.key === 'z' || e.key === 'Z')) {
          if (!eng || this.mode !== 'draw') return;
          e.preventDefault();
          if (e.shiftKey) eng.redo();
          else eng.undo();
          return;
        }
        if (mod && (e.key === 'y' || e.key === 'Y')) {
          if (!eng || this.mode !== 'draw') return;
          e.preventDefault();
          eng.redo();
          return;
        }
        if (this.mode === 'draw' && (e.key === '[' || e.key === ']')) {
          if (!eng) return;
          e.preventDefault();
          const delta = e.key === ']' ? 2 : -2;
          const next = Math.max(1, Math.min(48, eng.size + delta));
          eng.setSize(next);
          const sizeEl = this.el.querySelector('[data-brush-size]');
          if (sizeEl) {
            sizeEl.value = String(Math.round(next));
            this.el.querySelector('[data-size-val]').textContent = String(Math.round(next));
          }
        }
      });

      window.addEventListener('keyup', (e) => {
        if (!this.el.classList.contains('open')) return;
        if (e.code === 'Space') {
          this._spaceDown = false;
          const eng = this.studio && this.studio.getEngine();
          if (eng) eng.setSpacePan(false);
        }
      });
    }

    getExtras() {
      const showCredit = this.studio
        ? this.studio.getShowCredit()
        : (this.concept ? this.concept.show_credit !== false : true);
      return {
        back_variant: this.backVariant,
        back_hue: this.backHue,
        back_saturation: this.backSat,
        back_lightness: this.backLight,
        show_credit: !!showCredit,
      };
    }
  }

  global.CardobotViewer = CardViewer;
})(window);
