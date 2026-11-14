/**
 * 手札を選ぶ目的（欄）が 1 画面に複数あるときの判定（仕様書 012 追補 2026-11-14 B-2）。DOM に依存しない純関数。
 * 欄 = 売るカード / 捨てるカード / 配備するカードなど。選択中の欄だけが手札のゾーンを使い、他の欄で選んだカードは候補から外す。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/**
 * @typedef {object} TargetState
 * @property {string} id 欄の識別子
 * @property {number[]} candidates 手札のゾーンで押せるカード（他の欄に取られたカードはここから外す前の候補）
 * @property {number[]} selected 選んだカード
 * @property {number} [need] 必要な枚数（これに足りない欄が「未完了」。省略は 0 = 任意）
 * @property {number} [max] 上限（省略は無制限）
 * @property {boolean} [disabled] 今は使えない欄（選択中にできない）
 */

/**
 * 欄が完了しているか（必要な枚数に達している）。
 * @param {TargetState} target
 * @returns {boolean}
 */
export function targetComplete(target) {
  return target.selected.length >= (target.need ?? 0);
}

/**
 * 最初の「使えて未完了」の欄。
 * @param {TargetState[]} targets
 * @returns {TargetState | undefined}
 */
function firstIncomplete(targets) {
  return targets.find((t) => t.disabled !== true && !targetComplete(t));
}

/**
 * 選択中の欄を決める。保存された選択が今も使えるならそれ、なければ最初の未完了の欄。
 * 未完了がなければ、欄が 1 つなら（または idleWhenComplete でなければ）最初に使える欄、idleWhenComplete で複数なら null（手札を使わない）。
 * @param {TargetState[]} targets
 * @param {string | null | undefined} stored 前回の選択中の欄（未設定は undefined、明示的に手札を使わないは null）
 * @param {{ idleWhenComplete?: boolean }} [opts]
 * @returns {string | null}
 */
export function resolveActive(targets, stored, opts = {}) {
  if (typeof stored === 'string' && targets.some((t) => t.id === stored && t.disabled !== true)) {
    return stored;
  }
  const incomplete = firstIncomplete(targets);
  if (incomplete !== undefined) {
    return incomplete.id;
  }
  const usable = targets.filter((t) => t.disabled !== true);
  if (opts.idleWhenComplete === true && usable.length > 1) {
    return null;
  }
  return usable[0]?.id ?? null;
}

/**
 * 選択を変えた後の選択中の欄。今の欄が完了したら、次の未完了の欄（今の欄の後ろ、なければ先頭から）へ移る。移れる欄がなければそのまま。
 * @param {TargetState[]} targets 変更後の状態
 * @param {string | null} active
 * @returns {string | null}
 */
export function advanceActive(targets, active) {
  const current = targets.find((t) => t.id === active);
  if (current === undefined || !targetComplete(current)) {
    return active;
  }
  const index = targets.indexOf(current);
  const ordered = [...targets.slice(index + 1), ...targets.slice(0, index)];
  const next = ordered.find((t) => t.disabled !== true && !targetComplete(t));
  return next === undefined ? active : next.id;
}

/**
 * 他の欄で選んだカード（この欄の候補にできない）。
 * @param {TargetState[]} targets
 * @param {string} id この欄
 * @returns {Set<number>}
 */
export function claimedByOthers(targets, id) {
  return new Set(targets.filter((t) => t.id !== id).flatMap((t) => t.selected));
}

/**
 * この欄の、手札のゾーンで押せるカード（候補から他の欄が選んだカードを除く）。
 * @param {TargetState[]} targets
 * @param {string} id
 * @returns {number[]}
 */
export function candidatesFor(targets, id) {
  const target = targets.find((t) => t.id === id);
  if (target === undefined || target.disabled === true) {
    return [];
  }
  const claimed = claimedByOthers(targets, id);
  return target.candidates.filter((c) => !claimed.has(c));
}

/**
 * 手札のゾーンを押した結果（HandView が出す、手札のカードだけの並び）を、この欄の選択にする。
 * 手札にないカード（残すカードなど）の選択は保つ。上限は、単一（max = 1）なら入れ替え、複数なら上限を超える追加を無視する。
 * @param {TargetState} target
 * @param {number[]} fromHand HandView の選択（手札のカードだけ）
 * @param {number[]} hand 手札
 * @returns {number[]}
 */
export function pickFromHand(target, fromHand, hand) {
  const outside = target.selected.filter((id) => !hand.includes(id));
  const prevHand = target.selected.filter((id) => hand.includes(id));
  const max = target.max ?? Number.POSITIVE_INFINITY;
  if (max === 1) {
    // 後から押した方に入れ替わる（手札で新しく押したカードがあればそれ、なければ手札の選択は外れた）
    const added = fromHand.filter((id) => !prevHand.includes(id));
    return added.length > 0 ? [added[added.length - 1]] : (fromHand.length > 0 ? fromHand.slice(-1) : []);
  }
  const next = [...outside, ...fromHand];
  return next.length > max ? target.selected : next;
}

/**
 * 手札にない（別の一覧から選ぶ）カードを選んだ結果。単一（max = 1）なら入れ替え。
 * @param {TargetState} target
 * @param {number[]} fromOutside 別の一覧の選択
 * @param {number[]} hand 手札
 * @returns {number[]}
 */
export function pickFromOutside(target, fromOutside, hand) {
  const inHand = target.selected.filter((id) => hand.includes(id));
  const max = target.max ?? Number.POSITIVE_INFINITY;
  if (max === 1) {
    return fromOutside.slice(-1);
  }
  const next = [...inHand, ...fromOutside];
  return next.length > max ? target.selected : next;
}

/**
 * 選択から 1 枚外す（欄の × ボタン）。
 * @param {number[]} selected
 * @param {number} id
 * @returns {number[]}
 */
export function removeCard(selected, id) {
  return selected.filter((c) => c !== id);
}

/**
 * 選択中でない欄が使うカードに付ける印（手札の上）。選択中の欄のカードは印でなく選択として出す。
 * @param {TargetState[]} targets
 * @param {string | null} active
 * @param {Record<string, string>} badges 欄の id → 印の文言
 * @returns {Map<number, string>}
 */
export function plannedMarks(targets, active, badges) {
  /** @type {Map<number, string>} */
  const marks = new Map();
  for (const t of targets) {
    if (t.id === active) {
      continue;
    }
    for (const id of t.selected) {
      marks.set(id, badges[t.id] ?? '');
    }
  }
  return marks;
}

/**
 * 上限や候補の変化に合わせて選択を整える（手札から外れたカード・候補から外れたカードを落とし、上限で切る）。
 * @param {number[]} selected
 * @param {number[]} allowed 選べるカード（手札の候補 + 別の一覧の候補）
 * @param {number} [max]
 * @returns {number[]}
 */
export function pruneSelection(selected, allowed, max = Number.POSITIVE_INFINITY) {
  return selected.filter((id) => allowed.includes(id)).slice(0, max);
}
