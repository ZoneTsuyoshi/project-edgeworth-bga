/**
 * 同時選択の状態の操作 UI の共通部分（仕様書 008 §2-1〜§2-6、012 §6）。派生クラスは、選択の初期値・描画（renderEdit）・要約・送信だけを書く。
 *
 * 流れ: 選ぶ（edit）→ [Confirm] で確定（confirmed。[Change my choice] で選び直せる）。全員が確定するまで何度でも上書きできる。
 * どの状態でも [Sell cards] で手札を売れる（sell モード）。売却は取り消せない。手札をすべて売るときだけ確認を挟む。
 *
 * 注意（姉妹リポジトリで直した不具合）:
 *  - 同時選択の状態は、入場した時点ではまだ本人がアクティブになっていないことがある（setPlayersMultiactive は入場後）。
 *    UI は onEnteringState と onPlayerActivationChange の両方で（冪等に）組み立てる。
 *  - 確定すると本人は非アクティブになる（案 B）。args は入場時のままなので、確定した内容は送信に成功した時点でクライアントが覚える。
 *  - 確定済みの人は非アクティブなので、performAction は checkAction: false で送る。
 *  - 空配列は送信で落ちる。送る引数は compact() で空を省く。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { ChoiceState } from './ChoiceState.js';
import { CardPicker } from '../ui/CardPicker.js';
import { el } from '../ui/dom.js';
import { format, privateOf, sellableHand, sellsWholeHand, sellTotal, validMods } from './logic/common.js';
import { modOptionText, modName } from './texts.js';

export class ActionState extends ChoiceState {
    /**
     * @param {{ view: import('../ui/GameView.js').GameView | null }} game
     * @param {Bga} bga
     */
    constructor(game, bga) {
        super(game, bga);
        /** @type {Record<string, any>} */
        this.args = {};
        this.active = false;
        /** @type {Record<string, any> | null} 自分の `_private` */
        this.mine = null;
        /** @type {Record<string, any> | null} 確定済みの内容（サーバーの myChoice と同じ形） */
        this.confirmed = null;
        /** 確定済みから選び直している最中か */
        this.editing = false;
        /** @type {{ picked: number[], reserved: number[], askAll: boolean } | null} 売却中の状態 */
        this.sellMode = null;
        this.sending = false;
        /** 1 人ずつ順番に操作する状態（_private が無い。11 SetupLaunchPadPlace） */
        this.solo = false;
        /** @type {(() => void) | null} */
        this.unsubscribe = null;
    }

    /** 売却に使う手札の今の状態（通知で更新されるモデルから読む） */
    get hand() {
        return this.game.view?.m.myHand ?? [];
    }

    get maya() {
        const m = this.game.view?.m;
        return m === undefined || m === null || m.me === null ? 0 : m.players[m.me].maya;
    }

    get price() {
        return Number.parseInt(String(this.mine?.sellValue ?? 3), 10) || 0;
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
        this.args = args;
        this.active = isActive;
        view.stateFrame.enter(this.constructor.name, args, isActive);
        this.mine = privateOf(args, view.m.me) ?? (this.solo ? {} : null);
        this.confirmed = this.mine?.myChoice ?? null;
        this.editing = false;
        this.sellMode = null;
        this.sending = false;
        this.reset();
        this.unsubscribe?.();
        const listener = (/** @type {string} */ name) => {
            if (['cardsDiscarded', 'mayaChanged', 'handCardsAdded'].includes(name)) {
                this.refresh();
            }
        };
        view.modelListeners.add(listener);
        this.unsubscribe = () => view.modelListeners.delete(listener);
        this.refresh();
    }

    /**
     * 本人がアクティブになった / ならなくなった。
     * @param {unknown} _args
     * @param {boolean} isActive
     */
    onPlayerActivationChange(_args, isActive) {
        this.active = isActive;
        if (this.game.view !== null) {
            this.refresh();
        }
    }

    onLeavingState() {
        this.unsubscribe?.();
        this.unsubscribe = null;
        this.clearUi();
        this.bga.statusBar.removeActionButtons();
        super.onLeavingState();
    }

    // ---- 派生クラスが実装する ----

    /** 選択を初期状態にする（入場時） */
    reset() {}

    /**
     * 確定済みの内容から選択を復元する（[Change my choice]）。
     * @param {Record<string, any> | null} _choice
     */
    restore(_choice) {}

    /** 選んでいる最中の描画（タイトル、ボタン、盤面・手札・衛星の選択）。選択に変化があるたびに呼ばれる */
    renderEdit() {}

    /**
     * 確定済みの内容の要約（ステータスバーに出す）。
     * @param {Record<string, any>} _choice
     * @returns {string}
     */
    summarize(_choice) {
        return '';
    }

    /** @returns {string} 選択中のタイトル（BGA の自動のタイトルの代わり） */
    // 派生クラスの renderEdit が setTitle を呼ぶ

    /**
     * 確定済みの内容を、待機中に盤面・衛星・手札の上へ「予定」として出す（本人の画面だけ。他のプレイヤーの未公開の選択は出さない）。
     * @param {Record<string, any>} _choice
     */
    showPlan(_choice) {}

    // ---- 共通の流れ ----

    refresh() {
        const view = this.game.view;
        if (view === null) {
            return;
        }
        this.clearUi();
        this.bga.statusBar.removeActionButtons();
        const me = view.m.me;
        if (me === null || this.mine === null) {
            this.bga.statusBar.setTitle(_('Waiting for other players'));
            return;
        }
        if (this.sellMode !== null) {
            this.renderSell();
            return;
        }
        if (this.confirmed !== null && !this.editing) {
            this.renderConfirmed();
            return;
        }
        if (!this.active && !this.editing) {
            this.bga.statusBar.setTitle(_('Waiting for other players'));
            return;
        }
        this.renderEdit();
        this.addSellButton();
    }

    /** 盤面・手札・衛星の選択の表示をすべて外す */
    clearUi() {
        const view = this.game.view;
        if (view === null) {
            return;
        }
        view.stateFrame.leaveSelection();
        view.satelliteHandler = null;
        view.panel.hide();
        const me = view.m.me;
        if (me !== null) {
            const area = view.areas.get(me);
            area?.hand.setSelectable(null);
            area?.actions.setSelectable(null);
            area?.setSatPicks(null);
            area?.setSatPlans(null);
            area?.hand.setPlanned(null);
        }
    }

    /**
     * 確定済み: 要約と [Change my choice]。
     */
    renderConfirmed() {
        const choice = this.confirmed ?? {};
        const summary = this.summarize(choice);
        this.bga.statusBar.setTitle(summary === '' ? _('You have confirmed. Waiting for other players') : format(_('You have confirmed: ${summary}. Waiting for other players'), { summary }));
        this.showPlan(choice);
        this.bga.statusBar.addActionButton(_('Change my choice'), () => {
            this.editing = true;
            this.restore(this.confirmed);
            this.refresh();
        }, { color: 'secondary' });
        this.addSellButton();
    }

    /**
     * 確定して送る。成功したら確定済みの表示にする。
     * @param {string} action
     * @param {Record<string, unknown>} args
     * @param {Record<string, any>} choice サーバーの myChoice と同じ形の確定内容（復元と要約に使う）
     */
    async send(action, args, choice) {
        if (this.sending) {
            return;
        }
        this.sending = true;
        try {
            await this.bga.actions.performAction(action, args, { checkAction: false });
        } catch {
            // エラーは BGA が表示する。選択はそのまま残す
            this.sending = false;
            return;
        }
        this.sending = false;
        this.confirmed = choice;
        this.editing = false;
        this.refresh();
    }

    // ---- コストの内訳・起動の修正・売却の同梱（配備・打ち上げ・通信確立で共通） ----

    /**
     * コストの内訳（ボトムシート）、使える起動の修正の選択、足りないときの売却の促し（選択パネル）を出す。
     * 数値（コスト）はサーバーが args で渡した値。売却が足りなければ、売るカードを選ぶまで [Confirm] は押せない。
     * @param {object} p
     * @param {string} p.title ボトムシートの見出し
     * @param {string[]} p.lines 内訳の行
     * @param {{ cost: number, pay: ReturnType<typeof import('./logic/common.js').payState> }} p.quote
     * @param {import('./logic/common.js').ModsInfo | null} p.mods 使える起動の修正
     * @param {{ mods: import('./logic/common.js').ModsInfo, sel: { useMods: string[] } }[]} [p.moreMods] もう 1 枚の配備が使える起動の修正（追加の配備）
     * @param {{ sell: number[], useMods: string[] }} p.sel 選択（売却と起動の修正を書き換える）
     * @param {number[]} p.reserved 売れないカード（配備するカード）
     * @param {HTMLElement[]} [p.subject] 選択パネルの見出し部品
     * @param {{ onPick: (dir: string) => void, selected?: string | null }} [p.pad] 方向パッド
     */
    costBox(p) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const sel = p.sel;
        const left = this.maya + sellTotal(sel.sell.length, this.price) - p.quote.cost;
        view.sheet.show({
            title: p.title,
            lines: [...p.lines, format(_('You will have ${left} Maya left'), { left })],
            pad: p.pad,
            extra: [...(p.mods === null ? [] : [this.modsBox(p.mods, sel)]), ...(p.moreMods ?? []).map((m) => this.modsBox(m.mods, m.sel))],
        });
        view.panel.hide();
        if (p.quote.pay.kind === 'sell') {
            view.panel.show({
                title: _('Pay for the action'),
                body: [
                    ...(p.subject ?? []),
                    this.sellPrompt(p.quote.pay, p.reserved, sel.sell, (ids) => {
                        sel.sell = ids;
                        this.refresh();
                    }),
                ],
            });
        } else {
            sel.sell = [];
        }
    }

    /**
     * 起動の修正の選択（チェックボックス。使うとコストが下がる。各ラウンド 1 回。011 §6-3）。
     * @param {import('./logic/common.js').ModsInfo} mods
     * @param {{ useMods: string[] }} sel
     * @returns {HTMLElement}
     */
    modsBox(mods, sel) {
        return el('div', { class: 'pe-mods' }, [
            el('div', { class: 'pe-mods-title' }, [_('Use an activated effect to lower the cost (once per round)')]),
            ...mods.options.map((option) => el('label', { class: 'pe-mod' }, [
                el('input', {
                    type: 'checkbox',
                    checked: sel.useMods.includes(option.hostKey),
                    onChange: (/** @type {Event} */ e) => {
                        const on = /** @type {HTMLInputElement} */ (e.target).checked;
                        sel.useMods = validMods(mods, on ? [...sel.useMods, option.hostKey] : sel.useMods.filter((k) => k !== option.hostKey));
                        this.refresh();
                    },
                }),
                el('span', { title: modName(option.hostKey) }, [modOptionText(option)]),
            ])),
        ]);
    }

    // ---- 売却 ----

    /**
     * マヤが足りないときの売却の促し（008 §2-6）。必要な最小枚数と、売るカードを選ぶ部品（選択パネルに載せる）。
     * 選んだカードは選択に同梱して確定する。予約済みのカード（配備するカードなど）は選べない。
     * @param {{ need: number, short: number }} pay
     * @param {number[]} reserved 売れないカード
     * @param {number[]} selected 今選んでいるカード
     * @param {(ids: number[]) => void} onChange
     * @returns {HTMLElement}
     */
    sellPrompt(pay, reserved, selected, onChange) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const picker = new CardPicker(view, {
            ids: sellableHand(this.hand, reserved),
            selected,
            onChange,
        });
        const enough = selected.length >= pay.need;
        return el('div', { class: `pe-sellprompt${enough ? ' pe-sellprompt-ok' : ''}` }, [
            el('div', { class: 'pe-sellprompt-text' }, [
                format(_('Needs ${short} more Maya. Choose at least ${need} card(s) to sell (+${price} Maya each): ${n} / ${need} chosen'), {
                    short: pay.short,
                    need: pay.need,
                    price: this.price,
                    n: selected.length,
                }),
            ]),
            picker.root,
        ]);
    }

    /** ステータスバーに [Sell cards] を出す（手札があるとき） */
    addSellButton() {
        if (this.hand.length === 0) {
            return;
        }
        this.bga.statusBar.addActionButton(_('Sell cards'), () => this.startSell(), { color: 'secondary' });
    }

    /**
     * 売却を始める。
     * @param {number[]} [picked] 最初から選んでおくカード（支払い不足の促しのとき）
     */
    startSell(picked = []) {
        this.sellMode = { picked: [...picked], reserved: this.reservedForSell(), askAll: false };
        this.refresh();
    }

    /**
     * 売却に使えない手札（配備予定のカード、確定済みの内容が使うカードなど）。サーバーも確定済みの内容のカードを予約する。
     * @returns {number[]}
     */
    reservedForSell() {
        const reserved = [];
        const c = this.confirmed;
        if (c !== null) {
            reserved.push(...(Array.isArray(c.sell) ? c.sell.map(Number) : []));
            if (c.card !== undefined) {
                reserved.push(Number(c.card));
            }
        }
        return [...reserved, ...this.extraReserved()];
    }

    /**
     * 選択中の内容が使うカード（派生クラスが上書き）。
     * @returns {number[]}
     */
    extraReserved() {
        return [];
    }

    renderSell() {
        const view = this.game.view;
        const sell = this.sellMode;
        if (view === null || sell === null || view.m.me === null) {
            return;
        }
        const area = view.areas.get(view.m.me);
        const candidates = sellableHand(this.hand, sell.reserved);
        sell.picked = sell.picked.filter((id) => candidates.includes(id));
        const count = sell.picked.length;
        const gain = sellTotal(count, this.price);
        if (sell.askAll) {
            // 手札をすべて売るときだけ確認を挟む（008 §2-3）
            this.bga.statusBar.setTitle(format(_('Sell all ${n} cards in your hand (+${gain} Maya)? This cannot be undone'), { n: count, gain }));
            this.bga.statusBar.addActionButton(_('Yes, sell all'), () => this.doSell(), { color: 'alert' });
            this.bga.statusBar.addActionButton(_('Cancel'), () => {
                sell.askAll = false;
                this.refresh();
            }, { color: 'secondary' });
            return;
        }
        area?.hand.setSelectable(candidates, {
            multi: true,
            onChange: (ids) => {
                sell.picked = ids;
                this.refresh();
            },
        });
        area?.hand.setChosen(sell.picked);
        this.bga.statusBar.setTitle(count === 0 ? format(_('Choose cards to sell (+${price} Maya each)'), { price: this.price }) : format(_('Sell ${n} card(s) for +${gain} Maya'), { n: count, gain }));
        this.bga.statusBar.addActionButton(
            count === 0 ? _('Sell cards') : format(_('Sell ${n} card(s) (+${gain} Maya)'), { n: count, gain }),
            () => {
                if (count === 0) {
                    return;
                }
                if (sellsWholeHand(sell.picked, this.hand)) {
                    sell.askAll = true;
                    this.refresh();
                    return;
                }
                this.doSell();
            },
            { color: 'primary', disabled: count === 0 },
        );
        this.bga.statusBar.addActionButton(_('Cancel'), () => {
            this.cancelSell();
        }, { color: 'secondary' });
    }

    cancelSell() {
        this.sellMode = null;
        this.refresh();
    }

    async doSell() {
        const sell = this.sellMode;
        if (sell === null || sell.picked.length === 0 || this.sending) {
            return;
        }
        this.sending = true;
        try {
            await this.bga.actions.performAction('actSell', { cardIds: sell.picked }, { checkAction: false });
        } catch {
            this.sending = false;
            return;
        }
        this.sending = false;
        this.sellMode = null;
        this.afterSell(sell.picked);
        this.refresh();
    }

    /**
     * 売却した後（派生クラスが、売った札を選択から外す）。
     * @param {number[]} _sold
     */
    afterSell(_sold) {}
}
