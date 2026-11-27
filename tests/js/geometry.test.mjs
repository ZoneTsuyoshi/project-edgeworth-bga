// board/geometry.js・ui/layout-math.js・PieceLayer の純粋関数の単体テスト（仕様書 012 §3.2、003 §1・§4）。実行: npm run test:js
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';
import {
  BASE_CENTER, SATELLITE_DIRECTIONS, SATELLITE_ROTATION, boardSize, cellId, cellOriginUnits, cellRowCol, fromDoubled, ghostCells, hexHeight, nearestAngle, neighbor, outlineSegments, regionBorderSegments, regionCenterCell, shiftOf, SLOT_CENTERS, SLOT_SIZE, toDoubled, TILE_ASPECT,
} from '../../src/modules/js/board/geometry.js';
import { cardSizes, computeHexWidth, HEX_W_MAX, HEX_W_MIN, innerWidthOf, layoutOf, narrowStepFor, NARROW_STEPS, othersColumns, ROOT_GUTTER, ROOT_GUTTER_NARROW, rootMetrics, satelliteWidths, shiftNarrowStep, supplyCardSize, zoomAvailableHeight, zoomWidth } from '../../src/modules/js/ui/layout-math.js';

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

test('レイアウトの計算（014 §2.2）', () => {
  // しきい値 960 / 640
  assert.equal(layoutOf(960), 'wide');
  assert.equal(layoutOf(959), 'medium');
  assert.equal(layoutOf(640), 'medium');
  assert.equal(layoutOf(639), 'narrow');
  // wide: min(112, 幅に収まる値（場の列 84 + 間隔 12 を引く）, 高さの上限)
  assert.equal(HEX_W_MAX, 112);
  assert.equal(computeHexWidth({ rootWidth: 2400, cols: 12 }), 112);
  assert.equal(computeHexWidth({ rootWidth: 1250, cols: 12 }), 92.3);
  assert.equal(computeHexWidth({ rootWidth: 1250, cols: 6 }), 112);
  // medium: 幅に収まる値（場は盤面の下なので引かない）
  assert.equal(computeHexWidth({ rootWidth: 800, cols: 12 }), 64);
  // narrow: 標準 56 / 大 80。概観は幅に収める
  assert.deepEqual(NARROW_STEPS, { normal: 56, large: 80 });
  assert.equal(computeHexWidth({ rootWidth: 390, cols: 12 }), 56);
  assert.equal(computeHexWidth({ rootWidth: 390, cols: 12, narrowStep: 'large' }), 80);
  // 概観は見るだけの段。区域の下限 32 px を当てない（390 px の幅に 12 列を収めると 31.2 px）
  assert.equal(computeHexWidth({ rootWidth: 390, cols: 12, narrowStep: 'overview' }), 31.2);
  assert.ok(computeHexWidth({ rootWidth: 390, cols: 12, narrowStep: 'overview' }) < HEX_W_MIN);
  assert.equal(computeHexWidth({ rootWidth: 600, cols: 12, narrowStep: 'overview' }), 48);
  assert.equal(computeHexWidth({ rootWidth: 600, cols: 6, narrowStep: 'overview' }), 92.3);
  // 標準・大・wide・medium は 32 px 以上
  assert.ok(computeHexWidth({ rootWidth: 700, cols: 24 }) >= HEX_W_MIN);
  assert.equal(narrowStepFor(390, 4), 'large');
  assert.equal(narrowStepFor(390, 6.5), 'normal');
  assert.equal(narrowStepFor(390, 12.5), 'overview');
});

test('区域の幅の高さの上限（014 §2.2。画面の高さ × 0.62 ÷ 5.49、下限 56）', () => {
  // 高さ 600 の画面: 600 × 0.62 ÷ 5.49 = 67.8
  assert.equal(computeHexWidth({ rootWidth: 1250, cols: 12, innerHeight: 600 }), 67.8);
  assert.equal(computeHexWidth({ rootWidth: 800, cols: 12, innerHeight: 500 }), 56.5);
  // 幅の上限のほうが小さければそちら
  assert.equal(computeHexWidth({ rootWidth: 800, cols: 12, innerHeight: 900 }), 64);
  // 下限 56（画面が低くても、これ以上は小さくしない）
  assert.equal(computeHexWidth({ rootWidth: 1250, cols: 12, innerHeight: 300 }), 56);
  // 高さを渡さなければ上限なし
  assert.equal(computeHexWidth({ rootWidth: 1250, cols: 12 }), 92.3);
  // narrow には高さの上限を当てない
  assert.equal(computeHexWidth({ rootWidth: 390, cols: 12, innerHeight: 300 }), 56);
});

test('場のカードの大きさ（014 §2.2）', () => {
  // wide: 盤面の高さ（区域の幅 × 5.49）から 104 を引いて 4 で割る。上限 117
  assert.deepEqual(supplyCardSize('wide', 112), [84, 117]);
  assert.deepEqual(supplyCardSize('wide', 82.3), [62, 86]);
  assert.deepEqual(supplyCardSize('wide', 56), [36, 50]);
  assert.equal(supplyCardSize('wide', 0)[1], 0);
  // 幅は 715 : 999 の縦横比
  const [w, h] = supplyCardSize('wide', 90);
  assert.ok(Math.abs(w / h - 715 / 999) < 0.02);
  assert.deepEqual(supplyCardSize('medium', 64), [72, 101]);
  assert.deepEqual(supplyCardSize('narrow', 56), [64, 90]);
});

test('内側の幅と拡縮の段（014 §2.1、012 追補 U-6）', () => {
  assert.equal(ROOT_GUTTER, 12);
  assert.equal(ROOT_GUTTER_NARROW, 8);
  assert.equal(innerWidthOf(1400), 1376);
  // narrow だけ余白が 8 px
  assert.equal(innerWidthOf(390), 374);
  assert.equal(innerWidthOf(10), 0);
  // data-layout は余白 12 px を引いた幅で決め、narrow だけ余白を 8 px にして内側の幅を求める
  assert.deepEqual(rootMetrics(984), { layout: 'wide', width: 960 });
  assert.deepEqual(rootMetrics(983), { layout: 'medium', width: 959 });
  assert.deepEqual(rootMetrics(664), { layout: 'medium', width: 640 });
  assert.deepEqual(rootMetrics(663), { layout: 'narrow', width: 647 });
  assert.deepEqual(rootMetrics(390), { layout: 'narrow', width: 374 });
  // 1400 px 上限の根では、盤面は内側 1376 px で、場の列を引いて 12 列が 102.4 px
  assert.equal(computeHexWidth({ rootWidth: innerWidthOf(1400), cols: 12 }), 102.4);
  // 段は 1 つずつ移り、端で止まる
  assert.equal(shiftNarrowStep('normal', -1), 'overview');
  assert.equal(shiftNarrowStep('normal', 1), 'large');
  assert.equal(shiftNarrowStep('overview', -1), 'overview');
  assert.equal(shiftNarrowStep('large', 1), 'large');
  assert.equal(shiftNarrowStep('overview', 1), 'normal');
  assert.equal(shiftNarrowStep('large', -1), 'normal');
});

test('カードの大きさの名前は小文字にそろえて CSS 変数にする（--pe-actionother-w の不具合）', () => {
  // Layout.update は `--pe-${name.toLowerCase()}-w` を入れる。CSS が読む名前は --pe-actionother-w（小文字）
  for (const layout of /** @type {const} */ (['wide', 'medium', 'narrow'])) {
    const names = Object.keys(cardSizes(layout)).map((n) => n.toLowerCase());
    assert.ok(names.includes('actionother'));
    assert.ok(!names.includes('reward'), '褒賞は場のカード（supplyCardSize）に統一');
  }
  assert.deepEqual(cardSizes('narrow').actionOther, [36, 50]);
});

test('カードの大きさ（014 §2.2 の表）', () => {
  assert.deepEqual(cardSizes('wide'), { hand: [150, 210], action: [92, 129], actionOther: [44, 62], mission: [92, 129] });
  assert.deepEqual(cardSizes('medium'), { hand: [132, 184], action: [84, 117], actionOther: [44, 62], mission: [84, 117] });
  // narrow: 手札 104 × 145、ミッション 64 × 90、他プレイヤー 36 × 50。アクションカードは 5 枚が 1 行に入る幅 (W − 24) ÷ 5、上限 64
  assert.deepEqual(cardSizes('narrow', 374), { hand: [104, 145], action: [64, 90], actionOther: [36, 50], mission: [64, 90] });
  assert.deepEqual(cardSizes('narrow').action, [64, 90]);
  assert.deepEqual(cardSizes('narrow', 344).action, [64, 90]);
  assert.equal(cardSizes('narrow', 320).action[0], 59);
  for (const w of [280, 300, 320, 344, 374, 500, 639]) {
    const [aw] = cardSizes('narrow', w).action;
    assert.ok(aw * 5 + 6 * 4 <= w, `width ${w}: アクションカード 5 枚が 1 行に入る`);
  }
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

// data/（原本）は作者の手元だけにある。無いとき（CI）は skip する
const hasData = existsSync(new URL('../../data/boards/area/', import.meta.url));
test('キューブのマスの中心が、区域タイル原本の 4 つの正方形の上にある（Z 順: 左上・右上・左下・右下）', { skip: hasData ? false : 'data/ が無い（原本は手元だけ）' }, async () => {
  const { default: sharp } = await import('sharp');
  const dir = new URL('../../data/boards/area/', import.meta.url);
  for (const name of ['陸', '海', '山岳']) {
    const { data, info } = await sharp(fileURLToPath(new URL(`${name}@4x.png`, dir))).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
    const lum = (/** @type {number} */ x, /** @type {number} */ y) => {
      const i = (Math.round(y) * info.width + Math.round(x)) * 4;
      return data[i] + data[i + 1] + data[i + 2];
    };
    // 正方形の縁の少し内側と少し外側の輝度が、はっきり違う（縁がマスの縁と一致する。区域の模様と重なる辺が 1 つあってもよい）
    for (const c of SLOT_CENTERS) {
      const cx = c.x * info.width;
      const cy = c.y * info.height;
      const half = (SLOT_SIZE * info.width) / 2;
        const edge = [[-1, 0], [1, 0], [0, -1], [0, 1]].map(([dx, dy]) => Math.abs(lum(cx + dx * (half - 4), cy + dy * (half - 4)) - lum(cx + dx * (half + 5), cy + dy * (half + 5))));
      assert.ok(edge.filter((d) => d > 12).length >= 3, `${name}: 正方形の縁が中心の推定とずれている (${c.x}, ${c.y}) ${edge}`);
    }
  }
  // Z 順
  assert.ok(SLOT_CENTERS[0].x < SLOT_CENTERS[1].x && SLOT_CENTERS[0].y === SLOT_CENTERS[1].y);
  assert.ok(SLOT_CENTERS[2].y > SLOT_CENTERS[0].y && SLOT_CENTERS[2].x === SLOT_CENTERS[0].x);
  assert.ok(SLOT_CENTERS[3].x === SLOT_CENTERS[1].x && SLOT_CENTERS[3].y === SLOT_CENTERS[2].y);
});

test('発射拠点コマは区域の左上のマス（slot 0）の中心に置く。キューブは slot 1 以降（作者回答 2026-10-20）', () => {
  assert.deepEqual(BASE_CENTER, SLOT_CENTERS[0]);
  assert.ok(BASE_CENTER.x < SLOT_CENTERS[1].x && BASE_CENTER.y < SLOT_CENTERS[2].y);
});

test('衛星の向きは 6 方向で、コマの回転角は 60° ずつ（003 §4.1、012 §3.6）', () => {
  assert.deepEqual([...SATELLITE_DIRECTIONS].sort(), ['E', 'NE', 'NW', 'SE', 'SW', 'W']);
  // 原画は機首が左向き（W = 0°）。時計回りに W → NW → NE → E → SE → SW
  assert.deepEqual(['W', 'NW', 'NE', 'E', 'SE', 'SW'].map((d) => SATELLITE_ROTATION[d]), [0, 60, 120, 180, 240, 300]);
});

test('ミッションの枠: 集合の外に接する辺だけ（1 区域は 6 辺、隣り合う 2 区域は 10 辺）。左右をまたぐ形は間の辺を出さない', () => {
  assert.equal(outlineSegments([cellId(2, 3, 12)], 12, 0).length, 6);
  const east = neighbor(cellId(2, 3, 12), 'E', 12, 0);
  assert.equal(outlineSegments([cellId(2, 3, 12), east], 12, 0).length, 10);
  // 右端と左端はつながっている
  assert.equal(outlineSegments([cellId(2, 11, 12), cellId(2, 0, 12)], 12, 0).length, 10);
  assert.equal(outlineSegments([], 12, 0).length, 0);
});

test('他プレイヤーの列数と衛星カードの流動の幅（014 §2.2）', () => {
  // wide で幅 1180 px 以上のときだけ 2 列（他が 1 人なら 1 列）、それ以外は 1 列
  assert.equal(othersColumns('wide', 3, 1180), 2);
  assert.equal(othersColumns('wide', 3, 1179), 1);
  assert.equal(othersColumns('wide', 3), 2);
  assert.equal(othersColumns('wide', 2), 2);
  assert.equal(othersColumns('wide', 1), 1);
  assert.equal(othersColumns('medium', 3), 1);
  assert.equal(othersColumns('narrow', 3), 1);
  // wide（内側 1376 px）: 自分は上限 230、他は 2 列で列の幅（676 px）から (676 − 32) ÷ 5 = 128、1 列なら上限 150
  assert.deepEqual(satelliteWidths({ layout: 'wide', rootWidth: 1376, othersColumns: 2 }), { mine: 230, other: 128 });
  assert.deepEqual(satelliteWidths({ layout: 'wide', rootWidth: 1376, othersColumns: 1 }), { mine: 230, other: 150 });
  // wide でも幅に収まる値のほうが小さければそちら: (1000 − 48) ÷ 5 = 190
  assert.equal(satelliteWidths({ layout: 'wide', rootWidth: 1000, othersColumns: 1 }).mine, 190);
  // medium（内側 700 px）: 自分は (W − 40) ÷ 5 = 132、他は min(150, (W − 32) ÷ 5) = 133。上限は無い（自分）
  assert.deepEqual(satelliteWidths({ layout: 'medium', rootWidth: 700, othersColumns: 1 }), { mine: 132, other: 133 });
  assert.deepEqual(satelliteWidths({ layout: 'medium', rootWidth: 640, othersColumns: 1 }), { mine: 120, other: 121 });
  // 1 行に収まる: 5 機 + 間隔（自分 wide 12 / medium 10、他 8）が根の幅以内
  for (const w of [640, 700, 800, 959, 960, 1000, 1180, 1376, 1400]) {
    const layout = layoutOf(w);
    const { mine, other } = satelliteWidths({ layout, rootWidth: w, othersColumns: 1 });
    assert.ok(mine * 5 + (layout === 'wide' ? 12 : 10) * 4 <= w, `width ${w}`);
    assert.ok(other * 5 + 8 * 4 <= w, `width ${w}: 他プレイヤー`);
  }
  // narrow: 自分は 3 列で (W − 16) ÷ 3（390 px の画面 = 内側 374 px で 119）、他は 5 機 1 行で (W − 24) ÷ 5（同 70）
  assert.deepEqual(satelliteWidths({ layout: 'narrow', rootWidth: 374, othersColumns: 1 }), { mine: 119, other: 70 });
  assert.deepEqual(satelliteWidths({ layout: 'narrow', rootWidth: 344, othersColumns: 1 }), { mine: 109, other: 64 });
  assert.ok(satelliteWidths({ layout: 'narrow', rootWidth: 344, othersColumns: 1 }).other * 5 + 6 * 4 <= 344);
  assert.equal(satelliteWidths({ layout: 'narrow', rootWidth: 0, othersColumns: 1 }).mine, 0);
});
