/**
 * レイアウトの計算（仕様書 012 §2.2、§3.8）。DOM に触れない純粋関数。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** 区域の幅の上限（wide。px） */
export const HEX_W_MAX = 150;
/** 区域の幅の下限（px。これより小さくは拡縮しない） */
export const HEX_W_MIN = 28;
/** スマホ（narrow）の段階: 標準 52 px、大 76 px（012 §3.8）。概観は画面幅に収める */
export const NARROW_STEPS = { normal: 52, large: 76 };
/** PC のズームボタンの倍率（1.25 倍・0.8 倍） */
export const ZOOM_FACTOR = 1.25;
/** PC のズームの段数の範囲 */
export const ZOOM_LEVEL_LIMIT = 3;

/**
 * `#pe-root` の幅 → data-layout（012 §2.2）。
 * @param {number} rootWidth
 * @returns {'wide' | 'medium' | 'narrow'}
 */
export function layoutOf(rootWidth) {
  if (rootWidth >= 1180) {
    return 'wide';
  }
  if (rootWidth >= 700) {
    return 'medium';
  }
  return 'narrow';
}

/**
 * 盤面を幅に収める区域の幅（px）。
 * @param {number} rootWidth
 * @param {number} cols
 * @returns {number}
 */
export function fitHexWidth(rootWidth, cols) {
  return rootWidth / (cols + 0.5);
}

/**
 * 区域の幅 `--pe-hex-w`（px）を決める。
 * wide: 幅 ÷ (cols + 0.5) を 150 px で頭打ち。medium: 幅 ÷ (cols + 0.5)。narrow: 段階（概観 / 標準 / 大）。
 * PC のズームボタンの段数 zoomLevel は wide・medium の自動値に 1.25 倍ずつ掛ける。
 * @param {object} p
 * @param {number} p.rootWidth `#pe-root` の幅（px）
 * @param {number} p.cols 盤面の列数（12 / 9 / 6）
 * @param {number} [p.zoomLevel] PC のズーム段数（-3〜3）
 * @param {'overview' | 'normal' | 'large'} [p.narrowStep] スマホの段階
 * @returns {number} px（小数 1 桁に丸める）
 */
export function computeHexWidth({ rootWidth, cols, zoomLevel = 0, narrowStep = 'normal' }) {
  const layout = layoutOf(rootWidth);
  const fit = fitHexWidth(rootWidth, cols);
  let w;
  if (layout === 'narrow') {
    w = narrowStep === 'overview' ? fit : NARROW_STEPS[narrowStep];
  } else {
    const level = Math.max(-ZOOM_LEVEL_LIMIT, Math.min(ZOOM_LEVEL_LIMIT, zoomLevel));
    const auto = layout === 'wide' ? Math.min(HEX_W_MAX, fit) : fit;
    w = auto * ZOOM_FACTOR ** level;
  }
  return Math.round(Math.max(HEX_W_MIN, w) * 10) / 10;
}

/**
 * カードの大きさ（px。012 §4.1 の表。narrow 以外は wide の値）。
 * @param {'wide' | 'medium' | 'narrow'} layout
 * @returns {Record<string, [number, number]>} 種別 → [幅, 高さ]
 */
export function cardSizes(layout) {
  const narrow = layout === 'narrow';
  return {
    hand: narrow ? [88, 123] : [120, 168],
    satellite: narrow ? [132, 94] : [240, 172],
    action: narrow ? [60, 84] : [84, 117],
    actionOther: narrow ? [36, 50] : [48, 67],
    mission: narrow ? [60, 84] : [84, 117],
    reward: narrow ? [76, 106] : [108, 151],
  };
}

/**
 * 拡大表示の幅（px。012 §4.1: 通常 360、衛星カード 540、スマホは min(92 vw, ...)）。
 * @param {boolean} landscape 衛星カード（横長）か
 * @param {number} viewWidth 見えている幅（px）
 * @returns {number}
 */
export function zoomWidth(landscape, viewWidth) {
  const base = landscape ? 540 : 360;
  return Math.min(Math.floor(viewWidth * 0.92), base);
}

/**
 * 拡大表示の高さの上限（012 §4.6、§10 教訓 3）。`100vh` ではなく `window.innerHeight` からステータスバー下端を引く。
 * @param {number} innerHeight window.innerHeight
 * @param {number} barBottom ステータスバーの下端（px。無ければ 0）
 * @param {number} [margin]
 * @returns {number}
 */
export function zoomAvailableHeight(innerHeight, barBottom, margin = 8) {
  return Math.max(0, innerHeight - Math.max(margin, barBottom + margin) - margin);
}

/**
 * 選択できる区域の外接矩形が収まる最大の段階を選ぶ（012 §3.8 選択モード。narrow のみ）。
 * @param {number} viewportWidth 盤面のビューポートの幅（px）
 * @param {number} spanCols 選べる区域の外接矩形の幅（区域数。0.5 区域ぶんの余白込み）
 * @returns {'overview' | 'normal' | 'large'}
 */
export function narrowStepFor(viewportWidth, spanCols) {
  if (spanCols * NARROW_STEPS.large <= viewportWidth) {
    return 'large';
  }
  if (spanCols * NARROW_STEPS.normal <= viewportWidth) {
    return 'normal';
  }
  return 'overview';
}
