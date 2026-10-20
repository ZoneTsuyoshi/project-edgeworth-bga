/**
 * 配備（状態 61）の args の解釈、選択の検証、送信する引数の組み立て（008 §3-6、§3-7、009 §3.7）。
 * 配備のボーナス（追加の配備 / 1 枚引く）は通常の配備と同時に選ぶ（作者回答 2026-10-20）。
 * args（`_private`）: `targets`（カード番号 → 配備先の候補 `[{ satId, tier, cost, blocked, minSales, selectable, useMods?, next? }]`）、
 * `bonus`（ボーナス保持者か）、`canDraw`（保持者のみ）、`hand`、`sellValue`。
 * `next` は、通常の配備で先に同じ衛星へ 1 枚配備するときの段・コスト・選べない理由・起動の修正（追加の配備を同じ衛星にするときに使う）。
 * 合計コストの足し算と、売却の枚数の判定だけをここで行う。各カードのコストはサーバーが計算した値。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { cheapest, compact, costWith, entriesOf, jsonParam, parseMods, payState, validMods } from './common.js';

/**
 * @typedef {import('./common.js').ModsInfo} ModsInfo
 * @typedef {import('./common.js').Funds} Funds
 * @typedef {object} DeployNext
 * @property {number} tier
 * @property {number} cost
 * @property {string | null} blocked
 * @property {ModsInfo | null} mods
 * @typedef {object} DeployTarget
 * @property {number} cardId
 * @property {number} satId
 * @property {number} tier 配備後の段（1〜4）
 * @property {number} cost サーバーが計算した配備コスト（起動の修正を使わないとき）
 * @property {string | null} blocked 選べない理由（full / forbidden）
 * @property {ModsInfo | null} mods
 * @property {DeployNext | null} next 先に同じ衛星へ 1 枚配備した後の候補（ボーナス保持者のみ）
 * @typedef {{ op: 'draw' } | { op: 'deploy', cardId: number | null, satId: number | null, useMods: string[] }} DeployExtra 追加の行動
 * @typedef {object} DeploySelection
 * @property {number | null} cardId 通常の配備のカード
 * @property {number | null} satId 通常の配備の衛星
 * @property {number[]} sell 売却（2 枚分の合計コストに対する）
 * @property {string[]} useMods 通常の配備に使う起動の修正
 * @property {DeployExtra | null} extra 追加の行動
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
      const next = t.next === undefined || t.next === null ? null : {
        tier: Number.parseInt(String(t.next.tier), 10) || 1,
        cost: Number.parseInt(String(t.next.cost), 10) || 0,
        blocked: t.next.blocked === null || t.next.blocked === undefined ? null : String(t.next.blocked),
        mods: parseMods(t.next.useMods),
      };
      list.push({
        cardId: Number.parseInt(card, 10),
        satId: Number.parseInt(String(t.satId), 10),
        tier: Number.parseInt(String(t.tier), 10) || 1,
        cost: Number.parseInt(String(t.cost), 10) || 0,
        blocked: t.blocked === null || t.blocked === undefined ? null : String(t.blocked),
        mods: parseMods(t.useMods),
        next,
      });
    }
  }
  return list;
}

/**
 * 組（カード, 衛星）の一覧。選べる = 理由がなく、売却を使えば（起動の修正を使えば）払える。配備するカード自身は売れない。
 * 通常の配備（1 枚目）の候補。
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
 * 追加の配備として見たときの候補（段・コスト・起動の修正）。通常の配備と同じ衛星なら `next`（先に 1 枚付いた後）の値を使う。
 * @param {DeployTarget} target
 * @param {number | null} mainSatId 通常の配備の衛星
 * @returns {DeployTarget}
 */
export function extraVariant(target, mainSatId) {
  if (mainSatId !== null && target.satId === mainSatId && target.next !== null) {
    return { ...target, tier: target.next.tier, cost: target.next.cost, blocked: target.next.blocked, mods: target.next.mods };
  }
  return target;
}

/**
 * 起動の修正の候補から、もう 1 枚の配備が使う鍵を除く（同じ起動は 1 回だけ）。`all` は残りをすべて使ったときのコストに直す。
 * @param {ModsInfo | null} mods
 * @param {string[]} taken
 * @param {number} cost 修正を使わないときのコスト
 * @returns {ModsInfo | null}
 */
export function withoutMods(mods, taken, cost) {
  if (mods === null || taken.length === 0) {
    return mods;
  }
  const options = mods.options.filter((o) => !taken.includes(o.hostKey));
  if (options.length === 0) {
    return null;
  }
  return { options, all: Math.max(0, cost - options.reduce((sum, o) => sum + o.reduction, 0)) };
}

/**
 * 追加の配備の組（カード, 衛星）の一覧。通常の配備のカードは除く。選べる = 理由がなく、2 枚の合計コストを売却込みで払える。
 * @param {DeployTarget[]} targets
 * @param {number[]} hand 今の手札
 * @param {{ cardId: number, satId: number, cost: number, mods: string[] }} main 通常の配備（cost は選んだ起動の修正を使った後のコスト）
 * @param {number} maya
 * @param {number} price
 * @returns {import('./common.js').PairEntry<DeployTarget & { pay: ReturnType<typeof payState>, total: number }>[]}
 */
export function extraPairs(targets, hand, main, maya, price) {
  const funds = { maya, price, sellable: hand.length - 2 };
  return targets
    .filter((t) => hand.includes(t.cardId) && t.cardId !== main.cardId)
    .map((t) => {
      const v = extraVariant(t, main.satId);
      const mods = withoutMods(v.mods, main.mods, v.cost);
      const total = main.cost + v.cost;
      const pay = payState(total, funds);
      const best = payState(main.cost + cheapest(v.cost, mods), funds);
      return { a: t.cardId, b: t.satId, usable: v.blocked === null && best.kind !== 'short', data: { ...v, mods, pay, total } };
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
 * 配備するカード（通常の配備 → 追加の配備の順）。売れないカード。
 * @param {Pick<DeploySelection, 'cardId' | 'extra'>} sel
 * @returns {number[]}
 */
export function deployedCards(sel) {
  /** @type {number[]} */
  const cards = [];
  if (sel.cardId !== null) {
    cards.push(sel.cardId);
  }
  if (sel.extra?.op === 'deploy' && sel.extra.cardId !== null) {
    cards.push(sel.extra.cardId);
  }
  return cards;
}

/**
 * 確定できるか。2 枚の配備は合計コストに対して売却の枚数が足りていること、売却に配備するカードを含めないこと。
 * 何も選んでいなければ確定できない（何もしないときは [Pass]）。
 * @param {DeploySelection} sel
 * @param {{ pay: ReturnType<typeof payState> | null, canDraw: boolean }} ctx pay は 2 枚分の合計コストの支払い状況
 * @returns {{ ok: boolean, reason?: string }}
 */
export function validateDeploy(sel, ctx) {
  const mainStarted = sel.cardId !== null || sel.satId !== null;
  const mainDone = sel.cardId !== null && sel.satId !== null;
  const extra = sel.extra;
  if (extra?.op === 'draw' && !ctx.canDraw) {
    return { ok: false, reason: 'cannotDraw' };
  }
  const extraStarted = extra?.op === 'deploy' && (extra.cardId !== null || extra.satId !== null);
  const extraDone = extra?.op === 'deploy' && extra.cardId !== null && extra.satId !== null;
  if ((mainStarted && !mainDone) || (extraStarted && !extraDone) || (extra?.op === 'deploy' && !extraDone)) {
    return { ok: false, reason: 'incomplete' };
  }
  if (!mainDone && extra === null) {
    return { ok: false, reason: 'incomplete' };
  }
  if (!mainDone && !extraDone) {
    return { ok: true };
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
 * actConfirmDeploy に送る引数。通常の配備が決まっていれば deploy、なければ pass（追加の行動だけのこともある）。
 * @param {Partial<DeploySelection>} sel
 * @returns {Record<string, unknown>}
 */
export function deployActionArgs(sel) {
  const extra = sel.extra ?? null;
  /** @type {Record<string, unknown> | null} */
  let extraPayload = null;
  if (extra?.op === 'draw') {
    extraPayload = { op: 'draw' };
  } else if (extra?.op === 'deploy' && extra.cardId !== null && extra.satId !== null) {
    extraPayload = compact({ op: 'deploy', card: extra.cardId, sat: extra.satId, useMods: extra.useMods });
  }
  const extraArg = extraPayload === null ? undefined : JSON.stringify(extraPayload);
  const main = sel.cardId !== null && sel.cardId !== undefined && sel.satId !== null && sel.satId !== undefined;
  if (!main) {
    return compact({ op: 'pass', extra: extraArg });
  }
  return compact({ op: 'deploy', cardId: sel.cardId, satId: sel.satId, sell: sel.sell ?? [], useMods: jsonParam(sel.useMods ?? []), extra: extraArg });
}

/**
 * 確定済みの内容（`myChoice`）から選択を復元する（[Change my choice] の初期選択。008 §2-2）。
 * @param {any} choice
 * @returns {DeploySelection}
 */
export function deploySelectionFrom(choice) {
  const main = choice?.op === 'deploy';
  /** @type {DeployExtra | null} */
  let extra = null;
  if (choice?.extra?.op === 'draw') {
    extra = { op: 'draw' };
  } else if (choice?.extra?.op === 'deploy') {
    extra = {
      op: 'deploy',
      cardId: Number.parseInt(String(choice.extra.card), 10),
      satId: Number.parseInt(String(choice.extra.sat), 10),
      useMods: Array.isArray(choice.extra.useMods) ? choice.extra.useMods.map(String) : [],
    };
  }
  return {
    cardId: main ? Number.parseInt(String(choice.card), 10) : null,
    satId: main ? Number.parseInt(String(choice.sat), 10) : null,
    sell: main && Array.isArray(choice?.sell) ? choice.sell.map(Number) : [],
    useMods: main && Array.isArray(choice?.useMods) ? choice.useMods.map(String) : [],
    extra,
  };
}

/**
 * 配備の操作の段階。ボーナス保持者は、1 枚目（または [Pass]）を選んだら、ボーナスの使い方を必ず選んでから確定する。
 * - main: 通常の配備を選んでいる（まだ何も決まっていない / 1 枚目の途中）
 * - bonus: 1 枚目が決まった。[Deploy another card] / [Draw 1 card] / [No bonus] のどれかを選ぶ（[Confirm] は出さない）
 * - extra: 追加の配備（2 枚目）を選んでいる
 * - bonusOnly: [Pass] を押した。通常の配備をせずにボーナスだけ使うか（1 枚引く）、何もしないかを選ぶ
 * - ready: すべて選んだ。[Confirm] で 1 回に確定する
 * @param {Pick<DeploySelection, 'cardId' | 'satId' | 'extra'>} sel
 * @param {{ isBonus: boolean, canDraw: boolean, bonusDeclined: boolean, passing: boolean, extraStage: boolean }} ctx
 * @returns {'main' | 'bonus' | 'extra' | 'bonusOnly' | 'ready'}
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
  if (sel.extra?.op === 'deploy' && ctx.extraStage) {
    return 'extra';
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
