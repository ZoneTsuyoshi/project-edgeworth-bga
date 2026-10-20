/**
 * 手札などのカードを並べて選ぶ部品（企画の残すカード、効果の問いの CARDS、売却の同梱など。仕様書 012 §4.4）。
 * 選択は複数（max 枚まで）か単一（max = 1 のとき、選び直すと入れ替わる）。選べないカードはグレーにして理由をツールチップに出す。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { createCard } from '../cards/CardView.js';
import { cardInfo } from '../cards/card-text.js';
import { projectKey } from '../cards/sprite-layout.js';
import { el } from './dom.js';

export class CardPicker {
    /**
     * @param {import('./GameView.js').GameView} view
     * @param {{ ids: number[], selected?: number[], max?: number, disabled?: Map<number, string> | Set<number>, onChange?: (ids: number[]) => void }} opts
     */
    constructor(view, opts) {
        this.view = view;
        this.ids = [...opts.ids];
        this.selected = new Set(opts.selected ?? []);
        this.max = opts.max ?? Number.POSITIVE_INFINITY;
        this.disabled = opts.disabled ?? new Set();
        this.onChange = opts.onChange ?? (() => {});
        this.root = el('div', { class: 'pe-picker', role: 'group' });
        this.render();
    }

    /** @returns {number[]} 選択中（並びは ids の順） */
    value() {
        return this.ids.filter((id) => this.selected.has(id));
    }

    /**
     * @param {number[]} ids
     */
    setSelected(ids) {
        this.selected = new Set(ids);
        this.render();
    }

    /**
     * @param {Map<number, string> | Set<number>} disabled 選べないカード（Map なら理由つき）
     */
    setDisabled(disabled) {
        this.disabled = disabled;
        for (const id of [...this.selected]) {
            if (this.isDisabled(id)) {
                this.selected.delete(id);
            }
        }
        this.render();
    }

    /**
     * @param {number} id
     * @returns {boolean}
     */
    isDisabled(id) {
        return this.disabled.has(id);
    }

    render() {
        this.root.replaceChildren();
        if (this.ids.length === 0) {
            this.root.append(el('span', { class: 'pe-picker-empty' }, [_('No cards')]));
            return;
        }
        for (const id of this.ids) {
            const key = projectKey(id);
            const off = this.isDisabled(id);
            const card = createCard(this.view.env, key, { classes: ['pe-picker-card', off ? 'pe-unselectable' : 'pe-selectable', ...(this.selected.has(id) ? ['pe-selected'] : [])] });
            card.dataset.id = String(id);
            card.setAttribute('role', 'checkbox');
            card.setAttribute('aria-checked', this.selected.has(id) ? 'true' : 'false');
            card.setAttribute('aria-label', cardInfo(key)?.name ?? key);
            if (off) {
                const reason = this.disabled instanceof Map ? this.disabled.get(id) : undefined;
                card.title = reason ?? '';
            } else {
                card.tabIndex = 0;
                card.addEventListener('click', () => this.toggle(id));
                card.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.toggle(id);
                    }
                });
            }
            this.view.tooltip.bind(card, key);
            this.root.append(el('div', { class: 'pe-picker-slot' }, [card, el('span', { class: 'pe-picker-name' }, [cardInfo(key)?.name ?? key])]));
        }
    }

    /**
     * @param {number} id
     */
    toggle(id) {
        if (this.selected.has(id)) {
            this.selected.delete(id);
        } else if (this.max === 1) {
            this.selected = new Set([id]);
        } else if (this.selected.size < this.max) {
            this.selected.add(id);
        } else {
            return;
        }
        this.render();
        this.onChange(this.value());
    }
}
