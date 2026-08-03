/**
 * Editable multi-layer drawing engine for Card-o-Bot studio.
 */
(function (global) {
  'use strict';

  function uid() {
    return 'lyr_' + Math.random().toString(36).slice(2, 9);
  }

  class DrawingEngine {
    constructor(container, width, height) {
      this.container = container;
      this.width = width;
      this.height = height;
      this.layers = [];
      this.activeIndex = 0;
      this.tool = 'brush';
      this.color = 'rgba(100,100,100,1)';
      this.size = 4;
      this.drawing = false;
      this.last = null;
      this.undoStacks = {};
      this.redoStacks = {};
      this.onChange = null;

      this.stage = document.createElement('div');
      this.stage.className = 'drawing-stage';
      this.stage.style.cssText = 'position:relative;width:100%;height:100%;touch-action:none;';
      container.appendChild(this.stage);

      this.addLayer('Layer 1');
      this._bindPointer();
    }

    _emit() {
      if (typeof this.onChange === 'function') this.onChange(this);
    }

    _makeCanvas(name) {
      const canvas = document.createElement('canvas');
      canvas.width = this.width;
      canvas.height = this.height;
      canvas.className = 'drawing-layer-canvas';
      canvas.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;pointer-events:none;';
      const ctx = canvas.getContext('2d');
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      return { id: uid(), name, canvas, ctx, visible: true };
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
        l.canvas.style.pointerEvents = i === this.activeIndex && l.visible ? 'auto' : 'none';
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
      this.tool = tool === 'eraser' ? 'eraser' : 'brush';
    }

    setColor(color) {
      this.color = color;
    }

    setSize(size) {
      this.size = Math.max(0.5, Number(size) || 1);
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
      const layer = this.layers[this.activeIndex];
      if (!layer) return null;
      const rect = layer.canvas.getBoundingClientRect();
      const clientX = e.clientX ?? (e.touches && e.touches[0] && e.touches[0].clientX);
      const clientY = e.clientY ?? (e.touches && e.touches[0] && e.touches[0].clientY);
      if (clientX == null) return null;
      return {
        x: ((clientX - rect.left) / rect.width) * this.width,
        y: ((clientY - rect.top) / rect.height) * this.height,
      };
    }

    _bindPointer() {
      const start = (e) => {
        if (e.cancelable) e.preventDefault();
        const layer = this.layers[this.activeIndex];
        if (!layer || !layer.visible) return;
        this.drawing = true;
        this._snapshot(layer);
        this.redoStacks[layer.id] = [];
        this.last = this._pos(e);
        this._stroke(this.last, this.last);
      };
      const move = (e) => {
        if (!this.drawing) return;
        if (e.cancelable) e.preventDefault();
        const p = this._pos(e);
        if (!p || !this.last) return;
        this._stroke(this.last, p);
        this.last = p;
      };
      const end = () => {
        this.drawing = false;
        this.last = null;
        this._emit();
      };

      this.stage.addEventListener('pointerdown', start);
      this.stage.addEventListener('pointermove', move);
      this.stage.addEventListener('pointerup', end);
      this.stage.addEventListener('pointerleave', end);
      this.stage.addEventListener('pointercancel', end);
    }

    _stroke(a, b) {
      const layer = this.layers[this.activeIndex];
      if (!layer || !a || !b) return;
      const ctx = layer.ctx;
      ctx.save();
      if (this.tool === 'eraser') {
        ctx.globalCompositeOperation = 'destination-out';
        ctx.strokeStyle = 'rgba(0,0,0,1)';
      } else {
        ctx.globalCompositeOperation = 'source-over';
        ctx.strokeStyle = this.color;
      }
      ctx.lineWidth = this.size;
      ctx.beginPath();
      ctx.moveTo(a.x, a.y);
      ctx.lineTo(b.x, b.y);
      ctx.stroke();
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

    exportLayersJson() {
      return {
        version: 1,
        width: this.width,
        height: this.height,
        layers: this.layers.map((l) => ({
          id: l.id,
          name: l.name,
          visible: l.visible,
          png: l.visible ? l.canvas.toDataURL('image/png') : null,
        })),
      };
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
