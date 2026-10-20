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

/**
 * 11 SetupLaunchPadPlace（ACTIVE_PLAYER）: 空いている陸系の区域（args.vacantCells）を選んで発射拠点を置く。
 */
export class SetupLaunchPadPlace extends ChoiceState {
    /**
     * @param {import('../ui/GameView.js').GameView} view
     * @param {Record<string, any>} args
     * @param {boolean} isActive
     */
    enter(view, args, isActive) {
        if (!isActive || !Array.isArray(args.vacantCells)) {
            return;
        }
        view.stateFrame.selectCells(
            args.vacantCells.map((/** @type {unknown} */ c) => ({ cell: Number(c), kind: 'selectable' })),
            (cell) => {
                view.stateFrame.markSelected(cell);
                this.bga.statusBar.removeActionButtons();
                this.bga.statusBar.addActionButton(_('Place launch base'), () => this.bga.actions.performAction('actPlaceLaunchBase', { cell }));
            },
        );
    }
}

/**
 * 22 PlanningPlot: 選べるアクション（`_private.selectable`）から 1 枚選び、[Confirm] で確定する。確定後も選び直せる（再確定）。
 */
export class PlanningPlot extends ChoiceState {
    /**
     * @param {import('../ui/GameView.js').GameView} view
     * @param {Record<string, any>} args
     * @param {boolean} isActive
     */
    enter(view, args, isActive) {
        const me = view.m.me;
        const mine = args._private;
        if (me === null || mine === undefined || mine === null) {
            return;
        }
        const row = view.areas.get(me)?.actions;
        if (row === undefined) {
            return;
        }
        const chosenBefore = mine.myChoice?.action ?? null;
        row.setPlot(chosenBefore !== null || view.m.players[me].confirmed, chosenBefore);
        if (!isActive && chosenBefore === null) {
            return;
        }
        row.setSelectable((mine.selectable ?? []).map(Number), (no) => {
            this.bga.statusBar.removeActionButtons();
            if (no !== null) {
                this.bga.statusBar.addActionButton(chosenBefore === null ? _('Confirm') : _('Change my choice'), () => this.bga.actions.performAction('actConfirmPlot', { actionNo: no }));
            }
        });
    }
}
