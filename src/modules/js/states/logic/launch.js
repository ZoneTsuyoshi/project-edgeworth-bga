/**
 * 打ち上げ / 転回（状態 71・72）の args の解釈、選択の検証、送信する引数の組み立て（008 §3-2、§3-3、009 §3.7）。
 * args（`_private`）: `launchable`（メインのみ。`[{ satId, cost, blocked, minSales, selectable, useMods? }]`）、
 * `turnable`（`[{ satId, dir, blocked, selectable }]`。稼働中の衛星）、`canBudget`（ボーナスのみ）、`hand`、`sellValue`。向きは NE / E / SE / SW / W / NW（6 方向）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { cheapest, compact, costWith, jsonParam, parseMods, payState, validMods } from './common.js';

/** 向きの候補（6 方向。003 §4.1） */
export const DIRECTIONS = ['NE', 'E', 'SE', 'SW', 'W', 'NW'];

/**
 * @typedef {object} LaunchOption
 * @property {number} satId
 * @property {number} cost
 * @property {string | null} blocked active / no_project / no_launch_pad
 * @property {import('./common.js').ModsInfo | null} mods
 */

/**
 * @param {any} raw `_private.launchable`
 * @returns {LaunchOption[]}
 */
export function parseLaunchable(raw) {
  return (Array.isArray(raw) ? raw : []).map((/** @type {any} */ o) => ({
    satId: Number.parseInt(String(o.satId), 10),
    cost: Number.parseInt(String(o.cost), 10) || 0,
    blocked: o.blocked === null || o.blocked === undefined ? null : String(o.blocked),
    mods: parseMods(o.useMods),
  }));
}

/**
 * @typedef {object} TurnOption
 * @property {number} satId
 * @property {string | null} dir 現在の向き
 * @property {string | null} blocked forbidden / no_inactive_satellite
 */

/**
 * @param {any} raw `_private.turnable`
 * @returns {TurnOption[]}
 */
export function parseTurnable(raw) {
  return (Array.isArray(raw) ? raw : []).map((/** @type {any} */ o) => ({
    satId: Number.parseInt(String(o.satId), 10),
    dir: o.dir === null || o.dir === undefined ? null : String(o.dir),
    blocked: o.blocked === null || o.blocked === undefined ? null : String(o.blocked),
  }));
}

/**
 * 打ち上げの候補の選べる状態（売却を使えば払えるものは選べる）。
 * @param {LaunchOption} option
 * @param {import('./common.js').Funds} funds
 * @returns {{ usable: boolean, pay: ReturnType<typeof payState> }}
 */
export function launchState(option, funds) {
  const pay = payState(option.cost, funds);
  const best = payState(cheapest(option.cost, option.mods), funds);
  return { usable: option.blocked === null && best.kind !== 'short', pay };
}

/**
 * 起動の修正を反映した打ち上げコストと支払い状況。
 * @param {LaunchOption} option
 * @param {string[]} chosenMods
 * @param {import('./common.js').Funds} funds
 */
export function launchQuote(option, chosenMods, funds) {
  const mods = validMods(option.mods, chosenMods);
  const cost = costWith(option.cost, option.mods, mods);
  return { cost, pay: payState(cost, funds), mods };
}

/**
 * 確定できるか。
 * @param {{ op: string, satId: number | null, dir: string | null, sell: number[] }} sel
 * @param {{ pay: ReturnType<typeof payState> | null, canBudget: boolean }} ctx
 * @returns {{ ok: boolean, reason?: string }}
 */
export function validateLaunch(sel, ctx) {
  if (sel.op === 'pass') {
    return { ok: true };
  }
  if (sel.op === 'budget') {
    return ctx.canBudget ? { ok: true } : { ok: false, reason: 'cannotBudget' };
  }
  if (sel.satId === null) {
    return { ok: false, reason: 'incomplete' };
  }
  if (sel.dir === null || !DIRECTIONS.includes(sel.dir)) {
    return { ok: false, reason: 'needDirection' };
  }
  if (sel.op === 'turn') {
    return { ok: true };
  }
  if (ctx.pay === null || ctx.pay.kind === 'short') {
    return { ok: false, reason: 'cannotPay' };
  }
  return sel.sell.length >= ctx.pay.need ? { ok: true } : { ok: false, reason: 'needMoreSales' };
}

/**
 * actConfirmLaunch に送る引数。
 * @param {{ op: string, satId?: number | null, dir?: string | null, sell?: number[], useMods?: string[] }} sel
 * @returns {Record<string, unknown>}
 */
export function launchActionArgs(sel) {
  if (sel.op === 'launch') {
    return compact({ op: 'launch', satId: sel.satId, dir: sel.dir, sell: sel.sell ?? [], useMods: jsonParam(sel.useMods ?? []) });
  }
  if (sel.op === 'turn') {
    return compact({ op: 'turn', satId: sel.satId, dir: sel.dir });
  }
  return { op: sel.op };
}

/**
 * 確定済みの内容から選択を復元する。
 * @param {any} choice
 * @returns {{ op: string | null, satId: number | null, dir: string | null, sell: number[], useMods: string[] }}
 */
export function launchSelectionFrom(choice) {
  const op = typeof choice?.op === 'string' && choice.op !== 'pass' ? choice.op : null;
  return {
    op,
    satId: choice?.sat === undefined ? null : Number.parseInt(String(choice.sat), 10),
    dir: typeof choice?.dir === 'string' ? choice.dir : null,
    sell: Array.isArray(choice?.sell) ? choice.sell.map(Number) : [],
    useMods: Array.isArray(choice?.useMods) ? choice.useMods.map(String) : [],
  };
}

/**
 * 衛星 1 機に対してできる操作（衛星を先に選んだあと、[Launch] / [Turn] のどちらを押せるか）。
 * 打ち上げは非稼働の衛星（メインのみ。売却を使えば払えるものを含む）、転回は稼働中の衛星。
 * @param {number | null} satId
 * @param {{ launchable: LaunchOption[], turnable: TurnOption[], funds: import('./common.js').Funds, isBonus: boolean }} ctx
 * @returns {{ launch: boolean, turn: boolean }}
 */
export function satelliteOps(satId, ctx) {
  if (satId === null) {
    return {
      launch: !ctx.isBonus && ctx.launchable.some((o) => launchState(o, ctx.funds).usable),
      turn: ctx.turnable.some((o) => o.blocked === null),
    };
  }
  return {
    launch: !ctx.isBonus && ctx.launchable.some((o) => o.satId === satId && launchState(o, ctx.funds).usable),
    turn: ctx.turnable.some((o) => o.satId === satId && o.blocked === null),
  };
}
