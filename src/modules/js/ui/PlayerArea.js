/**
 * プレイヤーごとの領域（仕様書 012 §2.3〜§2.5）。
 * 自分: 衛星 5 機（衛星カード・配備カード・燃料ゲージ）、アクションカード 5 枚、ミッション 2 枚（表）、手札。
 * 他プレイヤー: 衛星 5 機の要約、手札の枚数、アクションカード 5 枚の状態、ミッションの裏面 2 枚。観戦者は全員を同じ大きさで並べる。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { SatelliteView } from '../cards/SatelliteView.js';
import { ActionRow, HandView } from '../cards/HandView.js';
import { renderMissions } from '../cards/FieldView.js';
import { COLOR_LETTERS } from '../cards/sprite-layout.js';
import { el } from './dom.js';

export class PlayerArea {
    /**
     * @param {import('../cards/CardView.js').Env} env
     * @param {import('../cards/Tooltip.js').Tooltip} tooltip
     * @param {import('./normalize.js').PlayerModel} player
     * @param {{ mine: boolean }} opts
     */
    constructor(env, tooltip, player, opts) {
        this.env = env;
        this.tooltip = tooltip;
        this.player = player;
        this.mine = opts.mine;
        /** @type {Map<number, SatelliteView>} */
        this.sats = new Map();
        this.detail = false;
        /** @type {number[]} 本人のミッション番号（観戦者・他人は空） */
        this.lastMissions = [];
        this.root = el('section', {
            class: `pe-player${opts.mine ? ' pe-player-mine' : ' pe-player-other'}`,
            dataset: { player: player.id, color: COLOR_LETTERS[player.colorIndex] },
        });
        this.title = el('span', { class: 'pe-player-name' }, [player.name]);
        this.marks = el('span', { class: 'pe-player-marks' });
        this.satsBox = el('div', { class: 'pe-sats' });
        this.actionsBox = el('div');
        this.missionsBox = el('div');
        this.handBox = el('div');
        this.actions = new ActionRow(env, tooltip, this.actionsBox, { mine: opts.mine });
        this.hand = new HandView(env, tooltip, this.handBox);
        this.root.append(
            el('header', { class: 'pe-player-head' }, [el('span', { class: 'pe-color-dot', 'aria-hidden': 'true' }), this.title, this.marks]),
            this.satsBox,
            el('div', { class: 'pe-player-row' }, [
                el('div', { class: 'pe-player-group' }, [el('span', { class: 'pe-field-label' }, [_('Actions')]), this.actionsBox]),
                el('div', { class: 'pe-player-group' }, [el('span', { class: 'pe-field-label' }, [_('Missions')]), this.missionsBox]),
            ]),
            el('div', { class: 'pe-player-group pe-player-handgroup' }, [el('span', { class: 'pe-field-label' }, [_('Hand')]), this.handBox]),
        );
        this.refresh();
    }

    /**
     * @param {boolean} detail 紙どおりの詳細表示（全機）
     */
    setDetail(detail) {
        this.detail = detail;
        for (const v of this.sats.values()) {
            v.setDetail(detail);
        }
    }

    /**
     * 「確定」「基準プレイヤー」の印。
     * @param {{ confirmed?: boolean, isBase?: boolean, finalFame?: number | null }} marks
     */
    setMarks(marks) {
        this.marks.replaceChildren(...[
            marks.isBase ? el('span', { class: 'pe-base-badge', title: _('Base player (first in turn order)') }, ['1']) : null,
            marks.confirmed ? el('span', { class: 'pe-check', title: _('Confirmed'), 'aria-label': _('Confirmed') }, ['✓']) : null,
            marks.finalFame === undefined || marks.finalFame === null ? null : el('span', { class: 'pe-fame', title: _('Fame') }, [`${_('Fame')} ${marks.finalFame}`]),
        ].filter((n) => n !== null));
    }

    /**
     * 衛星を選べるようにする（map に無い衛星は選択の対象外）。null で解除。
     * @param {Map<number, Omit<import('../cards/SatelliteView.js').SatellitePick, 'onPick'>> | null} picks
     * @param {(satId: number) => void} [onPick]
     */
    setSatPicks(picks, onPick = () => {}) {
        for (const [satId, view] of this.sats) {
            const spec = picks?.get(satId);
            view.setPick(spec === undefined ? null : { ...spec, onPick: () => onPick(satId) });
        }
    }

    /**
     * @param {number} satId
     * @returns {SatelliteView | undefined}
     */
    satView(satId) {
        return this.sats.get(satId);
    }

    /** モデル（player）の内容で全体を描き直す。衛星は既存の SatelliteView を更新する（詳細表示の状態を保つ） */
    refresh() {
        const p = this.player;
        const seen = new Set();
        for (const s of p.satellites) {
            seen.add(s.satId);
            const existing = this.sats.get(s.satId);
            if (existing === undefined) {
                const view = new SatelliteView(this.env, this.tooltip, s, { mine: this.mine, ownerName: p.name });
                view.setDetail(this.detail);
                this.sats.set(s.satId, view);
                this.satsBox.append(view.root);
            } else {
                existing.update(s);
            }
        }
        for (const [id, v] of [...this.sats]) {
            if (!seen.has(id)) {
                v.root.remove();
                this.sats.delete(id);
            }
        }
        this.actions.setStates(p.actionCards);
        this.refreshHand();
    }

    /**
     * 手札・ミッション。本人の手札は ID の並び、他人は枚数だけ。
     * @param {{ hand?: number[], missions?: number[] }} [mine] 本人の手札とミッション
     */
    refreshHand(mine) {
        const p = this.player;
        if (this.mine) {
            if (mine?.hand !== undefined) {
                this.hand.setCards(mine.hand);
            }
            if (mine?.missions !== undefined) {
                this.lastMissions = mine.missions;
            }
            renderMissions(this.env, this.tooltip, this.missionsBox, this.lastMissions, p.missionCount || 2);
        } else {
            this.hand.setCount(p.handCount);
            renderMissions(this.env, this.tooltip, this.missionsBox, null, p.missionCount || 2);
        }
    }
}
