/**
 * プレイヤーごとの領域（仕様書 014 §2.3・§2.4）。
 * 自分: レール + 名前 → 行 A（アクションカード 5 枚 + ミッション 2 枚。隣り合わせで中央寄せ）→ 行 B（ロケット 5 機。下端をそろえる）→ 行 C（手札。[Sell cards] は右端）。
 * 他プレイヤー（観戦者は全員）: 1 行目 = レール + 名前 + アクションカード 5 枚（小）+ ミッション裏 2 枚（小）、2 行目 = 小さいロケット 5 機（手札の欄は無い。枚数はパネル）。
 * 数値・印はプレイヤーパネルにだけ出す（012 追補 U-5）。地域 A〜H の列は出さない（パネルの n/8。014 §4.4）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { seatMark } from './marks.js';
import { SatelliteView } from '../cards/SatelliteView.js';
import { ActionRow, HandView } from '../cards/HandView.js';
import { renderMissions } from '../cards/FieldView.js';
import { COLOR_LETTERS } from '../cards/sprite-layout.js';
import { el } from './dom.js';

export class PlayerArea {
    /**
     * @param {import('../cards/CardView.js').Env} env
     * @param {import('./Tips.js').Tips} tips
     * @param {import('./normalize.js').PlayerModel} player
     * @param {{ mine: boolean }} opts
     */
    constructor(env, tips, player, opts) {
        this.env = env;
        this.tips = tips;
        this.player = player;
        this.mine = opts.mine;
        /** @type {Map<number, SatelliteView>} */
        this.sats = new Map();
        /** @type {number[]} 本人のミッション番号（観戦者・他人は空） */
        this.lastMissions = [];
        /** @type {number[] | null} 終了時に公開された全員のミッション（表向きにする。ゲーム中は null） */
        this.revealed = null;
        /** @type {Map<number, number> | null} 枠を付けるミッション（番号 → スロット）。得点表で選ばれたプレイヤーだけ */
        this.frames = null;
        this.root = el('section', {
            id: `pe-player-${player.id}`,
            class: `pe-player${opts.mine ? ' pe-player-mine' : ' pe-player-other'}`,
            dataset: { player: player.id, color: COLOR_LETTERS[player.colorIndex] },
        });
        this.title = el('span', { class: 'pe-player-name' }, [seatMark(player.order, 'pe-mark-name'), player.name]);
        this.satsBox = el('div', { class: 'pe-sats' });
        this.actionsBox = el('div');
        this.missionsBox = el('div');
        this.handBox = el('div');
        /** 手札のレールの右端のボタンの置き場（[Sell cards]。BGA の addActionButton の destination） */
        this.handTools = el('div', { class: 'pe-hand-tools' });
        this.actions = new ActionRow(env, tips, this.actionsBox, { mine: opts.mine });
        this.hand = new HandView(env, tips, this.handBox);
        /**
         * @param {string} label
         * @param {HTMLElement} body
         * @param {string} cls
         * @returns {HTMLElement}
         */
        const group = (label, body, cls) => el('div', { class: `pe-player-group ${cls}`, role: 'group', 'aria-label': label }, [
            opts.mine ? el('div', { class: 'pe-rail-label' }, [label]) : null,
            body,
        ]);
        this.actionsGroup = group(_('Action cards'), this.actionsBox, 'pe-group-actions');
        const missionsGroup = group(_('Missions'), this.missionsBox, 'pe-group-missions');
        const head = el('header', { class: 'pe-player-head' }, [this.title]);
        if (opts.mine) {
            this.root.append(
                head,
                el('div', { class: 'pe-row-a' }, [this.actionsGroup, missionsGroup]),
                this.satsBox,
                el('div', { class: 'pe-row-c pe-player-handgroup', role: 'group', 'aria-label': _('Hand') }, [
                    el('div', { class: 'pe-hand-head' }, [el('span', { class: 'pe-rail-label' }, [_('Hand')]), this.handTools]),
                    this.handBox,
                ]),
            );
        } else {
            // 他プレイヤー: 名前 + アクションカード + ミッション裏を 1 行に、その下に小さいロケット（手札の欄は無い）
            this.root.append(el('div', { class: 'pe-other-top' }, [head, this.actionsGroup, missionsGroup]), this.satsBox);
        }
        this.refresh();
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
     * 問いの対象の衛星を強調する（効果の窓）。null で全部消す。
     * @param {Set<number> | null} satIds
     */
    setSubjects(satIds) {
        for (const [satId, view] of this.sats) {
            view.setSubject(satIds?.has(satId) === true);
        }
    }

    /**
     * 問いの発生源の配備カードを強調する（効果の窓。全衛星に渡し、持っている衛星だけに出る）。null で全部消す。
     * @param {number | null} card
     */
    setSubjectCard(card) {
        for (const view of this.sats.values()) {
            view.setSubjectCard(card);
        }
    }

    /**
     * 配備カードを押して選べるようにする（全衛星に同じ map を渡す。衛星に無いカードは無視される）。null で解除。
     * @param {Map<number, 'selectable' | 'selected'> | null} cards
     * @param {(card: number) => void} [onPick]
     */
    setCardPicks(cards, onPick = () => {}) {
        for (const view of this.sats.values()) {
            view.setCardPicks(cards === null ? null : { cards, onPick });
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

    /** モデル（player）の内容で全体を描き直す。衛星は既存の SatelliteView を更新する */
    refresh() {
        const p = this.player;
        const seen = new Set();
        for (const s of p.satellites) {
            seen.add(s.satId);
            const existing = this.sats.get(s.satId);
            if (existing === undefined) {
                const view = new SatelliteView(this.env, this.tips, s, { mine: this.mine, ownerName: p.name });
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
     * 手札・ミッション。手札は本人だけ（ID の並び）。
     * @param {{ hand?: number[], missions?: number[] }} [mine] 本人の手札とミッション
     */
    refreshHand(mine) {
        if (this.mine) {
            if (mine?.hand !== undefined) {
                this.hand.setCards(mine.hand);
            }
            if (mine?.missions !== undefined) {
                this.lastMissions = mine.missions;
            }
        }
        this.renderMissionCards();
    }

    /** ミッションカード。終了時は全員を表向きにし、枠（盤面の達成区域と同じ色）を付ける。ゲーム中は本人だけ表向き */
    renderMissionCards() {
        const p = this.player;
        const faces = this.revealed ?? (this.mine ? this.lastMissions : null);
        renderMissions(this.env, this.tips, this.missionsBox, faces, p.missionCount || 2, this.frames);
    }

    /**
     * 終了時のミッションカードの公開と枠（012 追補 2026-11-14 C）。
     * @param {number[] | null} revealed 公開するミッション番号（null = 公開しない）
     * @param {Map<number, number> | null} frames 枠を付けるミッション（番号 → スロット）
     */
    setMissionView(revealed, frames) {
        this.revealed = revealed;
        this.frames = frames;
        this.renderMissionCards();
    }
}
