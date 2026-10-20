/**
 * 選択パネル（企画の残すカード、効果の問い、売却の同梱など、盤面の外で選ぶ操作を載せる枠。仕様書 012 §6）。
 * 盤面の上に出す。開いたときに画面の中央へスクロールし、枠と見出しを強調して見落とさないようにする
 * （姉妹リポジトリで、選択パネルが画面外で「ボタンが無い」ように見えた不具合の対策）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { clear, el } from './dom.js';

export class ChoicePanel {
    /**
     * @param {HTMLElement} root
     */
    constructor(root) {
        this.root = root;
        root.classList.add('pe-choice');
        root.hidden = true;
        this.opened = false;
    }

    /**
     * 開く（または中身を置き換える）。
     * @param {{ title: string, body: (Node | null | false)[], hint?: string }} content
     * @param {{ scroll?: boolean }} [opts] scroll: 開いたときに画面へスクロールする（既定は閉じていたときだけ）
     */
    show(content, opts = {}) {
        clear(this.root);
        this.root.append(el('div', { class: 'pe-choice-title' }, [content.title]));
        if (content.hint !== undefined && content.hint !== '') {
            this.root.append(el('div', { class: 'pe-choice-hint' }, [content.hint]));
        }
        for (const node of content.body) {
            if (node) {
                this.root.append(node);
            }
        }
        const wasHidden = this.root.hidden;
        this.root.hidden = false;
        if (opts.scroll ?? (wasHidden || !this.opened)) {
            this.scrollToView();
        }
        this.opened = true;
    }

    hide() {
        clear(this.root);
        this.root.hidden = true;
        this.opened = false;
    }

    scrollToView() {
        // 画面外にあるときだけ中央へ。リプレイの高速再生などでアニメーションが無効でも動くよう、behavior は auto
        const rect = this.root.getBoundingClientRect();
        if (rect.top < 0 || rect.bottom > window.innerHeight) {
            this.root.scrollIntoView({ block: 'center', behavior: 'auto' });
        }
    }
}
