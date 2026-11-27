// ui/round-track.js（ラウンドトラックの状態の計算。仕様書 014 §3）の単体テスト。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { PHASE_ACTION, PHASE_STEP, roundTrackState } from '../../src/modules/js/ui/round-track.js';

const ORDER = [10, 20, 30];

/** @param {ReturnType<typeof roundTrackState>} state */
const states = (state) => state.steps.map((s) => s.state);

test('セットアップ中・ゲーム終了は段を持たない', () => {
  assert.deepEqual(roundTrackState({ round: 0, phase: 'SETUP', bonusHolders: {}, playerOrder: ORDER }), { mode: 'setup', round: 0, steps: [] });
  assert.equal(roundTrackState({ round: 0, phase: 'PLANNING', bonusHolders: {}, playerOrder: ORDER }).mode, 'setup');
  assert.deepEqual(roundTrackState({ round: 7, phase: 'END', bonusHolders: {}, playerOrder: ORDER }), { mode: 'end', round: 7, steps: [] });
});

test('flow_phase と段の対応', () => {
  assert.deepEqual(PHASE_ACTION, { BUDGET: 1, DEPLOY: 2, LAUNCH_TURN: 3, LINK: 4, PROPOSAL: 5 });
  assert.equal(PHASE_STEP.PLANNING, 0);
  assert.equal(PHASE_STEP.ORBIT, 6);
  const s = roundTrackState({ round: 1, phase: 'PLANNING', bonusHolders: {}, playerOrder: ORDER });
  assert.equal(s.steps.length, 7);
  assert.deepEqual(s.steps.map((x) => x.no), [0, 1, 2, 3, 4, 5, 6]);
});

test('計画フェイズ: 計画が現在、I〜V と循環はこれから（誰が何を選んだかは未公開）', () => {
  // 公開前は bonusHolders が空。計画の間は、前のラウンドの値が残っていても公開前として扱う
  const s = roundTrackState({ round: 3, phase: 'PLANNING', bonusHolders: { 2: [10] }, playerOrder: ORDER });
  assert.deepEqual(states(s), ['current', 'todo', 'todo', 'todo', 'todo', 'todo', 'todo']);
  assert.ok(s.steps.every((x) => x.holders.length === 0));
});

test('公開後: 選ばれた段はこれから / 現在 / 済み、選ばれなかった段は行わない', () => {
  const holders = { 2: [20], 4: [10, 30] };
  const atDeploy = roundTrackState({ round: 3, phase: 'DEPLOY', bonusHolders: holders, playerOrder: ORDER });
  assert.deepEqual(states(atDeploy), ['done', 'skipped', 'current', 'skipped', 'todo', 'skipped', 'todo']);
  const atLink = roundTrackState({ round: 3, phase: 'LINK', bonusHolders: holders, playerOrder: ORDER });
  assert.deepEqual(states(atLink), ['done', 'skipped', 'done', 'skipped', 'current', 'skipped', 'todo']);
  const atOrbit = roundTrackState({ round: 3, phase: 'ORBIT', bonusHolders: holders, playerOrder: ORDER });
  assert.deepEqual(states(atOrbit), ['done', 'skipped', 'done', 'skipped', 'done', 'skipped', 'current']);
});

test('保持者は席順に並べ、I〜V だけに付く。キーのある段は保持者が空でも選ばれた扱い', () => {
  const s = roundTrackState({ round: 1, phase: 'BUDGET', bonusHolders: { 1: [30, 10], 3: [] }, playerOrder: ORDER });
  assert.deepEqual(s.steps[1].holders, [10, 30]);
  assert.deepEqual(s.steps[0].holders, []);
  assert.deepEqual(s.steps[6].holders, []);
  assert.equal(s.steps[3].state, 'todo');
  assert.equal(s.steps[2].state, 'skipped');
  // 席順に無い ID は最後
  const t = roundTrackState({ round: 1, phase: 'BUDGET', bonusHolders: { 1: [99, 20] }, playerOrder: ORDER });
  assert.deepEqual(t.steps[1].holders, [20, 99]);
});

test('入力を書き換えない', () => {
  const holders = { 1: [30, 10] };
  roundTrackState({ round: 1, phase: 'BUDGET', bonusHolders: holders, playerOrder: ORDER });
  assert.deepEqual(holders, { 1: [30, 10] });
});
