// 状態ごとの操作 UI の見本（状態に入るときの args の見本 JSON）を作る。見本 three-players-advanced の自分（Aoi。_me）が対象。
// 実行: node tools/preview/make-state-samples.mjs（tools/preview/samples/states/*.json を書く）。
// 使い方: tools/preview/index.html?state=<見本名>（steps で操作を再生して、画面を確かめる）。args の形はサーバーの getArgs（ChoiceArgs.php、WindowFlow::resolveArgs）に合わせている。
import { writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { CARD_INFO } from '../../src/modules/js/cards/card-info.js';

const DIR = path.join(path.dirname(fileURLToPath(import.meta.url)), 'samples', 'states');
const ME = 2310001;
const OTHERS = [2310002, 2310003];
const ALL = [ME, ...OTHERS];
const HAND = [3, 18, 44, 51, 70, 88];
const baseCost = (no) => CARD_INFO.projects[`P${String(no).padStart(3, '0')}`].cost;

/** 同時選択の共通の args（公開部分）。私的な部分を _private に入れる */
const choice = (mine, extra = {}) => ({ participants: ALL, confirmed: [OTHERS[0]], ...extra, _private: { myChoice: null, ...mine } });
/** 見本ファイル */
const sample = (name, body) => writeFileSync(path.join(DIR, `${name}.json`), `${JSON.stringify({ sample: 'three-players-advanced', ...body }, null, 1)}\n`);

// ---- 11 / 13 / 22 ----
sample('launchpad', {
  state: 'SetupLaunchPadPlace',
  active: true,
  args: { vacantCells: [2, 3, 4, 10, 11, 24, 25, 26, 36] },
  steps: [{ cellClick: 25 }],
});
sample('mulligan', {
  state: 'SetupMulligan',
  active: true,
  args: choice({ hand: HAND }),
  steps: [{ click: '.pe-hand-card[data-id="18"]' }, { click: '.pe-hand-card[data-id="70"]' }],
});
sample('planning', {
  state: 'PlanningPlot',
  active: true,
  args: choice({ selectable: [1, 2, 5] }),
  steps: [{ click: '.pe-action-card[data-no="2"]' }],
});

// ---- 61 / 62 配備 ----
const deployTargets = () => {
  const sats = [
    { satId: 11, tier: 4, active: true, blocked: null },
    { satId: 12, tier: 2, active: true, blocked: null },
    { satId: 13, tier: 1, active: false, blocked: null },
    { satId: 14, tier: 2, active: true, blocked: null },
    { satId: 15, tier: 5, active: false, blocked: 'full' },
  ];
  const targets = {};
  for (const card of HAND) {
    targets[card] = sats.map((s) => {
      const cost = baseCost(card) + (s.active ? 5 : 0);
      const entry = { satId: s.satId, tier: s.tier, cost, blocked: s.blocked, minSales: 0, selectable: s.blocked === null };
      if (card === 3 && s.active && s.blocked === null) {
        entry.useMods = { options: [{ hostKey: 'P:19', cardKey: 'fleet_commander', reduction: 2, cost: Math.max(0, cost - 2) }], all: Math.max(0, cost - 2) };
      }
      return entry;
    });
  }
  return targets;
};
sample('deploy-main', {
  state: 'DeployMainChoose',
  active: true,
  args: choice({ hand: HAND, sellValue: 3, targets: deployTargets() }),
  steps: [{ click: '.pe-hand-card[data-id="3"]' }, { click: '.pe-sat[data-sat-id="11"]' }, { click: '.pe-mod input' }],
});
sample('deploy-main-sell', {
  state: 'DeployMainChoose',
  active: true,
  maya: 8,
  args: choice({ hand: HAND, sellValue: 3, targets: deployTargets() }),
  steps: [{ click: '.pe-hand-card[data-id="51"]' }, { click: '.pe-sat[data-sat-id="12"]' }, { click: '.pe-picker-slot:nth-child(1) .pe-picker-card' }],
});
sample('deploy-main-sat-first', {
  state: 'DeployMainChoose',
  active: true,
  args: choice({ hand: HAND, sellValue: 3, targets: deployTargets() }),
  steps: [{ click: '.pe-sat[data-sat-id="13"]' }],
});
sample('deploy-bonus', {
  state: 'DeployBonusChoose',
  active: true,
  args: choice({ hand: HAND, sellValue: 3, targets: deployTargets(), canDraw: true }),
  steps: [{ clickText: 'Draw 1 card' }],
});
sample('deploy-confirmed', {
  state: 'DeployMainChoose',
  active: false,
  args: choice({ hand: HAND, sellValue: 3, targets: deployTargets(), myChoice: { op: 'deploy', card: 44, sat: 13, sell: [] } }),
  steps: [],
});

// ---- 71 / 72 打ち上げ / 転回 ----
const launchable = [
  { satId: 13, cost: 10, blocked: 'no_project', minSales: 0, selectable: false },
  { satId: 15, cost: 15, blocked: null, minSales: 0, selectable: true, useMods: { options: [{ hostKey: 'P:36', cardKey: 'lightweight_frame', reduction: 4, cost: 11 }], all: 11 } },
];
const turnable = [
  { satId: 11, dir: 'NE', blocked: null, selectable: true },
  { satId: 12, dir: 'SW', blocked: null, selectable: true },
  { satId: 14, dir: 'SE', blocked: null, selectable: true },
];
sample('launch-main', {
  state: 'LaunchMainChoose',
  active: true,
  args: choice({ hand: HAND, sellValue: 3, launchable, turnable }),
  steps: [{ clickText: 'Launch' }, { click: '.pe-sat[data-sat-id="15"]' }, { click: '.pe-dpad-btn[aria-label="Down-right"]' }],
});
sample('launch-main-east', {
  state: 'LaunchMainChoose',
  active: true,
  args: choice({ hand: HAND, sellValue: 3, launchable, turnable }),
  steps: [{ clickText: 'Launch' }, { click: '.pe-sat[data-sat-id="15"]' }, { click: '.pe-dpad-btn[aria-label="Right"]' }],
});
sample('launch-main-turn', {
  state: 'LaunchMainChoose',
  active: true,
  args: choice({ hand: HAND, sellValue: 3, launchable, turnable }),
  steps: [{ clickText: 'Turn' }, { click: '.pe-sat[data-sat-id="11"]' }, { click: '.pe-dpad-btn[aria-label="Up-right"]' }],
});
sample('launch-main-sell', {
  state: 'LaunchMainChoose',
  active: true,
  maya: 5,
  args: choice({ hand: HAND, sellValue: 3, launchable, turnable }),
  steps: [{ clickText: 'Launch' }, { click: '.pe-sat[data-sat-id="15"]' }, { click: '.pe-dpad-btn[aria-label="Up-left"]' }],
});
sample('launch-bonus', {
  state: 'LaunchBonusChoose',
  active: true,
  args: choice({ hand: HAND, sellValue: 3, launchable: [], turnable, canBudget: true }),
  steps: [],
});

// ---- 81 / 82 通信確立 ----
const linkable = [
  { cell: 22, selectable: true, sats: [{ satId: 11, cost: { area: 5, pioneer: 3, total: 8 }, minSales: 0 }, { satId: 14, cost: { area: 5, pioneer: 3, total: 8 }, minSales: 0 }] },
  { cell: 32, selectable: true, sats: [{ satId: 11, cost: { area: 9, pioneer: 0, total: 9 }, minSales: 0, useMods: { options: [{ hostKey: 'S:11', cardKey: 'hammer_ganymede', reduction: 2, cost: 7 }], all: 7 } }] },
  { cell: 34, selectable: true, sats: [{ satId: 14, cost: { area: 20, pioneer: 6, total: 26 }, minSales: 0 }] },
  { cell: 9, selectable: true, sats: [{ satId: 12, cost: { area: 6, pioneer: 0, total: 6 }, minSales: 0 }] },
  { cell: 47, selectable: false, sats: [{ satId: 14, cost: { area: 40, pioneer: 0, total: 40 }, minSales: null }] },
];
sample('link-main', {
  state: 'LinkMainChoose',
  active: true,
  args: choice({ hand: HAND, sellValue: 3, linkable }),
  steps: [{ cellClick: 22 }, { click: '.pe-sat[data-sat-id="14"]' }],
});
sample('link-main-sell', {
  state: 'LinkMainChoose',
  active: true,
  maya: 7,
  args: choice({ hand: HAND, sellValue: 3, linkable }),
  steps: [{ cellClick: 32 }],
});
sample('link-bonus', {
  state: 'LinkBonusChoose',
  active: true,
  args: choice({ hand: HAND, sellValue: 3, linkable: linkable.slice(0, 2) }),
  steps: [],
});

// ---- 91 企画 ----
sample('proposal', {
  state: 'ProposalKeepChoose',
  active: true,
  maya: 6,
  args: choice(
    {
      drawn: [5, 6, 7, 8, 9],
      keep: 2,
      hand: HAND,
      sellValue: 3,
      extra: {
        handDiscard: true,
        handCandidates: HAND,
        options: [
          { hostKey: 'P:70', cardKey: 'spin_off', kind: 'EXTRA_DISCARD', reward: 7, tiers: [] },
          { hostKey: 'P:72', cardKey: 'lanchester', kind: 'KEEP_PURCHASE', reward: 0, tiers: { 4: 1, 7: 2, 10: 3 }, affordable: [4], affordableBySelling: [4, 7], maxKeep: 5 },
        ],
      },
    },
    { keepCount: { [ME]: 2, [OTHERS[0]]: 2, [OTHERS[1]]: 2 } },
  ),
  steps: [{ click: '.pe-picker-slot:nth-child(1) .pe-picker-card' }, { click: '.pe-picker-slot:nth-child(2) .pe-picker-card' }, { click: 'input[type="radio"][name="pe-opt-P:72"]:not(:checked):not(:disabled)', nth: 2 }],
});
sample('proposal-plain', {
  state: 'ProposalKeepChoose',
  active: true,
  args: choice({ drawn: [5, 6], keep: 1, hand: HAND, sellValue: 3, extra: [] }),
  steps: [{ click: '.pe-picker-slot:nth-child(2) .pe-picker-card' }],
});

// ---- 31 効果の窓 ----
const waiting = [{ player_id: OTHERS[0], sources: ['procurement_officer'] }, { player_id: OTHERS[1], sources: ['twin_deploy_technician', 'juggler'] }];
const win = (questions, pending = []) => ({ windowId: 'DE', round: 3, waiting: [{ player_id: ME, sources: ['procurement_officer'] }, ...waiting], _private: { windowId: 'DE', pending, questions, hand: HAND, sellValue: 3 } });
const effectQ = (entryId, cardKey, o = {}) => ({ type: 'EFFECT', entryId, cardKey, mandatory: false, cost: { kind: 'NONE' }, fields: [], preview: {}, ...o });
sample('effects-order', {
  state: 'EffectWindowResolve',
  active: true,
  args: win([{ type: 'ORDER', entries: [{ entryId: 1, cardKey: 'twin_deploy_technician', forced: false }, { entryId: 2, cardKey: 'first_penguin', forced: true }, { entryId: 3, cardKey: 'juggler', forced: false }], defaultOrder: [2, 1, 3] }]),
  steps: [{ click: '.pe-order-row[data-entry="3"] button[aria-label="Move to the top"]' }],
});
sample('effects-use', {
  state: 'EffectWindowResolve',
  active: true,
  maya: 3,
  args: win([
    effectQ(10, 'procurement_officer', { cost: { kind: 'PAY_MAYA', amount: 4, canPay: false, shortfall: 1, canPayBySelling: true } }),
    effectQ(11, 'twin_deploy_technician', {
      fields: [
        { key: 'pairs', type: 'PAIRS', min: 1, max: 1, candidates: [[44, 13], [51, 13], [44, 11]], default: null },
        { key: 'useMods', type: 'USE_MODS', min: 0, max: 1, candidates: ['P:19'], default: null },
      ],
      preview: { costs: [{ card: 44, sat: 13, cost: 8 }, { card: 51, sat: 13, cost: 6 }, { card: 44, sat: 11, cost: 13 }] },
    }),
    effectQ(12, 'acceleration_booster', {
      cost: { kind: 'PAY_MAYA_TIER', tiers: { 2: 1, 3: 2, 4: 3, 5: 4 }, canPay: true, shortfall: 0, canPayBySelling: true },
      fields: [{ key: 'tier', type: 'AMOUNT', min: 0, max: 0, candidates: [2, 3, 4], default: null }],
      preview: { tiers: { 2: 1, 3: 2, 4: 3, 5: 4 } },
    }),
  ]),
  steps: [{ click: '.pe-effect[data-entry="11"] .pe-chip-btn' }],
});
sample('effects-fields', {
  state: 'EffectWindowResolve',
  active: true,
  args: win([
    effectQ(20, 'euro_africa_relay_network', {
      fields: [
        { key: 'area', type: 'AREA', min: 1, max: 1, candidates: [22, 32, 34], default: null },
        { key: 'direction', type: 'DIRECTION', min: 0, max: 0, candidates: ['NE', 'SE', 'NW', 'SW'], default: null },
        { key: 'satellite', type: 'SATELLITE', min: 0, max: 0, candidates: [11, 12, 14], default: null },
        { key: 'cards', type: 'CARDS', min: 1, max: 1, candidates: HAND, default: null },
      ],
    }),
    effectQ(21, 'juggler', {
      fields: [{ key: 'assignment', type: 'ASSIGNMENT', min: 0, max: 0, candidates: [{ card: 5, sat: 13 }, { card: 6, sat: 13 }], default: { 5: 13, 6: 13 } }],
      preview: { satellites: [13, 15] },
    }),
    effectQ(22, 'ground_link', {
      cost: { kind: 'DISCARD_COST', amount: 2, canPay: true },
      fields: [{ key: 'pairs', type: 'PAIRS', min: 1, max: 1, candidates: [[11, 22], [14, 34]], default: null }],
      preview: { links: [{ sat: 11, cell: 22, cost: 8, areaCost: 5, pioneer: 3, special: 0, costWithMods: 8 }, { sat: 14, cell: 34, cost: 26, areaCost: 20, pioneer: 6, special: 0, costWithMods: 26 }] },
    }),
  ]),
  steps: [],
});
sample('effects-waiting', {
  state: 'EffectWindowResolve',
  active: false,
  args: { windowId: 'DE', round: 3, waiting, _private: undefined },
  steps: [],
});

// ---- 名声の内訳（通知 N32） ----
const detail = (total, scale) => ({
  total,
  I: { points: 28 * scale, budget: 28 * scale },
  II: { points: 3, maya: 33 },
  III: { points: 20, land: 3, sea: 14, cells: [] },
  IV: { points: 28, regions: ['A', 'B', 'C', 'D', 'E', 'F', 'G'] },
  V: { points: 23, missions: [{ mission: 3, tier: 'EXTRA', points: 18, cells: [] }, { mission: 9, tier: 'EASY', points: 5, cells: [] }] },
  VI: { points: 20, sats: [{ sat_id: 11, fame: 5 }, { sat_id: 12, fame: 5 }, { sat_id: 14, fame: 10 }] },
  VII: { points: 35, a: 20, b: 5, c: 10 },
  VIII: { points: 24, rewards: [{ card: 'T001', value: 13, rank: 1, points: 18 }, { card: 'T013', value: 4, rank: 2, points: 6 }] },
  IX: { points: 3 },
});
sample('final-score', {
  state: null,
  active: false,
  args: {},
  notifications: [
    ['finalScore', { player_id: ALL[0], fame: 184, detail: detail(184, 1), missions: [{ mission: 1, achieved: false }, { mission: 9, achieved: true }] }],
    ['finalScore', { player_id: ALL[1], fame: 120, detail: detail(120, 0.5), missions: [{ mission: 3, achieved: true }, { mission: 5, achieved: false }] }],
    ['finalScore', { player_id: ALL[2], fame: 120, detail: detail(120, 0.5), missions: [{ mission: 2, achieved: false }, { mission: 4, achieved: false }] }],
  ],
  steps: [],
});
console.log('状態の見本を書きました:', DIR);
