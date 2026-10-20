/**
 * マリガン（状態 13）: 手札から捨てるカードを選ぶ（008 §8-1）。
 * args（`_private`）: `hand`。確定は actConfirmMulligan(discard)。0 枚は [Keep all]（空配列は送信で落ちるので省く）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { compact, intList } from './common.js';

/**
 * 捨てる枚数に応じたボタンの種類。
 * @param {number} count
 * @returns {{ kind: 'keep' | 'mulligan', count: number }}
 */
export function mulliganButton(count) {
  return { kind: count === 0 ? 'keep' : 'mulligan', count };
}

/**
 * 手札にあるカードだけを捨てる対象にする（売却で手札が減ったときに選択を整える）。
 * @param {number[]} picked
 * @param {number[]} hand
 * @returns {number[]}
 */
export function mulliganPicks(picked, hand) {
  return picked.filter((id) => hand.includes(id));
}

/**
 * @param {number[]} discard
 * @returns {Record<string, unknown>}
 */
export function mulliganActionArgs(discard) {
  return compact({ discard });
}

/**
 * 確定済みの内容から選択を復元する。
 * @param {any} choice
 * @returns {number[]}
 */
export function mulliganSelectionFrom(choice) {
  return intList(choice?.discard);
}
