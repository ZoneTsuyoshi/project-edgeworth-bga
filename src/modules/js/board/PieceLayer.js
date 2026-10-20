/**
 * コマの層（仕様書 012 §3.4、§3.6）: キューブ、衛星コマ、発射拠点コマ。
 * - キューブ: 区域内の 4 マスの中心に置く。同じマスに重なる分は右上へ約 12 % ずつずらし、2 つ以上なら「×n」のバッジ。CSS の角丸の正方形。
 * - 衛星コマ: 区域の中央、区域の幅の約 0.45 倍。原画（機首が左向き）を回転して 4 方向を表す。同じ区域に複数いるときは 0.12 W ずつずらす。
 * - 発射拠点: 区域の下部中央、約 0.3 倍の幅。キューブのマスを占めない。持ち主の色を縁に出す。
 * コマは区域の外側の絶対配置の箱（CSS 変数の位置）に入れ、位置の変更を transition で動かす。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { cellOriginUnits, cellRowCol, cubeStackOffset, nearestAngle, neighbor, SATELLITE_ROTATION, satelliteStackOffset, SLOT_CENTERS, SLOT_SIZE, shiftOf, TILE_ASPECT } from './geometry.js';
import { applySprite, COLOR_LETTERS, pieceSprite } from '../cards/sprite-layout.js';
import { el } from '../ui/dom.js';

/** 衛星コマの幅（区域の幅に対する割合。012 §3.6） */
const SATELLITE_WIDTH = 0.45;
/** 発射拠点コマの幅 */
const BASE_WIDTH = 0.3;
/** キューブの一辺（区域の幅に対する割合。マスの約 8 割） */
const CUBE_SIZE = SLOT_SIZE * 0.8;
/** 移動の 1 段階の長さ（ミリ秒。012 §3.7: 0.25 秒ずつ） */
const MOVE_MS = 250;
/** 反射のはね返り（012 §3.6: 0.2 秒） */
const BOUNCE_MS = 200;

/**
 * @typedef {object} PieceSatellite
 * @property {number} satId
 * @property {string} [shape] HAMMER など
 * @property {number | null} cell
 * @property {string | null} dir
 * @property {number} fuelUsed
 * @property {number} fuelCapacity
 */


/**
 * @param {number} ms
 * @returns {Promise<void>}
 */
function wait(ms) {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}

export class PieceLayer {
    /**
     * @param {import('./BoardView.js').BoardView} boardView
     * @param {{ onSatelliteClick?: (satId: number) => void }} [opts]
     */
    constructor(boardView, opts = {}) {
        this.view = boardView;
        this.env = boardView.env;
        this.layer = boardView.piecesLayer;
        this.onSatelliteClick = opts.onSatelliteClick ?? (() => {});
        /** @type {Map<string, HTMLElement>} */
        this.boxes = new Map();
        /** @type {Map<number, number>} 衛星の表示中の角度（累積） */
        this.angles = new Map();
        /** @type {Map<number, PieceSatellite & { colorIndex: number }>} */
        this.sats = new Map();
    }

    /**
     * 区域の左上の位置（W を 1 とした単位）を CSS 変数にする。
     * @param {HTMLElement} box
     * @param {number} cell
     */
    placeBox(box, cell) {
        const data = this.view.data;
        if (data === null) {
            return;
        }
        const { row, col } = cellRowCol(cell, data.cols);
        const o = cellOriginUnits(row, col, data.rowShift);
        box.style.setProperty('--px', String(o.left));
        box.style.setProperty('--r', String(row));
    }

    /**
     * 箱（区域と同じ大きさ。中のコマは % で配置する）を作る。
     * @param {string} key
     * @param {number} cell
     * @param {string} className
     * @returns {HTMLElement}
     */
    ensureBox(key, cell, className) {
        let box = this.boxes.get(key);
        if (box === undefined) {
            box = el('div', { class: `pe-pbox ${className}`, dataset: { piece: key } });
            this.boxes.set(key, box);
            this.layer.append(box);
        }
        this.placeBox(box, cell);
        box.dataset.cell = String(cell);
        return box;
    }

    /**
     * @param {string} key
     */
    removeBox(key) {
        this.boxes.get(key)?.remove();
        this.boxes.delete(key);
    }

    /**
     * 全コマを状態から作り直す（setup・再読み込み）。存在しなくなったコマは消す。
     * @param {{
     *   players: { id: number, colorIndex: number, padCell: number | null, satellites: PieceSatellite[], links: { cell: number, slot: number, level: number }[] }[]
     * }} state
     */
    sync(state) {
        const live = new Set();
        /** @type {Map<number, number[]>} 衛星のいる区域 → 衛星 ID（重ねる順） */
        const satsByCell = new Map();
        for (const p of state.players) {
            for (const s of p.satellites) {
                if (s.cell !== null && s.cell !== undefined) {
                    satsByCell.set(s.cell, [...(satsByCell.get(s.cell) ?? []), s.satId]);
                }
            }
        }
        for (const p of state.players) {
            if (p.padCell !== null && p.padCell !== undefined) {
                this.setBase(p.id, p.padCell, p.colorIndex);
                live.add(`base:${p.id}`);
            }
            for (const l of p.links) {
                this.setCube(p.id, l, p.colorIndex);
                live.add(`cube:${p.id}:${l.cell}`);
            }
            for (const s of p.satellites) {
                if (s.cell !== null && s.cell !== undefined) {
                    this.setSatellite({ ...s, colorIndex: p.colorIndex }, satsByCell.get(s.cell)?.indexOf(s.satId) ?? 0, false);
                    live.add(`sat:${s.satId}`);
                }
            }
        }
        for (const key of [...this.boxes.keys()]) {
            if (!live.has(key) && key !== 'preview') {
                this.removeBox(key);
                if (key.startsWith('sat:')) {
                    const id = Number(key.slice(4));
                    this.angles.delete(id);
                    this.sats.delete(id);
                }
            }
        }
        this.refreshStackBadges();
    }

    // ---- 発射拠点 ----

    /**
     * 発射拠点（区域の下部中央。持ち主の色を縁に出す。012 §3.6）。
     * @param {number} playerId
     * @param {number} cell
     * @param {number} colorIndex
     */
    setBase(playerId, cell, colorIndex) {
        const box = this.ensureBox(`base:${playerId}`, cell, 'pe-pbox-base');
        box.replaceChildren();
        const piece = el('span', {
            class: 'pe-base-piece',
            dataset: { color: COLOR_LETTERS[colorIndex] ?? 'b', player: playerId },
            style: { width: `${BASE_WIDTH * 100}%` },
            title: _('Launch base'),
        });
        applySprite(piece, pieceSprite(`base_${COLOR_LETTERS[colorIndex] ?? 'b'}`), this.env.imgUrl);
        box.append(piece);
    }

    // ---- キューブ ----

    /**
     * キューブ 1 個。同じマスの段（level）ぶん右上へずらす。
     * @param {number} playerId
     * @param {{ cell: number, slot: number, level: number }} link
     * @param {number} colorIndex
     * @param {string} [key] 箱のキー（プレビュー用）
     */
    setCube(playerId, link, colorIndex, key = `cube:${playerId}:${link.cell}`) {
        const box = this.ensureBox(key, link.cell, 'pe-pbox-cube');
        box.replaceChildren();
        box.dataset.slot = String(link.slot);
        box.dataset.level = String(link.level);
        const slot = SLOT_CENTERS[link.slot] ?? SLOT_CENTERS[0];
        const off = cubeStackOffset(link.level);
        const cube = el('span', {
            class: 'pe-cube',
            dataset: { color: COLOR_LETTERS[colorIndex] ?? 'b', player: playerId },
            style: {
                left: `${(slot.x + off.dx * SLOT_SIZE) * 100}%`,
                top: `${(slot.y + (off.dy * SLOT_SIZE) / TILE_ASPECT) * 100}%`,
                width: `${CUBE_SIZE * 100}%`,
                height: `${(CUBE_SIZE / TILE_ASPECT) * 100}%`,
                zIndex: String(link.level + 1),
            },
        });
        box.append(cube);
    }

    /** マスに 2 個以上のキューブが重なっているとき、一番上のキューブに「×n」のバッジを付ける（012 §3.4） */
    refreshStackBadges() {
        /** @type {Map<string, HTMLElement[]>} */
        const groups = new Map();
        for (const [key, box] of this.boxes) {
            if (!key.startsWith('cube:')) {
                continue;
            }
            const gk = `${box.dataset.cell}:${box.dataset.slot}`;
            groups.set(gk, [...(groups.get(gk) ?? []), box]);
            box.querySelectorAll('.pe-stack-badge').forEach((b) => b.remove());
        }
        for (const boxes of groups.values()) {
            if (boxes.length >= 2) {
                const top = boxes.reduce((a, b) => (Number(b.dataset.level) > Number(a.dataset.level) ? b : a));
                const slot = SLOT_CENTERS[Number(top.dataset.slot)] ?? SLOT_CENTERS[0];
                top.append(el('span', {
                    class: 'pe-stack-badge',
                    style: { left: `${(slot.x + 0.1) * 100}%`, top: `${(slot.y + 0.1) * 100}%` },
                    title: _('Several cubes are stacked here'),
                }, [`×${boxes.length}`]));
            }
        }
    }

    // ---- 衛星コマ ----

    /**
     * 衛星コマを置く（または向き・位置を更新する）。
     * @param {PieceSatellite & { colorIndex: number }} sat
     * @param {number} [stackIndex] 同じ区域内の順番（0 始まり）
     * @param {boolean} [animate] 位置が変わるとき transition で動かす
     * @param {string} [key] 箱のキー（プレビュー用）
     */
    setSatellite(sat, stackIndex = 0, animate = true, key = `sat:${sat.satId}`) {
        if (sat.cell === null || sat.cell === undefined) {
            this.removeSatellite(sat.satId);
            return;
        }
        this.sats.set(sat.satId, sat);
        const isNew = !this.boxes.has(key);
        const box = this.ensureBox(key, sat.cell, 'pe-pbox-sat');
        box.classList.toggle('pe-no-anim', isNew || !animate || !this.env.animationsActive());
        const off = satelliteStackOffset(stackIndex);
        box.style.setProperty('--ox', String(off.dx));
        box.style.setProperty('--oy', String(off.dy));
        let piece = /** @type {HTMLElement | null} */ (box.querySelector('.pe-sat-piece'));
        if (piece === null) {
            piece = el('span', {
                class: 'pe-sat-piece',
                dataset: { satId: sat.satId, color: COLOR_LETTERS[sat.colorIndex] ?? 'b' },
                style: { width: `${SATELLITE_WIDTH * 100}%` },
                role: 'button',
                tabindex: 0,
                onClick: () => this.onSatelliteClick(sat.satId),
            });
            applySprite(piece, pieceSprite(`satellite_${(sat.shape ?? 'HAMMER').toLowerCase()}_${COLOR_LETTERS[sat.colorIndex] ?? 'b'}`), this.env.imgUrl);
            box.append(piece);
        }
        const target = SATELLITE_ROTATION[sat.dir ?? 'NE'] ?? 0;
        const angle = isNew ? target : nearestAngle(this.angles.get(sat.satId) ?? target, target);
        this.angles.set(sat.satId, angle);
        piece.style.setProperty('--rot', `${angle}deg`);
        const remaining = Math.max(0, sat.fuelCapacity - sat.fuelUsed);
        piece.title = sat.fuelUsed > sat.fuelCapacity ? `${_('Fuel')}: ${_('Out of fuel')}` : `${_('Fuel')} ${remaining} / ${sat.fuelCapacity}`;
        // 区域の幅 96 px 以上のときだけコマの下に残量の細いバーを出す（CSS が --pe-fuel-ratio を使う）
        box.style.setProperty('--pe-fuel-ratio', String(sat.fuelCapacity > 0 ? Math.min(1, remaining / sat.fuelCapacity) : 0));
    }

    /**
     * @param {number} satId
     */
    removeSatellite(satId) {
        this.removeBox(`sat:${satId}`);
        this.angles.delete(satId);
        this.sats.delete(satId);
    }

    /**
     * 衛星を 1 区域動かす（循環フェイズ。012 §3.6〜§3.7）。反射のときは短いはね返りで向きを変えてから進む。
     * 左右の端をまたぐときは、端から出て反対側のゴーストから入る 2 段で動かす（0.25 秒ずつ）。
     * @param {number} satId
     * @param {number} toCell
     * @param {string} dir 移動後の向き
     * @param {boolean} reflected
     * @returns {Promise<void>}
     */
    async moveSatellite(satId, toCell, dir, reflected) {
        const sat = this.sats.get(satId);
        const box = this.boxes.get(`sat:${satId}`);
        const data = this.view.data;
        if (sat === undefined || box === undefined || data === null || sat.cell === null) {
            return;
        }
        const animate = this.env.animationsActive();
        const from = cellRowCol(sat.cell, data.cols);
        const to = cellRowCol(toCell, data.cols);
        const fromLeft = from.col + 0.5 * shiftOf(from.row, data.rowShift);
        const toLeft = to.col + 0.5 * shiftOf(to.row, data.rowShift);
        const wrapped = Math.abs(toLeft - fromLeft) > 1.5;
        if (reflected && animate) {
            box.classList.add('pe-bounce');
            this.setSatellite({ ...sat, dir }, 0, false);
            await wait(BOUNCE_MS);
            box.classList.remove('pe-bounce');
        }
        const next = { ...sat, cell: toCell, dir };
        if (wrapped && animate) {
            // 端から出る: 進行方向へ 1 区域ぶん外へ（ゴーストの位置）。入るときは反対側のゴーストの位置から
            const leavingRight = toLeft < fromLeft;
            box.classList.remove('pe-no-anim');
            box.style.setProperty('--px', String(leavingRight ? fromLeft + 1 : fromLeft - 1));
            box.style.setProperty('--r', String(to.row));
            await wait(MOVE_MS);
            box.classList.add('pe-no-anim');
            box.style.setProperty('--px', String(leavingRight ? toLeft - 1 : toLeft + 1));
            void box.offsetWidth;
            this.setSatellite(next, 0, true);
            box.classList.remove('pe-no-anim');
            box.style.setProperty('--px', String(toLeft));
            await wait(MOVE_MS);
        } else {
            this.setSatellite(next, 0, true);
            if (animate) {
                await wait(MOVE_MS);
            }
        }
    }

    /**
     * 衛星の向きだけ変える（転回）。
     * @param {number} satId
     * @param {string} dir
     */
    turnSatellite(satId, dir) {
        const sat = this.sats.get(satId);
        if (sat !== undefined) {
            this.setSatellite({ ...sat, dir }, 0, false);
        }
    }

    /**
     * 選択中のプレビュー（本人の画面だけ。半透明。012 §3.9）。null で消す。
     * @param {{ kind: 'cube', cell: number, slot?: number, level?: number, colorIndex: number } | { kind: 'satellite', cell: number, colorIndex: number, shape?: string, dir?: string } | null} preview
     */
    setPreview(preview) {
        this.removeBox('preview');
        if (preview === null) {
            return;
        }
        if (preview.kind === 'cube') {
            this.setCube(-1, { cell: preview.cell, slot: preview.slot ?? 0, level: preview.level ?? 0 }, preview.colorIndex, 'preview');
        } else {
            this.setSatellite({ satId: -1, shape: preview.shape, cell: preview.cell, dir: preview.dir ?? 'NE', fuelUsed: 0, fuelCapacity: 1, colorIndex: preview.colorIndex }, 0, false, 'preview');
            this.sats.delete(-1);
            this.angles.delete(-1);
        }
        this.boxes.get('preview')?.classList.add('pe-preview');
    }

    /**
     * 衛星の区域（稼働中でなければ null）。
     * @param {number} satId
     * @returns {number | null}
     */
    satelliteCell(satId) {
        return this.sats.get(satId)?.cell ?? null;
    }

    /**
     * 隣の区域（左右は接続）。通信確立の前後の区域の計算に使う（表示用）。
     * @param {number} cell
     * @param {string} dir
     * @returns {number | null}
     */
    neighborOf(cell, dir) {
        const data = this.view.data;
        return data === null ? null : neighbor(cell, dir, data.cols, data.rowShift);
    }
}
