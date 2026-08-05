/**
 * Gold-standard Card-o-Bot face layout (357×500 → 606×800).
 * Face type is Press Start 2P (wide pixel). Sizes/caps are tuned for that font.
 */
(function (global) {
  'use strict';

  const REF_W = 357;
  const REF_H = 500;
  const CARD_W = 606;
  const CARD_H = 800;
  const SX = CARD_W / REF_W;
  const SY = CARD_H / REF_H;

  function sx(n) { return Math.round(n * SX); }
  function sy(n) { return Math.round(n * SY); }

  const CardLayout = {
    CARD_W,
    CARD_H,
    FACE_FONT: '"Press Start 2P", "Courier New", monospace',
    // Hard display caps for Press Start 2P (AI aims match; face shrinks, never ellipsis).
    LIMITS: {
      nickname: 16,
      power_name: 12,
      ability_name: 12,
      ability_line: 8,
      power_value: 8,
      bio: 90,
    },
    NAME_INKS: {
      slate: 'rgba(88,88,88,1)',
      charcoal: 'rgba(54,62,70,1)',
      rose: 'rgba(196,96,112,1)',
      teal: 'rgba(44,127,162,1)',
      ink: 'rgba(36,48,58,1)',
      copper: 'rgba(168,96,72,1)',
    },
    STAT_INKS: {
      white: 'rgba(255,255,255,0.95)',
      mint: 'rgba(149,245,227,0.95)',
      ice: 'rgba(179,236,250,0.95)',
      peach: 'rgba(249,187,170,0.95)',
      butter: 'rgba(255,229,192,0.95)',
      foam: 'rgba(218,239,237,0.95)',
    },
    BG_PRESETS: {
      dock_teal: { h: 195, s: 65, l: 40 },
      rose_mist: { h: 350, s: 42, l: 42 },
      mint_hull: { h: 162, s: 38, l: 38 },
      night_steel: { h: 210, s: 22, l: 32 },
      warm_cargo: { h: 28, s: 40, l: 40 },
      deep_cyan: { h: 188, s: 55, l: 34 },
    },
    // Square art well: fill the printed window; bottom clears the "i" cutout (~y 575).
    ART: (() => {
      const y = sy(48);
      const side = Math.min(sx(322), 568 - y);
      const x = Math.round((CARD_W - side) / 2);
      return { x, y, w: side, h: side };
    })(),
    NICKNAME: {
      x: sx(36), y: sy(28), w: sx(230), h: sy(18),
      fontSize: 11, fontWeight: '400', color: 'rgba(88,88,88,1)',
      align: 'left', maxLines: 1, lineHeight: 1, minFontSize: 6,
    },
    CREDIT: {
      x: sx(130), y: sy(338), w: sx(190), h: sy(14),
      fontSize: 7, fontWeight: '400', color: 'rgba(255,236,228,0.95)',
      align: 'right', maxLines: 1, lineHeight: 1,
      textShadow: '0 0 3px rgba(0,0,0,0.95), 0 1px 0 rgba(0,0,0,0.8), 0 -1px 0 rgba(0,0,0,0.55)',
    },
    // Meta row: Press Start is wide; keep tiny and MASS widest.
    TYPE: {
      x: sx(68), y: sy(356), w: sx(88), h: sy(10),
      fontSize: 5, fontWeight: '400', color: 'rgba(88,88,88,1)',
      align: 'left', maxLines: 1, lineHeight: 1, minFontSize: 4,
    },
    HEIGHT: {
      x: sx(158), y: sy(356), w: sx(78), h: sy(10),
      fontSize: 5, fontWeight: '400', color: 'rgba(88,88,88,1)',
      align: 'right', maxLines: 1, lineHeight: 1, minFontSize: 4,
    },
    MASS: {
      x: sx(238), y: sy(356), w: sx(90), h: sy(10),
      fontSize: 5, fontWeight: '400', color: 'rgba(88,88,88,1)',
      align: 'right', maxLines: 1, lineHeight: 1, minFontSize: 4,
    },
    BIO: {
      x: sx(72), y: sy(376), w: sx(248), h: sy(46),
      fontSize: 5, fontWeight: '400', color: 'rgba(44,127,162,1)',
      align: 'left', maxLines: 6, lineHeight: 1.35, minFontSize: 4,
    },
    POWER: {
      x: sx(148), y: sy(433), w: sx(176), h: sy(13),
      fontSize: 6, fontWeight: '400', color: 'rgba(218,239,237,0.95)',
      align: 'left', maxLines: 1, transform: 'uppercase', valign: 'center',
      lineHeight: 1, minFontSize: 4,
    },
    ABILITY: {
      x: sx(148), y: sy(447), w: sx(176), h: sy(13),
      fontSize: 6, fontWeight: '400', color: 'rgba(249,187,170,0.95)',
      align: 'left', maxLines: 1, transform: 'uppercase', valign: 'center',
      lineHeight: 1, minFontSize: 4,
    },
    HP: {
      x: sx(288), y: sy(38), w: sx(44), h: sy(16),
      fontSize: 11, fontWeight: '400', color: 'rgba(224,126,140,0.95)',
      align: 'center', maxLines: 1, lineHeight: 1, minFontSize: 6,
    },
    STATS: [
      { key: 'npo', label: 'NPO', x: sx(40), y: sy(64), w: sx(48), h: sy(18), fontSize: 9, align: 'left' },
      { key: 'att', label: 'ATT', x: sx(40), y: sy(122), w: sx(48), h: sy(18), fontSize: 9, align: 'left' },
      { key: 'str', label: 'STR', x: sx(40), y: sy(180), w: sx(48), h: sy(18), fontSize: 9, align: 'left' },
      { key: 'los', label: 'LOS', x: sx(40), y: sy(238), w: sx(48), h: sy(18), fontSize: 9, align: 'left' },
      { key: 'con', label: 'CON', x: sx(40), y: sy(296), w: sx(48), h: sy(18), fontSize: 9, align: 'left' },
    ],
    STAT_COLOR: 'rgba(255,255,255,0.95)',
    BACKS: [
      'back-01.jpg', 'back-02.jpg', 'back-03.png', 'back-04.png',
      'back-05.png', 'back-06.png', 'back-07.jpg', 'back-08.jpg',
    ],
  };

  CardLayout.ART_STUDIO = {
    x: CardLayout.ART.x,
    y: CardLayout.ART.y,
    w: CardLayout.ART.w,
    h: CardLayout.ART.h,
  };

  global.CardobotLayout = CardLayout;
})(window);
