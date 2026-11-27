/**
 * 席順の印（設定 100。仕様書 014 §3・§13 の決定 D）。色だけに頼らず、プレイヤーを 1〜4 の数字でも見分けられるようにする。
 * 印はいつも DOM に置き、`body.pe-marks-on` のときだけ CSS が表示する（既定はオフ。紙のコマには印が無い）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { el } from './dom.js';

/** 設定 100 がオンのとき `document.body` に付けるクラス */
export const MARKS_CLASS = 'pe-marks-on';

/** 設定 100 の ID */
export const MARKS_PREF = 100;

/**
 * 席順の印（白い数字・濃い地・白い細い縁。小さいコマの上でも読める）。
 * @param {number} order 席順（1 始まり。0 以下は印なし）
 * @param {string} [extra] 追加のクラス
 * @returns {HTMLElement | null}
 */
export function seatMark(order, extra = '') {
    if (!(order > 0)) {
        return null;
    }
    return el('span', { class: `pe-mark ${extra}`.trim(), 'aria-hidden': 'true', dataset: { order } }, [String(order)]);
}

/**
 * 設定 100 の値を画面に反映する。
 * @param {boolean} on
 */
export function setMarksOn(on) {
    document.body.classList.toggle(MARKS_CLASS, on);
}
