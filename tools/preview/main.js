/**
 * BGA の外で画面を描く簡易プレビュー（tools/preview/index.html）。getAllDatas の見本 JSON（010 §8.2）を読み、
 * ui/GameView.js に渡す。通知は同じ表（notifications.js）で再生できる。静的サーバーで開く: `npx http-server . -p 8765` → /tools/preview/
 * クエリ: ?sample=three-players-advanced|two-players-standard-6col、?theme=dark、?layout=narrow（パネルを上へ）、?demo=select|dpad|move|detail、
 *   ?state=<samples/states/ の見本名>（状態の操作 UI。BGA の statusBar / actions は代用品。steps で操作を再生する）
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { GameView } from '../../src/modules/js/ui/GameView.js';
import { dispatchNotification } from '../../src/modules/js/notifications.js';
import { DeployState } from '../../src/modules/js/states/DeployState.js';
import { EffectWindowState } from '../../src/modules/js/states/EffectWindowState.js';
import { LaunchState } from '../../src/modules/js/states/LaunchState.js';
import { LinkState } from '../../src/modules/js/states/LinkState.js';
import { MulliganState } from '../../src/modules/js/states/MulliganState.js';
import { PlanningPlot, SetupLaunchPadPlace } from '../../src/modules/js/states/PlacementStates.js';
import { ProposalState } from '../../src/modules/js/states/ProposalState.js';

const params = new URLSearchParams(location.search);
const sampleName = params.get('sample') ?? 'three-players-advanced';
if (params.get('theme') === 'dark') {
    document.documentElement.dataset.theme = 'dark';
}
if (params.get('layout') === 'narrow') {
    document.body.classList.add('pv-narrow');
}

// 例外は画面の上に出す（headless のスクリーンショットで原因が分かるように）
window.addEventListener('error', (e) => {
    const box = document.getElementById('pv-log');
    if (box !== null) {
        box.hidden = false;
        box.textContent += `ERROR ${e.message}\n${e.error?.stack ?? ''}\n`;
    }
});

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
            view.stateFrame.chooseDirection(cell, (dir) => view.pieces.turnSatellite(sat.satId, dir), { title: 'Choose a direction', lines: ['Up-right / Right / Down-right / Down-left / Left / Up-left'] });
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

// ---- 状態の操作 UI（?state=<見本名>） ----
const stateName = params.get('state');
if (stateName !== null) {
    const plan = await (await fetch(`./samples/states/${stateName}.json`)).json();
    const titleBox = /** @type {HTMLElement} */ (document.getElementById('pv-title'));
    const buttonBox = /** @type {HTMLElement} */ (document.getElementById('pv-buttons'));
    const logBox = /** @type {HTMLElement} */ (document.getElementById('pv-log'));
    /** BGA の代用品: ステータスバーと performAction（送った引数を画面の上に出す） */
    const bga = {
        statusBar: {
            setTitle: (/** @type {string} */ text) => {
                titleBox.textContent = text.replaceAll('${you}', 'You');
            },
            addActionButton: (/** @type {string} */ label, /** @type {() => void} */ fn, /** @type {{ color?: string, disabled?: boolean }} */ opts = {}) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.textContent = label;
                b.disabled = opts.disabled === true;
                if (opts.color === 'secondary' || opts.color === 'alert') {
                    b.className = `pv-${opts.color}`;
                }
                b.addEventListener('click', fn);
                buttonBox.append(b);
            },
            removeActionButtons: () => buttonBox.replaceChildren(),
        },
        actions: {
            performAction: async (/** @type {string} */ name, /** @type {Record<string, any>} */ args, /** @type {unknown} */ opts) => {
                logBox.hidden = false;
                logBox.textContent += `${name} ${JSON.stringify(args)} ${opts === undefined ? '' : JSON.stringify(opts)}\n`;
                if (name === 'actSell') {
                    const self = view.m.me ?? 0;
                    await dispatchNotification(view, 'cardsDiscarded', { player_id: self, cards: args.cardIds, reason: 'sell' });
                    await dispatchNotification(view, 'mayaChanged', { player_id: self, maya: view.m.players[self].maya + 3 * args.cardIds.length });
                }
                return true;
            },
        },
    };
    const game = { view };
    const classes = {
        SetupLaunchPadPlace: () => new SetupLaunchPadPlace(game, bga),
        SetupMulligan: () => new MulliganState(game, bga),
        PlanningPlot: () => new PlanningPlot(game, bga),
        EffectWindowResolve: () => new EffectWindowState(game, bga),
        DeployMainChoose: () => new DeployState(game, bga),
        LaunchMainChoose: () => new LaunchState(game, bga),
        LaunchBonusChoose: () => new LaunchState(game, bga),
        LinkMainChoose: () => new LinkState(game, bga),
        LinkBonusChoose: () => new LinkState(game, bga, true),
        ProposalKeepChoose: () => new ProposalState(game, bga),
    };
    if (plan.maya !== undefined && view.m.me !== null) {
        view.m.players[view.m.me].maya = plan.maya;
        view.panels.get(view.m.me)?.update({});
    }
    const sleep = (/** @type {number} */ ms) => new Promise((resolve) => setTimeout(resolve, ms));
    if (plan.state !== null) {
        const state = /** @type {Record<string, () => any>} */ (classes)[plan.state]();
        state.onEnteringState(plan.args, plan.active);
        Object.assign(window, { peState: state });
    }
    for (const [name, args] of plan.notifications ?? []) {
        await dispatchNotification(view, name, args);
    }
    for (const step of plan.steps ?? []) {
        await sleep(120);
        if (step.click !== undefined) {
            const nodes = document.querySelectorAll(step.click);
            /** @type {HTMLElement | undefined} */ (nodes[(step.nth ?? 1) - 1])?.click();
        } else if (step.clickText !== undefined) {
            [...buttonBox.querySelectorAll('button')].find((b) => b.textContent === step.clickText)?.click();
        } else if (step.cellClick !== undefined) {
            view.stateFrame.handleCellPick(step.cellClick);
        } else if (step.wait !== undefined) {
            await sleep(step.wait);
        }
    }
    document.body.dataset.ready = 'true';
}
