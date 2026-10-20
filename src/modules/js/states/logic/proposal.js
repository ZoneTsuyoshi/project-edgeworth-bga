/**
 * 企画（状態 91）: 引いたカードから残すカードを選び、企画のオプション（P068・P070・P072）と売却を同梱する（008 §3-8、011 §6-5）。
 * args（`_private`）: `drawn`（引いたカード）、`keep`（残す枚数。P072 の購入分を含まない）、`extra`（オプション。なければ `[]`）、`hand`、`sellValue`。
 * `extra` = `{ handDiscard: bool, handCandidates: number[], options: [{ hostKey, cardKey, kind, reward, tiers, affordable, affordableBySelling, maxKeep }] }`。
 * 確定は actConfirmKeep(keep, handDiscard, optionUses, sell)。optionUses = 宿主の鍵 → 値（P070 は捨てる手札のカード ID、P072 は払う額）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { compact, entriesOf, intList, jsonParam, payState } from './common.js';

export const EXTRA_DISCARD = 'EXTRA_DISCARD';
export const KEEP_PURCHASE = 'KEEP_PURCHASE';

/**
 * @typedef {object} ProposalOption
 * @property {string} hostKey
 * @property {string} cardKey カードの識別子（nameId）
 * @property {string} kind EXTRA_DISCARD / KEEP_PURCHASE
 * @property {number} reward EXTRA_DISCARD: 得るマヤ
 * @property {{ pay: number, plus: number }[]} tiers KEEP_PURCHASE: 払う額と残す枚数の増分（払う額の昇順）
 * @property {number[]} affordable 売らずに払える額
 * @property {number[]} affordableBySelling 売却を使えば払える額
 *
 * @typedef {object} ProposalPlan
 * @property {number[]} drawn
 * @property {number} keep
 * @property {boolean} handDiscard P068: 手札からも捨てる候補に加えられる
 * @property {ProposalOption[]} options
 */

/**
 * @param {Record<string, any> | null} mine `_private`
 * @returns {ProposalPlan}
 */
export function parseProposal(mine) {
  const extra = mine?.extra;
  const options = (Array.isArray(extra?.options) ? extra.options : []).map((/** @type {any} */ o) => ({
    hostKey: String(o.hostKey),
    cardKey: String(o.cardKey),
    kind: String(o.kind),
    reward: Number.parseInt(String(o.reward), 10) || 0,
    tiers: entriesOf(o.tiers).map(([pay, plus]) => ({ pay: Number.parseInt(pay, 10), plus: Number.parseInt(String(plus), 10) })).sort((a, b) => a.pay - b.pay),
    affordable: intList(o.affordable),
    affordableBySelling: intList(o.affordableBySelling),
  }));
  return {
    drawn: intList(mine?.drawn),
    keep: Number.parseInt(String(mine?.keep), 10) || 0,
    handDiscard: extra?.handDiscard === true,
    options,
  };
}

/**
 * @typedef {object} ProposalSelection
 * @property {number[]} keep 残す（引いたカードの ID）
 * @property {number[]} handDiscard P068: 手札から捨てるカード
 * @property {Record<string, number>} uses 使うオプション（宿主の鍵 → 値）
 * @property {number[]} sell 売却するカード
 */

/**
 * 残す枚数（サーバーの ProposalOptions::keepCount と validate の式）。引いた枚数を超えない。
 * @param {ProposalPlan} plan
 * @param {ProposalSelection} sel
 * @returns {number}
 */
export function requiredKeep(plan, sel) {
  let keep = plan.keep;
  for (const option of plan.options) {
    if (option.kind === KEEP_PURCHASE && sel.uses[option.hostKey] !== undefined) {
      keep += option.tiers.find((t) => t.pay === sel.uses[option.hostKey])?.plus ?? 0;
    }
  }
  const base = Math.max(0, Math.min(keep, plan.drawn.length));
  return Math.min(plan.drawn.length, base + sel.handDiscard.length);
}

/**
 * オプションで払うマヤの合計（P072）。
 * @param {ProposalPlan} plan
 * @param {ProposalSelection} sel
 * @returns {number}
 */
export function optionPayment(plan, sel) {
  return plan.options.filter((o) => o.kind === KEEP_PURCHASE && sel.uses[o.hostKey] !== undefined).reduce((sum, o) => sum + (sel.uses[o.hostKey] ?? 0), 0);
}

/**
 * P070 で追加に捨てるカード。
 * @param {ProposalPlan} plan
 * @param {ProposalSelection} sel
 * @returns {number[]}
 */
export function extraDiscards(plan, sel) {
  return plan.options.filter((o) => o.kind === EXTRA_DISCARD && sel.uses[o.hostKey] !== undefined).map((o) => sel.uses[o.hostKey]);
}

/**
 * 売却に使えない手札（P068 で捨てる、P070 で捨てる）。
 * @param {ProposalPlan} plan
 * @param {ProposalSelection} sel
 * @returns {number[]}
 */
export function reservedHand(plan, sel) {
  return [...sel.handDiscard, ...extraDiscards(plan, sel)];
}

/**
 * 確定できるか（サーバーの ProposalOptions::validate と同じ条件。表示の絞り込みで、最終検証はサーバー）。
 * @param {ProposalPlan} plan
 * @param {ProposalSelection} sel
 * @param {{ hand: number[], maya: number, price: number }} ctx
 * @returns {{ ok: boolean, reason?: string, need: number, requiredKeep: number }}
 */
export function validateProposal(plan, sel, ctx) {
  const required = requiredKeep(plan, sel);
  const unique = (/** @type {number[]} */ list) => new Set(list).size === list.length;
  const result = (/** @type {string | undefined} */ reason, /** @type {number} */ need = 0) => ({ ok: reason === undefined, reason, need, requiredKeep: required });
  if (!unique(sel.keep) || sel.keep.some((id) => !plan.drawn.includes(id))) {
    return result('invalidKeep');
  }
  if (sel.keep.length !== required) {
    return result('keepCount');
  }
  if (sel.handDiscard.length > 0 && !plan.handDiscard) {
    return result('handDiscardNotAllowed');
  }
  if (sel.handDiscard.some((id) => !ctx.hand.includes(id))) {
    return result('invalidHandDiscard');
  }
  const extras = extraDiscards(plan, sel);
  if (extras.some((id) => !ctx.hand.includes(id) && !sel.keep.includes(id))) {
    return result('invalidExtraDiscard');
  }
  const claimed = [...sel.handDiscard, ...extras, ...sel.sell];
  if (!unique(claimed)) {
    return result('cardUsedTwice');
  }
  if (sel.sell.some((id) => !ctx.hand.includes(id))) {
    return result('invalidSell');
  }
  const reserved = reservedHand(plan, sel);
  const pay = payState(optionPayment(plan, sel), { maya: ctx.maya, price: ctx.price, sellable: ctx.hand.filter((id) => !reserved.includes(id)).length });
  if (pay.kind === 'short') {
    return result('cannotPay');
  }
  return sel.sell.length >= pay.need ? result(undefined, pay.need) : result('needMoreSales', pay.need);
}

/**
 * actConfirmKeep に送る引数。optionUses は JSON 文字列。
 * @param {ProposalSelection} sel
 * @returns {Record<string, unknown>}
 */
export function proposalActionArgs(sel) {
  return compact({
    keep: sel.keep,
    handDiscard: sel.handDiscard,
    optionUses: Object.keys(sel.uses).length === 0 ? undefined : jsonParam(sel.uses),
    sell: sel.sell,
  });
}

/**
 * 確定済みの内容から選択を復元する。
 * @param {any} choice
 * @returns {ProposalSelection}
 */
export function proposalSelectionFrom(choice) {
  /** @type {Record<string, number>} */
  const uses = {};
  for (const [key, value] of entriesOf(choice?.optionUses)) {
    uses[key] = Number.parseInt(String(value), 10);
  }
  return { keep: intList(choice?.keep), handDiscard: intList(choice?.handDiscard), uses, sell: intList(choice?.sell) };
}
