/**
 * 選べる・選べない・選択中のクラス名（仕様書 014 §6.1。カード・ロケット・アクションカード・コマに同じ意味で使う）。
 * 旧クラスはこの 5 つに統一した。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** 5 つのクラス */
export const PICK_CLASSES = ['pe-can', 'pe-can-sell', 'pe-picked', 'pe-off', 'pe-subject'];

/**
 * 衛星の選択モードの state → クラス。
 * @param {'selectable' | 'sellable' | 'blocked' | 'selected'} state
 * @returns {'pe-can' | 'pe-can-sell' | 'pe-off' | 'pe-picked'}
 */
export function pickClass(state) {
    switch (state) {
        case 'sellable':
            return 'pe-can-sell';
        case 'blocked':
            return 'pe-off';
        case 'selected':
            return 'pe-picked';
        default:
            return 'pe-can';
    }
}
