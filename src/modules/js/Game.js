/**
 * PROJECT EDGEWORTH のクライアント（最小。盤面・カードの表示は I-10、仕様書 012）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/**
 * 仮の待機状態（サーバー側 States/PlaceholderWait）。I-07 で状態ごとのクラスに置き換える。
 */
class PlaceholderWait {
    /**
     * @param {Game} game
     * @param {Bga} bga
     */
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
    }

    /**
     * @param {unknown} _args
     * @param {boolean} isCurrentPlayerActive
     */
    onEnteringState(_args, isCurrentPlayerActive) {
        if (isCurrentPlayerActive) {
            this.bga.statusBar.addActionButton(_('End the game (temporary)'), () => this.bga.actions.performAction('actEndTemporarily'));
        }
    }
}

export class Game {
    /**
     * @param {Bga} bga
     */
    constructor(bga) {
        this.bga = bga;
        this.bga.states.register('PlaceholderWait', new PlaceholderWait(this, bga));
    }

    /**
     * @param {{ players: Record<string, { id: number, name: string, maya: number, budget: number }> }} gamedatas
     */
    setup(gamedatas) {
        this.bga.gameArea.getElement().insertAdjacentHTML('beforeend', '<div id="pe-placeholder" class="pe-placeholder"></div>');
        const lines = Object.values(gamedatas.players).map((p) => `${p.name}: Maya ${p.maya}, Budget ${p.budget}`);
        document.getElementById('pe-placeholder').textContent = lines.join(' / ');
    }
}
