/**
 * Gold-standard Card-o-Bot face layout (357×500 → 606×800).
 * Name stays right of art left edge. Credit lives inside the art well.
 * Power/ability tops track printed INFERENCE POWER / SPECIAL ABILITY labels.
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
    // Hard display caps (AI aims match; face shrinks, never ellipsis).
    LIMITS: {
      nickname: 22,
      power_name: 18,
      ability_name: 16,
      ability_line: 12,
      power_value: 10,
      bio: 220,
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
    ART: { x: sx(27), y: sy(56), w: sx(303), h: sy(303) },
    NICKNAME: {
      // Vertically centered in the light name well; width leaves room for HP tab
      x: sx(36), y: sy(26), w: sx(230), h: sy(24),
      fontSize: sy(22), fontWeight: '700', color: 'rgba(88,88,88,1)',
      align: 'left', maxLines: 1,
    },
    // Inside art well, bottom-right; readable over any paint
    CREDIT: {
      x: sx(130), y: sy(330), w: sx(190), h: sy(22),
      fontSize: sy(14), fontWeight: '700', color: 'rgba(255,236,228,0.95)',
      align: 'right', maxLines: 1,
      textShadow: '0 0 3px rgba(0,0,0,0.95), 0 1px 0 rgba(0,0,0,0.8), 0 -1px 0 rgba(0,0,0,0.55)',
    },
    // Under-art strip: equal air above type row and below bio (art ~359, panel ~425)
    TYPE: {
      x: sx(70), y: sy(364), w: sx(105), h: sy(12),
      fontSize: sy(9), fontWeight: '400', color: 'rgba(88,88,88,1)',
      align: 'left', maxLines: 1,
    },
    HEIGHT: {
      // Keep a clear gap before MASS (wells must not overlap).
      x: sx(175), y: sy(364), w: sx(70), h: sy(12),
      fontSize: sy(9), fontWeight: '400', color: 'rgba(88,88,88,1)',
      align: 'right', maxLines: 1,
    },
    MASS: {
      x: sx(255), y: sy(364), w: sx(73), h: sy(12),
      fontSize: sy(9), fontWeight: '400', color: 'rgba(88,88,88,1)',
      align: 'right', maxLines: 1,
    },
    BIO: {
      x: sx(72), y: sy(379), w: sx(256), h: sy(42),
      fontSize: sy(12), fontWeight: '400', color: 'rgba(44,127,162,1)',
      align: 'left', maxLines: 4, lineHeight: 1.2,
    },
    // Dark panel ~y 425. White divider ~x 141–145.
    // One full-width value column for both rows (power title+value share the power well).
    POWER: {
      x: sx(150), y: sy(433), w: sx(172), h: sy(13),
      fontSize: sy(11), fontWeight: '700', color: 'rgba(218,239,237,0.95)',
      align: 'left', maxLines: 1, transform: 'uppercase', valign: 'center', lineHeight: 1,
    },
    ABILITY: {
      // One line only: shrink-to-fit, never wrap.
      x: sx(150), y: sy(447), w: sx(172), h: sy(13),
      fontSize: sy(11), fontWeight: '700', color: 'rgba(249,187,170,0.95)',
      align: 'left', maxLines: 1, transform: 'uppercase', valign: 'center', lineHeight: 1,
    },
    HP: {
      // Digit cutout right of printed HP glyphs; wide enough for 3-digit values
      x: sx(290), y: sy(37), w: sx(40), h: sy(20),
      fontSize: sy(20), fontWeight: '700', color: 'rgba(224,126,140,0.95)',
      align: 'center', maxLines: 1,
    },
    // Left edge of digits lined up with left edge of printed NPO/ATT/… titles
    STATS: [
      { key: 'npo', label: 'NPO', x: sx(40), y: sy(62), w: sx(40), h: sy(24), fontSize: sy(18), align: 'left' },
      { key: 'att', label: 'ATT', x: sx(40), y: sy(120), w: sx(40), h: sy(24), fontSize: sy(18), align: 'left' },
      { key: 'str', label: 'STR', x: sx(40), y: sy(178), w: sx(40), h: sy(24), fontSize: sy(18), align: 'left' },
      { key: 'los', label: 'LOS', x: sx(40), y: sy(236), w: sx(40), h: sy(24), fontSize: sy(18), align: 'left' },
      { key: 'con', label: 'CON', x: sx(40), y: sy(294), w: sx(40), h: sy(24), fontSize: sy(18), align: 'left' },
    ],
    STAT_COLOR: 'rgba(255,255,255,0.95)',
    BACKS: [
      'back-01.jpg', 'back-02.jpg', 'back-03.png', 'back-04.png',
      'back-05.png', 'back-06.png', 'back-07.jpg', 'back-08.jpg',
    ],
  };

  CardLayout.ART_STUDIO = {
    x: Math.round(CARD_W * 0.079),
    y: Math.round(CARD_H * 0.1125),
    w: Math.round(CARD_W * 0.842),
    h: Math.round(CARD_H * 0.6375),
  };

  global.CardobotLayout = CardLayout;
})(window);
