/**
 * プレイヤーパネルのカウンター（仕様書 012 §5）。BGA 標準のパネル（名前・色・時計）の下に足す。
 * マヤ・予算・手札の枚数・稼働中の衛星「n / 5」・置いたキューブ「n / 16」・このゲームで使う 2 枚の褒賞の比較値・確定の印・基準プレイヤーの「1」バッジ。
 * 名声は、ゲームオプション 105 が Visible のときだけ出す（ミッションを除く途中の名声。ホバーで内訳）。既定では出さない（001 §12。終了時に計算）。数値の部品は PlayerStats.js（自分の領域の見出しと共通）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { COLOR_LETTERS } from '../cards/sprite-layout.js';
import { el } from './dom.js';
import { buildStats, CUBES_TOTAL, SATELLITES_TOTAL } from './PlayerStats.js';

export { CUBES_TOTAL, SATELLITES_TOTAL };

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
        /** @type {{ total: number, detail: Record<string, any> } | null} */
        this.fame = null;
        /** @type {Set<() => void>} 描き直したときに呼ぶ（自分の領域の見出しの数値を同期する） */
        this.listeners = new Set();
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
     * @param {{ total: number, detail: Record<string, any> } | null} fame 途中の名声（ゲームオプション 105 が Visible のときだけ）
     */
    setFame(fame) {
        this.fame = fame;
        this.render();
    }

    render() {
        const p = this.player;
        const stats = buildStats(this.env, p, this.rewards, this.fame);
        const mark = el('span', { class: 'pe-panel-marks' }, [
            this.isBase ? el('span', { class: 'pe-base-badge', title: _('Base player (first in turn order)') }, ['1']) : null,
            p.confirmed ? el('span', { class: 'pe-check', title: _('Confirmed'), 'aria-label': _('Confirmed') }, ['✓']) : null,
            this.waiting && !p.confirmed ? el('span', { class: 'pe-waiting', title: _('Choosing…'), 'aria-label': _('Choosing…') }) : null,
        ]);
        this.root.replaceChildren(
            el('div', { class: 'pe-panel-row' }, [el('span', { class: 'pe-color-dot', 'aria-hidden': 'true' }), ...stats.first, mark]),
            el('div', { class: 'pe-panel-row' }, stats.second),
        );
        for (const listener of this.listeners) {
            listener();
        }
    }
}
