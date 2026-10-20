// 操作 UI の純粋関数（args の解釈、選択の検証、送信する引数の組み立て）の単体テスト。スプライトの生成物（sprites.js）に依存しない。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { keyOfNameId, nameOfNameId, parseHostKey } from '../../src/modules/js/cards/name-id.js';
import {
  compact, costWith, intList, jsonParam, minSales, parseMods, payState, privateOf, sellableHand, sellsWholeHand, sideAvailability, soleOption,
} from '../../src/modules/js/states/logic/common.js';
import { deployActionArgs, deployPairs, deployQuote, deploySelectionFrom, parseTargets, validateDeploy } from '../../src/modules/js/states/logic/deploy.js';
import { launchActionArgs, launchQuote, launchSelectionFrom, launchState, parseLaunchable, parseTurnable, validateLaunch } from '../../src/modules/js/states/logic/launch.js';
import { cellKind, linkActionArgs, linkBreakdown, linkPairs, linkQuote, parseLinkable, validateLink } from '../../src/modules/js/states/logic/link.js';
import { mulliganActionArgs, mulliganButton, mulliganPicks } from '../../src/modules/js/states/logic/mulligan.js';
import {
  optionPayment, parseProposal, proposalActionArgs, proposalSelectionFrom, requiredKeep, reservedHand, validateProposal,
} from '../../src/modules/js/states/logic/proposal.js';
import {
  answerFields, fieldDefault, initialOrder, initialValues, mayaToPay, moveEntry, pairCost, parseWaiting, parseWindow, reservedCards, salesNeeded, useEffectArgs, validateField, validateQuestion,
} from '../../src/modules/js/states/logic/effects.js';
import { playerScore, rankScores, scoreFromNotification, scoresFromGamedatas } from '../../src/modules/js/states/logic/score.js';

const funds = (maya, price = 3, sellable = 5) => ({ maya, price, sellable });

test('_private は BGA が平らにした形でも、プレイヤー ID がキーの形でも読める', () => {
  assert.deepEqual(privateOf({ _private: { hand: [1] } }, 7), { hand: [1] });
  assert.deepEqual(privateOf({ _private: { 7: { hand: [1] }, 8: { hand: [2] } } }, 7), { hand: [1] });
  assert.equal(privateOf({ _private: { 8: { hand: [2] } } }, 7), null);
  assert.equal(privateOf({}, 7), null);
  assert.deepEqual(intList(['1', 2, 'x']), [1, 2]);
});

test('売却の最小枚数と支払い状況（008 §2-6）', () => {
  assert.equal(minSales(10, funds(12)), 0);
  assert.equal(minSales(10, funds(4)), 2);
  assert.equal(minSales(10, funds(4, 3, 1)), null);
  assert.equal(minSales(10, funds(4, 0)), null);
  assert.deepEqual(payState(8, funds(4)), { kind: 'sell', need: 2, short: 4, left: -4 });
  assert.equal(payState(3, funds(4)).kind, 'free');
  assert.equal(payState(40, funds(4)).kind, 'short');
  assert.equal(sellsWholeHand([1, 2, 3], [1, 2, 3]), true);
  assert.equal(sellsWholeHand([1, 2], [1, 2, 3]), false);
  assert.equal(sellsWholeHand([], []), false);
  assert.deepEqual(sellableHand([1, 2, 3], [2]), [1, 3]);
});

test('起動の修正のコスト（サーバーが渡した値を使い、一部だけの組み合わせは同じ式で見積もる）', () => {
  const mods = parseMods({ options: [{ hostKey: 'P:19', cardKey: 'a', reduction: 2, cost: 8 }, { hostKey: 'P:20', cardKey: 'b', reduction: 2, cost: 8 }, { hostKey: 'P:21', cardKey: 'c', reduction: 3, cost: 7 }], all: 3 });
  assert.equal(costWith(10, mods, []), 10);
  assert.equal(costWith(10, mods, ['P:21']), 7);
  assert.equal(costWith(10, mods, ['P:19', 'P:20']), 6);
  assert.equal(costWith(10, mods, ['P:19', 'P:20', 'P:21']), 3);
  assert.equal(costWith(10, mods, ['X:1']), 10);
  assert.equal(costWith(10, null, ['P:19']), 10);
  assert.equal(parseMods({ options: [], all: 0 }), null);
  assert.equal(parseMods(undefined), null);
});

test('送る引数: 空配列は省き、JsonParam は JSON 文字列にする', () => {
  assert.deepEqual(compact({ a: [], b: [1], c: undefined, d: null, e: 0 }), { b: [1], e: 0 });
  assert.equal(jsonParam([]), undefined);
  assert.equal(jsonParam(['P:1']), '["P:1"]');
  assert.equal(jsonParam({ a: 1 }), '{"a":1}');
});

test('組の選択はカードからでも衛星からでも同じ結果になる（008 §3-6）', () => {
  const pairs = [
    { a: 3, b: 11, usable: true, data: null },
    { a: 3, b: 12, usable: false, data: null },
    { a: 18, b: 11, usable: true, data: null },
    { a: 18, b: 13, usable: true, data: null },
  ];
  const sats = sideAvailability(pairs, 'b', 3);
  assert.equal(sats.get(11)?.usable, true);
  assert.equal(sats.get(12)?.usable, false);
  assert.equal(sats.get(13)?.usable, false);
  const cards = sideAvailability(pairs, 'a', 11);
  assert.equal(cards.get(3)?.usable, true);
  assert.equal(cards.get(18)?.usable, true);
  const free = sideAvailability(pairs, 'a', null);
  assert.equal(free.get(3)?.usable, true);
  assert.equal(soleOption(pairs, 3), 11);
  assert.equal(soleOption(pairs, 18), null);
  assert.equal(soleOption(pairs, null), null);
});

const targetsRaw = {
  3: [
    { satId: 11, tier: 4, cost: 10, blocked: null, minSales: 0, selectable: true },
    { satId: 12, tier: 5, cost: 5, blocked: 'full', minSales: 0, selectable: false },
    { satId: 13, tier: 1, cost: 40, blocked: null, minSales: null, selectable: false },
  ],
  18: [{ satId: 11, tier: 4, cost: 9, blocked: null, minSales: 0, selectable: true, useMods: { options: [{ hostKey: 'P:19', cardKey: 'x', reduction: 2, cost: 7 }], all: 7 } }],
};

test('配備: args の解釈、選べる組、送信する引数', () => {
  const targets = parseTargets(targetsRaw);
  assert.equal(targets.length, 4);
  const pairs = deployPairs(targets, [3, 18, 44], 12, 3);
  assert.equal(pairs.find((p) => p.a === 3 && p.b === 11)?.usable, true);
  assert.equal(pairs.find((p) => p.a === 3 && p.b === 12)?.usable, false, '満杯');
  assert.equal(pairs.find((p) => p.a === 3 && p.b === 13)?.usable, false, '売っても届かない');
  // 手札に無いカードの組は出ない（売却済み）
  assert.equal(deployPairs(targets, [3], 12, 3).some((p) => p.a === 18), false);
  // 手札 2 枚: 売れるのは 1 枚（配備するカード自身は売れない）。コスト 40 には届かない
  assert.equal(deployPairs(targets, [3, 18], 30, 3).find((p) => p.a === 3 && p.b === 13)?.usable, false);
  const t = targets.find((x) => x.cardId === 18);
  assert.ok(t);
  const quote = deployQuote(t, ['P:19'], funds(5));
  assert.equal(quote.cost, 7);
  assert.equal(quote.pay.kind, 'sell');
  assert.equal(quote.pay.need, 1);
  assert.deepEqual(validateDeploy({ op: 'deploy', cardId: 18, satId: 11, sell: [] }, { pay: quote.pay, canDraw: false }), { ok: false, reason: 'needMoreSales' });
  assert.deepEqual(validateDeploy({ op: 'deploy', cardId: 18, satId: 11, sell: [44] }, { pay: quote.pay, canDraw: false }), { ok: true });
  assert.equal(validateDeploy({ op: 'deploy', cardId: 18, satId: 11, sell: [18] }, { pay: quote.pay, canDraw: false }).reason, 'sellsDeployedCard');
  assert.equal(validateDeploy({ op: 'deploy', cardId: null, satId: null, sell: [] }, { pay: null, canDraw: false }).reason, 'incomplete');
  assert.equal(validateDeploy({ op: 'draw', cardId: null, satId: null, sell: [] }, { pay: null, canDraw: false }).ok, false);
  assert.equal(validateDeploy({ op: 'draw', cardId: null, satId: null, sell: [] }, { pay: null, canDraw: true }).ok, true);
  assert.equal(validateDeploy({ op: 'pass', cardId: null, satId: null, sell: [] }, { pay: null, canDraw: false }).ok, true);
  assert.deepEqual(deployActionArgs({ op: 'pass' }), { op: 'pass' });
  assert.deepEqual(deployActionArgs({ op: 'deploy', cardId: 18, satId: 11, sell: [44], useMods: ['P:19'] }), { op: 'deploy', cardId: 18, satId: 11, sell: [44], useMods: '["P:19"]' });
  assert.deepEqual(deployActionArgs({ op: 'deploy', cardId: 3, satId: 11, sell: [], useMods: [] }), { op: 'deploy', cardId: 3, satId: 11 });
  assert.deepEqual(deploySelectionFrom({ op: 'deploy', card: 18, sat: 11, sell: [44], useMods: ['P:19'] }), { op: 'deploy', cardId: 18, satId: 11, sell: [44], useMods: ['P:19'] });
  assert.equal(deploySelectionFrom({ op: 'pass' }).op, null);
  assert.equal(deploySelectionFrom(null).op, null);
});

test('打ち上げ / 転回: 候補、向き、送信する引数', () => {
  const launchable = parseLaunchable([
    { satId: 13, cost: 10, blocked: null, minSales: 0, selectable: true },
    { satId: 14, cost: 15, blocked: 'no_project', minSales: 0, selectable: false },
    { satId: 15, cost: 15, blocked: null, minSales: 2, selectable: true, useMods: { options: [{ hostKey: 'P:36', cardKey: 'q', reduction: 1, cost: 14 }], all: 14 } },
  ]);
  assert.equal(launchState(launchable[0], funds(12)).usable, true);
  assert.equal(launchState(launchable[1], funds(100)).usable, false);
  assert.equal(launchState(launchable[2], funds(8)).pay.kind, 'sell');
  assert.equal(launchQuote(launchable[2], ['P:36'], funds(8)).cost, 14);
  const turnable = parseTurnable([{ satId: 11, dir: 'NE', blocked: null, selectable: true }, { satId: 12, dir: null, blocked: 'no_inactive_satellite', selectable: false }]);
  assert.equal(turnable[0].dir, 'NE');
  const pay = payState(10, funds(12));
  assert.equal(validateLaunch({ op: 'launch', satId: 13, dir: null, sell: [] }, { pay, canBudget: false }).reason, 'needDirection');
  assert.equal(validateLaunch({ op: 'launch', satId: 13, dir: 'SE', sell: [] }, { pay, canBudget: false }).ok, true);
  for (const dir of ['NE', 'E', 'SE', 'SW', 'W', 'NW']) {
    assert.equal(validateLaunch({ op: 'launch', satId: 13, dir, sell: [] }, { pay, canBudget: false }).ok, true, dir);
  }
  assert.equal(validateLaunch({ op: 'launch', satId: 13, dir: 'N', sell: [] }, { pay, canBudget: false }).ok, false);
  assert.equal(validateLaunch({ op: 'turn', satId: 11, dir: 'NE', sell: [] }, { pay: null, canBudget: false }).ok, true, '同じ向きも選べる（FAQ 行 17）');
  assert.equal(validateLaunch({ op: 'budget', satId: null, dir: null, sell: [] }, { pay: null, canBudget: false }).ok, false);
  assert.equal(validateLaunch({ op: 'budget', satId: null, dir: null, sell: [] }, { pay: null, canBudget: true }).ok, true);
  assert.equal(validateLaunch({ op: 'launch', satId: 13, dir: 'NE', sell: [] }, { pay: payState(20, funds(4, 3, 1)), canBudget: false }).reason, 'cannotPay');
  assert.deepEqual(launchActionArgs({ op: 'launch', satId: 13, dir: 'SE', sell: [], useMods: ['P:36'] }), { op: 'launch', satId: 13, dir: 'SE', useMods: '["P:36"]' });
  assert.deepEqual(launchActionArgs({ op: 'turn', satId: 11, dir: 'NE', sell: [9] }), { op: 'turn', satId: 11, dir: 'NE' });
  assert.deepEqual(launchActionArgs({ op: 'budget' }), { op: 'budget' });
  assert.deepEqual(launchSelectionFrom({ op: 'turn', sat: 11, dir: 'SW' }), { op: 'turn', satId: 11, dir: 'SW', sell: [], useMods: [] });
  assert.equal(launchSelectionFrom({ op: 'pass' }).op, null);
});

test('通信確立: 区域のハイライトの種類、コストの内訳、送信する引数', () => {
  const options = parseLinkable([
    { cell: 20, selectable: true, sats: [{ satId: 11, cost: { area: 5, pioneer: 3, total: 8 }, minSales: 0 }, { satId: 14, cost: { area: 5, pioneer: 3, total: 8 }, minSales: 0 }] },
    { cell: 21, selectable: true, sats: [{ satId: 11, cost: { area: 9, pioneer: 3, total: 12 }, minSales: 3 }] },
    { cell: 22, selectable: false, sats: [{ satId: 11, cost: { area: 30, pioneer: 0, total: 30 }, minSales: null }] },
  ]);
  const pairs = linkPairs(options, funds(10));
  const byCell = (/** @type {number} */ cell) => pairs.filter((p) => p.a === cell);
  assert.equal(cellKind(byCell(20)), 'selectable');
  assert.equal(cellKind(byCell(21)), 'sellable');
  assert.equal(cellKind(byCell(22)), null);
  assert.equal(soleOption(pairs, 21), 11);
  assert.equal(soleOption(pairs, 20), null);
  const quote = linkQuote(options[0], [], funds(10));
  assert.deepEqual(linkBreakdown(options[0], quote.cost, 10), { area: 5, pioneer: 3, modsReduction: 0, total: 8, left: 2, free: false });
  assert.equal(linkBreakdown({ ...options[0], total: 0 }, 0, 10).free, true);
  assert.equal(validateLink({ op: 'link', cell: 21, satId: 11, sell: [] }, { pay: payState(12, funds(10)) }).reason, 'needMoreSales');
  assert.equal(validateLink({ op: 'link', cell: 21, satId: 11, sell: [4] }, { pay: payState(12, funds(10)) }).ok, true);
  assert.equal(validateLink({ op: 'link', cell: null, satId: null, sell: [] }, { pay: null }).reason, 'incomplete');
  assert.equal(validateLink({ op: 'pass', cell: null, satId: null, sell: [] }, { pay: null }).ok, true);
  assert.deepEqual(linkActionArgs({ op: 'link', cell: 21, satId: 11, sell: [4] }), { op: 'link', cell: 21, satId: 11, sell: [4] });
  assert.deepEqual(linkActionArgs({ op: 'pass', cell: 5 }), { op: 'pass' });
});

test('マリガンの選択とボタン', () => {
  assert.deepEqual(mulliganButton(0), { kind: 'keep', count: 0 });
  assert.deepEqual(mulliganButton(2), { kind: 'mulligan', count: 2 });
  assert.deepEqual(mulliganPicks([1, 2, 9], [1, 2, 3]), [1, 2]);
  assert.deepEqual(mulliganActionArgs([]), {}, '空配列は送信で落ちるので省く');
  assert.deepEqual(mulliganActionArgs([3, 4]), { discard: [3, 4] });
});

const proposalPrivate = {
  drawn: [5, 6, 7, 8, 9],
  keep: 2,
  hand: [1, 2, 3],
  sellValue: 3,
  extra: {
    handDiscard: true,
    handCandidates: [1, 2, 3],
    options: [
      { hostKey: 'P:70', cardKey: 'x', kind: 'EXTRA_DISCARD', reward: 7, tiers: [] },
      { hostKey: 'P:72', cardKey: 'lanchester', kind: 'KEEP_PURCHASE', reward: 0, tiers: { 4: 1, 7: 2, 10: 3 }, affordable: [4], affordableBySelling: [4, 7], maxKeep: 5 },
    ],
  },
};

test('企画: 残す枚数、オプション、売却の同梱', () => {
  const plan = parseProposal(proposalPrivate);
  assert.equal(plan.options[1].tiers.length, 3);
  assert.deepEqual(plan.options[1].tiers[1], { pay: 7, plus: 2 });
  const base = { keep: [5, 6], handDiscard: [], uses: {}, sell: [] };
  assert.equal(requiredKeep(plan, base), 2);
  assert.equal(requiredKeep(plan, { ...base, handDiscard: [1] }), 3, 'P068: 手札から捨てた分だけ多く残せる');
  assert.equal(requiredKeep(plan, { ...base, uses: { 'P:72': 7 } }), 4, 'P072: 7 マヤで +2');
  assert.equal(requiredKeep({ ...plan, drawn: [5, 6] }, { ...base, uses: { 'P:72': 10 } }), 2, '引いた枚数を超えない');
  const ctx = { hand: [1, 2, 3], maya: 5, price: 3 };
  assert.equal(validateProposal(plan, base, ctx).ok, true);
  assert.equal(validateProposal(plan, { ...base, keep: [5] }, ctx).reason, 'keepCount');
  assert.equal(validateProposal(plan, { ...base, keep: [5, 99] }, ctx).reason, 'invalidKeep');
  assert.equal(validateProposal(plan, { ...base, keep: [5, 6, 7, 8], uses: { 'P:72': 7 } }, ctx).reason, 'needMoreSales', 'マヤ 5 では 7 を払えない。売却 1 枚が要る');
  assert.equal(validateProposal(plan, { ...base, keep: [5, 6, 7, 8], uses: { 'P:72': 7 }, sell: [1] }, ctx).ok, true);
  assert.equal(validateProposal(plan, { ...base, keep: [5, 6, 7, 8], uses: { 'P:72': 7 }, sell: [1], handDiscard: [1] }, ctx).reason, 'keepCount');
  assert.equal(validateProposal(plan, { ...base, keep: [5, 6, 7], handDiscard: [1], sell: [1] }, ctx).reason, 'cardUsedTwice');
  assert.equal(validateProposal(plan, { ...base, uses: { 'P:70': 99 } }, ctx).reason, 'invalidExtraDiscard');
  assert.equal(validateProposal(plan, { ...base, uses: { 'P:70': 2 } }, ctx).ok, true);
  assert.equal(validateProposal({ ...plan, handDiscard: false }, { ...base, keep: [5, 6, 7], handDiscard: [1] }, ctx).reason, 'handDiscardNotAllowed');
  assert.equal(optionPayment(plan, { ...base, uses: { 'P:72': 4, 'P:70': 2 } }), 4);
  assert.deepEqual(reservedHand(plan, { ...base, handDiscard: [1], uses: { 'P:70': 2 } }), [1, 2]);
  assert.deepEqual(proposalActionArgs({ keep: [5, 6], handDiscard: [], uses: {}, sell: [] }), { keep: [5, 6] });
  assert.deepEqual(proposalActionArgs({ keep: [5, 6, 7], handDiscard: [1], uses: { 'P:72': 7 }, sell: [2] }), { keep: [5, 6, 7], handDiscard: [1], optionUses: '{"P:72":7}', sell: [2] });
  assert.deepEqual(proposalSelectionFrom({ keep: [5], optionUses: { 'P:72': '7' } }), { keep: [5], handDiscard: [], uses: { 'P:72': 7 }, sell: [] });
  assert.deepEqual(parseProposal({ drawn: [1], keep: 1, extra: [] }).options, []);
});

test('効果の問い: ORDER の並べ替え、EFFECT のフィールド 8 種の検証と答え', () => {
  const win = parseWindow({
    pending: [{ entryId: 1, cardKey: 'a' }],
    hand: [4, 5],
    sellValue: 3,
    questions: [
      { type: 'ORDER', entries: [{ entryId: 7, cardKey: 'a', forced: true }, { entryId: 8, cardKey: 'b', forced: false }, { entryId: 9, cardKey: 'c', forced: false }], defaultOrder: [8, 7] },
      {
        type: 'EFFECT', entryId: 7, cardKey: 'a', mandatory: false, cost: { kind: 'PAY_MAYA', amount: 6, canPay: false, shortfall: 2, canPayBySelling: true },
        fields: [
          { key: 'satellite', type: 'SATELLITE', min: 0, max: 0, candidates: [11, 12], default: null },
          { key: 'area', type: 'AREA', min: 1, max: 1, candidates: [20], default: null },
          { key: 'direction', type: 'DIRECTION', min: 0, max: 0, candidates: ['NE', 'SE'], default: null },
          { key: 'tier', type: 'AMOUNT', min: 0, max: 0, candidates: [4, 7], default: null },
          { key: 'cards', type: 'CARDS', min: 1, max: 2, candidates: [4, 5], default: null },
          { key: 'pairs', type: 'PAIRS', min: 1, max: 1, candidates: [[4, 11], [5, 12]], default: null },
          { key: 'assignment', type: 'ASSIGNMENT', min: 0, max: 0, candidates: [{ card: 1, sat: 11 }], default: { 1: 11 } },
          { key: 'useMods', type: 'USE_MODS', min: 0, max: 1, candidates: ['P:19'], default: null },
        ],
        preview: { costs: [{ card: 4, sat: 11, cost: 9 }] },
      },
    ],
  });
  assert.equal(win.questions.length, 2);
  const [order, effect] = win.questions;
  assert.equal(order.type, 'ORDER');
  assert.deepEqual(initialOrder(order), [8, 7, 9], 'デフォルトの順に、足りない分は entries の並びで補う');
  assert.deepEqual(moveEntry([8, 7, 9], 9, 'top'), [9, 8, 7]);
  assert.deepEqual(moveEntry([8, 7, 9], 7, -1), [7, 8, 9]);
  assert.deepEqual(moveEntry([8, 7, 9], 8, -1), [8, 7, 9]);
  assert.deepEqual(moveEntry([8, 7, 9], 9, 1), [8, 7, 9]);
  assert.equal(effect.type, 'EFFECT');
  if (effect.type !== 'EFFECT') {
    return;
  }
  const values = initialValues(effect);
  assert.equal(values.area, 20, '候補が 1 つなら初期値にする');
  assert.equal(values.satellite, null);
  assert.deepEqual(values.assignment, { 1: 11 });
  assert.equal(validateQuestion(effect, values).key, 'satellite');
  assert.equal(validateField(effect.fields[0], 13).ok, false);
  assert.equal(validateField(effect.fields[2], 'SE').ok, true);
  assert.equal(validateField(effect.fields[3], 5).ok, false);
  assert.equal(validateField(effect.fields[4], [4, 4]).ok, false);
  assert.equal(validateField(effect.fields[4], []).ok, false);
  assert.equal(validateField(effect.fields[5], [[5, 12]]).ok, true);
  assert.equal(validateField(effect.fields[5], [[5, 11]]).ok, false);
  assert.equal(validateField({ key: 'n', type: 'AMOUNT', min: 0, max: 3, candidates: [], default: null }, 3).ok, true);
  assert.equal(validateField({ key: 'n', type: 'AMOUNT', min: 0, max: 3, candidates: [], default: null }, 4).ok, false);
  const done = { satellite: 12, area: 20, direction: 'NE', tier: '7', cards: ['4'], pairs: [[4, 11]], assignment: { 1: '12' }, useMods: [] };
  assert.equal(validateQuestion(effect, done).ok, true);
  assert.deepEqual(answerFields(effect, done), { satellite: 12, area: 20, direction: 'NE', tier: 7, cards: [4], pairs: [[4, 11]], assignment: { 1: 12 }, useMods: [] });
  const args = useEffectArgs(effect, done, [5]);
  assert.equal(args.entryId, 7);
  assert.deepEqual(JSON.parse(String(args.fields)).assignment, { 1: 12 });
  assert.deepEqual(args.sell, [5]);
  assert.equal('sell' in useEffectArgs(effect, done, []), false);
  // 代価: 払えないときは売却の最小枚数（手札のうち、答えが使うカードは売れない）
  assert.equal(mayaToPay(effect, done), 6);
  assert.deepEqual(reservedCards(effect, done), [4, 4]);
  assert.equal(salesNeeded(effect, done, { maya: 4, price: 3, hand: [4, 5] }), 1);
  assert.equal(salesNeeded(effect, done, { maya: 0, price: 3, hand: [4, 5] }), null, '売れるのは 1 枚だけで届かない');
  assert.equal(pairCost(effect.preview, [4, 11]), 9);
  assert.equal(pairCost({ links: [{ sat: 11, cell: 20, cost: 8 }] }, [11, 20]), 8);
  assert.equal(pairCost({}, [1, 2]), null);
});

test('段階の代価は選んだ段階を払う', () => {
  const q = { type: 'EFFECT', entryId: 1, cardKey: 'p', mandatory: false, cost: { kind: 'PAY_MAYA_TIER', tiers: { 4: 1, 7: 2 } }, fields: [], preview: {} };
  assert.equal(mayaToPay(q, { tier: 7 }), 7);
  assert.equal(mayaToPay(q, {}), 4, '選ぶ前は最安');
  assert.equal(mayaToPay({ ...q, cost: { kind: 'DISCARD_COST', amount: 1 } }, {}), 0);
  assert.equal(mayaToPay({ ...q, cost: { kind: 'NONE' } }, {}), 0);
  assert.deepEqual(fieldDefault({ key: 'x', type: 'USE_MODS', min: 0, max: 2, candidates: ['a'], default: null }), []);
});

test('待機表示は自分を除く', () => {
  assert.deepEqual(parseWaiting([{ player_id: 1, sources: ['a'] }, { player_id: 2, sources: ['b', 'c'] }], 1), [{ playerId: 2, sources: ['b', 'c'] }]);
  assert.deepEqual(parseWaiting(undefined, 1), []);
});

test('カードの識別子（nameId）から名前を引く', () => {
  assert.equal(keyOfNameId('lanchester'), 'P072');
  assert.equal(nameOfNameId('lanchester'), 'Lanchester');
  assert.equal(keyOfNameId('hammer_moon'), 'R001B');
  assert.equal(nameOfNameId('hammer_moon'), 'HAMMER (MOON)');
  assert.equal(nameOfNameId('no_such_card'), 'no such card');
  assert.deepEqual(parseHostKey('P:19'), { kind: 'P', src: 19, cardKey: 'P019' });
  assert.deepEqual(parseHostKey('S:13'), { kind: 'S', src: 13, cardKey: null });
});

test('名声の内訳（N32）', () => {
  const detail = {
    total: 184,
    I: { points: 28, budget: 28 }, II: { points: 3, maya: 33 }, III: { points: 20, land: 3, sea: 14, cells: [] }, IV: { points: 28, regions: ['A', 'B', 'C', 'D', 'E', 'F', 'G'] },
    V: { points: 23, missions: [{ mission: 3, tier: 'EXTRA', points: 18, cells: [] }, { mission: 9, tier: 'EASY', points: 5, cells: [] }] },
    VI: { points: 20, sats: [{ sat_id: 11, fame: 5 }] }, VII: { points: 35, a: 20, b: 5, c: 10 },
    VIII: { points: 24, rewards: [{ card: 'T001', value: 13, rank: 1, points: 18 }, { card: 'T010', value: 4, rank: 2, points: 6 }] }, IX: { points: 3 },
  };
  const s = scoreFromNotification({ player_id: 5, fame: 184, detail, missions: [{ mission: 3, achieved: true }, { mission: 1, achieved: false }] });
  assert.equal(s.total, 184);
  assert.equal(s.rows.length, 9);
  assert.deepEqual(s.rows.map((r) => r.points), [28, 3, 20, 28, 23, 20, 35, 24, 3]);
  assert.deepEqual(s.rows[4].parts, ['mission:3/extra/18', 'mission:9/easy/5']);
  assert.deepEqual(s.rows[2].parts, ['land:3', 'sea:14']);
  assert.deepEqual(s.missions, [{ mission: 3, achieved: true }, { mission: 1, achieved: false }]);
  // getAllDatas の形（ミッションは番号の配列。達成は V から分かる）
  const [fromData] = scoresFromGamedatas({ 5: { fame: detail, missions: [3, 1] }, 6: { fame: null, missions: [2] } }, [5, 6]);
  assert.deepEqual(fromData.missions, [{ mission: 3, achieved: true }, { mission: 1, achieved: false }]);
  assert.equal(scoresFromGamedatas(null, [5]).length, 0);
  // 順位（同点は同順位）
  const ranked = rankScores([playerScore(1, { total: 100 }, []), playerScore(2, { total: 120 }, []), playerScore(3, { total: 100 }, [])]);
  assert.deepEqual(ranked.map((r) => [r.playerId, r.rank]), [[2, 1], [1, 2], [3, 2]]);
});
