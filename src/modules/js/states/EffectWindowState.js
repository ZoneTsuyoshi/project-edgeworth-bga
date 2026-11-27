/**
 * 31 EffectWindowResolve（全員並行。選択が必要な効果のあるプレイヤーだけがアクティブ）: 効果の問いに答える（仕様書 008 §5、011 §6、014 §5.1）。
 * 問いは 2 種類。ORDER = 衝突する効果の処理順（初期はデフォルトの順。並べ替えて [Confirm order]）。EFFECT = 1 つの効果の [Use] / [Skip]
 * （フィールド 8 種の入力、代価、結果の見込み、売却の同梱）。窓の中の選択は確定したら取り消せない。
 *
 * 表示は 1 件ずつ（014 §5.1）。複数の EFFECT があるときは、トレイの左に発生源のカードを小さく並べ（待ち列）、注目中の 1 件だけを描く。
 * [Use] / [Skip] / [Confirm order] はステータスバー。入力が足りないあいだは、タイトルの `${todo}` が次にすることを言う。
 * 入力は部品で行う: 区域 = 盤面、衛星 = ロケット、手札のカード = 手札のゾーン、手札に無いカード = トレイのカードの行。
 * 入力の「欄」は 1 行（ラベル + 今の値）。入力先の欄は 1 つだけ `pe-picked`、他の欄に [Choose] / [Choose from hand]（HandTargets）。
 *
 * 状態 31 の args は入場時のもの。答えるたびに残りの問いが変わるので、サーバーが本人へ N34 effectQuestions を送る（通知のハンドラが update を呼ぶ）。
 * 他のプレイヤーの待機表示（誰が・どのカードの選択中か。カードは公開情報）は、N35 effectWaiting で更新する。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { directionIcon, directionLabel } from '../board/Highlights.js';
import { cardInfo, effectTextHtml } from '../cards/card-text.js';
import { createCard } from '../cards/CardView.js';
import { keyOfNameId, nameOfNameId } from '../cards/name-id.js';
import { projectKey } from '../cards/sprite-layout.js';
import { iconButton, pressButton } from '../ui/buttons.js';
import { CardPicker } from '../ui/CardPicker.js';
import { el, escapeHtml } from '../ui/dom.js';
import { costLine } from '../ui/Tray.js';
import { ActionState } from './ActionState.js';
import { format, privateOf } from './logic/common.js';
import {
  applyHandChange, cardOptions, isEstimated, normalizePairs, pairPayState, pickSatellite, satOptions, selectedCards,
} from './logic/effect-pairs.js';
import {
  assignmentLayout, assignmentMoves, cardsInHand, initialOrder, initialValues, lowerFirst, mayaToPay, moveCard, moveEntry, moveTargets, numberChoices, pairCost, pairKind, parseWaiting, parseWindow,
  pickFocus, queueQuestions, questionKey, reservedCards, salesNeeded, satellitePicks, stepNumber, subjectSatellite, useEffectArgs, validateQuestion,
} from './logic/effects.js';
import { cardName, costNote, modName } from './texts.js';

/**
 * 入力の欄（HandTargets の欄の id）ごとの、盤面・ロケットの入力口。入力先の欄が選択中のときだけ盤面・ロケットに出す。
 * @typedef {{ kind: 'area', cells: number[], value: number | null, pick: (cell: number) => void }
 *   | { kind: 'satellite', field: import('./logic/effects.js').Field, value: any, pick: (satId: number) => void }
 *   | { kind: 'pairs', picks: Map<number, { state: 'selectable' | 'sellable' | 'selected' | 'blocked', cost?: number, reason?: string }>, plans: Map<number, { text: string }[]>, pick: (satId: number) => void }} FieldDriver
 *
 * 付け替え（欄ではなく、常に入力先）: 配備カードを押して選び、置き先のロケットを押す。
 * @typedef {{ assignment: Record<string, any>, sats: number[], picked: number | null, pickCard: (card: number) => void, pickSat: (satId: number) => void }} AssignDriver
 */

/**
 * 問い 1 件の表示と、バーのボタンの内容。
 * @typedef {object} Built
 * @property {HTMLElement} body トレイに出す中身
 * @property {string} title ステータスバーのタイトル（`${you}` と `${card_name}`・`${todo}` は args で埋める）
 * @property {Record<string, string>} args タイトルの args
 * @property {{ label: string, color: 'primary' | 'secondary', disabled: boolean, tooltip?: string, run: () => void }[]} buttons バーのボタン（左から）
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
        /** @type {string | null} 注目中の問い（questionKey） */
        this.focus = null;
        /** 待ち列が出ているか（出ているときは節の先頭のカードを出さない） */
        this.queueShown = false;
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
        /** @type {Map<string, FieldDriver>} 欄の id → 盤面・ロケットの入力口（描き直しのたびに作り直す） */
        this.drivers = new Map();
        /** @type {AssignDriver | null} */
        this.assignDriver = null;
        // 効果の窓は自分でタイトルを決める（欄のタイトルで上書きしない）
        this.fixedTitle = true;
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
        this.focus = pickFocus(this.questions, null);
        super.onEnteringState(args, isActive);
    }

    onLeavingState() {
        if (this.game.view?.effectUi === this) {
            this.game.view.effectUi = null;
        }
        super.onLeavingState();
    }

    /**
     * 問いを取り込む。注目中の問いが残っていれば維持、無ければ先頭（ORDER があればそれ）。
     * @param {Record<string, any> | null} mine
     */
    load(mine) {
        this.mine = mine;
        this.questions = parseWindow(mine).questions;
        this.focus = pickFocus(this.questions, this.focus);
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
            area?.setSubjectCard(null);
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
        this.focus = null;
        this.clearUi();
        this.clearHandTools();
        this.bga.statusBar.removeActionButtons();
    }

    refresh() {
        const view = this.game.view;
        if (view === null) {
            return;
        }
        view.stateFrame.leaveSelection();
        view.tray.hide();
        const me = view.m.me;
        if (me !== null) {
            view.areas.get(me)?.hand.setSelectable(null);
            view.areas.get(me)?.hand.setPlanned(null);
            view.areas.get(me)?.setSatPicks(null);
            this.clearAssignUi();
        }
        view.satelliteHandler = null;
        this.resetHandFields();
        this.clearHandTools();
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

    /** 欄を置いた後、入力先の欄の盤面・ロケットの入力口を出す */
    mountHandTargets() {
        super.mountHandTargets();
        this.showDrivers(this.handTargets?.active ?? null);
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

    /** @returns {import('./logic/effects.js').Question | undefined} 注目中の問い */
    focusQuestion() {
        return this.questions.find((q) => questionKey(q) === this.focus) ?? this.questions[0];
    }

    /**
     * 注目を切り替える（入力値は問いごとに保たれる）。
     * @param {string} key
     */
    setFocus(key) {
        if (this.focus === key) {
            return;
        }
        this.focus = key;
        this.handActive = undefined;
        this.refresh();
    }

    renderWindow() {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        this.drivers.clear();
        this.assignDriver = null;
        const q = /** @type {import('./logic/effects.js').Question} */ (this.focusQuestion());
        const queue = queueQuestions(this.questions);
        // 待ち列があるとき、注目中の節の先頭の小さなカードは出さない（待ち列の選択中のカードと重複して見える。名前と効果文は出す）
        this.queueShown = queue.length > 0;
        const built = q.type === 'ORDER' ? this.orderBlock(q) : this.effectBlock(q);
        const body = queue.length === 0 ? built.body : el('div', { class: 'pe-effect-wrap' }, [this.queueRow(queue), built.body]);
        view.tray.show([{ label: _('Resolve your card effects'), body }]);
        this.bga.statusBar.setTitle(built.title, built.args);
        for (const button of built.buttons) {
            this.bga.statusBar.addActionButton(button.label, button.run, { color: button.color, disabled: button.disabled, tooltip: button.tooltip });
        }
    }

    /**
     * 待ち列: 未解決の問いの発生源のカードを小さく並べる。注目中は pe-picked、他は押して切り替える。
     * @param {import('./logic/effects.js').EffectQuestion[]} queue
     * @returns {HTMLElement}
     */
    queueRow(queue) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        return el('div', { class: 'pe-effect-queue', role: 'group', 'aria-label': _('Card effects to resolve') }, queue.map((e) => {
            const key = keyOfNameId(e.cardKey);
            const on = questionKey(e) === this.focus;
            const go = () => this.setFocus(questionKey(e));
            const node = key === null
                ? el('span', { class: `pe-card-chip${on ? ' pe-picked' : ''}` }, [nameOfNameId(e.cardKey)])
                : createCard(view.env, key, { classes: ['pe-picker-card', on ? 'pe-picked' : 'pe-can'] });
            node.dataset.entry = String(e.entryId);
            node.tabIndex = 0;
            node.setAttribute('role', 'button');
            node.setAttribute('aria-pressed', on ? 'true' : 'false');
            node.setAttribute('aria-label', nameOfNameId(e.cardKey));
            node.addEventListener('click', go);
            node.addEventListener('keydown', (ev) => {
                if (ev.key === 'Enter' || ev.key === ' ') {
                    ev.preventDefault();
                    go();
                }
            });
            if (key !== null) {
                view.tips.card(node, key);
            }
            return el('div', { class: 'pe-effect-queue-slot' }, [node]);
        }));
    }

    // ---- 盤面・ロケットの入力口 ----

    /**
     * 入力先の欄の、盤面・ロケットの入力口を出す（区域 = 盤面の枠、衛星・配備の組 = ロケットと盤面のコマ）。付け替えと対象の強調は常に出す。
     * 外すのは refresh / clearUi。
     * @param {string | null} active 入力先の欄の id
     */
    showDrivers(active) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const me = view.m.me;
        if (me === null) {
            return;
        }
        const area = view.areas.get(me);
        const driver = active === null ? undefined : this.drivers.get(active);
        for (const d of this.drivers.values()) {
            if (d.kind === 'pairs') {
                area?.setSatPlans(d.plans);
            }
        }
        if (driver?.kind === 'area') {
            view.stateFrame.selectCells(driver.cells.map((cell) => ({ cell, kind: cell === driver.value ? 'selected' : 'selectable' })), driver.pick);
        } else if (driver?.kind === 'satellite') {
            const picks = satellitePicks(driver.field, driver.value);
            area?.setSatPicks(new Map([...picks].map(([satId, state]) => [satId, { state }])), (satId) => driver.pick(satId));
            view.satelliteHandler = (satId) => {
                if (!picks.has(satId)) {
                    return false;
                }
                driver.pick(satId);
                return true;
            };
            this.selectSatCells([...picks].map(([satId, state]) => [satId, state]));
        } else if (driver?.kind === 'pairs') {
            this.showPairs(driver);
        }
        if (this.assignDriver !== null) {
            this.showAssign(this.assignDriver);
        }
        this.showSubjects(driver !== undefined);
    }

    /**
     * 稼働中の衛星の区域を、盤面の枠で示す（コマを押しても選べる）。
     * @param {[number, 'selectable' | 'selected' | 'sellable' | 'blocked'][]} states
     */
    selectSatCells(states) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const cells = states.flatMap(([satId, state]) => {
            const cell = state === 'blocked' ? null : view.pieces.satelliteCell(satId);
            return cell === null ? [] : [{ cell, kind: /** @type {'selectable' | 'selected'} */ (state === 'selected' ? 'selected' : 'selectable') }];
        });
        if (cells.length > 0) {
            view.stateFrame.selectCells(cells);
        }
    }

    /**
     * 問いの発生源と対象を強調する（注目中の問いだけ）。発生源の配備カード = `pe-subject`（衛星の上のカード）。
     * 問いが特定の衛星に紐づく効果（P082 など）は、対象の衛星（盤面のコマ・区域の枠・土台の衛星カード）も強調する。
     * @param {boolean} cellsInUse 盤面の区域の枠を、入力先の欄がすでに使っているか
     */
    showSubjects(cellsInUse) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const me = view.m.me;
        const q = this.focusQuestion();
        if (me === null || q === undefined || q.type !== 'EFFECT') {
            return;
        }
        const area = view.areas.get(me);
        area?.setSubjectCard(this.sourceCard(q));
        const satId = subjectSatellite(q);
        if (satId === null) {
            return;
        }
        const ids = new Set([satId]);
        view.pieces.setSubjects(ids);
        area?.setSubjects(ids);
        if (!cellsInUse) {
            const cell = view.pieces.satelliteCell(satId);
            if (cell !== null) {
                view.stateFrame.selectCells([{ cell, kind: 'selected' }]);
            }
        }
    }

    /**
     * 問いの発生源が、衛星に配備されたプロジェクトカードなら、その番号。
     * @param {import('./logic/effects.js').EffectQuestion} q
     * @returns {number | null}
     */
    sourceCard(q) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const key = keyOfNameId(q.cardKey);
        if (key === null || !/^P\d+$/.test(key)) {
            return null;
        }
        const me = view.m.me;
        const sats = me === null ? [] : view.m.players[me].satellites;
        return sats.some((sat) => sat.cards.some((c) => projectKey(c.id) === key)) ? Number.parseInt(key.slice(1), 10) : null;
    }

    /**
     * 配備の組の衛星側: 衛星カード・盤面のコマを押して配備先を選ぶ（II 配備と同じ操作感）。選べる衛星にはコストの札、選べない衛星は理由つきで灰色。
     * 組にした衛星の上には、配備予定のカードを予定の札で出す。
     * @param {Extract<FieldDriver, { kind: 'pairs' }>} d
     */
    showPairs(d) {
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
        this.selectSatCells([...d.picks].map(([satId, pick]) => [satId, pick.state]));
    }

    /**
     * 付け替え（P022）: 自分の衛星カードの配備カードを押して選び、置き先の衛星カードを押す。
     * 配備カードは押せる枠、選んだら置き先の衛星が選べる（4 枚の衛星は選べない）。衛星の上に付け替え後の並びを予定の札で出す。
     * @param {AssignDriver} d
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
                reason: state === 'blocked' ? _('A satellite holds up to 4 cards') : undefined,
            }])), d.pickSat);
        }
    }

    // ---- ORDER ----

    /**
     * 順番の問い 1 組: 効果のカードを小さく横に並べ、各カードに順番の数字と [◀][▶]。待ち列は出さない（確定すると N34 で次が届く）。
     * @param {import('./logic/effects.js').OrderQuestion} q
     * @returns {Built}
     */
    orderBlock(q) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const sig = q.entries.map((e) => e.entryId).join(',');
        const order = this.orders.get(sig) ?? initialOrder(q);
        this.orders.set(sig, order);
        const set = (/** @type {number[]} */ next) => {
            this.orders.set(sig, next);
            this.refresh();
        };
        const slots = order.map((entryId, index) => {
            const entry = q.entries.find((e) => e.entryId === entryId);
            const key = entry === undefined ? null : keyOfNameId(entry.cardKey);
            const name = nameOfNameId(entry?.cardKey ?? '');
            const card = key === null ? el('span', { class: 'pe-card-chip' }, [name]) : createCard(view.env, key, { classes: ['pe-picker-card'] });
            if (key !== null) {
                view.tips.card(card, key);
            }
            const moves = el('div', { class: 'pe-order-moves' });
            iconButton(this.bga, moves, 'left', _('Move earlier'), () => set(moveEntry(order, entryId, -1)), index === 0);
            iconButton(this.bga, moves, 'right', _('Move later'), () => set(moveEntry(order, entryId, 1)), index === order.length - 1);
            const forced = entry?.forced === true ? el('span', { class: 'pe-order-forced' }, [_('Mandatory')]) : null;
            if (forced !== null) {
                view.tips.text(forced, _('Mandatory effect'));
            }
            return el('li', { class: 'pe-order-slot', dataset: { entry: entryId } }, [
                el('div', { class: 'pe-order-card' }, [card, el('span', { class: 'pe-order-no' }, [String(index + 1)])]),
                el('span', { class: 'pe-picker-name' }, [name]),
                forced,
                moves,
            ]);
        });
        const body = el('div', { class: 'pe-effect' }, [el('ol', { class: 'pe-order-list' }, slots)]);
        return {
            body,
            title: _('${you} must choose the order of your card effects'),
            args: {},
            buttons: [{ label: _('Confirm order'), color: 'primary', disabled: false, tooltip: _('This choice is final'), run: () => this.act('actOrderEffects', { entryIds: order }, sig.length) }],
        };
    }

    // ---- EFFECT ----

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
     * 効果の問い 1 件（注目中）。発生源のカード + 効果文 + コストの行 + 欄 + 結果の見込み + 売却の欄。ボタンはステータスバー。
     * @param {import('./logic/effects.js').EffectQuestion} q
     * @returns {Built}
     */
    effectBlock(q) {
        const values = this.valuesOf(q);
        const key = keyOfNameId(q.cardKey);
        const info = key === null ? null : cardInfo(key);
        const name = info?.name ?? nameOfNameId(q.cardKey);
        const sells = (this.sells.get(q.entryId) ?? []).filter((id) => this.hand.includes(id));
        const changed = () => this.refresh();

        const head = el('div', { class: 'pe-effect-head' }, [
            key === null || this.queueShown ? null : this.smallCard(key),
            el('div', { class: 'pe-effect-title' }, [
                el('strong', {}, [name]),
                q.mandatory ? el('span', { class: 'pe-order-forced' }, [_('Mandatory')]) : null,
                info?.text === undefined ? null : this.effectText(info.text),
            ]),
        ]);

        const fields = q.fields.map((field) => this.fieldEditor(q, field, values, changed));
        const preview = this.previewLine(q);

        // 代価と売却の同梱
        const need = salesNeeded(q, values, { maya: this.maya, price: this.price, hand: this.hand });
        const cost = this.costRow(q, need);
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

        /** @type {Built['buttons']} */
        const buttons = [{
            label: _('Use'),
            color: 'primary',
            disabled: !ready,
            tooltip: _('This choice is final'),
            run: () => {
                if (ready) {
                    this.act('actUseEffect', useEffectArgs(q, values, sell), q.entryId);
                }
            },
        }];
        if (!q.mandatory) {
            buttons.push({
                label: _('Skip'),
                color: 'secondary',
                disabled: this.busy.has(q.entryId),
                tooltip: _('The effect is not used and cannot be used for this trigger later'),
                run: () => this.act('actSkipEffect', { entryId: q.entryId }, q.entryId),
            });
        }

        // タイトル: 入力が足りないあいだは「: ${todo}」（[Use] が押せない理由）。足りないのが売却だけのときは売る枚数の案内
        const base = q.mandatory ? _('${you} must resolve ${card_name}') : _('${you} may use ${card_name}');
        const withTodo = q.mandatory ? _('${you} must resolve ${card_name}: ${todo}') : _('${you} may use ${card_name}: ${todo}');
        /** @type {Pick<Built, 'title' | 'args'>} */
        let titled = { title: base, args: { card_name: name } };
        if (!check.ok) {
            titled = { title: withTodo, args: { card_name: name, todo: this.todoText(q, check.key) } };
        } else if (need !== null && need > 0 && !sellOk) {
            titled = { title: format(_('Choose at least ${need} card(s) to sell (+${price} Maya each): ${n} / ${need} chosen'), { need, price: this.price, n: sells.length }), args: {} };
        }

        return {
            body: el('div', { class: 'pe-effect', dataset: { entry: q.entryId } }, [head, cost, ...fields, preview, prompt]),
            ...titled,
            buttons,
        };
    }

    /**
     * 次にすること（`${todo}`。小文字で始まる。例: choose a card to deploy and a satellite）。
     * @param {import('./logic/effects.js').EffectQuestion} q
     * @param {string | undefined} key 足りない入力の欄
     * @returns {string}
     */
    todoText(q, key) {
        const field = q.fields.find((f) => f.key === key);
        if (field?.type === 'PAIRS' && pairKind(q.preview) === 'deploy') {
            return _('a card to deploy and a satellite');
        }
        return lowerFirst(field === undefined ? '' : this.fieldLabel(field));
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
     * 欄の行のラベル（名詞。1 行「ラベル + 今の値」のラベル）。
     * @param {import('./logic/effects.js').EffectQuestion} q
     * @param {import('./logic/effects.js').Field} field
     * @returns {string}
     */
    rowLabel(q, field) {
        switch (field.type) {
            case 'SATELLITE':
                return _('Satellite');
            case 'AREA':
                return _('Area');
            case 'DIRECTION':
                return _('Direction');
            case 'AMOUNT':
                return field.key === 'tier' ? _('Payment') : _('Amount');
            case 'CARDS':
                return _('Cards');
            case 'PAIRS':
                return pairKind(q.preview) === 'deploy' ? _('Cards to deploy') : _('Option');
            case 'ASSIGNMENT':
                return _('Moves');
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
        view.tips.card(card, key);
        return card;
    }

    /**
     * 代価の行（コストと結果の行と同じ形。足りないときは警告）。
     * @param {import('./logic/effects.js').EffectQuestion} q
     * @param {number | null} need 必要な売却の枚数（売っても届かないときは null）
     * @returns {HTMLElement | null}
     */
    costRow(q, need) {
        const cost = q.cost;
        /** @param {number} floor */
        const warn = (floor) => (need === null
            ? _('not enough Maya, even if you sell your cards')
            : need > 0 ? format(_('needs ${short} more Maya (sell ${need} card(s))'), { short: Math.max(0, floor - this.maya), need }) : undefined);
        switch (cost.kind) {
            case 'PAY_MAYA':
                return costLine([format(_('Cost: ${amount} Maya'), { amount: cost.amount ?? 0 })], { warn: warn(cost.amount ?? 0) });
            case 'PAY_MAYA_TIER':
                return costLine([_('Cost: pay Maya by tier'), Object.entries(cost.tiers ?? {}).map(([pay, amount]) => `${pay} → ${amount}`).join(', ')], { warn: warn(Math.min(...Object.keys(cost.tiers ?? {}).map(Number))) });
            case 'DISCARD_COST':
                return costLine([format(_('Cost: discard ${n} card(s) from your hand (no sale value)'), { n: cost.amount ?? 1 })], { warn: cost.canPay === false ? _('not enough cards') : undefined });
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
            return costLine([format(_('Moves ${areas} area(s), using ${fuel} fuel'), { areas: p.areas, fuel: p.fuel })]);
        }
        if (p.discard !== undefined) {
            return costLine([format(_('Discard ${n} card(s)'), { n: p.discard })]);
        }
        if (p.action !== undefined) {
            return costLine([format(_('Declare the Action bonus of ${action}'), { action: String(p.action) })]);
        }
        return null;
    }

    /**
     * 選択肢・数値・起動の修正の行（入力先にならない行。ラベル + ボタン）。
     * @param {string} label
     * @param {string} field 欄の key
     * @param {(box: HTMLElement) => void} build ボタンを置く
     * @returns {HTMLElement}
     */
    optionRow(label, field, build) {
        const box = el('span', { class: 'pe-opts', role: 'group', 'aria-label': label });
        build(box);
        return el('div', { class: 'pe-field pe-field-opts', dataset: { field } }, [
            el('div', { class: 'pe-field-main' }, [el('span', { class: 'pe-field-label' }, [label]), box]),
        ]);
    }

    /**
     * フィールド 1 つの入力部品（欄の行）。入力先の欄の管理は HandTargets（ActionState.handField）。
     * @param {import('./logic/effects.js').EffectQuestion} q
     * @param {import('./logic/effects.js').Field} field
     * @param {Record<string, any>} values
     * @param {() => void} changed
     * @returns {HTMLElement}
     */
    fieldEditor(q, field, values, changed) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const set = (/** @type {any} */ value) => {
            values[field.key] = value;
            changed();
        };
        const id = `${q.entryId}:${field.key}`;
        const label = this.rowLabel(q, field);
        /** 欄の入力（盤面・ロケット・トレイでの選択）。入力先を次の未入力の欄へ進めてから値を入れる */
        const pickInto = (/** @type {number[]} */ ids) => {
            this.handTargets?.change(id, ids);
        };

        switch (field.type) {
            case 'SATELLITE': {
                this.drivers.set(id, { kind: 'satellite', field, value: values[field.key], pick: (satId) => pickInto([satId]) });
                const value = values[field.key];
                return this.handField({
                    kind: 'direct',
                    id,
                    label,
                    badge: '',
                    candidates: [],
                    selected: value === null || value === undefined ? [] : [Number(value)],
                    need: 1,
                    max: 1,
                    itemText: (satId) => view.satName(satId),
                    onChange: (ids) => set(ids.length === 0 ? null : ids[0]),
                });
            }
            case 'AREA': {
                const value = values[field.key] === null || values[field.key] === undefined ? null : Number(values[field.key]);
                this.drivers.set(id, { kind: 'area', cells: field.candidates.map(Number), value, pick: (cell) => pickInto([cell]) });
                return this.handField({
                    kind: 'direct',
                    id,
                    label,
                    badge: '',
                    candidates: [],
                    selected: value === null ? [] : [value],
                    need: 1,
                    max: 1,
                    itemText: (cell) => format(_('Area ${cell}'), { cell }),
                    onChange: (ids) => set(ids.length === 0 ? null : ids[0]),
                });
            }
            case 'DIRECTION':
                return this.optionRow(label, field.key, (box) => {
                    for (const dir of field.candidates) {
                        const word = directionLabel(String(dir));
                        pressButton(this.bga, box, word, values[field.key] === dir, () => set(String(dir)), {
                            html: `${directionIcon(String(dir)).outerHTML}<span>${escapeHtml(word)}</span>`,
                            title: word,
                        });
                    }
                });
            case 'AMOUNT': {
                const current = values[field.key] === null || values[field.key] === undefined ? null : Number(values[field.key]);
                if (field.candidates.length === 0) {
                    const choices = numberChoices(field);
                    if (choices === null) {
                        return this.optionRow(label, field.key, (box) => {
                            iconButton(this.bga, box, 'minus', _('Decrease'), () => set(stepNumber(current, -1, field.min, field.max)), current !== null && current <= field.min);
                            box.append(el('span', { class: 'pe-number-value', 'aria-live': 'polite' }, [current === null ? _('None') : String(current)]));
                            iconButton(this.bga, box, 'plus', _('Increase'), () => set(stepNumber(current, 1, field.min, field.max)), current !== null && current >= field.max);
                        });
                    }
                    return this.optionRow(label, field.key, (box) => {
                        for (const n of choices) {
                            pressButton(this.bga, box, String(n), current === n, () => set(n));
                        }
                    });
                }
                const tiers = q.cost.kind === 'PAY_MAYA_TIER' ? (q.cost.tiers ?? {}) : {};
                return this.optionRow(label, field.key, (box) => {
                    for (const n of field.candidates) {
                        pressButton(
                            this.bga,
                            box,
                            field.key === 'tier' && tiers[String(n)] !== undefined ? format(_('Pay ${pay} Maya (${amount})'), { pay: n, amount: tiers[String(n)] }) : String(n),
                            current === Number(n),
                            () => set(Number(n)),
                        );
                    }
                });
            }
            case 'CARDS': {
                const current = Array.isArray(values[field.key]) ? values[field.key].map(Number) : [];
                if (cardsInHand(field, this.hand)) {
                    // 候補がすべて手札: 手札のゾーンで選ぶ（欄には選んだカードの名前だけ。他の欄があれば [Choose from hand] で切り替える）
                    return this.handField({
                        id,
                        label,
                        badge: _('Discard'),
                        candidates: field.candidates.map(Number),
                        selected: current,
                        need: field.min,
                        max: field.max,
                        onChange: (ids) => set(ids),
                    });
                }
                // 手札に無いカード: トレイのカードの行（欄が選択中のとき）
                return this.handField({
                    kind: 'direct',
                    id,
                    label,
                    badge: '',
                    candidates: [],
                    selected: current,
                    need: field.min,
                    max: field.max,
                    extra: (active) => (active
                        ? new CardPicker(view, { ids: field.candidates.map(Number), selected: current, max: field.max, onChange: (ids) => pickInto(ids) }).root
                        : null),
                    onChange: (ids) => set(ids),
                });
            }
            case 'PAIRS': {
                const kind = pairKind(q.preview);
                if (kind === 'deploy') {
                    return this.deployPairsEditor(q, field, values, set, label);
                }
                const list = Array.isArray(values[field.key]) ? values[field.key] : [];
                return this.optionRow(label, field.key, (box) => {
                    for (const pair of field.candidates) {
                        const on = list.some((/** @type {any} */ p) => JSON.stringify(p) === JSON.stringify(pair));
                        const cost = pairCost(q.preview, pair);
                        const text = kind === 'link'
                            ? format(_('${sat} to area ${cell}'), { sat: view.satName(Number(pair[0])), cell: pair[1] })
                            : JSON.stringify(pair);
                        pressButton(this.bga, box, cost === null ? text : `${text} — ${format(_('cost ${cost}'), { cost })}`, on, () => {
                            if (on) {
                                set(list.filter((/** @type {any} */ p) => JSON.stringify(p) !== JSON.stringify(pair)));
                            } else if (field.max === 1) {
                                set([pair]);
                            } else if (list.length < field.max) {
                                set([...list, pair]);
                            }
                        });
                    }
                });
            }
            case 'ASSIGNMENT':
                return this.assignmentEditor(q, field, values, label, set);
            case 'USE_MODS': {
                const list = Array.isArray(values[field.key]) ? values[field.key] : [];
                return this.optionRow(label, field.key, (box) => {
                    for (const hostKey of field.candidates) {
                        const on = list.includes(hostKey);
                        pressButton(this.bga, box, format(_('Use ${name} (activated effect)'), { name: modName(String(hostKey)) }), on, () => {
                            set(on ? list.filter((/** @type {string} */ k) => k !== hostKey) : [...list, hostKey]);
                        });
                    }
                });
            }
            default:
                return el('div');
        }
    }

    /**
     * 付け替え（P022）: 部品だけで操作する（配備カードを押す → 移す先のロケットを押す）。トレイには決めた移動を 1 行ずつ文字で出し、各行に取り消し。
     * @param {import('./logic/effects.js').EffectQuestion} q
     * @param {import('./logic/effects.js').Field} field
     * @param {Record<string, any>} values
     * @param {string} label
     * @param {(value: any) => void} set
     * @returns {HTMLElement}
     */
    assignmentEditor(q, field, values, label, set) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const sats = (Array.isArray(q.preview.satellites) ? q.preview.satellites : []).map(Number);
        const current = values[field.key] ?? {};
        const picked = this.pickedCards.get(q.entryId) ?? null;
        const pickCard = (/** @type {number} */ card) => {
            if (this.pickedCards.get(q.entryId) === card) {
                this.pickedCards.delete(q.entryId);
            } else {
                this.pickedCards.set(q.entryId, card);
            }
            this.refresh();
        };
        // カード → 置き先の衛星の順に押す（押したカードは選んだ状態のままにしない）
        this.assignDriver = {
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
                set(moveCard(current, card, satId, sats));
            },
        };
        const moves = assignmentMoves(current, field.candidates);
        const lines = moves.map((m) => {
            const row = el('div', { class: 'pe-move' }, [
                el('span', { class: 'pe-move-text' }, [format(_('${card} → ${sat}'), { card: cardName(m.card), sat: view.satName(m.to) })]),
            ]);
            iconButton(this.bga, row, 'close', _('Remove'), () => {
                this.pickedCards.delete(q.entryId);
                set(moveCard(current, m.card, m.from, sats));
            });
            return row;
        });
        if (picked !== null) {
            lines.push(el('div', { class: 'pe-move' }, [el('span', { class: 'pe-move-text' }, [format(_('${card} — choose a satellite'), { card: cardName(picked) })])]));
        }
        return el('div', { class: 'pe-field pe-picked', dataset: { field: field.key, type: field.type }, 'aria-current': 'true' }, [
            el('div', { class: 'pe-field-main' }, [
                el('span', { class: 'pe-field-label' }, [label]),
                lines.length === 0 ? el('span', { class: 'pe-field-value pe-field-empty' }, [_('None')]) : el('div', { class: 'pe-moves' }, lines),
            ]),
        ]);
    }

    /**
     * 配備の組（kind: deploy。R005・P011・P045・P074）: 手札のゾーンでカードを押し、衛星カード・盤面のコマで配備先を押す（どちらが先でもよい。B-3）。
     * 配備先が 1 機だけの効果はカードを押した時点で組が決まる。欄には選んだ組（カード to 衛星 — cost n）の一覧だけを出す。
     * 判定は logic/effect-pairs.js の純関数。ここは描画と、保留（押したがまだ組にならないカード・衛星）の保持だけ。
     * @param {import('./logic/effects.js').EffectQuestion} q
     * @param {import('./logic/effects.js').Field} field
     * @param {Record<string, any>} values
     * @param {(value: any) => void} set
     * @param {string} label
     * @returns {HTMLElement}
     */
    deployPairsEditor(q, field, values, set, label) {
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
        const slot = this.handField({
            id: targetId,
            label,
            badge: _('Deploy'),
            candidates: [...allowed],
            selected,
            need: field.min,
            max,
            reasons,
            itemText,
            onChange: (ids) => commit(applyHandChange(candidates, state, ids, { max, deployed })),
        });

        // 衛星側: 衛星カードと盤面のコマ
        const satReasons = satOptions(candidates, chosen, max, deployed, satIds, pending.card);
        const base = max === 1 ? [] : chosen;
        const funds = { maya: this.maya, price: this.price, handCount: this.hand.length, otherReserved: taken.size };
        /** @type {Map<number, { state: 'selectable' | 'sellable' | 'selected' | 'blocked', cost?: number, reason?: string }>} */
        const picks = new Map();
        for (const satId of satIds) {
            const reason = satReasons.get(satId) ?? null;
            if (pending.sat === satId) {
                picks.set(satId, { state: 'selected' });
            } else if (reason === null && pending.card !== null) {
                const { cost, pay } = pairPayState(q.preview, base, [pending.card, satId], funds);
                const note = `${costNote(cost, pay)}${isEstimated(base.length) ? ` (${_('estimated')})` : ''}`;
                picks.set(satId, pay.kind === 'short' ? { state: 'blocked', reason: note } : { state: pay.kind === 'free' ? 'selectable' : 'sellable', cost });
            } else if (reason === null) {
                picks.set(satId, { state: 'selectable' });
            } else {
                picks.set(satId, {
                    state: 'blocked',
                    reason: pending.card === null ? undefined : reason === 'full' ? _('A satellite holds up to 4 cards') : _('Cannot deploy this card here'),
                });
            }
        }
        /** @type {Map<number, { text: string }[]>} */
        const plans = new Map();
        for (const pair of chosen) {
            plans.set(pair[1], [...(plans.get(pair[1]) ?? []), { text: format(_('Deploying: ${card}'), { card: cardName(pair[0]) }) }]);
        }
        this.drivers.set(targetId, {
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
        return slot;
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
