// 効果の窓の配備の組（B-3）の純関数の単体テスト。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
  addPair, applyHandChange, cardOptions, isEstimated, normalizePairs, pairBlock, pairPayState, pickCard, pickSatellite, satOptions, selectedCards, soleSatellite, totalPairCost,
} from '../../src/modules/js/states/logic/effect-pairs.js';
import { mayaToPay } from '../../src/modules/js/states/logic/effects.js';

const none = { card: null, sat: null };
const st = (chosen = [], pending = none) => ({ chosen, pending });
// 手札 3・18・44。ANY_OWN（衛星 11・12・13）
const any = [[3, 11], [3, 12], [18, 11], [18, 12], [44, 11], [44, 13]];
// THIS（衛星 11 のみ）
const this11 = [[3, 11], [18, 11]];
const preview = { costs: [{ card: 3, sat: 11, cost: 4 }, { card: 18, sat: 11, cost: 6 }, { card: 3, sat: 12, cost: 9 }] };

test('配備先が 1 機だけなら、カードを押した時点で組になる', () => {
  assert.equal(soleSatellite(this11), 11);
  assert.equal(soleSatellite(any), null);
  assert.deepEqual(pickCard(this11, st(), { max: 2, deployed: {}, card: 3 }), st([[3, 11]]));
});

test('カード → 衛星の順で組ができ、衛星 → カードの順でも組ができる', () => {
  const afterCard = pickCard(any, st(), { max: 1, deployed: {}, card: 3 });
  assert.deepEqual(afterCard, st([], { card: 3, sat: null }));
  assert.deepEqual(pickSatellite(any, afterCard, { max: 1, deployed: {}, sat: 12 }), st([[3, 12]]));
  const afterSat = pickSatellite(any, st(), { max: 1, deployed: {}, sat: 13 });
  assert.deepEqual(afterSat, st([], { card: null, sat: 13 }));
  assert.deepEqual(pickCard(any, afterSat, { max: 1, deployed: {}, card: 44 }), st([[44, 13]]));
  // 候補にない組は何も変えない
  assert.deepEqual(pickCard(any, afterSat, { max: 1, deployed: {}, card: 3 }), afterSat);
  // 保留の衛星をもう一度押すと外れる
  assert.deepEqual(pickSatellite(any, afterSat, { max: 1, deployed: {}, sat: 13 }), st());
});

test('最大 2 枚: 2 組目も同じ手順。上限に達したら足せない。max = 1 は入れ替え', () => {
  const one = st([[3, 11]]);
  assert.equal(pairBlock(any, one.chosen, 2, {}, 18, 12), null);
  assert.equal(pairBlock(any, one.chosen, 2, {}, 3, 12), 'used');
  const two = st([[3, 11], [18, 12]]);
  assert.equal(pairBlock(any, two.chosen, 2, {}, 44, 11), 'max');
  assert.deepEqual(pickCard(any, two, { max: 2, deployed: {}, card: 44 }), two);
  assert.deepEqual(addPair([[3, 11]], [18, 12], 1), [[18, 12]]);
  assert.equal(pairBlock(any, [[3, 11]], 1, {}, 18, 12), null);
});

test('同じ衛星への 2 組目は、配備済みと合わせて 4 枚に収まるときだけ', () => {
  assert.equal(pairBlock(any, [[3, 11]], 2, { 11: 3 }, 18, 11), 'full');
  assert.equal(pairBlock(any, [[3, 11]], 2, { 11: 2 }, 18, 11), null);
  assert.equal(pairBlock(any, [], 2, { 11: 4 }, 3, 11), 'full');
  const options = satOptions(any, [[3, 11]], 2, { 11: 3, 12: 0, 13: 0 }, [11, 12, 13, 14], 18);
  assert.equal(options.get(11), 'full');
  assert.equal(options.get(12), null);
  assert.equal(options.get(13), 'notTarget');
  assert.equal(options.get(14), 'notTarget');
});

test('押せるカードと理由: 衛星が決まっていればその衛星への組だけ', () => {
  const free = cardOptions(any, [], 2, {}, null);
  assert.deepEqual([...free].filter(([, b]) => b === null).map(([c]) => c), [3, 18, 44]);
  const onSat13 = cardOptions(any, [], 2, {}, 13);
  assert.equal(onSat13.get(44), null);
  assert.equal(onSat13.get(3), 'notTarget');
  const full = cardOptions(this11, [], 2, { 11: 4 }, null);
  assert.equal(full.get(3), 'full');
  const maxed = cardOptions(any, [[3, 11], [18, 12]], 2, {}, null);
  assert.equal(maxed.get(44), 'max');
});

test('手札のゾーンの選択の変化: 押すと保留・組、押し直すと取り消し', () => {
  const ctx = { max: 2, deployed: {} };
  const pending = applyHandChange(any, st(), [3], ctx);
  assert.deepEqual(pending, st([], { card: 3, sat: null }));
  assert.deepEqual(selectedCards(pending), [3]);
  assert.deepEqual(applyHandChange(any, pending, [], ctx), st());
  const paired = st([[3, 11]]);
  assert.deepEqual(applyHandChange(any, paired, [], ctx), st());
  // 別のカードを押すと保留が入れ替わる
  assert.deepEqual(applyHandChange(any, pending, [18], ctx), st([], { card: 18, sat: null }));
  // 組があるまま別のカードを押す
  assert.deepEqual(applyHandChange(any, paired, [3, 18], ctx), st([[3, 11]], { card: 18, sat: null }));
  // max = 1 は入れ替え
  assert.deepEqual(applyHandChange(this11, st([[3, 11]]), [18], { max: 1, deployed: {} }), st([[18, 11]]));
});

test('コストは preview の加算。2 組目は見積もり。足りない分は売却の促しに回る', () => {
  assert.equal(totalPairCost(preview, [[3, 11], [18, 11]]), 10);
  assert.equal(isEstimated(0), false);
  assert.equal(isEstimated(1), true);
  const funds = { maya: 8, price: 3, handCount: 5, otherReserved: 0 };
  assert.deepEqual(pairPayState(preview, [], [3, 11], funds).pay.kind, 'free');
  const second = pairPayState(preview, [[3, 11]], [18, 11], funds);
  assert.equal(second.cost, 6);
  assert.equal(second.pay.kind, 'sell');
  assert.equal(second.pay.need, 1);
  // 売れる手札が足りないと払えない（配備するカード 2 枚と捨てる 1 枚は売れない）
  assert.equal(pairPayState(preview, [[3, 11]], [18, 11], { ...funds, handCount: 3, otherReserved: 1 }).pay.kind, 'short');
});

test('mayaToPay は効果の代価に配備の組のコストを足す', () => {
  const q = { cost: { kind: 'NONE' }, fields: [{ key: 'pairs', type: 'PAIRS' }], preview };
  assert.equal(mayaToPay(q, { pairs: [[3, 11], [18, 11]] }), 10);
  assert.equal(mayaToPay({ ...q, cost: { kind: 'PAY_MAYA', amount: 2 } }, { pairs: [[3, 11]] }), 6);
  assert.equal(mayaToPay({ ...q, preview: { links: [] } }, { pairs: [[3, 11]] }), 0);
  assert.deepEqual(normalizePairs([['3', '11']]), [[3, 11]]);
});
