// 手札を選ぶ欄の切り替え（012 追補 2026-11-14 B-2）の純関数の単体テスト。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { payState } from '../../src/modules/js/states/logic/common.js';
import {
  advanceActive, candidatesFor, claimedByOthers, plannedMarks, pickFromHand, pickFromOutside, pruneSelection, removeCard, resolveActive, targetComplete,
} from '../../src/modules/js/states/logic/hand-targets.js';

const hand = [1, 2, 3, 4, 5];
const deploy = (selected = []) => ({ id: 'deploy', candidates: hand, selected, need: 1, max: 1 });
const sell = (selected = [], need = 2) => ({ id: 'sell', candidates: hand, selected, need });
const discard = (selected = []) => ({ id: 'discard', candidates: hand, selected });

test('初期の選択中の欄は最初の未完了の欄。保存された欄が使えるならそれを優先する', () => {
  assert.equal(resolveActive([deploy(), sell()], undefined), 'deploy');
  assert.equal(resolveActive([deploy([1]), sell()], undefined), 'sell');
  assert.equal(resolveActive([deploy([1]), sell()], 'deploy'), 'deploy');
  // 保存された欄が使えない（無効・存在しない）ときは決め直す
  assert.equal(resolveActive([deploy([1]), { ...sell(), disabled: true }], 'sell'), 'deploy');
  assert.equal(resolveActive([deploy([1]), sell()], 'gone'), 'sell');
});

test('未完了の欄がなければ最初の使える欄。idleWhenComplete で欄が複数なら手札を使わない', () => {
  assert.equal(resolveActive([deploy([1]), sell([2, 3])], undefined), 'deploy');
  assert.equal(resolveActive([discard(), sell([2, 3])], undefined, { idleWhenComplete: true }), null);
  assert.equal(resolveActive([discard()], undefined, { idleWhenComplete: true }), 'discard');
  assert.equal(resolveActive([{ ...discard(), disabled: true }], undefined), null);
  assert.equal(resolveActive([{ ...discard(), disabled: true }, discard()], undefined, { idleWhenComplete: true }), 'discard');
});

test('欄が完了したら次の未完了の欄へ移る。移れる欄がなければそのまま', () => {
  assert.equal(advanceActive([deploy([1]), sell()], 'deploy'), 'sell');
  assert.equal(advanceActive([deploy(), sell()], 'deploy'), 'deploy');
  // 売却が足りたら、未完了の配備の欄（先頭）へ戻る
  assert.equal(advanceActive([deploy(), sell([2, 3])], 'sell'), 'deploy');
  assert.equal(advanceActive([deploy([1]), sell([2, 3])], 'sell'), 'sell');
  assert.equal(advanceActive([deploy([1]), { ...sell(), disabled: true }], 'deploy'), 'deploy');
  assert.equal(advanceActive([deploy([1])], null), null);
});

test('他の欄で選んだカードは、この欄の候補から外れる', () => {
  const targets = [deploy([1]), sell([2]), discard([3])];
  assert.deepEqual([...claimedByOthers(targets, 'sell')].sort(), [1, 3]);
  assert.deepEqual(candidatesFor(targets, 'sell'), [2, 4, 5]);
  assert.deepEqual(candidatesFor(targets, 'deploy'), [1, 4, 5]);
  assert.deepEqual(candidatesFor([{ ...discard(), disabled: true }], 'discard'), []);
  assert.deepEqual(candidatesFor(targets, 'none'), []);
});

test('選択中でない欄が選んだカードにだけ印を付ける', () => {
  const marks = plannedMarks([deploy([1]), sell([2, 3])], 'sell', { deploy: 'Deploy', sell: 'Sell' });
  assert.deepEqual([...marks], [[1, 'Deploy']]);
  assert.deepEqual([...plannedMarks([deploy([1]), sell([2])], null, { deploy: 'Deploy', sell: 'Sell' })].sort(), [[1, 'Deploy'], [2, 'Sell']]);
});

test('単一の欄（max = 1）は後から押した方に入れ替わる。複数の欄は上限を超える追加を無視する', () => {
  assert.deepEqual(pickFromHand(deploy([1]), [2], hand), [2]);
  assert.deepEqual(pickFromHand(deploy([1]), [], hand), []);
  assert.deepEqual(pickFromHand(deploy(), [3], hand), [3]);
  const two = { id: 'x', candidates: hand, selected: [1, 2], max: 2 };
  assert.deepEqual(pickFromHand(two, [1, 2, 3], hand), [1, 2]);
  assert.deepEqual(pickFromHand(two, [1], hand), [1]);
  assert.deepEqual(pickFromHand(sell([1, 2]), [1, 2, 3], hand), [1, 2, 3]);
});

test('手札にないカード（残すカード）を選んでいても、手札の選択で入れ替わる（P070 の欄）', () => {
  const extra = { id: 'extra', candidates: hand, selected: [90], max: 1 };
  assert.deepEqual(pickFromHand(extra, [2], hand), [2]);
  assert.deepEqual(pickFromOutside(extra, [91], hand), [91]);
  assert.deepEqual(pickFromOutside({ ...extra, selected: [2] }, [90], hand), [90]);
  assert.deepEqual(pickFromOutside({ ...extra, selected: [2] }, [], hand), []);
  const many = { id: 'm', candidates: hand, selected: [2, 90], max: 2 };
  assert.deepEqual(pickFromHand(many, [2, 3], hand), [2, 90]);
  assert.deepEqual(pickFromHand(many, [], hand), [90]);
});

test('× で外す。選択を候補と上限に整える', () => {
  assert.deepEqual(removeCard([1, 2, 3], 2), [1, 3]);
  assert.deepEqual(removeCard([1], 9), [1]);
  assert.deepEqual(pruneSelection([1, 9, 3], [1, 3, 4]), [1, 3]);
  assert.deepEqual(pruneSelection([1, 3, 4], [1, 3, 4], 2), [1, 3]);
});

test('売却の欄は、不足額に必要な枚数に達すると完了する', () => {
  // 予算 5 マヤ、コスト 12、売却額 3 → 3 枚必要
  const pay = payState(12, { maya: 5, price: 3, sellable: 4 });
  assert.equal(pay.kind, 'sell');
  assert.equal(pay.need, 3);
  const target = sell([1, 2], pay.need);
  assert.equal(targetComplete(target), false);
  assert.equal(targetComplete({ ...target, selected: [1, 2, 3] }), true);
  // 必要枚数に届いても、売るカードを増やすのは自由（上限なし）
  assert.deepEqual(pickFromHand(target, [1, 2, 3, 4], hand), [1, 2, 3, 4]);
  // 配備するカードが決まり、売却が未完了なら売却の欄が選択中になる
  assert.equal(resolveActive([deploy([5]), target], undefined), 'sell');
  // 売却の候補は配備するカードを含まない
  assert.deepEqual(candidatesFor([deploy([5]), target], 'sell'), [1, 2, 3, 4]);
  // 売ってもコストに届かないとき（short）は売却の欄そのものを出さない
  assert.equal(payState(100, { maya: 5, price: 3, sellable: 2 }).kind, 'short');
});
