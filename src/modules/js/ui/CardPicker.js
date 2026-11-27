/**
 * 手札などのカードを並べて選ぶ部品（企画の残すカード、効果の問いの CARDS、売却の同梱など。仕様書 012 §4.4）。
 * 選択は複数（max 枚まで）か単一（max = 1 のとき、選び直すと入れ替わる）。選べないカードは暗くして（pe-off）、押したときに理由を出す（014 §6.3）。
 * カードの行は手札と同じ大きさ + 英語名のキャプション（トレイ。014 §5）。`small` は効果の窓の小さな一覧。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { createCard } from '../cards/CardView.js';
import { cardInfo } from '../cards/card-text.js';
import { projectKey } from '../cards/sprite-layout.js';
import { format } from '../states/logic/common.js';
import { el } from './dom.js';
import { refuse } from './refuse.js';
import { tipLine } from './Tips.js';

export class CardPicker {
    /**
     * @param {import('./GameView.js').GameView} view
     * @param {{ ids: number[], selected?: number[], max?: number, disabled?: Map<number, string> | Set<number>, onChange?: (ids: number[]) => void, small?: boolean, interactive?: boolean }} opts small = 小さな一覧、interactive = false は見せるだけ（選択中でないので押しても何も出さない）
     */
    constructor(view, opts) {
        this.view = view;
        this.ids = [...opts.ids];
        this.selected = new Set(opts.selected ?? []);
        this.max = opts.max ?? Number.POSITIVE_INFINITY;
        this.disabled = opts.disabled ?? new Set();
        this.onChange = opts.onChange ?? (() => {});
        this.interactive = opts.interactive !== false;
        this.root = el('div', { class: `pe-picker${opts.small === true ? '' : ' pe-picker-hand'}`, role: 'group' });
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
            const card = createCard(this.view.env, key, { classes: ['pe-picker-card', off ? 'pe-off' : (this.interactive ? 'pe-can' : ''), ...(this.selected.has(id) ? ['pe-picked'] : [])] });
            card.dataset.id = String(id);
            card.setAttribute('role', 'checkbox');
            card.setAttribute('aria-checked', this.selected.has(id) ? 'true' : 'false');
            card.setAttribute('aria-label', cardInfo(key)?.name ?? key);
            if (off) {
                if (this.interactive) {
                    card.addEventListener('click', () => refuse(this.view.env, this.disabled instanceof Map ? this.disabled.get(id) : undefined));
                }
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
            this.view.tips.card(card, key, () => {
                const reason = off && this.disabled instanceof Map ? this.disabled.get(id) : undefined;
                return reason === undefined || reason === '' ? null : tipLine(reason, null, 'pe-tip-reason');
            });
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
            refuse(this.view.env, format(_('You can choose up to ${max} card(s)'), { max: this.max }));
            return;
        }
        this.render();
        this.onChange(this.value());
    }
}
