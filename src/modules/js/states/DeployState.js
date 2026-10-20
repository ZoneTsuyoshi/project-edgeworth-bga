/**
 * 61 DeployMainChoose / 62 DeployBonusChoose（全員同時）: 手札のカード 1 枚と配備先の衛星を選んで [Confirm]、または [Pass]（ボーナスは [Draw 1 card] も）。
 * カードから選んでも衛星から選んでも結果は「カード + 衛星」の組（008 §3-6）。コストの内訳と起動の修正（useMods）の選択、売却の同梱を出す。
 * args（`_private`）: targets / canDraw / hand / sellValue / useMods（候補ごと）。コストはサーバーが計算した値で、ここでは計算しない。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { createCard } from '../cards/CardView.js';
import { projectKey } from '../cards/sprite-layout.js';
import { el } from '../ui/dom.js';
import { ActionState } from './ActionState.js';
import { format, sideAvailability } from './logic/common.js';
import { deployActionArgs, deployPairs, deployQuote, deploySelectionFrom, parseTargets, validateDeploy } from './logic/deploy.js';
import { cardName, costNote, deployBlockedText } from './texts.js';

export class DeployState extends ActionState {
    constructor(/** @type {any} */ game, /** @type {Bga} */ bga) {
        super(game, bga);
        this.sel = deploySelectionFrom(null);
    }

    /** ボーナス（62）か。`canDraw` はボーナスの args にだけある */
    get isBonus() {
        return this.mine !== null && this.mine.canDraw !== undefined;
    }

    reset() {
        this.sel = deploySelectionFrom(null);
    }

    /** @param {Record<string, any> | null} choice */
    restore(choice) {
        this.sel = deploySelectionFrom(choice);
    }

    /** @param {Record<string, any>} choice */
    summarize(choice) {
        if (choice.op === 'deploy') {
            const satName = this.game.view?.satName(Number(choice.sat)) ?? String(choice.sat);
            return format(_('deploy ${card} to ${sat}'), { card: cardName(Number(choice.card)), sat: satName });
        }
        return choice.op === 'draw' ? _('draw 1 card') : _('pass');
    }

    extraReserved() {
        return this.sel.cardId === null ? [...this.sel.sell] : [this.sel.cardId, ...this.sel.sell];
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
        const me = view.m.me;
        const area = view.areas.get(me);
        const sel = this.sel;
        const hand = this.hand;
        const targets = parseTargets(mine.targets);
        const pairs = deployPairs(targets, hand, this.maya, this.price);
        // 売却などで手札が変わったとき、選択を整える
        if (sel.cardId !== null && !hand.includes(sel.cardId)) {
            sel.cardId = null;
            sel.satId = null;
            sel.sell = [];
        }
        sel.sell = sel.sell.filter((id) => hand.includes(id) && id !== sel.cardId);
        if (sel.cardId !== null && sel.satId !== null && pairs.find((p) => p.a === sel.cardId && p.b === sel.satId)?.usable !== true) {
            sel.satId = null;
        }
        if (sel.op === 'deploy' && sel.cardId === null && sel.satId === null) {
            sel.op = null;
        }

        if (sel.op === 'draw') {
            this.bga.statusBar.setTitle(_('${you} will draw 1 card (Action bonus). Confirm to draw'));
            this.addConfirm(true);
            this.addReset();
            this.addPass();
            return;
        }

        const cardAvail = sideAvailability(pairs, 'a', sel.satId);
        const satAvail = sideAvailability(pairs, 'b', sel.cardId);
        const allowedCards = hand.filter((id) => cardAvail.get(id)?.usable === true);
        area?.hand.setSelectable(allowedCards, {
            onChange: (ids) => {
                sel.cardId = ids[0] ?? null;
                sel.op = 'deploy';
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
            sel.op = 'deploy';
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
            const check = validateDeploy({ op: 'deploy', cardId: sel.cardId, satId: sel.satId, sell: sel.sell }, { pay: quote.pay, canDraw: false });
            ok = check.ok;
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
        }

        const hint = this.isBonus
            ? _('${you} may deploy another card or draw 1 card (Action bonus)')
            : _('${you} must deploy a card to a satellite, or pass');
        const selected = chosen === undefined ? '' : format(_(': ${card} to ${sat}'), { card: cardName(chosen.a), sat: view.satName(chosen.b) });
        this.bga.statusBar.setTitle(selected === '' ? `${hint}. ${_('Choose a card and a satellite')}` : `${hint}${selected}`);
        if (sel.cardId !== null || sel.satId !== null) {
            this.addConfirm(ok);
        }
        if (this.isBonus && mine.canDraw === true) {
            this.bga.statusBar.addActionButton(_('Draw 1 card'), () => {
                sel.op = 'draw';
                sel.cardId = null;
                sel.satId = null;
                sel.sell = [];
                sel.useMods = [];
                this.refresh();
            }, { color: 'secondary' });
        }
        if (sel.cardId !== null || sel.satId !== null) {
            this.addReset();
        }
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
            this.send('actConfirmDeploy', deployActionArgs(sel), sel.op === 'deploy'
                ? { op: 'deploy', card: sel.cardId, sat: sel.satId, sell: [...sel.sell], useMods: [...sel.useMods] }
                : { op: sel.op });
        }, { color: 'primary', disabled: !enabled });
    }

    addReset() {
        this.bga.statusBar.addActionButton(_('Reset'), () => {
            this.reset();
            this.refresh();
        }, { color: 'secondary' });
    }

    addPass() {
        this.bga.statusBar.addActionButton(_('Pass'), () => this.send('actConfirmDeploy', deployActionArgs({ op: 'pass' }), { op: 'pass' }), { color: 'secondary' });
    }
}
