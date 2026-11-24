/**
 * 初期手札のドラフト（状態 15）: 5 枚から 1 枚選び、残りを左隣へ回す（013 §6）。
 * args: 公開 `pickNo`・`pickTotal`・`packSize`、`_private`: `myChoice`・`pack`・`hand`・`sellValue`。確定は actConfirmDraftPick(card)。
 * 渡す相手・受け取る相手は席順（getAllDatas.playerOrder）から計算する（席順は変わらない）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { intList } from './common.js';

/**
 * 渡す相手（席順の次。最後の人の次は先頭）と受け取る相手（席順の前。先頭の前は最後の人）。
 * @param {number[]} order 席順（プレイヤー ID）
 * @param {number | null} me
 * @returns {{ passTo: number, receiveFrom: number } | null} 観戦者・席順にない人は null
 */
export function draftNeighbors(order, me) {
  const i = me === null ? -1 : order.indexOf(me);
  if (i < 0 || order.length < 2) {
    return null;
  }
  return { passTo: order[(i + 1) % order.length], receiveFrom: order[(i - 1 + order.length) % order.length] };
}

/**
 * 確定済みの内容から選択を復元する。
 * @param {any} choice
 * @returns {number | null}
 */
export function draftSelectionFrom(choice) {
  const ids = intList(choice?.card === undefined || choice?.card === null ? [] : [choice.card]);
  return ids.length === 0 ? null : ids[0];
}

/**
 * 選んだカードを、手元のカードにあるものだけに整える。
 * @param {number | null} picked
 * @param {number[]} cards
 * @returns {number | null}
 */
export function draftPicks(picked, cards) {
  return picked !== null && cards.includes(picked) ? picked : null;
}

/**
 * @param {number} card
 * @returns {Record<string, unknown>}
 */
export function draftActionArgs(card) {
  return { card };
}

/**
 * 見出しの材料（文面は DraftState が `_()` で組み立てる）。
 * @param {Record<string, any>} args 状態の args（公開部分）
 * @param {number[]} order 席順
 * @param {number | null} me
 * @returns {{ pickNo: number, pickTotal: number, packSize: number, passTo: number, receiveFrom: number, same: boolean, last: boolean } | null}
 */
export function draftHeading(args, order, me) {
  const n = draftNeighbors(order, me);
  if (n === null) {
    return null;
  }
  const pickNo = Number.parseInt(String(args?.pickNo ?? 1), 10) || 1;
  const pickTotal = Number.parseInt(String(args?.pickTotal ?? 4), 10) || 4;
  return {
    pickNo,
    pickTotal,
    packSize: Number.parseInt(String(args?.packSize ?? 0), 10) || 0,
    passTo: n.passTo,
    receiveFrom: n.receiveFrom,
    same: n.passTo === n.receiveFrom,
    last: pickNo >= pickTotal,
  };
}
