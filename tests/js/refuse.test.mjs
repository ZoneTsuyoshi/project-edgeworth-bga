/**
 * 選べないものを押したときの理由の表示（src/modules/js/ui/refuse.js。仕様書 014 §6.3）と、選択状態のクラス（cards/pick-state.js。§6.1）の単体テスト。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { PICK_CLASSES, pickClass } from '../../src/modules/js/cards/pick-state.js';
import { refuse, REFUSE_INTERVAL_MS, shouldShow } from '../../src/modules/js/ui/refuse.js';

test('同じ文は 2 秒以内に繰り返さない', () => {
    const last = new Map();
    assert.equal(shouldShow(last, 'a', 1000), true);
    assert.equal(shouldShow(last, 'a', 1000 + REFUSE_INTERVAL_MS - 1), false);
    assert.equal(shouldShow(last, 'a', 1000 + REFUSE_INTERVAL_MS), true);
});

test('別の文はすぐ出す', () => {
    const last = new Map();
    assert.equal(shouldShow(last, 'a', 0), true);
    assert.equal(shouldShow(last, 'b', 1), true);
    assert.equal(shouldShow(last, 'a', 2), false);
});

test('理由が無ければ一般の文を出す', () => {
    globalThis._ = (s) => s;
    const shown = [];
    const env = { showMessage: (m) => shown.push(m) };
    refuse(env, undefined);
    assert.deepEqual(shown, ['You cannot choose this now']);
    refuse(env, 'This satellite already has 4 cards');
    refuse(env, 'This satellite already has 4 cards');
    assert.deepEqual(shown, ['You cannot choose this now', 'This satellite already has 4 cards']);
});

test('衛星の選択状態は 5 つのクラスに対応する', () => {
    assert.equal(pickClass('selectable'), 'pe-can');
    assert.equal(pickClass('sellable'), 'pe-can-sell');
    assert.equal(pickClass('selected'), 'pe-picked');
    assert.equal(pickClass('blocked'), 'pe-off');
    for (const state of ['selectable', 'sellable', 'selected', 'blocked']) {
        assert.ok(PICK_CLASSES.includes(pickClass(state)));
    }
});
