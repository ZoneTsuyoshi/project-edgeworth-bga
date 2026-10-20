/**
 * 衛星 1 機の表示（仕様書 012 §4.2〜§4.5）。
 * 簡略表示（既定）: 衛星カードの上に配備カードの効果アイコンの帯を重ね、その下に配備カードのチップ（最大 4 行）と燃料ゲージ。
 * 詳細表示: 紙どおり、衛星カードの上に配備カードを下側約 55 % が見える向きで重ね、燃料コマを燃料庫の位置に置く。
 * 起動効果の使用済みは歯車に × を重ね、カードを少し暗くする（008 §4）。歯車と × は画像に焼かない。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { createCard } from './CardView.js';
import { applySprite, cardSprite, COLOR_LETTERS, pieceSprite, projectKey } from './sprite-layout.js';
import { chipInfo } from './card-text.js';
import { fuelSlotOrder, projectFuelSlots, SATELLITE_FUEL_SLOTS } from './fuel-slots.js';
import { SATELLITE_ROTATION } from '../board/geometry.js';
import { el } from '../ui/dom.js';

/** 簡略表示で効果アイコンの帯を置く位置（衛星カードに対する割合。上の暗い枠の中に 2 × 2。012 §4.2） */
const STRIP_BOX = { x: 0.3, y: 0.025, w: 0.555, h: 0.34, cols: 2, rows: 2, gap: 0.01 };

/** 衛星カードの燃料庫の数（002 §6。初期燃料庫 1 + 燃料庫 1） */
const SATELLITE_FUEL_COUNT = 2;

/**
 * @typedef {object} SatelliteData
 * @property {number} satId
 * @property {string} card 衛星カードの面（R001B など）
 * @property {string} [shape]
 * @property {number | null} cell
 * @property {string | null} dir
 * @property {number} fuelUsed
 * @property {number} fuelCapacity
 * @property {boolean} [grace]
 * @property {number} [activation] 衛星カードの起動効果の使用済み（0 / 1）
 * @property {{ id: number, activation: number }[]} cards 配備カード（段の昇順。id は P の番号）
 * @property {number} colorIndex 0〜3
 */

/**
 * 燃料の表示値。c = 消費、C = 容量。R = C - c。燃料切れは c > C（008 §7）。
 * @param {number} fuelUsed
 * @param {number} capacity
 * @returns {{ remaining: number, outOfFuel: boolean, markerIndex: number }} markerIndex はスロットの添字（燃料切れは capacity = 枠の外）
 */
export function fuelGauge(fuelUsed, capacity) {
  const outOfFuel = fuelUsed > capacity;
  return {
    remaining: Math.max(0, capacity - fuelUsed),
    outOfFuel,
    markerIndex: outOfFuel ? capacity : Math.min(fuelUsed, Math.max(0, capacity - 1)),
  };
}

/**
 * 歯車（起動効果）のアイコン。使用済みは × を重ねる。
 * @param {boolean} used
 * @param {boolean} dim 稼働中のみ発動の効果が非稼働の衛星にあるとき（薄く）
 * @returns {HTMLElement}
 */
export function gearIcon(used, dim) {
  const node = el('span', {
    class: `pe-gear${used ? ' pe-gear-used' : ''}${dim ? ' pe-gear-dim' : ''}`,
    title: used ? _('Used this round') : (dim ? _('Active satellites only') : _('Activated effect')),
    role: 'img',
    'aria-label': used ? _('Used this round') : _('Activated effect'),
  });
  node.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Zm8.5 5.2v-3.4l-2.1-.4a6.6 6.6 0 0 0-.9-2.1l1.2-1.8-2.4-2.4-1.8 1.2a6.6 6.6 0 0 0-2.1-.9L12.1 2H8.7l-.4 2.1a6.6 6.6 0 0 0-2.1.9L4.4 3.8 2 6.2l1.2 1.8c-.4.7-.7 1.4-.9 2.1l-2.1.4v3.4l2.1.4c.2.7.5 1.4.9 2.1L2 18.2l2.4 2.4 1.8-1.2c.7.4 1.4.7 2.1.9l.4 2.1h3.4l.4-2.1c.7-.2 1.4-.5 2.1-.9l1.8 1.2 2.4-2.4-1.2-1.8c.4-.7.7-1.4.9-2.1l2.1-.4Z" transform="scale(.9) translate(1.3 1.3)"/></svg>'
    + '<svg class="pe-gear-x" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19" stroke-width="3.2" stroke-linecap="round"/></svg>';
  return node;
}

export class SatelliteView {
    /**
     * @param {import('./CardView.js').Env} env
     * @param {import('./Tooltip.js').Tooltip} tooltip
     * @param {SatelliteData} sat
     * @param {{ mine: boolean, ownerName?: string, compact?: boolean }} opts
     */
    constructor(env, tooltip, sat, opts) {
        this.env = env;
        this.tooltip = tooltip;
        this.sat = sat;
        this.opts = opts;
        /** 詳細表示（紙どおり）か */
        this.detail = false;
        this.root = el('div', { class: 'pe-sat', dataset: { satId: sat.satId } });
        this.render();
    }

    /**
     * @param {SatelliteData} sat
     */
    update(sat) {
        this.sat = sat;
        this.render();
    }

    /**
     * @param {boolean} detail
     */
    setDetail(detail) {
        this.detail = detail;
        this.render();
    }

    /** 稼働中か（sat_cell が NULL でない。010 §5.1） */
    get active() {
        return this.sat.cell !== null && this.sat.cell !== undefined;
    }

    /** @returns {string[]} 配備カードのキー（P001 形式） */
    deployedKeys() {
        return this.sat.cards.map((c) => projectKey(c.id));
    }

    render() {
        const sat = this.sat;
        this.root.classList.toggle('pe-sat-active', this.active);
        this.root.classList.toggle('pe-sat-detail', this.detail);
        this.root.dataset.color = COLOR_LETTERS[sat.colorIndex] ?? 'b';
        this.root.replaceChildren(
            this.detail ? this.renderDetail() : this.renderSimple(),
            this.renderFuel(),
            this.renderFooter(),
        );
    }

    /** 衛星カード（横長）。拡大表示を結びつける */
    renderSatelliteCard() {
        const card = createCard(this.env, this.sat.card, { classes: ['pe-sat-card'] });
        this.tooltip.bind(card, this.sat.card, { chips: () => this.deployedKeys() });
        return card;
    }

    /** 簡略表示: 衛星カード + 効果アイコンの帯 + チップ */
    renderSimple() {
        const wrap = el('div', { class: 'pe-sat-simple' });
        const card = this.renderSatelliteCard();
        // 効果アイコンの帯を、衛星カードの上の暗い枠の中に 2 × 2 で重ねる
        const b = STRIP_BOX;
        const cellW = (b.w - b.gap * (b.cols - 1)) / b.cols;
        const cellH = (b.h - b.gap * (b.rows - 1)) / b.rows;
        this.sat.cards.forEach((c, i) => {
            const key = projectKey(c.id);
            const info = chipInfo(key);
            const strip = el('div', {
                class: `pe-strip${c.activation === 1 ? ' pe-used' : ''}`,
                dataset: { key, level: i + 1 },
                style: {
                    left: `${(b.x + (i % b.cols) * (cellW + b.gap)) * 100}%`,
                    top: `${(b.y + Math.floor(i / b.cols) * (cellH + b.gap)) * 100}%`,
                    width: `${cellW * 100}%`,
                    height: `${cellH * 100}%`,
                },
            });
            applySprite(strip, cardSprite(key, 'effects'), this.env.imgUrl);
            if (info?.activated) {
                strip.append(gearIcon(c.activation === 1, info.activeOnly && !this.active));
            }
            this.tooltip.bind(strip, key);
            card.append(strip);
        });
        wrap.append(card, this.renderChips());
        return wrap;
    }

    /** 配備カードのチップ（種類・名前・名声・燃料庫の数・歯車）。最大 4 行（012 §4.2） */
    renderChips() {
        const list = el('ul', { class: 'pe-chips' });
        this.sat.cards.forEach((c, i) => {
            const key = projectKey(c.id);
            const info = chipInfo(key);
            const used = c.activation === 1;
            const item = el('li', {
                class: `pe-chip${used ? ' pe-used' : ''}`,
                dataset: { key, type: info?.type.toLowerCase() ?? '' },
                tabindex: 0,
            }, [
                el('span', { class: 'pe-chip-level' }, [String(i + 1)]),
                el('span', { class: 'pe-chip-name' }, [info?.name ?? key]),
                el('span', { class: 'pe-chip-meta', title: _('Fame / fuel slots') }, [
                    el('span', { class: 'pe-chip-fame' }, [String(info?.fame ?? 0)]),
                    el('span', { class: 'pe-chip-fuel' }, [String(info?.fuelSlots ?? 0)]),
                ]),
                info?.activated ? gearIcon(used, info.activeOnly && !this.active) : null,
                info?.activeOnly && !info.activated ? el('span', { class: 'pe-chip-active-only', title: _('Active satellites only') }, ['◎']) : null,
            ]);
            this.tooltip.bind(item, key);
            list.append(item);
        });
        for (let i = this.sat.cards.length; i < 4; i++) {
            list.append(el('li', { class: 'pe-chip pe-chip-empty' }, [el('span', { class: 'pe-chip-level' }, [String(i + 1)])]));
        }
        return list;
    }

    /** 詳細表示: 紙どおり。配備カードを下側約 55 % が見える向きで重ね、燃料コマを燃料庫の位置に置く */
    renderDetail() {
        const wrap = el('div', { class: 'pe-sat-paper' });
        const cards = this.sat.cards;
        const stack = el('div', { class: 'pe-paper-stack', style: { '--pe-paper-n': cards.length } });
        cards.forEach((c, i) => {
            const key = projectKey(c.id);
            const node = createCard(this.env, key, { classes: ['pe-paper-card', ...(c.activation === 1 ? ['pe-used'] : [])] });
            node.style.setProperty('--pe-paper-level', String(i));
            const info = chipInfo(key);
            projectFuelSlots(info?.fuelSlots ?? 0).forEach((slot) => {
                node.append(el('span', {
                    class: 'pe-fuel-slot',
                    style: { left: `${slot.x * 100}%`, top: `${slot.y * 100}%`, width: `${slot.w * 100}%`, height: `${slot.h * 100}%` },
                }));
            });
            if (info?.activated) {
                node.append(gearIcon(c.activation === 1, info.activeOnly && !this.active));
            }
            this.tooltip.bind(node, key);
            stack.append(node);
        });
        const satCard = this.renderSatelliteCard();
        SATELLITE_FUEL_SLOTS.slice(0, SATELLITE_FUEL_COUNT).forEach((slot) => {
            satCard.append(el('span', {
                class: 'pe-fuel-slot',
                style: { left: `${slot.x * 100}%`, top: `${slot.y * 100}%`, width: `${slot.w * 100}%`, height: `${slot.h * 100}%` },
            }));
        });
        // 燃料コマを、下から数えた c 番目のスロットに置く（c が容量を超えたら枠の外。簡略表示のゲージと同じ規則）
        this.placePaperMarker(stack, satCard);
        if (cards.length > 0) {
            wrap.append(stack);
        }
        wrap.append(satCard);
        return wrap;
    }

    /**
     * @param {HTMLElement} stack
     * @param {HTMLElement} satCard
     */
    placePaperMarker(stack, satCard) {
        const slots = fuelSlotOrder(SATELLITE_FUEL_COUNT, this.sat.cards.map((c) => chipInfo(projectKey(c.id))?.fuelSlots ?? 0));
        const g = fuelGauge(this.sat.fuelUsed, slots.length);
        const target = g.outOfFuel ? null : slots[g.markerIndex];
        if (target === undefined || target === null) {
            return;
        }
        const host = target.source === 'satellite' ? satCard : /** @type {HTMLElement} */ (stack.children[target.source]);
        const rects = target.source === 'satellite' ? SATELLITE_FUEL_SLOTS : projectFuelSlots(chipInfo(projectKey(this.sat.cards[target.source].id))?.fuelSlots ?? 0);
        const r = rects[target.index];
        if (host === undefined || r === undefined) {
            return;
        }
        host.append(this.createFuelPiece('pe-fuel-piece pe-fuel-on-paper', { left: `${(r.x + r.w / 2) * 100}%`, top: `${(r.y + r.h / 2) * 100}%` }));
    }

    /**
     * 燃料コマ（所有者の色。コマ・アイコンのスプライト）。
     * @param {string} classes
     * @param {Record<string, string>} [style]
     * @returns {HTMLElement}
     */
    createFuelPiece(classes, style = {}) {
        const piece = el('span', { class: classes, style });
        applySprite(piece, pieceSprite(`fuel_${COLOR_LETTERS[this.sat.colorIndex] ?? 'b'}`), this.env.imgUrl);
        return piece;
    }

    /** 燃料ゲージ: 横一列のスロット（左が初期燃料庫）+ 「Fuel R / C」。燃料切れは枠の外に Out of fuel（012 §4.3） */
    renderFuel() {
        const cap = this.sat.fuelCapacity;
        const g = fuelGauge(this.sat.fuelUsed, cap);
        const slots = el('div', { class: 'pe-fuel-row', role: 'img', 'aria-label': `${_('Fuel')} ${g.remaining} / ${cap}` });
        for (let i = 0; i < cap; i++) {
            const slot = el('span', { class: `pe-fuel-cell${i === 0 ? ' pe-fuel-initial' : ''}` });
            if (!g.outOfFuel && i === g.markerIndex) {
                slot.append(this.createFuelPiece(`pe-fuel-piece${g.remaining === 0 ? ' pe-fuel-empty' : ''}`));
            }
            slots.append(slot);
        }
        const out = el('span', { class: `pe-fuel-out${g.outOfFuel ? ' pe-on' : ''}`, title: _('Will land at the next landing check.') });
        if (g.outOfFuel) {
            out.append(this.createFuelPiece('pe-fuel-piece'));
        }
        slots.append(out);
        const label = el('span', { class: `pe-fuel-label${g.outOfFuel ? ' pe-out-of-fuel' : ''}${g.remaining <= 1 && !g.outOfFuel ? ' pe-fuel-low' : ''}` }, [
            g.outOfFuel ? _('Out of fuel') : `${_('Fuel')} ${g.remaining} / ${cap}`,
        ]);
        return el('div', { class: 'pe-fuel' }, [slots, label]);
    }

    /** 稼働状態・向き・衛星カードの起動効果、詳細表示の切り替えボタン */
    renderFooter() {
        const sat = this.sat;
        const status = el('span', { class: 'pe-sat-status' }, [
            this.active ? `${_('Active')} ${sat.dir ?? ''}` : _('Standby'),
        ]);
        if (this.active && sat.dir !== null && sat.dir !== undefined) {
            status.prepend(el('span', { class: 'pe-dir-arrow', style: { transform: `rotate(${SATELLITE_ROTATION[sat.dir] ?? 0}deg)` }, 'aria-hidden': 'true' }, ['◀']));
        }
        const detail = el('button', {
            type: 'button',
            class: 'pe-btn pe-btn-small pe-sat-detail-btn',
            'aria-pressed': this.detail ? 'true' : 'false',
            onClick: () => this.setDetail(!this.detail),
        }, [this.detail ? _('Simple') : _('Detail')]);
        return el('div', { class: 'pe-sat-footer' }, [
            status,
            sat.activation === 1 ? el('span', { class: 'pe-sat-used' }, [_('Used'), gearIcon(true, false)]) : null,
            detail,
        ]);
    }
}
