/**
 * 31 EffectWindowResolve（全員並行。選択が必要な効果のあるプレイヤーだけがアクティブ）: 効果の問いに答える（仕様書 008 §5、011 §6）。
 * 問いは 2 種類。ORDER = 衝突する効果の処理順（初期はデフォルトの順。並べ替えて [Confirm order]）。EFFECT = 1 つの効果の [Use] / [Skip]
 * （フィールド 8 種の入力、代価、結果の見込み、売却の同梱）。窓の中の選択は確定したら取り消せない。
 *
 * 状態 31 の args は入場時のもの。答えるたびに残りの問いが変わるので、サーバーが本人へ N34 effectQuestions を送る（通知のハンドラが update を呼ぶ）。
 * 他のプレイヤーの待機表示（誰が・どのカードの選択中か。カードは公開情報）は、N35 effectWaiting で更新する。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { directionGlyph, directionLabel } from '../board/Highlights.js';
import { cardInfo, effectTextHtml } from '../cards/card-text.js';
import { createCard } from '../cards/CardView.js';
import { keyOfNameId, nameOfNameId } from '../cards/name-id.js';
import { CardPicker } from '../ui/CardPicker.js';
import { el } from '../ui/dom.js';
import { ActionState } from './ActionState.js';
import { format, privateOf } from './logic/common.js';
import {
  applyHandChange, cardOptions, isEstimated, normalizePairs, pairPayState, pickSatellite, satOptions, selectedCards, soleSatellite,
} from './logic/effect-pairs.js';
import {
  assignmentLayout, cardsInHand, initialOrder, initialValues, mayaToPay, moveCard, moveEntry, moveTargets, pairCost, pairKind, parseWaiting, parseWindow, reservedCards, salesNeeded, satellitePicks, subjectSatellite, useEffectArgs, validateQuestion,
} from './logic/effects.js';
import { cardName, costNote, modName } from './texts.js';

/**
 * 盤面・衛星カードから直接選ぶための入口（最初の該当フィールドだけ。AREA と同じ扱い）。手札から選ぶ欄は HandTargets（B-2）が受け持つ。
 * @typedef {{ kind: 'satellite', field: import('./logic/effects.js').Field, value: any, pick: (satId: number) => void }
 *   | { kind: 'pairs', picks: Map<number, { state: 'selectable' | 'sellable' | 'selected' | 'blocked', note?: string }>, plans: Map<number, { text: string }[]>, pick: (satId: number) => void }
 *   | { kind: 'assignment', assignment: Record<string, any>, sats: number[], picked: number | null, pickCard: (card: number) => void, pickSat: (satId: number) => void }} DirectDriver
 */

export class EffectWindowState extends ActionState {
    /**
     * @param {any} game
     * @param {Bga} bga
     */
    constructor(game, bga) {
        super(game, bga);
        /** @type {import('./logic/effects.js').Question[]} */
        this.questions = [];
        /** @type {{ playerId: number, sources: string[] }[]} */
        this.waiting = [];
        /** @type {Map<number, { sig: string, values: Record<string, any> }>} */
        this.values = new Map();
        /** @type {Map<number, number[]>} */
        this.sells = new Map();
        /** @type {Map<string, number[]>} */
        this.orders = new Map();
        /** @type {Set<number>} */
        this.busy = new Set();
        /** @type {Map<number, number>} 付け替えで押して選んだカード（entryId → カード番号） */
        this.pickedCards = new Map();
        /** @type {Map<number, import('./logic/effect-pairs.js').Pending>} 配備の組で、押したがまだ組になっていないカード・衛星（entryId → 保留） */
        this.pairPending = new Map();
    }

    /**
     * @param {Record<string, any>} args
     * @param {boolean} isActive
     */
    onEnteringState(args, isActive) {
        const view = this.game.view;
        if (view === null) {
            return;
        }
        view.effectUi = this;
        this.values.clear();
        this.sells.clear();
        this.orders.clear();
        this.busy.clear();
        this.pickedCards.clear();
        this.pairPending.clear();
        this.waiting = parseWaiting(args.waiting, view.m.me);
        this.questions = parseWindow(privateOf(args, view.m.me)).questions;
        super.onEnteringState(args, isActive);
    }

    onLeavingState() {
        if (this.game.view?.effectUi === this) {
            this.game.view.effectUi = null;
        }
        super.onLeavingState();
    }

    /**
     * 問いを取り込む。
     * @param {Record<string, any> | null} mine
     */
    load(mine) {
        this.mine = mine;
        this.questions = parseWindow(mine).questions;
    }

    /**
     * N34 effectQuestions: 答えた後の、残りの問い。
     * @param {Record<string, any>} a
     */
    update(a) {
        this.load(a);
        this.refresh();
    }

    /**
     * N35 effectWaiting: 待っている人と、その人のカード。
     * @param {unknown} waiting
     */
    setWaiting(waiting) {
        this.waiting = parseWaiting(waiting, this.game.view?.m.me ?? null);
        this.refresh();
    }

    /** 付け替え・問いの対象の表示（衛星カードの予定の札、配備カードの選択、強調）を外す */
    clearAssignUi() {
        const view = this.game.view;
        const me = view?.m.me ?? null;
        if (view === null) {
            return;
        }
        view.pieces.setSubjects(null);
        if (me !== null) {
            const area = view.areas.get(me);
            area?.setCardPicks(null);
            area?.setSatPlans(null);
            area?.setSubjects(null);
        }
    }

    clearUi() {
        super.clearUi();
        this.clearAssignUi();
    }

    /** N26: 窓が終わった */
    close() {
        this.questions = [];
        this.waiting = [];
        this.mine = null;
        this.clearUi();
        this.bga.statusBar.removeActionButtons();
    }

    refresh() {
        const view = this.game.view;
        if (view === null) {
            return;
        }
        view.stateFrame.leaveSelection();
        view.panel.hide();
        const me = view.m.me;
        if (me !== null) {
            view.areas.get(me)?.hand.setSelectable(null);
            view.areas.get(me)?.hand.setPlanned(null);
            view.areas.get(me)?.setSatPicks(null);
            this.clearAssignUi();
        }
        view.satelliteHandler = null;
        this.resetHandFields();
        this.bga.statusBar.removeActionButtons();
        if (this.sellMode !== null) {
            this.renderSell();
            return;
        }
        if (me === null || this.questions.length === 0) {
            this.bga.statusBar.setTitle(this.waitingText());
            if (this.mine !== null) {
                this.addSellButton();
            }
            return;
        }
        this.renderWindow();
        this.addSellButton();
        this.mountHandTargets();
    }

    /** @returns {string} 「Waiting for X (CARD), Y (CARD)」 */
    waitingText() {
        const view = this.game.view;
        if (view === null || this.waiting.length === 0) {
            return _('Waiting for other players');
        }
        const names = this.waiting.map((w) => {
            const name = view.m.players[w.playerId]?.name ?? String(w.playerId);
            return w.sources.length === 0 ? name : `${name} (${w.sources.map(nameOfNameId).join(', ')})`;
        });
        return format(_('Waiting for ${players}'), { players: names.join(', ') });
    }

    renderWindow() {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        /** @type {HTMLElement[]} */
        const body = [];
        /** @type {{ cells: number[], value: number | null, pick: (cell: number) => void } | null} */
        let areaDriver = null;
        /** @type {DirectDriver | null} */
        let satDriver = null;
        /** @type {DirectDriver | null} */
        let assignDriver = null;
        /** @type {DirectDriver | null} */
        let pairsDriver = null;
        for (const q of this.questions) {
            if (q.type === 'ORDER') {
                body.push(this.orderBlock(q));
            } else {
                const block = this.effectBlock(q, (driver) => {
                    areaDriver ??= driver;
                }, (driver) => {
                    if (driver.kind === 'satellite') {
                        satDriver ??= driver;
                    } else if (driver.kind === 'assignment') {
                        assignDriver ??= driver;
                    } else if (driver.kind === 'pairs') {
                        pairsDriver ??= driver;
                    }
                });
                body.push(block);
            }
        }
        view.panel.show({ title: _('Resolve your card effects'), hint: _('Your choices are final once you press the button, and are revealed to everyone when all players are done'), body });
        this.bga.statusBar.setTitle(this.questions.some((q) => q.type === 'ORDER') ? _('${you} must choose the order of your card effects') : _('${you} must resolve your card effects'));
        // 区域の選択は盤面でも選べる（最初の AREA フィールドの候補を強調する）
        const driver = /** @type {{ cells: number[], value: number | null, pick: (cell: number) => void } | null} */ (areaDriver);
        if (driver !== null) {
            view.stateFrame.selectCells(driver.cells.map((cell) => ({ cell, kind: cell === driver.value ? 'selected' : 'selectable' })), driver.pick);
        }
        this.showDirect(/** @type {DirectDriver | null} */ (satDriver), driver !== null, /** @type {DirectDriver | null} */ (assignDriver), /** @type {DirectDriver | null} */ (pairsDriver));
        this.showSubjects(driver !== null || satDriver !== null || pairsDriver !== null);
    }

    /**
     * 問いが特定の衛星に紐づく効果（P082 など）の、対象の衛星を強調する。盤面のコマ・区域の枠と、自分の衛星カード。
     * @param {boolean} cellsInUse 盤面の区域の枠を、区域・衛星の選択がすでに使っているか
     */
    showSubjects(cellsInUse) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const me = view.m.me;
        const ids = new Set(this.questions.flatMap((q) => {
            const satId = q.type === 'EFFECT' ? subjectSatellite(q) : null;
            return satId === null ? [] : [satId];
        }));
        if (me === null || ids.size === 0) {
            return;
        }
        view.pieces.setSubjects(ids);
        view.areas.get(me)?.setSubjects(ids);
        if (!cellsInUse) {
            const cells = [...ids].flatMap((satId) => {
                const cell = view.pieces.satelliteCell(satId);
                return cell === null ? [] : [{ cell, kind: /** @type {'selected'} */ ('selected') }];
            });
            if (cells.length > 0) {
                view.stateFrame.selectCells(cells);
            }
        }
    }

    /**
     * 衛星と付け替えの直接選択を出す（最初の該当フィールドだけ）。衛星 = 候補の衛星カードと盤面のコマ。
     * 外すのは refresh / clearUi（衛星の選択・衛星コマの受け口）。
     * @param {DirectDriver | null} sat
     * @param {boolean} hasArea 区域の選択が盤面を使っているか（そのときは衛星の区域の枠を足さない）
     * @param {DirectDriver | null} [assign] 付け替え（配備カードを押して選び、置き先の衛星を押す）
     * @param {DirectDriver | null} [pairs] 配備の組（手札のカードと配備先の衛星。手札側は HandTargets が受け持つ）
     */
    showDirect(sat, hasArea, assign = null, pairs = null) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const me = view.m.me;
        if (me === null) {
            return;
        }
        const area = view.areas.get(me);
        if (sat?.kind === 'satellite') {
            const picks = satellitePicks(sat.field, sat.value);
            area?.setSatPicks(new Map([...picks].map(([satId, state]) => [satId, { state }])), (satId) => sat.pick(satId));
            view.satelliteHandler = (satId) => {
                if (!picks.has(satId)) {
                    return false;
                }
                sat.pick(satId);
                return true;
            };
            if (!hasArea) {
                // 稼働中の候補は、盤面の区域も枠で示す（コマを押しても選べる）
                const cells = [...picks].flatMap(([satId, state]) => {
                    const cell = view.pieces.satelliteCell(satId);
                    return cell === null ? [] : [{ cell, kind: /** @type {'selectable' | 'selected'} */ (state) }];
                });
                if (cells.length > 0) {
                    view.stateFrame.selectCells(cells);
                }
            }
        }
        if (assign?.kind === 'assignment') {
            this.showAssign(assign);
        }
        if (pairs?.kind === 'pairs') {
            this.showPairs(pairs, hasArea);
        }
    }

    /**
     * 配備の組の衛星側: 衛星カード・盤面のコマを押して配備先を選ぶ（II 配備と同じ操作感）。選べる衛星にはコストの札、選べない衛星は理由つきで灰色。
     * 組にした衛星の上には、配備予定のカードを予定の札で出す。
     * @param {Extract<DirectDriver, { kind: 'pairs' }>} d
     * @param {boolean} hasArea
     */
    showPairs(d, hasArea) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const area = view.areas.get(view.m.me ?? -1);
        area?.setSatPicks(d.picks, d.pick);
        area?.setSatPlans(d.plans);
        view.satelliteHandler = (satId) => {
            const pick = d.picks.get(satId);
            if (pick === undefined || pick.state === 'blocked') {
                return false;
            }
            d.pick(satId);
            return true;
        };
        if (!hasArea) {
            const cells = [...d.picks].flatMap(([satId, pick]) => {
                const cell = pick.state === 'blocked' ? null : view.pieces.satelliteCell(satId);
                return cell === null ? [] : [{ cell, kind: /** @type {'selectable' | 'selected'} */ (pick.state === 'selected' ? 'selected' : 'selectable') }];
            });
            if (cells.length > 0) {
                view.stateFrame.selectCells(cells);
            }
        }
    }

    /**
     * 付け替え（P022）: 自分の衛星カードの配備カードを押して選び、置き先の衛星カードを押す。
     * 配備カードは押せる枠、選んだら置き先の衛星が選べる（4 枚の衛星は選べない）。衛星の上に付け替え後の並びを予定の札で出す。
     * @param {Extract<DirectDriver, { kind: 'assignment' }>} d
     */
    showAssign(d) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const area = view.areas.get(view.m.me ?? -1);
        if (area === undefined) {
            return;
        }
        const cards = Object.keys(d.assignment).map(Number);
        area.setCardPicks(new Map(cards.map((c) => [c, c === d.picked ? 'selected' : 'selectable'])), d.pickCard);
        const layout = assignmentLayout(d.assignment, d.sats);
        area.setSatPlans(new Map(d.sats.map((satId) => {
            const names = (layout.get(satId) ?? []).map(cardName);
            return [satId, [{ text: names.length === 0 ? _('After rearranging: no cards') : format(_('After rearranging: ${cards}'), { cards: names.join(', ') }) }]];
        })));
        if (d.picked !== null) {
            const targets = moveTargets(d.assignment, d.picked, d.sats);
            area.setSatPicks(new Map([...targets].map(([satId, state]) => [satId, {
                state,
                note: state === 'blocked' ? _('A satellite holds up to 4 cards') : undefined,
            }])), d.pickSat);
        }
    }

    // ---- ORDER ----

    /**
     * @param {import('./logic/effects.js').OrderQuestion} q
     * @returns {HTMLElement}
     */
    orderBlock(q) {
        const sig = q.entries.map((e) => e.entryId).join(',');
        const order = this.orders.get(sig) ?? initialOrder(q);
        this.orders.set(sig, order);
        const set = (/** @type {number[]} */ next) => {
            this.orders.set(sig, next);
            this.refresh();
        };
        const rows = order.map((entryId, index) => {
            const entry = q.entries.find((e) => e.entryId === entryId);
            const key = entry === undefined ? null : keyOfNameId(entry.cardKey);
            return el('li', { class: 'pe-order-row', dataset: { entry: entryId } }, [
                el('span', { class: 'pe-order-no' }, [String(index + 1)]),
                this.cardChip(entry?.cardKey ?? '', key),
                entry?.forced === true ? el('span', { class: 'pe-order-forced', title: _('Mandatory effect') }, [_('Mandatory')]) : null,
                el('span', { class: 'pe-order-moves' }, [
                    el('button', { type: 'button', class: 'pe-btn pe-btn-small', title: _('Move to the top'), 'aria-label': _('Move to the top'), disabled: index === 0, onClick: () => set(moveEntry(order, entryId, 'top')) }, ['⤒']),
                    el('button', { type: 'button', class: 'pe-btn pe-btn-small', title: _('Move up'), 'aria-label': _('Move up'), disabled: index === 0, onClick: () => set(moveEntry(order, entryId, -1)) }, ['▲']),
                    el('button', { type: 'button', class: 'pe-btn pe-btn-small', title: _('Move down'), 'aria-label': _('Move down'), disabled: index === order.length - 1, onClick: () => set(moveEntry(order, entryId, 1)) }, ['▼']),
                ]),
            ]);
        });
        return el('section', { class: 'pe-choice-section pe-order' }, [
            el('div', { class: 'pe-choice-label' }, [_('The order of these effects changes the result. Choose the order to resolve them (the initial order is the default order)')]),
            el('ol', { class: 'pe-order-list' }, rows),
            el('button', {
                type: 'button',
                class: 'pe-btn pe-btn-primary',
                onClick: () => this.act('actOrderEffects', { entryIds: order }, sig.length),
            }, [_('Confirm order')]),
        ]);
    }

    // ---- EFFECT ----

    /**
     * カード名のチップ（拡大表示つき）。
     * @param {string} nameId
     * @param {string | null} key
     * @returns {HTMLElement}
     */
    cardChip(nameId, key) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const chip = el('span', { class: 'pe-card-chip', tabindex: 0 }, [nameOfNameId(nameId)]);
        if (key !== null) {
            view.tooltip.bind(chip, key);
        }
        return chip;
    }

    /**
     * 問い 1 つ分の入力値（問いの内容が変わったら初期値に戻す）。
     * @param {import('./logic/effects.js').EffectQuestion} q
     * @returns {Record<string, any>}
     */
    valuesOf(q) {
        const sig = JSON.stringify([q.fields, q.cost.kind]);
        const existing = this.values.get(q.entryId);
        if (existing !== undefined && existing.sig === sig) {
            return existing.values;
        }
        const values = initialValues(q);
        this.values.set(q.entryId, { sig, values });
        this.sells.delete(q.entryId);
        this.pairPending.delete(q.entryId);
        return values;
    }

    /**
     * @param {import('./logic/effects.js').EffectQuestion} q
     * @param {(driver: { cells: number[], value: number | null, pick: (cell: number) => void }) => void} onArea
     * @param {(driver: DirectDriver) => void} [onDirect]
     * @returns {HTMLElement}
     */
    effectBlock(q, onArea, onDirect = () => {}) {
        const values = this.valuesOf(q);
        const key = keyOfNameId(q.cardKey);
        const info = key === null ? null : cardInfo(key);
        const sells = (this.sells.get(q.entryId) ?? []).filter((id) => this.hand.includes(id));
        const changed = () => this.refresh();

        const head = el('div', { class: 'pe-effect-head' }, [
            key === null ? null : this.smallCard(key),
            el('div', { class: 'pe-effect-title' }, [
                el('strong', {}, [info?.name ?? nameOfNameId(q.cardKey)]),
                q.mandatory ? el('span', { class: 'pe-order-forced' }, [_('Mandatory')]) : null,
                info?.text === undefined ? null : this.effectText(info.text),
            ]),
        ]);

        const fields = q.fields.map((field) => this.fieldEditor(q, field, values, onArea, changed, onDirect));
        const preview = this.previewLine(q);

        // 代価と売却の同梱
        const need = salesNeeded(q, values, { maya: this.maya, price: this.price, hand: this.hand });
        const cost = this.costLine(q, need);
        const reserved = reservedCards(q, values);
        const prompt = need !== null && need > 0
            ? this.sellPrompt({ need, short: Math.max(0, mayaToPay(q, values) - this.maya) }, reserved, sells, (ids) => {
                this.sells.set(q.entryId, ids);
                changed();
            }, `sell:${q.entryId}`)
            : null;
        const check = validateQuestion(q, values);
        const payable = q.cost.kind === 'DISCARD_COST' ? q.cost.canPay !== false : need !== null;
        const sellOk = need === null || sells.length >= need;
        const ready = check.ok && payable && sellOk && !this.busy.has(q.entryId);
        const sell = need !== null && need > 0 ? sells : [];

        return el('section', { class: 'pe-choice-section pe-effect', dataset: { entry: q.entryId } }, [
            head,
            cost,
            ...fields,
            preview,
            prompt,
            el('div', { class: 'pe-effect-buttons' }, [
                el('button', {
                    type: 'button',
                    class: 'pe-btn pe-btn-primary',
                    disabled: !ready,
                    onClick: () => {
                        if (ready) {
                            this.act('actUseEffect', useEffectArgs(q, values, sell), q.entryId);
                        }
                    },
                }, [_('Use')]),
                q.mandatory ? null : el('button', {
                    type: 'button',
                    class: 'pe-btn',
                    disabled: this.busy.has(q.entryId),
                    title: _('The effect is not used and cannot be used for this trigger later'),
                    onClick: () => this.act('actSkipEffect', { entryId: q.entryId }, q.entryId),
                }, [_('Skip')]),
                check.ok ? null : el('span', { class: 'pe-effect-need' }, [this.needText(check.reason, check.key, q)]),
            ]),
        ]);
    }

    /**
     * @param {string | undefined} reason
     * @param {string | undefined} key
     * @param {import('./logic/effects.js').EffectQuestion} q
     * @returns {string}
     */
    needText(reason, key, q) {
        const field = q.fields.find((f) => f.key === key);
        if (field?.type === 'PAIRS' && pairKind(q.preview) === 'deploy') {
            return format(_('Choose: ${field}'), { field: _('a card to deploy and a satellite') });
        }
        const label = field === undefined ? '' : this.fieldLabel(field);
        return format(_('Choose: ${field}'), { field: label });
    }

    /**
     * @param {import('./logic/effects.js').Field} field
     * @returns {string}
     */
    fieldLabel(field) {
        switch (field.type) {
            case 'SATELLITE':
                return _('a satellite');
            case 'AREA':
                return _('an area');
            case 'DIRECTION':
                return _('a direction');
            case 'AMOUNT':
                return field.key === 'tier' ? _('how much to pay') : _('an amount');
            case 'CARDS':
                return field.min === field.max ? format(_('${n} card(s)'), { n: field.min }) : format(_('${min} to ${max} card(s)'), { min: field.min, max: field.max });
            case 'PAIRS':
                return _('an option from the list');
            case 'ASSIGNMENT':
                return _('where each card goes');
            case 'USE_MODS':
                return _('Activated effects to use (lower the cost)');
            default:
                return field.key;
        }
    }

    /**
     * 効果文（英語。`[Maya]` などの記号はアイコンにする）。
     * @param {string} text
     * @returns {HTMLElement}
     */
    effectText(text) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const p = el('p', { class: 'pe-effect-text' });
        p.innerHTML = effectTextHtml(text, view.env.imgUrl);
        return p;
    }

    /**
     * @param {string} key
     * @returns {HTMLElement}
     */
    smallCard(key) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const card = createCard(view.env, key, { classes: ['pe-picker-card'] });
        view.tooltip.bind(card, key);
        return card;
    }

    /**
     * 代価の表示。
     * @param {import('./logic/effects.js').EffectQuestion} q
     * @param {number | null} need 必要な売却の枚数（売っても届かないときは null）
     * @returns {HTMLElement | null}
     */
    costLine(q, need) {
        const cost = q.cost;
        switch (cost.kind) {
            case 'PAY_MAYA':
                return el('div', { class: 'pe-effect-cost' }, [
                    format(_('Cost: ${amount} Maya'), { amount: cost.amount ?? 0 }),
                    need === null ? ` — ${_('not enough Maya, even if you sell your cards')}` : need > 0 ? ` — ${format(_('needs ${short} more Maya (sell ${need} card(s))'), { short: Math.max(0, (cost.amount ?? 0) - this.maya), need })}` : '',
                ]);
            case 'PAY_MAYA_TIER':
                return el('div', { class: 'pe-effect-cost' }, [
                    _('Cost: pay Maya by tier'),
                    ` — ${Object.entries(cost.tiers ?? {}).map(([pay, amount]) => `${pay} → ${amount}`).join(', ')}`,
                    need === null ? ` — ${_('not enough Maya, even if you sell your cards')}` : need > 0 ? ` — ${format(_('needs ${short} more Maya (sell ${need} card(s))'), { short: Math.max(0, Math.min(...Object.keys(cost.tiers ?? {}).map(Number)) - this.maya), need })}` : '',
                ]);
            case 'DISCARD_COST':
                return el('div', { class: 'pe-effect-cost' }, [format(_('Cost: discard ${n} card(s) from your hand (no sale value)'), { n: cost.amount ?? 1 }), cost.canPay === false ? ` — ${_('not enough cards')}` : '']);
            default:
                return null;
        }
    }

    /**
     * サーバーが計算した結果の見込み。
     * @param {import('./logic/effects.js').EffectQuestion} q
     * @returns {HTMLElement | null}
     */
    previewLine(q) {
        const p = q.preview;
        if (p.areas !== undefined) {
            return el('div', { class: 'pe-effect-preview' }, [format(_('Moves ${areas} area(s), using ${fuel} fuel'), { areas: p.areas, fuel: p.fuel })]);
        }
        if (p.discard !== undefined) {
            return el('div', { class: 'pe-effect-preview' }, [format(_('Discard ${n} card(s)'), { n: p.discard })]);
        }
        if (p.action !== undefined) {
            return el('div', { class: 'pe-effect-preview' }, [format(_('Declare the Action bonus of ${action}'), { action: String(p.action) })]);
        }
        return null;
    }

    /**
     * フィールド 1 つの入力部品。
     * @param {import('./logic/effects.js').EffectQuestion} q
     * @param {import('./logic/effects.js').Field} field
     * @param {Record<string, any>} values
     * @param {(driver: { cells: number[], value: number | null, pick: (cell: number) => void }) => void} onArea
     * @param {() => void} changed
     * @param {(driver: DirectDriver) => void} [onDirect]
     * @returns {HTMLElement}
     */
    fieldEditor(q, field, values, onArea, changed, onDirect = () => {}) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const set = (/** @type {any} */ value) => {
            values[field.key] = value;
            changed();
        };
        const wrap = (/** @type {HTMLElement[]} */ children) => el('div', { class: 'pe-field-editor', dataset: { field: field.key, type: field.type } }, [
            el('div', { class: 'pe-choice-label' }, [this.fieldLabel(field)]),
            ...children,
        ]);
        /** @param {string} label @param {boolean} on @param {() => void} fn @param {string} [title] */
        const chip = (label, on, fn, title) => el('button', { type: 'button', class: `pe-chip-btn${on ? ' pe-selected' : ''}`, 'aria-pressed': on ? 'true' : 'false', title, onClick: fn }, [label]);

        switch (field.type) {
            case 'SATELLITE':
                // 衛星カードと盤面のコマを押して選ぶ（チップは補助。選んだ衛星の表示を兼ねる）
                onDirect({ kind: 'satellite', field, value: values[field.key], pick: (satId) => set(satId) });
                return wrap([el('div', { class: 'pe-chip-row' }, field.candidates.map((id) => chip(view.satName(Number(id)), String(values[field.key]) === String(id), () => {
                    view.focusSatellite(Number(id));
                    set(Number(id));
                })))]);
            case 'AREA': {
                onArea({ cells: field.candidates.map(Number), value: values[field.key] === null ? null : Number(values[field.key]), pick: (cell) => set(cell) });
                return wrap([el('div', { class: 'pe-chip-row' }, field.candidates.map((cell) => chip(format(_('Area ${cell}'), { cell }), String(values[field.key]) === String(cell), () => {
                    view.board.scrollToCell(Number(cell));
                    set(Number(cell));
                })))]);
            }
            case 'DIRECTION':
                return wrap([el('div', { class: 'pe-chip-row' }, field.candidates.map((dir) => chip(`${directionGlyph(String(dir))} ${directionLabel(String(dir))}`, values[field.key] === dir, () => set(String(dir)))))]);
            case 'AMOUNT': {
                if (field.candidates.length === 0) {
                    return wrap([el('input', {
                        type: 'number',
                        class: 'pe-number',
                        min: field.min,
                        max: field.max,
                        value: values[field.key] ?? '',
                        onChange: (/** @type {Event} */ e) => set(/** @type {HTMLInputElement} */ (e.target).value === '' ? null : Number(/** @type {HTMLInputElement} */ (e.target).value)),
                    })]);
                }
                const tiers = q.cost.kind === 'PAY_MAYA_TIER' ? (q.cost.tiers ?? {}) : {};
                return wrap([el('div', { class: 'pe-chip-row' }, field.candidates.map((n) => chip(
                    field.key === 'tier' && tiers[String(n)] !== undefined ? format(_('Pay ${pay} Maya (${amount})'), { pay: n, amount: tiers[String(n)] }) : String(n),
                    Number(values[field.key]) === Number(n) && values[field.key] !== null,
                    () => set(Number(n)),
                )))]);
            }
            case 'CARDS': {
                if (cardsInHand(field, this.hand)) {
                    // 候補がすべて手札: 手札のゾーンで選ぶ（欄には選んだカードの一覧だけ。他の欄があれば [Choose from hand] で切り替える）
                    const current = Array.isArray(values[field.key]) ? values[field.key].map(Number) : [];
                    const label = this.fieldLabel(field);
                    const slot = this.handField({
                        id: `${q.entryId}:${field.key}`,
                        label,
                        badge: _('Discard'),
                        candidates: field.candidates.map(Number),
                        selected: current,
                        need: field.min,
                        max: field.max,
                        title: format(_('${label} (${n} of ${max} chosen)'), { label, n: current.length, max: field.max }),
                        onChange: (ids) => set(ids),
                    });
                    return el('div', { class: 'pe-field-editor', dataset: { field: field.key, type: field.type } }, [slot]);
                }
                const picker = new CardPicker(view, { ids: field.candidates.map(Number), selected: values[field.key], max: field.max, onChange: (ids) => set(ids) });
                return wrap([picker.root]);
            }
            case 'PAIRS': {
                const kind = pairKind(q.preview);
                if (kind === 'deploy') {
                    return this.deployPairsEditor(q, field, values, set, onDirect);
                }
                const list = Array.isArray(values[field.key]) ? values[field.key] : [];
                return wrap([el('div', { class: 'pe-pair-list' }, field.candidates.map((pair) => {
                    const on = list.some((/** @type {any} */ p) => JSON.stringify(p) === JSON.stringify(pair));
                    const cost = pairCost(q.preview, pair);
                    const label = kind === 'deploy'
                        ? format(_('${card} to ${sat}'), { card: cardName(Number(pair[0])), sat: view.satName(Number(pair[1])) })
                        : kind === 'link'
                            ? format(_('${sat} to area ${cell}'), { sat: view.satName(Number(pair[0])), cell: pair[1] })
                            : JSON.stringify(pair);
                    return chip(cost === null ? label : `${label} — ${format(_('cost ${cost}'), { cost })}`, on, () => {
                        if (on) {
                            set(list.filter((/** @type {any} */ p) => JSON.stringify(p) !== JSON.stringify(pair)));
                        } else if (field.max === 1) {
                            set([pair]);
                        } else if (list.length < field.max) {
                            set([...list, pair]);
                        }
                    });
                }))]);
            }
            case 'ASSIGNMENT': {
                const sats = (Array.isArray(q.preview.satellites) ? q.preview.satellites : []).map(Number);
                const current = values[field.key] ?? {};
                const picked = this.pickedCards.get(q.entryId) ?? null;
                const move = (/** @type {number} */ card, /** @type {number} */ satId) => {
                    set(moveCard(current, card, satId, sats));
                };
                const pickCard = (/** @type {number} */ card) => {
                    if (this.pickedCards.get(q.entryId) === card) {
                        this.pickedCards.delete(q.entryId);
                    } else {
                        this.pickedCards.set(q.entryId, card);
                    }
                    changed();
                };
                // カード → 置き先の衛星の順に押す（押したカードは選んだ状態のままにしない）
                onDirect({
                    kind: 'assignment',
                    assignment: current,
                    sats,
                    picked,
                    pickCard,
                    pickSat: (satId) => {
                        const card = this.pickedCards.get(q.entryId);
                        if (card === undefined) {
                            return;
                        }
                        this.pickedCards.delete(q.entryId);
                        move(card, satId);
                    },
                });
                return wrap([
                    el('p', { class: 'pe-assign-hint' }, [_('Click a card on your board, then the satellite to move it to. You can also use the lists below')]),
                    el('div', { class: 'pe-assign' }, field.candidates.map((c) => {
                        const card = Number(c.card);
                        const targets = moveTargets(current, card, sats);
                        return el('label', { class: 'pe-assign-row' }, [
                            el('button', {
                                type: 'button',
                                class: `pe-chip-btn${picked === card ? ' pe-selected' : ''}`,
                                'aria-pressed': picked === card ? 'true' : 'false',
                                onClick: () => pickCard(card),
                            }, [cardName(card)]),
                            el('select', {
                                onChange: (/** @type {Event} */ e) => {
                                    this.pickedCards.delete(q.entryId);
                                    move(card, Number(/** @type {HTMLSelectElement} */ (e.target).value));
                                },
                            }, sats.map((satId) => el('option', { value: satId, selected: Number(current[card]) === satId, disabled: targets.get(satId) === 'blocked' }, [view.satName(satId)]))),
                        ]);
                    })),
                ]);
            }
            case 'USE_MODS': {
                const list = Array.isArray(values[field.key]) ? values[field.key] : [];
                return wrap([el('div', { class: 'pe-mods' }, field.candidates.map((hostKey) => el('label', { class: 'pe-mod' }, [
                    el('input', {
                        type: 'checkbox',
                        checked: list.includes(hostKey),
                        onChange: (/** @type {Event} */ e) => set(/** @type {HTMLInputElement} */ (e.target).checked ? [...list, hostKey] : list.filter((/** @type {string} */ k) => k !== hostKey)),
                    }),
                    el('span', {}, [format(_('Use ${name} (activated effect)'), { name: modName(String(hostKey)) })]),
                ])))]);
            }
            default:
                return wrap([]);
        }
    }

    /**
     * 配備の組（kind: deploy。R005・P011・P045・P074）: 手札のゾーンでカードを押し、衛星カード・盤面のコマで配備先を押す（どちらが先でもよい。B-3）。
     * 配備先が 1 機だけの効果はカードを押した時点で組が決まる。欄には選んだ組（カード to 衛星 — cost n）の一覧だけを出す。
     * 判定は logic/effect-pairs.js の純関数。ここは描画と、保留（押したがまだ組にならないカード・衛星）の保持だけ。
     * @param {import('./logic/effects.js').EffectQuestion} q
     * @param {import('./logic/effects.js').Field} field
     * @param {Record<string, any>} values
     * @param {(value: any) => void} set
     * @param {(driver: DirectDriver) => void} onDirect
     * @returns {HTMLElement}
     */
    deployPairsEditor(q, field, values, set, onDirect) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const me = view.m.me;
        const targetId = `${q.entryId}:${field.key}`;
        // 同じ問いの捨てるカード（P074）は配備できない
        const taken = new Set(q.fields.filter((f) => f.type === 'CARDS').flatMap((f) => (Array.isArray(values[f.key]) ? values[f.key] : [])).map(Number));
        const candidates = normalizePairs(field.candidates).filter((p) => !taken.has(p[0]));
        const chosen = normalizePairs(values[field.key]);
        const stored = this.pairPending.get(q.entryId) ?? { card: null, sat: null };
        const pending = {
            card: stored.card !== null && candidates.some((p) => p[0] === stored.card) ? stored.card : null,
            sat: stored.sat !== null && candidates.some((p) => p[1] === stored.sat) ? stored.sat : null,
        };
        const max = field.max;
        const sats = me === null ? [] : view.m.players[me].satellites;
        const satIds = sats.map((s) => s.satId);
        const deployed = Object.fromEntries(sats.map((s) => [s.satId, s.cards.length]));
        const state = { chosen, pending };
        const sole = soleSatellite(candidates);
        /** @param {import('./logic/effect-pairs.js').PairState} next */
        const commit = (next) => {
            this.pairPending.set(q.entryId, next.pending);
            if (next.pending.card !== null || next.pending.sat !== null) {
                // 組の途中は、手札のゾーンを配備の欄に保つ（他の欄が未完了でも次の欄へ移らない）
                this.handActive = targetId;
            } else {
                // 組ができた: 次の未完了の欄（捨てるカード・売るカード）へ移る（なければ配備の欄のまま）
                this.handActive = undefined;
            }
            set(next.chosen.map((pair) => field.candidates.find((c) => Number(c[0]) === pair[0] && Number(c[1]) === pair[1]) ?? pair));
        };

        // 手札側: 押せるカード = 組にできるカード + 選んでいるカード（押し直して取り消す）
        const options = cardOptions(candidates, chosen, max, deployed, pending.sat);
        const selected = selectedCards(state);
        const allowed = new Set([...options].filter(([, block]) => block === null).map(([card]) => card));
        for (const card of selected) {
            allowed.add(card);
        }
        /** @type {Map<number, string>} */
        const reasons = new Map();
        for (const card of this.hand) {
            if (allowed.has(card)) {
                continue;
            }
            const block = options.get(card);
            reasons.set(card, block === undefined ? _('This effect cannot deploy this card')
                : block === 'max' ? format(_('You can deploy up to ${max} card(s) with this effect'), { max })
                    : block === 'full' ? _('A satellite holds up to 4 cards')
                        : _('Cannot deploy this card here'));
        }
        const itemText = (/** @type {number} */ card) => {
            const index = chosen.findIndex((p) => p[0] === card);
            if (index < 0) {
                return format(_('${card} — choose a satellite'), { card: cardName(card) });
            }
            const pair = chosen[index];
            const cost = pairCost(q.preview, pair);
            const base = format(_('${card} to ${sat}'), { card: cardName(card), sat: view.satName(pair[1]) });
            const note = cost === null ? '' : ` — ${format(_('cost ${cost}'), { cost })}${isEstimated(index) ? ` (${_('estimated')})` : ''}`;
            return `${base}${note}`;
        };
        const label = _('Cards to deploy');
        const title = pending.card !== null
            ? format(_('Choose a satellite to deploy ${card} to'), { card: cardName(pending.card) })
            : pending.sat !== null
                ? format(_('Choose a card to deploy to ${sat}'), { sat: view.satName(pending.sat) })
                : format(_('${label} (${n} of ${max} chosen)'), { label, n: chosen.length, max });
        const slot = this.handField({
            id: targetId,
            label,
            badge: _('Deploy'),
            candidates: [...allowed],
            selected,
            need: field.min,
            max,
            title,
            reasons,
            itemText,
            onChange: (ids) => commit(applyHandChange(candidates, state, ids, { max, deployed })),
        });

        // 衛星側: 衛星カードと盤面のコマ
        const satReasons = satOptions(candidates, chosen, max, deployed, satIds, pending.card);
        const base = max === 1 ? [] : chosen;
        const funds = { maya: this.maya, price: this.price, handCount: this.hand.length, otherReserved: taken.size };
        /** @type {Map<number, { state: 'selectable' | 'sellable' | 'selected' | 'blocked', note?: string }>} */
        const picks = new Map();
        for (const satId of satIds) {
            const reason = satReasons.get(satId) ?? null;
            if (pending.sat === satId) {
                picks.set(satId, { state: 'selected' });
            } else if (reason === null && pending.card !== null) {
                const { cost, pay } = pairPayState(q.preview, base, [pending.card, satId], funds);
                const note = `${costNote(cost, pay)}${isEstimated(base.length) ? ` (${_('estimated')})` : ''}`;
                picks.set(satId, { state: pay.kind === 'short' ? 'blocked' : pay.kind === 'free' ? 'selectable' : 'sellable', note });
            } else if (reason === null) {
                picks.set(satId, { state: 'selectable' });
            } else {
                picks.set(satId, {
                    state: 'blocked',
                    note: pending.card === null ? undefined : reason === 'full' ? _('A satellite holds up to 4 cards') : _('Cannot deploy this card here'),
                });
            }
        }
        /** @type {Map<number, { text: string }[]>} */
        const plans = new Map();
        for (const pair of chosen) {
            plans.set(pair[1], [...(plans.get(pair[1]) ?? []), { text: format(_('Deploying: ${card}'), { card: cardName(pair[0]) }) }]);
        }
        onDirect({
            kind: 'pairs',
            picks,
            plans,
            pick: (satId) => {
                if (picks.get(satId)?.state === 'blocked') {
                    return;
                }
                commit(pickSatellite(candidates, state, { max, deployed, sat: satId }));
            },
        });
        return el('div', { class: 'pe-field-editor', dataset: { field: field.key, type: field.type } }, [
            sole === null ? el('p', { class: 'pe-assign-hint' }, [_('Click a card in your hand, then the satellite to deploy it to (or a satellite first)')]) : null,
            slot,
        ]);
    }

    // ---- 送信 ----

    /**
     * 答えを送る。窓の選択は取り消せない。残りの問いは N34 で届く。
     * @param {string} action
     * @param {Record<string, unknown>} args
     * @param {number} key 二重送信を防ぐ鍵（entryId）
     */
    async act(action, args, key) {
        if (this.busy.has(key)) {
            return;
        }
        this.busy.add(key);
        try {
            await this.bga.actions.performAction(action, args);
        } catch {
            // エラーは BGA が表示する。入力はそのまま残す
        }
        this.busy.delete(key);
        this.refresh();
    }

    // ActionState の確定の流れは使わない
    renderEdit() {}
}
