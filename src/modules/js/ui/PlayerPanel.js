/**
 * プレイヤーパネルのカウンター（仕様書 012 §5）。BGA 標準のパネル（名前・色・時計）の下に足す。
 * マヤ・予算・手札の枚数・稼働中の衛星「n / 5」・置いたキューブ「n / 16」・このゲームで使う 2 枚の褒賞の比較値・確定の印・基準プレイヤーの「1」バッジ。
 * 名声は表示しない（001 §12。終了時に計算）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { applySprite, cardSprite, COLOR_LETTERS, pieceSprite } from '../cards/sprite-layout.js';
import { cardInfo } from '../cards/card-text.js';
import { el } from './dom.js';

/** キューブの総数（001） */
export const CUBES_TOTAL = 16;
/** 衛星の数（1 人 5 機） */
export const SATELLITES_TOTAL = 5;

export class PlayerPanel {
    /**
     * @param {import('../cards/CardView.js').Env} env
     * @param {HTMLElement} host BGA のプレイヤーパネル（`bga.playerPanels.getElement`）
     * @param {import('./normalize.js').PlayerModel} player
     * @param {string[]} rewards 褒賞の 2 面
     */
    constructor(env, host, player, rewards) {
        this.env = env;
        this.player = player;
        this.rewards = rewards;
        this.root = el('div', { class: 'pe-panel', dataset: { player: player.id, color: COLOR_LETTERS[player.colorIndex] } });
        host.append(this.root);
        this.isBase = false;
        this.waiting = false;
        this.render();
    }

    /**
     * @param {Partial<import('./normalize.js').PlayerModel>} patch
     */
    update(patch) {
        Object.assign(this.player, patch);
        this.render();
    }

    /**
     * @param {boolean} isBase 基準プレイヤー（席順 1 番手。「1」バッジ）
     */
    setBase(isBase) {
        this.isBase = isBase;
        this.render();
    }

    /**
     * 待機表示: 他のプレイヤーが選択中のとき、待っている人に小さな回転アイコン（確定済みはチェック）。
     * @param {boolean} waiting
     */
    setWaiting(waiting) {
        this.waiting = waiting;
        this.render();
    }

    /**
     * アイコン付きのカウンター 1 つ。
     * @param {string} cls
     * @param {string} title
     * @param {HTMLElement} icon
     * @param {string | number} value
     * @returns {HTMLElement}
     */
    counter(cls, title, icon, value) {
        return el('span', { class: `pe-counter ${cls}`, title }, [icon, el('span', { class: 'pe-counter-value' }, [String(value)])]);
    }

    /**
     * @param {string} name コマ・アイコンの名前
     * @param {string} cls
     * @returns {HTMLElement}
     */
    pieceIcon(name, cls) {
        const icon = el('span', { class: `pe-icon ${cls}`, 'aria-hidden': 'true' });
        applySprite(icon, pieceSprite(name), this.env.imgUrl);
        return icon;
    }

    render() {
        const p = this.player;
        const letter = COLOR_LETTERS[p.colorIndex] ?? 'b';
        const active = p.satellites.filter((s) => s.cell !== null).length;
        const rewardCounters = this.rewards.map((key) => {
            const info = cardInfo(key);
            const icon = el('span', { class: 'pe-icon pe-icon-reward', 'aria-hidden': 'true' });
            applySprite(icon, cardSprite(key, 'base'), this.env.imgUrl);
            const value = p.rewardValues[key];
            const name = info?.name ?? key;
            const compare = /** @type {Record<string, any>} */ (/** @type {unknown} */ (info))?.compare ?? '';
            return this.counter('pe-counter-reward', `${name}: ${compare}`, icon, value === undefined ? '–' : value);
        });
        const mark = el('span', { class: 'pe-panel-marks' }, [
            this.isBase ? el('span', { class: 'pe-base-badge', title: _('Base player (first in turn order)') }, ['1']) : null,
            p.confirmed ? el('span', { class: 'pe-check', title: _('Confirmed'), 'aria-label': _('Confirmed') }, ['✓']) : null,
            this.waiting && !p.confirmed ? el('span', { class: 'pe-waiting', title: _('Choosing…'), 'aria-label': _('Choosing…') }) : null,
        ]);
        this.root.replaceChildren(
            el('div', { class: 'pe-panel-row' }, [
                el('span', { class: 'pe-color-dot', 'aria-hidden': 'true' }),
                this.counter('pe-counter-maya', _('Maya'), this.pieceIcon('maya_1', 'pe-icon-maya'), p.maya),
                this.counter('pe-counter-budget', _('Budget'), this.pieceIcon(`disk_${letter}`, 'pe-icon-disk'), p.budget),
                this.counter('pe-counter-hand', _('Cards in hand'), el('span', { class: 'pe-icon pe-icon-cardback', 'aria-hidden': 'true' }), p.handCount),
                mark,
            ]),
            el('div', { class: 'pe-panel-row' }, [
                this.counter('pe-counter-sats', _('Active satellites'), this.pieceIcon(`satellite_hammer_${letter}`, 'pe-icon-sat'), `${active} / ${SATELLITES_TOTAL}`),
                this.counter('pe-counter-cubes', _('Cubes placed'), el('span', { class: 'pe-icon pe-icon-cube', dataset: { color: letter }, 'aria-hidden': 'true' }), `${CUBES_TOTAL - p.cubesLeft} / ${CUBES_TOTAL}`),
                ...rewardCounters,
            ]),
        );
    }
}
