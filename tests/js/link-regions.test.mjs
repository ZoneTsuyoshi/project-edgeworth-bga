// 通信確立済みの地域（A〜H）の計算の単体テスト。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { linkedRegions, refreshLinkedRegions, REGION_LETTERS } from '../../src/modules/js/ui/link-regions.js';

const cells = [
  { cell: 0, region: 'A' }, { cell: 1, region: 'A' }, { cell: 9, region: 'B' }, { cell: 20, region: 'H' }, { cell: 30, region: 'c' },
];

test('キューブが無ければ空', () => {
  assert.deepEqual(linkedRegions([], cells), []);
});

test('同じ地域の複数のキューブは 1 つにまとまり、A〜H の順に並ぶ', () => {
  assert.deepEqual(linkedRegions([{ cell: 20 }, { cell: 1 }, { cell: 0 }, { cell: 9 }], cells), ['A', 'B', 'H']);
});

test('区域の番号が文字列でも、地域が小文字でも扱える', () => {
  assert.deepEqual(linkedRegions([{ cell: /** @type {any} */ ('30') }], cells), ['C']);
});

test('盤面に無い区域は無視する', () => {
  assert.deepEqual(linkedRegions([{ cell: 999 }], cells), []);
});

test('refreshLinkedRegions は盤面が無ければ空にする', () => {
  const p = { links: [{ cell: 0 }], linkedRegions: ['A'] };
  refreshLinkedRegions(p, null);
  assert.deepEqual(p.linkedRegions, []);
  refreshLinkedRegions(p, { cells });
  assert.deepEqual(p.linkedRegions, ['A']);
  assert.equal(REGION_LETTERS.length, 8);
});
