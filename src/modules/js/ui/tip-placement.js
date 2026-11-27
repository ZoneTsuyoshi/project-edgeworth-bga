/**
 * ツールチップの位置の計算（仕様書 014 §7）。DOM に依存しない純粋関数。
 * 対象の要素のすぐ横に、右 → 左 → 下 → 上の順で、入る側に置く。対象を覆わない。画面の端から 8 px 以内に入らない。上はステータスバーの下。
 * 入る側が無いときは、いちばん収まりのよい側を選び、その側で使える最大の幅と高さ（maxWidth / maxHeight）を返す。
 * 呼び出し側（Tips）はその大きさに収まるよう、画像と文を横に並べる → 幅を広げる → 画像を縮める、の順で形を変える。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** 画面の端からの余白（px） */
export const TIP_EDGE = 8;
/** 対象とツールチップの間（px） */
export const TIP_GAP = 8;

/** @typedef {{ left: number, top: number, right: number, bottom: number }} TipRect */
/** @typedef {'right' | 'left' | 'bottom' | 'top'} TipSide */

/**
 * @typedef {object} TipPlacement
 * @property {TipSide} side 置く側
 * @property {number} left 左端（px。`position: fixed`）
 * @property {number} top 上端（px）
 * @property {number} maxWidth その側で使える最大の幅（px）
 * @property {number} maxHeight その側で使える最大の高さ（px）
 * @property {boolean} fits 渡された大きさのまま収まるか（false のときは maxWidth / maxHeight まで縮める前提の位置）
 */

/**
 * @param {number} v
 * @param {number} lo
 * @param {number} hi
 * @returns {number}
 */
function clamp(v, lo, hi) {
  return Math.max(lo, Math.min(hi, v));
}

/**
 * ツールチップの位置を決める。
 * @param {TipRect} anchor 対象の要素の矩形（`getBoundingClientRect()`。見た目の位置）
 * @param {{ width: number, height: number }} size ツールチップの大きさ
 * @param {{ width: number, height: number, top?: number }} viewport 見える領域（幅・高さ）。top は上端の制限（BGA のステータスバー `#page-title` の下端。無ければ 0）
 * @returns {TipPlacement}
 */
export function placeTip(anchor, size, viewport) {
  const region = {
    left: TIP_EDGE,
    right: Math.max(TIP_EDGE, viewport.width - TIP_EDGE),
    top: Math.max(TIP_EDGE, (viewport.top ?? 0) + TIP_EDGE),
    bottom: Math.max(TIP_EDGE, viewport.height - TIP_EDGE),
  };
  region.bottom = Math.max(region.top, region.bottom);
  const regionW = region.right - region.left;
  const regionH = region.bottom - region.top;
  /** @type {Record<TipSide, { w: number, h: number }>} */
  const room = {
    right: { w: Math.max(0, region.right - (anchor.right + TIP_GAP)), h: regionH },
    left: { w: Math.max(0, anchor.left - TIP_GAP - region.left), h: regionH },
    bottom: { w: regionW, h: Math.max(0, region.bottom - (anchor.bottom + TIP_GAP)) },
    top: { w: regionW, h: Math.max(0, anchor.top - TIP_GAP - region.top) },
  };
  /** @type {TipSide[]} */
  const order = ['right', 'left', 'bottom', 'top'];
  const fitting = order.find((s) => size.width <= room[s].w && size.height <= room[s].h);
  // どこにも入らない: 幅・高さの足りなさがいちばん小さい側（同じなら右 → 左 → 下 → 上の順）
  const score = (/** @type {TipSide} */ s) => Math.min(room[s].w / Math.max(1, size.width), room[s].h / Math.max(1, size.height));
  const side = fitting ?? order.reduce((best, s) => (score(s) > score(best) ? s : best), order[0]);
  const w = Math.min(size.width, room[side].w);
  const h = Math.min(size.height, room[side].h);
  const cx = (anchor.left + anchor.right) / 2;
  const cy = (anchor.top + anchor.bottom) / 2;
  let left;
  let top;
  if (side === 'right' || side === 'left') {
    left = side === 'right' ? anchor.right + TIP_GAP : anchor.left - TIP_GAP - w;
    top = clamp(cy - h / 2, region.top, region.bottom - h);
  } else {
    left = clamp(cx - w / 2, region.left, region.right - w);
    top = side === 'bottom' ? anchor.bottom + TIP_GAP : anchor.top - TIP_GAP - h;
  }
  return { side, left, top, maxWidth: room[side].w, maxHeight: room[side].h, fits: fitting !== undefined };
}
