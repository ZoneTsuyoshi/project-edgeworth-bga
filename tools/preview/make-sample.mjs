// プレビュー用の getAllDatas の見本 JSON（010 §8.2 の形）を作る。盤面は 003 §3.3・§3.4 の表と §2.2・§2.3 の置き方から組み立てる。
// 実行: node tools/preview/make-sample.mjs（tools/preview/samples/*.json を書く）。PHP の BoardLayoutBuilder とは独立の見本で、描画の確認にだけ使う。
import { writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { CARD_INFO } from '../../src/modules/js/cards/card-info.js';

const DIR = path.join(path.dirname(fileURLToPath(import.meta.url)), 'samples');

// ---- 003 §3.3（表面）・§3.4（裏面）。行 0〜2 の左から ----
const T = { S: 'SEA', L: 'LAND', 深: 'DEEP_SEA', 礁: 'REEF', 森: 'FOREST', 山: 'MOUNTAIN', 雪: 'SNOW', 海: 'SEA', 陸: 'LAND' };
const parse = (s) => s.split(' ').map((c) => T[c]);
const FACES = {
  STANDARD: {
    A: ['S S L', 'S L L', 'L L L'], B: ['L S S', 'L L S', 'L L S'], C: ['S S S', 'S L L', 'S S L'], D: ['S L L', 'L S S', 'L S S'],
    E: ['L L S', 'S L S', 'S S S'], F: ['L L S', 'S S L', 'S S S'], G: ['S S S', 'S S S', 'S S S'], H: ['L S S', 'L L S', 'L S S'],
  },
  ADVANCED: {
    A: ['海 礁 雪', '礁 森 森', '陸 山 陸'], B: ['雪 礁 海', '山 森 礁', '山 陸 礁'], C: ['海 海 海', '海 雪 山', '深 礁 陸'], D: ['礁 雪 雪', '陸 礁 深', '森 礁 海'],
    E: ['山 陸 礁', '礁 山 礁', '海 礁 海'], F: ['山 森 礁', '礁 礁 陸', '海 海 海'], G: ['深 深 礁', '礁 深 礁', '雪 海 礁'], H: ['森 礁 深', '森 森 礁', '雪 礁 海'],
  },
};
const ALPHA = 'ABCD';

/** 003 §2.2・§2.3: placements[br][bc] = { board, flip } から区域の一覧を作る */
function buildBoard(face, rowShift, placements) {
  const cols = 3 * placements[0].length;
  const regions = [];
  const cells = [];
  placements.forEach((line, br) => {
    line.forEach((p, bc) => {
      const required = (br + rowShift) % 2 === 0 ? 'alpha' : 'beta';
      const type = ALPHA.includes(p.board) ? 'alpha' : 'beta';
      const flip = type !== required;
      regions.push({ br, bc, board: p.board, flip });
      FACES[face][p.board].forEach((row, lr) => {
        parse(row).forEach((areaType, lc) => {
          const r = 3 * br + (flip ? 2 - lr : lr);
          const c = 3 * bc + (flip ? 2 - lc : lc);
          cells.push({ cell: r * cols + c, x: 2 * c + ((r + rowShift) % 2), y: r, type: areaType, region: p.board });
        });
      });
    });
  });
  cells.sort((a, b) => a.cell - b.cell);
  return { cols, rows: 6, rowShift, face, regions, cells };
}

const SHAPES = ['HAMMER', 'RETRO', 'SCALLOP', 'CONCORDE', 'STEALTH'];
const LETTERS = ['B', 'G', 'R', 'Y'];
const COLORS = ['0000ff', '008000', 'ff0000', 'ffa500'];

function satellites(playerIndex, base, spec) {
  return SHAPES.map((shape, i) => {
    const s = spec[i] ?? {};
    const cards = (s.cards ?? []).map((id, k) => ({ id, activation: (s.used ?? []).includes(id) ? 1 : 0, order: k }));
    const capacity = 2 + cards.reduce((n, c) => n + CARD_INFO.projects[`P${String(c.id).padStart(3, '0')}`].fuelSlots, 0);
    return {
      satId: base + i, shape, plan: 'MOON', card: `R00${i + 1}${LETTERS[playerIndex]}`,
      cell: s.cell ?? null, dir: s.dir ?? null, fuelUsed: s.fuelUsed ?? 0, fuelCapacity: capacity, grace: false, activation: 0,
      cards: cards.map(({ id, activation }) => ({ id, activation })),
    };
  });
}

function players(ids, names) {
  // BGA がフレームワーク側で足す name・color と、PublicView 互換の maya・budget
  return Object.fromEntries(ids.map((id, i) => [id, { id, name: names[i], color: COLORS[i], score: 0, maya: 0, budget: 15 }]));
}

// ---- 見本 1: 3 人・12 列・裏面・180° 回転あり・行のずれ P = 1 ----
const ids3 = [2310001, 2310002, 2310003];
const board1 = buildBoard('ADVANCED', 1, [
  [{ board: 'C' }, { board: 'A' }, { board: 'F' }, { board: 'H' }],
  [{ board: 'E' }, { board: 'B' }, { board: 'G' }, { board: 'D' }],
]);
const sample1 = {
  _me: ids3[0],
  round: 3,
  phase: 'PLANNING',
  basePlayer: ids3[1],
  players: players(ids3, ['Aoi', 'Bruno', 'Chie']),
  playerOrder: ids3,
  board: board1,
  rewards: ['T001', 'T013'],
  deckCount: 61,
  discard: [12, 40, 7],
  myHand: [3, 18, 44, 51, 70, 88],
  myDrawn: [],
  myMissions: [1, 9],
  playerData: {
    [ids3[0]]: {
      maya: 30, budget: 15, padCell: 17, handCount: 6, drawnCount: 0, missionCount: 2, cubesLeft: 12,
      actionCards: { 1: 0, 2: 0, 3: 1, 4: 2, 5: 0 },
      rewardValues: { T001: 3, T013: 1 },
      satellites: satellites(0, 11, [
        { cell: 20, dir: 'NE', fuelUsed: 2, cards: [1, 4, 3], used: [1] },
        { cell: 0, dir: 'SW', fuelUsed: 0, cards: [10] },
        { cell: null, cards: [] },
        { cell: 35, dir: 'SE', fuelUsed: 9, cards: [2] },
        { cell: null, cards: [5, 6, 7, 8] },
      ]),
      links: [{ cell: 20, slot: 0, level: 0 }, { cell: 21, slot: 1, level: 0 }, { cell: 33, slot: 0, level: 0 }, { cell: 57, slot: 2, level: 0 }],
    },
    [ids3[1]]: {
      maya: 12, budget: 15, padCell: 8, handCount: 5, drawnCount: 0, missionCount: 2, cubesLeft: 13,
      actionCards: { 1: 2, 2: 0, 3: 0, 4: 0, 5: 0 },
      rewardValues: { T001: 2, T013: 2 },
      confirmed: true,
      satellites: satellites(1, 31, [
        { cell: 20, dir: 'NW', fuelUsed: 1, cards: [20, 21] },
        { cell: 20, dir: 'SE', fuelUsed: 0, cards: [] },
        { cell: null, cards: [30] },
        { cell: null, cards: [] },
        { cell: null, cards: [] },
      ]),
      links: [{ cell: 20, slot: 0, level: 1 }, { cell: 21, slot: 1, level: 1 }, { cell: 9, slot: 0, level: 0 }],
    },
    [ids3[2]]: {
      maya: 8, budget: 15, padCell: 47, handCount: 4, drawnCount: 0, missionCount: 2, cubesLeft: 14,
      actionCards: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 2 },
      rewardValues: { T001: 1, T013: 0 },
      satellites: satellites(2, 51, [
        { cell: 11, dir: 'NE', fuelUsed: 4, cards: [60] },
        { cell: null, cards: [] },
        { cell: null, cards: [] },
        { cell: null, cards: [] },
        { cell: null, cards: [] },
      ]),
      links: [{ cell: 46, slot: 0, level: 0 }, { cell: 21, slot: 2, level: 0 }],
    },
  },
  bonusHolders: { 3: [ids3[0]] },
  window: null,
  finalScores: null,
};

// ---- 見本 2: 2 人・6 列・表面・回転なし（Earth の 2×2 の暫定配置。003 未確定 No.1）・P = 0 ----
const ids2 = [2320001, 2320002];
const board2 = buildBoard('STANDARD', 0, [
  [{ board: 'A' }, { board: 'B' }],
  [{ board: 'E' }, { board: 'F' }],
]);
const sample2 = {
  _me: ids2[1],
  round: 1,
  phase: 'DEPLOY',
  basePlayer: ids2[0],
  players: players(ids2, ['Dai', 'Emi']),
  playerOrder: ids2,
  board: board2,
  rewards: ['T002', 'T005'],
  deckCount: 70,
  discard: [],
  myHand: [14, 22],
  myDrawn: [],
  myMissions: [4, 5],
  playerData: {
    [ids2[0]]: {
      maya: 20, budget: 15, padCell: 7, handCount: 5, drawnCount: 0, missionCount: 2, cubesLeft: 16,
      actionCards: { 1: 0, 2: 1, 3: 0, 4: 0, 5: 0 }, rewardValues: { T002: 0, T005: 0 },
      satellites: satellites(0, 11, [{ cell: null, cards: [] }, { cell: null, cards: [] }, { cell: null, cards: [] }, { cell: null, cards: [] }, { cell: null, cards: [] }]),
      links: [],
    },
    [ids2[1]]: {
      maya: 26, budget: 15, padCell: 10, handCount: 2, drawnCount: 0, missionCount: 2, cubesLeft: 16,
      actionCards: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 }, rewardValues: { T002: 0, T005: 0 },
      satellites: satellites(1, 31, [{ cell: null, cards: [] }, { cell: null, cards: [] }, { cell: null, cards: [] }, { cell: null, cards: [] }, { cell: null, cards: [] }]),
      links: [],
    },
  },
  bonusHolders: {},
  window: null,
  finalScores: null,
};

writeFileSync(path.join(DIR, 'three-players-advanced.json'), `${JSON.stringify(sample1, null, 1)}\n`);
writeFileSync(path.join(DIR, 'two-players-standard-6col.json'), `${JSON.stringify(sample2, null, 1)}\n`);
console.log('サンプルを書きました:', DIR);
