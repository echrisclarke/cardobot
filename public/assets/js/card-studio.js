/**
 * Card studio: framed composite + optional drawing + export/save/download.
 */
(function (global) {
  'use strict';

  const CARD_W = 606;
  const CARD_H = 800;
  const ART_X = 48;
  const ART_Y = 90;
  const ART_W = 510;
  const ART_H = 510;

  class CardStudio {
    constructor(opts) {
      this.root = opts.root;
      this.assetBase = opts.assetBase || '';
      this.frameUrl = opts.frameUrl;
      this.bgUrl = opts.bgUrl;
      this.onStatus = opts.onStatus || function () {};
      this.engine = null;
      this.artImage = null;
      this.concept = {};
      this.hue = 195;
      this.saturation = 65;
      this.lightness = 40;
      this._buildDom();
    }

    _buildDom() {
      this.root.innerHTML = '';
      this.root.classList.add('card-studio');

      this.card = document.createElement('div');
      this.card.className = 'studio-card';
      this.card.id = 'studioCardRoot';

      this.colorFill = document.createElement('div');
      this.colorFill.className = 'studio-card-color';
      this.bgImg = document.createElement('img');
      this.bgImg.className = 'studio-card-bg';
      this.bgImg.alt = '';
      this.bgImg.src = this.bgUrl;

      this.artWrap = document.createElement('div');
      this.artWrap.className = 'studio-art-wrap';
      this.artEl = document.createElement('img');
      this.artEl.className = 'studio-art';
      this.artEl.alt = 'Card art';
      this.drawHost = document.createElement('div');
      this.drawHost.className = 'studio-draw-host';
      this.artWrap.appendChild(this.artEl);
      this.artWrap.appendChild(this.drawHost);

      this.frameImg = document.createElement('img');
      this.frameImg.className = 'studio-frame';
      this.frameImg.alt = 'Card frame';
      this.frameImg.src = this.frameUrl;

      this.textLayer = document.createElement('div');
      this.textLayer.className = 'studio-text';
      this.nickEl = document.createElement('div');
      this.nickEl.className = 'studio-nickname';
      this.bioEl = document.createElement('div');
      this.bioEl.className = 'studio-bio';
      this.powerEl = document.createElement('div');
      this.powerEl.className = 'studio-power';
      this.abilityEl = document.createElement('div');
      this.abilityEl.className = 'studio-ability';
      this.textLayer.appendChild(this.nickEl);
      this.textLayer.appendChild(this.bioEl);
      this.textLayer.appendChild(this.powerEl);
      this.textLayer.appendChild(this.abilityEl);

      this.card.appendChild(this.colorFill);
      this.card.appendChild(this.bgImg);
      this.card.appendChild(this.artWrap);
      this.card.appendChild(this.frameImg);
      this.card.appendChild(this.textLayer);
      this.root.appendChild(this.card);

      this.tools = document.createElement('div');
      this.tools.className = 'studio-tools';
      this.tools.innerHTML = `
        <div class="studio-tool-row">
          <button type="button" data-tool="brush" class="studio-btn active">Brush</button>
          <button type="button" data-tool="eraser" class="studio-btn">Eraser</button>
          <label class="studio-label">Color <input type="color" id="studioColor" value="#646464"></label>
          <label class="studio-label">Size <input type="range" id="studioSize" min="1" max="40" value="4" step="0.5"></label>
        </div>
        <div class="studio-tool-row">
          <button type="button" id="studioNewLayer" class="studio-btn">New layer</button>
          <button type="button" id="studioClearLayer" class="studio-btn">Clear layer</button>
          <button type="button" id="studioTrashLayers" class="studio-btn">Trash all</button>
          <button type="button" id="studioUndo" class="studio-btn">Undo</button>
          <button type="button" id="studioRedo" class="studio-btn">Redo</button>
        </div>
        <div class="studio-tool-row">
          <label class="studio-label">Hue <input type="range" id="studioHue" min="0" max="360" value="195"></label>
          <label class="studio-label">Sat <input type="range" id="studioSat" min="0" max="100" value="65"></label>
          <label class="studio-label">Light <input type="range" id="studioLight" min="0" max="100" value="40"></label>
        </div>
        <div class="studio-layers" id="studioLayerList"></div>
      `;
      this.root.appendChild(this.tools);
      this._bindTools();
      this._applyHsl();
    }

    _bindTools() {
      this.tools.querySelectorAll('[data-tool]').forEach((btn) => {
        btn.addEventListener('click', () => {
          this.tools.querySelectorAll('[data-tool]').forEach((b) => b.classList.remove('active'));
          btn.classList.add('active');
          if (this.engine) this.engine.setTool(btn.getAttribute('data-tool'));
        });
      });
      this.tools.querySelector('#studioColor').addEventListener('input', (e) => {
        if (this.engine) this.engine.setColor(e.target.value);
      });
      this.tools.querySelector('#studioSize').addEventListener('input', (e) => {
        if (this.engine) this.engine.setSize(e.target.value);
      });
      this.tools.querySelector('#studioNewLayer').addEventListener('click', () => {
        if (this.engine) {
          this.engine.addLayer();
          this._renderLayerList();
        }
      });
      this.tools.querySelector('#studioClearLayer').addEventListener('click', () => {
        if (this.engine) this.engine.clearActive();
      });
      this.tools.querySelector('#studioTrashLayers').addEventListener('click', () => {
        if (this.engine) {
          this.engine.deleteAllLayers();
          this._renderLayerList();
        }
      });
      this.tools.querySelector('#studioUndo').addEventListener('click', () => this.engine && this.engine.undo());
      this.tools.querySelector('#studioRedo').addEventListener('click', () => this.engine && this.engine.redo());
      ['studioHue', 'studioSat', 'studioLight'].forEach((id) => {
        this.tools.querySelector('#' + id).addEventListener('input', () => {
          this.hue = +this.tools.querySelector('#studioHue').value;
          this.saturation = +this.tools.querySelector('#studioSat').value;
          this.lightness = +this.tools.querySelector('#studioLight').value;
          this._applyHsl();
        });
      });
    }

    _applyHsl() {
      this.colorFill.style.background = `hsl(${this.hue} ${this.saturation}% ${this.lightness}%)`;
      this.bgImg.style.filter = `hue-rotate(${this.hue - 195}deg) saturate(${this.saturation / 65}) brightness(${0.7 + this.lightness / 200})`;
    }

    _renderLayerList() {
      const list = this.tools.querySelector('#studioLayerList');
      if (!this.engine || !list) return;
      list.innerHTML = '';
      this.engine.getLayerList().forEach((l) => {
        const row = document.createElement('div');
        row.className = 'studio-layer-row' + (l.active ? ' active' : '');
        row.innerHTML = `<button type="button" class="studio-btn layer-select">${l.name}</button>
          <button type="button" class="studio-btn layer-vis">${l.visible ? 'Hide' : 'Show'}</button>`;
        row.querySelector('.layer-select').addEventListener('click', () => {
          this.engine.setActive(l.index);
          this._renderLayerList();
        });
        row.querySelector('.layer-vis').addEventListener('click', () => {
          this.engine.toggleVisibility(l.index);
          this._renderLayerList();
        });
        list.appendChild(row);
      });
    }

    setConcept(concept) {
      this.concept = concept || {};
      this.nickEl.textContent = this.concept.nickname || this.concept.subject || 'Unnamed';
      this.bioEl.textContent = this.concept.bio || this.concept.details || '';
      this.powerEl.textContent = this.concept.power_name || '';
      this.abilityEl.textContent = this.concept.ability_line || '';
    }

    async setArt(url) {
      this.artEl.src = url;
      this.artImage = await loadImage(url);
      if (!this.engine) {
        this.engine = new global.CardobotDrawingEngine(this.drawHost, ART_W, ART_H);
        this.engine.onChange = () => this._renderLayerList();
        const color = this.tools.querySelector('#studioColor').value;
        this.engine.setColor(color);
        this._renderLayerList();
      }
    }

    setDrawingEnabled(on) {
      this.tools.style.display = on ? '' : 'none';
      this.drawHost.style.pointerEvents = on ? 'auto' : 'none';
    }

    async compositeDataUrl(scale) {
      scale = scale || 2;
      const w = CARD_W * scale;
      const h = CARD_H * scale;
      const canvas = document.createElement('canvas');
      canvas.width = w;
      canvas.height = h;
      const ctx = canvas.getContext('2d');

      ctx.fillStyle = `hsl(${this.hue} ${this.saturation}% ${this.lightness}%)`;
      ctx.fillRect(0, 0, w, h);

      try {
        const bg = await loadImage(this.bgUrl);
        ctx.globalAlpha = 0.85;
        ctx.drawImage(bg, 0, 0, w, h);
        ctx.globalAlpha = 1;
      } catch (e) { /* optional */ }

      if (this.artImage) {
        ctx.drawImage(this.artImage, ART_X * scale, ART_Y * scale, ART_W * scale, ART_H * scale);
      }

      if (this.engine) {
        const flat = await loadImage(this.engine.exportFlattenedPng());
        ctx.drawImage(flat, ART_X * scale, ART_Y * scale, ART_W * scale, ART_H * scale);
      }

      try {
        const frame = await loadImage(this.frameUrl);
        ctx.drawImage(frame, 0, 0, w, h);
      } catch (e) { /* required ideally */ }

      // Text
      ctx.fillStyle = '#1a1a1a';
      ctx.font = `bold ${22 * scale}px Georgia, serif`;
      ctx.fillText(this.nickEl.textContent || '', 56 * scale, 70 * scale);
      ctx.font = `${14 * scale}px Georgia, serif`;
      wrapText(ctx, this.bioEl.textContent || '', 56 * scale, 620 * scale, 490 * scale, 18 * scale);
      ctx.font = `bold ${16 * scale}px Georgia, serif`;
      ctx.fillText(this.powerEl.textContent || '', 56 * scale, 720 * scale);
      ctx.font = `${14 * scale}px Georgia, serif`;
      ctx.fillText(this.abilityEl.textContent || '', 56 * scale, 748 * scale);

      return canvas.toDataURL('image/png');
    }

    getDrawingData() {
      return this.engine ? this.engine.exportLayersJson() : null;
    }

    getHsl() {
      return { hue: this.hue, saturation: this.saturation, lightness: this.lightness };
    }
  }

  function loadImage(src) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = () => resolve(img);
      img.onerror = reject;
      img.src = src;
    });
  }

  function wrapText(ctx, text, x, y, maxWidth, lineHeight) {
    const words = String(text).split(/\s+/);
    let line = '';
    let yy = y;
    let lines = 0;
    for (let n = 0; n < words.length && lines < 4; n++) {
      const test = line + words[n] + ' ';
      if (ctx.measureText(test).width > maxWidth && n > 0) {
        ctx.fillText(line, x, yy);
        line = words[n] + ' ';
        yy += lineHeight;
        lines++;
      } else {
        line = test;
      }
    }
    if (lines < 4) ctx.fillText(line, x, yy);
  }

  global.CardobotStudio = CardStudio;
})(window);
