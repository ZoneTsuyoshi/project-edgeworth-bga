/**
 * ボトムシート（仕様書 012 §2.5、§3.9、§6）。選択中の、コストの内訳・方向パッド・衛星の候補を出す。
 * スマホではツールチップに頼れないので、盤面の下に貼り付く（position: sticky）。数値はサーバーが args で渡したものをそのまま出す。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { SATELLITE_DIRECTIONS } from '../board/geometry.js';
import { directionGlyph, directionLabel } from '../board/Highlights.js';
import { clear, el } from './dom.js';

export class BottomSheet {
    /**
     * @param {HTMLElement} root
     */
    constructor(root) {
        this.root = root;
        root.classList.add('pe-sheet');
        root.hidden = true;
    }

    /**
     * @param {{ title?: string, lines?: string[], pad?: { onPick: (dir: string) => void, selected?: string | null, disabled?: string[] }, extra?: (HTMLElement | null)[] }} content
     */
    show(content) {
        clear(this.root);
        if (content.title !== undefined) {
            this.root.append(el('div', { class: 'pe-sheet-title' }, [content.title]));
        }
        for (const line of content.lines ?? []) {
            this.root.append(el('div', { class: 'pe-sheet-line' }, [line]));
        }
        if (content.pad !== undefined) {
            this.root.append(this.directionPad(content.pad));
        }
        for (const node of content.extra ?? []) {
            if (node !== null) {
                this.root.append(node);
            }
        }
        this.root.hidden = false;
    }

    hide() {
        clear(this.root);
        this.root.hidden = true;
    }

    /**
     * 2 列 × 3 行の方向パッド（各 56 × 48 px。区域の幅 90 px 未満のとき。012 §3.9）。画面上の位置が向きと一致する。
     * @param {{ onPick: (dir: string) => void, selected?: string | null, disabled?: string[] }} pad
     * @returns {HTMLElement}
     */
    directionPad(pad) {
        // NW NE / W E / SW SE の並び
        const order = ['NW', 'NE', 'W', 'E', 'SW', 'SE'].filter((d) => SATELLITE_DIRECTIONS.includes(d));
        return el('div', { class: 'pe-dpad', role: 'group', 'aria-label': _('Direction') }, order.map((dir) => el('button', {
            type: 'button',
            class: `pe-dpad-btn${pad.selected === dir ? ' pe-selected' : ''}`,
            disabled: pad.disabled?.includes(dir) === true,
            title: directionLabel(dir),
            'aria-label': directionLabel(dir),
            onClick: () => pad.onPick(dir),
        }, [directionGlyph(dir)])));
    }
}
