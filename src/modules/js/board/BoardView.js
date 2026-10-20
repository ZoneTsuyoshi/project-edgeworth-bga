/**
 * 盤面の描画（仕様書 012 §3）。区域タイル方式: 区域ごとに正立のタイル（スプライト）を置くので、180° 回転した盤面でも
 * 区域内の 4 マスは回らない（003 §2.3）。区域の位置は CSS 変数 `--pe-hex-w` から calc で決めるので、拡縮は変数の変更だけで済む。
 * 層（下から）: 区域タイル、地域アイコン、地域の外周（SVG）、コマ（PieceLayer）、ハイライト（Highlights）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { boardHeightUnits, cellOriginUnits, cellRowCol, ghostCells, regionBorderSegments, regionCenterCell, ROWS } from './geometry.js';
import { applySprite, pieceSprite, tileSprite } from '../cards/sprite-layout.js';
import { el } from '../ui/dom.js';

const SVG_NS = 'http://www.w3.org/2000/svg';
/** タップとスクロールの区別（pointermove がこの距離を超えたら選択としない。012 §10） */
const MOVE_THRESHOLD_PX = 8;

/**
 * @typedef {object} BoardData
 * @property {number} cols 12 / 9 / 6
 * @property {number} [rows] 常に 6
 * @property {number} rowShift 行のずれ P（0 / 1）
 * @property {{ br: number, bc: number, board: string, flip?: boolean }[]} regions
 * @property {{ cell: number, type: string, region: string }[]} cells
 */

/**
 * @param {string} tag
 * @param {Record<string, string>} attrs
 * @returns {SVGElement}
 */
export function svgEl(tag, attrs = {}) {
  const node = document.createElementNS(SVG_NS, tag);
  for (const [k, v] of Object.entries(attrs)) {
    node.setAttribute(k, v);
  }
  return /** @type {SVGElement} */ (node);
}

export class BoardView {
    /**
     * @param {import('../cards/CardView.js').Env} env
     * @param {HTMLElement} host 盤面を入れる要素
     */
    constructor(env, host) {
        this.env = env;
        this.host = host;
        /** @type {BoardData | null} */
        this.data = null;
        /** @type {Map<number, HTMLElement>} */
        this.cellEls = new Map();
        /** @type {(cell: number) => void} 区域が選ばれたとき（12 §10: pointerup、移動 8 px 以内） */
        this.onPick = () => {};
        this.viewport = el('div', { class: 'pe-board-viewport', id: 'pe-board-viewport' });
        this.board = el('div', { class: 'pe-board', id: 'pe-board' });
        this.tilesLayer = el('div', { class: 'pe-tiles' });
        this.bordersSvg = svgEl('svg', { class: 'pe-borders', 'aria-hidden': 'true' });
        this.piecesLayer = el('div', { class: 'pe-pieces' });
        this.highlightLayer = el('div', { class: 'pe-highlights' });
        this.arrowsLayer = el('div', { class: 'pe-arrows' });
        this.board.append(this.tilesLayer, this.bordersSvg, this.piecesLayer, this.highlightLayer, this.arrowsLayer);
        // 上下は接続せず反射する。薄い帯にシェブロンを敷く（012 §3.7）。枠は盤面より狭いとき中央、広いとき横スクロール
        this.frame = el('div', { class: 'pe-board-frame' }, [
            el('div', { class: 'pe-reflect pe-reflect-top', 'aria-hidden': 'true', title: _('Satellites bounce back here') }),
            this.board,
            el('div', { class: 'pe-reflect pe-reflect-bottom', 'aria-hidden': 'true', title: _('Satellites bounce back here') }),
        ]);
        this.viewport.append(this.frame);
        host.append(this.viewport);
        this.bindPointer();
    }

    /** 区域のタップ（Pointer Events。スクロールとの区別のため移動量で判定する。012 §10） */
    bindPointer() {
        /** @type {{ x: number, y: number, cell: number } | null} */
        let down = null;
        const cellOf = (/** @type {EventTarget | null} */ target) => {
            const node = /** @type {HTMLElement | null} */ (/** @type {Element | null} */ (target)?.closest?.('.pe-cell'));
            return node === null ? null : Number(node.dataset.cell);
        };
        this.tilesLayer.addEventListener('pointerdown', (e) => {
            const cell = cellOf(e.target);
            down = cell === null ? null : { x: e.clientX, y: e.clientY, cell };
        });
        this.tilesLayer.addEventListener('pointermove', (e) => {
            if (down !== null && Math.hypot(e.clientX - down.x, e.clientY - down.y) > MOVE_THRESHOLD_PX) {
                down = null;
            }
        });
        this.tilesLayer.addEventListener('pointerup', (e) => {
            const cell = cellOf(e.target);
            if (down !== null && cell === down.cell) {
                this.onPick(cell);
            }
            down = null;
        });
        this.tilesLayer.addEventListener('pointercancel', () => {
            down = null;
        });
    }

    /**
     * 盤面（区域 72 個＋ゴースト＋地域の外周）を作る。ゲーム中は変わらない（004 エッジケース）ので、1 回だけ呼ぶ。
     * @param {BoardData} data
     */
    render(data) {
        this.data = data;
        const rows = data.rows ?? ROWS;
        this.frame.style.setProperty('--pe-cols', String(data.cols));
        this.frame.style.setProperty('--pe-rows', String(rows));
        this.frame.style.setProperty('--pe-rowshift', String(data.rowShift));
        this.tilesLayer.replaceChildren();
        this.cellEls.clear();

        const centers = new Map(data.regions.map((r) => [regionCenterCell(r.br, r.bc, data.cols), r.board]));
        /** @type {Record<number, { type: string, region: string }>} */
        const byCell = {};
        for (const c of data.cells) {
            byCell[c.cell] = { type: String(c.type).toLowerCase(), region: c.region };
        }

        for (let cell = 0; cell < data.cols * rows; cell++) {
            const info = byCell[cell];
            if (info === undefined) {
                continue;
            }
            const node = this.createCellEl(cell, info.type, info.region, centers.get(cell), false);
            this.cellEls.set(cell, node);
            this.tilesLayer.append(node);
        }
        for (const g of ghostCells(data.cols, data.rowShift, rows)) {
            const info = byCell[g.cell];
            if (info === undefined) {
                continue;
            }
            const node = this.createCellEl(g.cell, info.type, info.region, centers.get(g.cell), true);
            node.style.setProperty('--px', String(g.left));
            node.dataset.side = g.side;
            this.tilesLayer.append(node);
        }
        this.renderBorders(data, byCell, rows);
    }

    /**
     * @param {number} cell
     * @param {string} type
     * @param {string} region
     * @param {string | undefined} iconLetter 地域アイコン（中央の区域だけ）
     * @param {boolean} ghost
     * @returns {HTMLElement}
     */
    createCellEl(cell, type, region, iconLetter, ghost) {
        const data = /** @type {BoardData} */ (this.data);
        const { row, col } = cellRowCol(cell, data.cols);
        const origin = cellOriginUnits(row, col, data.rowShift);
        const node = el('div', {
            class: `pe-cell pe-type-${type}${ghost ? ' pe-ghost' : ''}`,
            dataset: { cell, row, col, type, region },
            style: { '--r': row, '--px': origin.left },
        });
        applySprite(node, tileSprite(type), this.env.imgUrl);
        if (iconLetter !== undefined) {
            const icon = el('span', { class: 'pe-region-icon', 'aria-hidden': 'true' });
            applySprite(icon, pieceSprite(`area_${iconLetter}`), this.env.imgUrl);
            node.append(icon);
        }
        return node;
    }

    /**
     * 地域の外周（隣り合う区域の地域が違う辺だけ）を 1 枚の SVG に白い太線で描く（012 §3.5）。
     * @param {BoardData} data
     * @param {Record<number, { type: string, region: string }>} byCell
     * @param {number} rows
     */
    renderBorders(data, byCell, rows) {
        const regionOfCell = [];
        for (let i = 0; i < data.cols * rows; i++) {
            regionOfCell.push(byCell[i]?.region ?? '');
        }
        const svg = this.bordersSvg;
        svg.replaceChildren();
        svg.setAttribute('viewBox', `0 0 ${data.cols + 0.5} ${boardHeightUnits(rows)}`);
        svg.setAttribute('preserveAspectRatio', 'none');
        for (const s of regionBorderSegments(regionOfCell, data.cols, data.rowShift, rows)) {
            svg.append(svgEl('line', { x1: String(s.x1), y1: String(s.y1), x2: String(s.x2), y2: String(s.y2), class: 'pe-border-line' }));
        }
    }

    /**
     * @param {number} cell
     * @returns {HTMLElement | undefined}
     */
    cellEl(cell) {
        return this.cellEls.get(cell);
    }

    /**
     * 区域が画面の中央に来るように盤面をスクロールする（スマホ。012 §3.8）。
     * @param {number} cell
     */
    scrollToCell(cell) {
        const node = this.cellEls.get(cell);
        if (node === undefined) {
            return;
        }
        const left = node.offsetLeft + node.offsetWidth / 2 - this.viewport.clientWidth / 2;
        this.viewport.scrollTo({ left: Math.max(0, left), behavior: this.env.animationsActive() ? 'smooth' : 'auto' });
    }
}
