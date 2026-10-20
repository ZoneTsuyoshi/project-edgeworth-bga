/**
 * 91 ProposalKeepChoose（全員同時）: 引いたカードから残すカードを選んで [Confirm]（メイン: 1 枚 / ボーナス: 2 枚。効果による増減あり）。
 * 企画のオプション（P068 手札から捨てて多く残す / P070 手札を追加で捨てて +7 マヤ / P072 マヤを払って残す枚数を増やす）と売却の同梱を、同じ画面で選ぶ（011 §6-5）。
 * 引いたカード自体は取り消せない。残すカードの選び直しは全員の確定まで可（008 §3-8）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { CardPicker } from '../ui/CardPicker.js';
import { el } from '../ui/dom.js';
import { nameOfNameId } from '../cards/name-id.js';
import { ActionState } from './ActionState.js';
import { format } from './logic/common.js';
import {
  EXTRA_DISCARD, KEEP_PURCHASE, optionPayment, parseProposal, proposalActionArgs, proposalSelectionFrom, requiredKeep, reservedHand, validateProposal,
} from './logic/proposal.js';
import { cardName } from './texts.js';

export class ProposalState extends ActionState {
    constructor(/** @type {any} */ game, /** @type {Bga} */ bga) {
        super(game, bga);
        this.sel = proposalSelectionFrom(null);
    }

    reset() {
        this.sel = proposalSelectionFrom(null);
    }

    /** @param {Record<string, any> | null} choice */
    restore(choice) {
        this.sel = proposalSelectionFrom(choice);
    }

    /** @param {Record<string, any>} choice */
    summarize(choice) {
        const sel = proposalSelectionFrom(choice);
        const parts = [format(_('keep ${cards}'), { cards: sel.keep.map(cardName).join(', ') })];
        if (sel.handDiscard.length > 0) {
            parts.push(format(_('discard ${cards} from hand'), { cards: sel.handDiscard.map(cardName).join(', ') }));
        }
        if (Object.keys(sel.uses).length > 0) {
            parts.push(_('use proposal options'));
        }
        if (sel.sell.length > 0) {
            parts.push(format(_('sell ${n} card(s)'), { n: sel.sell.length }));
        }
        return parts.join('; ');
    }

    /**
     * 確定済みの企画（残すカード）を、選択パネルに「予定」として出す。手札から捨てる予定のカードは手札の上に印を付ける。
     * @param {Record<string, any>} choice
     */
    showPlan(choice) {
        const view = this.game.view;
        const me = view?.m.me ?? null;
        if (view === null || me === null) {
            return;
        }
        const sel = proposalSelectionFrom(choice);
        const picker = new CardPicker(view, { ids: sel.keep, selected: sel.keep, disabled: new Set(sel.keep) });
        view.panel.show({
            title: _('Planned: the cards you will keep'),
            body: [el('div', { class: 'pe-choice-section' }, [picker.root])],
        }, { scroll: false });
        view.areas.get(me)?.hand.setPlanned(new Map(sel.handDiscard.map((id) => [id, _('Discard')])));
    }

    extraReserved() {
        const plan = parseProposal(this.mine);
        return [...reservedHand(plan, this.sel), ...this.sel.sell];
    }

    /** @param {number[]} sold */
    afterSell(sold) {
        this.sel.sell = this.sel.sell.filter((id) => !sold.includes(id));
        this.sel.handDiscard = this.sel.handDiscard.filter((id) => !sold.includes(id));
        for (const [key, id] of Object.entries(this.sel.uses)) {
            if (sold.includes(id)) {
                delete this.sel.uses[key];
            }
        }
    }

    renderEdit() {
        const view = this.game.view;
        if (view === null || this.mine === null) {
            return;
        }
        const plan = parseProposal(this.mine);
        const sel = this.sel;
        const hand = this.hand;
        // 選択を整える（売却などで手札が変わったとき、オプションで残す枚数が減ったとき）
        sel.keep = sel.keep.filter((id) => plan.drawn.includes(id));
        sel.handDiscard = plan.handDiscard ? sel.handDiscard.filter((id) => hand.includes(id)) : [];
        for (const option of plan.options) {
            if (option.kind === EXTRA_DISCARD && sel.uses[option.hostKey] !== undefined && !hand.includes(sel.uses[option.hostKey]) && !sel.keep.includes(sel.uses[option.hostKey])) {
                delete sel.uses[option.hostKey];
            }
        }
        sel.sell = sel.sell.filter((id) => hand.includes(id));
        const required = requiredKeep(plan, sel);
        sel.keep = sel.keep.slice(0, required);

        const claimedExcept = (/** @type {'handDiscard' | 'extra' | 'sell'} */ role) => {
            const ids = new Set();
            if (role !== 'handDiscard') {
                sel.handDiscard.forEach((id) => ids.add(id));
            }
            if (role !== 'extra') {
                plan.options.filter((o) => o.kind === EXTRA_DISCARD && sel.uses[o.hostKey] !== undefined).forEach((o) => ids.add(sel.uses[o.hostKey]));
            }
            if (role !== 'sell') {
                sel.sell.forEach((id) => ids.add(id));
            }
            return ids;
        };
        const check = validateProposal(plan, sel, { hand, maya: this.maya, price: this.price });
        /** @type {HTMLElement[]} */
        const body = [];

        // 引いたカード
        const keepPicker = new CardPicker(view, {
            ids: plan.drawn,
            selected: sel.keep,
            max: required,
            onChange: (ids) => {
                sel.keep = ids;
                this.refresh();
            },
        });
        body.push(el('div', { class: 'pe-choice-section' }, [
            el('div', { class: 'pe-choice-label' }, [format(_('Cards you drew: keep ${n} of ${total} (${chosen} chosen). The rest are discarded'), { n: required, total: plan.drawn.length, chosen: sel.keep.length })]),
            keepPicker.root,
        ]));

        // P068: 手札から捨てて、その枚数だけ多く残す
        if (plan.handDiscard) {
            const picker = new CardPicker(view, {
                ids: hand,
                selected: sel.handDiscard,
                disabled: new Map([...claimedExcept('handDiscard')].map((id) => [id, _('Already used for another purpose')])),
                onChange: (ids) => {
                    sel.handDiscard = ids;
                    this.refresh();
                },
            });
            body.push(el('div', { class: 'pe-choice-section' }, [
                el('div', { class: 'pe-choice-label' }, [_('Optional: discard cards from your hand too. For each one, you can keep one more drawn card')]),
                picker.root,
            ]));
        }

        // P070 / P072
        for (const option of plan.options) {
            const name = nameOfNameId(option.cardKey);
            if (option.kind === EXTRA_DISCARD) {
                const used = sel.uses[option.hostKey] !== undefined;
                const candidates = [...hand, ...sel.keep.filter((id) => !hand.includes(id))];
                const section = el('div', { class: 'pe-choice-section' }, [
                    el('label', { class: 'pe-option' }, [
                        el('input', {
                            type: 'checkbox',
                            checked: used,
                            onChange: (/** @type {Event} */ e) => {
                                if (/** @type {HTMLInputElement} */ (e.target).checked) {
                                    const first = candidates.find((id) => !claimedExcept('extra').has(id));
                                    if (first !== undefined) {
                                        sel.uses[option.hostKey] = first;
                                    }
                                } else {
                                    delete sel.uses[option.hostKey];
                                }
                                this.refresh();
                            },
                        }),
                        el('span', {}, [format(_('${name}: discard 1 more card for +${reward} Maya (no sale value)'), { name, reward: option.reward })]),
                    ]),
                ]);
                if (used) {
                    section.append(new CardPicker(view, {
                        ids: candidates,
                        selected: [sel.uses[option.hostKey]],
                        max: 1,
                        disabled: new Map([...claimedExcept('extra')].map((id) => [id, _('Already used for another purpose')])),
                        onChange: (ids) => {
                            if (ids.length === 0) {
                                delete sel.uses[option.hostKey];
                            } else {
                                sel.uses[option.hostKey] = ids[0];
                            }
                            this.refresh();
                        },
                    }).root);
                }
                body.push(section);
            } else if (option.kind === KEEP_PURCHASE) {
                const group = `pe-opt-${option.hostKey}`;
                const choices = [{ pay: 0, plus: 0 }, ...option.tiers];
                body.push(el('div', { class: 'pe-choice-section' }, [
                    el('div', { class: 'pe-choice-label' }, [format(_('${name}: pay Maya to keep more cards'), { name })]),
                    ...choices.map((t) => {
                        const affordable = t.pay === 0 || option.affordable.includes(t.pay);
                        const bySelling = option.affordableBySelling.includes(t.pay);
                        const enabled = affordable || bySelling;
                        const current = (sel.uses[option.hostKey] ?? 0) === t.pay;
                        return el('label', { class: `pe-option${enabled ? '' : ' pe-option-off'}` }, [
                            el('input', {
                                type: 'radio',
                                name: group,
                                checked: current,
                                disabled: !enabled,
                                onChange: () => {
                                    if (t.pay === 0) {
                                        delete sel.uses[option.hostKey];
                                    } else {
                                        sel.uses[option.hostKey] = t.pay;
                                    }
                                    this.refresh();
                                },
                            }),
                            el('span', {}, [
                                t.pay === 0
                                    ? _('Do not use')
                                    : format(_('Pay ${pay} Maya: keep ${plus} more'), { pay: t.pay, plus: t.plus }) + (affordable ? '' : enabled ? ` (${_('sell cards to pay')})` : ` (${_('not enough Maya')})`),
                            ]),
                        ]);
                    }),
                ]));
            }
        }

        // 支払いが足りないときの売却の促し
        const payment = optionPayment(plan, sel);
        if (payment > 0) {
            const need = Math.max(0, Math.ceil((payment - this.maya) / Math.max(1, this.price)));
            if (need > 0) {
                body.push(this.sellPrompt({ need, short: Math.max(0, payment - this.maya) }, reservedHand(plan, sel), sel.sell, (ids) => {
                    sel.sell = ids;
                    this.refresh();
                }));
            } else {
                sel.sell = [];
            }
        } else {
            sel.sell = [];
        }

        if (check.reason !== undefined && check.reason !== 'keepCount' && check.reason !== 'needMoreSales') {
            body.push(el('div', { class: 'pe-choice-error', role: 'alert' }, [this.reasonText(check.reason)]));
        }
        view.panel.show({
            title: format(_('Proposal: choose ${n} card(s) to keep'), { n: required }),
            body,
        });
        this.bga.statusBar.setTitle(format(_('${you} must choose ${n} card(s) to keep (${chosen} chosen)'), { n: required, chosen: sel.keep.length }));
        this.bga.statusBar.addActionButton(_('Confirm'), () => {
            if (!check.ok) {
                return;
            }
            this.send('actConfirmKeep', proposalActionArgs(sel), {
                keep: [...sel.keep],
                handDiscard: [...sel.handDiscard],
                optionUses: { ...sel.uses },
                sell: [...sel.sell],
            });
        }, { color: 'primary', disabled: !check.ok });
        this.bga.statusBar.addActionButton(_('Reset'), () => {
            this.reset();
            this.refresh();
        }, { color: 'secondary' });
    }

    /**
     * @param {string} reason
     * @returns {string}
     */
    reasonText(reason) {
        switch (reason) {
            case 'cardUsedTwice':
                return _('A card cannot be used for two purposes');
            case 'cannotPay':
                return _('Not enough Maya, even if you sell your cards');
            case 'invalidExtraDiscard':
                return _('Choose a card in your hand (or a kept card) to discard');
            default:
                return reason;
        }
    }
}
