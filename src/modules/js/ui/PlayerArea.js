/**
 * プレイヤーごとの領域（仕様書 012 §2.3〜§2.5）。
 * 見出しは名前（プレイヤー色）と地域 A〜H の列だけ。数値・印はプレイヤーパネルにだけ出す（012 追補 U-5）。
 * 自分: 衛星 5 機（衛星カード・配備カード・燃料ゲージ）、アクションカード 5 枚、ミッション 2 枚（表）、手札。
 * 他プレイヤー: 衛星 5 機の要約、アクションカード 5 枚の状態、ミッションの裏面 2 枚（手札の欄は出さない。枚数はパネル）。観戦者は全員を同じ大きさで並べる。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { SatelliteView } from '../cards/SatelliteView.js';
import { ActionRow, HandView } from '../cards/HandView.js';
import { renderMissions } from '../cards/FieldView.js';
import { COLOR_LETTERS } from '../cards/sprite-layout.js';
import { el } from './dom.js';
import { regionRow } from './PlayerStats.js';

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
        /** @type {number[] | null} 終了時に公開された全員のミッション（表向きにする。ゲーム中は null） */
        this.revealed = null;
        /** @type {Map<number, number> | null} 枠を付けるミッション（番号 → スロット）。得点表で選ばれたプレイヤーだけ */
        this.frames = null;
        this.root = el('section', {
            id: `pe-player-${player.id}`,
            class: `pe-player${opts.mine ? ' pe-player-mine' : ' pe-player-other'}`,
            dataset: { player: player.id, color: COLOR_LETTERS[player.colorIndex] },
        });
        this.title = el('span', { class: 'pe-player-name' }, [player.name]);
        /** 見出しの地域 A〜H の列（通信確立済みの地域は濃く） */
        this.regionsBox = el('span', { class: 'pe-player-regions' });
        this.satsBox = el('div', { class: 'pe-sats' });
        this.actionsBox = el('div');
        this.missionsBox = el('div');
        this.handBox = el('div');
        /** 手札の見出しの行のボタンの置き場（[Sell cards]。BGA の addActionButton の destination。012 追補 U-1） */
        this.handTools = el('div', { class: 'pe-hand-tools' });
        this.actions = new ActionRow(env, tooltip, this.actionsBox, { mine: opts.mine });
        this.hand = new HandView(env, tooltip, this.handBox);
        this.actionsGroup = el('div', { class: 'pe-player-group', role: 'group', 'aria-label': _('Actions') }, [this.actionsBox]);
        // 並びは CSS（レイアウトごと）で決める。見出しの文字は出さず、各グループに aria-label を付ける（012 追補 U-4）
        this.root.append(
            el('header', { class: 'pe-player-head' }, [this.title, this.regionsBox]),
            this.satsBox,
            el('div', { class: 'pe-player-row' }, [
                this.actionsGroup,
                el('div', { class: 'pe-player-group', role: 'group', 'aria-label': _('Missions') }, [this.missionsBox]),
            ]),
        );
        if (opts.mine) {
            // 手札の上端の行は文字なしで [Sell cards] の置き場だけ（空なら隠れる）。手札の欄は自分にだけ（他プレイヤーの枚数はパネル）
            this.root.append(el('div', { class: 'pe-player-group pe-player-handgroup', role: 'group', 'aria-label': _('Hand') }, [
                el('div', { class: 'pe-hand-head' }, [this.handTools]),
                this.handBox,
            ]));
        }
        this.refresh();
    }

    /**
     * 自分のアクションカードの行を別の場所（計画フェイズの選択パネル）へ移す。null で自分の領域の元の場所へ戻す。
     * 同じ行が画面に 2 つ同時に出ないよう、要素そのものを移す。
     * @param {HTMLElement | null} container
     */
    moveActionsTo(container) {
        if (container === null) {
            if (this.actionsBox.parentElement !== this.actionsGroup) {
                this.actionsGroup.append(this.actionsBox);
            }
            return;
        }
        container.append(this.actionsBox);
    }

    /**
     * @param {boolean} detail 紙どおりの詳細表示（全機。ゲームの設定 101。012 追補 U-8）
     */
    setDetail(detail) {
        this.detail = detail;
        for (const v of this.sats.values()) {
            v.setDetail(detail);
        }
    }

    /** 見出しの地域 A〜H の列をモデルに合わせて描き直す */
    renderRegions() {
        this.regionsBox.replaceChildren(regionRow(this.env, this.player.linkedRegions));
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
        this.renderRegions();
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
        renderMissions(this.env, this.tooltip, this.missionsBox, faces, p.missionCount || 2, this.frames);
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
