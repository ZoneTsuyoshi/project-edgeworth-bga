/**
 * トレイ（`#pe-tray`。仕様書 014 §5）。盤面の下の、卓に無い部品を選ぶ・読むための領域。箱なし。レールとラベル、その下に中身。
 * PR 1 では、選択パネル（ChoicePanel）とボトムシート（BottomSheet）を同じ場所に置き、捨て札の一覧だけをこのクラスが持つ。
 * 選択パネルとボトムシートの置き換え（1 つのトレイへ）は PR 4。中身が何も無いときはトレイごと隠す。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { clear, el } from './dom.js';

export class Tray {
    /**
     * @param {HTMLElement} root `#pe-tray`
     * @param {import('../cards/CardView.js').Env} env
     */
    constructor(root, env) {
        this.root = root;
        this.env = env;
        root.classList.add('pe-tray');
        root.setAttribute('aria-live', 'polite');
        /** 選択パネルの置き場（ChoicePanel） */
        this.panelBox = el('div', { id: 'pe-tray-panel' });
        /** コストの行・方向パッドの置き場（BottomSheet） */
        this.sheetBox = el('div', { id: 'pe-tray-sheet' });
        /** 一覧（捨て札）の置き場 */
        this.listBox = el('div', { id: 'pe-tray-list', class: 'pe-tray-list' });
        this.listBox.hidden = true;
        root.append(this.panelBox, this.sheetBox, this.listBox);
        this.sync();
    }

    /** @returns {boolean} 一覧を開いているか */
    get listOpen() {
        return !this.listBox.hidden;
    }

    /**
     * 一覧を開く（または中身を置き換える）。ラベルの行の右端に BGA 標準の [Close]（secondary）を置く。
     * @param {{ title: string, body: Node, onClose: () => void }} content
     */
    showList(content) {
        clear(this.listBox);
        const head = el('div', { class: 'pe-tray-head' }, [el('span', { class: 'pe-rail-label' }, [content.title])]);
        this.listBox.append(head, content.body);
        this.listBox.hidden = false;
        this.env.addButton(_('Close'), content.onClose, { color: 'secondary', destination: head });
        this.sync();
    }

    hideList() {
        clear(this.listBox);
        this.listBox.hidden = true;
        this.sync();
    }

    /** トレイは中身があるときだけ出す */
    sync() {
        this.root.hidden = this.panelBox.hidden && this.sheetBox.hidden && this.listBox.hidden;
    }
}
