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
import { buildStats } from './PlayerStats.js';

export class PlayerArea {
    /**
     * @param {import('../cards/CardView.js').Env} env
     * @param {import('../cards/Tooltip.js').Tooltip} tooltip
     * @param {import('./normalize.js').PlayerModel} player
     * @param {{ mine: boolean, rewards?: string[], onToggleDetail?: () => void }} opts
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
        /** 見出しの行の数値（マヤ・予算・手札・稼働中の衛星・通信確立数・褒賞の比較値・名声）。プレイヤーパネルと同じ内容 */
        this.statsBox = el('span', { class: 'pe-player-stats' });
        /** @type {string[]} 褒賞の 2 面 */
        this.rewards = opts.rewards ?? [];
        /** @type {{ total: number, detail: Record<string, any> } | null} 途中の名声（ゲームオプション 105） */
        this.fame = null;
        /** 詳細 / 簡略の切り替え（作者回答 2026-10-20: プレイヤーの領域の見出しにだけ置く。どの領域で押しても全員の衛星をまとめて切り替える） */
        this.detailButton = el('button', {
            type: 'button',
            class: 'pe-btn pe-btn-small pe-detail-toggle',
            'aria-pressed': 'false',
            title: _('Show all satellites with deployed cards stacked as on the paper cards'),
            onClick: () => opts.onToggleDetail?.(),
        }, [_('Detail view')]);
        this.satsBox = el('div', { class: 'pe-sats' });
        this.actionsBox = el('div');
        this.missionsBox = el('div');
        this.handBox = el('div');
        this.actions = new ActionRow(env, tooltip, this.actionsBox, { mine: opts.mine });
        this.hand = new HandView(env, tooltip, this.handBox);
        this.root.append(
            el('header', { class: 'pe-player-head' }, [el('span', { class: 'pe-color-dot', 'aria-hidden': 'true' }), this.title, this.marks, this.statsBox, this.detailButton]),
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
        this.detailButton.setAttribute('aria-pressed', detail ? 'true' : 'false');
        this.detailButton.textContent = detail ? _('Simple view') : _('Detail view');
        for (const v of this.sats.values()) {
            v.setDetail(detail);
        }
    }

    /** 見出しの行の数値をモデルに合わせて描き直す */
    renderStats() {
        const stats = buildStats(this.env, this.player, this.rewards, this.fame);
        this.statsBox.replaceChildren(...stats.first, ...stats.second);
    }

    /**
     * 途中の名声（ゲームオプション 105）を受ける。null で出さない。
     * @param {{ total: number, detail: Record<string, any> } | null} fame
     */
    setFame(fame) {
        this.fame = fame;
        this.renderStats();
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
     * 衛星の上に「予定」の札を出す（map に無い衛星は消す）。null で全部消す。
     * @param {Map<number, import('../cards/SatelliteView.js').SatellitePlan[]> | null} plans
     */
    setSatPlans(plans) {
        for (const [satId, view] of this.sats) {
            view.setPlans(plans?.get(satId) ?? []);
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
        this.renderStats();
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
