/**
 * 状態ごとの操作 UI の枠（仕様書 012 §3.9、§6、009 の args、010 §13）。
 * 細部は状態機械（I-07）の状態クラスとのつなぎ込みで仕上げる。ここでは次を用意する。
 *   - 同時選択の args（`participants`・`confirmed`・`_private`）から確定の印と待機表示を出す
 *   - 区域を選ぶ（ハイライト + タップ）、アクションカード・手札を選ぶ、コストの内訳と方向パッド（ボトムシート）を出す
 * ステータスバーのボタン（[Confirm]・[Pass]・[Change my choice]）は状態クラスが `bga.statusBar` に出す。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { cellRowCol } from '../board/geometry.js';
import { int } from './dom.js';

/**
 * 009 / 010 §13 の args の形（使うキーだけ。無いものは省略されうる）。
 * @typedef {object} StateArgs
 * @property {number[]} [participants]
 * @property {number[]} [confirmed]
 * @property {number[]} [vacantCells] 11 SetupLaunchPadPlace
 * @property {Record<string, any>} [_private] 参加者ごとの値（プレイヤー ID がキー）
 * @property {{ player_id: number, sources?: string[] }[]} [waiting] 31 EffectWindowResolve
 */

export class StateFrame {
    /**
     * @param {import('./GameView.js').GameView} view
     */
    constructor(view) {
        this.view = view;
        /** @type {{ cell: number, kind: import('../board/Highlights.js').HighlightKind }[]} */
        this.baseHighlights = [];
        /** @type {((cell: number) => void) | null} */
        this.pickHandler = null;
        this.selecting = false;
    }

    /**
     * 状態に入ったとき。標準の args の形に合わせて、確定の印・待機表示・ハイライトを出す。
     * @param {string} _stateName
     * @param {Record<string, any>} args
     * @param {boolean} isActive 自分が操作できるか
     */
    enter(_stateName, args, isActive) {
        const m = this.view.m;
        const participants = (args.participants ?? []).map((/** @type {unknown} */ v) => int(v));
        const confirmed = (args.confirmed ?? []).map((/** @type {unknown} */ v) => int(v));
        // 確定の印と待機の回転アイコン（確定済みはチェック）。内容は出さない（010 §9.2）
        for (const id of m.playerOrder) {
            m.players[id].confirmed = confirmed.includes(id);
            this.view.panels.get(id)?.setWaiting(participants.includes(id));
        }
        this.view.refreshMarks();

        // 区域・アクション・手札・衛星の選択は、状態ごとのクラス（states/）が isActive と args の `_private` から組み立てる。
        // ここは確定の印と待機表示だけを標準の args の形（participants・confirmed）から出す。
        if (!isActive) {
            this.leaveSelection();
        }
    }

    /** 状態を出るとき */
    leave() {
        this.leaveSelection();
        const m = this.view.m;
        if (m.me !== null) {
            this.view.areas.get(m.me)?.actions.setSelectable(null);
            this.view.areas.get(m.me)?.hand.setSelectable(null);
        }
        for (const panel of this.view.panels.values()) {
            panel.setWaiting(false);
        }
    }

    leaveSelection() {
        this.selecting = false;
        this.pickHandler = null;
        this.baseHighlights = [];
        this.view.highlights.clear();
        this.view.highlights.clearArrows();
        this.view.sheet.hide();
        this.view.pieces.setPreview(null);
    }

    /**
     * 区域を選べるようにする（ハイライト + タップ）。スマホでは選べる区域が収まる段階に自動で切り替え、その位置までスクロールする。
     * @param {{ cell: number, kind: import('../board/Highlights.js').HighlightKind }[]} list
     * @param {(cell: number) => void} [onPick]
     */
    selectCells(list, onPick) {
        this.selecting = true;
        this.baseHighlights = list;
        this.pickHandler = onPick ?? null;
        this.view.highlights.set(list);
        const data = this.view.board.data;
        if (data !== null && list.length > 0) {
            const cols = list.map((e) => cellRowCol(e.cell, data.cols).col);
            const span = Math.max(...cols) - Math.min(...cols) + 1.5;
            this.view.layout.autoZoomForSelection(span, this.view.board.viewport.clientWidth);
            this.view.board.scrollToCell(list[0].cell);
        }
    }

    restoreHighlights() {
        this.view.highlights.set(this.baseHighlights);
    }

    /**
     * 盤面のタップ。選べる区域のときだけ受ける（選べない区域は何も起きない。012 §3.9）。
     * @param {number} cell
     */
    handleCellPick(cell) {
        if (!this.selecting || this.pickHandler === null) {
            return;
        }
        if (this.view.highlights.pickable().includes(cell)) {
            this.pickHandler(cell);
        }
    }

    /**
     * 選択中の区域を太線にする。
     * @param {number | null} cell
     */
    markSelected(cell) {
        const list = this.baseHighlights.filter((e) => e.cell !== cell);
        if (cell !== null) {
            list.push({ cell, kind: 'selected' });
        }
        this.view.highlights.set(list);
    }

    /**
     * 方向の選択。区域の幅 90 px 以上は区域の周囲の矢印、それ未満はボトムシートの 2 × 2 パッド（012 §3.9）。
     * @param {number} cell
     * @param {(dir: string) => void} onPick
     * @param {{ title?: string, lines?: string[], selected?: string | null, disabled?: string[] }} [opts]
     */
    chooseDirection(cell, onPick, opts = {}) {
        const handler = (/** @type {string} */ dir) => {
            this.view.pieces.setPreview(null);
            onPick(dir);
        };
        this.view.highlights.showArrows(cell, handler, opts);
        this.view.sheet.show({
            title: opts.title,
            lines: opts.lines,
            pad: { onPick: handler, selected: opts.selected, disabled: opts.disabled },
        });
    }

    /**
     * コストの内訳を出す（サーバーが計算した args の値を並べるだけ。クライアントでは計算しない。012 §6）。
     * @param {string} title
     * @param {string[]} lines
     */
    showCost(title, lines) {
        this.view.sheet.show({ title, lines });
    }
}
