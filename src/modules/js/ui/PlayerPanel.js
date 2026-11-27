/**
 * プレイヤーパネルのカウンター（仕様書 012 §5）。BGA 標準のパネル（名前・色・時計）の下に足す。
 * 1 行に、マヤ・予算・手札の枚数・キューブの残り・通信確立した地域の数・このゲームで使う 2 枚の褒賞の比較値・基準プレイヤーの「1」（四角い札）と、そのプレイヤーの領域へ移るボタンを右端に（014 §8）。確定の ✓ と待機の回転は廃止（BGA 標準のアクティブ表示に任せる）。
 * 名声は、ゲームオプション 105 が Visible のときだけ出す（ミッションを除く途中の名声。ホバーで内訳）。既定では出さない（001 §12。終了時に計算）。数値の部品は PlayerStats.js。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { COLOR_LETTERS } from '../cards/sprite-layout.js';
import { el } from './dom.js';
import { buildStats, CUBES_TOTAL } from './PlayerStats.js';
import { format } from '../states/logic/common.js';

export { CUBES_TOTAL };

/** 領域へ移るボタンの矢印（下向き。SVG） */
const JUMP_ICON = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 4v14M6 13l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.4"/></svg>';

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
        /** @type {{ total: number, detail: Record<string, any> } | null} */
        this.fame = null;
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
     * @param {{ total: number, detail: Record<string, any> } | null} fame 途中の名声（ゲームオプション 105 が Visible のときだけ）
     */
    setFame(fame) {
        this.fame = fame;
        this.render();
    }

    render() {
        const p = this.player;
        const stats = buildStats(this.env, p, this.rewards, this.fame);
        // 領域へ移るボタン（32 × 32 px）。カウンターと同じ行の右端。アニメーションが無効なら即座に移る
        const jump = el('button', {
            type: 'button',
            class: 'pe-panel-jump',
            'aria-label': format(_('Go to ${player_name}\'s area'), { player_name: p.name }),
            onClick: () => document.getElementById(`pe-player-${p.id}`)?.scrollIntoView({ block: 'start', behavior: this.env.animationsActive() ? 'smooth' : 'auto' }),
        });
        jump.innerHTML = JUMP_ICON;
        const marks = el('span', { class: 'pe-panel-marks' }, [
            this.isBase ? el('span', { class: 'pe-base-badge', role: 'img', 'aria-label': _('Base player (first in turn order)'), title: _('Base player (first in turn order)') }, ['1']) : null,
            jump,
        ]);
        this.root.replaceChildren(el('div', { class: 'pe-panel-row' }, [...stats, marks]));
    }
}
