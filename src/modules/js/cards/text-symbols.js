/**
 * カードの英語の効果文に出る記号（`[Maya]`・`[Fuel]` など。002 §10）をアイコンの HTML に置き換える純粋関数。
 * スプライトの配置情報（sprites.js）には依存しない。画像の指定は呼び出し側が `resolve` で渡す（単体テストのため）。
 * 置き換え表（SYMBOLS）はここ 1 か所に置く。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { escapeHtml } from '../ui/dom.js';

/**
 * @typedef {{ label: string, piece: string, framed?: boolean }} SymbolDef
 * label = 代替テキスト（aria-label・title）に入れる英語名。piece = コマ・アイコンのスプライト名（sprite-layout の pieceSprite）。
 * framed = 四角い枠を CSS で重ねる（現在は未使用。予算は作者が追加した原本 `予算` を使う）。
 */

/** @type {Record<string, SymbolDef>} キーは角括弧の中身を小文字にしたもの */
export const SYMBOLS = {
  maya: { label: 'Maya', piece: 'icon_maya' },
  budget: { label: 'Budget', piece: 'icon_budget' },
  fuel: { label: 'Fuel', piece: 'icon_fuel' },
  crew: { label: 'Crew', piece: 'icon_crew' },
  device: { label: 'Device', piece: 'icon_device' },
  software: { label: 'Software', piece: 'icon_software' },
  land: { label: 'Land', piece: 'icon_land' },
  sea: { label: 'Sea', piece: 'icon_sea' },
  ...Object.fromEntries(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'].map((l) => [l, { label: `Area ${l.toUpperCase()}`, piece: `area_${l.toUpperCase()}` }])),
};

/** 使われているコマ・アイコンの名前の一覧（重複なし）。スプライト側に全部あることの確認に使う */
export const SYMBOL_PIECES = [...new Set(Object.values(SYMBOLS).map((s) => s.piece))];

/**
 * 1 つの記号のアイコン要素（HTML 文字列）。
 * @param {SymbolDef} def
 * @param {{ style: string, aspect: number }} css 背景の指定（`style` 属性の中身）とセルの幅 / 高さ
 * @returns {string}
 */
export function symbolHtml(def, css) {
  const label = escapeHtml(def.label);
  const style = `${css.style}--pe-glyph-aspect:${Math.round(css.aspect * 1e4) / 1e4}`;
  return `<span class="pe-glyph${def.framed === true ? ' pe-glyph-framed' : ''}" role="img" aria-label="${label}" title="${label}" style="${escapeHtml(style)}"></span>`;
}

/**
 * 効果文を HTML にする。先に全体をエスケープし、そのあとで `[記号]` を置き換える（記号の外の文字は必ずエスケープされる）。
 * 表にない記号や、画像が引けない記号（resolve が null）はそのまま文字で残す。
 * @param {string} text 英語の効果文（改行は `\n`）
 * @param {(piece: string) => ({ style: string, aspect: number } | null)} resolve コマ・アイコンの名前 → 背景の指定
 * @returns {string}
 */
export function symbolsToHtml(text, resolve) {
  return escapeHtml(text)
    .replace(/\[([A-Za-z]+)\]/g, (whole, name) => {
      const def = Object.hasOwn(SYMBOLS, name.toLowerCase()) ? SYMBOLS[name.toLowerCase()] : undefined;
      const css = def === undefined ? null : resolve(def.piece);
      return def === undefined || css === null ? whole : symbolHtml(def, css);
    })
    .replaceAll('\n', '<br>');
}
