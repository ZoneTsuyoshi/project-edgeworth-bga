/**
 * 拡大表示（仕様書 012 §4.6）。PC はホバー 300〜500 ms 後、スマホ（タッチ）はタップ。選択モード中は長押し 500 ms。
 * 【作者回答 2026-10-20】PC のホバー表示は、カードを覆わずクリックを奪わない: `pointer-events: none` にして、
 * 画面の左右どちらかの端（ホバー中のカードがない側）に固定で出す。マウス押下で閉じる。選択モード中は待ち時間を長くし、
 * 右クリックで即時に出す。タップ / 長押しで開く表示（タッチ）は従来どおり画面の中央付近で、チップもタップできる。
 * すべてのカードの拡大表示はこのクラスの `bind` を通る（手札・場・衛星・選択パネル・効果の窓など）。
 * 要素は `document.body` 直下に置き（BGA の CSS zoom / transform の祖先を避ける。012 §10 教訓 4）、
 * 高さの上限は `window.innerHeight` からステータスバーを引く（教訓 3）。拡大用シートの読み込み前は通常のシートで代用する。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { applySprite, cardSprite } from './sprite-layout.js';
import { cardTextHtml } from './card-text.js';
import { zoomAvailableHeight, zoomWidth } from '../ui/layout-math.js';
import { el } from '../ui/dom.js';

const HOVER_DELAY_MS = 400;
/** 選択モード中のホバーは、通り過ぎるだけで出ないよう長めに待つ（作者要望 2026-10-20）。右クリックなら待たずに出す */
const HOVER_DELAY_SELECTING_MS = 900;
const LONG_PRESS_MS = 500;
const MOVE_THRESHOLD_PX = 8;

export class Tooltip {
    /**
     * @param {import('./CardView.js').Env} env
     */
    constructor(env) {
        this.env = env;
        /** @type {Set<string>} 読み込みが終わった拡大用シート */
        this.ready = new Set();
        /** @type {number | null} */
        this.timer = null;
        /** @type {string | null} 表示中のカード */
        this.currentKey = null;
        /** @type {'hover' | 'touch'} hover = 端に固定・操作を通す、touch = 中央・操作できる */
        this.mode = 'touch';
        this.image = el('div', { class: 'pe-zoom-img' });
        this.text = el('div', { class: 'pe-zoom-text' });
        this.chips = el('div', { class: 'pe-zoom-chips' });
        this.root = el('div', { id: 'pe-zoom', class: 'pe-zoom', hidden: true, 'aria-hidden': 'true', role: 'dialog' }, [this.image, this.chips, this.text]);
        document.body.appendChild(this.root);
        // 画面の空いた所をタップすると閉じる（スマホ。012 §4.6）
        document.addEventListener('pointerdown', (e) => {
            if (!this.root.hidden && !this.root.contains(/** @type {Node} */ (e.target))) {
                this.hide();
            }
        }, true);
        this.root.addEventListener('pointerleave', (e) => {
            if (e.pointerType === 'mouse' && this.mode === 'touch') {
                this.hide();
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.hide();
            }
        });
    }

    /**
     * 要素に拡大表示を結びつける。
     * @param {HTMLElement} node
     * @param {string} key カードのキー
     * @param {{ chips?: () => string[] }} [opts] 衛星カードの拡大表示に並べる配備カードのキー
     */
    bind(node, key, opts = {}) {
        node.dataset.peZoomKey = key;
        /** @type {{ x: number, y: number } | null} */
        let down = null;
        /** @type {number | null} */
        let press = null;
        const cancelPress = () => {
            if (press !== null) {
                clearTimeout(press);
                press = null;
            }
        };
        node.addEventListener('pointerenter', (e) => {
            if (e.pointerType === 'mouse') {
                this.cancel();
                const delay = this.env.selectionMode() ? HOVER_DELAY_SELECTING_MS : HOVER_DELAY_MS;
                this.timer = window.setTimeout(() => this.show(key, node, opts, 'hover'), delay);
            }
        });
        // 右クリックは待たずに出す（選択中でも確認できる）。ブラウザのメニューは出さない
        node.addEventListener('contextmenu', (e) => {
            if (this.env.selectionMode() || !this.root.hidden) {
                e.preventDefault();
                this.show(key, node, opts, 'hover');
            }
        });
        node.addEventListener('pointerleave', (e) => {
            if (e.pointerType === 'mouse') {
                this.cancel();
                if (this.mode === 'hover' || !this.root.matches(':hover')) {
                    this.hide();
                }
            }
        });
        node.addEventListener('pointerdown', (e) => {
            down = { x: e.clientX, y: e.clientY };
            if (e.pointerType !== 'mouse' && this.env.selectionMode()) {
                press = window.setTimeout(() => {
                    press = null;
                    down = null;
                    this.show(key, node, opts);
                }, LONG_PRESS_MS);
            }
        });
        node.addEventListener('pointermove', (e) => {
            if (down !== null && Math.hypot(e.clientX - down.x, e.clientY - down.y) > MOVE_THRESHOLD_PX) {
                cancelPress();
                down = null;
            }
        });
        node.addEventListener('pointerup', (e) => {
            cancelPress();
            // 選択モード以外のタップでも開く（タッチのみ。マウスはホバーで開く）
            if (down !== null && e.pointerType !== 'mouse' && !this.env.selectionMode()) {
                this.show(key, node, opts);
            }
            down = null;
        });
        node.addEventListener('pointercancel', () => {
            cancelPress();
            down = null;
        });
    }

    cancel() {
        if (this.timer !== null) {
            clearTimeout(this.timer);
            this.timer = null;
        }
    }

    /**
     * @param {string} key
     * @param {HTMLElement | null} anchor 表示位置の基準（無ければ画面中央）
     * @param {{ chips?: () => string[] }} [opts]
     * @param {'hover' | 'touch'} [mode]
     */
    show(key, anchor, opts = {}, mode = 'touch') {
        this.cancel();
        this.mode = mode;
        this.root.classList.toggle('pe-zoom-hover', mode === 'hover');
        const landscape = /^R\d/.test(key);
        this.currentKey = key;
        this.root.classList.toggle('pe-zoom-landscape', landscape);
        const viewWidth = Math.min(window.innerWidth, document.documentElement.clientWidth || window.innerWidth);
        this.root.style.setProperty('--pe-zoom-w', `${zoomWidth(landscape, viewWidth)}px`);

        // 拡大用シートの読み込み前は通常のシートで代用する
        const zoomSprite = cardSprite(key, 'zoom');
        const useZoom = zoomSprite !== null && this.ready.has(zoomSprite.file);
        applySprite(this.image, useZoom ? zoomSprite : cardSprite(key, 'base'), this.env.imgUrl);
        if (zoomSprite !== null && !useZoom) {
            const probe = new Image();
            probe.onload = () => {
                this.ready.add(zoomSprite.file);
                if (this.currentKey === key && !this.root.hidden) {
                    applySprite(this.image, zoomSprite, this.env.imgUrl);
                }
            };
            probe.src = this.env.imgUrl(zoomSprite.file);
        }
        this.image.classList.toggle('pe-zoom-landscape', landscape);
        this.text.innerHTML = cardTextHtml(key, this.env.imgUrl);

        // 衛星カードの拡大表示には配備されている全カードのチップを並べる。タップでそのカードの拡大表示に切り替える
        this.chips.replaceChildren();
        const chipKeys = opts.chips?.() ?? [];
        for (const chipKey of mode === 'hover' ? [] : chipKeys) {
            const label = chipKey;
            this.chips.append(el('button', { type: 'button', class: 'pe-zoom-chip', onClick: () => this.show(chipKey, anchor, opts, 'touch') }, [label]));
        }
        this.chips.hidden = mode === 'hover' || chipKeys.length === 0;

        // 上部のステータスバー（BGA の #page-title）を隠さない
        const bar = document.getElementById('page-title');
        const barBottom = bar === null ? 0 : Math.max(0, bar.getBoundingClientRect().bottom);
        this.root.style.setProperty('--pe-zoom-avail', `${zoomAvailableHeight(window.innerHeight, barBottom)}px`);
        this.root.hidden = false;
        this.root.setAttribute('aria-hidden', 'false');
        const rect = this.root.getBoundingClientRect();
        const minTop = Math.max(8, barBottom + 8);
        let left = (viewWidth - rect.width) / 2;
        let top = Math.max(minTop, (window.innerHeight - rect.height) / 2);
        if (mode === 'hover' && anchor !== null) {
            // ホバー中のカードがない側の画面の端に固定する（カードを覆わない）
            const a = anchor.getBoundingClientRect();
            const leftX = 8;
            const rightX = Math.max(8, viewWidth - rect.width - 8);
            const overlap = (/** @type {number} */ x) => Math.max(0, Math.min(x + rect.width, a.right) - Math.max(x, a.left));
            left = a.left + a.width / 2 < viewWidth / 2 ? rightX : leftX;
            if (overlap(left) > overlap(left === leftX ? rightX : leftX)) {
                left = left === leftX ? rightX : leftX;
            }
            top = Math.max(minTop, (window.innerHeight - rect.height) / 2);
        } else if (anchor !== null) {
            const a = anchor.getBoundingClientRect();
            left = Math.min(Math.max(8, a.left + a.width / 2 - rect.width / 2), Math.max(8, viewWidth - rect.width - 8));
            top = Math.min(Math.max(minTop, a.top + a.height / 2 - rect.height / 2), Math.max(minTop, window.innerHeight - rect.height - 8));
        }
        this.root.style.left = `${left}px`;
        this.root.style.top = `${top}px`;
    }

    hide() {
        this.cancel();
        this.root.hidden = true;
        this.root.setAttribute('aria-hidden', 'true');
        this.currentKey = null;
    }
}
