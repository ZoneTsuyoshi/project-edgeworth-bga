/**
 * カードの要素を作る（仕様書 012 §4.1、§12）。スプライトは % で当てる。縦横比はクラスで決め、角の丸みは画像の透過に任せる。
 * スプライトが読めなかったときのため、カード名の文字を背面に置く（012 エッジケース「画像の読み込み失敗」）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { applySprite, cardSprite } from './sprite-layout.js';
import { cardInfo } from './card-text.js';
import { el } from '../ui/dom.js';

/**
 * 画面全体で共有する依存（BGA の外でも動くように注入する）。
 * @typedef {object} Env
 * @property {(file: string) => string} imgUrl 画像ファイル名 → URL（BGA では `bga.images.getImgUrl`）
 * @property {(playerId: number) => HTMLElement | null} playerPanel BGA のプレイヤーパネル（無ければ null）
 * @property {() => boolean} animationsActive リプレイの高速再生などでは false
 * @property {() => number | null} currentPlayerId 観戦者は null
 * @property {() => boolean} selectionMode 選択モード中か（タップは選択、長押しが拡大。012 §4.6）
 */

/**
 * @typedef {object} CardOptions
 * @property {'base' | 'zoom'} [tier]
 * @property {string[]} [classes]
 * @property {boolean} [back] 裏面として扱う（PREV / AREV / MREV のキーを渡す）
 */

/**
 * カード 1 枚の要素。キーの種類で縦長 / 横長を決める（衛星カード R… は横長）。
 * @param {Env} env
 * @param {string} key P001・PREV・A001・AREV・M001・MREV・T001・R001B・R006 など
 * @param {CardOptions} [opts]
 * @returns {HTMLElement}
 */
export function createCard(env, key, opts = {}) {
  const landscape = /^R\d/.test(key);
  const info = cardInfo(key);
  const face = el('div', { class: 'pe-card-face' });
  applySprite(face, cardSprite(key, opts.tier ?? 'base'), env.imgUrl);
  const node = el('div', {
    class: ['pe-card', landscape ? 'pe-card-landscape' : 'pe-card-portrait', ...(opts.classes ?? [])].join(' '),
    dataset: { key, name: info?.name ?? key },
  }, [face]);
  return node;
}

/**
 * 山札・捨て札・ミッションの裏面など、キーが決まっている裏向きのカード。
 * @param {Env} env
 * @param {'PREV' | 'AREV' | 'MREV'} key
 * @param {string[]} [classes]
 * @returns {HTMLElement}
 */
export function createCardBack(env, key, classes = []) {
  return createCard(env, key, { classes: ['pe-card-back', ...classes] });
}

/**
 * 枚数などの小さなバッジ（バッジは右上の外側。012 §10 教訓 6）。
 * @param {string | number} text
 * @param {string} [extraClass]
 * @returns {HTMLElement}
 */
export function createBadge(text, extraClass = '') {
  return el('span', { class: `pe-badge ${extraClass}`.trim() }, [String(text)]);
}
