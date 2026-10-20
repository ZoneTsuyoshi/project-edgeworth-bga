/**
 * 場の情報（仕様書 012 §2.3、§4.7）: 褒賞カード 2 枚、プロジェクトカードの山札（枚数）と捨て札（一番上の 1 枚）、ラウンド。
 * ミッション（本人は表、他プレイヤーは裏）もここで作る。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { projectKey, missionKey } from './sprite-layout.js';
import { createBadge, createCard, createCardBack } from './CardView.js';
import { el } from '../ui/dom.js';

export class FieldView {
    /**
     * @param {import('./CardView.js').Env} env
     * @param {import('./Tooltip.js').Tooltip} tooltip
     * @param {HTMLElement} root
     */
    constructor(env, tooltip, root) {
        this.env = env;
        this.tooltip = tooltip;
        this.root = root;
        root.classList.add('pe-field');
        this.rewardsBox = el('div', { class: 'pe-rewards' });
        this.deckBox = el('div', { class: 'pe-deck', title: _('Project deck') });
        this.discardBox = el('button', { type: 'button', class: 'pe-discard', title: _('Discard pile'), onClick: () => this.openDiscard() });
        this.roundBox = el('div', { class: 'pe-round' });
        root.append(
            el('div', { class: 'pe-field-group' }, [el('span', { class: 'pe-field-label' }, [_('Rewards')]), this.rewardsBox]),
            el('div', { class: 'pe-field-group' }, [el('span', { class: 'pe-field-label' }, [_('Project deck')]), this.deckBox]),
            el('div', { class: 'pe-field-group' }, [el('span', { class: 'pe-field-label' }, [_('Discard')]), this.discardBox]),
            this.roundBox,
        );
        /** @type {number[]} */
        this.discard = [];
        this.modal = null;
    }

    /**
     * @param {string[]} rewards 褒賞の面（T001 など）
     */
    setRewards(rewards) {
        this.rewardsBox.replaceChildren(...rewards.map((key) => {
            const card = createCard(this.env, key, { classes: ['pe-reward-card'] });
            this.tooltip.bind(card, key);
            return card;
        }));
    }

    /**
     * @param {number} count
     */
    setDeck(count) {
        this.deckBox.replaceChildren(createCardBack(this.env, 'PREV', ['pe-deck-card']), createBadge(count, 'pe-deck-count'));
        this.deckBox.classList.toggle('pe-empty', count === 0);
    }

    /**
     * 捨て札（ID の並び。最後が一番上。010 §4.2）。
     * @param {number[]} ids
     */
    setDiscard(ids) {
        this.discard = [...ids];
        this.discardBox.replaceChildren();
        const top = ids[ids.length - 1];
        if (top === undefined) {
            this.discardBox.append(el('span', { class: 'pe-discard-empty' }, [_('Empty')]));
        } else {
            this.discardBox.append(createCard(this.env, projectKey(top), { classes: ['pe-discard-card'] }));
        }
        this.discardBox.append(createBadge(ids.length, 'pe-discard-count'));
    }

    /**
     * @param {number} round
     */
    setRound(round) {
        this.roundBox.textContent = round > 0 ? `${_('Round')} ${round}` : _('Setup');
    }

    /** 捨て札の全一覧（公開情報）を開く（012 §4.7） */
    openDiscard() {
        this.closeDiscard();
        const list = el('div', { class: 'pe-modal-cards' });
        for (const id of [...this.discard].reverse()) {
            const card = createCard(this.env, projectKey(id), { classes: ['pe-hand-card'] });
            this.tooltip.bind(card, projectKey(id));
            list.append(card);
        }
        this.modal = el('div', { class: 'pe-modal', role: 'dialog', 'aria-label': _('Discard pile') }, [
            el('div', { class: 'pe-modal-body' }, [
                el('div', { class: 'pe-modal-head' }, [
                    el('span', {}, [`${_('Discard pile')} (${this.discard.length})`]),
                    el('button', { type: 'button', class: 'pe-btn pe-btn-small', onClick: () => this.closeDiscard() }, [_('Close')]),
                ]),
                this.discard.length === 0 ? el('p', {}, [_('Empty')]) : list,
            ]),
        ]);
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.closeDiscard();
            }
        });
        document.body.append(this.modal);
    }

    closeDiscard() {
        this.modal?.remove();
        this.modal = null;
    }
}

/**
 * ミッション 2 枚。本人は表向き、他プレイヤー・観戦者は裏向き（MREV）2 枚（012 §4.7）。達成状況は出さない。
 * @param {import('./CardView.js').Env} env
 * @param {import('./Tooltip.js').Tooltip} tooltip
 * @param {HTMLElement} root
 * @param {number[] | null} mine 本人のミッション番号。他プレイヤーは null
 * @param {number} count 枚数（他プレイヤーの裏面の数）
 */
export function renderMissions(env, tooltip, root, mine, count) {
    root.classList.add('pe-missions');
    root.replaceChildren();
    if (mine !== null && mine.length > 0) {
        for (const no of mine) {
            const card = createCard(env, missionKey(no), { classes: ['pe-mission-card'] });
            tooltip.bind(card, missionKey(no));
            root.append(card);
        }
        return;
    }
    for (let i = 0; i < count; i++) {
        root.append(createCardBack(env, 'MREV', ['pe-mission-card']));
    }
}
