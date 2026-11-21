/**
 * レイアウトの計算（仕様書 012 §2.2、§3.8）。DOM に触れない純粋関数。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** 区域の幅の上限（wide。px） */
export const HEX_W_MAX = 150;
/** 区域の幅の下限（px。これより小さくは拡縮しない） */
export const HEX_W_MIN = 32;
/** スマホ（narrow）の段階: 標準 52 px、大 76 px（012 §3.8）。概観は画面幅に収める */
export const NARROW_STEPS = { normal: 52, large: 76 };
/** スマホの段の並び（小さい順）。拡縮ボタンはこの順に 1 つずつ移る */
export const NARROW_STEP_ORDER = /** @type {const} */ (['overview', 'normal', 'large']);
/** `#pe-root` の幅の上限（px。012 追補 U-2） */
export const ROOT_MAX_WIDTH = 1400;
/** `#pe-root` の左右の余白（px。操作しない余白。012 追補 U-2） */
export const ROOT_GUTTER = 12;

/**
 * `#pe-root` の外寸の幅から、左右の余白を引いた内側の幅。盤面の幅と data-layout の判定はこの値で行う。
 * @param {number} rootWidth `#pe-root` の外寸の幅（px）
 * @returns {number}
 */
export function innerWidthOf(rootWidth) {
  return Math.max(0, rootWidth - ROOT_GUTTER * 2);
}

/**
 * スマホの段を delta 段ずらす（端で止まる。拡縮ボタンの [−]=-1、[+]=+1）。
 * @param {'overview' | 'normal' | 'large'} step
 * @param {number} delta
 * @returns {'overview' | 'normal' | 'large'}
 */
export function shiftNarrowStep(step, delta) {
  const i = NARROW_STEP_ORDER.indexOf(step) + delta;
  return NARROW_STEP_ORDER[Math.max(0, Math.min(NARROW_STEP_ORDER.length - 1, i))];
}

/**
 * `#pe-root` の内側の幅 → data-layout（012 §2.2）。
 * @param {number} rootWidth 余白を引いた内側の幅（`innerWidthOf`）
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
 * @param {object} p
 * @param {number} p.rootWidth `#pe-root` の内側の幅（px。`innerWidthOf`）
 * @param {number} p.cols 盤面の列数（12 / 9 / 6）
 * @param {'overview' | 'normal' | 'large'} [p.narrowStep] スマホの段階
 * @returns {number} px（小数 1 桁に丸める）
 */
export function computeHexWidth({ rootWidth, cols, narrowStep = 'normal' }) {
  const layout = layoutOf(rootWidth);
  const fit = fitHexWidth(rootWidth, cols);
  let w;
  if (layout === 'narrow') {
    w = narrowStep === 'overview' ? fit : NARROW_STEPS[narrowStep];
  } else {
    w = layout === 'wide' ? Math.min(HEX_W_MAX, fit) : fit;
  }
  return Math.round(Math.max(HEX_W_MIN, w) * 10) / 10;
}

/**
 * カードの大きさ（px。012 §4.1 の表。narrow 以外は wide の値）。衛星カードは流動（`satelliteWidths`）。
 * @param {'wide' | 'medium' | 'narrow'} layout
 * @returns {Record<string, [number, number]>} 種別 → [幅, 高さ]
 */
export function cardSizes(layout) {
  const narrow = layout === 'narrow';
  return {
    hand: narrow ? [88, 123] : [120, 168],
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

/** 衛星カードの幅の上限（px。012 追補 U-6）: 自分 240、スマホの自分 200、他プレイヤー 160 */
export const SAT_W_MINE_MAX = 240;
export const SAT_W_MINE_NARROW_MAX = 200;
export const SAT_W_OTHER_MAX = 160;
/** スマホの他プレイヤーの衛星の幅（横 1 列のスクロール。px） */
export const SAT_W_OTHER_NARROW = 120;
/** 自分の衛星を 5 機 1 行に並べる幅の下限（px）。これ未満だと配備カードのチップの名前が切れるので、3 機ずつ（3 + 2 の 2 行）にする */
export const SAT_W_MINE_ROW_MIN = 200;
/** プレイヤーの領域の左右の余白と枠（padding 10 px × 2 + border 1 px × 2。CSS の 10 プレイヤーの領域と合わせる） */
export const PLAYER_AREA_PAD = 22;
/** 衛星の間隔（px。CSS の `.pe-sats` の gap と合わせる） */
export const SAT_GAP = 10;
/** 1 人分の衛星の数 */
export const SAT_COUNT = 5;
/** 他プレイヤーの領域の列の間隔（px。CSS の `#pe-others` の gap と合わせる） */
export const OTHERS_GAP = 10;

/**
 * 他プレイヤーの領域を並べる列数。wide だけ 2 列（他が 1 人なら 1 列）、それ以外は 1 列。
 * @param {'wide' | 'medium' | 'narrow'} layout
 * @param {number} otherCount 他プレイヤーの人数（観戦者は全員）
 * @returns {number}
 */
export function othersColumns(layout, otherCount) {
  return layout === 'wide' && otherCount >= 2 ? 2 : 1;
}

/**
 * 衛星カードの幅 5 機が 1 行に収まる幅（px。切り捨て）。
 * @param {number} areaWidth 領域（プレイヤーの面）の外寸の幅
 * @returns {number}
 */
function fitFiveInRow(areaWidth) {
  return fitInRow(areaWidth, SAT_COUNT);
}

/**
 * n 機が 1 行に収まる衛星カードの幅（px。切り捨て）。
 * @param {number} areaWidth 領域（プレイヤーの面）の外寸の幅
 * @param {number} n 1 行の機数
 * @returns {number}
 */
function fitInRow(areaWidth, n) {
  return Math.floor((areaWidth - PLAYER_AREA_PAD - SAT_GAP * (n - 1)) / n);
}

/**
 * 衛星カードの幅（px。012 追補 U-6）。`Layout.update` が px の CSS 変数として入れる（`cqw` は使わない）。
 * 自分: wide・medium は 5 機が 1 行に収まる幅（上限 240）。それが 200（`SAT_W_MINE_ROW_MIN`）未満なら 3 機ずつ 2 行の幅（上限 240）。narrow は 2 列（上限 200）。
 * 他プレイヤー: 他の列の幅から 5 機が 1 行に収まる幅（上限 160）、narrow は 120（横 1 列のスクロール）。
 * @param {object} p
 * @param {'wide' | 'medium' | 'narrow'} p.layout
 * @param {number} p.rootWidth `#pe-root` の内側の幅（`innerWidthOf`）
 * @param {number} p.othersColumns 他プレイヤーの列数（`othersColumns`）
 * @returns {{ mine: number, other: number }}
 */
export function satelliteWidths({ layout, rootWidth, othersColumns: columns }) {
  const floorAt = (/** @type {number} */ w) => Math.max(0, w);
  if (layout === 'narrow') {
    const mine = Math.floor((rootWidth - PLAYER_AREA_PAD - SAT_GAP) / 2);
    return { mine: floorAt(Math.min(SAT_W_MINE_NARROW_MAX, mine)), other: SAT_W_OTHER_NARROW };
  }
  const columnWidth = (rootWidth - OTHERS_GAP * (columns - 1)) / columns;
  const five = fitFiveInRow(rootWidth);
  const mine = five >= SAT_W_MINE_ROW_MIN ? five : fitInRow(rootWidth, 3);
  return {
    mine: floorAt(Math.min(SAT_W_MINE_MAX, mine)),
    other: floorAt(Math.min(SAT_W_OTHER_MAX, fitFiveInRow(columnWidth))),
  };
}
