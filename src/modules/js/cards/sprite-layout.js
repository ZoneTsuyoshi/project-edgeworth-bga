/**
 * スプライトの位置を CSS にする（仕様書 012 §7.3）。位置の元は scripts/build-sprites.mjs が生成する `sprites.js`
 * （`npm run build:sprites`。.gitignore 対象）。background-size / background-position は Safari の小数ピクセルのずれを避けるため、
 * すべて % で書く（012 §3.3、§10 教訓 10）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { SPRITES } from './sprites.js';

/**
 * @typedef {{ file: string, size: string, position: string }} SpriteCss
 * @typedef {'base' | 'zoom' | 'effects'} CardTier
 */

/**
 * @param {number} n
 * @param {number} [w]
 * @returns {string}
 */
const pad = (n, w = 3) => String(n).padStart(w, '0');

/** @param {number} no プロジェクトカードの番号（1〜99） */
export const projectKey = (no) => `P${pad(no)}`;
/** @param {number} no アクション番号（1〜5） */
export const actionKey = (no) => `A${pad(no)}`;
/** @param {number} no ミッション番号（1〜10） */
export const missionKey = (no) => `M${pad(no)}`;

/**
 * セルを % の size / position にする。列・行が 1 つしかない軸の position は 0%。
 * @param {number} cols
 * @param {number} rows
 * @param {number} col
 * @param {number} row
 * @returns {{ size: string, position: string }}
 */
export function percentCss(cols, rows, col, row) {
  const pct = (/** @type {number} */ v) => `${Math.round(v * 1e5) / 1e5}%`;
  return {
    size: `${pct(cols * 100)} ${pct(rows * 100)}`,
    position: `${pct(cols > 1 ? (col / (cols - 1)) * 100 : 0)} ${pct(rows > 1 ? (row / (rows - 1)) * 100 : 0)}`,
  };
}

/**
 * カード（プロジェクト・衛星・アクション・ミッション・褒賞）のスプライト指定。
 * @param {string} key P001・PREV・A001・M001・T001・R001B・R006 など
 * @param {CardTier} [tier] 通常は 'base'。拡大は 'zoom'。プロジェクトカードの効果アイコンの帯は 'effects'
 * @returns {SpriteCss | null} キーが無いとき null
 */
export function cardSprite(key, tier = 'base') {
  const entry = /** @type {Record<string, any>} */ (SPRITES.cards)[key];
  const pos = entry?.[tier];
  if (pos === undefined) {
    return null;
  }
  const sheet = /** @type {Record<string, any>} */ (SPRITES.sheets)[pos.sheet];
  return { file: sheet.file, ...percentCss(sheet.cols, sheet.rows, pos.col, pos.row) };
}

/**
 * 区域タイルのスプライト指定。
 * @param {string} type sea / land / deep_sea / reef / forest / mountain / snow
 * @returns {SpriteCss | null}
 */
export function tileSprite(type) {
  const pos = /** @type {Record<string, any>} */ (SPRITES.tiles)[type];
  if (pos === undefined) {
    return null;
  }
  const sheet = /** @type {Record<string, any>} */ (SPRITES.sheets)[pos.sheet];
  return { file: sheet.file, ...percentCss(sheet.cols, sheet.rows, pos.col, pos.row) };
}

/**
 * コマ・アイコンのスプライト指定（名前は `satellite_hammer_b`、`base_r`、`fuel_g`、`cube_b`、`maya_1`、`area_A` など。012 §7.4）。
 * セルの寸法はグループごとに違うので、シートの寸法とセルの位置から % を出す。
 * @param {string} name
 * @returns {(SpriteCss & { aspect: number }) | null} aspect はセルの幅 / 高さ
 */
export function pieceSprite(name) {
  const item = /** @type {Record<string, any>} */ (SPRITES.pieces.items)[name];
  if (item === undefined) {
    return null;
  }
  const sheet = SPRITES.pieces.sheet;
  const c = item.cell;
  const pct = (/** @type {number} */ v) => `${Math.round(v * 1e5) / 1e5}%`;
  return {
    file: sheet.file,
    size: `${pct((sheet.w / c.w) * 100)} ${pct((sheet.h / c.h) * 100)}`,
    position: `${pct(sheet.w > c.w ? (c.x / (sheet.w - c.w)) * 100 : 0)} ${pct(sheet.h > c.h ? (c.y / (sheet.h - c.h)) * 100 : 0)}`,
    aspect: c.w / c.h,
  };
}

/**
 * 要素にスプライトを当てる。画像の URL は env.imgUrl（BGA では `bga.images.getImgUrl`）で作る。
 * @param {HTMLElement} el
 * @param {SpriteCss | null} sprite
 * @param {(file: string) => string} imgUrl
 */
export function applySprite(el, sprite, imgUrl) {
  if (sprite === null) {
    return;
  }
  el.style.backgroundImage = `url("${imgUrl(sprite.file)}")`;
  el.style.backgroundSize = sprite.size;
  el.style.backgroundPosition = sprite.position;
}

/** プレイヤー色（0 = 青、1 = 緑、2 = 赤、3 = 黄。002 §3.6）→ コマの色の綴り */
export const COLOR_LETTERS = ['b', 'g', 'r', 'y'];
