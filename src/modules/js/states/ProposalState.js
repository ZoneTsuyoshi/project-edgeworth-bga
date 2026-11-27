/**
 * 91 ProposalKeepChoose（全員同時）: 引いたカードから残すカードを選んで [Confirm]（メイン: 1 枚 / ボーナス: 2 枚。効果による増減あり）。
 * 企画のオプション（P068 手札から捨てて多く残す / P070 手札を追加で捨てて +7 マヤ / P072 マヤを払って残す枚数を増やす）と売却の同梱を、同じ画面で選ぶ（011 §6-5）。
 * 引いたカード自体は取り消せない。残すカードの選び直しは全員の確定まで可（008 §3-8）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { CardPicker } from '../ui/CardPicker.js';
import { el } from '../ui/dom.js';
import { costLine } from '../ui/Tray.js';
import { nameOfNameId } from '../cards/name-id.js';
import { ActionState } from './ActionState.js';
import { format } from './logic/common.js';
import { pickFromOutside } from './logic/hand-targets.js';
import {
  EXTRA_DISCARD, KEEP_PURCHASE, keepComplete, optionPayment, parseProposal, proposalActionArgs, proposalMode, proposalSelectionFrom, pruneExtraUses, requiredKeep, reservedHand, validateProposal,
} from './logic/proposal.js';
import { cardName } from './texts.js';

export class ProposalState extends ActionState {
    constructor(/** @type {any} */ game, /** @type {Bga} */ bga) {
        super(game, bga);
        this.sel = proposalSelectionFrom(null);
        // 初期の選択中の欄は「引いたカード」（手札を使わない）。手札の欄はボタンで切り替える
        this.handIdle = true;
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
     * 確定済みの企画（残すカード）を、トレイに「予定」として出す。手札から捨てる予定のカードは手札の上に印を付ける。
     * @param {Record<string, any>} choice
     */
    showPlan(choice) {
        const view = this.game.view;
        const me = view?.m.me ?? null;
        if (view === null || me === null) {
            return;
        }
        const sel = proposalSelectionFrom(choice);
        const picker = new CardPicker(view, { ids: sel.keep, selected: sel.keep, interactive: false });
        view.tray.show([{ label: _('Planned: the cards you will keep'), body: picker.root }]);
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
        const decided = proposalMode(this.mine) === 'decided';
        sel.keep = decided ? [...plan.drawn] : sel.keep.filter((id) => plan.drawn.includes(id));
        sel.handDiscard = plan.handDiscard && !decided ? sel.handDiscard.filter((id) => hand.includes(id)) : [];
        sel.sell = sel.sell.filter((id) => hand.includes(id));
        const required = requiredKeep(plan, sel);
        sel.keep = sel.keep.slice(0, required);
        // P070 は残すカードの選択が完了してから（完了でなくなったら解除）
        pruneExtraUses(plan, sel, hand);
        const keepDone = keepComplete(plan, sel);

        const check = validateProposal(plan, sel, { hand, maya: this.maya, price: this.price });
        const option70 = plan.options.find((o) => o.kind === EXTRA_DISCARD);
        /** @type {import('../ui/Tray.js').TraySection[]} */
        const sections = [];

        // 引いたカード（決定済みの画面では出さない）。トレイの最初の節（ラベル = 見出し）
        const keepPicker = decided ? null : new CardPicker(view, {
            ids: plan.drawn,
            selected: sel.keep,
            max: required,
            onChange: (ids) => {
                sel.keep = ids;
                this.refresh();
            },
        });
        sections.push({
            label: decided ? format(_('Proposal: ${name}'), { name: nameOfNameId(option70?.cardKey ?? '') }) : format(_('Proposal: choose ${n} card(s) to keep'), { n: required }),
            body: keepPicker?.root ?? null,
        });

        // P068: 手札から捨てて、その枚数だけ多く残す（手札のゾーンで選ぶ）
        if (plan.handDiscard && !decided) {
            sections.push({ body: this.handField({
                id: 'handDiscard',
                label: _('Cards to discard from hand'),
                badge: _('Discard'),
                candidates: hand,
                selected: sel.handDiscard,
                title: _('Choose cards to discard from your hand. For each one, you can keep one more drawn card'),
                onChange: (ids) => {
                    sel.handDiscard = ids;
                    this.refresh();
                },
            }) });
        }

        // P070 / P072
        for (const option of plan.options) {
            const name = nameOfNameId(option.cardKey);
            if (decided && option.kind !== EXTRA_DISCARD) {
                continue;
            }
            if (option.kind === EXTRA_DISCARD) {
                const used = sel.uses[option.hostKey];
                // 手札にない候補 = 残すと決めた引いたカード。欄の中の小さな一覧から選ぶ（手札のゾーンと合わせて 1 枚）
                const kept = sel.keep.filter((id) => !hand.includes(id));
                const keptPicker = kept.length === 0 ? undefined : new CardPicker(view, {
                    small: true,
                    ids: kept,
                    selected: used === undefined ? [] : [used],
                    max: 1,
                    onChange: (ids) => {
                        const next = pickFromOutside({ id: option.hostKey, candidates: [], selected: used === undefined ? [] : [used], max: 1 }, ids, hand);
                        if (next.length === 0) {
                            delete sel.uses[option.hostKey];
                        } else {
                            sel.uses[option.hostKey] = next[0];
                        }
                        this.refresh();
                    },
                });
                sections.push({ body: this.handField({
                    id: `extra:${option.hostKey}`,
                    label: format(_('${name}: discard 1 more card for +${reward} Maya (no sale value)'), { name, reward: option.reward }),
                    badge: _('Discard'),
                    candidates: hand,
                    selected: used === undefined ? [] : [used],
                    max: 1,
                    disabled: keepDone ? undefined : _('Choose the cards to keep first'),
                    title: format(_('${name}: choose 1 card to discard (+${reward} Maya)'), { name, reward: option.reward }),
                    extra: keptPicker?.root,
                    onChange: (ids) => {
                        if (ids.length === 0) {
                            delete sel.uses[option.hostKey];
                        } else {
                            sel.uses[option.hostKey] = ids[0];
                        }
                        this.refresh();
                    },
                }) });
            } else if (option.kind === KEEP_PURCHASE) {
                const group = `pe-opt-${option.hostKey}`;
                const choices = [{ pay: 0, plus: 0 }, ...option.tiers];
                sections.push({ label: format(_('${name}: pay Maya to keep more cards'), { name }), body: [
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
                ] });
            }
        }

        // 支払いが足りないときの売却の促し
        const payment = optionPayment(plan, sel);
        if (payment > 0) {
            const need = Math.max(0, Math.ceil((payment - this.maya) / Math.max(1, this.price)));
            if (need > 0) {
                sections.push({ body: this.sellPrompt({ need, short: Math.max(0, payment - this.maya) }, [], sel.sell, (ids) => {
                    sel.sell = ids;
                    this.refresh();
                }) });
            } else {
                sel.sell = [];
            }
        } else {
            sel.sell = [];
        }

        if (check.reason !== undefined && check.reason !== 'keepCount' && check.reason !== 'needMoreSales') {
            sections.push({ body: costLine([], { warn: this.reasonText(check.reason) }) });
        }
        view.tray.show(sections);
        this.bga.statusBar.setTitle(decided
            ? format(_('${you} keep all ${n} drawn cards. You may also use ${name}'), { n: plan.drawn.length, name: nameOfNameId(option70?.cardKey ?? '') })
            : format(_('${you} must choose ${n} card(s) to keep (${chosen} chosen)'), { n: required, chosen: sel.keep.length }));
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
        }, { color: 'alert', classes: 'pe-bar-undo' });
    }

    /**
     * 自動で全部残した人は、待機の画面と [Sell cards] を出す（[Change my choice] は出さない。サーバーが actConfirmKeep を受け付けない）。
     */
    renderConfirmed() {
        if (proposalMode(this.mine) !== 'auto') {
            super.renderConfirmed();
            return;
        }
        this.bga.statusBar.setTitle(format(_('You keep all ${n} drawn cards. Waiting for other players'), { n: parseProposal(this.mine).drawn.length }));
        this.showPlan(this.confirmed ?? {});
        // 自動で確定した人も 91 の参加者なので売却できる（009 §8、Machine::sell の stepParticipants）
        this.addSellButton();
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
