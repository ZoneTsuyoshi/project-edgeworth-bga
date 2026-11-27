/**
 * ツールチップの位置の計算（src/modules/js/ui/tip-placement.js。仕様書 014 §7）の単体テスト。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { placeTip, TIP_EDGE, TIP_GAP } from '../../src/modules/js/ui/tip-placement.js';

const VIEW = { width: 1366, height: 768, top: 80 };
const rect = (left, top, width, height) => ({ left, top, right: left + width, bottom: top + height });

/** 置いた箱が見える領域に入り、対象と重ならないこと */
function assertInside(anchor, size, view, p) {
  const w = Math.min(size.width, p.maxWidth);
  const h = Math.min(size.height, p.maxHeight);
  const box = rect(p.left, p.top, w, h);
  assert.ok(box.left >= TIP_EDGE - 1e-9, `左 ${box.left}`);
  assert.ok(box.right <= view.width - TIP_EDGE + 1e-9, `右 ${box.right}`);
  assert.ok(box.top >= Math.max(TIP_EDGE, (view.top ?? 0) + TIP_EDGE) - 1e-9, `上 ${box.top}`);
  assert.ok(box.bottom <= view.height - TIP_EDGE + 1e-9, `下 ${box.bottom}`);
  const overlapX = Math.min(box.right, anchor.right) - Math.max(box.left, anchor.left);
  const overlapY = Math.min(box.bottom, anchor.bottom) - Math.max(box.top, anchor.top);
  assert.ok(overlapX <= 0 || overlapY <= 0, '対象を覆っている');
}

test('右に入るときは右（対象のすぐ横、縦は対象の中心）', () => {
  const anchor = rect(100, 400, 150, 210);
  const size = { width: 480, height: 300 };
  const p = placeTip(anchor, size, VIEW);
  assert.equal(p.side, 'right');
  assert.equal(p.left, anchor.right + TIP_GAP);
  assert.equal(p.top, 505 - 150);
  assert.equal(p.fits, true);
  assertInside(anchor, size, VIEW, p);
});

test('右に入らないときは左へ回る', () => {
  const anchor = rect(1100, 300, 150, 210);
  const size = { width: 480, height: 300 };
  const p = placeTip(anchor, size, VIEW);
  assert.equal(p.side, 'left');
  assert.equal(p.left, anchor.left - TIP_GAP - 480);
  assertInside(anchor, size, VIEW, p);
});

test('縦に高くて左右に入らない幅の狭い画面では下へ回る', () => {
  const view = { width: 390, height: 844, top: 70 };
  const anchor = rect(120, 100, 150, 100);
  const size = { width: 374, height: 300 };
  const p = placeTip(anchor, size, view);
  assert.equal(p.side, 'bottom');
  assert.equal(p.top, anchor.bottom + TIP_GAP);
  assert.equal(p.left, TIP_EDGE);
  assertInside(anchor, size, view, p);
});

test('下にも入らないときは上へ回る', () => {
  const view = { width: 390, height: 844, top: 70 };
  const anchor = rect(120, 600, 150, 200);
  const size = { width: 374, height: 340 };
  const p = placeTip(anchor, size, view);
  assert.equal(p.side, 'top');
  assert.equal(p.top, anchor.top - TIP_GAP - 340);
  assertInside(anchor, size, view, p);
});

test('どこにも入らないときは、いちばん広い側の最大の幅と高さを返す', () => {
  const anchor = rect(100, 400, 150, 210);
  const size = { width: 480, height: 900 };
  const p = placeTip(anchor, size, VIEW);
  assert.equal(p.fits, false);
  assert.equal(p.side, 'right');
  // 見える領域の高さ = 768 - 8 - (80 + 8)
  assert.equal(p.maxHeight, 768 - 8 - 88);
  assert.equal(p.maxWidth, 1366 - 8 - anchor.right - TIP_GAP);
  assert.equal(p.top, 88);
  assertInside(anchor, size, VIEW, p);
});

test('ステータスバーの下に置く（上端の制限）', () => {
  const anchor = rect(100, 90, 150, 210);
  const size = { width: 480, height: 300 };
  const p = placeTip(anchor, size, VIEW);
  assert.equal(p.side, 'right');
  assert.equal(p.top, 88);
  const noBar = placeTip(anchor, size, { width: 1366, height: 768 });
  assert.equal(noBar.top, 45);
});

test('画面の下の端でも、下端から 8 px 以内に入らない', () => {
  const anchor = rect(100, 640, 150, 120);
  const size = { width: 480, height: 400 };
  const p = placeTip(anchor, size, VIEW);
  assert.equal(p.top + 400, 768 - 8);
  assertInside(anchor, size, VIEW, p);
});

test('狭い画面（360 px）: 左右に入らず上下に回り、横の端も 8 px 内に入らない', () => {
  const view = { width: 360, height: 780, top: 60 };
  const anchor = rect(10, 300, 100, 140);
  const size = { width: 344, height: 200 };
  const p = placeTip(anchor, size, view);
  assert.equal(p.side, 'bottom');
  assert.equal(p.left, TIP_EDGE);
  assertInside(anchor, size, view, p);
  const wide = { width: 360, height: 780, top: 60 };
  const right = rect(250, 300, 100, 140);
  const q = placeTip(right, { width: 344, height: 200 }, wide);
  assert.equal(q.left + 344, 360 - TIP_EDGE);
});

test('横向きのスマホ（844 × 390）: 低い画面では右に置き、高さは領域いっぱいまで返す', () => {
  const view = { width: 844, height: 390, top: 66 };
  const anchor = rect(60, 200, 130, 93);
  const size = { width: 640, height: 400 };
  const p = placeTip(anchor, size, view);
  assert.equal(p.fits, false);
  assert.equal(p.side, 'right');
  assert.equal(p.maxHeight, 390 - 8 - 74);
  assertInside(anchor, size, view, p);
});
