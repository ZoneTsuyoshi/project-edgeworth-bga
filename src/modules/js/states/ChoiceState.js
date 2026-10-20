/**
 * 同時選択の状態（I-07 の ChooseState の 9 状態）と、区域・アクションを選ぶ状態の UI の枠（仕様書 009、010 §13、012 §3.9）。
 * args（`participants`・`confirmed`・`_private`）から確定の印と待機表示、選べる区域・アクションのハイライトを出す。
 * 状態ごとの操作（act* の引数）の仕上げは、状態クラスをこのクラスの派生にして onEnteringState の後に足す。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

export class ChoiceState {
    /**
     * @param {{ view: import('../ui/GameView.js').GameView | null }} game
     * @param {Bga} bga
     */
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
    }

    /**
     * @param {Record<string, any>} args
     * @param {boolean} isCurrentPlayerActive
     */
    onEnteringState(args, isCurrentPlayerActive) {
        const view = this.game.view;
        if (view === null) {
            return;
        }
        view.stateFrame.enter(this.constructor.name, args, isCurrentPlayerActive);
        this.enter(view, args, isCurrentPlayerActive);
    }

    /**
     * 派生クラスが操作を足す場所。
     * @param {import('../ui/GameView.js').GameView} _view
     * @param {Record<string, any>} _args
     * @param {boolean} _isActive
     */
    enter(_view, _args, _isActive) {}

    onLeavingState() {
        this.game.view?.stateFrame.leave();
    }
}
