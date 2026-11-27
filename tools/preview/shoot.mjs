/**
 * プレビューのスクリーンショット（仕様書 014 §10）。`npm run preview:shots`。
 * Chrome を DevTools Protocol で動かす（依存の追加なし。Node 組み込みの WebSocket・node:http・node:child_process だけ）。
 * tools/preview/index.html を、リポジトリ直下を配る小さな静的サーバーで開き、幅 × 状態の画像を `build/preview-shots/`（Git 管理外）に書く。
 *
 * 使い方:
 *   npm run preview:shots                                  既定: 幅 360 / 390 / 768 / 1024 / 1366 / 1440 / 1920 / 2560 × 主な状態（none / launchpad / planning / deploy-main / link-main / launch-main / draft / proposal / effects-use / effects-order / final-score / final-score-4p ほか）
 *                                                          + 観戦者・2 人 6 列・名声オン・ダーク・設定 100 オン（1440 と 390）+ ツールチップ
 *   npm run preview:shots -- --widths 1440,390 --states planning,deploy-main
 *   npm run preview:shots -- --query "theme=dark" --suffix dark --states planning --widths 1440
 *   npm run preview:shots -- --query "sample=two-players-standard-6col" --suffix 2p6col --states none --widths 1440
 *   npm run preview:shots -- --spec path/to/spec.json      自分で書いた手順（steps）の再生
 *   npm run preview:shots -- --tips                        ツールチップの撮影だけ（既定の撮影にも含む）。撮るたびに、ツールチップが見える領域に入り、対象と重ならないことを確かめて TIP 行で出す
 *
 * 状態 `none` は状態の操作 UI なし（?state= を付けない）。360・390 は mobile + dpr 2 + ?layout=narrow。
 * --viewport で、ページ全体ではなく画面 1 枚分だけを撮る（スクロール・sticky の確認用）。
 * spec = { shots: [{ name, width, height?, mobile?, dpr?, query?, state?, fullPage?, steps?: [...] }] }
 * steps（PR 3 のツールチップの確認に使う）:
 *   { eval: "js", log?: true } | { hover: "css", dx?, dy? } | { click: "css" } | { tap: "css" } | { wait: ms } | { scrollTo: "css", block? } | { scrollY: px } | { shot: "name" }
 *   | { checkTip: "対象の css" }（ツールチップの矩形が見える領域の中にあり、対象と重ならないことを確かめて標準出力に出す。014 §10 の自動検査 7 番）
 * 終了時に、ページの横のはみ出し（scrollWidth > innerWidth）と、ページ内の例外（#pv-log の ERROR 行）を標準出力に出す。Chrome が無いときは案内を出して終了コード 0。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { mkdirSync, readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { ROOT, checkTip, findChrome, launchChrome, parseArgs, sleep, startServer } from './chrome-driver.mjs';

const OUT_DIR = join(ROOT, 'build', 'preview-shots');

/** 既定の幅 → 画面の高さ（ページ全体を撮るので、高さは sticky などの見え方にだけ効く） */
const WIDTHS = { 360: 780, 390: 844, 768: 1024, 1024: 768, 1366: 768, 1440: 900, 1920: 1080, 2560: 1440 };
const STATES = ['none', 'launchpad', 'planning', 'planning-confirmed', 'mulligan', 'deploy-main', 'link-main', 'link-main-sell', 'launch-main', 'launch-board-turn-direction', 'draft', 'proposal', 'sell-pick', 'effects-use', 'effects-order', 'final-score', 'final-score-4p'];
/** 既定の撮影に足す組み合わせ（014 §10。観戦者・2 人 6 列・名声オン） */
const EXTRA_SHOTS = [
    { name: 'w1440-none-spectator', width: 1440, state: 'none', query: 'spectator=1' },
    { name: 'w1440-none-2p6col', width: 1440, state: 'none', query: 'sample=two-players-standard-6col' },
    { name: 'w1440-none-fame', width: 1440, state: 'none', query: 'fame=1' },
    { name: 'w390-none-fame', width: 390, state: 'none', query: 'fame=1' },
    // ダーク（BGA の枠だけが変わる）と設定 100（席順の印）オン
    { name: 'w1440-planning-dark', width: 1440, state: 'planning', query: 'theme=dark' },
    { name: 'w390-planning-dark', width: 390, state: 'planning', query: 'theme=dark' },
    { name: 'w1440-planning-marks', width: 1440, state: 'planning', query: 'marks=1' },
    { name: 'w390-planning-marks', width: 390, state: 'planning', query: 'marks=1' },
    { name: 'w1440-none-marks', width: 1440, state: 'none', query: 'marks=1' },
];
/**
 * ツールチップの撮影（014 §7）。ホバー（マウス）は 1440 × 900 と 1366 × 768、タップ・情報の印（タッチ）は 390 × 844、横向きのスマホは 844 × 390。
 * 各撮影のあとに checkTip で画面内・対象と重ならないことを確かめる。
 */
const TIP_TARGETS = [
    ['hand', '#pe-me .pe-hand-card'],
    ['paper', '#pe-me .pe-paper-card'],
    ['sat', '#pe-me .pe-sat-card'],
    ['reward', '.pe-reward-card'],
    ['counter', '.player-board .pe-counter-maya'],
    ['piece', '.pe-sat-piece'],
];
/** 1440 だけで撮る、盤面・ラウンドトラック・場の文字だけのツールチップ */
const TIP_TARGETS_EXTRA = [
    ['cube', '.pe-cube'],
    ['base', '.pe-base-piece'],
    ['round', '.pe-round-step:nth-child(3)'],
    ['deck', '.pe-deck'],
    ['badge', '.pe-base-badge'],
];
/** @param {number} width @param {number} height @param {string} query @param {string} suffix @param {string[][]} [targets] */
function tipHoverShots(width, height, query, suffix, targets = TIP_TARGETS) {
    return targets.map(([name, sel]) => ({
        name: `tip-w${width}-${name}${suffix}`,
        width,
        height,
        state: 'none',
        query,
        fullPage: false,
        steps: [{ hover: sel }, { wait: 1100 }, { checkTip: sel }],
    }));
}
const TIP_SHOTS = [
    ...tipHoverShots(1440, 900, '', ''),
    ...tipHoverShots(1440, 900, '', '', TIP_TARGETS_EXTRA),
    ...tipHoverShots(1366, 768, '', ''),
    ...tipHoverShots(1366, 768, 'theme=dark', '-dark'),
    { name: 'tip-w390-tap-hand', width: 390, height: 844, state: 'none', fullPage: false, steps: [{ tap: '#pe-me .pe-hand-card' }, { wait: 300 }, { checkTip: '#pe-me .pe-hand-card' }] },
    { name: 'tip-w390-tap-hand-dark', width: 390, height: 844, state: 'none', query: 'theme=dark', fullPage: false, steps: [{ tap: '#pe-me .pe-hand-card' }, { wait: 300 }, { checkTip: '#pe-me .pe-hand-card' }] },
    // 選択中のカード（配備の選択。最初の 1 枚が選択済み）の情報の印。押すとツールチップが開く
    { name: 'tip-w390-info-mark', width: 390, height: 844, state: 'deploy-main', fullPage: false, steps: [{ wait: 400 }, { eval: "document.querySelectorAll('.pe-tip-info').length", log: true }] },
    { name: 'tip-w390-info-open', width: 390, height: 844, state: 'deploy-main', fullPage: false, steps: [{ wait: 400 }, { tap: '.pe-tip-info' }, { wait: 300 }, { checkTip: '.pe-tip-info' }] },
    { name: 'tip-w844x390-sat', width: 844, height: 390, mobile: true, state: 'none', fullPage: false, steps: [{ tap: '#pe-me .pe-sat-card' }, { wait: 300 }, { checkTip: '#pe-me .pe-sat-card' }] },
    { name: 'tip-w844x390-hand', width: 844, height: 390, mobile: true, state: 'none', fullPage: false, steps: [{ tap: '#pe-me .pe-hand-card' }, { wait: 300 }, { checkTip: '#pe-me .pe-hand-card' }] },
];
/**
 * @typedef {object} Shot
 * @property {string} name
 * @property {number} width
 * @property {number} [height]
 * @property {boolean} [mobile]
 * @property {number} [dpr]
 * @property {string} [query] 追加のクエリ（例: theme=dark&sample=...）
 * @property {string} [state] samples/states/ の見本名（none = 状態なし）
 * @property {boolean} [fullPage]
 * @property {Record<string, any>[]} [steps]
 */

/** @param {import('./chrome-driver.mjs').Page} page @param {Shot} shot @param {string} base @param {boolean} fullPageDefault */
async function runShot(page, shot, base, fullPageDefault) {
    const mobile = shot.mobile ?? shot.width <= 480;
    const height = shot.height ?? /** @type {Record<number, number>} */ (WIDTHS)[shot.width] ?? 900;
    await page.send('Emulation.setDeviceMetricsOverride', { width: shot.width, height, deviceScaleFactor: shot.dpr ?? (mobile ? 2 : 1), mobile });
    if (mobile) {
        await page.send('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 5 });
    }
    const q = new URLSearchParams(shot.query ?? '');
    if (shot.state !== undefined && shot.state !== 'none') {
        q.set('state', shot.state);
    }
    if (mobile && !q.has('layout')) {
        q.set('layout', 'narrow');
    }
    await page.send('Page.navigate', { url: `${base}/tools/preview/index.html?${q.toString()}` });
    // main.js が描き終えたら body[data-ready] が付く
    let ready = false;
    for (let i = 0; i < 100 && !ready; i++) {
        await sleep(150);
        ready = await page.eval(`document.body?.dataset.ready === 'true'`).catch(() => false);
    }
    await sleep(shot.state === undefined || shot.state === 'none' ? 800 : 900);
    const fullPage = shot.fullPage ?? fullPageDefault;
    for (const step of shot.steps ?? []) {
        if (step.eval !== undefined) {
            const v = await page.eval(step.eval);
            if (step.log === true) {
                console.log('eval:', JSON.stringify(v));
            }
        } else if (step.hover !== undefined) {
            const r = await page.rectOf(step.hover);
            if (r === null) {
                console.log('hover: 見つからない', step.hover);
                continue;
            }
            await page.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: r.x + (step.dx ?? 0), y: r.y + (step.dy ?? 0), pointerType: 'mouse' });
        } else if (step.click !== undefined) {
            const r = await page.rectOf(step.click);
            if (r === null) {
                console.log('click: 見つからない', step.click);
                continue;
            }
            for (const type of ['mouseMoved', 'mousePressed', 'mouseReleased']) {
                await page.send('Input.dispatchMouseEvent', { type, x: r.x, y: r.y, button: 'left', clickCount: 1, pointerType: 'mouse' });
            }
        } else if (step.tap !== undefined) {
            const r = await page.rectOf(step.tap);
            if (r === null) {
                console.log('tap: 見つからない', step.tap);
                continue;
            }
            await page.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: r.x, y: r.y }] });
            await sleep(60);
            await page.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        } else if (step.checkTip !== undefined) {
            console.log(`TIP ${shot.name}:`, await checkTip(page, step.checkTip));
        } else if (step.wait !== undefined) {
            await sleep(step.wait);
        } else if (step.scrollTo !== undefined) {
            await page.eval(`document.querySelector(${JSON.stringify(step.scrollTo)})?.scrollIntoView({ block: ${JSON.stringify(step.block ?? 'start')} })`);
        } else if (step.scrollY !== undefined) {
            await page.eval(`window.scrollTo(0, ${Number(step.scrollY)})`);
        } else if (step.shot !== undefined) {
            await page.capture(join(OUT_DIR, `${step.shot}.png`), step.fullPage ?? fullPage);
        }
    }
    // 横のはみ出しと、ページ内の例外
    const overflow = await page.eval('({ sw: document.documentElement.scrollWidth, iw: window.innerWidth })').catch(() => null);
    if (overflow !== null && overflow.sw > overflow.iw) {
        console.log(`OVERFLOW ${shot.name}: scrollWidth ${overflow.sw} > innerWidth ${overflow.iw}`);
    }
    const log = await page.eval(`(document.getElementById('pv-log')?.textContent ?? '')`).catch(() => '');
    const errors = String(log).split('\n').filter((line) => line.startsWith('ERROR'));
    // 送信された操作（プレビューの performAction の記録）。前後の比較用（PE_ACTIONS=1 で出す）
    if (process.env.PE_ACTIONS === '1') {
        const acts = String(log).split('\n').filter((line) => line.startsWith('act'));
        console.log(`ACT ${shot.name}: ${acts.join(' | ')}`);
    }
    if (errors.length > 0) {
        console.log(`PAGE ERRORS ${shot.name}:\n  ${errors.join('\n  ')}`);
    }
    await page.capture(join(OUT_DIR, `${shot.name}.png`), fullPage);
    return { overflow: overflow !== null && overflow.sw > overflow.iw, errors: errors.length };
}

async function main() {
    const args = parseArgs(process.argv.slice(2));
    const chromePath = findChrome();
    if (chromePath === null) {
        console.log('Chrome が見つからないので、スクリーンショットを撮りません（環境変数 CHROME_PATH で実行ファイルを指定できます）。');
        return;
    }
    /** @type {Shot[]} */
    let shots;
    if (args.tips === true) {
        shots = TIP_SHOTS;
    } else if (typeof args.spec === 'string') {
        shots = JSON.parse(readFileSync(resolve(args.spec), 'utf8')).shots;
    } else {
        const widths = typeof args.widths === 'string' ? args.widths.split(',').map(Number) : Object.keys(WIDTHS).map(Number);
        const states = typeof args.states === 'string' ? args.states.split(',') : STATES;
        const suffix = typeof args.suffix === 'string' ? `-${args.suffix}` : '';
        const query = typeof args.query === 'string' ? args.query : '';
        shots = widths.flatMap((width) => states.map((state) => ({ name: `w${width}-${state}${suffix}`, width, state, query })));
        if (typeof args.widths !== 'string' && typeof args.states !== 'string') {
            shots.push(...EXTRA_SHOTS.map((shot) => ({ ...shot, query: [shot.query, query].filter((q) => q !== '').join('&') })));
            shots.push(...TIP_SHOTS);
        }
    }
    const fullPage = args.viewport !== true;
    mkdirSync(OUT_DIR, { recursive: true });

    const { server, port: webPort } = /** @type {{ server: import('node:http').Server, port: number }} */ (await startServer());
    const browser = launchChrome(chromePath);
    let overflows = 0;
    let errors = 0;
    try {
        for (const shot of shots) {
            const { page, close } = await browser.open();
            try {
                const r = await runShot(page, shot, `http://127.0.0.1:${webPort}`, fullPage);
                overflows += r.overflow ? 1 : 0;
                errors += r.errors;
            } catch (error) {
                console.log(`FAILED ${shot.name}: ${error instanceof Error ? error.message : error}`);
                errors++;
            }
            await close();
        }
    } finally {
        await browser.close();
        server.closeAllConnections();
        server.close();
    }
    console.log(`${shots.length} 枚。横のはみ出し ${overflows} 件、ページ内の例外 ${errors} 件。出力: build/preview-shots/`);
}

await main();
