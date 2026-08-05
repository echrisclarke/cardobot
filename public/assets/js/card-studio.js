/**
 * Framed card composite surface (layout-aware). Tools live in Card Viewer / Ink Deck.
 */
(function (global) {
  'use strict';

  const L = () => global.CardobotLayout || {
    CARD_W: 606, CARD_H: 800,
    ART_STUDIO: { x: 63, y: 90, w: 480, h: 480 },
    NICKNAME: { x: 80, y: 48, fontSize: 32, color: '#585858' },
    BIO: { x: 120, y: 616, w: 430, fontSize: 14, color: 'rgba(44,127,162,1)', maxLines: 4 },
    POWER: { x: 277, y: 710, fontSize: 14, color: 'rgba(218,239,237,0.9)' },
    ABILITY: { x: 277, y: 733, fontSize: 14, color: 'rgba(249,187,170,0.9)' },
    HP: { x: 455, y: 45, w: 100, fontSize: 40, color: 'rgba(88,88,88,0.75)' },
    STATS: [],
    STAT_COLOR: 'rgba(255,255,255,0.88)',
  };

  class CardStudio {
    constructor(opts) {
      this.root = opts.root;
      this.assetBase = opts.assetBase || '';
      this.frameUrl = opts.frameUrl;
      this.bgUrl = opts.bgUrl;
      this.onStatus = opts.onStatus || function () {};
      this.hideTools = opts.hideTools !== false;
      this.engine = null;
      this.artImage = null;
      this.concept = {};
      this.stats = { hp: 0, npo: 0, att: 0, str: 0, los: 0, con: 0 };
      this.hue = 195;
      this.saturation = 65;
      this.lightness = 40;
      this._userTintLocked = false;
      this.showCredit = true;
      this._buildDom();
    }

    _buildDom() {
      const layout = L();
      this.root.innerHTML = '';
      this.root.classList.add('card-studio');

      this.card = document.createElement('div');
      this.card.className = 'studio-card';

      this.colorFill = document.createElement('div');
      this.colorFill.className = 'studio-card-color';
      this.bgImg = document.createElement('img');
      this.bgImg.className = 'studio-card-bg';
      this.bgImg.alt = '';
      this.bgImg.src = this.bgUrl;

      this.artWrap = document.createElement('div');
      this.artWrap.className = 'studio-art-wrap';
      const artBox = layout.ART_STUDIO || layout.ART;
      if (artBox) {
        const W = layout.CARD_W;
        const H = layout.CARD_H;
        this.artWrap.style.left = ((artBox.x / W) * 100).toFixed(2) + '%';
        this.artWrap.style.top = ((artBox.y / H) * 100).toFixed(2) + '%';
        this.artWrap.style.width = ((artBox.w / W) * 100).toFixed(2) + '%';
        this.artWrap.style.height = ((artBox.h / H) * 100).toFixed(2) + '%';
      }
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
      this.textLayer.innerHTML = '';
      this.nickEl = this._absText('studio-nickname', layout.NICKNAME);
      this.creditEl = layout.CREDIT ? this._absText('studio-credit', layout.CREDIT) : null;
      this.typeEl = layout.TYPE ? this._absText('studio-type', layout.TYPE) : null;
      this.heightEl = layout.HEIGHT ? this._absText('studio-height', layout.HEIGHT) : null;
      this.massEl = layout.MASS ? this._absText('studio-mass', layout.MASS) : null;
      this.bioEl = this._absText('studio-bio', layout.BIO);
      this.powerEl = this._absText('studio-power', layout.POWER);
      this.powerValueEl = null;
      this.abilityEl = this._absText('studio-ability', layout.ABILITY);
      this.hpEl = this._absText('studio-hp', layout.HP);
      this.statEls = {};
      (layout.STATS || []).forEach((s) => {
        this.statEls[s.key] = this._absText('studio-stat studio-stat-' + s.key, {
          x: s.x, y: s.y, w: s.w, h: s.h, fontSize: s.fontSize,
          fontWeight: '700', color: layout.STAT_COLOR, align: s.align || 'left',
        });
      });

      this.card.appendChild(this.colorFill);
      this.card.appendChild(this.bgImg);
      this.card.appendChild(this.artWrap);
      this.card.appendChild(this.frameImg);
      this.card.appendChild(this.textLayer);
      this.root.appendChild(this.card);

      this.tools = document.createElement('div');
      this.tools.className = 'studio-tools';
      this.tools.style.display = 'none';
      this.root.appendChild(this.tools);
      this._applyHsl();
    }

    _absText(cls, box) {
      const el = document.createElement('div');
      el.className = cls;
      el._box = box;
      const W = L().CARD_W;
      const H = L().CARD_H;
      const fs = box.fontSize || 14;
      const multi = (box.maxLines || 1) > 1;
      const align = box.align || 'left';
      const valignStart = multi || box.valign === 'start';
      // Multi-line: grid for vertical centering. Stretch + min-width:0 so
      // lines wrap inside the well (start sizing can overflow the right rule).
      const layoutCss = multi
        ? [
            'display:grid',
            'align-content:center',
            'justify-items:stretch',
            'min-width:0',
            'text-align:' + align,
            'padding:0.28em 0.5em 0.28em 0',
          ]
        : [
            'display:flex',
            valignStart ? 'align-items:flex-start' : 'align-items:center',
            align === 'center' ? 'justify-content:center'
              : (align === 'right' ? 'justify-content:flex-end' : 'justify-content:flex-start'),
            // Right-aligned meta needs a hair of inset so units (t/kg) are not clipped.
            align === 'right' ? 'padding:0 0.2em 0 0' : 'padding:0',
          ];
      const faceFont = (L().FACE_FONT) || '"Press Start 2P", "Courier New", monospace';
      el.style.cssText = [
        'position:absolute',
        'left:' + ((box.x / W) * 100).toFixed(2) + '%',
        'top:' + ((box.y / H) * 100).toFixed(2) + '%',
        box.w ? 'width:' + ((box.w / W) * 100).toFixed(2) + '%' : '',
        box.h ? 'height:' + ((box.h / H) * 100).toFixed(2) + '%' : '',
        'font-family:' + faceFont,
        'font-size:calc(' + fs + ' / ' + W + ' * 100cqw)',
        'font-weight:' + (box.fontWeight || '400'),
        'color:' + (box.color || '#222'),
        'line-height:' + (box.lineHeight || 1.15),
        'overflow:hidden',
        'white-space:' + (multi ? 'normal' : 'nowrap'),
        // Face text must never show ellipsis; shrink/trim instead.
        'text-overflow:clip',
        'text-transform:' + (box.transform || 'none'),
        box.textShadow ? 'text-shadow:' + box.textShadow : '',
        'pointer-events:none',
        ...layoutCss,
        'box-sizing:border-box',
        'margin:0',
      ].filter(Boolean).join(';');
      this.textLayer.appendChild(el);
      return el;
    }

    async _ensureFaceFont() {
      const faceFont = (L().FACE_FONT) || '"Press Start 2P", "Courier New", monospace';
      if (!document.fonts || !document.fonts.load) return;
      try {
        await document.fonts.load('12px ' + faceFont);
        await document.fonts.ready;
      } catch (e) { /* use fallback metrics */ }
    }

    _fitText(el, text, capFs) {
      const box = el._box || {};
      const W = L().CARD_W;
      let base = box.fontSize || 14;
      if (capFs != null && capFs > 0) base = Math.min(base, capFs);
      const minFs = box.minFontSize != null ? box.minFontSize : 5;
      let fs = base;
      const multi = (box.maxLines || 1) > 1;
      let value = text == null ? '' : String(text);
      if (box.transform === 'uppercase') value = value.toUpperCase();
      el.textContent = value;

      el.style.overflow = 'hidden';
      el.style.textOverflow = 'clip';
      el.style.whiteSpace = multi ? 'normal' : 'nowrap';

      const apply = (size) => {
        el.style.fontSize = 'calc(' + size + ' / ' + W + ' * 100cqw)';
      };
      const overflows = () => multi
        ? (el.scrollHeight > el.clientHeight + 1 || el.scrollWidth > el.clientWidth + 1)
        : (el.scrollWidth > el.clientWidth + 1);

      apply(fs);
      for (let i = 0; i < 48 && overflows() && fs > minFs; i++) {
        fs -= 1;
        apply(fs);
      }
      // Last resort: trim at a word boundary (never leave "...").
      // Keep Mass/Height labels intact; shorten numbers before chopping the label.
      if (overflows()) {
        apply(minFs);
        fs = minFs;
        while (value.length > 1 && overflows()) {
          const unitMatch = value.match(/(\d+(?:\.\d+)?)\s+([a-z]+)\s*$/i);
          if (unitMatch && unitMatch[1].length > 1) {
            const num = unitMatch[1];
            const unit = unitMatch[2];
            value = value.slice(0, unitMatch.index) + num.slice(0, -1) + ' ' + unit;
            el.textContent = value;
            continue;
          }
          // Drop Type:/H:/M: prefix before eating into the label letters.
          const labeled = value.match(/^(type|height|mass|h|m)\s*:\s*(.+)$/i);
          if (labeled && labeled[2].length > 0) {
            value = labeled[2];
            el.textContent = value;
            continue;
          }
          const sp = value.lastIndexOf(' ');
          value = (sp > 0 ? value.slice(0, sp) : value.slice(0, -1))
            .replace(/[,\s.;:…]+$/g, '')
            .trim();
          el.textContent = value;
        }
      }

      el.style.textOverflow = 'clip';
      el.dataset.fitSize = String(fs);
      return fs;
    }

    _clipField(key, text) {
      let value = text == null ? '' : String(text).trim();
      const limits = (L().LIMITS) || {};
      const maxMap = {
        nickname: limits.nickname || 22,
        power_name: limits.power_name || 18,
        ability_name: limits.ability_name || 16,
        ability_line: limits.ability_line || 12,
        power_value: limits.power_value || 10,
        bio: limits.bio || 220,
      };
      const max = maxMap[key];
      if (max && value.length > max) {
        const cut = value.slice(0, max);
        const sp = cut.lastIndexOf(' ');
        value = (sp >= Math.floor(max * 0.55) ? cut.slice(0, sp) : cut)
          .replace(/[,\s.;:…]+$/g, '')
          .trim();
        value = value.replace(/\b(in|on|at|to|for|with|when|and|or|of|the|a|an)\s*$/i, '').trim();
      }
      return value;
    }

    _setNamedValueLine(el, title, value, titleKey, valueKey) {
      if (!el) return 0;
      const box = el._box || {};
      const W = L().CARD_W;
      let titleText = title == null ? '' : String(title).trim();
      let valueText = value == null ? '' : String(value).trim();
      if (box.transform === 'uppercase') {
        titleText = titleText.toUpperCase();
        valueText = valueText.toUpperCase();
      }
      el.textContent = '';
      el.style.display = 'flex';
      el.style.justifyContent = 'space-between';
      el.style.alignItems = 'center';
      el.style.gap = '0.35em';
      el.style.whiteSpace = 'nowrap';
      el.style.overflow = 'hidden';
      el.style.textOverflow = 'clip';
      el.style.flexWrap = 'nowrap';

      const left = document.createElement('span');
      left.style.cssText = 'flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:clip;white-space:nowrap';
      left.textContent = titleText;
      el.appendChild(left);

      if (valueText) {
        const right = document.createElement('span');
        right.style.cssText = 'flex:0 0 auto;white-space:nowrap;overflow:visible';
        right.textContent = valueText;
        el.appendChild(right);
      }

      const base = box.fontSize || 14;
      const minFs = box.minFontSize != null ? box.minFontSize : 5;
      let fs = base;
      const apply = (size) => {
        el.style.fontSize = 'calc(' + size + ' / ' + W + ' * 100cqw)';
      };
      const overflows = () => (
        el.scrollWidth > el.clientWidth + 1
        || left.scrollWidth > left.clientWidth + 1
      );
      apply(fs);
      for (let i = 0; i < 48 && overflows() && fs > minFs; i++) {
        fs -= 1;
        apply(fs);
      }
      if (overflows()) {
        apply(minFs);
        fs = minFs;
        while (titleText.length > 1 && overflows()) {
          const sp = titleText.lastIndexOf(' ');
          titleText = (sp > 0 ? titleText.slice(0, sp) : titleText.slice(0, -1))
            .replace(/[,\s.;:…]+$/g, '')
            .trim();
          left.textContent = titleText;
        }
      }
      el.dataset.fitSize = String(fs);
      el.dataset[titleKey] = titleText;
      el.dataset[valueKey] = valueText;
      return fs;
    }

    _setPowerLine(title, value) {
      return this._setNamedValueLine(this.powerEl, title, value, 'powerTitle', 'powerValue');
    }

    _setAbilityLine(title, value) {
      return this._setNamedValueLine(this.abilityEl, title, value, 'abilityTitle', 'abilityValue');
    }

    _applyHsl() {
      this.colorFill.style.background = `hsl(${this.hue} ${this.saturation}% ${this.lightness}%)`;
      this.bgImg.style.filter = `hue-rotate(${this.hue - 195}deg) saturate(${this.saturation / 65}) brightness(${0.7 + this.lightness / 200})`;
    }

    setHsl(h, s, l, fromUser) {
      this.hue = h;
      this.saturation = s;
      this.lightness = l;
      if (fromUser) this._userTintLocked = true;
      this._applyHsl();
    }

    async setConcept(concept, stats) {
      this.concept = concept || {};
      if (stats) this.stats = Object.assign({}, this.stats, stats);
      if (Object.prototype.hasOwnProperty.call(this.concept, 'show_credit')) {
        this.showCredit = this.concept.show_credit !== false && this.concept.show_credit !== 0
          && this.concept.show_credit !== '0' && this.concept.show_credit !== 'false';
      }
      await this._ensureFaceFont();
      const nick = this._clipField('nickname', this.concept.nickname || this.concept.subject || 'Unnamed');
      const bio = this._clipField('bio', this.concept.bio || this.concept.details || '');
      const power = this._clipField('power_name', this.concept.power_name || '');
      const powerValue = this._clipField('power_value', this.concept.power_value || '');
      let abilityName = this._clipField('ability_name', this.concept.ability_name || '');
      let abilityEffect = this._clipField('ability_line', this.concept.ability_line || '');
      // Legacy: if only ability_line exists and looks like a title, treat it as the name.
      if (!abilityName && abilityEffect && !/^[+\d]/.test(abilityEffect)) {
        abilityName = abilityEffect;
        abilityEffect = '';
      }
      const meta = this._metaLines(this.concept, this.stats);
      this._applyConceptStyle(this.concept);
      const nickFs = this._fitText(this.nickEl, nick);
      if (this.creditEl) this._fitText(this.creditEl, meta.credit);
      this.setShowCredit(this.showCredit);
      if (this.typeEl) this._fitText(this.typeEl, meta.type);
      if (this.heightEl) this._fitText(this.heightEl, meta.height);
      if (this.massEl) this._fitText(this.massEl, meta.mass);
      this._fitText(this.bioEl, bio);
      this._setPowerLine(power, powerValue);
      this._setAbilityLine(abilityName, abilityEffect);
      this._fitText(this.hpEl, this.stats.hp != null ? String(this.stats.hp) : '', nickFs);
      Object.keys(this.statEls).forEach((k) => {
        this._fitText(this.statEls[k], this.stats[k] != null ? String(this.stats[k]) : '');
      });
    }

    _applyConceptStyle(concept) {
      const layout = L();
      const nameColor = this._resolveNameInk(concept);
      const statsColor = this._resolveStatInk(concept);
      if (this.nickEl) this.nickEl.style.color = nameColor;
      Object.keys(this.statEls || {}).forEach((k) => {
        this.statEls[k].style.color = statsColor;
      });

      // Apply AI initial tint only until the player retunes the face color.
      if (!this._userTintLocked) {
        const hsl = this._resolveBgHsl(concept);
        if (hsl) this.setHsl(hsl.h, hsl.s, hsl.l);
      }
    }

    lockUserTint(on) {
      this._userTintLocked = !!on;
    }

    setShowCredit(on) {
      this.showCredit = !!on;
      if (this.concept) this.concept.show_credit = this.showCredit;
      if (this.creditEl) {
        this.creditEl.style.visibility = this.showCredit ? 'visible' : 'hidden';
        this.creditEl.setAttribute('aria-hidden', this.showCredit ? 'false' : 'true');
      }
    }

    getShowCredit() {
      return !!this.showCredit;
    }

    _resolveNameInk(concept) {
      const layout = L();
      if (concept.name_color) return String(concept.name_color);
      const key = String(concept.name_ink || '').toLowerCase();
      const map = layout.NAME_INKS || {};
      return map[key] || (layout.NICKNAME && layout.NICKNAME.color) || 'rgba(88,88,88,1)';
    }

    _resolveStatInk(concept) {
      const layout = L();
      if (concept.stats_color) return String(concept.stats_color);
      const key = String(concept.stats_ink || '').toLowerCase();
      const map = layout.STAT_INKS || {};
      return map[key] || layout.STAT_COLOR || 'rgba(255,255,255,0.95)';
    }

    _resolveBgHsl(concept) {
      const layout = L();
      const h = Number(concept.card_hue);
      const s = Number(concept.card_sat);
      const l = Number(concept.card_light);
      if (Number.isFinite(h) && Number.isFinite(s) && Number.isFinite(l)) {
        return { h, s, l };
      }
      const key = String(concept.card_bg || '').toLowerCase().replace(/-/g, '_');
      const preset = (layout.BG_PRESETS || {})[key];
      return preset || null;
    }

    _metaLines(concept, stats) {
      const user = String(concept.creator_username || concept.username || concept.owner || 'Cardy')
        .replace(/\s+/g, '');
      const kind = String(concept.type || 'Bot').trim() || 'Bot';
      let height = stats.height || concept.height || '';
      let mass = stats.mass || concept.mass || '';
      if (!height || !mass) {
        const fb = this._fallbackMeasures(kind, concept);
        height = height || fb.height;
        mass = mass || fb.mass;
      }
      height = this._abbrevMeasure(height, 'height');
      mass = this._abbrevMeasure(mass, 'mass');
      // Short labels: Press Start 2P cannot afford "Height: "/"Mass: " padding.
      if (!/^h:/i.test(height) && !/^height:/i.test(height)) height = 'H: ' + height;
      else height = height.replace(/^height:\s*/i, 'H: ');
      if (!/^m:/i.test(mass) && !/^mass:/i.test(mass)) mass = 'M: ' + mass;
      else mass = mass.replace(/^mass:\s*/i, 'M: ');
      return {
        credit: user,
        type: 'Type: ' + kind,
        height,
        mass,
      };
    }

    _abbrevMeasure(raw, kind) {
      let s = String(raw || '').trim();
      s = s.replace(/^(height|mass)\s*:\s*/i, '');
      s = s.replace(/,/g, '');
      const map = [
        [/(\d+(?:\.\d+)?)\s*(kilometers|kilometres)\b/gi, '$1 km'],
        [/(\d+(?:\.\d+)?)\s*(meters|metres)\b/gi, '$1 m'],
        [/(\d+(?:\.\d+)?)\s*(centimeters|centimetres)\b/gi, '$1 cm'],
        [/(\d+(?:\.\d+)?)\s*(millimeters|millimetres)\b/gi, '$1 mm'],
        [/(\d+(?:\.\d+)?)\s*(inches)\b/gi, '$1 in'],
        [/(\d+(?:\.\d+)?)\s*(feet|foot)\b/gi, '$1 ft'],
        [/(\d+(?:\.\d+)?)\s*(tonnes|tons|ton)\b/gi, '$1 t'],
        [/(\d+(?:\.\d+)?)\s*(kilograms)\b/gi, '$1 kg'],
        [/(\d+(?:\.\d+)?)\s*(grams)\b/gi, '$1 g'],
        [/(\d+(?:\.\d+)?)\s*(pounds)\b/gi, '$1 lb'],
      ];
      map.forEach(([re, rep]) => { s = s.replace(re, rep); });
      s = s.replace(/\s+/g, ' ').trim();
      if (!s) return s;
      if (kind === 'height' && !/\b(m|cm|mm|km|in|ft)\b/i.test(s) && /^\d/.test(s)) s += ' m';
      if (kind === 'mass' && !/\b(kg|g|t|lb)\b/i.test(s) && /^\d/.test(s)) s += ' kg';
      return s;
    }

    _fallbackMeasures(kind, concept) {
      const k = String(kind).toUpperCase();
      const bands = {
        BOT: [0.8, 2.4, 12, 180], ROBOT: [0.8, 2.4, 12, 180],
        ANDROID: [1.4, 2.1, 45, 120], HUMAN: [1.4, 2.0, 40, 110],
        PERSON: [1.4, 2.0, 40, 110], CRITTER: [0.2, 1.2, 2, 45],
        CREATURE: [0.2, 1.2, 2, 45],
      };
      const b = bands[k] || [0.6, 2.2, 8, 140];
      const seed = String(concept.nickname || concept.subject || 'x');
      let h = 0;
      for (let i = 0; i < seed.length; i++) h = ((h << 5) - h + seed.charCodeAt(i)) | 0;
      const t = Math.abs(h % 1000) / 1000;
      const u = Math.abs((h >> 3) % 1000) / 1000;
      return {
        height: (b[0] + t * (b[1] - b[0])).toFixed(1) + ' m',
        mass: String(Math.round(b[2] + u * (b[3] - b[2]))) + ' kg',
      };
    }

    async setArt(url) {
      this.artEl.src = url;
      this.artImage = await loadImage(url);
      const art = L().ART_STUDIO;
      if (!this.engine) {
        this.engine = new global.CardobotDrawingEngine(this.drawHost, art.w, art.h, {
          assetBase: this.assetBase,
        });
        this.engine.setEnabled(false);
      }
    }

    setDrawingEnabled(on) {
      if (this.engine) this.engine.setEnabled(!!on);
      this.drawHost.style.pointerEvents = on ? 'auto' : 'none';
      this.tools.style.display = 'none';
    }

    getEngine() {
      return this.engine;
    }

    async compositeDataUrl(scale) {
      scale = scale || 2;
      const layout = L();
      const w = layout.CARD_W * scale;
      const h = layout.CARD_H * scale;
      const canvas = document.createElement('canvas');
      canvas.width = w;
      canvas.height = h;
      const ctx = canvas.getContext('2d');
      const art = layout.ART_STUDIO;

      ctx.fillStyle = `hsl(${this.hue} ${this.saturation}% ${this.lightness}%)`;
      ctx.fillRect(0, 0, w, h);

      try {
        const bg = await loadImage(this.bgUrl);
        ctx.globalAlpha = 0.85;
        ctx.drawImage(bg, 0, 0, w, h);
        ctx.globalAlpha = 1;
      } catch (e) { /* optional */ }

      if (this.artImage) {
        // Match .studio-art CSS scale(1.08) so export crops paper margins the same way.
        const bleed = 1.08;
        const aw = art.w * scale * bleed;
        const ah = art.h * scale * bleed;
        const ax = art.x * scale - (aw - art.w * scale) / 2;
        const ay = art.y * scale - (ah - art.h * scale) / 2;
        ctx.save();
        ctx.beginPath();
        ctx.rect(art.x * scale, art.y * scale, art.w * scale, art.h * scale);
        ctx.clip();
        ctx.drawImage(this.artImage, ax, ay, aw, ah);
        ctx.restore();
      }

      if (this.engine) {
        const flat = await loadImage(this.engine.exportFlattenedPng());
        ctx.drawImage(flat, art.x * scale, art.y * scale, art.w * scale, art.h * scale);
      }

      try {
        const frame = await loadImage(this.frameUrl);
        ctx.drawImage(frame, 0, 0, w, h);
      } catch (e) { /* */ }

      const drawBox = (box, text) => {
        if (!text || !box) return;
        ctx.fillStyle = box.color || '#222';
        const faceFont = (layout.FACE_FONT) || '"Press Start 2P", "Courier New", monospace';
        ctx.font = `${box.fontWeight || '400'} ${(box.fontSize || 14) * scale}px ${faceFont}`;
        const align = box.align === 'center' ? 'center' : (box.align === 'right' ? 'right' : 'left');
        ctx.textAlign = align;
        let x = box.x * scale;
        if (align === 'center') x = (box.x + (box.w || 0) / 2) * scale;
        if (align === 'right') x = (box.x + (box.w || 0)) * scale;
        const y = (box.y + (box.fontSize || 14)) * scale;
        if ((box.maxLines || 1) > 1) {
          ctx.textAlign = 'left';
          wrapText(ctx, text, box.x * scale, y, (box.w || 400) * scale, (box.fontSize || 14) * 1.25 * scale, box.maxLines);
        } else {
          const t = (box.transform === 'uppercase') ? String(text).toUpperCase() : String(text);
          ctx.fillText(t, x, y);
        }
        ctx.textAlign = 'left';
      };

      const nameColor = this.nickEl ? this.nickEl.style.color : (layout.NICKNAME && layout.NICKNAME.color);
      const statsColor = (this.statEls.npo && this.statEls.npo.style.color) || layout.STAT_COLOR;
      drawBox(Object.assign({}, layout.NICKNAME, { color: nameColor }), this.nickEl.textContent);
      if (this.creditEl && this.showCredit) drawBox(layout.CREDIT, this.creditEl.textContent);
      if (this.typeEl) drawBox(layout.TYPE, this.typeEl.textContent);
      if (this.heightEl) drawBox(layout.HEIGHT, this.heightEl.textContent);
      if (this.massEl) drawBox(layout.MASS, this.massEl.textContent);
      drawBox(layout.BIO, this.bioEl.textContent);
      if (layout.POWER) {
        const pTitle = this.powerEl.dataset.powerTitle || '';
        const pValue = this.powerEl.dataset.powerValue || '';
        if (pValue) {
          drawBox(Object.assign({}, layout.POWER, { align: 'left', w: Math.round(layout.POWER.w * 0.62) }), pTitle);
          drawBox(Object.assign({}, layout.POWER, {
            align: 'right',
            x: layout.POWER.x,
            w: layout.POWER.w,
          }), pValue);
        } else {
          drawBox(layout.POWER, pTitle || this.powerEl.textContent);
        }
      }
      if (layout.ABILITY) {
        const aTitle = this.abilityEl.dataset.abilityTitle || '';
        const aValue = this.abilityEl.dataset.abilityValue || '';
        if (aValue) {
          drawBox(Object.assign({}, layout.ABILITY, { align: 'left', w: Math.round(layout.ABILITY.w * 0.62) }), aTitle);
          drawBox(Object.assign({}, layout.ABILITY, {
            align: 'right',
            x: layout.ABILITY.x,
            w: layout.ABILITY.w,
          }), aValue);
        } else {
          drawBox(layout.ABILITY, aTitle || this.abilityEl.textContent);
        }
      }
      drawBox(layout.HP, this.hpEl.textContent);
      (layout.STATS || []).forEach((s) => {
        drawBox({
          x: s.x, y: s.y, w: s.w, fontSize: s.fontSize, fontWeight: '700',
          color: statsColor, align: s.align || 'left',
        }, this.stats[s.key] != null ? String(this.stats[s.key]) : '');
      });

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

  function wrapText(ctx, text, x, y, maxWidth, lineHeight, maxLines) {
    const words = String(text).split(/\s+/);
    let line = '';
    let yy = y;
    let lines = 0;
    for (let n = 0; n < words.length && lines < maxLines; n++) {
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
    if (lines < maxLines) ctx.fillText(line, x, yy);
  }

  global.CardobotStudio = CardStudio;
})(window);
