/**
 * 15 SetupDraftPick（全員同時）: 手元のカードから 1 枚を選び、残りを左隣へ回す。これを 4 回繰り返す（013 §6）。
 * 選ぶカードは選択パネルの CardPicker（012 未確定 17）。取ったカードは手札に入る（既存の手札の欄と [Sell cards]）。確定後は全員が確定するまで選び直せる。
 * 最後の 1 枚は、左隣から回ってきたものが自動で手札に入る。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { ActionState } from './ActionState.js';
import { cardInfo } from '../cards/card-text.js';
import { projectKey } from '../cards/sprite-layout.js';
import { CardPicker } from '../ui/CardPicker.js';
import { el } from '../ui/dom.js';
import { format, intList } from './logic/common.js';
import { draftActionArgs, draftHeading, draftPicks, draftSelectionFrom } from './logic/draft.js';

/**
 * @param {number} id
 * @returns {string}
 */
function cardName(id) {
    const key = projectKey(id);
    return cardInfo(key)?.name ?? key;
}

export class DraftState extends ActionState {
    constructor(/** @type {any} */ game, /** @type {Bga} */ bga) {
        super(game, bga);
        /** @type {number | null} 選んでいるカード */
        this.pick = null;
    }

    /** 選べるカード（本人の私的な args。なければモデルの myPack） */
    get cards() {
        const fromArgs = this.mine?.pack;
        return Array.isArray(fromArgs) ? intList(fromArgs) : (this.game.view?.m.myPack ?? []);
    }

    reset() {
        this.pick = null;
    }

    /** @param {Record<string, any> | null} choice */
    restore(choice) {
        this.pick = draftSelectionFrom(choice);
    }

    /** @param {Record<string, any>} choice */
    summarize(choice) {
        const id = draftSelectionFrom(choice);
        return id === null ? '' : format(_('keep ${name}'), { name: cardName(id) });
    }

    /**
     * 見出しの文（パネルの欄ラベルと添え書き）。
     * @returns {{ title: string, label: string, note: string } | null}
     */
    headingText() {
        const view = this.game.view;
        if (view === null) {
            return null;
        }
        const h = draftHeading(this.args, view.m.playerOrder, view.m.me);
        if (h === null) {
            return null;
        }
        const nameOf = (/** @type {number} */ id) => view.m.players[id]?.name ?? String(id);
        const vars = { count: h.packSize, passTo: nameOf(h.passTo), receiveFrom: nameOf(h.receiveFrom), name: nameOf(h.passTo) };
        return {
            title: format(_('Pick ${pickNo} of ${pickTotal}'), { pickNo: h.pickNo, pickTotal: h.pickTotal }),
            label: h.same
                ? format(_('Keep 1 of ${count} cards and pass the rest to ${name}. Next, you receive cards from ${name}'), vars)
                : format(_('Keep 1 of ${count} cards and pass the rest to ${passTo}. Next, you receive cards from ${receiveFrom}'), vars),
            note: h.last ? _('The last card passed to you goes to your hand automatically') : '',
        };
    }

    /**
     * 選択パネル（見出し + カード）を出す。
     * @param {CardPicker} picker
     */
    showPanel(picker) {
        const view = this.game.view;
        const text = this.headingText();
        if (view === null || text === null) {
            return;
        }
        view.panel.show({
            title: text.title,
            hint: text.note,
            body: [el('div', { class: 'pe-choice-section' }, [el('div', { class: 'pe-choice-label' }, [text.label]), picker.root])],
        });
    }

    renderEdit() {
        const view = this.game.view;
        if (view === null || view.m.me === null) {
            return;
        }
        this.pick = draftPicks(this.pick, this.cards);
        const picker = new CardPicker(view, {
            ids: this.cards,
            selected: this.pick === null ? [] : [this.pick],
            max: 1,
            onChange: (ids) => {
                this.pick = ids[0] ?? null;
                this.refresh();
            },
        });
        this.showPanel(picker);
        this.bga.statusBar.setTitle(_('${you} must keep 1 card. Click the card you want to keep'));
        const pick = this.pick;
        this.bga.statusBar.addActionButton(
            this.confirmed === null ? _('Confirm') : _('Confirm the new choice'),
            () => {
                if (pick !== null) {
                    this.send('actConfirmDraftPick', draftActionArgs(pick), { card: pick });
                }
            },
            { color: 'primary', disabled: pick === null },
        );
    }

    /**
     * 確定済みのカードを、パネルのカードの上に「Picked」として出す（本人の画面だけ）。
     * @param {Record<string, any>} choice
     */
    showPlan(choice) {
        const view = this.game.view;
        const id = draftSelectionFrom(choice);
        if (view === null || id === null) {
            return;
        }
        const ids = this.cards.includes(id) ? this.cards : [...this.cards, id];
        const picker = new CardPicker(view, { ids, selected: [id], max: 1, disabled: new Set(ids) });
        const card = picker.root.querySelector(`.pe-picker-card[data-id="${id}"]`);
        if (card !== null) {
            card.classList.remove('pe-unselectable');
            card.classList.add('pe-planned');
            card.append(el('span', { class: 'pe-plan-badge' }, [_('Picked')]));
        }
        this.showPanel(picker);
    }
}
