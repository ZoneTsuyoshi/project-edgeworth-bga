/**
 * 通信確立（状態 81・82）の args の解釈、選択の検証、コストの内訳の行、送信する引数の組み立て（008 §3-4、§3-5、009 §3.7）。
 * args（`_private`）: `linkable`（`[{ cell, selectable, sats: [{ satId, cost: { area, pioneer, total }, minSales, useMods? }] }]`）、`hand`、`sellValue`。
 * 売却すれば払える区域は破線、払えない区域はハイライトしない（012 §3.9）。コストの値はサーバーが計算したもの。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { cheapest, compact, costWith, jsonParam, parseMods, payState, validMods } from './common.js';

/**
 * @typedef {object} LinkOption
 * @property {number} cell
 * @property {number} satId
 * @property {number} area 区域コスト（特殊通信確立の分を含む）
 * @property {number} pioneer 先駆コスト（すでに置かれているキューブに対する最悪値）
 * @property {number} total
 * @property {import('./common.js').ModsInfo | null} mods
 */

/**
 * @param {any} raw `_private.linkable`
 * @returns {LinkOption[]}
 */
export function parseLinkable(raw) {
  /** @type {LinkOption[]} */
  const list = [];
  for (const c of Array.isArray(raw) ? raw : []) {
    for (const s of Array.isArray(c.sats) ? c.sats : []) {
      list.push({
        cell: Number.parseInt(String(c.cell), 10),
        satId: Number.parseInt(String(s.satId), 10),
        area: Number.parseInt(String(s.cost?.area), 10) || 0,
        pioneer: Number.parseInt(String(s.cost?.pioneer), 10) || 0,
        total: Number.parseInt(String(s.cost?.total), 10) || 0,
        mods: parseMods(s.useMods),
      });
    }
  }
  return list;
}

/**
 * 組（区域, 衛星）。売っても（起動の修正を使っても）払えない組は選べない。
 * @param {LinkOption[]} options
 * @param {import('./common.js').Funds} funds
 * @returns {import('./common.js').PairEntry<LinkOption & { pay: ReturnType<typeof payState> }>[]}
 */
export function linkPairs(options, funds) {
  return options.map((o) => {
    const pay = payState(o.total, funds);
    const best = payState(cheapest(o.total, o.mods), funds);
    return { a: o.cell, b: o.satId, usable: best.kind !== 'short', data: { ...o, pay } };
  });
}

/**
 * 区域のハイライトの種類。払える組があれば通常、売却すれば払える組だけなら破線、どれも払えなければ出さない。
 * @param {import('./common.js').PairEntry<{ pay: ReturnType<typeof payState> }>[]} pairs その区域の組
 * @returns {'selectable' | 'sellable' | null}
 */
export function cellKind(pairs) {
  const usable = pairs.filter((p) => p.usable);
  if (usable.length === 0) {
    return null;
  }
  return usable.some((p) => p.data.pay.kind === 'free') ? 'selectable' : 'sellable';
}

/**
 * 起動の修正を反映したコストと支払い状況。
 * @param {LinkOption} option
 * @param {string[]} chosenMods
 * @param {import('./common.js').Funds} funds
 */
export function linkQuote(option, chosenMods, funds) {
  const mods = validMods(option.mods, chosenMods);
  const cost = costWith(option.total, option.mods, mods);
  return { cost, pay: payState(cost, funds), mods };
}

/**
 * コストの内訳を、数値の組として返す（文面は呼び出し側で翻訳する）。起動の修正を使うときは「modsReduction」で差を示す。
 * @param {LinkOption} option
 * @param {number} cost 修正を反映した最終コスト
 * @param {number} maya 所持マヤ
 * @returns {{ area: number, pioneer: number, modsReduction: number, total: number, left: number, free: boolean }}
 */
export function linkBreakdown(option, cost, maya) {
  return {
    area: option.area,
    pioneer: option.pioneer,
    modsReduction: Math.max(0, option.total - cost),
    total: cost,
    left: maya - cost,
    free: cost === 0,
  };
}

/**
 * 確定できるか。
 * @param {{ op: string, cell: number | null, satId: number | null, sell: number[] }} sel
 * @param {{ pay: ReturnType<typeof payState> | null }} ctx
 * @returns {{ ok: boolean, reason?: string }}
 */
export function validateLink(sel, ctx) {
  if (sel.op === 'pass') {
    return { ok: true };
  }
  if (sel.cell === null || sel.satId === null) {
    return { ok: false, reason: 'incomplete' };
  }
  if (ctx.pay === null || ctx.pay.kind === 'short') {
    return { ok: false, reason: 'cannotPay' };
  }
  return sel.sell.length >= ctx.pay.need ? { ok: true } : { ok: false, reason: 'needMoreSales' };
}

/**
 * actConfirmLink に送る引数。
 * @param {{ op: string, cell?: number | null, satId?: number | null, sell?: number[], useMods?: string[] }} sel
 * @returns {Record<string, unknown>}
 */
export function linkActionArgs(sel) {
  if (sel.op !== 'link') {
    return { op: sel.op };
  }
  return compact({ op: 'link', cell: sel.cell, satId: sel.satId, sell: sel.sell ?? [], useMods: jsonParam(sel.useMods ?? []) });
}

/**
 * 確定済みの内容から選択を復元する。
 * @param {any} choice
 * @returns {{ op: string | null, cell: number | null, satId: number | null, sell: number[], useMods: string[] }}
 */
export function linkSelectionFrom(choice) {
  return {
    op: typeof choice?.op === 'string' && choice.op !== 'pass' ? choice.op : null,
    cell: choice?.cell === undefined ? null : Number.parseInt(String(choice.cell), 10),
    satId: choice?.sat === undefined ? null : Number.parseInt(String(choice.sat), 10),
    sell: Array.isArray(choice?.sell) ? choice.sell.map(Number) : [],
    useMods: Array.isArray(choice?.useMods) ? choice.useMods.map(String) : [],
  };
}
