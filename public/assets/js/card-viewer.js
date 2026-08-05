/**
 * Card Viewer + Ink Deck apps for Card-o-Bot.
 */
(function (global) {
  'use strict';

  const SWATCHES = [
    '#222222', '#646464', '#ffffff', '#e07e8c', '#5ed2f0',
    '#95f5e3', '#f9bbaa', '#ffe5c0', '#7a5cff', '#3d8b40',
  ];

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
          <div class="cob-card-viewport" data-viewport>
            <div class="cob-card-inner" data-inner>
              <div class="cob-face cob-face-front" data-front></div>
              <div class="cob-face cob-face-back" data-back>
                <div class="cob-back-tint" data-back-tint></div>
                <img class="cob-back-art" data-back-art alt="Card back">
                <div class="cob-back-picker" data-back-picker></div>
              </div>
            </div>
          </div>
          <div class="cob-hud" data-hud>
            <label>Size <input type="range" min="1" max="48" value="8" data-brush-size><span data-size-val>8</span></label>
            <label>Opacity <input type="range" min="5" max="100" value="100" data-brush-opacity><span data-opacity-val>100%</span></label>
          </div>
          <div class="cob-popover cob-popover-color" data-popover-color>
            <div class="cob-swatches" data-swatches></div>
            <label class="cob-color-wheel-wrap">
              <input type="color" value="#646464" data-brush-color>
            </label>
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
            <h3>Tips</h3>
            <div class="cob-brush-grid" data-brush-grid></div>
            <button type="button" class="cob-app-btn" data-act="close-sheet">Close</button>
          </div>
          <div class="cob-sheet cob-panel" data-sheet-layers>
            <h3>Layers</h3>
            <div data-layer-list></div>
            <div class="cob-layer-row">
              <button type="button" class="cob-app-btn" data-act="new-layer">New</button>
              <button type="button" class="cob-app-btn" data-act="clear-layer">Clear</button>
              <button type="button" class="cob-app-btn" data-act="trash-layers">Trash</button>
            </div>
            <button type="button" class="cob-app-btn" data-act="close-sheet">Close</button>
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
      const brushColorEl = this.el.querySelector('[data-brush-color]');
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
      if (brushColorEl) {
        brushColorEl.addEventListener('input', () => {
          this._setBrushColor(brushColorEl.value);
        });
      }

      this._bindGestures();
      this._renderChrome();
    }

    _renderBackPicker() {
      const backs = (global.CardobotLayout && global.CardobotLayout.BACKS) || [];
      this.backPicker.innerHTML = backs.map((file, i) =>
        `<button type="button" class="cob-back-thumb${i === this.backVariant ? ' active' : ''}" data-back-idx="${i}" title="Back ${i + 1}">
          <img src="${this.assetBase}/assets/img/cardbacks/${file}" alt="">
        </button>`
      ).join('');
      this.backPicker.querySelectorAll('[data-back-idx]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          this._setBackVariant(+btn.getAttribute('data-back-idx'));
        });
      });
    }

    _setBackVariant(idx) {
      const backs = (global.CardobotLayout && global.CardobotLayout.BACKS) || [];
      if (!backs.length) return;
      this.backVariant = ((idx % backs.length) + backs.length) % backs.length;
      this.backArt.src = this.assetBase + '/assets/img/cardbacks/' + backs[this.backVariant];
      this.backPicker.querySelectorAll('.cob-back-thumb').forEach((el, i) => {
        el.classList.toggle('active', i === this.backVariant);
      });
    }

    _renderSwatches() {
      const wrap = this.el.querySelector('[data-swatches]');
      wrap.innerHTML = SWATCHES.map((c) =>
        `<button type="button" class="cob-swatch" data-swatch="${c}" style="background:${c}" aria-label="${c}"></button>`
      ).join('');
      wrap.querySelectorAll('[data-swatch]').forEach((btn) => {
        btn.addEventListener('click', () => this._setBrushColor(btn.getAttribute('data-swatch')));
      });
    }

    _setBrushColor(color) {
      this.brushColor = color;
      const input = this.el.querySelector('[data-brush-color]');
      if (input) input.value = color;
      const eng = this.studio && this.studio.getEngine();
      if (eng) eng.setColor(color);
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

    _iconBtn(id, ico, title) {
      return `<button type="button" class="cob-tool-btn" data-dock="${id}" title="${title}" aria-label="${title}"><span class="ico">${ico}</span></button>`;
    }

    _renderChrome() {
      const draw = this.mode === 'draw';
      this.el.classList.toggle('is-draw-mode', draw);
      this.el.classList.toggle('is-viewer-mode', !draw);
      this.el.classList.toggle('is-flipped', this.flipped);

      if (draw) {
        this.railLeft.innerHTML = [
          this._iconBtn('brush', '✎', 'Brush'),
          this._iconBtn('eraser', '⌫', 'Eraser'),
          this._iconBtn('hand', '✋', 'Hand'),
          this._iconBtn('brushes', '◇', 'Tips'),
          this._iconBtn('inkcolor', '◉', 'Ink color'),
          this._iconBtn('hud', '◎', 'Size / opacity'),
          this._iconBtn('undo', '↶', 'Undo'),
          this._iconBtn('redo', '↷', 'Redo'),
          this._iconBtn('zoomout', '−', 'Zoom out'),
          this._iconBtn('zoomin', '+', 'Zoom in'),
          this._iconBtn('resetzoom', '⤢', 'Fit'),
        ].join('');
        this.railRight.innerHTML = [
          this._iconBtn('layers', '☰', 'Layers'),
          this._iconBtn('tint', '◐', 'Card tint'),
        ].join('');
        this.dock.innerHTML = '';
      } else {
        this.railLeft.innerHTML = '';
        this.railRight.innerHTML = '';
        this.dock.innerHTML = [
          this._iconBtn('flip', '↻', 'Flip'),
          this._iconBtn('draw', '✎', 'Draw'),
          this._iconBtn('tint', '◐', 'Card tint'),
          this._iconBtn('save', '↓', 'Save'),
          this._iconBtn('download', '⇪', 'Download'),
        ].join('');
      }

      this.el.querySelectorAll('[data-dock]').forEach((btn) => {
        btn.addEventListener('click', () => this._dock(btn.getAttribute('data-dock')));
      });
      this._syncToolActive();
      this.hud.classList.toggle('open', draw && this.hud.classList.contains('pinned'));
    }

    _syncToolActive() {
      this.el.querySelectorAll('[data-dock]').forEach((btn) => {
        const id = btn.getAttribute('data-dock');
        const on = (id === this.activeTool)
          || (id === 'brush' && this.activeTool === 'brush')
          || (id === 'eraser' && this.activeTool === 'eraser')
          || (id === 'hand' && this.activeTool === 'hand');
        btn.classList.toggle('active', on && (id === 'brush' || id === 'eraser' || id === 'hand'));
      });
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

      if (payload.backVariant != null) this.backVariant = +payload.backVariant || 0;
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
        if (this.artUrl) await this.studio.setArt(this.artUrl);
        if (payload.hsl && Number.isFinite(+payload.hsl.hue)) {
          this.studio.setHsl(+payload.hsl.hue, +payload.hsl.saturation, +payload.hsl.lightness, true);
          this.studio.lockUserTint(true);
        }
        if (payload.drawingData) {
          await this.studio.loadDrawingData(payload.drawingData);
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
      this.onClose({
        sessionId: this.sessionId,
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
        this._openSheet(this.sheetColor);
        return;
      }
      if (id === 'brushes') {
        this._openSheet(this.sheetBrushes);
        return;
      }
      if (id === 'layers') {
        this._renderLayers();
        this._openSheet(this.sheetLayers);
        return;
      }
      if (id === 'inkcolor') {
        this.popoverColor.classList.toggle('open');
        return;
      }
      if (id === 'hud') {
        this.hud.classList.toggle('pinned');
        this.hud.classList.toggle('open', this.hud.classList.contains('pinned'));
        return;
      }
      if (id === 'brush' && eng) {
        this.activeTool = 'brush';
        eng.setTool('brush');
        this._syncToolActive();
        return;
      }
      if (id === 'eraser' && eng) {
        this.activeTool = 'eraser';
        eng.setTool('eraser');
        this._syncToolActive();
        return;
      }
      if (id === 'hand' && eng) {
        this.activeTool = 'hand';
        eng.setTool('hand');
        this._syncToolActive();
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
      this._closeSheets();
      this._closePopovers();
      sheet.classList.add('open');
    }

    _closeSheets() {
      [this.sheetColor, this.sheetBrushes, this.sheetLayers].forEach((s) => s.classList.remove('open'));
    }

    _closePopovers() {
      this.popoverColor.classList.remove('open');
    }

    _renderLayers() {
      const list = this.el.querySelector('[data-layer-list]');
      const eng = this.studio && this.studio.getEngine();
      list.innerHTML = '';
      if (!eng) return;
      eng.getLayerList().forEach((l) => {
        const row = document.createElement('div');
        row.className = 'cob-layer-row';
        row.innerHTML = `<button type="button" class="cob-app-btn">${l.name}${l.active ? ' •' : ''}</button>
          <button type="button" class="cob-app-btn">${l.visible ? 'Hide' : 'Show'}</button>`;
        row.children[0].addEventListener('click', () => { eng.setActive(l.index); this._renderLayers(); });
        row.children[1].addEventListener('click', () => { eng.toggleVisibility(l.index); this._renderLayers(); });
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

    _bindGestures() {
      let dragging = false;
      let last = null;
      let pinchStart = null;
      const stage = this.el.querySelector('.cob-stage');

      // Capture phase so pinch works even when the drawing stage stops propagation.
      const onDown = (e) => {
        this._pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        const onInk = !!(e.target.closest && e.target.closest('.drawing-stage'));
        const panMode = this.activeTool === 'hand' || this._spaceDown;
        if (this.mode === 'draw' && onInk && !panMode) {
          // Let the engine paint; still track pointers for pinch.
          if (this._pointers.size >= 2) {
            const eng = this.studio && this.studio.getEngine();
            if (eng) eng.drawing = false;
          }
          return;
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
        if (this.mode === 'draw' || this.activeTool === 'hand' || this._spaceDown) {
          this.pan.x += dx;
          this.pan.y += dy;
          this._applyTransform();
          return;
        }
        if (!this._reduced) {
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
