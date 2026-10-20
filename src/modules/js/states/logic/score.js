/**
 * 名声の内訳（N32 finalScore、getAllDatas の finalScores。010 §11、001 §12）を表の行に整える。文面は呼び出し側で翻訳する。
 * 通知は `{ fame, detail, missions: [{ mission, achieved }] }`、getAllDatas は `{ fame: detail, missions: [番号] }` の形。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** 区分の並び（001 §12） */
export const SECTIONS = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];

/**
 * @typedef {object} ScoreRow
 * @property {string} section I〜IX
 * @property {number} points
 * @property {string[]} parts 内訳の部品（`kind:値` の形。呼び出し側が文面にする）
 *
 * @typedef {object} PlayerScore
 * @property {number} playerId
 * @property {number} total
 * @property {ScoreRow[]} rows
 * @property {{ mission: number, achieved: boolean | null }[]} missions
 */

/**
 * 区分の内訳から、内訳の部品を作る。
 * @param {string} section
 * @param {Record<string, any>} d
 * @returns {string[]}
 */
export function partsOf(section, d) {
  switch (section) {
    case 'I':
      return [`budget:${d.budget ?? d.points}`];
    case 'II':
      return [`maya:${d.maya ?? 0}`];
    case 'III':
      return [`land:${d.land ?? 0}`, `sea:${d.sea ?? 0}`];
    case 'IV':
      return [`regions:${Array.isArray(d.regions) ? d.regions.length : 0}`, ...(Array.isArray(d.regions) ? [`regionNames:${d.regions.join('')}`] : [])];
    case 'V':
      return (Array.isArray(d.missions) ? d.missions : []).map((/** @type {any} */ m) => `mission:${m.mission}/${String(m.tier).toLowerCase()}/${m.points}`);
    case 'VI':
      return (Array.isArray(d.sats) ? d.sats : []).map((/** @type {any} */ s) => `sat:${s.sat_id}/${s.fame}`);
    case 'VII':
      return [`a:${d.a ?? 0}`, `b:${d.b ?? 0}`, `c:${d.c ?? 0}`];
    case 'VIII':
      return (Array.isArray(d.rewards) ? d.rewards : []).map((/** @type {any} */ r) => `reward:${r.card}/${r.rank}/${r.points}`);
    default:
      return [];
  }
}

/**
 * @param {number} playerId
 * @param {Record<string, any>} detail 内訳（`I`〜`IX` と `total`）
 * @param {unknown} missions ミッション（番号の配列、または `[{ mission, achieved }]`）
 * @returns {PlayerScore}
 */
export function playerScore(playerId, detail, missions) {
  const rows = SECTIONS.map((section) => ({
    section,
    points: Number.parseInt(String(detail?.[section]?.points), 10) || 0,
    parts: partsOf(section, detail?.[section] ?? {}),
  }));
  const total = Number.isFinite(Number(detail?.total)) ? Number(detail.total) : rows.reduce((sum, r) => sum + r.points, 0);
  const achieved = new Set((Array.isArray(detail?.V?.missions) ? detail.V.missions : []).map((/** @type {any} */ m) => Number(m.mission)));
  const list = (Array.isArray(missions) ? missions : []).map((/** @type {any} */ m) => {
    if (typeof m === 'object' && m !== null) {
      return { mission: Number(m.mission), achieved: m.achieved === true };
    }
    return { mission: Number(m), achieved: achieved.has(Number(m)) };
  });
  return { playerId, total, rows, missions: list };
}

/**
 * N32 の args → PlayerScore。
 * @param {Record<string, any>} args
 * @returns {PlayerScore}
 */
export function scoreFromNotification(args) {
  const detail = { ...(args.detail ?? {}) };
  detail.total ??= Number(args.fame);
  return playerScore(Number(args.player_id), detail, args.missions);
}

/**
 * getAllDatas の finalScores → PlayerScore の一覧（席順 order に並べる）。
 * @param {unknown} raw
 * @param {number[]} order
 * @returns {PlayerScore[]}
 */
export function scoresFromGamedatas(raw, order) {
  if (raw === null || raw === undefined || typeof raw !== 'object') {
    return [];
  }
  const map = /** @type {Record<string, { fame?: Record<string, any> | null, missions?: unknown }>} */ (raw);
  return order.filter((id) => map[id]?.fame !== null && map[id]?.fame !== undefined).map((id) => playerScore(id, map[id].fame ?? {}, map[id].missions));
}

/**
 * 順位つきに並べる（合計の大きい順。同点は同順位で席順のまま。001 §12-4）。
 * @param {PlayerScore[]} scores
 * @returns {(PlayerScore & { rank: number })[]}
 */
export function rankScores(scores) {
  const sorted = [...scores].sort((a, b) => b.total - a.total);
  return sorted.map((s) => ({ ...s, rank: 1 + sorted.filter((o) => o.total > s.total).length }));
}
