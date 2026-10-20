/**
 * 61 DeployMainChoose（全員同時）: 手札のカード 1 枚と配備先の衛星を選んで [Confirm]、または [Pass]。
 * 配備のボーナス保持者は、同じ 1 回の確定で、続けて「追加の配備」か [Draw 1 card] も選ぶ（作者回答 2026-10-20）。
 * カードから選んでも衛星から選んでも結果は「カード + 衛星」の組（008 §3-6）。コストの内訳と起動の修正（useMods）の選択、売却の同梱を出す。
 * 追加の配備は 2 枚の合計コストで支払いを判定し、売却も合計に対して同梱する。引くカードは同時処理なので、この選択の配備には使えない。
 * args（`_private`）: targets / bonus / canDraw / hand / sellValue / useMods（候補ごと）。各カードのコストはサーバーが計算した値で、ここでは足し算だけをする。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { createCard } from '../cards/CardView.js';
import { projectKey } from '../cards/sprite-layout.js';
import { el } from '../ui/dom.js';
import { ActionState } from './ActionState.js';
import { format, payState, sideAvailability } from './logic/common.js';
import {
    deployActionArgs, deployPairs, deployQuote, deploySelectionFrom, deployedCards, extraPairs, parseTargets, validateDeploy, withoutMods,
} from './logic/deploy.js';
import { cardName, costNote, deployBlockedText } from './texts.js';

export class DeployState extends ActionState {
    constructor(/** @type {any} */ game, /** @type {Bga} */ bga) {
        super(game, bga);
        this.sel = deploySelectionFrom(null);
        /** 追加の配備を選んでいる最中か（通常の配備は決まっていて、変えるには追加を取り消す） */
        this.extraStage = false;
    }

    /** 配備のボーナス保持者か（追加の配備 / 1 枚引くを選べる） */
    get isBonus() {
        return this.mine?.bonus === true;
    }

    reset() {
        this.sel = deploySelectionFrom(null);
        this.extraStage = false;
    }

    /** @param {Record<string, any> | null} choice */
    restore(choice) {
        this.sel = deploySelectionFrom(choice);
        this.extraStage = this.sel.extra?.op === 'deploy';
    }

    /** @param {Record<string, any>} choice */
    summarize(choice) {
        const satName = (/** @type {unknown} */ id) => this.game.view?.satName(Number(id)) ?? String(id);
        /** @type {string[]} */
        const parts = [];
        if (choice.op === 'deploy') {
            parts.push(format(_('deploy ${card} to ${sat}'), { card: cardName(Number(choice.card)), sat: satName(choice.sat) }));
        }
        if (choice.extra?.op === 'deploy') {
            parts.push(format(_('deploy ${card} to ${sat}'), { card: cardName(Number(choice.extra.card)), sat: satName(choice.extra.sat) }));
        } else if (choice.extra?.op === 'draw') {
            parts.push(_('draw 1 card'));
        }
        return parts.length === 0 ? _('pass') : parts.join(', ');
    }

    extraReserved() {
        return [...deployedCards(this.sel), ...this.sel.sell];
    }

    /** @param {number[]} sold */
    afterSell(sold) {
        this.sel.sell = this.sel.sell.filter((id) => !sold.includes(id));
    }

    renderEdit() {
        const view = this.game.view;
        const mine = this.mine;
        if (view === null || mine === null || view.m.me === null) {
            return;
        }
        const sel = this.sel;
        const hand = this.hand;
        const targets = parseTargets(mine.targets);
        // 売却などで手札が変わったとき、選択を整える
        for (const id of deployedCards(sel)) {
            if (!hand.includes(id)) {
                this.reset();
                break;
            }
        }
        sel.sell = sel.sell.filter((id) => hand.includes(id) && !deployedCards(sel).includes(id));
        if (this.extraStage && sel.extra?.op !== 'deploy') {
            this.extraStage = false;
        }
        if (this.extraStage) {
            this.renderExtra(view, mine, targets);
        } else {
            this.renderMain(view, mine, targets);
        }
    }

    /**
     * 通常の配備（1 枚目）の選択。選んだ後、ボーナス保持者は [Deploy another card] / [Draw 1 card] を選べる。
     * @param {import('../ui/GameView.js').GameView} view
     * @param {Record<string, any>} mine
     * @param {import('./logic/deploy.js').DeployTarget[]} targets
     */
    renderMain(view, mine, targets) {
        const me = /** @type {number} */ (view.m.me);
        const area = view.areas.get(me);
        const sel = this.sel;
        const hand = this.hand;
        const pairs = deployPairs(targets, hand, this.maya, this.price);
        if (sel.cardId !== null && sel.satId !== null && pairs.find((p) => p.a === sel.cardId && p.b === sel.satId)?.usable !== true) {
            sel.satId = null;
        }
        if (sel.cardId === null) {
            sel.satId = null;
        }
        const drawing = sel.extra?.op === 'draw';
        const cardAvail = sideAvailability(pairs, 'a', sel.satId);
        const satAvail = sideAvailability(pairs, 'b', sel.cardId);
        const allowedCards = hand.filter((id) => cardAvail.get(id)?.usable === true);
        area?.hand.setSelectable(allowedCards, {
            onChange: (ids) => {
                sel.cardId = ids[0] ?? null;
                if (sel.cardId === null) {
                    sel.sell = [];
                }
                this.refresh();
            },
        });
        area?.hand.setChosen(sel.cardId === null ? [] : [sel.cardId]);

        /** @type {Map<number, Omit<import('../cards/SatelliteView.js').SatellitePick, 'onPick'>>} */
        const picks = new Map();
        for (const satellite of view.m.players[me].satellites) {
            const entry = satAvail.get(satellite.satId);
            const pair = entry?.entry ?? null;
            if (sel.satId === satellite.satId) {
                const quote = pair === null ? null : deployQuote(pair.data, sel.useMods, { maya: this.maya, price: this.price, sellable: hand.length - 1 });
                picks.set(satellite.satId, { state: 'selected', note: quote === null ? undefined : costNote(quote.cost, quote.pay) });
            } else if (entry === undefined || pair === null) {
                picks.set(satellite.satId, { state: 'blocked', note: sel.cardId === null ? undefined : _('Cannot deploy this card here') });
            } else if (entry.usable) {
                picks.set(satellite.satId, sel.cardId === null ? { state: 'selectable' } : { state: pair.data.pay.kind === 'free' ? 'selectable' : 'sellable', note: costNote(pair.data.cost, pair.data.pay) });
            } else {
                picks.set(satellite.satId, { state: 'blocked', note: pair.data.blocked === null ? costNote(pair.data.cost, pair.data.pay) : deployBlockedText(pair.data.blocked) });
            }
        }
        area?.setSatPicks(picks, (satId) => {
            sel.satId = sel.satId === satId ? null : satId;
            this.refresh();
        });

        // コストの内訳（ボトムシート）と、起動の修正・売却の同梱
        const chosen = pairs.find((p) => p.a === sel.cardId && p.b === sel.satId);
        let ok = false;
        if (chosen !== undefined) {
            const target = chosen.data;
            const funds = { maya: this.maya, price: this.price, sellable: hand.length - 1 };
            const quote = deployQuote(target, sel.useMods, funds);
            sel.useMods = quote.mods;
            ok = validateDeploy(sel, { pay: quote.pay, canDraw: mine.canDraw === true }).ok;
            this.costBox({
                title: format(_('Deploy ${card} to ${sat} (slot ${tier})'), { card: cardName(chosen.a), sat: view.satName(chosen.b), tier: target.tier }),
                lines: [format(_('Deploy cost: ${cost} Maya'), { cost: quote.cost })],
                quote,
                mods: target.mods,
                sel,
                reserved: [chosen.a],
                subject: [this.cardPreview(chosen.a)],
            });
        } else if (sel.cardId !== null || sel.satId !== null) {
            view.sheet.show({
                title: sel.cardId === null ? _('Choose a card to deploy') : _('Choose a satellite to deploy to'),
                lines: [sel.cardId === null ? _('Satellites with a note can receive a card') : format(_('Selected: ${card}'), { card: cardName(sel.cardId) })],
            });
        } else if (drawing) {
            ok = true;
        }

        const hint = this.isBonus
            ? _('${you} must deploy a card to a satellite, or pass. You may also deploy another card or draw 1 card (Action bonus)')
            : _('${you} must deploy a card to a satellite, or pass');
        const selected = chosen === undefined ? '' : format(_(': ${card} to ${sat}'), { card: cardName(chosen.a), sat: view.satName(chosen.b) });
        const drawNote = drawing ? `. ${_('You will also draw 1 card')}` : '';
        this.bga.statusBar.setTitle(selected === '' && !drawing ? `${hint}. ${_('Choose a card and a satellite')}` : `${hint}${selected}${drawNote}`);
        if (chosen !== undefined || drawing) {
            this.addConfirm(ok);
        }
        if (this.isBonus && chosen !== undefined) {
            this.bga.statusBar.addActionButton(_('Deploy another card'), () => {
                sel.extra = { op: 'deploy', cardId: null, satId: null, useMods: [] };
                this.extraStage = true;
                this.refresh();
            }, { color: 'secondary' });
        }
        if (this.isBonus && mine.canDraw === true && !drawing) {
            this.bga.statusBar.addActionButton(_('Draw 1 card'), () => {
                sel.extra = { op: 'draw' };
                this.refresh();
            }, { color: 'secondary' });
        }
        if (drawing) {
            this.bga.statusBar.addActionButton(_('Do not draw'), () => {
                sel.extra = null;
                this.refresh();
            }, { color: 'secondary' });
        }
        if (sel.cardId !== null || sel.satId !== null) {
            this.addReset();
        }
        this.addPass();
    }

    /**
     * 追加の配備（2 枚目）の選択。通常の配備は決まっていて、コストは 2 枚の合計で見せる。
     * @param {import('../ui/GameView.js').GameView} view
     * @param {Record<string, any>} mine
     * @param {import('./logic/deploy.js').DeployTarget[]} targets
     */
    renderExtra(view, mine, targets) {
        const me = /** @type {number} */ (view.m.me);
        const area = view.areas.get(me);
        const sel = this.sel;
        const extra = /** @type {{ op: 'deploy', cardId: number | null, satId: number | null, useMods: string[] }} */ (sel.extra);
        const hand = this.hand;
        const funds = { maya: this.maya, price: this.price, sellable: hand.length - 2 };
        const mainTarget = targets.find((t) => t.cardId === sel.cardId && t.satId === sel.satId);
        if (mainTarget === undefined || sel.cardId === null || sel.satId === null) {
            // 通常の配備の候補が見つからない（手札が変わった）。選び直す
            this.reset();
            this.refresh();
            return;
        }
        const mainQuote = deployQuote(mainTarget, sel.useMods, { maya: this.maya, price: this.price, sellable: hand.length - 1 });
        sel.useMods = mainQuote.mods;
        const main = { cardId: sel.cardId, satId: sel.satId, cost: mainQuote.cost, mods: sel.useMods };
        const pairs = extraPairs(targets, hand, main, this.maya, this.price);
        if (extra.cardId !== null && extra.satId !== null && pairs.find((p) => p.a === extra.cardId && p.b === extra.satId)?.usable !== true) {
            extra.satId = null;
        }
        if (extra.cardId === null) {
            extra.satId = null;
        }
        const cardAvail = sideAvailability(pairs, 'a', extra.satId);
        const satAvail = sideAvailability(pairs, 'b', extra.cardId);
        const allowedCards = hand.filter((id) => cardAvail.get(id)?.usable === true);
        area?.hand.setSelectable(allowedCards, {
            onChange: (ids) => {
                extra.cardId = ids[0] ?? null;
                this.refresh();
            },
        });
        area?.hand.setChosen(extra.cardId === null ? [sel.cardId] : [sel.cardId, extra.cardId]);

        /** @type {Map<number, Omit<import('../cards/SatelliteView.js').SatellitePick, 'onPick'>>} */
        const picks = new Map();
        for (const satellite of view.m.players[me].satellites) {
            const entry = satAvail.get(satellite.satId);
            const pair = entry?.entry ?? null;
            const firstHere = satellite.satId === sel.satId ? format(_('${card} goes here first'), { card: cardName(sel.cardId) }) : '';
            const withFirst = (/** @type {string} */ note) => (firstHere === '' ? note : `${firstHere}. ${note}`);
            if (extra.satId === satellite.satId && pair !== null) {
                picks.set(satellite.satId, { state: 'selected', note: withFirst(costNote(pair.data.total, pair.data.pay)) });
            } else if (entry === undefined || pair === null) {
                picks.set(satellite.satId, { state: 'blocked', note: extra.cardId === null ? (firstHere === '' ? undefined : firstHere) : _('Cannot deploy this card here') });
            } else if (entry.usable) {
                picks.set(satellite.satId, extra.cardId === null
                    ? { state: 'selectable', note: firstHere === '' ? undefined : firstHere }
                    : { state: pair.data.pay.kind === 'free' ? 'selectable' : 'sellable', note: withFirst(costNote(pair.data.total, pair.data.pay)) });
            } else {
                picks.set(satellite.satId, { state: 'blocked', note: pair.data.blocked === null ? withFirst(costNote(pair.data.total, pair.data.pay)) : deployBlockedText(pair.data.blocked) });
            }
        }
        area?.setSatPicks(picks, (satId) => {
            extra.satId = extra.satId === satId ? null : satId;
            this.refresh();
        });

        const chosen = pairs.find((p) => p.a === extra.cardId && p.b === extra.satId);
        let ok = false;
        if (chosen !== undefined) {
            const target = chosen.data;
            const extraMods = withoutMods(target.mods, sel.useMods, target.cost);
            const quote = deployQuote({ ...target, mods: extraMods }, extra.useMods, funds);
            extra.useMods = quote.mods;
            const total = mainQuote.cost + quote.cost;
            const pay = payState(total, funds);
            ok = validateDeploy(sel, { pay, canDraw: false }).ok;
            this.costBox({
                title: format(_('Deploy ${card} to ${sat} (slot ${tier}), then ${card2} to ${sat2} (slot ${tier2})'), {
                    card: cardName(sel.cardId),
                    sat: view.satName(sel.satId),
                    tier: mainTarget.tier,
                    card2: cardName(chosen.a),
                    sat2: view.satName(chosen.b),
                    tier2: target.tier,
                }),
                lines: [
                    format(_('Deploy cost: ${cost} Maya'), { cost: mainQuote.cost }),
                    format(_('Additional deploy cost: ${cost} Maya'), { cost: quote.cost }),
                    format(_('Total: ${cost} Maya'), { cost: total }),
                ],
                quote: { cost: total, pay },
                mods: mainTarget.mods,
                sel,
                moreMods: extraMods === null ? [] : [{ mods: extraMods, sel: extra }],
                reserved: [sel.cardId, chosen.a],
                subject: [this.cardPreview(sel.cardId), this.cardPreview(chosen.a)],
            });
        } else {
            view.sheet.show({
                title: extra.cardId === null ? _('Choose another card to deploy') : _('Choose a satellite to deploy to'),
                lines: [format(_('First: ${card}'), { card: cardName(sel.cardId) })],
            });
        }

        this.bga.statusBar.setTitle(chosen === undefined
            ? `${_('${you} may deploy another card (Action bonus)')}. ${_('Choose a card and a satellite')}`
            : format(_('${you} may deploy another card (Action bonus): ${card} to ${sat}'), { card: cardName(chosen.a), sat: view.satName(chosen.b) }));
        if (chosen !== undefined) {
            this.addConfirm(ok);
        }
        this.bga.statusBar.addActionButton(_('Do not deploy another card'), () => {
            sel.extra = null;
            this.extraStage = false;
            this.refresh();
        }, { color: 'secondary' });
        this.addReset();
        this.addPass();
    }

    /**
     * 小さなカード画像（選択パネルの見出し用）。
     * @param {number} id
     * @returns {HTMLElement}
     */
    cardPreview(id) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const card = createCard(view.env, projectKey(id), { classes: ['pe-picker-card'] });
        view.tooltip.bind(card, projectKey(id));
        return el('div', { class: 'pe-choice-subject' }, [card, el('span', {}, [cardName(id)])]);
    }

    /**
     * @param {boolean} enabled
     */
    addConfirm(enabled) {
        this.bga.statusBar.addActionButton(_('Confirm'), () => {
            const sel = this.sel;
            if (!enabled) {
                return;
            }
            this.send('actConfirmDeploy', deployActionArgs(sel), this.choiceOf(sel));
        }, { color: 'primary', disabled: !enabled });
    }

    /**
     * 送る内容を、サーバーの myChoice と同じ形にする（確定後の復元と要約に使う）。
     * @param {import('./logic/deploy.js').DeploySelection} sel
     * @returns {Record<string, any>}
     */
    choiceOf(sel) {
        /** @type {Record<string, any>} */
        const choice = sel.cardId !== null && sel.satId !== null
            ? { op: 'deploy', card: sel.cardId, sat: sel.satId, sell: [...sel.sell], useMods: [...sel.useMods] }
            : { op: 'pass' };
        if (sel.extra?.op === 'draw') {
            choice.extra = { op: 'draw' };
        } else if (sel.extra?.op === 'deploy') {
            choice.extra = { op: 'deploy', card: sel.extra.cardId, sat: sel.extra.satId, useMods: [...sel.extra.useMods] };
        }
        return choice;
    }

    addReset() {
        this.bga.statusBar.addActionButton(_('Reset'), () => {
            this.reset();
            this.refresh();
        }, { color: 'secondary' });
    }

    addPass() {
        this.bga.statusBar.addActionButton(_('Pass'), () => this.send('actConfirmDeploy', deployActionArgs({}), { op: 'pass' }), { color: 'secondary' });
    }
}
