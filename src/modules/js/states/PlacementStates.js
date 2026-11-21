/**
 * 11 SetupLaunchPadPlace（1 人ずつ）と 22 PlanningPlot（全員同時）の操作 UI。どちらも売却（[Sell cards]）ができる。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { CARD_INFO } from '../cards/card-info.js';
import { el } from '../ui/dom.js';
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
 */
export class PlanningPlot extends ActionState {
    constructor(/** @type {any} */ game, /** @type {Bga} */ bga) {
        super(game, bga);
        /** @type {number | null} */
        this.no = null;
        /** 次にパネルを出すとき画面へスクロールするか（入場時だけ） */
        this.scrollPanel = true;
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

    /**
     * @param {Record<string, any>} args
     * @param {boolean} isActive
     */
    onEnteringState(args, isActive) {
        this.scrollPanel = true;
        super.onEnteringState(args, isActive);
    }

    onLeavingState() {
        super.onLeavingState();
        this.peRestoreActions();
    }

    /** 描き直しのたびに、アクションカードの行を元の場所へ戻してから外す（パネルの付け直しは refresh の最後） */
    clearUi() {
        this.peRestoreActions();
        super.clearUi();
    }

    refresh() {
        super.refresh();
        this.peShowActionsPanel();
    }

    peRestoreActions() {
        const view = this.game.view;
        const me = view?.m.me ?? null;
        if (view !== null && me !== null) {
            view.areas.get(me)?.moveActionsTo(null);
        }
    }

    /**
     * 計画の間、自分のアクションカードの行を選択パネル（盤面の上）に出す（012 追補 U-1 の 8）。確定後も 22 の間はパネルに置く。
     * 同じ行が 2 か所に出ないよう、要素そのものを移す。観戦者・参加していない人には出さない。
     */
    peShowActionsPanel() {
        const view = this.game.view;
        const me = view?.m.me ?? null;
        const area = me === null ? undefined : view?.areas.get(me);
        if (view === null || area === undefined || this.mine === null) {
            return;
        }
        const body = el('div', { class: 'pe-choice-section pe-plan-actions' });
        view.panel.show({
            title: _('Choose your Action Card'),
            hint: _('Sideways cards were used last round and cannot be chosen'),
            body: [body],
        }, { scroll: this.scrollPanel });
        this.scrollPanel = false;
        area.moveActionsTo(body);
    }

    renderConfirmed() {
        const view = this.game.view;
        if (view !== null && view.m.me !== null) {
            // 確定済み: 場に置いた自分のアクションカードを表向きで見せる（他の人には裏面）
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
