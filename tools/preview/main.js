/**
 * BGA の外で画面を描く簡易プレビュー（tools/preview/index.html）。getAllDatas の見本 JSON（010 §8.2）を読み、
 * ui/GameView.js に渡す。通知は同じ表（notifications.js）で再生できる。静的サーバーで開く: `npx http-server . -p 8765` → /tools/preview/
 * BGA の枠に近い見た目（上部バー・ステータスバー・右の列・フッター。仕様書 014 §10）。画像は `npm run preview:shots`（tools/preview/shoot.mjs）。
 * 見本の steps: click（css。nth 番目）/ clickText（ステータスバーのボタンの文字）/ cellClick（区域）/ notify（[通知名, args]）/ wait。
 * クエリ: ?sample=three-players-advanced|two-players-standard-6col、?theme=dark（BGA 風の枠だけが変わる）、?layout=narrow|wide（narrow = パネルをゲームの上へ。
 *   指定が無ければ幅 980 px 未満で narrow）、?touch=1、?spectator=1、?fame=1、?tools=1（開発用のボタン）、?demo=select|dpad|move、
 *   ?state=<samples/states/ の見本名>（状態の操作 UI。BGA の statusBar / actions / dialogs / gameArea は代用品。steps で操作を再生する。?nosteps=1 で再生しない）
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { GameView } from '../../src/modules/js/ui/GameView.js';
import { dispatchNotification } from '../../src/modules/js/notifications.js';
import { DeployState } from '../../src/modules/js/states/DeployState.js';
import { EffectWindowState } from '../../src/modules/js/states/EffectWindowState.js';
import { LaunchState } from '../../src/modules/js/states/LaunchState.js';
import { LinkState } from '../../src/modules/js/states/LinkState.js';
import { DraftState } from '../../src/modules/js/states/DraftState.js';
import { MulliganState } from '../../src/modules/js/states/MulliganState.js';
import { PlanningPlot, SetupLaunchPadPlace } from '../../src/modules/js/states/PlacementStates.js';
import { ProposalState } from '../../src/modules/js/states/ProposalState.js';

const params = new URLSearchParams(location.search);
const stateName = params.get('state');
/** 状態の見本（?state=）。見本の JSON の `sample` は、?sample= が無いときに使う盤面の見本 */
const statePlan = stateName === null ? null : await (await fetch(`./samples/states/${stateName}.json`)).json();
const sampleName = params.get('sample') ?? statePlan?.sample ?? 'three-players-advanced';
if (params.get('theme') === 'dark') {
    document.documentElement.dataset.theme = 'dark';
}
if (params.get('layout') === 'narrow' || (params.get('layout') !== 'wide' && window.innerWidth < 980)) {
    document.body.classList.add('pv-narrow');
}
// ?marks=1: 設定 100（席順の印）をオンにする
if (params.get('marks') === '1') {
    document.body.classList.add('pe-marks-on');
}
if (params.get('tools') === '1') {
    document.body.classList.add('pv-tools');
}
// BGA は #ebd-body に desktop_version / mobile_version と touch-device / notouch-device を付ける
const narrowPage = document.body.classList.contains('pv-narrow');
document.body.id = 'ebd-body';
document.body.classList.add(params.get('touch') === '1' || narrowPage ? 'touch-device' : 'notouch-device');
document.body.classList.add(narrowPage ? 'mobile_version' : 'desktop_version');

// 例外は画面の上に出す（headless のスクリーンショットで原因が分かるように。shoot.mjs が #pv-log の中身を標準出力に出す）
window.addEventListener('error', (e) => {
    const box = document.getElementById('pv-log');
    if (box !== null) {
        box.hidden = false;
        box.textContent += `ERROR ${e.message}\n${e.error?.stack ?? ''}\n`;
    }
});
window.addEventListener('unhandledrejection', (e) => {
    const box = document.getElementById('pv-log');
    if (box !== null) {
        box.hidden = false;
        box.textContent += `ERROR (promise) ${e.reason?.message ?? e.reason}\n${e.reason?.stack ?? ''}\n`;
    }
});

// BGA のグローバルの代用
window._ = (/** @type {string} */ s) => s;

const raw = await (await fetch(`./samples/${sampleName}.json`)).json();
const me = raw._me ?? null;
// ?fame=1: 名声の公開オプション（ゲームオプション 105 が Visible）の getAllDatas を再現する
if (params.get('fame') === '1') {
    const detail = (total) => ({ total, I: { points: 6, budget: 6 }, II: { points: 4, maya: 12 }, III: { points: 8, land: 5, sea: 3 }, IV: { points: 3, regions: ['A', 'B'] }, VI: { points: 5, sats: [] }, VII: { points: total - 26, a: 0, b: 0, c: 0 }, VIII: { points: 0, rewards: [] }, IX: { points: 0 } });
    raw.publicFame = Object.fromEntries(Object.keys(raw.players).map((id, i) => [id, { total: 40 - i * 7, detail: detail(40 - i * 7) }]));
}
const side = /** @type {HTMLElement} */ (document.getElementById('pv-side'));
const panelHosts = new Map();
const STAR = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3 6.9 7.5.7-5.7 5 1.7 7.4L12 18.1 5.5 22l1.7-7.4-5.7-5L9 8.9z"/></svg>';
/** BGA の player-board の名前の色（プレイヤーの色。黒に近い・暗い色は BGA が明るくするが、ここでは原本の色に近い値） */
const NAME_COLORS = params.get('theme') === 'dark'
    ? { '0000ff': '#6db3f2', ff0000: '#ff7a70', '008000': '#5fd68a', ffa500: '#f2c75c' }
    : { '0000ff': '#0b528d', ff0000: '#ad0003', '008000': '#007633', ffa500: '#b9881c' };

/** @type {import('../../src/modules/js/cards/CardView.js').Env} */
const env = {
    imgUrl: (file) => `../../src/img/${file}`,
    playerPanel: (id) => {
        if (!panelHosts.has(id)) {
            // BGA の .player-board（名前と得点の欄）の中に、ゲームの要素を足す
            const board = document.createElement('div');
            board.className = 'player-board';
            board.id = `player_board_${id}`;
            const color = String(raw.players[id]?.color ?? '000');
            board.innerHTML = `<div class="pv-head"><span class="player-name" style="color:${NAME_COLORS[color] ?? `#${color}`}">${raw.players[id]?.name ?? id}</span><span class="player_score">${STAR}<span>${raw.players[id]?.score ?? 0}</span></span></div>`;
            const host = document.createElement('div');
            host.className = 'pv-content';
            board.append(host);
            side.append(board);
            panelHosts.set(id, host);
        }
        return panelHosts.get(id);
    },
    animationsActive: () => params.get('anim') !== 'off',
    currentPlayerId: () => (params.get('spectator') === '1' ? null : me),
    showMessage: (message) => bga.dialogs.showMessage(message, 'info'),
    addButton: (label, callback, opts) => bga.statusBar.addActionButton(label, callback, opts),
};

/** BGA の代用品（ステータスバー・performAction・dialogs・gameArea）。状態の見本（?state=）以外でも Env.addButton が使う */
const titleBox = /** @type {HTMLElement} */ (document.getElementById('pv-title'));
const buttonBox = /** @type {HTMLElement} */ (document.getElementById('pv-buttons'));
const logBox = /** @type {HTMLElement} */ (document.getElementById('pv-log'));
const bga = {
    statusBar: {
        setTitle: (/** @type {string} */ text, /** @type {Record<string, string>} */ args = {}) => {
            // BGA は ${you} と、args のキー（${card_name} など）を埋める
            titleBox.textContent = Object.entries({ you: 'You', ...args }).reduce((t, [k, v]) => t.replaceAll(`\${${k}}`, String(v)), text);
        },
        /**
         * BGA の `statusBar.addActionButton(label, callback, params)` の代用。クラス（bgabutton + bgabutton_blue / bgabutton_red / bgabutton_gray）、
         * `destination`（置き場の要素）、`disabled`、`title`（title 属性）、`tooltip`（BGA のツールチップの代わりに data-tooltip）、`classes` を再現する。
         */
        addActionButton: (/** @type {string} */ label, /** @type {() => void} */ fn, /** @type {{ color?: string, disabled?: boolean, classes?: string | string[], destination?: HTMLElement, tooltip?: string, title?: string }} */ opts = {}) => {
            const b = document.createElement('button');
            b.type = 'button';
            // BGA の label は HTML（SVG のアイコンのボタンに使う）
            b.innerHTML = label;
            b.disabled = opts.disabled === true;
            const color = opts.color ?? 'primary';
            b.className = `bgabutton bgabutton_${color === 'secondary' ? 'gray' : color === 'alert' ? 'red' : 'blue'}`;
            const extra = Array.isArray(opts.classes) ? opts.classes : (opts.classes ?? '').split(/\s+/).filter((c) => c !== '');
            b.classList.add(...extra);
            if (extra.includes('pe-bar-undo')) {
                b.style.marginLeft = '16px';
            }
            if (opts.title !== undefined) {
                b.title = opts.title;
            }
            if (opts.tooltip !== undefined) {
                b.dataset.tooltip = opts.tooltip;
            }
            b.addEventListener('click', () => {
                if (!b.disabled) {
                    fn();
                }
            });
            (opts.destination ?? buttonBox).append(b);
            return b;
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
    dialogs: {
        /** 画面の上に一時的に出る帯（type: info は白、error は赤） */
        showMessage: (/** @type {string} */ text, /** @type {string} */ type = 'info') => {
            const box = document.createElement('div');
            box.className = `pv-msg${type === 'error' ? ' pv-msg-error' : ''}`;
            box.textContent = text;
            document.body.append(box);
            setTimeout(() => box.remove(), 2500);
        },
    },
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
});
const demo = params.get('demo');
if (demo !== null && demo in demos) {
    requestAnimationFrame(() => setTimeout(() => /** @type {Record<string, () => void>} */ (demos)[demo](), 300));
}

// ---- 状態の操作 UI（?state=<見本名>） ----
if (statePlan !== null) {
    const plan = statePlan;
    const game = { view };
    const classes = {
        SetupLaunchPadPlace: () => new SetupLaunchPadPlace(game, bga),
        SetupMulligan: () => new MulliganState(game, bga),
        SetupDraftPick: () => new DraftState(game, bga),
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
    if (plan.myHand !== undefined && view.m.me !== null) {
        // 手札の見本の差し替え（ドラフトの途中など）
        view.m.myHand = plan.myHand;
        view.m.players[view.m.me].handCount = plan.myHand.length;
        view.areas.get(view.m.me)?.hand.setCards(plan.myHand);
        view.refreshPlayer(view.m.me);
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
    // ?nosteps=1: 見本の steps を再生しない（撮影の道具が自分の手順で操作するとき）
    for (const step of params.get('nosteps') === '1' ? [] : plan.steps ?? []) {
        await sleep(120);
        if (step.click !== undefined) {
            const node = document.querySelectorAll(step.click)[(step.nth ?? 1) - 1];
            // SVG の要素（盤面のハイライトなど）には click() が無い
            if (typeof (/** @type {HTMLElement} */ (node))?.click === 'function') {
                /** @type {HTMLElement} */ (node).click();
            } else {
                node?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
            }
        } else if (step.notify !== undefined) {
            // 通知の再生（効果の窓の N34 effectQuestions など）
            await dispatchNotification(view, step.notify[0], step.notify[1]);
        } else if (step.clickText !== undefined) {
            [...buttonBox.querySelectorAll('button')].find((b) => b.textContent === step.clickText)?.click();
        } else if (step.cellClick !== undefined) {
            view.stateFrame.handleCellPick(step.cellClick);
        } else if (step.wait !== undefined) {
            await sleep(step.wait);
        }
    }
}
document.body.dataset.ready = 'true';
