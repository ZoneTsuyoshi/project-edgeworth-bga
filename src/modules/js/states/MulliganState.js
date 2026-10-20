/**
 * 13 SetupMulligan（全員同時）: 手札から捨てるカードをクリックで選び（トグル）、[Mulligan N cards] か [Keep all] で確定する（008 §8-1）。
 * 確定後は他の人が確定するまで選び直せる。捨てたカードは全員が確定したあと公開され、同じ枚数を引き直す。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { ActionState } from './ActionState.js';
import { format } from './logic/common.js';
import { mulliganActionArgs, mulliganButton, mulliganPicks, mulliganSelectionFrom } from './logic/mulligan.js';

export class MulliganState extends ActionState {
    constructor(/** @type {any} */ game, /** @type {Bga} */ bga) {
        super(game, bga);
        /** @type {number[]} 捨てるカード */
        this.discard = [];
    }

    reset() {
        this.discard = [];
    }

    /** @param {Record<string, any> | null} choice */
    restore(choice) {
        this.discard = mulliganSelectionFrom(choice);
    }

    /** @param {Record<string, any>} choice */
    summarize(choice) {
        const n = mulliganSelectionFrom(choice).length;
        return n === 0 ? _('keep all cards') : format(_('mulligan ${n} card(s)'), { n });
    }

    renderEdit() {
        const view = this.game.view;
        if (view === null || view.m.me === null) {
            return;
        }
        this.discard = mulliganPicks(this.discard, this.hand);
        const button = mulliganButton(this.discard.length);
        view.areas.get(view.m.me)?.hand.setSelectable(this.hand, {
            multi: true,
            onChange: (ids) => {
                this.discard = ids;
                this.refresh();
            },
        });
        view.areas.get(view.m.me)?.hand.setChosen(this.discard);
        this.bga.statusBar.setTitle(_('${you} may discard cards to draw replacements (Mulligan). Click the cards to discard'));
        this.bga.statusBar.addActionButton(
            button.kind === 'keep' ? _('Keep all') : format(_('Mulligan ${n} card(s)'), { n: button.count }),
            () => this.send('actConfirmMulligan', mulliganActionArgs(this.discard), { discard: [...this.discard] }),
            { color: 'primary' },
        );
    }

    /**
     * 確定済みの引き直し（捨てる予定のカード）を手札の上に出す。
     * @param {Record<string, any>} choice
     */
    showPlan(choice) {
        const view = this.game.view;
        const me = view?.m.me ?? null;
        if (view === null || me === null) {
            return;
        }
        view.areas.get(me)?.hand.setPlanned(new Map(mulliganSelectionFrom(choice).map((id) => [id, _('Discard')])));
    }

    extraReserved() {
        return [];
    }

    /** @param {number[]} sold */
    afterSell(sold) {
        this.discard = this.discard.filter((id) => !sold.includes(id));
    }
}
