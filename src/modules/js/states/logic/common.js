/**
 * 状態ごとの操作 UI が共有する純粋関数（BGA にも DOM にも、スプライトの生成物にも依存しない。tests/js で単体テストする）。
 * - args の解釈（`_private`、起動の修正 `useMods`）
 * - 売却の計算（不足分と最小の売却枚数。コスト自体はサーバーが計算した args の値で、ここでは計算しない。008 §2-6）
 * - act* に送る引数の整形（空配列は送信で落ちるので省く。JsonParam は JSON 文字列にして渡す）
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/**
 * @typedef {{ hostKey: string, cardKey: string, reduction: number, cost: number }} ModOption
 * @typedef {{ options: ModOption[], all: number }} ModsInfo
 * @typedef {{ maya: number, price: number, sellable: number }} Funds 所持マヤ、手札 1 枚の売却額、売れる手札の枚数
 */

/**
 * `args._private` から自分の分を取り出す。BGA は参加者の分だけを `_private` に平らにして渡すが、サーバーの形（プレイヤー ID がキー）でも読める。
 * @param {Record<string, any> | null | undefined} args
 * @param {number | null} me
 * @returns {Record<string, any> | null}
 */
export function privateOf(args, me) {
  const p = args?._private;
  if (p === undefined || p === null || typeof p !== 'object') {
    return null;
  }
  if (me !== null && p[me] !== undefined) {
    return p[me];
  }
  const keys = Object.keys(p);
  const keyed = keys.length > 0 && keys.every((k) => /^\d+$/.test(k));
  return keyed ? null : p;
}

/**
 * 整数の配列にそろえる（BGA は数値を文字列で渡すことがある）。
 * @param {unknown} value
 * @returns {number[]}
 */
export function intList(value) {
  return Array.isArray(value) ? value.map((v) => Number.parseInt(String(v), 10)).filter((n) => !Number.isNaN(n)) : [];
}

/**
 * PHP の連想配列（JSON では object。空だと配列 []）を [key, value] の並びにする。
 * @param {unknown} value
 * @returns {[string, any][]}
 */
export function entriesOf(value) {
  if (value === null || value === undefined || typeof value !== 'object') {
    return [];
  }
  return Object.entries(value);
}

// ---- 売却 ----

/**
 * 払うために最低限売る枚数。売っても届かないときは null（サーバーの Payability::minSales と同じ式）。
 * @param {number} cost
 * @param {Funds} funds
 * @returns {number | null}
 */
export function minSales(cost, funds) {
  const short = Math.max(0, cost - funds.maya);
  if (short === 0) {
    return 0;
  }
  if (funds.price <= 0) {
    return null;
  }
  const need = Math.ceil(short / funds.price);
  return need <= funds.sellable ? need : null;
}

/**
 * コストの支払い状況。free = 売らずに払える、sell = 売却すれば払える（need 枚以上）、short = 売っても払えない。
 * @param {number} cost
 * @param {Funds} funds
 * @returns {{ kind: 'free' | 'sell' | 'short', need: number, short: number, left: number }}
 */
export function payState(cost, funds) {
  const need = minSales(cost, funds);
  const short = Math.max(0, cost - funds.maya);
  if (need === null) {
    return { kind: 'short', need: 0, short, left: funds.maya - cost };
  }
  return { kind: need === 0 ? 'free' : 'sell', need, short, left: funds.maya - cost };
}

/**
 * 売却額の合計。
 * @param {number} count
 * @param {number} price
 * @returns {number}
 */
export function sellTotal(count, price) {
  return Math.max(0, count) * Math.max(0, price);
}

/**
 * 売却で手札をすべて売るか（確認が要るのはこのときだけ。008 §2-3）。
 * @param {number[]} picked
 * @param {number[]} hand
 * @returns {boolean}
 */
export function sellsWholeHand(picked, hand) {
  return hand.length > 0 && hand.every((id) => picked.includes(id));
}

/**
 * 売れる手札（予約済みのカード = 配備するカード・同梱済みの売却を除く）。
 * @param {number[]} hand
 * @param {number[]} reserved
 * @returns {number[]}
 */
export function sellableHand(hand, reserved) {
  return hand.filter((id) => !reserved.includes(id));
}

// ---- 起動の修正（useMods） ----

/**
 * args の useMods を整える。無ければ null。
 * @param {any} raw
 * @returns {ModsInfo | null}
 */
export function parseMods(raw) {
  if (raw === undefined || raw === null || !Array.isArray(raw.options) || raw.options.length === 0) {
    return null;
  }
  return {
    options: raw.options.map((/** @type {any} */ o) => ({
      hostKey: String(o.hostKey),
      cardKey: String(o.cardKey),
      reduction: Number.parseInt(String(o.reduction), 10) || 0,
      cost: Number.parseInt(String(o.cost), 10) || 0,
    })),
    all: Number.parseInt(String(raw.all), 10) || 0,
  };
}

/**
 * 選んだ起動の修正を使ったときのコスト。サーバーが渡した値を使い、渡されていない組み合わせ（一部だけ）は
 * サーバーが `all` を出す式（合計から引く量の和、下限 0）と同じ式で見積もる。確定時の最終判定はサーバー。
 * @param {number} total 修正を使わないときのコスト
 * @param {ModsInfo | null} mods
 * @param {string[]} chosen 使う宿主の鍵
 * @returns {number}
 */
export function costWith(total, mods, chosen) {
  const keys = validMods(mods, chosen);
  if (mods === null || keys.length === 0) {
    return total;
  }
  if (keys.length === mods.options.length) {
    return mods.all;
  }
  if (keys.length === 1) {
    return mods.options.find((o) => o.hostKey === keys[0])?.cost ?? total;
  }
  const reduction = mods.options.filter((o) => keys.includes(o.hostKey)).reduce((sum, o) => sum + o.reduction, 0);
  return Math.max(0, total - reduction);
}

/**
 * 候補にある鍵だけにする。
 * @param {ModsInfo | null} mods
 * @param {string[]} chosen
 * @returns {string[]}
 */
export function validMods(mods, chosen) {
  if (mods === null) {
    return [];
  }
  const allowed = mods.options.map((o) => o.hostKey);
  return [...new Set(chosen)].filter((k) => allowed.includes(k));
}

/**
 * 修正をすべて使ったときが最安（下限は 0）。売却で払えるかの判定に使う。
 * @param {number} total
 * @param {ModsInfo | null} mods
 * @returns {number}
 */
export function cheapest(total, mods) {
  return mods === null ? total : Math.min(total, mods.all);
}

// ---- 送信する引数 ----

/**
 * 空の配列・undefined・null を省く（空配列は送信で落ちる。サーバー側は未指定を空として扱う）。
 * @param {Record<string, unknown>} args
 * @returns {Record<string, unknown>}
 */
export function compact(args) {
  /** @type {Record<string, unknown>} */
  const out = {};
  for (const [key, value] of Object.entries(args)) {
    if (value === undefined || value === null || (Array.isArray(value) && value.length === 0)) {
      continue;
    }
    out[key] = value;
  }
  return out;
}

/**
 * JsonParam の引数は JSON 文字列にして渡す。
 * @param {unknown} value
 * @returns {string | undefined}
 */
export function jsonParam(value) {
  if (value === undefined || value === null) {
    return undefined;
  }
  if (Array.isArray(value) && value.length === 0) {
    return undefined;
  }
  return JSON.stringify(value);
}

// ---- 組の選択（カードと衛星、区域と衛星のどちらから入っても同じ結果になる。008 §3-4、§3-6） ----

/**
 * @template T
 * @typedef {{ a: number, b: number, usable: boolean, data: T }} PairEntry
 */

/**
 * 片側（a）の各候補が今選べるか。反対側（b）が選ばれていれば、その組が選べるときだけ。
 * 選ばれていなければ、その a を含む組のどれかが選べるとき。
 * @template T
 * @param {PairEntry<T>[]} pairs
 * @param {'a' | 'b'} side 状態を知りたい側
 * @param {number | null} otherSelected 反対側で選ばれているもの
 * @returns {Map<number, { usable: boolean, entry: PairEntry<T> | null }>} 側の各値 → 選べるか、反対側が選ばれているときはその組
 */
export function sideAvailability(pairs, side, otherSelected) {
  const other = side === 'a' ? 'b' : 'a';
  /** @type {Map<number, { usable: boolean, entry: PairEntry<T> | null }>} */
  const result = new Map();
  for (const entry of pairs) {
    const key = entry[side];
    const current = result.get(key) ?? { usable: false, entry: null };
    if (otherSelected === null) {
      current.usable = current.usable || entry.usable;
      current.entry ??= entry;
    } else if (entry[other] === otherSelected) {
      current.usable = entry.usable;
      current.entry = entry;
    }
    result.set(key, current);
  }
  return result;
}

/**
 * a が選ばれていて、組める b が 1 つだけなら、それを返す（衛星の選択を省く。008 §3-4）。
 * @template T
 * @param {PairEntry<T>[]} pairs
 * @param {number | null} a
 * @returns {number | null}
 */
export function soleOption(pairs, a) {
  if (a === null) {
    return null;
  }
  const options = pairs.filter((p) => p.a === a && p.usable);
  return options.length === 1 ? options[0].b : null;
}

// ---- 文面 ----

/**
 * `${name}` を値に置き換える（翻訳された文字列の差し込み。BGA の `${you}` などは置き換えない）。
 * @param {string} template
 * @param {Record<string, string | number>} vars
 * @returns {string}
 */
export function format(template, vars) {
  let out = template;
  for (const [key, value] of Object.entries(vars)) {
    out = out.replaceAll(`\${${key}}`, String(value));
  }
  return out;
}
