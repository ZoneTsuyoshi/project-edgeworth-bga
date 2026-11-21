/**
 * レイアウトの切り替え（仕様書 012 §2.2、§3.8）。`@media` ではなく、ゲーム領域の実際の幅を ResizeObserver で測り、
 * `data-layout`（wide / medium / narrow）と CSS 変数（`--pe-root-w`、`--pe-hex-w`）を JS が設定する。
 * 更新は 100 ms でデバウンスする（012 §10）。盤面の拡縮は `--pe-hex-w` の変更だけで行う（bga-zoom は使わない暫定案。未確定事項 No.3）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { cardSizes, computeHexWidth, innerWidthOf, layoutOf, NARROW_STEP_ORDER, narrowStepFor, shiftNarrowStep } from './layout-math.js';
import { el } from './dom.js';

const DEBOUNCE_MS = 100;
/** 自分でズームした直後は自動切り替えをしない時間（012 エッジケース） */
const MANUAL_ZOOM_GRACE_MS = 5000;

export class Layout {
    /**
     * @param {HTMLElement} root `#pe-root`
     * @param {() => number} getCols 盤面の列数
     */
    constructor(root, getCols) {
        this.root = root;
        this.getCols = getCols;
        /** @type {'overview' | 'normal' | 'large'} */
        this.narrowStep = 'normal';
        this.lastManualZoom = 0;
        /** @type {number | null} */
        this.timer = null;
        this.rootWidth = 0;
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
        // 左右の余白（`#pe-root` の padding）を引いた内側の幅で、盤面の幅と data-layout を決める（012 追補 U-2）
        const width = innerWidthOf(outer);
        this.rootWidth = width;
        const layout = layoutOf(width);
        const hexW = computeHexWidth({ rootWidth: width, cols: this.getCols(), narrowStep: this.narrowStep });
        this.root.dataset.layout = layout;
        this.root.style.setProperty('--pe-root-w', `${width}px`);
        this.root.style.setProperty('--pe-hex-w', `${hexW}px`);
        // 区域の幅 90 px 以上: 方向の矢印を盤面に出す。それ未満はボトムシートの方向パッド（012 §3.9）
        this.root.dataset.hexSize = hexW >= 90 ? 'large' : (hexW >= 52 ? 'medium' : 'small');
        const sizes = cardSizes(layout);
        for (const [name, [w, h]] of Object.entries(sizes)) {
            this.root.style.setProperty(`--pe-${name}-w`, `${w}px`);
            this.root.style.setProperty(`--pe-${name}-h`, `${h}px`);
        }
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
        if (layoutOf(this.rootWidth) !== 'narrow' || Date.now() - this.lastManualZoom < MANUAL_ZOOM_GRACE_MS) {
            return;
        }
        this.narrowStep = narrowStepFor(viewportWidth, spanCols);
        this.update();
    }

    /** 盤面の枠の右上に重ねるズームボタン [−][⌂][+]。narrow だけ出す（PC はブラウザのズームに任せる。012 追補 U-6） */
    renderTools() {
        if (this.tools === null) {
            this.tools = el('div', { class: 'pe-board-tools', role: 'group', 'aria-label': _('Board zoom') });
        }
        const narrow = this.root.dataset.layout === 'narrow';
        this.tools.hidden = !narrow;
        const last = NARROW_STEP_ORDER.length - 1;
        const index = NARROW_STEP_ORDER.indexOf(this.narrowStep);
        const btn = (/** @type {string} */ icon, /** @type {string} */ label, /** @type {() => void} */ onClick, /** @type {boolean} */ disabled) => {
            const b = el('button', { type: 'button', class: 'pe-board-tool', title: label, 'aria-label': label, onClick }, [icon]);
            b.disabled = disabled;
            return b;
        };
        this.tools.replaceChildren(
            btn('−', _('Zoom out'), () => this.setNarrowStep(shiftNarrowStep(this.narrowStep, -1)), index <= 0),
            btn('⌂', _('Default zoom'), () => this.setNarrowStep('normal'), this.narrowStep === 'normal'),
            btn('+', _('Zoom in'), () => this.setNarrowStep(shiftNarrowStep(this.narrowStep, 1)), index >= last),
        );
        return this.tools;
    }
}
