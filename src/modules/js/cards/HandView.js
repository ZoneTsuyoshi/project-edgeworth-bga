/**
 * 手札（プロジェクトカード）とアクションカード 5 枚（仕様書 012 §4.4、§12）。
 * 手札は本人のみ ID の並び（他プレイヤーの枚数はプレイヤーパネルだけ。012 追補 U-5）。アクションカードは全員の状態が公開（010 §6.1）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { actionKey, projectKey } from './sprite-layout.js';
import { createCard, createCardBack } from './CardView.js';
import { cardInfo } from './card-text.js';
import { el } from '../ui/dom.js';

/**
 * 選択できるカードの集まり。クリックで選択を切り替える（単一 / 複数）。
 */
class Selectable {
    constructor() {
        /** @type {Set<number> | null} 選べる ID。null なら選択モードでない */
        this.allowed = null;
        this.multi = false;
        /** @type {Set<number>} */
        this.chosen = new Set();
        /** @type {(ids: number[]) => void} */
        this.onChange = () => {};
        /** @type {Map<number, string>} 押せないカードの理由 */
        this.reasons = new Map();
    }
}

export class HandView {
    /**
     * @param {import('./CardView.js').Env} env
     * @param {import('./Tooltip.js').Tooltip} tooltip
     * @param {HTMLElement} root
     */
    constructor(env, tooltip, root) {
        this.env = env;
        this.tooltip = tooltip;
        this.root = root;
        root.classList.add('pe-hand');
        /** @type {number[]} */
        this.ids = [];
        this.count = 0;
        this.sel = new Selectable();
        /** @type {Map<number, string>} 予定のカード（確定済みの内容が使うカード）→ 札の文言 */
        this.planned = new Map();
    }

    /**
     * 確定済みの内容が使う手札に「予定」の印を付ける（本人の画面だけ）。null で外す。
     * @param {Map<number, string> | null} planned
     */
    setPlanned(planned) {
        this.planned = planned ?? new Map();
        this.render();
    }

    /**
     * 本人の手札（ID の並び）を描く。
     * @param {number[]} ids
     */
    setCards(ids) {
        this.ids = [...ids];
        this.count = ids.length;
        this.sel.chosen = new Set([...this.sel.chosen].filter((id) => this.ids.includes(id)));
        this.render();
    }

    /**
     * 選択モードにする。ids が null で解除。
     * @param {number[] | null} ids 選べるカード
     * @param {{ multi?: boolean, onChange?: (ids: number[]) => void, reasons?: Map<number, string> }} [opts] reasons = 押せないカードの理由（title）
     */
    setSelectable(ids, opts = {}) {
        this.sel.allowed = ids === null ? null : new Set(ids);
        this.sel.reasons = opts.reasons ?? new Map();
        this.sel.multi = opts.multi === true;
        this.sel.onChange = opts.onChange ?? (() => {});
        if (ids === null) {
            this.sel.chosen.clear();
        }
        this.render();
    }

    /**
     * 選択中のカードを置き換える（選択モードのまま描き直す）。
     * @param {number[]} ids
     */
    setChosen(ids) {
        this.sel.chosen = new Set(ids.filter((id) => this.ids.includes(id)));
        this.render();
    }

    /** @returns {number[]} */
    selected() {
        return [...this.sel.chosen];
    }

    render() {
        this.root.replaceChildren();
        if (this.ids.length === 0) {
            this.root.append(el('span', { class: 'pe-hand-empty' }, [_('No cards in hand')]));
            return;
        }
        for (const id of this.ids) {
            const card = createCard(this.env, projectKey(id), { classes: ['pe-hand-card'] });
            card.dataset.id = String(id);
            this.tooltip.bind(card, projectKey(id));
            if (this.sel.allowed?.has(id)) {
                card.classList.add('pe-selectable');
                card.tabIndex = 0;
                card.addEventListener('click', () => this.toggle(id));
            } else if (this.sel.allowed !== null) {
                card.classList.add('pe-unselectable');
                const reason = this.sel.reasons.get(id);
                if (reason !== undefined) {
                    card.title = reason;
                }
            }
            card.classList.toggle('pe-selected', this.sel.chosen.has(id));
            const plan = this.planned.get(id);
            if (plan !== undefined) {
                card.classList.add('pe-planned');
                card.append(el('span', { class: 'pe-plan-badge' }, [plan]));
            }
            // 手札のカードの下に英語名のキャプション（2 行まで。カードの券面は日本語の印刷なので、ログのカード名と照らせるようにする。014 §2.3）
            this.root.append(el('div', { class: 'pe-card-slot' }, [card, el('span', { class: 'pe-card-name' }, [cardInfo(projectKey(id))?.name ?? projectKey(id)])]));
        }
    }

    /**
     * @param {number} id
     */
    toggle(id) {
        if (this.sel.chosen.has(id)) {
            this.sel.chosen.delete(id);
        } else {
            if (!this.sel.multi) {
                this.sel.chosen.clear();
            }
            this.sel.chosen.add(id);
        }
        this.render();
        this.sel.onChange(this.selected());
    }
}

/** アクションカードの状態（010 §6.1）: 0 = 手札、1 = 縦（今ラウンドに選んだ）、2 = 横（前ラウンドに選んだ I 以外） */
export const ACTION_HAND = 0;
export const ACTION_UPRIGHT = 1;
export const ACTION_SIDEWAYS = 2;

export class ActionRow {
    /**
     * @param {import('./CardView.js').Env} env
     * @param {import('./Tooltip.js').Tooltip} tooltip
     * @param {HTMLElement} root
     * @param {{ mine: boolean }} opts
     */
    constructor(env, tooltip, root, opts) {
        this.env = env;
        this.tooltip = tooltip;
        this.root = root;
        this.mine = opts.mine;
        root.classList.add('pe-actions');
        root.classList.toggle('pe-actions-other', !opts.mine);
        /** @type {Record<string, number>} */
        this.states = {};
        /** 確定済み（公開前）か。本人は選んだアクション番号も分かる */
        this.plot = { confirmed: false, actionNo: /** @type {number | null} */ (null) };
        /** @type {Set<number> | null} */
        this.allowed = null;
        /** @type {number | null} */
        this.chosen = null;
        /** @type {(no: number | null) => void} */
        this.onChange = () => {};
    }

    /**
     * @param {Record<string, number>} states アクション番号 → 状態
     */
    setStates(states) {
        this.states = states;
        this.render();
    }

    /**
     * @param {boolean} confirmed
     * @param {number | null} [actionNo] 本人だけ分かる選択（010 §9）
     */
    setPlot(confirmed, actionNo = null) {
        this.plot = { confirmed, actionNo: this.mine ? actionNo : null };
        this.render();
    }

    /**
     * 計画フェイズで選べるアクション（args の `_private.selectable`）。null で解除。
     * @param {number[] | null} nos
     * @param {(no: number | null) => void} [onChange]
     */
    setSelectable(nos, onChange = () => {}) {
        this.allowed = nos === null ? null : new Set(nos);
        this.onChange = onChange;
        if (nos === null) {
            this.chosen = null;
        }
        this.render();
    }

    render() {
        this.root.replaceChildren();
        for (let no = 1; no <= 5; no++) {
            const state = this.states[no] ?? ACTION_HAND;
            const sideways = state === ACTION_SIDEWAYS;
            const slot = el('div', { class: `pe-action-slot${sideways ? ' pe-action-slot-side' : ''}` });
            const card = createCard(this.env, actionKey(no), { classes: ['pe-action-card', `pe-action-state-${state}`] });
            card.dataset.no = String(no);
            if (sideways) {
                card.classList.add('pe-sideways');
                card.title = _('Used last round. Returns to your hand next round.');
            }
            this.tooltip.bind(card, actionKey(no));
            if (this.allowed?.has(no)) {
                card.classList.add('pe-selectable');
                card.tabIndex = 0;
                card.addEventListener('click', () => {
                    this.chosen = this.chosen === no ? null : no;
                    this.render();
                    this.onChange(this.chosen);
                });
            }
            card.classList.toggle('pe-selected', this.chosen === no);
            slot.append(el('div', { class: 'pe-action-box' }, [card]));
            if (this.mine) {
                // 自分のアクションカードの下に英語名のキャプション（I Budget 形。014 §2.3）
                slot.append(el('span', { class: 'pe-card-name' }, [actionCaption(no)]));
            }
            this.root.append(slot);
        }
        // 確定済み（公開前）: 場に置いたカード。本人には選んだカードが分かる（確定の ✓ は廃止。BGA 標準のアクティブ表示に任せる。014 §4.4）
        if (this.plot.confirmed) {
            const back = this.plot.actionNo === null
                ? createCardBack(this.env, 'AREV', ['pe-action-card', 'pe-action-plot'])
                : createCard(this.env, actionKey(this.plot.actionNo), { classes: ['pe-action-card', 'pe-action-plot'] });
            if (this.plot.actionNo !== null) {
                back.classList.add('pe-planned');
                back.append(el('span', { class: 'pe-plan-badge' }, [_('Planned')]));
            }
            this.root.append(el('div', { class: 'pe-action-slot pe-action-plotslot' }, [el('div', { class: 'pe-action-box' }, [back])]));
        }
    }
}

/**
 * アクションカードの英語名（`I Budget` 形）。
 * @param {number} no 1〜5
 * @returns {string}
 */
function actionCaption(no) {
    const info = /** @type {Record<string, any> | null} */ (cardInfo(actionKey(no)));
    return info === null ? String(no) : `${info.numeral} ${info.name}`;
}
