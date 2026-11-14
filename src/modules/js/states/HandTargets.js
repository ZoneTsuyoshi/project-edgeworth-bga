/**
 * 手札を選ぶ目的（欄）を登録し、手札のゾーンを選択中の欄に割り当てる部品（仕様書 012 追補 2026-11-14 B-2）。
 * 欄 = パネルの見出し + 選んだカードの一覧（名前 + ×）。欄が複数あるときは各欄に [Choose from hand] を置く。
 * 選択中の欄だけが手札のゾーン（HandView.setSelectable / setChosen）を使い、他の欄で選んだカードは手札の上に印（setPlanned）を付けて候補から外す。
 * 判定（次に移る欄、他の欄に取られたカードを候補から外す）は logic/hand-targets.js の純関数。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { el } from '../ui/dom.js';
import {
  advanceActive, candidatesFor, pickFromHand, plannedMarks, removeCard, resolveActive, targetComplete,
} from './logic/hand-targets.js';
import { cardName } from './texts.js';

/**
 * @typedef {object} HandTarget
 * @property {string} id 欄の識別子（同じ画面で一意）
 * @property {string} label 欄の見出し
 * @property {string} badge 他の欄が選択中のとき、手札の上に出す印（例: Sell / Discard / Deploy）
 * @property {number[]} candidates 手札のゾーンで押せるカード
 * @property {number[]} selected 選んだカード
 * @property {number} [need] 必要な枚数（足りない欄が未完了）。省略は 0
 * @property {number} [max] 上限。省略は無制限
 * @property {string} [disabled] 今は使えない理由（あれば欄は選択中になれない）
 * @property {string} [title] 選択中のときのステータスバーのタイトル
 * @property {number[]} [exclude] この欄の候補にできないカード（他の目的に予約済み。印は付く）
 * @property {Map<number, string>} [reasons] 手札のゾーンで押せないカードの理由（title）
 * @property {(cardId: number) => string} [itemText] 選んだカードの一覧の行の文言（省略はカード名）。組の行など
 * @property {HTMLElement} [extra] 欄の中に足す部品（残すカードだけの小さな一覧など）
 * @property {(ids: number[]) => void} onChange 選択が変わった（HandTargets が上限と他の欄との重なりを整えた後の並び）
 */

/**
 * @typedef {object} HandTargetsOptions
 * @property {import('../cards/HandView.js').HandView} hand
 * @property {HandTarget[]} targets
 * @property {string | null | undefined} active 前回の選択中の欄
 * @property {boolean} [idleWhenComplete] 未完了の欄がなく欄が複数のときは、手札を使わない状態から始める
 * @property {(id: string | null) => void} setActive 選択中の欄を覚える（描き直しはしない）
 * @property {() => void} refresh 画面を描き直す
 */

export class HandTargets {
    /**
     * @param {HandTargetsOptions} opts
     */
    constructor(opts) {
        this.opts = opts;
        this.targets = opts.targets;
        /** @type {string | null} */
        this.active = resolveActive(this.targets, opts.active, { idleWhenComplete: opts.idleWhenComplete });
    }

    /** @returns {import('./logic/hand-targets.js').TargetState[]} */
    states() {
        return this.targets.map((t) => ({ id: t.id, candidates: t.candidates, selected: t.selected, need: t.need, max: t.max, disabled: t.disabled !== undefined }));
    }

    /**
     * 選択中の欄の、ステータスバーのタイトル（欄が未完了のとき、または欄が複数のとき。完了済みの単独の欄は元のタイトルのまま）。
     * @returns {string | null}
     */
    activeTitle() {
        const target = this.targets.find((t) => t.id === this.active);
        if (target?.title === undefined) {
            return null;
        }
        return this.targets.length > 1 || !targetComplete({ id: target.id, candidates: [], selected: target.selected, need: target.need }) ? target.title : null;
    }

    /**
     * 欄の部品（見出し + [Choose from hand] + 選んだカードの一覧）。
     * @param {string} id
     * @returns {HTMLElement}
     */
    section(id) {
        const target = this.targets.find((t) => t.id === id);
        if (target === undefined) {
            return el('div');
        }
        const active = this.active === id;
        const several = this.targets.length > 1;
        const items = target.selected.map((cardId) => el('li', { class: 'pe-handfield-item', dataset: { id: cardId } }, [
            el('span', { class: 'pe-handfield-name' }, [target.itemText?.(cardId) ?? cardName(cardId)]),
            el('button', {
                type: 'button',
                class: 'pe-btn pe-btn-small',
                title: _('Remove'),
                'aria-label': _('Remove'),
                onClick: () => this.change(id, removeCard(target.selected, cardId)),
            }, ['×']),
        ]));
        return el('div', { class: `pe-handfield${active ? ' pe-handfield-active' : ''}${target.disabled === undefined ? '' : ' pe-handfield-off'}`, dataset: { target: id } }, [
            el('div', { class: 'pe-handfield-head' }, [
                el('div', { class: 'pe-choice-label' }, [target.label]),
                several ? el('button', {
                    type: 'button',
                    class: `pe-btn pe-btn-small${active ? ' pe-btn-primary' : ''}`,
                    disabled: target.disabled !== undefined,
                    title: target.disabled,
                    'aria-pressed': active ? 'true' : 'false',
                    onClick: () => {
                        this.opts.setActive(id);
                        this.opts.refresh();
                    },
                }, [_('Choose from hand')]) : null,
            ]),
            target.disabled === undefined ? null : el('div', { class: 'pe-handfield-note' }, [target.disabled]),
            items.length === 0 ? null : el('ul', { class: 'pe-handfield-list' }, items),
            target.extra ?? null,
        ]);
    }

    /**
     * 選択を変える。上限と他の欄との重なりを整え、欄が完了したら次の未完了の欄へ移ってから onChange を呼ぶ。
     * @param {string} id
     * @param {number[]} ids
     */
    change(id, ids) {
        const target = this.targets.find((t) => t.id === id);
        if (target === undefined) {
            return;
        }
        const next = this.states().map((s) => (s.id === id ? { ...s, selected: ids } : s));
        this.opts.setActive(advanceActive(next, this.active));
        target.onChange(ids);
    }

    /**
     * 手札のゾーンに反映する（選択中の欄の候補と選択、他の欄の印）。
     */
    apply() {
        const hand = this.opts.hand;
        const states = this.states();
        /** @type {Record<string, string>} */
        const badges = {};
        for (const t of this.targets) {
            badges[t.id] = t.badge;
        }
        const marks = plannedMarks(states, this.active, badges);
        for (const t of this.targets) {
            for (const id of t.exclude ?? []) {
                if (!marks.has(id)) {
                    marks.set(id, _('In use'));
                }
            }
        }
        const target = this.targets.find((t) => t.id === this.active);
        if (target === undefined) {
            hand.setSelectable(null);
        } else {
            const excluded = new Set(target.exclude ?? []);
            const candidates = candidatesFor(states, target.id).filter((id) => !excluded.has(id));
            hand.setSelectable(candidates, {
                multi: target.max !== 1,
                reasons: target.reasons,
                onChange: (ids) => this.change(target.id, pickFromHand({ id: target.id, candidates, selected: target.selected, max: target.max }, ids, hand.ids)),
            });
            hand.setChosen(target.selected);
        }
        // 選択中の欄が選んだカードには印を付けない（選択として出る）
        if (target !== undefined) {
            for (const id of target.selected) {
                marks.delete(id);
            }
        }
        hand.setPlanned(marks);
    }

    /**
     * 手札のゾーンが画面外なら見える位置までスクロールする（スマホ）。
     */
    reveal() {
        const root = this.opts.hand.root;
        if (typeof root.getBoundingClientRect !== 'function') {
            return;
        }
        const rect = root.getBoundingClientRect();
        const height = window.innerHeight || document.documentElement.clientHeight;
        if (rect.top < 0 || rect.bottom > height) {
            root.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }
}
