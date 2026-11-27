/**
 * レイアウトの切り替え（仕様書 012 §2.2、§3.8）。`@media` ではなく、ゲーム領域の実際の幅を ResizeObserver で測り、
 * `data-layout`（wide / medium / narrow）と CSS 変数（`--pe-root-w`、`--pe-hex-w`）を JS が設定する。
 * 更新は 100 ms でデバウンスする（012 §10）。盤面の拡縮は `--pe-hex-w` の変更だけで行う（bga-zoom は使わない暫定案。未確定事項 No.3）。
 * しきい値・区域の幅の上限・場のカードの大きさは 014 §2.2。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { BOARD_HEIGHT_PER_HEX, cardSizes, computeHexWidth, NARROW_STEP_ORDER, narrowStepFor, othersColumns, rootMetrics, satelliteWidths, shiftNarrowStep, supplyCardSize } from './layout-math.js';
import { el } from './dom.js';

const DEBOUNCE_MS = 100;
/** 自分でズームした直後は自動切り替えをしない時間（012 エッジケース） */
const MANUAL_ZOOM_GRACE_MS = 5000;

export class Layout {
    /**
     * @param {HTMLElement} root `#pe-root`
     * @param {() => number} getCols 盤面の列数
     * @param {() => number} [getOthers] 他プレイヤーの人数（衛星の幅と他プレイヤーの列数に使う）
     */
    constructor(root, getCols, getOthers = () => 2) {
        this.root = root;
        this.getCols = getCols;
        this.getOthers = getOthers;
        /** @type {'overview' | 'normal' | 'large'} */
        this.narrowStep = 'normal';
        this.lastManualZoom = 0;
        /** @type {number | null} */
        this.timer = null;
        this.rootWidth = 0;
        /** @type {'wide' | 'medium' | 'narrow'} */
        this.layout = 'wide';
        /** @type {HTMLElement | null} */
        this.tools = null;
        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(() => this.schedule()).observe(root);
        }
        window.addEventListener('resize', () => this.schedule());
        this.update();
    }

    schedule() {
        if (this.timer !== null) {
            clearTimeout(this.timer);
        }
        this.timer = window.setTimeout(() => {
            this.timer = null;
            this.update();
        }, DEBOUNCE_MS);
    }

    /** 幅を測り直して data-layout と CSS 変数を更新する */
    update() {
        const outer = Math.round(this.root.getBoundingClientRect().width || this.root.clientWidth || window.innerWidth);
        // 左右の余白（`#pe-root` の padding。narrow は 8 px、それ以外は 12 px。014 §2.1）を引いた内側の幅で、盤面の幅と data-layout を決める
        const { layout, width } = rootMetrics(outer);
        this.rootWidth = width;
        this.layout = layout;
        const hexW = computeHexWidth({ rootWidth: width, cols: this.getCols(), narrowStep: this.narrowStep, innerHeight: window.innerHeight, layout });
        this.root.dataset.layout = layout;
        this.root.dataset.narrowStep = this.narrowStep;
        this.root.style.setProperty('--pe-root-w', `${width}px`);
        this.root.style.setProperty('--pe-hex-w', `${hexW}px`);
        // 区域の幅 90 px 以上: 方向の矢印を盤面に出す。それ未満はボトムシートの方向パッド（012 §3.9）
        this.root.dataset.hexSize = hexW >= 90 ? 'large' : (hexW >= 52 ? 'medium' : 'small');
        const sizes = cardSizes(layout, width);
        for (const [name, [w, h]] of Object.entries(sizes)) {
            // CSS のカスタムプロパティは大文字小文字を区別するので、名前は小文字にそろえる（`actionOther` → `--pe-actionother-w`）
            this.root.style.setProperty(`--pe-${name.toLowerCase()}-w`, `${w}px`);
            this.root.style.setProperty(`--pe-${name.toLowerCase()}-h`, `${h}px`);
        }
        // 場のカードの大きさ（wide は盤面の高さに合わせた縦 1 列。medium 72 × 101、narrow 64 × 90）
        const [supplyW, supplyH] = supplyCardSize(layout, hexW);
        this.root.style.setProperty('--pe-supply-w', `${supplyW}px`);
        this.root.style.setProperty('--pe-supply-h', `${supplyH}px`);
        this.root.style.setProperty('--pe-board-h', `${Math.round(hexW * BOARD_HEIGHT_PER_HEX)}px`);
        // 衛星カードの幅は流動（px の CSS 変数。012 追補 U-6）
        const columns = othersColumns(layout, this.getOthers(), width);
        const sat = satelliteWidths({ layout, rootWidth: width, othersColumns: columns });
        this.root.style.setProperty('--pe-satellite-w', `${sat.mine}px`);
        this.root.style.setProperty('--pe-satellite-other-w', `${sat.other}px`);
        this.root.style.setProperty('--pe-others-cols', String(columns));
        this.renderTools();
    }

    /** @param {'overview' | 'normal' | 'large'} step */
    setNarrowStep(step) {
        this.narrowStep = step;
        this.lastManualZoom = Date.now();
        this.update();
    }

    /**
     * 選択モードに入ったとき、選べる区域が収まる最大の段階へ切り替える（narrow のみ。直前に手動でズームしていたら何もしない）。
     * @param {number} spanCols 選べる区域の外接矩形の幅（区域数）
     * @param {number} viewportWidth
     */
    autoZoomForSelection(spanCols, viewportWidth) {
        if (this.layout !== 'narrow' || Date.now() - this.lastManualZoom < MANUAL_ZOOM_GRACE_MS) {
            return;
        }
        this.narrowStep = narrowStepFor(viewportWidth, spanCols);
        this.update();
    }

    /** 盤面の上の拡縮の行 [−][⌂][+]（高さ 36 px、右寄せ、通常の流れの中。014 §2.5）。narrow だけ出す（PC はブラウザのズームに任せる。A.5） */
    renderTools() {
        if (this.tools === null) {
            this.tools = el('div', { class: 'pe-board-tools', role: 'group', 'aria-label': _('Board zoom') });
        }
        const narrow = this.root.dataset.layout === 'narrow';
        this.tools.hidden = !narrow;
        const last = NARROW_STEP_ORDER.length - 1;
        const index = NARROW_STEP_ORDER.indexOf(this.narrowStep);
        const btn = (/** @type {string} */ icon, /** @type {string} */ label, /** @type {() => void} */ onClick, /** @type {boolean} */ disabled) => {
            const b = el('button', { type: 'button', class: 'pe-board-tool', 'aria-label': label, onClick });
            b.append(zoomIcon(icon));
            b.disabled = disabled;
            return b;
        };
        this.tools.replaceChildren(
            btn('M5 12h14', _('Zoom out'), () => this.setNarrowStep(shiftNarrowStep(this.narrowStep, -1)), index <= 0),
            btn('M4 11l8-7 8 7M6 10v9h12v-9', _('Default zoom'), () => this.setNarrowStep('normal'), this.narrowStep === 'normal'),
            btn('M5 12h14M12 5v14', _('Zoom in'), () => this.setNarrowStep(shiftNarrowStep(this.narrowStep, 1)), index >= last),
        );
        return this.tools;
    }
}

/**
 * 拡縮の行のアイコン（SVG。文字の記号は使わない。014 §1.2）。
 * @param {string} path 24 × 24 の線の path
 * @returns {SVGElement}
 */
function zoomIcon(path) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('width', '18');
    svg.setAttribute('height', '18');
    svg.setAttribute('aria-hidden', 'true');
    const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    p.setAttribute('d', path);
    p.setAttribute('fill', 'none');
    p.setAttribute('stroke', 'currentColor');
    p.setAttribute('stroke-width', '2');
    p.setAttribute('stroke-linecap', 'square');
    svg.append(p);
    return svg;
}
