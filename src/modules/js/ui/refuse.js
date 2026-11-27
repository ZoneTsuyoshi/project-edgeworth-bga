/**
 * 選べないものを押したときの理由の表示（仕様書 014 §6.3）。BGA では `bga.dialogs.showMessage(文, 'info')`（Env.showMessage）。
 * 選択中でないとき（操作の番でないとき）は呼ばない。同じ文は 2 秒以内に繰り返さない。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** 同じ文を繰り返さない時間（ミリ秒） */
export const REFUSE_INTERVAL_MS = 2000;

/** @type {Map<string, number>} 文 → 最後に出した時刻 */
const lastShown = new Map();

/**
 * 今、この文を出してよいか（出してよいなら時刻を記録する）。純粋に近い関数（`last` を渡せるのでテストできる）。
 * @param {Map<string, number>} last
 * @param {string} text
 * @param {number} now
 * @returns {boolean}
 */
export function shouldShow(last, text, now) {
    const prev = last.get(text);
    if (prev !== undefined && now - prev < REFUSE_INTERVAL_MS) {
        return false;
    }
    last.set(text, now);
    return true;
}

/**
 * 理由を出す。理由が無ければ一般の文。
 * @param {import('../cards/CardView.js').Env} env
 * @param {string | undefined | null} reason
 */
export function refuse(env, reason) {
    const text = reason === undefined || reason === null || reason === '' ? _('You cannot choose this now') : reason;
    if (shouldShow(lastShown, text, Date.now())) {
        env.showMessage(text);
    }
}
