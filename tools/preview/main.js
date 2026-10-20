/**
 * BGA の外で画面を描く簡易プレビュー（tools/preview/index.html）。getAllDatas の見本 JSON（010 §8.2）を読み、
 * ui/GameView.js に渡す。通知は同じ表（notifications.js）で再生できる。静的サーバーで開く: `npx http-server . -p 8765` → /tools/preview/
 * クエリ: ?sample=three-players-advanced|two-players-standard-6col、?theme=dark、?layout=narrow（パネルを上へ）、?demo=select|dpad|move|detail
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { GameView } from '../../src/modules/js/ui/GameView.js';
import { dispatchNotification } from '../../src/modules/js/notifications.js';

const params = new URLSearchParams(location.search);
const sampleName = params.get('sample') ?? 'three-players-advanced';
if (params.get('theme') === 'dark') {
    document.documentElement.dataset.theme = 'dark';
}
if (params.get('layout') === 'narrow') {
    document.body.classList.add('pv-narrow');
}

// BGA のグローバルの代用
window._ = (/** @type {string} */ s) => s;

const raw = await (await fetch(`./samples/${sampleName}.json`)).json();
const me = raw._me ?? null;
const side = /** @type {HTMLElement} */ (document.getElementById('pv-side'));
const panelHosts = new Map();

/** @type {import('../../src/modules/js/cards/CardView.js').Env} */
const env = {
    imgUrl: (file) => `../../src/img/${file}`,
    playerPanel: (id) => {
        if (!panelHosts.has(id)) {
            const host = document.createElement('div');
            host.className = 'pv-panel';
            host.innerHTML = `<b>${raw.players[id]?.name ?? id}</b>`;
            side.append(host);
            panelHosts.set(id, host);
        }
        return panelHosts.get(id);
    },
    animationsActive: () => params.get('anim') !== 'off',
    currentPlayerId: () => (params.get('spectator') === '1' ? null : me),
    selectionMode: () => view.stateFrame.selecting,
};

const view = new GameView(env, /** @type {HTMLElement} */ (document.getElementById('game_play_area')));
view.setup(raw);
Object.assign(window, { peView: view, peNotify: (/** @type {string} */ name, /** @type {Record<string, any>} */ args) => dispatchNotification(view, name, args) });

// ---- 操作の枠を試すボタン ----
const tools = /** @type {HTMLElement} */ (document.getElementById('pv-tools'));
const addTool = (/** @type {string} */ label, /** @type {() => void} */ fn) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.textContent = label;
    b.addEventListener('click', fn);
    tools.append(b);
};
const myArea = () => view.areas.get(view.m.me ?? -1);

const demos = {
    select() {
        // 通信確立の候補（選べる / 売却すれば選べる）。タップで選択中の太線
        const cells = view.m.board?.cells.filter((c) => c.type !== 'SEA').slice(0, 14) ?? [];
        view.stateFrame.selectCells(cells.map((c, i) => ({ cell: c.cell, kind: i % 4 === 3 ? 'sellable' : 'selectable' })), (cell) => {
            view.stateFrame.markSelected(cell);
            view.stateFrame.showCost('Link cost', ['Area 5 + Pioneer 3 (to Blue) = 8 Maya', 'You will have 4 Maya left']);
        });
        view.stateFrame.showCost('Select an area', ['Tap a highlighted area']);
    },
    dpad() {
        const sat = view.m.players[view.m.me ?? 0]?.satellites.find((s) => s.cell !== null);
        if (sat?.cell !== null && sat !== undefined) {
            const cell = /** @type {number} */ (sat.cell);
            view.stateFrame.selectCells([{ cell, kind: 'selected' }]);
            view.stateFrame.chooseDirection(cell, (dir) => view.pieces.turnSatellite(sat.satId, dir), { title: 'Choose a direction', lines: ['Up-right / Down-right / Up-left / Down-left'] });
        }
    },
    async move() {
        const sat = view.m.players[view.m.me ?? 0]?.satellites.find((s) => s.cell !== null && s.satId === 14);
        if (sat !== undefined) {
            await dispatchNotification(view, 'satellitesMoved', { moves: [{ sat_id: sat.satId, from_cell: sat.cell, cell: 35 - 12 + 1, dir: 'NE', reflected: false }] });
        }
    },
    detail() {
        view.setDetailAll(true);
    },
    select_actions() {
        myArea()?.actions.setSelectable([1, 2, 5]);
        myArea()?.actions.setPlot(true, null);
    },
};
for (const [name, fn] of Object.entries(demos)) {
    addTool(name, () => fn());
}
addTool('clear', () => {
    view.stateFrame.leave();
    myArea()?.actions.setSelectable(null);
    view.setDetailAll(false);
});
const demo = params.get('demo');
if (demo !== null && demo in demos) {
    requestAnimationFrame(() => setTimeout(() => /** @type {Record<string, () => void>} */ (demos)[demo](), 300));
}
