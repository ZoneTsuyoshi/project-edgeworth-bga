/**
 * 11 SetupLaunchPadPlace（1 人ずつ）と 22 PlanningPlot（全員同時）の操作 UI。どちらも売却（[Sell cards]）ができる。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { CARD_INFO } from '../cards/card-info.js';
import { ActionState } from './ActionState.js';
import { format } from './logic/common.js';

/**
 * 11 SetupLaunchPadPlace（ACTIVE_PLAYER）: 空いている陸系の区域（args.vacantCells）を選んで [Place launch base] で置く。
 */
export class SetupLaunchPadPlace extends ActionState {
    /**
     * @param {any} game
     * @param {Bga} bga
     */
    constructor(game, bga) {
        super(game, bga);
        this.solo = true;
        /** @type {number | null} */
        this.cell = null;
    }

    reset() {
        this.cell = null;
    }

    renderEdit() {
        const view = this.game.view;
        const cells = Array.isArray(this.args.vacantCells) ? this.args.vacantCells.map(Number) : [];
        if (view === null) {
            return;
        }
        view.stateFrame.selectCells(cells.map((cell) => ({ cell, kind: cell === this.cell ? 'selected' : 'selectable' })), (cell) => {
            this.cell = cell;
            this.refresh();
        });
        this.bga.statusBar.setTitle(this.cell === null ? _('${you} must place your launch base: choose a highlighted area') : format(_('${you} will place your launch base in area ${cell}'), { cell: this.cell }));
        if (this.cell !== null) {
            const cell = this.cell;
            this.bga.statusBar.addActionButton(_('Place launch base'), () => this.send('actPlaceLaunchBase', { cell }, { cell }), { color: 'primary' });
        }
    }
}

/**
 * 22 PlanningPlot（MULTIPLE_ACTIVE）: 選べるアクション（`_private.selectable`）から 1 枚選び、[Confirm] で確定する。確定後も選び直せる（再確定。[Change my choice]）。
 * 自分の領域の行 A のアクションカードをそのまま押して選ぶ（選べる = pe-can、横向き = pe-off、選んだ = pe-picked。確定後は選んだ札に Planned。014 §2.3）。
 */
export class PlanningPlot extends ActionState {
    constructor(/** @type {any} */ game, /** @type {Bga} */ bga) {
        super(game, bga);
        /** @type {number | null} */
        this.no = null;
    }

    reset() {
        this.no = null;
    }

    /** @param {Record<string, any> | null} choice */
    restore(choice) {
        this.no = choice?.action === undefined ? null : Number(choice.action);
    }

    /** @param {Record<string, any>} choice */
    summarize(choice) {
        const info = /** @type {Record<string, { numeral: string, name: string }>} */ (/** @type {unknown} */ (CARD_INFO.actions))[`A${String(choice.action).padStart(3, '0')}`];
        return info === undefined ? '' : `${info.numeral} ${info.name}`;
    }

    renderConfirmed() {
        const view = this.game.view;
        if (view !== null && view.m.me !== null) {
            // 確定済み: 自分の行 A の選んだ札に Planned の印（他の人には裏向きの 1 枚が場に出る）
            view.areas.get(view.m.me)?.actions.setPlot(true, Number(this.confirmed?.action ?? null));
        }
        super.renderConfirmed();
    }

    renderEdit() {
        const view = this.game.view;
        const me = view?.m.me ?? null;
        const row = me === null ? undefined : view?.areas.get(me)?.actions;
        if (row === undefined || this.mine === null) {
            return;
        }
        const confirmedNo = this.confirmed?.action === undefined ? null : Number(this.confirmed.action);
        row.setPlot(this.confirmed !== null, confirmedNo);
        row.chosen = this.no;
        row.setSelectable((this.mine.selectable ?? []).map(Number), (no) => {
            this.no = no;
            this.refresh();
        });
        this.bga.statusBar.setTitle(this.no === null ? _('${you} must choose an Action Card') : _('${you} chose an Action Card. Confirm it'));
        if (this.no !== null) {
            const no = this.no;
            this.bga.statusBar.addActionButton(this.confirmed === null ? _('Confirm') : _('Confirm the new choice'), () => this.send('actConfirmPlot', { actionNo: no }, { action: no }), { color: 'primary' });
        }
    }
}
