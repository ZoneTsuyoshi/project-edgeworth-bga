/**
 * getAllDatas（010 §8.2）の値を、画面が使う形にそろえる（BGA に依存しない純粋関数）。
 * BGA は整数を文字列で渡すことがあるので int にそろえ、I-07 の getAllDatas にまだ無い項目は既定値で埋める（無ければ表示しない）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { CARD_INFO } from '../cards/card-info.js';
import { int } from './dom.js';

/** gameinfos.jsonc の player_colors（青・緑・赤・橙）→ 色番号（0 = BLUE、1 = GREEN、2 = RED、3 = YELLOW。002 §3.6） */
const HEX_TO_COLOR = { '0000ff': 0, '008000': 1, ff0000: 2, ffa500: 3 };
const NAME_TO_COLOR = { BLUE: 0, GREEN: 1, RED: 2, YELLOW: 3, b: 0, g: 1, r: 2, y: 3 };

/**
 * 色を 0〜3 にする。数値・'BLUE' などの名前・BGA の 16 進（'0000ff'）を受ける。決められなければ fallback。
 * @param {unknown} value
 * @param {number} fallback
 * @returns {number}
 */
export function colorIndexOf(value, fallback) {
  if (typeof value === 'number' && value >= 0 && value <= 3) {
    return value;
  }
  if (typeof value === 'string') {
    const lower = value.toLowerCase().replace('#', '');
    if (lower in HEX_TO_COLOR) {
      return HEX_TO_COLOR[/** @type {keyof typeof HEX_TO_COLOR} */ (lower)];
    }
    const named = NAME_TO_COLOR[/** @type {keyof typeof NAME_TO_COLOR} */ (value)] ?? NAME_TO_COLOR[/** @type {keyof typeof NAME_TO_COLOR} */ (value.toUpperCase())];
    if (named !== undefined) {
      return named;
    }
    if (/^\d$/.test(value) && Number(value) <= 3) {
      return Number(value);
    }
  }
  return fallback;
}

/**
 * 衛星カードの面（R001B・R006 など）の色（002 の衛星カード表。010 §5.1 の PublicView は色を送らないので、カードから決める）。
 * @param {string} card
 * @returns {number | null}
 */
export function colorOfSatelliteCard(card) {
  const info = /** @type {Record<string, { color?: string }>} */ (CARD_INFO.satellites)[card];
  const c = colorIndexOf(info?.color, -1);
  return c < 0 ? null : c;
}

/**
 * @typedef {object} PlayerModel
 * @property {number} id
 * @property {string} name
 * @property {number} colorIndex 0〜3
 * @property {number} maya
 * @property {number} budget
 * @property {number | null} padCell
 * @property {number} handCount
 * @property {number} drawnCount
 * @property {number} missionCount
 * @property {number} cubesLeft
 * @property {Record<string, number>} actionCards アクション番号 → 0 手札 / 1 縦 / 2 横
 * @property {import('../cards/SatelliteView.js').SatelliteData[]} satellites
 * @property {{ cell: number, slot: number, level: number }[]} links
 * @property {Record<string, number>} rewardValues 褒賞カード（T001 など）→ 比較値の現在値（010 に未定義。→ 報告）
 * @property {boolean} confirmed 同時選択で確定済みか
 */

/**
 * @typedef {object} GameModel
 * @property {number} round
 * @property {string} phase SETUP / PLANNING / BUDGET / DEPLOY / LAUNCH_TURN / LINK / PROPOSAL / ORBIT / END
 * @property {number | null} basePlayer
 * @property {number[]} playerOrder
 * @property {number | null} me 観戦者は null
 * @property {import('../board/BoardView.js').BoardData | null} board
 * @property {string[]} rewards
 * @property {number} deckCount
 * @property {number[]} discard
 * @property {number[]} myHand
 * @property {number[]} myDrawn
 * @property {number[]} myMissions
 * @property {Record<number, PlayerModel>} players
 * @property {Record<string, number[]>} bonusHolders アクション番号 → 保持者（キーがある = 選ばれたアクション）
 * @property {{ id: string } | null} window
 * @property {unknown} finalScores
 */

/**
 * @param {any} raw getAllDatas の戻り値
 * @param {number | null} me 閲覧者のプレイヤー ID（観戦者は null）
 * @returns {GameModel}
 */
export function normalizeGamedatas(raw, me) {
  const order = (raw.playerOrder ?? Object.keys(raw.players ?? {})).map((/** @type {unknown} */ v) => int(v));
  /** @type {Record<number, PlayerModel>} */
  const players = {};
  order.forEach((/** @type {number} */ id, /** @type {number} */ index) => {
    const base = raw.players?.[id] ?? {};
    const data = raw.playerData?.[id] ?? {};
    const cardColor = colorOfSatelliteCard(String(data.satellites?.[0]?.card ?? ''));
    const colorIndex = colorIndexOf(data.color ?? data.colorIndex ?? base.color ?? base.player_color, cardColor ?? index);
    const satellites = (data.satellites ?? []).map((/** @type {any} */ s) => ({
      satId: int(s.satId),
      card: String(s.card),
      shape: s.shape === undefined ? undefined : String(s.shape),
      cell: s.cell === null || s.cell === undefined ? null : int(s.cell),
      dir: s.dir === null || s.dir === undefined ? null : String(s.dir),
      fuelUsed: int(s.fuelUsed),
      fuelCapacity: int(s.fuelCapacity),
      grace: s.grace === true || s.grace === 1,
      activation: int(s.activation),
      cards: (s.cards ?? []).map((/** @type {any} */ c) => ({ id: int(c.id), activation: int(c.activation) })),
      colorIndex: colorIndexOf(s.color, colorOfSatelliteCard(String(s.card)) ?? colorIndex),
    }));
    players[id] = {
      id,
      name: String(base.name ?? base.player_name ?? data.name ?? `#${id}`),
      colorIndex,
      maya: int(data.maya ?? base.maya),
      budget: int(data.budget ?? base.budget),
      padCell: data.padCell === null || data.padCell === undefined ? null : int(data.padCell),
      handCount: int(data.handCount),
      drawnCount: int(data.drawnCount),
      missionCount: int(data.missionCount),
      cubesLeft: int(data.cubesLeft, 16),
      actionCards: Object.fromEntries(Object.entries(data.actionCards ?? {}).map(([k, v]) => [k, int(v)])),
      satellites,
      links: (data.links ?? []).map((/** @type {any} */ l) => ({ cell: int(l.cell), slot: int(l.slot), level: int(l.level) })),
      rewardValues: Object.fromEntries(Object.entries(data.rewardValues ?? {}).map(([k, v]) => [k, int(v)])),
      confirmed: data.confirmed === true,
    };
  });
  const board = raw.board === undefined || raw.board === null ? null : {
    cols: int(raw.board.cols, 12),
    rows: int(raw.board.rows, 6),
    rowShift: int(raw.board.rowShift),
    regions: (raw.board.regions ?? []).map((/** @type {any} */ r) => ({ br: int(r.br), bc: int(r.bc), board: String(r.board), flip: r.flip === true })),
    cells: (raw.board.cells ?? []).map((/** @type {any} */ c) => ({ cell: int(c.cell), type: String(c.type), region: String(c.region) })),
  };
  return {
    round: int(raw.round),
    phase: String(raw.phase ?? 'SETUP'),
    basePlayer: raw.basePlayer === undefined || raw.basePlayer === null ? null : int(raw.basePlayer),
    playerOrder: order,
    me: me !== null && order.includes(me) ? me : null,
    board,
    rewards: (raw.rewards ?? []).map(String),
    deckCount: int(raw.deckCount),
    discard: (raw.discard ?? []).map((/** @type {unknown} */ v) => int(v)),
    myHand: (raw.myHand ?? []).map((/** @type {unknown} */ v) => int(v)),
    myDrawn: (raw.myDrawn ?? []).map((/** @type {unknown} */ v) => int(v)),
    myMissions: (raw.myMissions ?? []).map((/** @type {unknown} */ v) => int(v)),
    players,
    bonusHolders: Object.fromEntries(Object.entries(raw.bonusHolders ?? {}).map(([k, v]) => [k, /** @type {unknown[]} */ (v).map((x) => int(x))])),
    window: raw.window ?? null,
    finalScores: raw.finalScores ?? null,
  };
}
