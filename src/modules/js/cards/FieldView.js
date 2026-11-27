/**
 * 場（`#pe-supply`。仕様書 014 §4.3）: 褒賞カード 2 枚、プロジェクトカードの山札（枚数）と捨て札（一番上の 1 枚）。
 * 枠・見出しの箱は無く、各スロットの上に 12 px のラベル（REWARDS・DECK・DISCARD）を置く。捨て札を押すと、一覧がトレイに開く（GameView が差す）。
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
        root.classList.add('pe-supply');
        this.rewardsBox = el('div', { class: 'pe-rewards' });
        this.deckBox = el('div', { class: 'pe-deck' });
        this.discardBox = el('button', { type: 'button', class: 'pe-discard', 'aria-label': _('Discard pile'), onClick: () => this.onToggleDiscard() });
        /** @type {() => void} 捨て札を押したとき（トレイに一覧を開く / 閉じる。GameView が差す） */
        this.onToggleDiscard = () => {};
        const slot = (/** @type {string} */ label, /** @type {HTMLElement} */ body, /** @type {string} */ cls) => el('div', { class: `pe-supply-slot ${cls}`, role: 'group', 'aria-label': label }, [
            el('div', { class: 'pe-rail-label', 'aria-hidden': 'true' }, [label]),
            body,
        ]);
        root.append(
            slot(_('Rewards'), this.rewardsBox, 'pe-supply-rewards'),
            slot(_('Deck'), this.deckBox, 'pe-supply-deck'),
            slot(_('Discard'), this.discardBox, 'pe-supply-discard'),
        );
        /** @type {number[]} */
        this.discard = [];
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

    /** @returns {HTMLElement} 捨て札の全一覧（公開情報。新しい順。トレイに出す。012 §4.7、014 §4.3） */
    discardList() {
        if (this.discard.length === 0) {
            return el('p', { class: 'pe-tray-empty' }, [_('Empty')]);
        }
        const list = el('div', { class: 'pe-tray-cards' });
        for (const id of [...this.discard].reverse()) {
            const card = createCard(this.env, projectKey(id), { classes: ['pe-hand-card'] });
            this.tooltip.bind(card, projectKey(id));
            list.append(card);
        }
        return list;
    }
}

/**
 * ミッション 2 枚。本人は表向き、他プレイヤー・観戦者は裏向き（MREV）2 枚（012 §4.7）。終了時は全員を表向きにして呼ぶ（mine に番号を渡す）。
 * @param {import('./CardView.js').Env} env
 * @param {import('./Tooltip.js').Tooltip} tooltip
 * @param {HTMLElement} root
 * @param {number[] | null} mine 本人のミッション番号。他プレイヤーは null
 * @param {number} count 枚数（他プレイヤーの裏面の数）
 * @param {Map<number, number> | null} [frames] 枠を付けるカード（ミッション番号 → スロット 0 / 1）。達成したカードだけ
 */
export function renderMissions(env, tooltip, root, mine, count, frames = null) {
    root.classList.add('pe-missions');
    root.replaceChildren();
    if (mine !== null && mine.length > 0) {
        for (const no of mine) {
            const card = createCard(env, missionKey(no), { classes: ['pe-mission-card'] });
            tooltip.bind(card, missionKey(no));
            const slot = frames?.get(no);
            if (slot !== undefined) {
                // 盤面の達成区域の枠と同じ色・線種で囲み、左上にスロット番号の札を付ける（012 追補 2026-11-14 C）
                card.classList.add('pe-mission-framed', `pe-mission-slot-${slot}`);
                card.append(el('span', { class: 'pe-mission-slotno', 'aria-hidden': 'true' }, [String(slot + 1)]));
            }
            root.append(card);
        }
        return;
    }
    for (let i = 0; i < count; i++) {
        root.append(createCardBack(env, 'MREV', ['pe-mission-card']));
    }
}
