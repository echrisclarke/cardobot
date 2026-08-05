/**
 * Multi-layer stamp brush engine for Card-o-Bot Ink Deck.
 */
(function (global) {
  'use strict';

  function uid() {
    return 'lyr_' + Math.random().toString(36).slice(2, 9);
  }

  class DrawingEngine {
    constructor(container, width, height, opts) {
      this.container = container;
      this.width = width;
      this.height = height;
      this.assetBase = (opts && opts.assetBase) || '';
      this.layers = [];
      this.activeIndex = 0;
      this.tool = 'brush'; // brush | eraser | hand
      this.color = '#646464';
      this.size = 8;
      this.opacity = 1;
      this.drawing = false;
      this.last = null;
      this.lastDir = 0;
      this.distAcc = 0;
      this.undoStacks = {};
      this.redoStacks = {};
      this.onChange = null;
      this.onPan = null;
      this.brush = {
        id: 'hard-round',
        spacing: 0.12,
        sizePressure: true,
        opacityPressure: false,
        flow: 1,
        rotateToDirection: false,
        tipImg: null,
      };
      this._tipCache = {};
      this._tintCache = { key: '', canvas: null };
      this._enabled = true;
      this._spacePan = false;

      this.stage = document.createElement('div');
      this.stage.className = 'drawing-stage';
      this.stage.style.cssText = 'position:relative;width:100%;height:100%;touch-action:none;cursor:crosshair;';
      container.appendChild(this.stage);

      this.addLayer('Layer 1');
      this._bindPointer();
    }

    _emit() {
      if (typeof this.onChange === 'function') this.onChange(this);
    }

    setEnabled(on) {
      this._enabled = !!on;
      this.stage.style.pointerEvents = on ? 'auto' : 'none';
      this._updateCursor();
    }

    setSpacePan(on) {
      this._spacePan = !!on;
      this._updateCursor();
    }

    _isPanTool() {
      return this.tool === 'hand' || this._spacePan;
    }

    _updateCursor() {
      if (!this._enabled) {
        this.stage.style.cursor = 'default';
        return;
      }
      this.stage.style.cursor = this._isPanTool() ? 'grab' : 'crosshair';
    }

    _makeCanvas(name, id) {
      const canvas = document.createElement('canvas');
      canvas.width = this.width;
      canvas.height = this.height;
      canvas.className = 'drawing-layer-canvas';
      canvas.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;pointer-events:none;';
      const ctx = canvas.getContext('2d', { willReadFrequently: true });
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      return { id: id || uid(), name, canvas, ctx, visible: true };
    }

    addLayer(name) {
      const layer = this._makeCanvas(name || ('Layer ' + (this.layers.length + 1)));
      this.layers.push(layer);
      this.stage.appendChild(layer.canvas);
      this.activeIndex = this.layers.length - 1;
      this.undoStacks[layer.id] = [];
      this.redoStacks[layer.id] = [];
      this._refreshPointerTargets();
      this._emit();
      return layer;
    }

    _refreshPointerTargets() {
      this.layers.forEach((l, i) => {
        l.canvas.style.pointerEvents = 'none';
        l.canvas.style.visibility = l.visible ? 'visible' : 'hidden';
        l.canvas.style.zIndex = String(i + 1);
      });
    }

    setActive(index) {
      if (index < 0 || index >= this.layers.length) return;
      this.activeIndex = index;
      this._refreshPointerTargets();
      this._emit();
    }

    setTool(tool) {
      if (tool === 'eraser') this.tool = 'eraser';
      else if (tool === 'hand') this.tool = 'hand';
      else this.tool = 'brush';
      this._updateCursor();
    }

    setColor(color) {
      this.color = color || '#646464';
      this._tintCache.key = '';
    }

    setSize(size) {
      this.size = Math.max(0.5, Number(size) || 1);
    }

    setOpacity(opacity) {
      const n = Number(opacity);
      this.opacity = Math.max(0.02, Math.min(1, Number.isFinite(n) ? n : 1));
    }

    async setBrushPreset(preset, tipUrl) {
      if (!preset) return;
      this.brush = Object.assign({}, this.brush, preset);
      this._tintCache.key = '';
      if (tipUrl) {
        this.brush.tipImg = await this._loadTip(tipUrl);
      } else if (preset.tip) {
        const url = this.assetBase + '/assets/brushes/' + preset.tip;
        this.brush.tipImg = await this._loadTip(url);
      }
    }

    _luminanceToAlpha(img) {
      const c = document.createElement('canvas');
      c.width = img.width || img.naturalWidth || 1;
      c.height = img.height || img.naturalHeight || 1;
      const ctx = c.getContext('2d');
      ctx.drawImage(img, 0, 0);
      const data = ctx.getImageData(0, 0, c.width, c.height);
      const px = data.data;
      let hasTransparency = false;
      for (let i = 0; i < px.length; i += 4) {
        if (px[i + 3] < 250) {
          hasTransparency = true;
          break;
        }
      }
      if (!hasTransparency) {
        for (let i = 0; i < px.length; i += 4) {
          const lum = (0.299 * px[i] + 0.587 * px[i + 1] + 0.114 * px[i + 2]) / 255;
          px[i] = 255;
          px[i + 1] = 255;
          px[i + 2] = 255;
          px[i + 3] = Math.round(Math.min(1, Math.max(0, lum)) * 255);
        }
        ctx.putImageData(data, 0, 0);
      }
      return c;
    }

    _loadTip(url) {
      if (this._tipCache[url]) return Promise.resolve(this._tipCache[url]);
      return new Promise((resolve) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
          const tip = this._luminanceToAlpha(img);
          this._tipCache[url] = tip;
          resolve(tip);
        };
        img.onerror = () => resolve(null);
        img.src = url;
      });
    }

    _tintedTip() {
      const tip = this.brush.tipImg;
      if (!tip) return null;
      const key = this.color + '|' + (tip.width || 0) + 'x' + (tip.height || 0);
      if (this._tintCache.key === key && this._tintCache.canvas) {
        return this._tintCache.canvas;
      }
      const off = document.createElement('canvas');
      off.width = tip.width;
      off.height = tip.height;
      const octx = off.getContext('2d');
      octx.clearRect(0, 0, off.width, off.height);
      octx.drawImage(tip, 0, 0);
      octx.globalCompositeOperation = 'source-in';
      octx.fillStyle = this.color;
      octx.fillRect(0, 0, off.width, off.height);
      this._tintCache = { key, canvas: off };
      return off;
    }

    toggleVisibility(index) {
      const layer = this.layers[index];
      if (!layer) return;
      layer.visible = !layer.visible;
      this._refreshPointerTargets();
      this._emit();
    }

    clearActive() {
      const layer = this.layers[this.activeIndex];
      if (!layer) return;
      this._snapshot(layer);
      layer.ctx.clearRect(0, 0, this.width, this.height);
      this.redoStacks[layer.id] = [];
      this._emit();
    }

    deleteAllLayers() {
      this.layers.forEach((l) => l.canvas.remove());
      this.layers = [];
      this.undoStacks = {};
      this.redoStacks = {};
      this.addLayer('Layer 1');
    }

    _snapshot(layer) {
      const stack = this.undoStacks[layer.id] || (this.undoStacks[layer.id] = []);
      stack.push(layer.ctx.getImageData(0, 0, this.width, this.height));
      if (stack.length > 40) stack.shift();
    }

    undo() {
      const layer = this.layers[this.activeIndex];
      if (!layer) return;
      const stack = this.undoStacks[layer.id] || [];
      if (!stack.length) return;
      const redo = this.redoStacks[layer.id] || (this.redoStacks[layer.id] = []);
      redo.push(layer.ctx.getImageData(0, 0, this.width, this.height));
      layer.ctx.putImageData(stack.pop(), 0, 0);
      this._emit();
    }

    redo() {
      const layer = this.layers[this.activeIndex];
      if (!layer) return;
      const redo = this.redoStacks[layer.id] || [];
      if (!redo.length) return;
      this._snapshot(layer);
      layer.ctx.putImageData(redo.pop(), 0, 0);
      this._emit();
    }

    _pos(e) {
      const rect = this.stage.getBoundingClientRect();
      const clientX = e.clientX;
      const clientY = e.clientY;
      if (clientX == null) return null;
      const pressure = (e.pointerType === 'pen' && typeof e.pressure === 'number' && e.pressure > 0)
        ? e.pressure
        : 0.5;
      return {
        x: ((clientX - rect.left) / Math.max(rect.width, 1)) * this.width,
        y: ((clientY - rect.top) / Math.max(rect.height, 1)) * this.height,
        pressure,
      };
    }

    _bindPointer() {
      let panLast = null;

      const start = (e) => {
        if (!this._enabled) return;
        if (e.pointerType === 'touch' && e.isPrimary === false) return;
        if (this._isPanTool()) {
          // Do not stopPropagation: viewer also needs the pointer for pinch/zoom.
          if (e.cancelable) e.preventDefault();
          try { this.stage.setPointerCapture(e.pointerId); } catch (_) { /* */ }
          this.drawing = false;
          panLast = { x: e.clientX, y: e.clientY };
          this.stage.style.cursor = 'grabbing';
          return;
        }
        e.stopPropagation();
        if (e.cancelable) e.preventDefault();
        const layer = this.layers[this.activeIndex];
        if (!layer || !layer.visible) return;
        try { this.stage.setPointerCapture(e.pointerId); } catch (_) { /* */ }
        this.drawing = true;
        this._snapshot(layer);
        this.redoStacks[layer.id] = [];
        this.last = this._pos(e);
        this.distAcc = 0;
        this._dab(this.last);
      };
      const move = (e) => {
        if (panLast && this._isPanTool()) {
          e.stopPropagation();
          if (e.cancelable) e.preventDefault();
          const dx = e.clientX - panLast.x;
          const dy = e.clientY - panLast.y;
          panLast = { x: e.clientX, y: e.clientY };
          if (typeof this.onPan === 'function') this.onPan(dx, dy);
          return;
        }
        if (!this.drawing) return;
        e.stopPropagation();
        if (e.cancelable) e.preventDefault();
        const p = this._pos(e);
        if (!p || !this.last) return;
        this._strokeTo(this.last, p);
        this.last = p;
      };
      const end = (e) => {
        if (panLast) {
          panLast = null;
          this._updateCursor();
          try { this.stage.releasePointerCapture(e.pointerId); } catch (_) { /* */ }
          return;
        }
        if (!this.drawing) return;
        e.stopPropagation();
        this.drawing = false;
        this.last = null;
        try { this.stage.releasePointerCapture(e.pointerId); } catch (_) { /* */ }
        this._emit();
      };

      this.stage.addEventListener('pointerdown', start);
      this.stage.addEventListener('pointermove', move);
      this.stage.addEventListener('pointerup', end);
      this.stage.addEventListener('pointercancel', end);
      this.stage.addEventListener('contextmenu', (e) => e.preventDefault());
    }

    _strokeTo(a, b) {
      const dx = b.x - a.x;
      const dy = b.y - a.y;
      const dist = Math.hypot(dx, dy);
      if (dist < 0.01) return;
      this.lastDir = Math.atan2(dy, dx);
      // Photoshop-like: spacing is % of stamp diameter (drawn at size*2).
      const diameter = Math.max(1, this.size * 2);
      const spacingPx = Math.max(0.5, diameter * (this.brush.spacing || 0.15));
      this.distAcc += dist;
      let steps = Math.floor(this.distAcc / spacingPx);
      if (steps < 1) return;
      for (let i = 1; i <= steps; i++) {
        const t = (i * spacingPx - (this.distAcc - dist)) / dist;
        if (t < 0 || t > 1) continue;
        const p = {
          x: a.x + dx * t,
          y: a.y + dy * t,
          pressure: a.pressure + (b.pressure - a.pressure) * t,
        };
        this._dab(p);
      }
      this.distAcc %= spacingPx;
    }

    _dab(p) {
      const layer = this.layers[this.activeIndex];
      if (!layer || !p) return;
      const ctx = layer.ctx;
      let size = this.size;
      let alpha = (this.brush.flow == null ? 1 : this.brush.flow) * this.opacity;
      if (this.brush.sizePressure) {
        size *= 0.35 + p.pressure * 0.9;
      }
      if (this.brush.opacityPressure) {
        alpha *= 0.25 + p.pressure * 0.85;
      }
      alpha = Math.max(0.01, Math.min(1, alpha));
      ctx.save();
      if (this.tool === 'eraser') {
        ctx.globalCompositeOperation = 'destination-out';
        ctx.fillStyle = 'rgba(0,0,0,' + alpha + ')';
      } else {
        ctx.globalCompositeOperation = 'source-over';
        ctx.globalAlpha = alpha;
      }

      if (this.brush.tipImg) {
        const tip = this.tool === 'eraser' ? this.brush.tipImg : this._tintedTip();
        const w = size * 2;
        const h = size * 2;
        ctx.translate(p.x, p.y);
        if (this.brush.rotateToDirection) ctx.rotate(this.lastDir);
        if (this.tool === 'eraser') {
          ctx.globalAlpha = alpha;
        }
        ctx.drawImage(tip, -w / 2, -h / 2, w, h);
      } else {
        ctx.beginPath();
        ctx.fillStyle = this.tool === 'eraser' ? 'rgba(0,0,0,' + alpha + ')' : this.color;
        ctx.arc(p.x, p.y, size / 2, 0, Math.PI * 2);
        ctx.fill();
      }
      ctx.restore();
    }

    getLayerList() {
      return this.layers.map((l, i) => ({
        id: l.id,
        name: l.name,
        visible: l.visible,
        active: i === this.activeIndex,
        index: i,
      }));
    }

    getLayerThumb(index, size) {
      const layer = this.layers[index];
      if (!layer || !layer.canvas) return '';
      const dim = Math.max(24, Math.min(96, Number(size) || 48));
      const c = document.createElement('canvas');
      c.width = dim;
      c.height = dim;
      const ctx = c.getContext('2d');
      ctx.fillStyle = '#2a3034';
      ctx.fillRect(0, 0, dim, dim);
      // Checker so transparent ink reads.
      for (let y = 0; y < dim; y += 6) {
        for (let x = 0; x < dim; x += 6) {
          if (((x + y) / 6) % 2 < 1) {
            ctx.fillStyle = '#3a4248';
            ctx.fillRect(x, y, 6, 6);
          }
        }
      }
      const scale = Math.min(dim / layer.canvas.width, dim / layer.canvas.height);
      const w = Math.max(1, layer.canvas.width * scale);
      const h = Math.max(1, layer.canvas.height * scale);
      ctx.drawImage(layer.canvas, (dim - w) / 2, (dim - h) / 2, w, h);
      return c.toDataURL('image/png');
    }

    exportLayersJson() {
      return {
        version: 2,
        width: this.width,
        height: this.height,
        brush: this.brush.id,
        layers: this.layers.map((l) => ({
          id: l.id,
          name: l.name,
          visible: !!l.visible,
          png: l.canvas.toDataURL('image/png'),
        })),
      };
    }

    async importLayersJson(data) {
      if (!data || !Array.isArray(data.layers) || !data.layers.length) return false;
      this.layers.forEach((l) => l.canvas.remove());
      this.layers = [];
      this.undoStacks = {};
      this.redoStacks = {};

      const loadPng = (src) => new Promise((resolve) => {
        if (!src || typeof src !== 'string') {
          resolve(null);
          return;
        }
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => resolve(null);
        img.src = src;
      });

      for (let i = 0; i < data.layers.length; i++) {
        const src = data.layers[i];
        const layer = this._makeCanvas(src.name || ('Layer ' + (i + 1)), src.id || uid());
        layer.visible = src.visible !== false;
        this.layers.push(layer);
        this.stage.appendChild(layer.canvas);
        this.undoStacks[layer.id] = [];
        this.redoStacks[layer.id] = [];
        const img = await loadPng(src.png);
        if (img) {
          layer.ctx.clearRect(0, 0, this.width, this.height);
          layer.ctx.drawImage(img, 0, 0, this.width, this.height);
        }
      }
      this.activeIndex = Math.max(0, this.layers.length - 1);
      this._refreshPointerTargets();
      this._emit();
      return true;
    }

    exportFlattenedPng() {
      const out = document.createElement('canvas');
      out.width = this.width;
      out.height = this.height;
      const ctx = out.getContext('2d');
      this.layers.forEach((l) => {
        if (l.visible) ctx.drawImage(l.canvas, 0, 0);
      });
      return out.toDataURL('image/png');
    }
  }

  global.CardobotDrawingEngine = DrawingEngine;
})(window);
