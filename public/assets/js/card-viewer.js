/**
 * Card Viewer + Ink Deck apps for Card-o-Bot.
 */
(function (global) {
  'use strict';

  class CardViewer {
    constructor(opts) {
      this.assetBase = opts.assetBase || '';
      this.apiBase = opts.apiBase || '';
      this.onClose = opts.onClose || function () {};
      this.onCreditChange = opts.onCreditChange || null;
      this.onSave = opts.onSave || null;
      this.mode = 'viewer'; // viewer | draw
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
      this.brushes = [];
      this.activeBrushId = 'hard-round';
      this.studio = null;
      this._pointers = new Map();
      this._reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
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
          <div class="cob-card-viewport" data-viewport>
            <div class="cob-card-inner" data-inner>
              <div class="cob-face cob-face-front" data-front></div>
              <div class="cob-face cob-face-back" data-back>
                <div class="cob-back-tint" data-back-tint></div>
                <img class="cob-back-art" data-back-art alt="Card back">
                <div class="cob-back-plate"><strong data-back-name></strong><span data-back-type></span></div>
              </div>
            </div>
          </div>
          <div class="cob-sheet" data-sheet-color>
            <h3>Color</h3>
            <label>Face
              <select data-color-face>
                <option value="front">Front tint</option>
                <option value="back">Back tint</option>
              </select>
            </label>
            <label>Hue <input type="range" min="0" max="360" value="195" data-hue></label>
            <label>Sat <input type="range" min="0" max="100" value="65" data-sat></label>
            <label>Light <input type="range" min="0" max="100" value="40" data-light></label>
            <label data-back-style-wrap>Back style
              <select data-back-style></select>
            </label>
            <label class="cob-check">
              <input type="checkbox" data-show-credit checked>
              Show username on card
            </label>
            <button type="button" class="cob-app-btn" data-act="close-sheet">Close</button>
          </div>
          <div class="cob-sheet" data-sheet-brushes>
            <h3>Brushes</h3>
            <div class="cob-brush-grid" data-brush-grid></div>
            <button type="button" class="cob-app-btn" data-act="close-sheet">Close</button>
          </div>
          <div class="cob-sheet" data-sheet-layers>
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
      // Stay inside the physical console screen, not a separate phone chrome.
      const screen = document.querySelector('.console-screen');
      (screen || document.body).appendChild(this.el);
      if (!screen) this.el.classList.add('cob-app-shell--fallback');

      this.viewport = this.el.querySelector('[data-viewport]');
      this.inner = this.el.querySelector('[data-inner]');
      this.front = this.el.querySelector('[data-front]');
      this.backArt = this.el.querySelector('[data-back-art]');
      this.backTint = this.el.querySelector('[data-back-tint]');
      this.titleEl = this.el.querySelector('[data-title]');
      this.statusEl = this.el.querySelector('[data-status]');
      this.dock = this.el.querySelector('[data-dock]');
      this.sheetColor = this.el.querySelector('[data-sheet-color]');
      this.sheetBrushes = this.el.querySelector('[data-sheet-brushes]');
      this.sheetLayers = this.el.querySelector('[data-sheet-layers]');

      const backs = (global.CardobotLayout && global.CardobotLayout.BACKS) || [];
      const sel = this.el.querySelector('[data-back-style]');
      backs.forEach((b, i) => {
        const opt = document.createElement('option');
        opt.value = String(i);
        opt.textContent = 'Back ' + (i + 1);
        sel.appendChild(opt);
      });

      this.el.addEventListener('click', (e) => {
        const act = e.target.closest('[data-act]');
        if (!act) return;
        this._action(act.getAttribute('data-act'));
      });

      this.el.querySelectorAll('[data-hue],[data-sat],[data-light],[data-color-face],[data-back-style]').forEach((el) => {
        el.addEventListener('input', () => this._onColorInput());
        el.addEventListener('change', () => this._onColorInput());
      });
      const creditToggle = this.el.querySelector('[data-show-credit]');
      if (creditToggle) {
        creditToggle.addEventListener('change', () => this._onCreditToggle());
      }

      this._bindGestures();
      this._renderDock();
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
        if (b.baseSize) eng.setSize(b.baseSize);
      }
      if (this.statusEl) this.statusEl.textContent = b.name;
      this._closeSheets();
    }

    _renderDock() {
      const draw = this.mode === 'draw';
      // Short labels: Press Start 2P is wide and must fit the console dock.
      const items = draw
        ? [
            ['brush', 'Ink', '✎'],
            ['eraser', 'Erase', '⌫'],
            ['brushes', 'Tips', '◇'],
            ['size', 'Size', '◎'],
            ['undo', 'Undo', '↶'],
            ['redo', 'Redo', '↷'],
            ['layers', 'Layer', '☰'],
            ['color', 'Tint', '◐'],
            ['zoomout', 'Out', '−'],
            ['zoomin', 'In', '+'],
            ['resetzoom', '1:1', '⤢'],
          ]
        : [
            ['flip', 'Flip', '↻'],
            ['draw', 'Draw', '✎'],
            ['color', 'Tint', '◐'],
            ['save', 'Save', '↓'],
            ['download', 'Get', '⇪'],
          ];
      this.dock.innerHTML = items.map(([id, label, ico]) =>
        `<button type="button" class="cob-dock-btn" data-dock="${id}"><span class="ico">${ico}</span>${label}</button>`
      ).join('');
      this.dock.querySelectorAll('[data-dock]').forEach((btn) => {
        btn.addEventListener('click', () => this._dock(btn.getAttribute('data-dock')));
      });
    }

    async open(payload) {
      // Always host inside the device screen (never a separate fullscreen phone shell).
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
        this.studio.setConcept(this.concept, this.stats);
        if (this.artUrl) await this.studio.setArt(this.artUrl);
        this._syncColorSlidersFromStudio();
        this._syncCreditToggle();
      }

      const backs = (global.CardobotLayout && global.CardobotLayout.BACKS) || [];
      if (backs.length) {
        this.backArt.src = this.assetBase + '/assets/img/cardbacks/' + backs[this.backVariant % backs.length];
      }
      this.el.querySelector('[data-back-name]').textContent = this.concept.nickname || this.concept.subject || 'Card';
      this.el.querySelector('[data-back-type]').textContent = (this.concept.type || 'BOT') + ' · Card-o-Bot';
      this._applyBackTint();
      this._applyTransform();

      this.el.classList.add('open');
      document.body.classList.add('cob-app-open');
      this._setMode(this.mode);
    }

    close() {
      this.el.classList.remove('open');
      document.body.classList.remove('cob-app-open');
      this._closeSheets();
      this.onClose({
        sessionId: this.sessionId,
        drawing: this.studio ? this.studio.getDrawingData() : null,
        hsl: this.studio ? this.studio.getHsl() : null,
      });
    }

    _setMode(mode) {
      this.mode = mode;
      this.titleEl.textContent = mode === 'draw' ? 'INK DECK' : 'CARD VIEWER';
      this._renderDock();
      if (this.studio) {
        this.studio.setDrawingEnabled(mode === 'draw');
      }
      this.viewport.classList.toggle('is-drawing', mode === 'draw');
      if (mode === 'draw') {
        this.flipped = false;
        this.viewport.classList.remove('flipped');
        this.tilt = { x: 0, y: 0 };
        this._pointers.clear();
        this.statusEl.textContent = '';
        const b = this.brushes.find((x) => x.id === this.activeBrushId) || this.brushes[0];
        if (b) this._selectBrush(b);
      } else {
        this.statusEl.textContent = '';
      }
      this._applyTransform();
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
        return;
      }
      if (id === 'draw') {
        this._setMode('draw');
        return;
      }
      if (id === 'color') {
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
      if (id === 'brush' && eng) {
        eng.setTool('brush');
        this.statusEl.textContent = 'Brush';
        return;
      }
      if (id === 'eraser' && eng) {
        eng.setTool('eraser');
        this.statusEl.textContent = 'Eraser';
        return;
      }
      if (id === 'undo' && eng) { eng.undo(); return; }
      if (id === 'redo' && eng) { eng.redo(); return; }
      if (id === 'zoomin') { this.scale = Math.min(3.5, this.scale * 1.2); this._applyTransform(); return; }
      if (id === 'zoomout') { this.scale = Math.max(0.6, this.scale / 1.2); this._applyTransform(); return; }
      if (id === 'resetzoom') { this.scale = 1; this.pan = { x: 0, y: 0 }; this._applyTransform(); return; }
      if (id === 'size' && eng) {
        const next = eng.size >= 28 ? 4 : eng.size + 4;
        eng.setSize(next);
        this.statusEl.textContent = 'Size ' + Math.round(next);
        return;
      }
      if (id === 'save' && typeof this.onSave === 'function') {
        await this.onSave({ download: false, studio: this.studio, sessionId: this.sessionId, viewer: this });
        this.statusEl.textContent = 'Saved to collection';
        return;
      }
      if (id === 'download' && typeof this.onSave === 'function') {
        await this.onSave({ download: true, studio: this.studio, sessionId: this.sessionId, viewer: this });
        this.statusEl.textContent = 'Download ready';
      }
    }

    _openSheet(sheet) {
      this._closeSheets();
      sheet.classList.add('open');
    }

    _closeSheets() {
      [this.sheetColor, this.sheetBrushes, this.sheetLayers].forEach((s) => s.classList.remove('open'));
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

    _syncColorSlidersFromStudio() {
      if (!this.studio) return;
      const hsl = this.studio.getHsl();
      const hue = this.el.querySelector('[data-hue]');
      const sat = this.el.querySelector('[data-sat]');
      const light = this.el.querySelector('[data-light]');
      const face = this.el.querySelector('[data-color-face]');
      if (face) face.value = 'front';
      if (hue) hue.value = String(hsl.hue);
      if (sat) sat.value = String(hsl.saturation);
      if (light) light.value = String(hsl.lightness);
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
      const h = +this.el.querySelector('[data-hue]').value;
      const s = +this.el.querySelector('[data-sat]').value;
      const l = +this.el.querySelector('[data-light]').value;
      if (face === 'front' && this.studio) {
        this.studio.setHsl(h, s, l, true);
      } else {
        this.backHue = h;
        this.backSat = s;
        this.backLight = l;
        this._applyBackTint();
      }
      const style = this.el.querySelector('[data-back-style]');
      if (style) {
        this.backVariant = +style.value || 0;
        const backs = (global.CardobotLayout && global.CardobotLayout.BACKS) || [];
        if (backs.length) {
          this.backArt.src = this.assetBase + '/assets/img/cardbacks/' + backs[this.backVariant % backs.length];
        }
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

      this.viewport.addEventListener('pointerdown', (e) => {
        // Ink deck owns all pointers; never capture or tilt/grab here.
        if (this.mode === 'draw') return;
        this.viewport.setPointerCapture(e.pointerId);
        this._pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        if (this._pointers.size === 1) {
          dragging = true;
          last = { x: e.clientX, y: e.clientY };
        }
      });

      this.viewport.addEventListener('pointermove', (e) => {
        if (this.mode === 'draw') return;
        if (!this._pointers.has(e.pointerId)) return;
        this._pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

        if (this._pointers.size === 2) {
          const pts = [...this._pointers.values()];
          const dist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
          const mid = { x: (pts[0].x + pts[1].x) / 2, y: (pts[0].y + pts[1].y) / 2 };
          if (!pinchStart) pinchStart = { dist, mid, scale: this.scale, pan: { ...this.pan } };
          else {
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
        if (!this._reduced) {
          this.tilt.y = Math.max(-12, Math.min(12, this.tilt.y + dx * 0.08));
          this.tilt.x = Math.max(-10, Math.min(10, this.tilt.x - dy * 0.08));
          this._applyTransform();
        }
      });

      const end = (e) => {
        if (this.mode === 'draw') {
          this._pointers.clear();
          dragging = false;
          last = null;
          pinchStart = null;
          return;
        }
        this._pointers.delete(e.pointerId);
        if (this._pointers.size < 2) pinchStart = null;
        if (this._pointers.size === 0) {
          dragging = false;
          last = null;
          if (!this._reduced) {
            this.tilt = { x: 0, y: 0 };
            this._applyTransform();
          }
        }
      };
      this.viewport.addEventListener('pointerup', end);
      this.viewport.addEventListener('pointercancel', end);

      this.viewport.addEventListener('click', (e) => {
        if (this.mode === 'draw') return;
        if (Math.abs(this.tilt.x) + Math.abs(this.tilt.y) > 2) return;
        // ignore if came from dock
        if (e.target.closest('.cob-dock')) return;
      });

      window.addEventListener('keydown', (e) => {
        if (!this.el.classList.contains('open')) return;
        if (e.code === 'Space' || e.code === 'Enter') {
          if (this.mode !== 'draw') {
            e.preventDefault();
            this._dock('flip');
          }
        }
        if (e.key === 'Escape') this.close();
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
