/**
 * 81 LinkMainChoose / 82 LinkBonusChoose（全員同時）: 通信確立する区域と衛星を選んで [Confirm]、または [Pass]（キューブの放棄）。
 * 区域をタップ（払える = 実線、売却すれば払える = 破線、払えない = 出さない）、その区域に届く衛星が複数なら衛星を選ぶ。先に衛星を選ぶ入口もある（008 §3-4）。
 * コストの内訳（区域コスト + 先駆コスト）、起動の修正（useMods）、売却の同梱はサーバーが args で渡した値を出す。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { ActionState } from './ActionState.js';
import { format, sideAvailability, soleOption } from './logic/common.js';
import { cellKind, linkActionArgs, linkBreakdown, linkPairs, linkQuote, linkSelectionFrom, parseLinkable, validateLink } from './logic/link.js';
import { costNote } from './texts.js';

export class LinkState extends ActionState {
    /**
     * @param {any} game
     * @param {Bga} bga
     * @param {boolean} [bonus] 82 LinkBonusChoose か
     */
    constructor(game, bga, bonus = false) {
        super(game, bga);
        this.bonus = bonus;
        this.sel = linkSelectionFrom(null);
    }

    reset() {
        this.sel = linkSelectionFrom(null);
    }

    /** @param {Record<string, any> | null} choice */
    restore(choice) {
        this.sel = linkSelectionFrom(choice);
    }

    /** @param {Record<string, any>} choice */
    summarize(choice) {
        if (choice.op === 'link') {
            const view = this.game.view;
            return format(_('establish a link at area ${cell} with ${sat}'), { cell: choice.cell, sat: view?.satName(Number(choice.sat)) ?? String(choice.sat) });
        }
        return _('pass (abandon the cube)');
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
        const sel = this.sel;
        const funds = { maya: this.maya, price: this.price, sellable: this.hand.length };
        const options = parseLinkable(mine.linkable);
        const pairs = linkPairs(options, funds);
        // 区域だけ選ばれていて衛星が 1 機しか届かないときは、衛星の選択を省く（008 §3-4）
        const sole = soleOption(pairs, sel.cell);
        if (sel.cell !== null && sel.satId === null && sole !== null) {
            sel.satId = sole;
        }
        if (sel.cell !== null && sel.satId !== null && pairs.find((p) => p.a === sel.cell && p.b === sel.satId)?.usable !== true) {
            sel.satId = null;
        }
        sel.op = sel.cell !== null || sel.satId !== null ? 'link' : null;

        const cellAvail = sideAvailability(pairs, 'a', sel.satId);
        const satAvail = sideAvailability(pairs, 'b', sel.cell);

        // 区域のハイライト（払えない区域は出さない）。選択中は太線
        /** @type {{ cell: number, kind: import('../board/Highlights.js').HighlightKind }[]} */
        const cells = [];
        for (const [cell, v] of cellAvail) {
            if (!v.usable) {
                continue;
            }
            const kind = cellKind(pairs.filter((p) => p.a === cell && (sel.satId === null || p.b === sel.satId)));
            if (kind !== null && cell !== sel.cell) {
                cells.push({ cell, kind });
            }
        }
        if (sel.cell !== null) {
            cells.push({ cell: sel.cell, kind: 'selected' });
        }
        view.stateFrame.selectCells(cells, (cell) => {
            sel.cell = cell;
            this.refresh();
        });

        // 衛星の選択（その区域に届く衛星。先に衛星を選ぶ入口にもなる）
        /** @type {Map<number, Omit<import('../cards/SatelliteView.js').SatellitePick, 'onPick'>>} */
        const picks = new Map();
        for (const [satId, v] of satAvail) {
            const pair = v.entry;
            if (sel.satId === satId) {
                picks.set(satId, { state: 'selected' });
            } else if (v.usable && pair !== null) {
                picks.set(satId, { state: pair.data.pay.kind === 'free' ? 'selectable' : 'sellable', note: sel.cell === null ? undefined : costNote(pair.data.total, pair.data.pay) });
            } else {
                picks.set(satId, { state: 'blocked', note: sel.cell === null ? _('Cannot establish a link') : _('Cannot reach this area') });
            }
        }
        area?.setSatPicks(picks, (satId) => {
            sel.satId = sel.satId === satId ? null : satId;
            this.refresh();
        });

        const chosen = pairs.find((p) => p.a === sel.cell && p.b === sel.satId);
        let ok = false;
        if (chosen !== undefined) {
            const option = chosen.data;
            const quote = linkQuote(option, sel.useMods, funds);
            sel.useMods = quote.mods;
            ok = validateLink({ op: 'link', cell: sel.cell, satId: sel.satId, sell: sel.sell }, { pay: quote.pay }).ok;
            const b = linkBreakdown(option, quote.cost, this.maya);
            const lines = b.free
                ? [_('Link cost: Free')]
                : [
                    format(_('Link cost: Area ${area} + Pioneer ${pioneer}${mods} = ${total} Maya'), {
                        area: b.area,
                        pioneer: b.pioneer,
                        mods: b.modsReduction > 0 ? format(_(' - activated effects ${n}'), { n: b.modsReduction }) : '',
                        total: b.total,
                    }),
                    _('If another player links this area at the same time, no Pioneer cost is paid between you'),
                ];
            this.costBox({
                title: format(_('Establish a link at area ${cell} with ${sat}'), { cell: option.cell, sat: view.satName(option.satId) }),
                lines,
                quote,
                mods: option.mods,
                sel,
                reserved: [],
            });
            // 選択中の区域に、キューブのプレビュー（本人の画面だけ）
            const color = view.m.players[me].colorIndex;
            view.pieces.setPreview({ kind: 'cube', cell: option.cell, colorIndex: color });
        } else if (sel.cell !== null || sel.satId !== null) {
            view.sheet.show({
                title: sel.cell === null ? _('Choose an area') : _('Choose a satellite'),
                lines: [sel.cell === null ? _('Highlighted areas can be reached by this satellite') : _('Several of your satellites reach this area. Choose one')],
            });
        }

        const hint = this.bonus ? _('${you} may establish another link (Action bonus), or pass') : _('${you} must choose an area to establish a link, or pass');
        this.bga.statusBar.setTitle(chosen === undefined ? `${hint}. ${_('Tap a highlighted area (dashed = you need to sell cards)')}` : hint);
        if (sel.cell !== null || sel.satId !== null) {
            this.bga.statusBar.addActionButton(_('Confirm'), () => {
                if (!ok) {
                    return;
                }
                this.send('actConfirmLink', linkActionArgs({ op: 'link', cell: sel.cell, satId: sel.satId, sell: sel.sell, useMods: sel.useMods }), { op: 'link', cell: sel.cell, sat: sel.satId, sell: [...sel.sell], useMods: [...sel.useMods] });
            }, { color: 'primary', disabled: !ok });
            this.bga.statusBar.addActionButton(_('Reset'), () => {
                this.reset();
                this.refresh();
            }, { color: 'secondary' });
        }
        this.bga.statusBar.addActionButton(_('Pass'), () => this.send('actConfirmLink', linkActionArgs({ op: 'pass' }), { op: 'pass' }), { color: 'secondary' });
    }
}
