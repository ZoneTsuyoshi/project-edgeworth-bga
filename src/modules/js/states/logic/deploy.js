/**
 * 配備（状態 61）の args の解釈、選択の検証、送信する引数の組み立て（008 §3-6、§3-7、009 §3.7）。
 * 配備のボーナス（追加の配備 / 1 枚引く）。追加の配備は [Deploy another card] で 1 枚目を先に確定（ステージ）してから 2 枚目を選ぶ（009 追補 2026-11-12）。
 * args（`_private`）: `targets`（カード番号 → 配備先の候補 `[{ satId, tier, cost, blocked, minSales, selectable, useMods? }]`）、
 * `bonus`（ボーナス保持者か）、`canDraw`（保持者のみ。S0 のときだけ真になりうる）、`hand`、`sellValue`、
 * `stage`（0 = S0 未ステージ / 1 = S1 ステージ済み）、`canReset`、`stage1`（S1 のみ: 1 枚目 `{card, satId, tier, paid, sold}`）、`resetBlockedReason`（S1 のみ）。
 * S1 の `targets` は、1 枚目が衛星に付いた実際の盤面での 2 枚目の候補。クライアントは見積もりを自前で計算せず、売却の枚数の判定だけをここで行う。
 * 売却は 1 枚ごとに、そのカードのコストに対して行う。
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
 * @typedef {{ op: 'draw' }} DeployExtra S0 の追加の行動（1 枚引く）。追加の配備は S1 で別の選択（DeploySelection）として持つ
 * @typedef {object} DeploySelection 1 枚の配備の選択（S0 の 1 枚目、S1 の 2 枚目）
 * @property {number | null} cardId 配備するカード
 * @property {number | null} satId 配備先の衛星
 * @property {number[]} sell 売却（このカードのコストに対する）
 * @property {string[]} useMods 使う起動の修正
 * @property {DeployExtra | null} extra S0 のみ: 追加の行動
 * @typedef {object} DeployStageInfo
 * @property {boolean} staged S1 か
 * @property {{ card: number, satId: number, tier: number, paid: number, sold: number[] } | null} first S1 のステージ済みの 1 枚目
 * @property {boolean} canReset
 * @property {string | null} resetBlockedReason
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
 * S0 の 1 枚目と S1 の 2 枚目の候補（どちらもサーバーの `targets`）。
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
 * 配備するカード（売れないカード）。
 * @param {Pick<DeploySelection, 'cardId'>} sel
 * @returns {number[]}
 */
export function deployedCards(sel) {
  return sel.cardId === null ? [] : [sel.cardId];
}

/**
 * 確定できるか（S0 の 1 枚目と、S1 の 2 枚目で共通）。売却の枚数が足りていること、売却に配備するカードを含めないこと。
 * S0 では何も選んでいなければ確定できない（何もしないときは [Pass]）。S1 は 2 枚目なしでも確定できるので `optional` を使う。
 * @param {DeploySelection} sel
 * @param {{ pay: ReturnType<typeof payState> | null, canDraw: boolean, optional?: boolean }} ctx pay はこのカードのコストの支払い状況
 * @returns {{ ok: boolean, reason?: string }}
 */
export function validateDeploy(sel, ctx) {
  const started = sel.cardId !== null || sel.satId !== null;
  const done = sel.cardId !== null && sel.satId !== null;
  const extra = sel.extra;
  if (extra?.op === 'draw' && !ctx.canDraw) {
    return { ok: false, reason: 'cannotDraw' };
  }
  if (started && !done) {
    return { ok: false, reason: 'incomplete' };
  }
  if (!done) {
    // 何も選んでいない: S0 は引くときだけ、S1（optional）は 2 枚目なしで確定できる
    return ctx.optional === true || extra !== null ? { ok: true } : { ok: false, reason: 'incomplete' };
  }
  if (deployedCards(sel).some((id) => sel.sell.includes(id))) {
    return { ok: false, reason: 'sellsDeployedCard' };
  }
  if (ctx.pay === null || ctx.pay.kind === 'short') {
    return { ok: false, reason: 'cannotPay' };
  }
  return sel.sell.length >= ctx.pay.need ? { ok: true } : { ok: false, reason: 'needMoreSales' };
}

/**
 * actConfirmDeploy に送る引数（S0）。1 枚目が決まっていれば deploy、なければ pass（引くだけのこともある）。
 * @param {Partial<DeploySelection>} sel
 * @returns {Record<string, unknown>}
 */
export function deployActionArgs(sel) {
  const extraArg = sel.extra?.op === 'draw' ? JSON.stringify({ op: 'draw' }) : undefined;
  const main = sel.cardId !== null && sel.cardId !== undefined && sel.satId !== null && sel.satId !== undefined;
  if (!main) {
    return compact({ op: 'pass', extra: extraArg });
  }
  return compact({ op: 'deploy', cardId: sel.cardId, satId: sel.satId, sell: sel.sell ?? [], useMods: jsonParam(sel.useMods ?? []), extra: extraArg });
}

/**
 * actStageDeploy に送る引数（S0 → S1。[Deploy another card] で 1 枚目をステージする）。
 * @param {Pick<DeploySelection, 'cardId' | 'satId' | 'sell' | 'useMods'>} sel
 * @returns {Record<string, unknown>}
 */
export function deployStageArgs(sel) {
  return compact({ cardId: sel.cardId, satId: sel.satId, sell: sel.sell, useMods: jsonParam(sel.useMods) });
}

/**
 * S1 の actConfirmDeploy に送る引数。サーバーは `extra` だけを見る（2 枚目 `{op: deploy, card, sat, sell?, useMods?}` か `{op: pass}`）。
 * @param {Pick<DeploySelection, 'cardId' | 'satId' | 'sell' | 'useMods'>} sel
 * @returns {Record<string, unknown>}
 */
export function deploySecondArgs(sel) {
  const second = sel.cardId !== null && sel.satId !== null;
  const extra = second ? compact({ op: 'deploy', card: sel.cardId, sat: sel.satId, sell: sel.sell, useMods: sel.useMods }) : { op: 'pass' };
  return { op: 'pass', extra: JSON.stringify(extra) };
}

/**
 * 確定済みの内容（`myChoice`）から S0 の選択を復元する（[Change my choice] の初期選択。008 §2-2）。
 * @param {any} choice
 * @returns {DeploySelection}
 */
export function deploySelectionFrom(choice) {
  const main = choice?.op === 'deploy';
  return {
    cardId: main ? Number.parseInt(String(choice.card), 10) : null,
    satId: main ? Number.parseInt(String(choice.sat), 10) : null,
    sell: main && Array.isArray(choice?.sell) ? choice.sell.map(Number) : [],
    useMods: main && Array.isArray(choice?.useMods) ? choice.useMods.map(String) : [],
    extra: choice?.extra?.op === 'draw' ? { op: 'draw' } : null,
  };
}

/**
 * S1 の確定済みの内容から 2 枚目の選択を復元する（`extra` だけを見る）。
 * @param {any} choice
 * @returns {DeploySelection}
 */
export function deploySecondFrom(choice) {
  const e = choice?.extra;
  const deploy = e?.op === 'deploy';
  return {
    cardId: deploy ? Number.parseInt(String(e.card), 10) : null,
    satId: deploy ? Number.parseInt(String(e.sat), 10) : null,
    sell: deploy && Array.isArray(e.sell) ? e.sell.map(Number) : [],
    useMods: deploy && Array.isArray(e.useMods) ? e.useMods.map(String) : [],
    extra: null,
  };
}

/**
 * `_private` の段の情報（S0 / S1、ステージ済みの 1 枚目、[Reset] の可否と理由）。
 * @param {any} mine
 * @returns {DeployStageInfo}
 */
export function parseStage(mine) {
  const staged = Number.parseInt(String(mine?.stage ?? 0), 10) === 1;
  const f = mine?.stage1;
  const first = staged && f !== undefined && f !== null
    ? {
      card: Number.parseInt(String(f.card), 10),
      satId: Number.parseInt(String(f.satId), 10),
      tier: Number.parseInt(String(f.tier), 10) || 1,
      paid: Number.parseInt(String(f.paid), 10) || 0,
      sold: Array.isArray(f.sold) ? f.sold.map(Number) : [],
    }
    : null;
  const reason = mine?.resetBlockedReason;
  return {
    staged,
    first,
    canReset: staged && mine?.canReset === true,
    resetBlockedReason: reason === undefined || reason === null ? null : String(reason),
  };
}

/**
 * 配備の操作の段階（S0 の中）。ボーナス保持者は、1 枚目（または [Pass]）を選んだら、ボーナスの使い方を必ず選んでから確定する。
 * - main: 1 枚目を選んでいる（まだ何も決まっていない / 途中）
 * - bonus: 1 枚目が決まった。[Deploy another card] / [Draw 1 card] / [No bonus] のどれかを選ぶ（[Confirm] は出さない）
 * - bonusOnly: [Pass] を押した。通常の配備をせずにボーナスだけ使うか（1 枚引く）、何もしないかを選ぶ
 * - ready: すべて選んだ。[Confirm] で確定する
 * S1（ステージ済み）はこの関数を使わず、2 枚目を選ぶ画面になる。
 * @param {Pick<DeploySelection, 'cardId' | 'satId' | 'extra'>} sel
 * @param {{ isBonus: boolean, canDraw: boolean, bonusDeclined: boolean, passing: boolean }} ctx
 * @returns {'main' | 'bonus' | 'bonusOnly' | 'ready'}
 */
export function deployStage(sel, ctx) {
  const mainDone = sel.cardId !== null && sel.satId !== null;
  if (!ctx.isBonus) {
    return mainDone ? 'ready' : 'main';
  }
  if (!mainDone) {
    if (!ctx.passing) {
      return 'main';
    }
    return sel.extra?.op === 'draw' ? 'ready' : 'bonusOnly';
  }
  if (sel.extra?.op === 'draw' || ctx.bonusDeclined) {
    return 'ready';
  }
  return 'bonus';
}

/**
 * [Pass] を押したとき、ボーナスの選択へ進むか（1 枚引けるボーナス保持者だけ）。それ以外はそのまま確定する。
 * @param {{ isBonus: boolean, canDraw: boolean }} ctx
 * @returns {boolean}
 */
export function passNeedsBonusChoice(ctx) {
  return ctx.isBonus && ctx.canDraw;
}
