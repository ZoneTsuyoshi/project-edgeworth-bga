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
import { cardInfo } from '../cards/card-text.js';
import { createCard } from '../cards/CardView.js';
import { keyOfNameId, nameOfNameId } from '../cards/name-id.js';
import { CardPicker } from '../ui/CardPicker.js';
import { el } from '../ui/dom.js';
import { ActionState } from './ActionState.js';
import { format, privateOf } from './logic/common.js';
import {
  initialOrder, initialValues, mayaToPay, moveEntry, pairCost, pairKind, parseWaiting, parseWindow, reservedCards, salesNeeded, useEffectArgs, validateQuestion,
} from './logic/effects.js';
import { cardName, modName } from './texts.js';

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

    /** N26: 窓が終わった */
    close() {
        this.questions = [];
        this.waiting = [];
        this.mine = null;
        this.game.view?.panel.hide();
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
        }
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
        for (const q of this.questions) {
            if (q.type === 'ORDER') {
                body.push(this.orderBlock(q));
            } else {
                const block = this.effectBlock(q, (driver) => {
                    areaDriver ??= driver;
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
        return values;
    }

    /**
     * @param {import('./logic/effects.js').EffectQuestion} q
     * @param {(driver: { cells: number[], value: number | null, pick: (cell: number) => void }) => void} onArea
     * @returns {HTMLElement}
     */
    effectBlock(q, onArea) {
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
                info?.text === undefined ? null : el('p', { class: 'pe-effect-text' }, [info.text]),
            ]),
        ]);

        const fields = q.fields.map((field) => this.fieldEditor(q, field, values, onArea, changed));
        const preview = this.previewLine(q);

        // 代価と売却の同梱
        const need = salesNeeded(q, values, { maya: this.maya, price: this.price, hand: this.hand });
        const cost = this.costLine(q, need);
        const reserved = reservedCards(q, values);
        const prompt = need !== null && need > 0
            ? this.sellPrompt({ need, short: Math.max(0, mayaToPay(q, values) - this.maya) }, reserved, sells, (ids) => {
                this.sells.set(q.entryId, ids);
                changed();
            })
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
     * @returns {HTMLElement}
     */
    fieldEditor(q, field, values, onArea, changed) {
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
                const picker = new CardPicker(view, { ids: field.candidates.map(Number), selected: values[field.key], max: field.max, onChange: (ids) => set(ids) });
                return wrap([picker.root]);
            }
            case 'PAIRS': {
                const kind = pairKind(q.preview);
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
                return wrap([el('div', { class: 'pe-assign' }, field.candidates.map((c) => el('label', { class: 'pe-assign-row' }, [
                    el('span', {}, [cardName(Number(c.card))]),
                    el('select', {
                        onChange: (/** @type {Event} */ e) => set({ ...current, [c.card]: Number(/** @type {HTMLSelectElement} */ (e.target).value) }),
                    }, sats.map((satId) => el('option', { value: satId, selected: Number(current[c.card]) === satId }, [view.satName(satId)]))),
                ])))]);
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
