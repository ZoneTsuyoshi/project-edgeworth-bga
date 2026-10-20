/**
 * 区域のハイライトと方向の矢印（仕様書 012 §3.9）。ハイライトは clip-path の要素には描けないので、
 * 盤面と同じ大きさの SVG に六角形の輪郭を stroke で描く。色だけに頼らず線種・太さ・バッジを併用する。
 * 左右の接続の両側（本物とゴースト）を同時に強調する（§3.7）。「選べない」区域には何も出さない。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { boardHeightUnits, cellOriginUnits, cellRowCol, ghostCells, hexVertices, ROWS, SATELLITE_DIRECTIONS } from './geometry.js';
import { svgEl } from './BoardView.js';
import { el } from '../ui/dom.js';

/**
 * ハイライトの種類。
 * selectable = 選べる（二重線＋点滅）、sellable = 売却すれば選べる（破線＋「売却」バッジ）、selected = 選択中（太線）、
 * path = 進行方向の前後（アクション選択ボーナスの通信確立）。
 * @typedef {'selectable' | 'sellable' | 'selected' | 'path'} HighlightKind
 */

/** 方向の矢印の位置（区域の中心からの向き。画面上の向き） */
const ARROW_VECTORS = { NE: [0.7, -0.7], E: [1, 0], SE: [0.7, 0.7], SW: [-0.7, 0.7], W: [-1, 0], NW: [-0.7, -0.7] };

export class Highlights {
    /**
     * @param {import('./BoardView.js').BoardView} boardView
     */
    constructor(boardView) {
        this.view = boardView;
        this.svg = svgEl('svg', { class: 'pe-hl-svg', 'aria-hidden': 'true' });
        boardView.highlightLayer.append(this.svg);
        /** @type {Map<number, HighlightKind>} */
        this.entries = new Map();
    }

    /**
     * ハイライトを置き換える。
     * @param {{ cell: number, kind: HighlightKind }[]} list
     */
    set(list) {
        this.entries = new Map(list.map((e) => [e.cell, e.kind]));
        this.draw();
    }

    clear() {
        this.set([]);
    }

    /**
     * @param {number} cell
     * @returns {HighlightKind | undefined}
     */
    kindOf(cell) {
        return this.entries.get(cell);
    }

    /** @returns {number[]} 選べる（または売却すれば選べる）区域 */
    pickable() {
        return [...this.entries].filter(([, k]) => k === 'selectable' || k === 'sellable').map(([c]) => c);
    }

    draw() {
        const data = this.view.data;
        this.svg.replaceChildren();
        if (data === null) {
            return;
        }
        const rows = data.rows ?? ROWS;
        this.svg.setAttribute('viewBox', `0 0 ${data.cols + 0.5} ${boardHeightUnits(rows)}`);
        this.svg.setAttribute('preserveAspectRatio', 'none');
        const ghosts = ghostCells(data.cols, data.rowShift, rows);
        for (const [cell, kind] of this.entries) {
            const { row, col } = cellRowCol(cell, data.cols);
            const o = cellOriginUnits(row, col, data.rowShift);
            this.svg.append(this.polygonGroup(o.left, o.top, kind, cell));
            for (const g of ghosts.filter((x) => x.cell === cell)) {
                this.svg.append(this.polygonGroup(g.left, o.top, kind, cell));
            }
        }
    }

    /**
     * 六角形の輪郭 1 つ。選べる = 白の太線の上に濃い細線（二重線）。
     * @param {number} left
     * @param {number} top
     * @param {HighlightKind} kind
     * @param {number} cell
     * @returns {SVGElement}
     */
    polygonGroup(left, top, kind, cell) {
        const points = hexVertices(left, top, 1).map((v) => `${v.x},${v.y}`).join(' ');
        const group = svgEl('g', { class: `pe-hl pe-hl-${kind}`, 'data-cell': String(cell) });
        group.append(svgEl('polygon', { points, class: 'pe-hl-outer' }), svgEl('polygon', { points, class: 'pe-hl-inner' }));
        if (kind === 'sellable') {
            // コーナーに「売却」のバッジ（硬貨のアイコン）。色以外の手がかり
            const cx = left + 0.82;
            const cy = top + 0.28;
            const badge = svgEl('g', { class: 'pe-hl-badge' });
            badge.append(
                svgEl('circle', { cx: String(cx), cy: String(cy), r: '0.11' }),
                Object.assign(svgEl('text', { x: String(cx), y: String(cy + 0.045), 'text-anchor': 'middle', 'font-size': '0.14' }), { textContent: '¥' }),
            );
            group.append(badge);
        }
        return group;
    }

    /**
     * 区域の周囲に 6 方向の矢印ボタンを出す（打ち上げ・転回。区域の幅 90 px 以上のとき。それ未満は方向パッド）。
     * @param {number} cell
     * @param {(dir: string) => void} onPick
     * @param {{ selected?: string | null, disabled?: string[] }} [opts]
     */
    showArrows(cell, onPick, opts = {}) {
        const data = this.view.data;
        this.clearArrows();
        if (data === null) {
            return;
        }
        const { row, col } = cellRowCol(cell, data.cols);
        const o = cellOriginUnits(row, col, data.rowShift);
        const cx = o.left + 0.5;
        const cy = o.top + 0.5 * (503 / 435);
        for (const dir of SATELLITE_DIRECTIONS) {
            const v = ARROW_VECTORS[/** @type {keyof typeof ARROW_VECTORS} */ (dir)];
            const disabled = opts.disabled?.includes(dir) === true;
            const btn = el('button', {
                type: 'button',
                class: `pe-arrow pe-arrow-${dir.toLowerCase()}${opts.selected === dir ? ' pe-selected' : ''}`,
                title: directionLabel(dir),
                'aria-label': directionLabel(dir),
                disabled,
                style: { '--ax': cx + v[0] * 0.6, '--ay': cy + v[1] * 0.6 },
                onClick: () => onPick(dir),
            }, [directionGlyph(dir)]);
            this.view.arrowsLayer.append(btn);
        }
    }

    clearArrows() {
        this.view.arrowsLayer.replaceChildren();
    }
}

/**
 * @param {string} dir NE / E / SE / SW / W / NW
 * @returns {string}
 */
export function directionLabel(dir) {
    const labels = { NE: _('Up-right'), E: _('Right'), SE: _('Down-right'), SW: _('Down-left'), W: _('Left'), NW: _('Up-left') };
    return labels[/** @type {keyof typeof labels} */ (dir)] ?? dir;
}

/**
 * @param {string} dir
 * @returns {string}
 */
export function directionGlyph(dir) {
    const glyphs = { NE: '↗', E: '→', SE: '↘', SW: '↙', W: '←', NW: '↖' };
    return glyphs[/** @type {keyof typeof glyphs} */ (dir)] ?? '?';
}
