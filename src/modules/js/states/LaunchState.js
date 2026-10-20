/**
 * 71 LaunchMainChoose / 72 LaunchBonusChoose（全員同時）: [Launch]（メインのみ）/ [Turn] / [Budget +1]（ボーナスのみ）/ [Pass]。
 * 打ち上げ: 非稼働の衛星を選び、自分の発射拠点の区域で向き（斜め 4 方向）を選ぶ。転回: 稼働中の衛星を選び、向きを選ぶ（現在と同じ向きも可）。
 * 打ち上げコスト・起動の修正（useMods）・売却の同梱はサーバーが args で渡した値を出す（008 §3-2、§3-3）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { directionLabel } from '../board/Highlights.js';
import { ActionState } from './ActionState.js';
import { format } from './logic/common.js';
import { launchActionArgs, launchQuote, launchSelectionFrom, launchState, parseLaunchable, parseTurnable, validateLaunch } from './logic/launch.js';
import { costNote, launchBlockedText, turnBlockedText } from './texts.js';

export class LaunchState extends ActionState {
    constructor(/** @type {any} */ game, /** @type {Bga} */ bga) {
        super(game, bga);
        this.sel = launchSelectionFrom(null);
    }

    /** ボーナス（72）か。`canBudget` はボーナスの args にだけある */
    get isBonus() {
        return this.mine !== null && this.mine.canBudget !== undefined;
    }

    reset() {
        this.sel = launchSelectionFrom(null);
    }

    /** @param {Record<string, any> | null} choice */
    restore(choice) {
        this.sel = launchSelectionFrom(choice);
    }

    /** @param {Record<string, any>} choice */
    summarize(choice) {
        const view = this.game.view;
        const sat = view?.satName(Number(choice.sat)) ?? String(choice.sat);
        switch (choice.op) {
            case 'launch':
                return format(_('launch ${sat} (${dir})'), { sat, dir: directionLabel(String(choice.dir)) });
            case 'turn':
                return format(_('turn ${sat} (${dir})'), { sat, dir: directionLabel(String(choice.dir)) });
            case 'budget':
                return _('raise the budget by 1');
            default:
                return _('pass');
        }
    }

    extraReserved() {
        return [...this.sel.sell];
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
        const player = view.m.players[me];
        const sel = this.sel;
        const funds = { maya: this.maya, price: this.price, sellable: this.hand.length };
        const launchable = parseLaunchable(mine.launchable);
        const turnable = parseTurnable(mine.turnable);
        const canLaunch = !this.isBonus && launchable.some((o) => launchState(o, funds).usable);
        const canTurn = turnable.some((o) => o.blocked === null);
        const canBudget = this.isBonus && mine.canBudget === true;
        let ok = sel.op === 'budget' && canBudget;
        let hint = this.isBonus ? _('${you} may turn a satellite or raise your budget (Action bonus), or pass') : _('${you} must launch a satellite, turn one, or pass');

        if (sel.op === 'launch') {
            /** @type {Map<number, Omit<import('../cards/SatelliteView.js').SatellitePick, 'onPick'>>} */
            const picks = new Map();
            for (const option of launchable) {
                const state = launchState(option, funds);
                if (sel.satId === option.satId) {
                    const picked = launchQuote(option, sel.useMods, funds);
                    picks.set(option.satId, { state: 'selected', note: costNote(picked.cost, picked.pay) });
                } else if (state.usable) {
                    picks.set(option.satId, { state: state.pay.kind === 'free' ? 'selectable' : 'sellable', note: costNote(option.cost, state.pay) });
                } else {
                    picks.set(option.satId, { state: 'blocked', note: option.blocked === null ? costNote(option.cost, state.pay) : launchBlockedText(option.blocked) });
                }
            }
            area?.setSatPicks(picks, (satId) => {
                sel.satId = sel.satId === satId ? null : satId;
                sel.dir = sel.satId === null ? null : sel.dir;
                this.refresh();
            });
            const option = launchable.find((o) => o.satId === sel.satId);
            if (option !== undefined && player.padCell !== null) {
                const quote = launchQuote(option, sel.useMods, funds);
                sel.useMods = quote.mods;
                ok = validateLaunch({ op: 'launch', satId: sel.satId, dir: sel.dir, sell: sel.sell }, { pay: quote.pay, canBudget: false }).ok;
                this.directionBox(player.padCell, sel.satId ?? 0, player.colorIndex, {
                    title: format(_('Launch ${sat}: choose the direction'), { sat: view.satName(option.satId) }),
                    lines: [format(_('Launch cost: ${cost} Maya'), { cost: quote.cost })],
                    quote,
                    mods: option.mods,
                });
                hint = sel.dir === null ? _('${you} must choose the direction of the launched satellite') : format(_('Launch ${sat} (${dir}).'), { sat: view.satName(option.satId), dir: directionLabel(sel.dir) });
            } else {
                hint = _('${you} must choose a satellite to launch');
                view.sheet.show({ title: _('Choose a satellite to launch'), lines: [_('Standby satellites with Project cards can be launched')] });
            }
        } else if (sel.op === 'turn') {
            /** @type {Map<number, Omit<import('../cards/SatelliteView.js').SatellitePick, 'onPick'>>} */
            const picks = new Map();
            for (const option of turnable) {
                if (sel.satId === option.satId) {
                    picks.set(option.satId, { state: 'selected' });
                } else if (option.blocked === null) {
                    picks.set(option.satId, { state: 'selectable' });
                } else {
                    picks.set(option.satId, { state: 'blocked', note: turnBlockedText(option.blocked) });
                }
            }
            area?.setSatPicks(picks, (satId) => {
                sel.satId = sel.satId === satId ? null : satId;
                sel.dir = null;
                this.refresh();
            });
            const option = turnable.find((o) => o.satId === sel.satId);
            const satView = sel.satId === null ? undefined : area?.satView(sel.satId);
            const cell = satView?.sat.cell ?? null;
            if (option !== undefined && cell !== null) {
                ok = validateLaunch({ op: 'turn', satId: sel.satId, dir: sel.dir, sell: [] }, { pay: null, canBudget: false }).ok;
                const same = sel.dir !== null && sel.dir === option.dir;
                this.directionBox(cell, option.satId, player.colorIndex, {
                    title: format(_('Turn ${sat}: choose the new direction'), { sat: view.satName(option.satId) }),
                    lines: [
                        option.dir === null ? '' : format(_('Current direction: ${dir}'), { dir: directionLabel(option.dir) }),
                        same ? _('Same direction: the turn effect still triggers (360 degree turn)') : '',
                    ].filter((l) => l !== ''),
                    quote: null,
                    mods: null,
                });
                hint = sel.dir === null ? _('${you} must choose the new direction') : format(_('Turn ${sat} (${dir}).'), { sat: view.satName(option.satId), dir: directionLabel(sel.dir) });
            } else {
                hint = _('${you} must choose a satellite to turn');
                view.sheet.show({ title: _('Choose a satellite to turn'), lines: [_('Active satellites can be turned. The new direction may be the same as the current one')] });
            }
        } else if (sel.op === 'budget') {
            hint = _('${you} will raise your budget by 1 (Action bonus). Confirm to raise');
        }

        this.bga.statusBar.setTitle(hint);
        if (sel.op === null) {
            if (!this.isBonus) {
                this.addOp('launch', _('Launch'), canLaunch);
            }
            this.addOp('turn', _('Turn'), canTurn);
            if (this.isBonus) {
                this.addOp('budget', _('Budget +1'), canBudget);
            }
        } else {
            this.bga.statusBar.addActionButton(_('Confirm'), () => {
                if (!ok) {
                    return;
                }
                this.send('actConfirmLaunch', launchActionArgs(sel), sel.op === 'budget' ? { op: 'budget' } : { op: sel.op, sat: sel.satId, dir: sel.dir, sell: [...sel.sell], useMods: [...sel.useMods] });
            }, { color: 'primary', disabled: !ok });
            this.bga.statusBar.addActionButton(_('Reset'), () => {
                this.reset();
                this.refresh();
            }, { color: 'secondary' });
        }
        this.bga.statusBar.addActionButton(_('Pass'), () => this.send('actConfirmLaunch', launchActionArgs({ op: 'pass' }), { op: 'pass' }), { color: 'secondary' });
    }

    /**
     * 操作の選択ボタン。選べないときは無効にする。
     * @param {'launch' | 'turn' | 'budget'} op
     * @param {string} label
     * @param {boolean} enabled
     */
    addOp(op, label, enabled) {
        this.bga.statusBar.addActionButton(label, () => {
            if (!enabled) {
                return;
            }
            this.sel.op = op;
            this.refresh();
        }, { color: 'primary', disabled: !enabled });
    }

    /**
     * 向きの選択（区域の周囲の矢印とボトムシートの方向パッド）、プレビュー、コストの内訳。
     * @param {number} cell 向きを決める区域（打ち上げは発射拠点、転回は衛星のいる区域）
     * @param {number} satId
     * @param {number} colorIndex
     * @param {{ title: string, lines: string[], quote: ReturnType<typeof launchQuote> | null, mods: import('./logic/common.js').ModsInfo | null }} p
     */
    directionBox(cell, satId, colorIndex, p) {
        const view = /** @type {import('../ui/GameView.js').GameView} */ (this.game.view);
        const sel = this.sel;
        const pick = (/** @type {string} */ dir) => {
            sel.dir = dir;
            this.refresh();
        };
        view.stateFrame.selectCells([{ cell, kind: 'selected' }]);
        view.highlights.showArrows(cell, pick, { selected: sel.dir });
        const shape = view.findSatellite(satId)?.sat.shape;
        if (sel.dir !== null) {
            view.pieces.setPreview({ kind: 'satellite', cell, colorIndex, shape, dir: sel.dir });
        }
        if (p.quote === null) {
            view.sheet.show({ title: p.title, lines: p.lines, pad: { onPick: pick, selected: sel.dir } });
            return;
        }
        this.costBox({ title: p.title, lines: p.lines, quote: p.quote, mods: p.mods, sel, reserved: [], pad: { onPick: pick, selected: sel.dir } });
    }
}
