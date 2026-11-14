/**
 * 61 DeployMainChoose（全員同時）: 手札のカード 1 枚と配備先の衛星を選んで [Confirm]、または [Pass]。
 * 配備のボーナス保持者は、同じ 1 回の確定で、続けて 1 枚引く（[Draw 1 card]）か、追加の配備（[Deploy another card]）を選ぶ（作者回答 2026-10-20）。
 * 追加の配備は段階確定（009 追補 2026-11-12）: S0 で 1 枚目を選んで [Deploy another card] を押すとサーバーが 1 枚目を先に確定（ステージ）し、
 * S1 で 2 枚目を選ぶ（または 2 枚目なしで確定）。S1 の [Reset] は 1 枚目の取り消し（山札から引いた後は不可）。段は args の `stage` / `stage1` から復元する。
 * カードから選んでも衛星から選んでも結果は「カード + 衛星」の組（008 §3-6）。コストの内訳と起動の修正（useMods）の選択、売却の同梱を出す。
 * 売却は 1 枚ごとに、そのカードのコストに対して行う。引くカードは同時処理なので、S0 の配備には使えない（ステージで引いたカードは 2 枚目に使える）。
 * args（`_private`）: targets / bonus / canDraw / hand / sellValue / stage / stage1 / canReset / resetBlockedReason。
 * 各カードのコスト（2 枚目は 1 枚目の効果を含む）はサーバーが計算した値で、ここでは見積もらない。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { createCard } from '../cards/CardView.js';
import { projectKey } from '../cards/sprite-layout.js';
import { el } from '../ui/dom.js';
import { ActionState } from './ActionState.js';
import { format, sideAvailability } from './logic/common.js';
import {
    deployActionArgs, deployPairs, deployQuote, deploySecondArgs, deploySecondFrom, deploySelectionFrom, deployStage, deployStageArgs, deployedCards, parseStage, parseTargets, passNeedsBonusChoice, validateDeploy,
} from './logic/deploy.js';
import { cardName, costNote, deployBlockedText } from './texts.js';

/** [Reset] を押せない理由（サーバーの REASON_IRREVERSIBLE と同じ文面。翻訳対象にするためリテラルで持つ） */
const RESET_BLOCKED_TEXT = () => _('You have drawn cards and cannot undo this deployment');

export class DeployState extends ActionState {
    constructor(/** @type {any} */ game, /** @type {Bga} */ bga) {
        super(game, bga);
        this.sel = deploySelectionFrom(null);
        /** S1 の 2 枚目の選択 */
        this.second = deploySelectionFrom(null);
        /** ボーナスを使わないと選んだか（[No bonus]） */
        this.bonusDeclined = false;
        /** ボーナス保持者が [Pass] を押して、ボーナスだけの選択に進んでいるか */
        this.passing = false;
    }

    onEnteringState(/** @type {Record<string, any>} */ args, /** @type {boolean} */ isActive) {
        // 通知（deployStaged / deployStageReset）から画面を差し替えられるようにする
        const view = this.game.view;
        if (view !== null) {
            view.deployUi = this;
        }
        super.onEnteringState(args, isActive);
    }

    onLeavingState() {
        const view = this.game.view;
        if (view !== null && view.deployUi === this) {
            view.deployUi = null;
        }
        super.onLeavingState();
    }

    /** 配備のボーナス保持者か（追加の配備 / 1 枚引くを選べる） */
    get isBonus() {
        return this.mine?.bonus === true;
    }

    /** 段（S0 / S1）と、S1 のステージ済みの 1 枚目・[Reset] の可否 */
    get stageInfo() {
        return parseStage(this.mine);
    }

    reset() {
        this.sel = deploySelectionFrom(null);
        this.second = deploySelectionFrom(null);
        this.bonusDeclined = false;
        this.passing = false;
    }

    /** @param {Record<string, any> | null} choice */
    restore(choice) {
        if (this.stageInfo.staged) {
            this.second = deploySecondFrom(choice);
            return;
        }
        this.sel = deploySelectionFrom(choice);
        this.passing = this.isBonus && choice?.op === 'pass' && this.mine?.canDraw === true;
        this.bonusDeclined = this.isBonus && choice?.op === 'deploy' && !choice.extra;
    }

    /**
     * サーバーの通知（ステージ / Reset）で `_private` が変わった。段・候補・[Reset] の可否を差し替えて描き直す。
     * ステージ・Reset をするとサーバーは確定を取り消すので、未確定の表示に戻す。
     * @param {Record<string, any>} priv 状態 61 の本人分の args
     * @param {boolean} wasReset Reset のとき、直前にステージしていた 1 枚目を選び直した状態にする
     */
    applyStage(priv, wasReset) {
        const before = this.stageInfo.first;
        this.mine = priv;
        this.confirmed = priv.myChoice ?? null;
        this.editing = this.confirmed === null;
        this.sellMode = null;
        this.sending = false;
        this.reset();
        if (wasReset && before !== null) {
            this.sel.cardId = before.card;
            this.sel.satId = before.satId;
            this.sel.sell = before.sold;
        }
        this.refresh();
    }

    /**
     * 確定済みの配備を予定として出す（カードは手札の上、衛星には半透明のカードを重ねる）。
     * @param {Record<string, any>} choice
     */
    showPlan(choice) {
        const view = this.game.view;
        const area = view === null || view.m.me === null ? undefined : view.areas.get(view.m.me);
        if (view === null || area === undefined) {
            return;
        }
        /** @type {Map<number, string>} */
        const cards = new Map();
        /** @type {Map<number, import('../cards/SatelliteView.js').SatellitePlan[]>} */
        const sats = new Map();
        const add = (/** @type {unknown} */ card, /** @type {unknown} */ sat, /** @type {string} */ order) => {
            const cardId = Number(card);
            const satId = Number(sat);
            cards.set(cardId, _('Planned'));
            sats.set(satId, [...(sats.get(satId) ?? []), { text: format(_('${order}Deploy: ${card}'), { order, card: cardName(cardId) }), cardKey: projectKey(cardId) }]);
        };
        if (this.stageInfo.staged) {
            // 1 枚目は配備済み（衛星の上に実際に出ている）。予定として出すのは 2 枚目だけ
            if (choice.extra?.op === 'deploy') {
                add(choice.extra.card, choice.extra.sat, '');
            }
        } else if (choice.op === 'deploy') {
            add(choice.card, choice.sat, '');
        }
        area.hand.setPlanned(cards);
        area.setSatPlans(sats);
    }

    /** @param {Record<string, any>} choice */
    summarize(choice) {
        const satName = (/** @type {unknown} */ id) => this.game.view?.satName(Number(id)) ?? String(id);
        /** @type {string[]} */
        const parts = [];
        const staged = this.stageInfo;
        if (staged.staged) {
            if (staged.first !== null) {
                parts.push(format(_('deployed ${card} to ${sat}'), { card: cardName(staged.first.card), sat: satName(staged.first.satId) }));
            }
            parts.push(choice.extra?.op === 'deploy'
                ? format(_('deploy ${card} to ${sat}'), { card: cardName(Number(choice.extra.card)), sat: satName(choice.extra.sat) })
                : _('no other card'));
            return parts.join(', ');
        }
        if (choice.op === 'deploy') {
            parts.push(format(_('deploy ${card} to ${sat}'), { card: cardName(Number(choice.card)), sat: satName(choice.sat) }));
        }
        if (choice.extra?.op === 'draw') {
            parts.push(_('draw 1 card'));
        }
        return parts.length === 0 ? _('pass') : parts.join(', ');
    }

    extraReserved() {
        const sel = this.stageInfo.staged ? this.second : this.sel;
        return [...deployedCards(sel), ...sel.sell];
    }

    /** @param {number[]} sold */
    afterSell(sold) {
        this.sel.sell = this.sel.sell.filter((id) => !sold.includes(id));
        this.second.sell = this.second.sell.filter((id) => !sold.includes(id));
    }

    renderEdit() {
        const view = this.game.view;
        const mine = this.mine;
        if (view === null || mine === null || view.m.me === null) {
            return;
        }
        const hand = this.hand;
        const targets = parseTargets(mine.targets);
        if (this.stageInfo.staged) {
            this.second.sell = this.second.sell.filter((id) => hand.includes(id) && id !== this.second.cardId);
            if (this.second.cardId !== null && !hand.includes(this.second.cardId)) {
                this.second = deploySelectionFrom(null);
            }
            this.renderSecond(view, mine, targets);
            return;
        }
        const sel = this.sel;
        // 売却などで手札が変わったとき、選択を整える
        if (deployedCards(sel).some((id) => !hand.includes(id))) {
            this.reset();
        }
        sel.sell = sel.sell.filter((id) => hand.includes(id) && !deployedCards(sel).includes(id));
        if (this.passing && (sel.cardId === null || sel.satId === null)) {
            this.renderPassing();
        } else {
            this.passing = false;
            this.renderMain(view, mine, targets);
        }
    }

    /**
     * カードと衛星の選択（手札と衛星の選択、コストの内訳・売却・起動の修正）。S0 の 1 枚目と S1 の 2 枚目で共通。
     * 候補とコストはサーバーの `targets`（S1 では 1 枚目が付いた実際の盤面での値）。
     * @param {import('../ui/GameView.js').GameView} view
     * @param {import('./logic/deploy.js').DeployTarget[]} targets
     * @param {import('./logic/deploy.js').DeploySelection} sel
     * @param {boolean} second 2 枚目の選択か
     * @returns {{ chosen: { a: number, b: number } | undefined, pay: ReturnType<typeof import('./logic/common.js').payState> | null }}
     */
    renderPicker(view, targets, sel, second) {
        const me = /** @type {number} */ (view.m.me);
        const area = view.areas.get(me);
        const hand = this.hand;
        const pairs = deployPairs(targets, hand, this.maya, this.price);
        if (sel.cardId !== null && sel.satId !== null && pairs.find((p) => p.a === sel.cardId && p.b === sel.satId)?.usable !== true) {
            sel.satId = null;
        }
        if (sel.cardId === null) {
            sel.satId = null;
            sel.sell = [];
        }
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
        if (chosen !== undefined) {
            const target = chosen.data;
            const quote = deployQuote(target, sel.useMods, { maya: this.maya, price: this.price, sellable: hand.length - 1 });
            sel.useMods = quote.mods;
            this.costBox({
                title: format(_('Deploy ${card} to ${sat} (slot ${tier})'), { card: cardName(chosen.a), sat: view.satName(chosen.b), tier: target.tier }),
                lines: [format(second ? _('Additional deploy cost: ${cost} Maya') : _('Deploy cost: ${cost} Maya'), { cost: quote.cost })],
                quote,
                mods: target.mods,
                sel,
                reserved: [],
                subject: [this.cardPreview(chosen.a)],
                before: {
                    id: 'deploy',
                    label: _('Card to deploy'),
                    badge: _('Deploy'),
                    candidates: allowedCards,
                    selected: [chosen.a],
                    need: 1,
                    max: 1,
                    title: _('Choose a card to deploy'),
                    onChange: (ids) => {
                        sel.cardId = ids[0] ?? null;
                        if (sel.cardId === null) {
                            sel.sell = [];
                        }
                        this.refresh();
                    },
                },
            });
            return { chosen, pay: quote.pay };
        }
        if (sel.cardId !== null || sel.satId !== null || second) {
            view.sheet.show({
                title: sel.cardId === null ? (second ? _('Choose another card to deploy') : _('Choose a card to deploy')) : _('Choose a satellite to deploy to'),
                lines: [sel.cardId === null ? _('Satellites with a note can receive a card') : format(_('Selected: ${card}'), { card: cardName(sel.cardId) })],
            });
        }
        return { chosen: undefined, pay: null };
    }

    /**
     * S0: 通常の配備（1 枚目）の選択。選んだ後、ボーナス保持者は [Deploy another card] / [Draw 1 card] を選べる。
     * @param {import('../ui/GameView.js').GameView} view
     * @param {Record<string, any>} mine
     * @param {import('./logic/deploy.js').DeployTarget[]} targets
     */
    renderMain(view, mine, targets) {
        const sel = this.sel;
        const { chosen, pay } = this.renderPicker(view, targets, sel, false);
        if (sel.cardId === null || sel.satId === null) {
            // 通常の配備が決まっていないときは、ボーナスの選択を持ち越さない
            this.bonusDeclined = false;
            if (sel.extra?.op === 'draw') {
                sel.extra = null;
            }
        }
        const ok = chosen !== undefined && validateDeploy(sel, { pay, canDraw: mine.canDraw === true }).ok;
        const stage = deployStage(sel, { isBonus: this.isBonus, canDraw: mine.canDraw === true, bonusDeclined: this.bonusDeclined, passing: false });
        const drawing = sel.extra?.op === 'draw';
        const selected = chosen === undefined ? '' : format(_(': ${card} to ${sat}'), { card: cardName(chosen.a), sat: view.satName(chosen.b) });
        if (stage === 'bonus') {
            // 1 枚目が決まった。ボーナスの使い方を選ぶまで [Confirm] は出さない
            this.bga.statusBar.setTitle(format(_('${you} will deploy ${card} to ${sat}. Now choose how to use the Action bonus: deploy another card, draw 1 card, or no bonus'), {
                card: cardName(chosen?.a ?? sel.cardId ?? 0),
                sat: view.satName(chosen?.b ?? sel.satId ?? 0),
            }));
            // 1 枚目をサーバーが先に確定（ステージ）し、2 枚目の候補とコストを返す
            this.bga.statusBar.addActionButton(_('Deploy another card'), () => this.stageFirst(ok), { color: 'primary', disabled: !ok });
            if (mine.canDraw === true) {
                this.bga.statusBar.addActionButton(_('Draw 1 card'), () => {
                    sel.extra = { op: 'draw' };
                    this.refresh();
                }, { color: 'primary' });
            }
            this.bga.statusBar.addActionButton(_('No bonus'), () => {
                this.bonusDeclined = true;
                this.refresh();
            }, { color: 'primary' });
        } else {
            const hint = this.isBonus
                ? _('${you} must deploy a card to a satellite, or pass. Then you choose how to use the Action bonus')
                : _('${you} must deploy a card to a satellite, or pass');
            const drawNote = drawing ? `. ${_('You will also draw 1 card')}` : (this.bonusDeclined ? `. ${_('You will not use the bonus')}` : '');
            this.bga.statusBar.setTitle(selected === '' ? `${hint}. ${_('Choose a card and a satellite')}` : `${hint}${selected}${drawNote}`);
            if (stage === 'ready') {
                this.addConfirm(ok);
                if (this.isBonus) {
                    this.addBonusAgain();
                }
            }
        }
        if (sel.cardId !== null || sel.satId !== null) {
            this.addReset();
        }
        this.addPass();
    }

    /**
     * S1: ステージ済み。1 枚目は配備済みで、2 枚目の候補とコストはサーバーの値（1 枚目の効果を含む）。
     * 2 枚目を選ぶ、または 2 枚目なしで [Confirm]。[Reset] で 1 枚目を取り消す（山札から引いた後は不可）。
     * @param {import('../ui/GameView.js').GameView} view
     * @param {Record<string, any>} _mine
     * @param {import('./logic/deploy.js').DeployTarget[]} targets
     */
    renderSecond(view, _mine, targets) {
        const sel = this.second;
        const info = this.stageInfo;
        const { chosen, pay } = this.renderPicker(view, targets, sel, true);
        const ok = validateDeploy(sel, { pay, canDraw: false, optional: true }).ok;
        const first = info.first === null
            ? ''
            : format(_('${you} have deployed ${card} to ${sat}.'), { card: cardName(info.first.card), sat: view.satName(info.first.satId) });
        const body = chosen === undefined
            ? _('Choose another card to deploy, or confirm')
            : format(_('Deploy another card: ${card} to ${sat}'), { card: cardName(chosen.a), sat: view.satName(chosen.b) });
        // [Reset] を押せないときは、理由をタイトルにも出す
        const note = info.canReset ? '' : ` (${RESET_BLOCKED_TEXT()})`;
        this.bga.statusBar.setTitle(`${first} ${body}${note}`.trim());
        this.bga.statusBar.addActionButton(_('Confirm'), () => {
            if (ok) {
                this.send('actConfirmDeploy', deploySecondArgs(sel), this.secondChoiceOf(sel));
            }
        }, { color: 'primary', disabled: !ok });
        if (sel.cardId !== null || sel.satId !== null) {
            this.bga.statusBar.addActionButton(_('Cancel the bonus'), () => {
                this.second = deploySelectionFrom(null);
                this.refresh();
            }, { color: 'secondary' });
        }
        this.addUndoStage(info);
    }

    /**
     * [Reset]（S1）: ステージした 1 枚目を取り消す。山札から引いた後は押せない状態で出し、理由を表示する。
     * @param {import('./logic/deploy.js').DeployStageInfo} info
     */
    addUndoStage(info) {
        const blocked = !info.canReset;
        const reason = RESET_BLOCKED_TEXT();
        this.bga.statusBar.addActionButton(_('Reset'), () => this.resetStage(), {
            color: 'secondary',
            disabled: blocked,
            title: blocked ? reason : _('Cancel the first deployment and choose again'),
        });
    }

    /**
     * 1 枚目をサーバーに送ってステージする（S0 → S1）。結果は通知 deployStaged で届く。
     * @param {boolean} ok 1 枚目の選択が確定できる状態か
     */
    async stageFirst(ok) {
        const sel = this.sel;
        if (!ok || this.sending || sel.cardId === null || sel.satId === null) {
            return;
        }
        this.sending = true;
        try {
            await this.bga.actions.performAction('actStageDeploy', deployStageArgs(sel), { checkAction: false });
        } catch {
            // エラーは BGA が表示する。選択はそのまま残す
        }
        this.sending = false;
    }

    /** ステージした 1 枚目を取り消す（S1 → S0）。結果は通知 deployStageReset で届く。 */
    async resetStage() {
        if (this.sending || !this.stageInfo.canReset) {
            return;
        }
        this.sending = true;
        try {
            await this.bga.actions.performAction('actResetStage', {}, { checkAction: false });
        } catch {
            // エラーは BGA が表示する
        }
        this.sending = false;
    }

    /**
     * [Pass] から進む、通常の配備をしないボーナスだけの選択。1 枚引くか、ボーナスなしで見送るか。
     */
    renderPassing() {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const sel = this.sel;
        sel.cardId = null;
        sel.satId = null;
        sel.sell = [];
        sel.useMods = [];
        const drawing = sel.extra?.op === 'draw';
        view.sheet.show({
            title: _('Pass the deployment'),
            lines: [drawing ? _('You will draw 1 card (Action bonus)') : _('You will not deploy a card. Use the Action bonus to draw 1 card, or pass without it')],
        });
        if (drawing) {
            this.bga.statusBar.setTitle(_('${you} will pass the deployment and draw 1 card (Action bonus). Confirm to finish'));
            this.addConfirm(true);
            this.addBonusAgain();
        } else {
            this.bga.statusBar.setTitle(_('${you} will not deploy a card. Choose: draw 1 card (Action bonus), or pass without the bonus'));
            this.bga.statusBar.addActionButton(_('Draw 1 card'), () => {
                sel.extra = { op: 'draw' };
                this.refresh();
            }, { color: 'primary' });
            this.bga.statusBar.addActionButton(_('Pass without the bonus'), () => this.send('actConfirmDeploy', deployActionArgs({}), { op: 'pass' }), { color: 'primary' });
        }
        this.bga.statusBar.addActionButton(_('Back'), () => {
            this.reset();
            this.refresh();
        }, { color: 'secondary' });
    }

    /** ボーナスの選択をやり直す（選び終わった後） */
    addBonusAgain() {
        this.bga.statusBar.addActionButton(_('Choose the bonus again'), () => {
            this.sel.extra = null;
            this.bonusDeclined = false;
            this.refresh();
        }, { color: 'secondary' });
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
        }
        return choice;
    }

    /**
     * S1 で送る内容を、サーバーの myChoice と同じ形にする（2 枚目だけが意味を持つ）。
     * @param {import('./logic/deploy.js').DeploySelection} sel
     * @returns {Record<string, any>}
     */
    secondChoiceOf(sel) {
        return {
            op: 'staged',
            extra: sel.cardId !== null && sel.satId !== null
                ? { op: 'deploy', card: sel.cardId, sat: sel.satId, sell: [...sel.sell], useMods: [...sel.useMods] }
                : { op: 'pass' },
        };
    }

    addReset() {
        this.bga.statusBar.addActionButton(_('Reset'), () => {
            this.reset();
            this.refresh();
        }, { color: 'secondary' });
    }

    addPass() {
        this.bga.statusBar.addActionButton(_('Pass'), () => {
            if (passNeedsBonusChoice({ isBonus: this.isBonus, canDraw: this.mine?.canDraw === true })) {
                // ボーナスがあるときは、1 枚引くかどうかを選んでから確定する
                this.reset();
                this.passing = true;
                this.refresh();
                return;
            }
            this.send('actConfirmDeploy', deployActionArgs({}), { op: 'pass' });
        }, { color: 'secondary' });
    }
}
