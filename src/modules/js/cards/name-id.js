/**
 * カードの識別子（`nameId`。サーバーの効果の窓が送る cardKey）からカードのキー・名前を引く（002 §3.2）。
 * 窓の `waiting.sources` や問いの `cardKey`、起動の修正の候補の `cardKey` はカード名ではなく `nameId`（例: `lanchester`）なので、ここで引く。
 * 元は card-info.js（生成物）。スプライトには依存しない（テストできる）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { CARD_INFO } from './card-info.js';

/** @type {Map<string, string> | null} */
let index = null;

/** @returns {Map<string, string>} nameId → カードのキー（P072 など。衛星は最初の面） */
function buildIndex() {
  const map = new Map();
  const info = /** @type {Record<string, Record<string, { nameId?: string }>>} */ (/** @type {unknown} */ (CARD_INFO));
  for (const group of ['projects', 'satellites']) {
    for (const [key, card] of Object.entries(info[group])) {
      if (card.nameId !== undefined && !map.has(card.nameId)) {
        map.set(card.nameId, key);
      }
    }
  }
  return map;
}

/**
 * @param {string} nameId
 * @returns {string | null} P072 など。見つからなければ null
 */
export function keyOfNameId(nameId) {
  index ??= buildIndex();
  return index.get(nameId) ?? null;
}

/**
 * 表示用の名前。見つからなければ nameId をそのまま返す（snake_case を単語に直す）。
 * @param {string} nameId
 * @returns {string}
 */
export function nameOfNameId(nameId) {
  const key = keyOfNameId(nameId);
  const info = /** @type {Record<string, Record<string, { name?: string, shape?: string, plan?: string }>>} */ (/** @type {unknown} */ (CARD_INFO));
  if (key !== null) {
    const card = key.startsWith('P') ? info.projects[key] : info.satellites[key];
    if (card?.name !== undefined) {
      return card.name;
    }
    if (card?.shape !== undefined) {
      return `${card.shape} (${card.plan})`;
    }
  }
  return nameId.replaceAll('_', ' ');
}

/**
 * 起動の修正の宿主の鍵（'P:19' = プロジェクトカード 19、'S:13' = 衛星 13 の衛星カード）→ カードのキー。
 * @param {string} hostKey
 * @returns {{ kind: string, src: number, cardKey: string | null }}
 */
export function parseHostKey(hostKey) {
  const [kind, src] = String(hostKey).split(':');
  const n = Number.parseInt(src ?? '0', 10) || 0;
  return { kind: kind ?? '', src: n, cardKey: kind === 'P' ? `P${String(n).padStart(3, '0')}` : null };
}
