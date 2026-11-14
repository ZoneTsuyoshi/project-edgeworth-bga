/**
 * 効果の窓の配備の組（PAIRS、kind: deploy。R005・P011・P045・P074）を、手札のカードと衛星の直接選択で作るための判定
 * （仕様書 012 追補 2026-11-14 B-3）。DOM に依存しない純関数。
 * 組 = [カード番号, 衛星 ID]。候補はサーバーが列挙したもの（preview.costs と同じ並び）。コストの出所はサーバーの preview で、加算以外はしない。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { payState } from './common.js';
import { pairCost } from './effects.js';

/** 衛星 1 機に配備できるカードの枚数（サーバーの ProjectCardRepository::MAX_DEPLOYED と同じ。最終検証はサーバー） */
const MAX_DEPLOYED = 4;

/**
 * @typedef {[number, number]} Pair
 * @typedef {{ card: number | null, sat: number | null }} Pending 押したがまだ組になっていないカード / 衛星
 * @typedef {{ chosen: Pair[], pending: Pending }} PairState
 * @typedef {'notTarget' | 'full' | 'used' | 'max'} Block
 */

/**
 * @param {any[]} raw
 * @returns {Pair[]}
 */
export function normalizePairs(raw) {
  return (Array.isArray(raw) ? raw : []).map((p) => [Number(p[0]), Number(p[1])]);
}

/**
 * 組を足せない理由（足せるなら null）。max = 1 のときは今の組を入れ替えるので、選択済みの組は数えない。
 * @param {Pair[]} candidates
 * @param {Pair[]} chosen
 * @param {number} max
 * @param {Record<number, number>} deployed 衛星 ID → 今の配備枚数
 * @param {number} card
 * @param {number} sat
 * @returns {Block | null}
 */
export function pairBlock(candidates, chosen, max, deployed, card, sat) {
  if (!candidates.some((p) => p[0] === card && p[1] === sat)) {
    return 'notTarget';
  }
  const effective = max === 1 ? [] : chosen;
  if (effective.length >= max) {
    return 'max';
  }
  if (effective.some((p) => p[0] === card)) {
    return 'used';
  }
  const onSat = effective.filter((p) => p[1] === sat).length;
  if ((deployed[sat] ?? 0) + onSat >= MAX_DEPLOYED) {
    return 'full';
  }
  return null;
}

/**
 * 配備先が 1 機しかない（THIS）なら、その衛星。
 * @param {Pair[]} candidates
 * @returns {number | null}
 */
export function soleSatellite(candidates) {
  const sats = new Set(candidates.map((p) => p[1]));
  return sats.size === 1 ? [...sats][0] : null;
}

/**
 * 手札の各カード（候補に載るもの）が今押せるか。sat が決まっていればその衛星への組だけを見る。
 * 値は押せないときの理由（押せるなら null）。理由は、衛星が決まっていないときは最後に見つかった理由。
 * @param {Pair[]} candidates
 * @param {Pair[]} chosen
 * @param {number} max
 * @param {Record<number, number>} deployed
 * @param {number | null} sat
 * @returns {Map<number, Block | null>}
 */
export function cardOptions(candidates, chosen, max, deployed, sat) {
  /** @type {Map<number, Block | null>} */
  const result = new Map();
  for (const [card, target] of candidates) {
    const block = sat !== null && target !== sat ? 'notTarget' : pairBlock(candidates, chosen, max, deployed, card, target);
    if (result.get(card) !== null) {
      result.set(card, block === null ? null : (result.get(card) ?? block));
    }
  }
  return result;
}

/**
 * 衛星 1 機ごとに今押せるか。card が決まっていればそのカードの組だけを見る。値は押せないときの理由（押せるなら null）。
 * @param {Pair[]} candidates
 * @param {Pair[]} chosen
 * @param {number} max
 * @param {Record<number, number>} deployed
 * @param {number[]} sats 自分の衛星
 * @param {number | null} card
 * @returns {Map<number, Block | null>}
 */
export function satOptions(candidates, chosen, max, deployed, sats, card) {
  return new Map(sats.map((sat) => {
    const cards = card === null ? [...new Set(candidates.filter((p) => p[1] === sat).map((p) => p[0]))] : [card];
    if (cards.length === 0 || !candidates.some((p) => p[1] === sat && (card === null || p[0] === card))) {
      return [sat, 'notTarget'];
    }
    /** @type {Block | null} */
    let reason = 'notTarget';
    for (const c of cards) {
      const block = pairBlock(candidates, chosen, max, deployed, c, sat);
      if (block === null) {
        reason = null;
        break;
      }
      reason = block;
    }
    return [sat, reason];
  }));
}

/**
 * 組を足す（max = 1 は入れ替え）。足せなければそのまま。
 * @param {Pair[]} chosen
 * @param {Pair} pair
 * @param {number} max
 * @returns {Pair[]}
 */
export function addPair(chosen, pair, max) {
  return max === 1 ? [pair] : [...chosen, pair];
}

/**
 * そのカードの組を外す。
 * @param {Pair[]} chosen
 * @param {number} card
 * @returns {Pair[]}
 */
export function removePairOfCard(chosen, card) {
  return chosen.filter((p) => p[0] !== card);
}

/**
 * 手札のカードを押した結果。衛星が決まっている（先に押した）か、配備先が 1 機だけなら組ができる。そうでなければカードを保留にする。
 * 押せないカードは何も変えない。
 * @param {Pair[]} candidates
 * @param {PairState} state
 * @param {{ max: number, deployed: Record<number, number>, card: number }} ctx
 * @returns {PairState}
 */
export function pickCard(candidates, state, ctx) {
  const { max, deployed, card } = ctx;
  const sat = state.pending.sat ?? soleSatellite(candidates);
  if (sat !== null) {
    if (pairBlock(candidates, state.chosen, max, deployed, card, sat) !== null) {
      return state;
    }
    return { chosen: addPair(state.chosen, [card, sat], max), pending: { card: null, sat: null } };
  }
  const anySat = candidates.some((p) => p[0] === card && pairBlock(candidates, state.chosen, max, deployed, card, p[1]) === null);
  return anySat ? { chosen: max === 1 ? [] : state.chosen, pending: { card, sat: null } } : state;
}

/**
 * 衛星を押した結果。カードが決まっている（先に押した）なら組ができる。押し直すと保留の衛星を外す。
 * @param {Pair[]} candidates
 * @param {PairState} state
 * @param {{ max: number, deployed: Record<number, number>, sat: number }} ctx
 * @returns {PairState}
 */
export function pickSatellite(candidates, state, ctx) {
  const { max, deployed, sat } = ctx;
  const card = state.pending.card;
  if (card !== null) {
    if (pairBlock(candidates, state.chosen, max, deployed, card, sat) !== null) {
      return state;
    }
    return { chosen: addPair(state.chosen, [card, sat], max), pending: { card: null, sat: null } };
  }
  if (state.pending.sat === sat) {
    return { chosen: state.chosen, pending: { card: null, sat: null } };
  }
  const usable = candidates.some((p) => p[1] === sat && pairBlock(candidates, state.chosen, max, deployed, p[0], sat) === null);
  return usable ? { chosen: state.chosen, pending: { card: null, sat } } : state;
}

/**
 * 手札のゾーンの選択が変わった結果を、組の状態に反映する（外れたカードは組・保留から外し、増えたカードは押した扱い）。
 * @param {Pair[]} candidates
 * @param {PairState} state
 * @param {number[]} next 手札のゾーンの選択（組のカード + 保留のカード）
 * @param {{ max: number, deployed: Record<number, number> }} ctx
 * @returns {PairState}
 */
export function applyHandChange(candidates, state, next, ctx) {
  const prev = selectedCards(state);
  let current = state;
  for (const card of prev.filter((c) => !next.includes(c))) {
    current = current.pending.card === card
      ? { chosen: current.chosen, pending: { ...current.pending, card: null } }
      : { chosen: removePairOfCard(current.chosen, card), pending: current.pending };
  }
  for (const card of next.filter((c) => !prev.includes(c))) {
    current = pickCard(candidates, current, { ...ctx, card });
  }
  return current;
}

/**
 * 手札のゾーンで選択中として出すカード（組のカード + 保留のカード）。
 * @param {PairState} state
 * @returns {number[]}
 */
export function selectedCards(state) {
  return [...state.chosen.map((p) => p[0]), ...(state.pending.card === null ? [] : [state.pending.card])];
}

/**
 * 組のコストの合計（サーバーの preview の値の加算だけ）。
 * @param {Record<string, any>} preview
 * @param {Pair[]} chosen
 * @returns {number}
 */
export function totalPairCost(preview, chosen) {
  return chosen.reduce((sum, pair) => sum + (pairCost(preview, pair) ?? 0), 0);
}

/**
 * 2 組目以降はコストが見積もり（サーバーの preview は 1 枚だけ配備したときの値。同じ衛星への 2 枚目は 1 枚目の後で再計算される）。
 * @param {number} index 組の並び（0 始まり）
 * @returns {boolean}
 */
export function isEstimated(index) {
  return index >= 1;
}

/**
 * 組を足したときの支払い状況（これまでの組のコストの合計 + この組。売れる手札は、配備するカード・捨てるカードを除く）。
 * @param {Record<string, any>} preview
 * @param {Pair[]} chosen 今の組（max = 1 の入れ替えのときは呼ぶ側が空にする）
 * @param {Pair} pair
 * @param {{ maya: number, price: number, handCount: number, otherReserved: number }} funds otherReserved = 配備以外で売れない枚数（P074 の捨てる枚数など）
 * @returns {{ cost: number, pay: { kind: 'free' | 'sell' | 'short', need: number, short: number, left: number } }}
 */
export function pairPayState(preview, chosen, pair, funds) {
  const cost = pairCost(preview, pair) ?? 0;
  const total = totalPairCost(preview, chosen) + cost;
  const sellable = Math.max(0, funds.handCount - chosen.length - 1 - funds.otherReserved);
  return { cost, pay: payState(total, { maya: funds.maya, price: funds.price, sellable }) };
}
