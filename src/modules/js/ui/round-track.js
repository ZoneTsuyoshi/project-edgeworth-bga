/**
 * ラウンドトラックの状態の計算（仕様書 014 §3）。DOM に触れない純粋関数。
 * 入力は `flow_phase`（010 §7.2）・ラウンド番号・アクション選択ボーナスの保持者（`bonusHolders`）・席順。出力は段ごとの状態と保持者。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** flow_phase → アクション番号（A001〜A005 = I〜V） */
export const PHASE_ACTION = { BUDGET: 1, DEPLOY: 2, LAUNCH_TURN: 3, LINK: 4, PROPOSAL: 5 };

/** flow_phase → 段の番号（0 = 計画、1〜5 = I〜V、6 = 循環） */
export const PHASE_STEP = { PLANNING: 0, BUDGET: 1, DEPLOY: 2, LAUNCH_TURN: 3, LINK: 4, PROPOSAL: 5, ORBIT: 6 };

/** 段の並び（番号 0〜6） */
export const STEP_NUMBERS = [0, 1, 2, 3, 4, 5, 6];

/**
 * @typedef {'current' | 'todo' | 'skipped' | 'done'} StepState
 *   current = 今の段、todo = これから行う（または公開前でまだ分からない）、skipped = このラウンドは行わない（誰も選ばなかった。公開後）、done = 済んだ
 */

/**
 * @typedef {object} RoundTrackState
 * @property {'setup' | 'round' | 'end'} mode setup = セットアップ中（ラウンド 0）、end = ゲーム終了
 * @property {number} round
 * @property {{ no: number, state: StepState, holders: number[] }[]} steps 計画（0）・I〜V（1〜5）・循環（6）の順。holders は I〜V だけ（席順）
 */

/**
 * ラウンドトラックの状態を計算する。
 * - 公開前（計画フェイズ、または `bonusHolders` が空）は I〜V を全部 `todo`（まだ誰が何を選んだか分からない）。
 * - 公開後は、`bonusHolders` にキーがある I〜V が「選ばれた」。選ばれなかった段は、今の段より前でも後でも `skipped`。
 * @param {object} p
 * @param {number} p.round
 * @param {string} p.phase SETUP / PLANNING / BUDGET / DEPLOY / LAUNCH_TURN / LINK / PROPOSAL / ORBIT / END
 * @param {Record<string, number[]>} p.bonusHolders アクション番号（文字列）→ 保持者の ID（キーがある = 選ばれたアクション）
 * @param {number[]} p.playerOrder 席順（保持者の並びに使う）
 * @returns {RoundTrackState}
 */
export function roundTrackState({ round, phase, bonusHolders, playerOrder }) {
  if (phase === 'END') {
    return { mode: 'end', round, steps: [] };
  }
  if (phase === 'SETUP' || round <= 0) {
    return { mode: 'setup', round, steps: [] };
  }
  const current = PHASE_STEP[/** @type {keyof typeof PHASE_STEP} */ (phase)] ?? 0;
  const chosen = new Set(Object.keys(bonusHolders).map(Number).filter((n) => n >= 1 && n <= 5));
  const revealed = phase !== 'PLANNING' && chosen.size > 0;
  const seat = (/** @type {number} */ id) => {
    const i = playerOrder.indexOf(id);
    return i < 0 ? Number.MAX_SAFE_INTEGER : i;
  };
  const steps = STEP_NUMBERS.map((no) => {
    const isAction = no >= 1 && no <= 5;
    /** @type {StepState} */
    let state = 'todo';
    if (no === current) {
      state = 'current';
    } else if (isAction && revealed && !chosen.has(no)) {
      state = 'skipped';
    } else if (no < current) {
      state = 'done';
    }
    const holders = isAction && revealed ? [...(bonusHolders[String(no)] ?? [])].sort((a, b) => seat(a) - seat(b)) : [];
    return { no, state, holders };
  });
  return { mode: 'round', round, steps };
}
