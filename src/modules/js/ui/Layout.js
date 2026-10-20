/**
 * レイアウトの切り替え（仕様書 012 §2.2、§3.8）。`@media` ではなく、ゲーム領域の実際の幅を ResizeObserver で測り、
 * `data-layout`（wide / medium / narrow）と CSS 変数（`--pe-root-w`、`--pe-hex-w`）を JS が設定する。
 * 更新は 100 ms でデバウンスする（012 §10）。盤面の拡縮は `--pe-hex-w` の変更だけで行う（bga-zoom は使わない暫定案。未確定事項 No.3）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { cardSizes, computeHexWidth, layoutOf, narrowStepFor, ZOOM_LEVEL_LIMIT } from './layout-math.js';
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
        this.zoomLevel = 0;
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
        const width = Math.round(this.root.getBoundingClientRect().width || this.root.clientWidth || window.innerWidth);
        this.rootWidth = width;
        const layout = layoutOf(width);
        const hexW = computeHexWidth({ rootWidth: width, cols: this.getCols(), zoomLevel: this.zoomLevel, narrowStep: this.narrowStep });
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

    /** @param {number} delta ズームの段数（PC。±1） */
    zoom(delta) {
        this.zoomLevel = Math.max(-ZOOM_LEVEL_LIMIT, Math.min(ZOOM_LEVEL_LIMIT, this.zoomLevel + delta));
        this.lastManualZoom = Date.now();
        this.update();
    }

    fit() {
        this.zoomLevel = 0;
        this.lastManualZoom = Date.now();
        this.update();
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

    /** 盤面の右上のズームボタン。PC は [−][Fit][+]、スマホは [Overview][Normal][Large] */
    renderTools() {
        if (this.tools === null) {
            this.tools = el('div', { class: 'pe-board-tools', role: 'group', 'aria-label': _('Board zoom') });
        }
        const narrow = this.root.dataset.layout === 'narrow';
        const btn = (/** @type {string} */ label, /** @type {() => void} */ onClick, /** @type {boolean} */ pressed = false, /** @type {string} */ title = label) =>
            el('button', { type: 'button', class: 'pe-btn pe-btn-small', title, 'aria-pressed': pressed ? 'true' : 'false', onClick }, [label]);
        this.tools.replaceChildren(...(narrow
            ? [
                btn(_('Overview'), () => this.setNarrowStep('overview'), this.narrowStep === 'overview'),
                btn(_('Normal'), () => this.setNarrowStep('normal'), this.narrowStep === 'normal'),
                btn(_('Large'), () => this.setNarrowStep('large'), this.narrowStep === 'large'),
            ]
            : [
                btn('−', () => this.zoom(-1), false, _('Zoom out')),
                btn(_('Fit'), () => this.fit(), this.zoomLevel === 0),
                btn('+', () => this.zoom(1), false, _('Zoom in')),
            ]));
        return this.tools;
    }
}
