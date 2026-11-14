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
 * @property {{ mission: number, slot: number, tier: string, points: number, cells: number[] }[]} outlines 採用したミッションの達成区域（V の内訳の順。終了時の盤面の枠用）。slot = そのプレイヤーのミッションカードの並びでの位置（0 = 1 枚目。枠の色・線種を決める）
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
 * ミッションカードのスロット（0 = 1 枚目、1 = 2 枚目）。枠の色・線種はこれで決める（012 追補 2026-11-14 C）。
 * @param {number[]} cards そのプレイヤーのミッションカードの番号（並び順）
 * @param {number} mission
 * @param {number} fallback カードの並びが分からないとき（内訳の順）
 * @returns {number}
 */
export function missionSlot(cards, mission, fallback = 0) {
  const at = cards.indexOf(mission);
  return Math.max(0, at >= 0 ? at : fallback) % 2;
}

/**
 * 終了時に表向きにする全員のミッションカード（012 追補 2026-11-14 C）。ゲーム中・得点が未着のプレイヤーは null（従来どおり裏面）。
 * @param {PlayerScore | undefined} score
 * @returns {number[] | null}
 */
export function revealedMissions(score) {
  return score === undefined || score.missions.length === 0 ? null : score.missions.map((m) => m.mission);
}

/**
 * ミッションカードに付ける枠（番号 → スロット）。達成していない（outlines にない）カードは枠なし。
 * @param {PlayerScore | undefined} score
 * @returns {Map<number, number>}
 */
export function missionFrames(score) {
  return new Map((score?.outlines ?? []).map((o) => [o.mission, o.slot]));
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
  const outlines = (Array.isArray(detail?.V?.missions) ? detail.V.missions : []).map((/** @type {any} */ m, /** @type {number} */ i) => ({
    mission: Number(m.mission),
    slot: missionSlot(list.map((l) => l.mission), Number(m.mission), i),
    tier: String(m.tier).toLowerCase(),
    points: Number(m.points) || 0,
    cells: (Array.isArray(m.cells) ? m.cells : []).map(Number),
  }));
  return { playerId, total, rows, missions: list, outlines };
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

/** 途中の名声（ゲームオプション 105）の内訳に出す区分。ミッション（V）は含めない（001 §12） */
export const PUBLIC_FAME_SECTIONS = SECTIONS.filter((section) => section !== 'V');

/**
 * getAllDatas の `publicFame`（プレイヤー ID => `{ total, detail }`）を読む。キーが無ければ null（Hidden。何も出さない）。
 * @param {unknown} raw
 * @returns {Record<number, { total: number, detail: Record<string, any> }> | null}
 */
export function parsePublicFame(raw) {
  if (raw === null || raw === undefined || typeof raw !== 'object' || Array.isArray(raw)) {
    return null;
  }
  /** @type {Record<number, { total: number, detail: Record<string, any> }>} */
  const result = {};
  for (const [id, value] of Object.entries(/** @type {Record<string, any>} */ (raw))) {
    const detail = value?.detail !== null && typeof value?.detail === 'object' ? value.detail : {};
    const total = Number.parseInt(String(value?.total ?? detail.total), 10);
    result[Number(id)] = { total: Number.isNaN(total) ? 0 : total, detail };
  }
  return result;
}

/**
 * N36 の `fame` を、今の途中の名声へ重ねる（通知に無いプレイヤーは前の値のまま）。
 * @param {Record<number, { total: number, detail: Record<string, any> }> | null} current
 * @param {unknown} fame
 * @returns {Record<number, { total: number, detail: Record<string, any> }> | null}
 */
export function mergePublicFame(current, fame) {
  const next = parsePublicFame(fame);
  return next === null ? current : { ...(current ?? {}), ...next };
}

/**
 * 途中の名声の内訳（ホバーで出す行）。ミッション（V）を除く I〜IX。
 * @param {Record<string, any>} detail
 * @returns {{ section: string, points: number }[]}
 */
export function fameBreakdown(detail) {
  return PUBLIC_FAME_SECTIONS.map((section) => ({ section, points: Number.parseInt(String(detail?.[section]?.points), 10) || 0 }));
}
