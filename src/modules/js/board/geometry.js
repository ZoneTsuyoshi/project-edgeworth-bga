/**
 * 盤面の幾何（仕様書 003 §1、012 §3.2）。BGA のグローバルに依存しない純粋関数だけを置く（Node で単体テストする）。
 * 座標は 003 のオフセット座標 (row, col)、ダブルド座標 (x, y)、区域 ID（cell = row × cols + col）。
 * ピクセルは区域の幅 W（`--pe-hex-w`）を 1 とした単位で返す関数と、W を渡して px で返す関数がある。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** 区域タイル画像の比（高さ / 幅。435 × 503。012 §1） */
export const TILE_ASPECT = 503 / 435;

/** 盤面の行数（003 §1.1。常に 6） */
export const ROWS = 6;

/**
 * 区域内の 4 マスの中心（タイル幅・高さに対する割合）。区域タイルの原本（435 × 503）の正方形の実測: 左上 (37–128, 132–222)、
 * 右上 (308–399, 132–222)、左下 (37–128, 279–370)、右下 (308–399, 279–370)（全 7 タイルで同じ位置）。tests/js/geometry.test.mjs が原本画像で確かめる。
 * Z 順: 0 = 左上、1 = 右上、2 = 左下、3 = 右下（003 §5.1）。回転しても slot 0 は画面の左上のまま。
 */
export const SLOT_CENTERS = [
  { x: 0.19, y: 0.352 },
  { x: 0.8126, y: 0.352 },
  { x: 0.19, y: 0.645 },
  { x: 0.8126, y: 0.645 },
];

/** マスの大きさ（区域の幅に対する割合。91 px / 435 px） */
export const SLOT_SIZE = 0.209;

/** ダブルド座標の方向（003 §1.2）。[dx, dy] */
export const DIRECTIONS = {
  E: [2, 0],
  W: [-2, 0],
  NE: [1, -1],
  NW: [-1, -1],
  SE: [1, 1],
  SW: [-1, 1],
};

/** 衛星の向き 4 方向（003 §4.1） */
export const SATELLITE_DIRECTIONS = ['NE', 'SE', 'NW', 'SW'];

/**
 * 衛星コマの画像（機首が左向き）を各向きにする CSS rotate の角度（度。時計回り。012 §3.6）。
 * @type {Record<string, number>}
 */
export const SATELLITE_ROTATION = { NE: 120, SE: 240, NW: 60, SW: 300 };

/**
 * 行のずれ（0 または 1。1 の行は半区域右にずれる。003 §1.1）。
 * @param {number} row
 * @param {number} rowShift 盤面全体の P（0 または 1）
 * @returns {number}
 */
export function shiftOf(row, rowShift) {
  return (((row + rowShift) % 2) + 2) % 2;
}

/**
 * @param {number} row
 * @param {number} col
 * @param {number} cols
 * @returns {number}
 */
export function cellId(row, col, cols) {
  return row * cols + col;
}

/**
 * @param {number} cell
 * @param {number} cols
 * @returns {{ row: number, col: number }}
 */
export function cellRowCol(cell, cols) {
  return { row: Math.floor(cell / cols), col: cell % cols };
}

/**
 * オフセット座標 → ダブルド座標（003 §1.2）。
 * @param {number} row
 * @param {number} col
 * @param {number} rowShift
 * @returns {{ x: number, y: number }}
 */
export function toDoubled(row, col, rowShift) {
  return { x: 2 * col + shiftOf(row, rowShift), y: row };
}

/**
 * ダブルド座標 → オフセット座標。左右は接続する（x を 2 × cols で割った余り）。上下の盤面外は null。
 * @param {number} x
 * @param {number} y
 * @param {number} cols
 * @param {number} rowShift
 * @param {number} [rows]
 * @returns {{ row: number, col: number } | null}
 */
export function fromDoubled(x, y, cols, rowShift, rows = ROWS) {
  if (y < 0 || y >= rows) {
    return null;
  }
  const period = 2 * cols;
  const wrapped = ((x % period) + period) % period;
  return { row: y, col: (wrapped - shiftOf(y, rowShift)) / 2 };
}

/**
 * 隣の区域（左右は接続、上下の盤面外は null）。
 * @param {number} cell
 * @param {string} dir E / W / NE / NW / SE / SW
 * @param {number} cols
 * @param {number} rowShift
 * @returns {number | null}
 */
export function neighbor(cell, dir, cols, rowShift) {
  const d = DIRECTIONS[/** @type {keyof typeof DIRECTIONS} */ (dir)];
  if (d === undefined) {
    return null;
  }
  const { row, col } = cellRowCol(cell, cols);
  const p = toDoubled(row, col, rowShift);
  const n = fromDoubled(p.x + d[0], p.y + d[1], cols, rowShift);
  return n === null || !Number.isInteger(n.col) ? null : cellId(n.row, n.col, cols);
}

/**
 * 区域の高さ（W に対する px。012 §3.2）。
 * @param {number} w
 * @returns {number}
 */
export function hexHeight(w) {
  return w * TILE_ASPECT;
}

/**
 * 区域の左上（W を 1 とした単位。012 §3.2: left = (col + 0.5 × shift) × W、top = row × 0.75 × H）。
 * @param {number} row
 * @param {number} col
 * @param {number} rowShift
 * @returns {{ left: number, top: number }}
 */
export function cellOriginUnits(row, col, rowShift) {
  return { left: col + 0.5 * shiftOf(row, rowShift), top: row * 0.75 * TILE_ASPECT };
}

/**
 * 区域の左上（px）。
 * @param {number} row
 * @param {number} col
 * @param {number} rowShift
 * @param {number} w 区域の幅（px）
 * @returns {{ left: number, top: number }}
 */
export function cellOrigin(row, col, rowShift, w) {
  const o = cellOriginUnits(row, col, rowShift);
  return { left: o.left * w, top: o.top * w };
}

/**
 * 盤面の大きさ（px）。幅 = (cols + 0.5) × W、高さ = (0.75 × (rows - 1) + 1) × H（012 §3.2）。
 * @param {number} cols
 * @param {number} w
 * @param {number} [rows]
 * @returns {{ width: number, height: number }}
 */
export function boardSize(cols, w, rows = ROWS) {
  return { width: (cols + 0.5) * w, height: (0.75 * (rows - 1) + 1) * hexHeight(w) };
}

/**
 * 盤面の高さを W に対する単位で返す（SVG の viewBox 用）。
 * @param {number} [rows]
 * @returns {number}
 */
export function boardHeightUnits(rows = ROWS) {
  return (0.75 * (rows - 1) + 1) * TILE_ASPECT;
}

/**
 * 区域の中心（px）。
 * @param {number} row
 * @param {number} col
 * @param {number} rowShift
 * @param {number} w
 * @returns {{ x: number, y: number }}
 */
export function cellCenter(row, col, rowShift, w) {
  const o = cellOrigin(row, col, rowShift, w);
  return { x: o.left + w / 2, y: o.top + hexHeight(w) / 2 };
}

/**
 * 六角形（pointy-top）の頂点。上から時計回りに v0（上）、v1（右上）、v2（右下）、v3（下）、v4（左下）、v5（左上）。
 * @param {number} left 区域の左端
 * @param {number} top 区域の上端
 * @param {number} w 区域の幅
 * @returns {{ x: number, y: number }[]}
 */
export function hexVertices(left, top, w) {
  const h = hexHeight(w);
  return [
    { x: left + w / 2, y: top },
    { x: left + w, y: top + h / 4 },
    { x: left + w, y: top + (3 * h) / 4 },
    { x: left + w / 2, y: top + h },
    { x: left, y: top + (3 * h) / 4 },
    { x: left, y: top + h / 4 },
  ];
}

/** 方向 → その辺の頂点の組（hexVertices の添字） */
const EDGE_VERTICES = { NE: [0, 1], E: [1, 2], SE: [2, 3], SW: [3, 4], W: [4, 5], NW: [5, 0] };

/**
 * 左右の接続のゴースト（012 §3.7）。ずれた行の左端と、ずれていない行の右端にできる半区域ぶんの空きに、
 * 反対側の端の区域を描く。盤面の幅は増えない（はみ出す部分は盤面の overflow で隠す）。
 * @param {number} cols
 * @param {number} rowShift
 * @param {number} [rows]
 * @returns {{ cell: number, row: number, col: number, side: 'left' | 'right', left: number }[]} left は W を 1 とした区域の左端（負または cols）
 */
export function ghostCells(cols, rowShift, rows = ROWS) {
  const out = [];
  for (let row = 0; row < rows; row++) {
    if (shiftOf(row, rowShift) === 1) {
      // 左の空きに、同じ行の右端の区域（その右半分が見える）
      out.push({ cell: cellId(row, cols - 1, cols), row, col: cols - 1, side: /** @type {const} */ ('left'), left: -0.5 });
    } else {
      // 右の空きに、同じ行の左端の区域（その左半分が見える）
      out.push({ cell: cellId(row, 0, cols), row, col: 0, side: /** @type {const} */ ('right'), left: cols });
    }
  }
  return out;
}

/**
 * 地域の外周の線分（012 §3.5）。隣り合う区域の地域（boardId）が違う辺だけを集める。
 * 左右の接続をまたぐ辺は、盤面の端（ゴーストとの境界）の位置に出る。上下の盤面の外周は含めない。
 * 座標は W を 1 とした単位。
 * @param {string[]} regionOfCell 区域 ID → 地域（A〜H）
 * @param {number} cols
 * @param {number} rowShift
 * @param {number} [rows]
 * @returns {{ x1: number, y1: number, x2: number, y2: number }[]}
 */
export function regionBorderSegments(regionOfCell, cols, rowShift, rows = ROWS) {
  const seen = new Set();
  const out = [];
  const round = (/** @type {number} */ v) => Math.round(v * 1000) / 1000;
  for (let row = 0; row < rows; row++) {
    for (let col = 0; col < cols; col++) {
      const cell = cellId(row, col, cols);
      const o = cellOriginUnits(row, col, rowShift);
      const v = hexVertices(o.left, o.top, 1);
      for (const [dir, [a, b]] of Object.entries(EDGE_VERTICES)) {
        const n = neighbor(cell, dir, cols, rowShift);
        if (n === null || regionOfCell[n] === regionOfCell[cell]) {
          continue;
        }
        const seg = { x1: round(v[a].x), y1: round(v[a].y), x2: round(v[b].x), y2: round(v[b].y) };
        const key = [seg.x1, seg.y1, seg.x2, seg.y2].join(',');
        const rev = [seg.x2, seg.y2, seg.x1, seg.y1].join(',');
        if (!seen.has(key) && !seen.has(rev)) {
          seen.add(key);
          out.push(seg);
        }
      }
    }
  }
  return out;
}

/**
 * 地域の中央の区域（地域アイコンを置く (lr, lc) = (1, 1)。180° 回転しても動かない。003 §2.3）。
 * @param {number} br 段（0 = 上段）
 * @param {number} bc 段内の位置
 * @param {number} cols
 * @returns {number}
 */
export function regionCenterCell(br, bc, cols) {
  return cellId(3 * br + 1, 3 * bc + 1, cols);
}

/**
 * 同じマスに重なるキューブのずらし（012 §3.4: 右上へ約 12 %。マスの大きさに対する割合）。
 * @param {number} level 段（0 = 一番下）
 * @returns {{ dx: number, dy: number }}
 */
export function cubeStackOffset(level) {
  return { dx: 0.12 * level, dy: -0.12 * level };
}

/**
 * 同じ区域に複数の衛星がいるときのずらし（012 §3.6: 中心から 0.12 W ずつ）。
 * @param {number} index 区域内での順番（0 始まり）
 * @returns {{ dx: number, dy: number }}
 */
export function satelliteStackOffset(index) {
  return { dx: 0.12 * index, dy: 0.12 * index };
}

/**
 * 時計回りの角度を、いま表示している角度から最短で回るように連続させる（0° ↔ 300° で一周しない）。
 * @param {number} current 現在の（累積した）角度
 * @param {number} target 目標（0〜360）
 * @returns {number}
 */
export function nearestAngle(current, target) {
  let delta = (((target - current) % 360) + 360) % 360;
  if (delta > 180) {
    delta -= 360;
  }
  return current + delta;
}
