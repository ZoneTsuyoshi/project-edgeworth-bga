// board/geometry.js・ui/layout-math.js・PieceLayer の純粋関数の単体テスト（仕様書 012 §3.2、003 §1・§4）。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
  boardSize, cellId, cellOriginUnits, cellRowCol, fromDoubled, ghostCells, hexHeight, nearestAngle, neighbor, regionBorderSegments, regionCenterCell, shiftOf, toDoubled, TILE_ASPECT,
} from '../../src/modules/js/board/geometry.js';
import { computeHexWidth, layoutOf, narrowStepFor, zoomAvailableHeight, zoomWidth } from '../../src/modules/js/ui/layout-math.js';

test('行のずれと区域 ID（003 §1.1・§1.4）', () => {
  assert.equal(shiftOf(1, 0), 1);
  assert.equal(shiftOf(0, 1), 1);
  assert.equal(shiftOf(2, 1), 1);
  assert.equal(cellId(2, 5, 12), 29);
  assert.deepEqual(cellRowCol(29, 12), { row: 2, col: 5 });
});

test('ダブルド座標との往復と左右の接続（003 §1.2）', () => {
  assert.deepEqual(toDoubled(1, 3, 0), { x: 7, y: 1 });
  assert.deepEqual(fromDoubled(7, 1, 12, 0), { row: 1, col: 3 });
  // 左端の行 0（x = 0）で SW: x = -1 → 2W - 1、y = 1（p.17 例 6）。P = 0 の行 1 は右にずれるので col = (23 - 1) / 2 = 11
  assert.equal(neighbor(cellId(0, 0, 12), 'SW', 12, 0), cellId(1, 11, 12));
  // 上端の NE は盤面外
  assert.equal(neighbor(cellId(0, 4, 12), 'NE', 12, 0), null);
  // 右端の SE（行 0、col 11、P=0）→ x = 22 + 1 = 23 → 行 1 の col 11（ずれ 1）
  assert.equal(neighbor(cellId(0, 11, 12), 'SE', 12, 0), cellId(1, 11, 12));
  // 同じ行の E は右端で左端へ
  assert.equal(neighbor(cellId(2, 11, 12), 'E', 12, 0), cellId(2, 0, 12));
});

test('区域の位置と盤面の大きさ（012 §3.2）', () => {
  assert.ok(Math.abs(TILE_ASPECT - 1.156) < 0.001);
  assert.deepEqual(cellOriginUnits(0, 0, 0), { left: 0, top: 0 });
  assert.equal(cellOriginUnits(1, 2, 0).left, 2.5);
  assert.ok(Math.abs(cellOriginUnits(2, 0, 0).top - 1.5 * TILE_ASPECT) < 1e-9);
  const size = boardSize(12, 100);
  assert.equal(size.width, 1250);
  assert.ok(Math.abs(size.height - 4.75 * hexHeight(100)) < 1e-9);
  assert.equal(boardSize(9, 10).width, 95);
  assert.equal(boardSize(6, 10).width, 65);
});

test('ゴースト: ずれた行は左、ずれていない行は右（012 §3.7）', () => {
  const g = ghostCells(12, 0);
  assert.equal(g.length, 6);
  assert.deepEqual(g[0], { cell: 0, row: 0, col: 0, side: 'right', left: 12 });
  assert.deepEqual(g[1], { cell: 23, row: 1, col: 11, side: 'left', left: -0.5 });
  assert.equal(ghostCells(6, 1)[0].side, 'left');
});

test('地域の外周: 違う地域の辺だけ。左右の接続の辺も出る（012 §3.5）', () => {
  // 2 列 × 6 行の盤面で、左列が地域 A、右列が地域 B
  const regions = [];
  for (let r = 0; r < 6; r++) {
    regions.push('A', 'B');
  }
  const segs = regionBorderSegments(regions, 2, 0);
  assert.ok(segs.length > 0);
  const same = regionBorderSegments(regions.map(() => 'A'), 2, 0);
  assert.equal(same.length, 0);
  // 地域アイコンの区域（lr, lc) = (1, 1)
  assert.equal(regionCenterCell(1, 2, 12), cellId(4, 7, 12));
});

test('レイアウトの計算（012 §2.2、§3.8）', () => {
  assert.equal(layoutOf(1180), 'wide');
  assert.equal(layoutOf(1179), 'medium');
  assert.equal(layoutOf(700), 'medium');
  assert.equal(layoutOf(699), 'narrow');
  assert.equal(computeHexWidth({ rootWidth: 2400, cols: 12 }), 150);
  assert.equal(computeHexWidth({ rootWidth: 1250, cols: 12 }), 100);
  assert.equal(computeHexWidth({ rootWidth: 800, cols: 12 }), 64);
  assert.equal(computeHexWidth({ rootWidth: 390, cols: 12 }), 52);
  assert.equal(computeHexWidth({ rootWidth: 390, cols: 12, narrowStep: 'large' }), 76);
  assert.equal(computeHexWidth({ rootWidth: 390, cols: 12, narrowStep: 'overview' }), 31.2);
  assert.equal(computeHexWidth({ rootWidth: 1250, cols: 12, zoomLevel: 1 }), 125);
  assert.equal(narrowStepFor(390, 4), 'large');
  assert.equal(narrowStepFor(390, 7), 'normal');
  assert.equal(narrowStepFor(390, 12.5), 'overview');
});

test('拡大表示の寸法（012 §4.1、§4.6）', () => {
  assert.equal(zoomWidth(false, 1600), 360);
  assert.equal(zoomWidth(true, 1600), 540);
  assert.equal(zoomWidth(true, 390), 358);
  assert.equal(zoomAvailableHeight(800, 60), 800 - 68 - 8);
});

test('衛星コマの回転は最短で回る', () => {
  assert.equal(nearestAngle(300, 60), 420);
  assert.equal(nearestAngle(60, 300), -60);
  assert.equal(nearestAngle(120, 120), 120);
});
