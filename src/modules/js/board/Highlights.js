/**
 * 区域のハイライトと方向の矢印（仕様書 012 §3.9、014 §6.2・§6.4）。ハイライトは clip-path の要素には描けないので、
 * 盤面と同じ大きさの SVG に六角形の輪郭を stroke で描く。色だけに頼らず線種・太さ・バッジを併用する。
 * 左右の接続の両側（本物とゴースト）を同時に強調する（§3.7）。区域を選ぶ間は、選べない区域を暗い幕で覆う（014 §6.2）。
 * 見た目は 014 §6.1 の 5 つの意味に合わせる: 選べる（白 + 濃色の二重の輪郭）・売却すれば選べる（破線 + 硬貨の印）・選択中（金の太い輪郭）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { boardHeightUnits, cellOriginUnits, cellRowCol, ghostCells, hexVertices, ROWS, SATELLITE_DIRECTIONS } from './geometry.js';
import { svgEl } from './BoardView.js';
import { el } from '../ui/dom.js';

/**
 * ハイライトの種類。
 * selectable = 選べる（二重線）、sellable = 売却すれば選べる（破線＋「売却」バッジ）、selected = 選択中（太線）、
 * path = 進行方向の前後（アクション選択ボーナスの通信確立）。
 * @typedef {'selectable' | 'sellable' | 'selected' | 'path'} HighlightKind
 */

/** 方向の矢印の向き（回転の角度。SVG の矢印は右向きを 0 度にして描く） */
const ARROW_ANGLES = { E: 0, SE: 45, SW: 135, W: 180, NW: 225, NE: 315 };

/**
 * 方向の矢印の SVG（記号の文字は使わない。014 §1.2）。
 * @param {string} dir
 * @returns {SVGElement}
 */
export function directionIcon(dir) {
    const svg = svgEl('svg', { viewBox: '0 0 24 24', class: 'pe-dir-icon', 'aria-hidden': 'true' });
    const g = svgEl('g', { transform: `rotate(${ARROW_ANGLES[/** @type {keyof typeof ARROW_ANGLES} */ (dir)] ?? 0} 12 12)` });
    g.append(svgEl('path', { d: 'M3 12h15M12 5l7 7-7 7', fill: 'none', stroke: 'currentColor', 'stroke-width': '3', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }));
    svg.append(g);
    return svg;
}

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
        for (const node of this.view.tilesLayer.querySelectorAll('.pe-hl-pick')) {
            node.classList.remove('pe-hl-pick');
        }
        const selecting = this.pickable().length > 0;
        this.view.board.classList.toggle('pe-board-selecting', selecting);
        if (data === null) {
            return;
        }
        const rows = data.rows ?? ROWS;
        this.svg.setAttribute('viewBox', `0 0 ${data.cols + 0.5} ${boardHeightUnits(rows)}`);
        this.svg.setAttribute('preserveAspectRatio', 'none');
        const ghosts = ghostCells(data.cols, data.rowShift, rows);
        if (selecting) {
            // 区域を選ぶ間は、選べない区域（左右のゴーストも）を暗い幕で覆う（014 §6.2）
            const veil = svgEl('g', { class: 'pe-hl-veil' });
            for (let cell = 0; cell < data.cols * rows; cell++) {
                if (!this.entries.has(cell)) {
                    const { row, col } = cellRowCol(cell, data.cols);
                    const o = cellOriginUnits(row, col, data.rowShift);
                    veil.append(this.hexPolygon(o.left, o.top));
                }
            }
            for (const g of ghosts) {
                if (!this.entries.has(g.cell)) {
                    const { row, col } = cellRowCol(g.cell, data.cols);
                    veil.append(this.hexPolygon(g.left, cellOriginUnits(row, col, data.rowShift).top));
                }
            }
            this.svg.append(veil);
        }
        for (const [cell, kind] of this.entries) {
            const { row, col } = cellRowCol(cell, data.cols);
            const o = cellOriginUnits(row, col, data.rowShift);
            this.svg.append(this.polygonGroup(o.left, o.top, kind, cell));
            for (const g of ghosts.filter((x) => x.cell === cell)) {
                this.svg.append(this.polygonGroup(g.left, o.top, kind, cell));
            }
            if (kind === 'selectable' || kind === 'sellable') {
                // ホバーで明るくする対象（盤面のタイルに付ける。ハイライトの層は押せないので）
                for (const node of this.view.tilesLayer.querySelectorAll(`.pe-cell[data-cell="${cell}"]`)) {
                    node.classList.add('pe-hl-pick');
                }
            }
        }
    }

    /**
     * 幕の六角形 1 つ。
     * @param {number} left
     * @param {number} top
     * @returns {SVGElement}
     */
    hexPolygon(left, top) {
        return svgEl('polygon', { points: hexVertices(left, top, 1).map((v) => `${v.x},${v.y}`).join(' ') });
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
            // コーナーに「売却」の印（マヤの硬貨。文字は使わない）。色以外の手がかり
            const cx = left + 0.82;
            const cy = top + 0.28;
            const badge = svgEl('g', { class: 'pe-hl-badge' });
            badge.append(
                svgEl('circle', { cx: String(cx), cy: String(cy), r: '0.12' }),
                svgEl('circle', { cx: String(cx), cy: String(cy), r: '0.07', class: 'pe-hl-badge-ring' }),
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
                class: `pe-arrow pe-arrow-${dir.toLowerCase()}${opts.selected === dir ? ' pe-picked' : ''}`,
                'aria-label': directionLabel(dir),
                'aria-pressed': opts.selected === dir ? 'true' : 'false',
                disabled,
                style: { '--ax': cx + v[0] * 0.6, '--ay': cy + v[1] * 0.6 },
                onClick: () => onPick(dir),
            }, [directionIcon(dir)]);
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
