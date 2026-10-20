/**
 * 配備（状態 61・62）の args の解釈、選択の検証、送信する引数の組み立て（008 §3-6、§3-7、009 §3.7）。
 * args（`_private`）: `targets`（カード番号 → 配備先の候補 `[{ satId, tier, cost, blocked, minSales, selectable, useMods? }]`）、`canDraw`（ボーナスのみ）、`hand`、`sellValue`。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { cheapest, compact, costWith, entriesOf, jsonParam, parseMods, payState, validMods } from './common.js';

/**
 * @typedef {import('./common.js').ModsInfo} ModsInfo
 * @typedef {import('./common.js').Funds} Funds
 * @typedef {object} DeployTarget
 * @property {number} cardId
 * @property {number} satId
 * @property {number} tier 配備後の段（1〜4）
 * @property {number} cost サーバーが計算した配備コスト（起動の修正を使わないとき）
 * @property {string | null} blocked 選べない理由（full / forbidden）
 * @property {ModsInfo | null} mods
 */

/**
 * @param {any} raw `_private.targets`
 * @returns {DeployTarget[]}
 */
export function parseTargets(raw) {
  /** @type {DeployTarget[]} */
  const list = [];
  for (const [card, targets] of entriesOf(raw)) {
    for (const t of Array.isArray(targets) ? targets : []) {
      list.push({
        cardId: Number.parseInt(card, 10),
        satId: Number.parseInt(String(t.satId), 10),
        tier: Number.parseInt(String(t.tier), 10) || 1,
        cost: Number.parseInt(String(t.cost), 10) || 0,
        blocked: t.blocked === null || t.blocked === undefined ? null : String(t.blocked),
        mods: parseMods(t.useMods),
      });
    }
  }
  return list;
}

/**
 * 組（カード, 衛星）の一覧。選べる = 理由がなく、売却を使えば（起動の修正を使えば）払える。配備するカード自身は売れない。
 * @param {DeployTarget[]} targets
 * @param {number[]} hand 今の手札（売却で変わるので args の hand ではなく現在の手札）
 * @param {number} maya 今の所持マヤ
 * @param {number} price 売却額
 * @returns {import('./common.js').PairEntry<DeployTarget & { pay: ReturnType<typeof payState> }>[]}
 */
export function deployPairs(targets, hand, maya, price) {
  return targets
    .filter((t) => hand.includes(t.cardId))
    .map((t) => {
      const funds = { maya, price, sellable: hand.length - 1 };
      const pay = payState(t.cost, funds);
      const best = payState(cheapest(t.cost, t.mods), funds);
      return { a: t.cardId, b: t.satId, usable: t.blocked === null && best.kind !== 'short', data: { ...t, pay } };
    });
}

/**
 * 配備の確定内容の見出しと不足の判定。
 * @param {DeployTarget} target
 * @param {string[]} chosenMods
 * @param {Funds} funds
 * @returns {{ cost: number, pay: ReturnType<typeof payState>, mods: string[] }}
 */
export function deployQuote(target, chosenMods, funds) {
  const mods = validMods(target.mods, chosenMods);
  const cost = costWith(target.cost, target.mods, mods);
  return { cost, pay: payState(cost, funds), mods };
}

/**
 * 確定できるか。売却の枚数が足りていること、売却に配備するカードを含めないこと。
 * @param {{ op: string, cardId: number | null, satId: number | null, sell: number[] }} sel
 * @param {{ pay: ReturnType<typeof payState> | null, canDraw: boolean }} ctx
 * @returns {{ ok: boolean, reason?: string }}
 */
export function validateDeploy(sel, ctx) {
  if (sel.op === 'pass') {
    return { ok: true };
  }
  if (sel.op === 'draw') {
    return ctx.canDraw ? { ok: true } : { ok: false, reason: 'cannotDraw' };
  }
  if (sel.cardId === null || sel.satId === null) {
    return { ok: false, reason: 'incomplete' };
  }
  if (sel.cardId !== null && sel.sell.includes(sel.cardId)) {
    return { ok: false, reason: 'sellsDeployedCard' };
  }
  if (ctx.pay === null || ctx.pay.kind === 'short') {
    return { ok: false, reason: 'cannotPay' };
  }
  return sel.sell.length >= ctx.pay.need ? { ok: true } : { ok: false, reason: 'needMoreSales' };
}

/**
 * actConfirmDeploy に送る引数。
 * @param {{ op: string, cardId?: number | null, satId?: number | null, sell?: number[], useMods?: string[] }} sel
 * @returns {Record<string, unknown>}
 */
export function deployActionArgs(sel) {
  if (sel.op !== 'deploy') {
    return { op: sel.op };
  }
  return compact({ op: 'deploy', cardId: sel.cardId, satId: sel.satId, sell: sel.sell ?? [], useMods: jsonParam(sel.useMods ?? []) });
}

/**
 * 確定済みの内容（`myChoice`）から選択を復元する（[Change my choice] の初期選択。008 §2-2）。
 * @param {any} choice
 * @returns {{ op: string | null, cardId: number | null, satId: number | null, sell: number[], useMods: string[] }}
 */
export function deploySelectionFrom(choice) {
  const op = choice?.op === 'deploy' || choice?.op === 'draw' ? choice.op : null;
  return {
    op,
    cardId: op === 'deploy' ? Number.parseInt(String(choice.card), 10) : null,
    satId: op === 'deploy' ? Number.parseInt(String(choice.sat), 10) : null,
    sell: op === 'deploy' && Array.isArray(choice?.sell) ? choice.sell.map(Number) : [],
    useMods: op === 'deploy' && Array.isArray(choice?.useMods) ? choice.useMods.map(String) : [],
  };
}
