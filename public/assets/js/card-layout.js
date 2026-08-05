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
    // Press Start first; CJK/other scripts fall through to Noto / system.
    FACE_FONT: '"Press Start 2P", "Noto Sans SC", "Noto Sans JP", "Noto Sans", "Segoe UI", sans-serif',
    // Matches printed INFERENCE POWER / SPECIAL ABILITY chrome on the frame.
    FACE_SANS: 'Roboto, "Noto Sans", "Segoe UI", sans-serif',
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
    // Art well: 85% card width fills the printed window; bottom clears the "i" (~y 575).
    ART: (() => {
      const w = Math.round(CARD_W * 0.85);
      const x = Math.round((CARD_W - w) / 2);
      const y = sy(48);
      const h = Math.min(w, 568 - y);
      return { x, y, w, h };
    })(),
    // Sizes are px at CARD_W (606). Display scales from card width in studio.
    // Target Press Start hierarchy: nick ≥ HP > stats > bio ≈ power > meta.
    NICKNAME: {
      x: sx(36), y: sy(26), w: sx(230), h: sy(26),
      fontSize: 22, fontWeight: '400', color: 'rgba(88,88,88,1)',
      align: 'left', maxLines: 1, lineHeight: 1, minFontSize: 11,
    },
    CREDIT: {
      x: sx(130), y: sy(338), w: sx(190), h: sy(14),
      fontSize: 11, fontWeight: '500', color: 'rgba(255,236,228,0.95)',
      align: 'right', maxLines: 1, lineHeight: 1, minFontSize: 8,
      fontFamily: 'sans',
      textShadow: '0 0 3px rgba(0,0,0,0.95), 0 1px 0 rgba(0,0,0,0.8), 0 -1px 0 rgba(0,0,0,0.55)',
    },
    TYPE: {
      x: sx(64), y: sy(354), w: sx(96), h: sy(14),
      fontSize: 12, fontWeight: '500', color: 'rgba(88,88,88,1)',
      align: 'left', maxLines: 1, lineHeight: 1, minFontSize: 9,
      fontFamily: 'sans',
    },
    HEIGHT: {
      x: sx(158), y: sy(354), w: sx(90), h: sy(14),
      fontSize: 12, fontWeight: '500', color: 'rgba(88,88,88,1)',
      align: 'center', maxLines: 1, lineHeight: 1, minFontSize: 9,
      fontFamily: 'sans',
    },
    MASS: {
      x: sx(246), y: sy(354), w: sx(90), h: sy(14),
      fontSize: 12, fontWeight: '500', color: 'rgba(88,88,88,1)',
      align: 'right', maxLines: 1, lineHeight: 1, minFontSize: 9,
      fontFamily: 'sans',
    },
    BIO: {
      x: sx(72), y: sy(374), w: sx(248), h: sy(42),
      fontSize: 11, fontWeight: '400', color: 'rgba(44,127,162,1)',
      align: 'left', maxLines: 5, lineHeight: 1.35, minFontSize: 7,
    },
    // Sans to match printed INFERENCE POWER / SPECIAL ABILITY; center-anchored in value column.
    POWER: {
      x: sx(148), y: sy(432), w: sx(176), h: sy(16),
      fontSize: 13, fontWeight: '500', color: 'rgba(218,239,237,0.95)',
      align: 'center', maxLines: 1, transform: 'uppercase', valign: 'center',
      lineHeight: 1, minFontSize: 8, fontFamily: 'sans',
    },
    ABILITY: {
      x: sx(148), y: sy(447), w: sx(176), h: sy(16),
      fontSize: 13, fontWeight: '500', color: 'rgba(249,187,170,0.95)',
      align: 'center', maxLines: 1, transform: 'uppercase', valign: 'center',
      lineHeight: 1, minFontSize: 8, fontFamily: 'sans',
    },
    HP: {
      x: sx(288), y: sy(36), w: sx(44), h: sy(22),
      fontSize: 20, fontWeight: '400', color: 'rgba(224,126,140,0.95)',
      align: 'center', maxLines: 1, lineHeight: 1, minFontSize: 11,
    },
    STATS: [
      { key: 'npo', label: 'NPO', x: sx(40), y: sy(64), w: sx(48), h: sy(18), fontSize: 14, align: 'left' },
      { key: 'att', label: 'ATT', x: sx(40), y: sy(122), w: sx(48), h: sy(18), fontSize: 14, align: 'left' },
      { key: 'str', label: 'STR', x: sx(40), y: sy(180), w: sx(48), h: sy(18), fontSize: 14, align: 'left' },
      { key: 'los', label: 'LOS', x: sx(40), y: sy(238), w: sx(48), h: sy(18), fontSize: 14, align: 'left' },
      { key: 'con', label: 'CON', x: sx(40), y: sy(296), w: sx(48), h: sy(18), fontSize: 14, align: 'left' },
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
