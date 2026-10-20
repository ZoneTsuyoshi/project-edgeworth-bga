/**
 * 効果の窓（状態 31）の問いの解釈と、答えの組み立て（011 §6、008 §5）。
 * args（`_private`）: `pending`（`[{ entryId, cardKey }]`）、`questions`、`hand`、`sellValue`。問いは `ORDER` か `EFFECT`。
 * フィールド 8 種: SATELLITE / AREA / DIRECTION / AMOUNT / CARDS / PAIRS / ASSIGNMENT / USE_MODS。クライアントは検証しない（表示の絞り込みのみ）。
 * 最終検証はサーバー（AnswerValidator）が行う。ここの検証は [Use] を押せるかの目安で、サーバーと同じ条件にそろえてある。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { entriesOf, intList, jsonParam, payState } from './common.js';

/**
 * @typedef {object} Field
 * @property {string} key
 * @property {string} type
 * @property {number} min
 * @property {number} max
 * @property {any[]} candidates
 * @property {any} default
 *
 * @typedef {object} OrderQuestion
 * @property {'ORDER'} type
 * @property {{ entryId: number, cardKey: string, forced: boolean }[]} entries
 * @property {number[]} defaultOrder
 *
 * @typedef {object} EffectQuestion
 * @property {'EFFECT'} type
 * @property {number} entryId
 * @property {string} cardKey
 * @property {boolean} mandatory
 * @property {{ kind: string, amount?: number, tiers?: Record<string, number>, canPay?: boolean, shortfall?: number, canPayBySelling?: boolean }} cost
 * @property {Field[]} fields
 * @property {Record<string, any>} preview
 *
 * @typedef {OrderQuestion | EffectQuestion} Question
 */

/**
 * @param {any} raw
 * @returns {Field}
 */
function parseField(raw) {
  return {
    key: String(raw.key),
    type: String(raw.type),
    min: Number.parseInt(String(raw.min), 10) || 0,
    max: Number.parseInt(String(raw.max), 10) || 0,
    candidates: Array.isArray(raw.candidates) ? raw.candidates : [],
    default: raw.default ?? null,
  };
}

/**
 * @param {Record<string, any> | null} mine `_private`
 * @returns {{ pending: { entryId: number, cardKey: string }[], questions: Question[], hand: number[], sellValue: number }}
 */
export function parseWindow(mine) {
  /** @type {Question[]} */
  const questions = [];
  for (const q of Array.isArray(mine?.questions) ? mine.questions : []) {
    if (q.type === 'ORDER') {
      questions.push({
        type: 'ORDER',
        entries: (q.entries ?? []).map((/** @type {any} */ e) => ({ entryId: Number.parseInt(String(e.entryId), 10), cardKey: String(e.cardKey), forced: e.forced === true })),
        defaultOrder: intList(q.defaultOrder),
      });
    } else if (q.type === 'EFFECT') {
      questions.push({
        type: 'EFFECT',
        entryId: Number.parseInt(String(q.entryId), 10),
        cardKey: String(q.cardKey),
        mandatory: q.mandatory === true,
        cost: q.cost ?? { kind: 'NONE' },
        fields: (q.fields ?? []).map(parseField),
        preview: q.preview ?? {},
      });
    }
  }
  return {
    pending: (Array.isArray(mine?.pending) ? mine.pending : []).map((/** @type {any} */ p) => ({ entryId: Number.parseInt(String(p.entryId), 10), cardKey: String(p.cardKey) })),
    questions,
    hand: intList(mine?.hand),
    sellValue: Number.parseInt(String(mine?.sellValue), 10) || 0,
  };
}

/**
 * 待機表示: 公開の `waiting` を「誰が・どのカード」に整える（自分を除く）。
 * @param {any} waiting
 * @param {number | null} me
 * @returns {{ playerId: number, sources: string[] }[]}
 */
export function parseWaiting(waiting, me) {
  return (Array.isArray(waiting) ? waiting : [])
    .map((/** @type {any} */ w) => ({ playerId: Number.parseInt(String(w.player_id), 10), sources: (Array.isArray(w.sources) ? w.sources : []).map(String) }))
    .filter((/** @type {{ playerId: number }} */ w) => w.playerId !== me);
}

// ---- 順序（ORDER） ----

/**
 * 順序を 1 つ動かす。direction は -1（上）/ 1（下）/ 'top'（先頭）。
 * @param {number[]} order
 * @param {number} entryId
 * @param {-1 | 1 | 'top'} direction
 * @returns {number[]}
 */
export function moveEntry(order, entryId, direction) {
  const index = order.indexOf(entryId);
  if (index < 0) {
    return order;
  }
  const next = [...order];
  next.splice(index, 1);
  const to = direction === 'top' ? 0 : Math.max(0, Math.min(order.length - 1, index + direction));
  next.splice(to, 0, entryId);
  return next;
}

/**
 * 初期の並び（デフォルトの順。007 §6-3）。entries に含まれる ID だけを、足りなければ entries の並びで補う。
 * @param {OrderQuestion} q
 * @returns {number[]}
 */
export function initialOrder(q) {
  const ids = q.entries.map((e) => e.entryId);
  const order = q.defaultOrder.filter((id) => ids.includes(id));
  return [...order, ...ids.filter((id) => !order.includes(id))];
}

// ---- フィールド ----

/**
 * フィールドの初期値。候補が 1 つしかない必須の選択は、その候補を初期値にする。
 * @param {Field} field
 * @returns {any}
 */
export function fieldDefault(field) {
  switch (field.type) {
    case 'SATELLITE':
    case 'AREA':
    case 'DIRECTION':
    case 'AMOUNT':
      if (field.default !== null && field.default !== undefined) {
        return field.default;
      }
      return field.candidates.length === 1 ? field.candidates[0] : null;
    case 'CARDS':
    case 'USE_MODS':
      return [];
    case 'PAIRS':
      return field.min >= 1 && field.candidates.length === 1 && field.max === 1 ? [field.candidates[0]] : [];
    case 'ASSIGNMENT':
      return { ...(field.default ?? {}) };
    default:
      return null;
  }
}

/**
 * 問いの初期値（フィールドの key → 値）。
 * @param {EffectQuestion} q
 * @returns {Record<string, any>}
 */
export function initialValues(q) {
  return Object.fromEntries(q.fields.map((f) => [f.key, fieldDefault(f)]));
}

/**
 * @param {any} a
 * @param {any} b
 * @returns {boolean}
 */
function same(a, b) {
  return JSON.stringify(a) === JSON.stringify(b);
}

/**
 * フィールド 1 つが答えとして足りているか（サーバーの AnswerValidator と同じ条件）。
 * @param {Field} field
 * @param {any} value
 * @returns {{ ok: boolean, reason?: string }}
 */
export function validateField(field, value) {
  switch (field.type) {
    case 'SATELLITE':
    case 'AREA':
    case 'DIRECTION':
      return value !== null && value !== undefined && field.candidates.some((c) => String(c) === String(value)) ? { ok: true } : { ok: false, reason: 'choose' };
    case 'AMOUNT': {
      if (value === null || value === undefined || !Number.isInteger(Number(value))) {
        return { ok: false, reason: 'choose' };
      }
      const n = Number(value);
      if (field.candidates.length > 0) {
        return field.candidates.some((c) => Number(c) === n) ? { ok: true } : { ok: false, reason: 'notAllowed' };
      }
      return n >= field.min && n <= field.max ? { ok: true } : { ok: false, reason: 'range' };
    }
    case 'CARDS':
    case 'USE_MODS': {
      const list = Array.isArray(value) ? value : [];
      const unique = new Set(list.map(String)).size === list.length;
      const inCandidates = list.every((v) => field.candidates.some((c) => String(c) === String(v)));
      return unique && inCandidates && list.length >= field.min && list.length <= field.max ? { ok: true } : { ok: false, reason: 'count' };
    }
    case 'PAIRS': {
      const list = Array.isArray(value) ? value : [];
      const inCandidates = list.every((v) => field.candidates.some((c) => same(c, v)));
      return inCandidates && list.length >= field.min && list.length <= field.max ? { ok: true } : { ok: false, reason: 'count' };
    }
    case 'ASSIGNMENT':
      return value !== null && typeof value === 'object' ? { ok: true } : { ok: false, reason: 'choose' };
    default:
      return { ok: false, reason: 'unknown' };
  }
}

/**
 * 問い全体の答えとして足りているか。
 * @param {EffectQuestion} q
 * @param {Record<string, any>} values
 * @returns {{ ok: boolean, reason?: string, key?: string }}
 */
export function validateQuestion(q, values) {
  for (const f of q.fields) {
    const r = validateField(f, values[f.key]);
    if (!r.ok) {
      return { ...r, key: f.key };
    }
  }
  return { ok: true };
}

/**
 * 送信する fields（key → 値）。
 * 数値の候補は数値、衛星 ID と区域も数値、方向は文字列のまま。PAIRS・CARDS は配列。ASSIGNMENT はカード番号 → 衛星 ID。
 * @param {EffectQuestion} q
 * @param {Record<string, any>} values
 * @returns {Record<string, any>}
 */
export function answerFields(q, values) {
  /** @type {Record<string, any>} */
  const out = {};
  for (const f of q.fields) {
    const v = values[f.key];
    if (f.type === 'CARDS') {
      out[f.key] = intList(v);
    } else if (f.type === 'USE_MODS') {
      out[f.key] = (Array.isArray(v) ? v : []).map(String);
    } else if (f.type === 'PAIRS') {
      out[f.key] = Array.isArray(v) ? v : [];
    } else if (f.type === 'ASSIGNMENT') {
      out[f.key] = Object.fromEntries(entriesOf(v).map(([card, sat]) => [card, Number.parseInt(String(sat), 10)]));
    } else if (f.type === 'DIRECTION') {
      out[f.key] = String(v);
    } else {
      out[f.key] = Number.parseInt(String(v), 10);
    }
  }
  return out;
}

/**
 * actUseEffect に送る引数。fields は JSON 文字列。
 * @param {EffectQuestion} q
 * @param {Record<string, any>} values
 * @param {number[]} sell
 * @returns {Record<string, unknown>}
 */
export function useEffectArgs(q, values, sell) {
  /** @type {Record<string, unknown>} */
  const args = { entryId: q.entryId, fields: jsonParam(answerFields(q, values)) ?? '{}' };
  if (sell.length > 0) {
    args.sell = sell;
  }
  return args;
}

// ---- 代価と売却 ----

/**
 * 払うマヤ。PAY_MAYA は固定額、PAY_MAYA_TIER は選んだ段階（フィールド `tier` の値。なければ最安）。手札を捨てる代価は 0。
 * @param {EffectQuestion} q
 * @param {Record<string, any>} values
 * @returns {number}
 */
export function mayaToPay(q, values) {
  const cost = q.cost;
  if (cost.kind === 'PAY_MAYA') {
    return cost.amount ?? 0;
  }
  if (cost.kind === 'PAY_MAYA_TIER') {
    const tiers = Object.keys(cost.tiers ?? {}).map(Number);
    const chosen = values.tier === null || values.tier === undefined ? NaN : Number(values.tier);
    if (tiers.includes(chosen)) {
      return chosen;
    }
    return tiers.length === 0 ? 0 : Math.min(...tiers);
  }
  return 0;
}

/**
 * 答えが使う手札（売却に使えない）。CARDS、PAIRS（配備のカード = 組の先頭）、ASSIGNMENT は手札を使わない。
 * @param {EffectQuestion} q
 * @param {Record<string, any>} values
 * @returns {number[]}
 */
export function reservedCards(q, values) {
  /** @type {number[]} */
  const ids = [];
  for (const f of q.fields) {
    if (f.type === 'CARDS') {
      ids.push(...intList(values[f.key]));
    } else if (f.type === 'PAIRS' && q.preview.costs !== undefined) {
      for (const pair of Array.isArray(values[f.key]) ? values[f.key] : []) {
        ids.push(Number(pair[0]));
      }
    }
  }
  return ids;
}

/**
 * [Use] で売却が必要な枚数（不足分 ÷ 売却額の切り上げ）。売っても届かないときは null。
 * @param {EffectQuestion} q
 * @param {Record<string, any>} values
 * @param {{ maya: number, price: number, hand: number[] }} ctx
 * @returns {number | null}
 */
export function salesNeeded(q, values, ctx) {
  const reserved = reservedCards(q, values);
  const pay = payState(mayaToPay(q, values), { maya: ctx.maya, price: ctx.price, sellable: ctx.hand.filter((id) => !reserved.includes(id)).length });
  return pay.kind === 'short' ? null : pay.need;
}

/**
 * 組（PAIRS）の表示用の分類。deploy = [カード番号, 衛星 ID]、link = [衛星 ID, 区域]、other = 不明。
 * @param {Record<string, any>} preview
 * @returns {'deploy' | 'link' | 'other'}
 */
export function pairKind(preview) {
  if (preview.costs !== undefined) {
    return 'deploy';
  }
  if (preview.links !== undefined) {
    return 'link';
  }
  return 'other';
}

/**
 * 組のコスト（サーバーが preview に入れたもの）。
 * @param {Record<string, any>} preview
 * @param {[number, number]} pair
 * @returns {number | null}
 */
export function pairCost(preview, pair) {
  const kind = pairKind(preview);
  if (kind === 'deploy') {
    const row = preview.costs.find((/** @type {any} */ c) => Number(c.card) === Number(pair[0]) && Number(c.sat) === Number(pair[1]));
    return row === undefined ? null : Number(row.cost);
  }
  if (kind === 'link') {
    const row = preview.links.find((/** @type {any} */ l) => Number(l.sat) === Number(pair[0]) && Number(l.cell) === Number(pair[1]));
    return row === undefined ? null : Number(row.cost);
  }
  return null;
}
