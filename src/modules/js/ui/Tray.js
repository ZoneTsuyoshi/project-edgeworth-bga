/**
 * トレイ（`#pe-tray`。仕様書 014 §5）。盤面の下の、卓に無い部品を選ぶ・読むための領域。箱なし。レールとラベル、その下に中身。
 * 旧・選択パネル（ChoicePanel）とボトムシート（BottomSheet）を 1 つにしたもの。開いても盤面は上下せず、画面外でも自動でスクロールしない。
 * 中身が何も無いときはトレイごと隠す。説明文（hint）は出さない（説明はステータスバーのタイトル 1 行に）。
 *
 * 使い方（状態クラス側）:
 *   view.tray.show([{ label: 'Pick 2 of 4', body: picker.root }, { body: [costLine(['Deploy cost: 5 Maya']), directionPad({ onPick })] }]);
 *   view.tray.hide();
 * 中身の部品: カードの行（`CardPicker`）、コストと結果の行（`costLine`）、方向パッド（`directionPad`）、欄（`states/HandTargets.js` の欄の行）、
 * 捨て札の一覧（`showList`。節とは別の置き場）。効果の窓は、今の一覧をそのまま 1 つの節として載せる（PR 5 で作り替える）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { SATELLITE_DIRECTIONS } from '../board/geometry.js';
import { directionIcon, directionLabel } from '../board/Highlights.js';
import { clear, el } from './dom.js';

/**
 * トレイの節 1 つ。label はレール（12 px・大文字）のラベル、body は中身（要素・要素の配列）。
 * @typedef {object} TraySection
 * @property {string} [label]
 * @property {Node | (Node | null | false | undefined)[] | null} body
 */

/**
 * コストと結果の行（文字だけ。1 行にまとめ、収まらないときだけ折り返す。警告はアイコン + 警告色）。
 * @param {string[]} parts `·` でつなぐ文
 * @param {{ warn?: string, ok?: string }} [opts] warn = 警告の文（アイコン + 警告色）、ok = 条件を満たした注記（OK の色）
 * @returns {HTMLElement}
 */
export function costLine(parts, opts = {}) {
    const children = /** @type {(Node | string)[]} */ ([parts.filter((p) => p !== '').join(' · ')]);
    if (opts.warn !== undefined) {
        const icon = el('span', { class: 'pe-warn-icon', 'aria-hidden': 'true' });
        icon.innerHTML = '<svg viewBox="0 0 24 24"><path d="M12 3L2 21h20z" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/><path d="M12 10v5M12 17.8v1.2" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>';
        children.push(el('span', { class: 'pe-cost-warn' }, [icon, opts.warn]));
    }
    if (opts.ok !== undefined) {
        children.push(el('span', { class: 'pe-cost-ok' }, [opts.ok]));
    }
    return el('p', { class: 'pe-cost-line' }, children);
}

/**
 * 2 列 × 3 行の方向パッド（各 56 × 48 px。区域の幅 90 px 未満のとき。012 §3.9、014 §5）。画面上の位置が向きと一致する。盤面の操作具として例外。
 * @param {{ onPick: (dir: string) => void, selected?: string | null, disabled?: string[] }} pad
 * @returns {HTMLElement}
 */
export function directionPad(pad) {
    // NW NE / W E / SW SE の並び
    const order = ['NW', 'NE', 'W', 'E', 'SW', 'SE'].filter((d) => SATELLITE_DIRECTIONS.includes(d));
    return el('div', { class: 'pe-dpad', role: 'group', 'aria-label': _('Direction') }, order.map((dir) => el('button', {
        type: 'button',
        class: `pe-dpad-btn${pad.selected === dir ? ' pe-picked' : ''}`,
        disabled: pad.disabled?.includes(dir) === true,
        'aria-label': directionLabel(dir),
        'aria-pressed': pad.selected === dir ? 'true' : 'false',
        onClick: () => pad.onPick(dir),
    }, [directionIcon(dir)])));
}

export class Tray {
    /**
     * @param {HTMLElement} root `#pe-tray`
     * @param {import('../cards/CardView.js').Env} env
     */
    constructor(root, env) {
        this.root = root;
        this.env = env;
        root.classList.add('pe-tray');
        root.setAttribute('aria-live', 'polite');
        /** 節の置き場 */
        this.sectionsBox = el('div', { id: 'pe-tray-sections', class: 'pe-tray-sections' });
        this.sectionsBox.hidden = true;
        /** 一覧（捨て札）の置き場 */
        this.listBox = el('div', { id: 'pe-tray-list', class: 'pe-tray-list' });
        this.listBox.hidden = true;
        root.append(this.sectionsBox, this.listBox);
        this.sync();
    }

    /** @returns {boolean} 一覧を開いているか */
    get listOpen() {
        return !this.listBox.hidden;
    }

    /**
     * 節を置き換えて出す（開いているときは中身だけ替える。盤面は動かさず、スクロールもしない）。
     * @param {TraySection[]} sections
     */
    show(sections) {
        clear(this.sectionsBox);
        for (const section of sections) {
            const body = Array.isArray(section.body) ? section.body : [section.body];
            const nodes = body.filter((n) => n !== null && n !== undefined && n !== false);
            if (nodes.length === 0 && section.label === undefined) {
                continue;
            }
            this.sectionsBox.append(el('section', { class: 'pe-tray-sec' }, [
                section.label === undefined ? null : el('div', { class: 'pe-tray-head' }, [el('span', { class: 'pe-rail-label' }, [section.label])]),
                ...nodes,
            ]));
        }
        this.sectionsBox.hidden = this.sectionsBox.childElementCount === 0;
        this.sync();
    }

    /** 節を閉じる（捨て札の一覧は残す） */
    hide() {
        clear(this.sectionsBox);
        this.sectionsBox.hidden = true;
        this.sync();
    }

    /**
     * 一覧を開く（または中身を置き換える）。ラベルの行の右端に BGA 標準の [Close]（secondary）を置く。
     * @param {{ title: string, body: Node, onClose: () => void }} content
     */
    showList(content) {
        clear(this.listBox);
        const head = el('div', { class: 'pe-tray-head' }, [el('span', { class: 'pe-rail-label' }, [content.title])]);
        this.listBox.append(head, content.body);
        this.listBox.hidden = false;
        this.env.addButton(_('Close'), content.onClose, { color: 'secondary', destination: head });
        this.sync();
    }

    hideList() {
        clear(this.listBox);
        this.listBox.hidden = true;
        this.sync();
    }

    /** トレイは中身があるときだけ出す */
    sync() {
        this.root.hidden = this.sectionsBox.hidden && this.listBox.hidden;
    }
}
