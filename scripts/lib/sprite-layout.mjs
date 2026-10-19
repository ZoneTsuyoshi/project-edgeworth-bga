// スプライトの並び順の規則（仕様書 012 第 7.2〜7.4 節）。build-sprites.mjs と check-sprites.mjs が共用する純関数と定数。
// 副作用（ファイル読み書き）は持たない。

const pad = (n, w = 3) => String(n).padStart(w, '0');
const range = (from, to) => Array.from({ length: to - from + 1 }, (_, i) => from + i);

// ---- キー（原本のファイル名から `@4x.png` を除いたもの） ----

/** プロジェクトカード P001〜P099 */
export const PROJECT_KEYS = range(1, 99).map((n) => `P${pad(n)}`);
/** アクション・ミッションの表裏。PREV（プロジェクトの裏）を先頭に置く。012 §7.3 */
export const MISC_KEYS = [
  'PREV',
  ...range(1, 5).map((n) => `A${pad(n)}`), 'AREV',
  ...range(1, 10).map((n) => `M${pad(n)}`), 'MREV',
];
export const REWARD_KEYS = range(1, 24).map((n) => `T${pad(n)}`);
/** 衛星カード。MOON 面 20（形状 5 × 色 4。B・G・R・Y）→ 色別プラン R006〜R025（20）の順 */
export const ROCKET_KEYS = [
  ...range(1, 5).flatMap((s) => ['B', 'G', 'R', 'Y'].map((c) => `R${pad(s)}${c}`)),
  ...range(6, 25).map((n) => `R${pad(n)}`),
];
/** 区域タイルの識別子（003 §3.1）と原本のファイル名（`data/boards/area/`） */
export const TILE_SOURCES = {
  sea: '海', land: '陸', deep_sea: '深海', reef: '岩礁', forest: '森林', mountain: '山岳', snow: '雪原',
};
export const TILE_KEYS = Object.keys(TILE_SOURCES);

/** 原本のファイル名に空白が入っている例外（CLAUDE.md）。キー → 実際のファイル名（拡張子なし） */
const SOURCE_NAME_EXCEPTIONS = { P037: 'P037 ' };

/**
 * キー → 原本の PNG（`data/` からの相対パス）。
 * @param {string} key
 * @returns {string}
 */
export function cardSource(key) {
  if (key in TILE_SOURCES) return `boards/area/${TILE_SOURCES[key]}@4x.png`;
  const name = SOURCE_NAME_EXCEPTIONS[key] ?? key;
  if (/^P\d{3}$|^PREV$/.test(key)) return `cards/projects/${name}@4x.png`;
  if (/^A\d{3}$|^AREV$/.test(key)) return `cards/actions/${name}@4x.png`;
  if (/^M\d{3}$|^MREV$/.test(key)) return `cards/missions/${name}@4x.png`;
  if (/^T\d{3}$/.test(key)) return `cards/rewards/${name}@4x.png`;
  if (/^R\d{3}[BGRY]?$/.test(key)) return `cards/rockets/${name}@4x.png`;
  throw new Error(`未知のキー: ${key}`);
}

// ---- シート定義 ----

/** zoom シートを置くサブフォルダ（`img/zoom/`。Studio で使えないときは `--flat-zoom` で空にする。012 未確定事項 No.9） */
export const DEFAULT_ZOOM_DIR = 'zoom/';

const CELL_CARD_BASE = { w: 300, h: 419 };
const CELL_CARD_ZOOM = { w: 500, h: 699 };
const CELL_ROCKET_BASE = { w: 400, h: 286 };
const CELL_ROCKET_ZOOM = { w: 600, h: 430 };
/** 区域タイル。原本は 435〜436 × 502〜503 で、深海だけ幅 436。全種が収まる 436 × 503 にして中央寄せで貼る */
const CELL_TILE = { w: 436, h: 503 };

/** 効果アイコンの帯（012 §4.2）。プロジェクトカードの下部のアイコン欄の切り出し位置と縮小後のセル */
export const EFFECT_STRIP = {
  /**
   * 原本（715〜716 × 998〜999）の左上を原点とする切り出し範囲。アイコン欄の枠は、左の縦線が x 169〜173、右の縦線が x 552〜556、
   * 下の線が y 967〜971（全 99 枚で ±1 px の差）。上の線は右半分が段差で高く（y 約 822）、上の文章欄の枠線と並走するので、
   * 上端は y 820 にして、文章欄の枠線のかけらが上端に細く入るのを許容する。
   */
  crop: { left: 164, top: 820, width: 400, height: 156 },
  cell: { w: 300, h: 117 },
  /** 全カード共通の位置で足りるため、カードごとの補正は空。必要なら `{ P012: { dx: 1, dy: 0 } }` のように持つ（build が dx・dy を足す） */
  adjust: {},
};

const slice = (keys, from, count) => keys.slice(from, from + count);

/**
 * シート（ファイルごと）の定義。出力順。`lossless` は可逆圧縮。`mode`: fill = セルへ拡縮（fit fill）、paste = 実寸を中央寄せで貼る、
 * strip = 効果アイコンの帯を切り出して拡縮。`keys` の並びがそのままセルの順（左上から右へ行ごと）。
 */
export const SHEETS = [
  // base（img/ 直下）
  { stem: 'pe_cards_project_1', tier: 'base', cell: CELL_CARD_BASE, cols: 11, rows: 3, mode: 'fill', keys: slice(PROJECT_KEYS, 0, 33) },
  { stem: 'pe_cards_project_2', tier: 'base', cell: CELL_CARD_BASE, cols: 11, rows: 3, mode: 'fill', keys: slice(PROJECT_KEYS, 33, 33) },
  { stem: 'pe_cards_project_3', tier: 'base', cell: CELL_CARD_BASE, cols: 11, rows: 3, mode: 'fill', keys: slice(PROJECT_KEYS, 66, 33) },
  { stem: 'pe_cards_misc', tier: 'base', cell: CELL_CARD_BASE, cols: 6, rows: 3, mode: 'fill', keys: MISC_KEYS },
  { stem: 'pe_cards_reward', tier: 'base', cell: CELL_CARD_BASE, cols: 8, rows: 3, mode: 'fill', keys: REWARD_KEYS },
  { stem: 'pe_cards_rocket', tier: 'base', cell: CELL_ROCKET_BASE, cols: 8, rows: 5, mode: 'fill', keys: ROCKET_KEYS },
  { stem: 'pe_cards_effects', tier: 'base', cell: EFFECT_STRIP.cell, cols: 13, rows: 8, mode: 'strip', keys: PROJECT_KEYS },
  { stem: 'pe_tiles', tier: 'base', cell: CELL_TILE, cols: 4, rows: 2, mode: 'paste', lossless: true, keys: TILE_KEYS },
  // zoom（img/zoom/）
  { stem: 'pe_zoom_project_1', tier: 'zoom', cell: CELL_CARD_ZOOM, cols: 6, rows: 5, mode: 'fill', keys: slice(PROJECT_KEYS, 0, 30) },
  { stem: 'pe_zoom_project_2', tier: 'zoom', cell: CELL_CARD_ZOOM, cols: 6, rows: 5, mode: 'fill', keys: slice(PROJECT_KEYS, 30, 30) },
  { stem: 'pe_zoom_project_3', tier: 'zoom', cell: CELL_CARD_ZOOM, cols: 6, rows: 5, mode: 'fill', keys: slice(PROJECT_KEYS, 60, 30) },
  { stem: 'pe_zoom_misc', tier: 'zoom', cell: CELL_CARD_ZOOM, cols: 6, rows: 5, mode: 'fill', keys: [...slice(PROJECT_KEYS, 90, 9), ...MISC_KEYS] },
  { stem: 'pe_zoom_reward', tier: 'zoom', cell: CELL_CARD_ZOOM, cols: 6, rows: 4, mode: 'fill', keys: REWARD_KEYS },
  { stem: 'pe_zoom_rocket', tier: 'zoom', cell: CELL_ROCKET_ZOOM, cols: 6, rows: 7, mode: 'fill', keys: ROCKET_KEYS },
];

/** コマ・アイコンのシート（012 §7.4）。ロスレス */
export const PIECES_STEM = 'pe_pieces';

/** ファイル名（zoom は zoomDir が前に付く）。 */
export function sheetFile(sheet, zoomDir = DEFAULT_ZOOM_DIR) {
  return `${sheet.tier === 'zoom' ? zoomDir : ''}${sheet.stem}.webp`;
}

export function sheetSize(sheet) {
  return { w: sheet.cols * sheet.cell.w, h: sheet.rows * sheet.cell.h };
}

/** 生成するシートの stem（出力順。pieces を含む） */
export function allStems() {
  return [...SHEETS.map((s) => s.stem), PIECES_STEM];
}

/**
 * キー → 指定解像度でのシート・列・行。プロジェクトカードの効果アイコンの帯は tier = 'effects'。
 * @param {string} key P001・PREV・A001・T001・R001B・R006・sea など
 * @param {'base'|'zoom'|'effects'} tier
 * @returns {{sheet: string, col: number, row: number}}
 */
export function cardPosition(key, tier) {
  for (const s of SHEETS) {
    const isEffects = s.mode === 'strip';
    if (tier === 'effects' ? !isEffects : (isEffects || s.tier !== tier)) continue;
    const i = s.keys.indexOf(key);
    if (i >= 0) return { sheet: s.stem, col: i % s.cols, row: Math.floor(i / s.cols) };
  }
  throw new Error(`キー ${key} は ${tier} のシートにありません`);
}

// ---- コマ・アイコン（012 第 7.4 節） ----

const COLORS = ['b', 'g', 'r', 'y'];
const SATELLITE_SHAPES = ['hammer', 'retro', 'scallop', 'concorde', 'stealth'];
const MAYA_VALUES = [1, 3, 5, 10, 20, 50];
const AREA_LETTERS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

const PIECE_GROUPS = [
  { prefix: 'satellite', cell: { w: 232, h: 132 }, cols: 4, names: SATELLITE_SHAPES.flatMap((s) => COLORS.map((c) => `${s}_${c}`)), src: (n) => `piecies/satellite/${n}@4x.png` },
  { prefix: 'base', cell: { w: 164, h: 228 }, cols: 4, names: COLORS, src: (n) => `piecies/base/base_${n}@4x.png` },
  { prefix: 'fuel', cell: { w: 80, h: 96 }, cols: 4, names: COLORS, src: (n) => `piecies/fuel/fuel_${n}@4x.png` },
  { prefix: 'disk', cell: { w: 80, h: 96 }, cols: 4, names: COLORS, src: (n) => `piecies/disk/disk_${n}@4x.png` },
  { prefix: 'cube', cell: { w: 80, h: 96 }, cols: 4, names: COLORS, src: (n) => `piecies/cube/cube_${n}@4x.png` },
  { prefix: 'maya', cell: { w: 128, h: 132 }, cols: 6, names: MAYA_VALUES.map(String), src: (n) => `piecies/maya/maya${n}@4x.png` },
  { prefix: 'area', cell: { w: 128, h: 140 }, cols: 4, names: AREA_LETTERS, src: (n) => `icons/area/area_${n}@4x.png` },
];

/** コマ・アイコンのシート全体の寸法。グループを上から順に積む（幅は最大のグループ） */
export function piecesSize() {
  const cells = pieceCells();
  return {
    w: Math.max(...cells.map((c) => c.x + c.w)),
    h: Math.max(...cells.map((c) => c.y + c.h)),
  };
}

/**
 * コマ・アイコンのセル。名前は `satellite_hammer_b`、`base_r`、`fuel_g`、`maya_10`、`area_A` の形式。
 * @returns {{name: string, src: string, x: number, y: number, w: number, h: number}[]}
 */
export function pieceCells() {
  const out = [];
  let y = 0;
  for (const g of PIECE_GROUPS) {
    g.names.forEach((n, i) => {
      out.push({
        name: `${g.prefix}_${n}`, src: g.src(n),
        x: (i % g.cols) * g.cell.w, y: y + Math.floor(i / g.cols) * g.cell.h, w: g.cell.w, h: g.cell.h,
      });
    });
    y += Math.ceil(g.names.length / g.cols) * g.cell.h;
  }
  return out;
}
