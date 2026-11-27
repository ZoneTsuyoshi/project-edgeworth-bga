/**
 * UI の自動検査（仕様書 014 §10。審査の指摘 7 点を機械的に確かめる）。`npm run check:ui`。
 * Chrome を DevTools Protocol で動かし（shoot.mjs と chrome-driver.mjs を共有）、幅 360 / 390 / 768 / 1024 / 1366 / 1440 / 1920 / 2560 ×
 * samples/states/ の全部の状態の見本（steps を再生した後の画面）+ 状態なし・観戦者・2 人 6 列・名声オン・ダーク・設定 100 オンを調べる。
 * 検査:
 *   1 ページの横のはみ出し（scrollWidth <= innerWidth）
 *   2 #pe-root の中に position: fixed / sticky が無い
 *   3 押せる要素が 32 × 32 px 以上（#pe-root とパネルの中の自分たちの要素。盤面の区域は選択中のものだけ。narrow の概観の段は除く）
 *   4 #pe-root・パネルの中の自分たちの要素・ツールチップに title 属性が無い（BGA 標準のボタン .bgabutton は例外）
 *   5 文字が 12 px 以上（同じ範囲で、文字を直接持つ見えている要素）
 *   6 実際にスクロールする入れ子の要素は narrow の盤面（#pe-board-viewport）だけ
 *   7 ツールチップが画面内にあり、対象を覆わず、中身が切れていない（1366 × 768・1440 × 900 のホバー、390 × 844 のタップ、844 × 390 のタップ）
 *   8 卓の上の文字のコントラスト比が 4.5:1 以上（背景の基準色 #0a1122 との比。カードの画像・面の上の文字は対象外）
 *   0 ページ内の JavaScript の例外
 * 使い方:
 *   npm run check:ui
 *   npm run check:ui -- --widths 390,1440 --states planning,deploy-main   絞り込み（既定は全部）
 *   npm run check:ui -- --no-tips                                          ツールチップの検査（7 番）を省く
 *   npm run check:ui -- --only-tips                                        ツールチップの検査（7 番）だけ
 * 違反は 検査・見本・幅・セレクタ・値 の一覧で出し、違反があれば終了コード 1。Chrome が無いときは案内を出して終了コード 0。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { readdirSync } from 'node:fs';
import { join } from 'node:path';
import { ROOT, checkTip, findChrome, launchChrome, parseArgs, sleep, startServer } from './chrome-driver.mjs';

const WIDTHS = { 360: 780, 390: 844, 768: 1024, 1024: 768, 1366: 768, 1440: 900, 1920: 1080, 2560: 1440 };
/** 状態なし・観戦者・2 人 6 列・名声オン・ダーク・設定 100 オン（どの幅でも調べる） */
const EXTRAS = [
    ['none', ''],
    ['none-spectator', 'spectator=1'],
    ['none-2p6col', 'sample=two-players-standard-6col'],
    ['none-fame', 'fame=1'],
    ['planning-dark', 'theme=dark', 'planning'],
    ['planning-marks', 'marks=1', 'planning'],
    ['none-marks', 'marks=1'],
];
/** 例外（理由つき。最小に）。[検査の番号, セレクタの正規表現, 理由] */
const EXCEPTIONS = /** @type {[number, RegExp, string][]} */ ([]);

/** ページの中で動かす検査。違反の配列 { check, selector, value } を返す */
const COLLECT = String.raw`(() => {
    const out = [];
    const root = document.getElementById('pe-root');
    if (!root) return [{ check: 0, selector: '#pe-root', value: 'ない' }];
    const BG = [10, 17, 34];
    const sel = (e) => {
        const parts = [];
        let n = e;
        for (let i = 0; n && n.nodeType === 1 && i < 4; i++) {
            let s = n.tagName.toLowerCase();
            if (n.id) { parts.unshift('#' + n.id); break; }
            const cls = [...n.classList].filter((c) => c.startsWith('pe-')).slice(0, 2).join('.');
            if (cls) s += '.' + cls;
            parts.unshift(s);
            n = n.parentElement;
        }
        return parts.join(' > ');
    };
    const visible = (e) => {
        if (e.getClientRects().length === 0) return false;
        const cs = getComputedStyle(e);
        return cs.visibility !== 'hidden' && cs.display !== 'none';
    };
    const own = (e) => e.closest('#pe-root, .pe-panel, #pe-tip') !== null;
    const scope = [...document.querySelectorAll('#pe-root *, .pe-panel, .pe-panel *, #pe-tip, #pe-tip *')];
    const layout = root.dataset.layout;
    const overview = layout === 'narrow' && root.dataset.narrowStep === 'overview';
    const add = (check, e, value) => out.push({ check, selector: sel(e), value });
    // 1
    if (document.documentElement.scrollWidth > window.innerWidth) add(1, document.documentElement, 'scrollWidth ' + document.documentElement.scrollWidth + ' > innerWidth ' + window.innerWidth);
    // 2
    for (const e of root.querySelectorAll('*')) {
        const p = getComputedStyle(e).position;
        if (p === 'fixed' || p === 'sticky') add(2, e, p);
    }
    // 3
    const CLICK = 'button, a[href], [role="button"], .pe-can, .pe-can-sell, .pe-picked, [data-pick]';
    const seen = new Set();
    for (const e of scope) {
        if (!visible(e) || e.closest('#pe-tip')) continue;
        const cs = getComputedStyle(e);
        const parentPointer = e.parentElement && getComputedStyle(e.parentElement).cursor === 'pointer';
        const clickable = e.matches(CLICK) || (cs.cursor === 'pointer' && !parentPointer);
        if (!clickable || seen.has(e)) continue;
        seen.add(e);
        if (e.closest('.pe-cell, svg.pe-hl-svg, .pe-hl')) {
            // 盤面の区域は選択中のものだけ。概観の段は対象外
            if (overview || !e.closest('.pe-hl-selected')) continue;
        }
        if (e.closest('button') && e.closest('button') !== e) continue;
        const r = e.getBoundingClientRect();
        // 小さい部品は ::after で押せる範囲を広げてよい（盤面のコマ）
        const ps = getComputedStyle(e, '::after');
        const pw = ps.content !== 'none' && ps.position === 'absolute' ? parseFloat(ps.width) || 0 : 0;
        const ph = ps.content !== 'none' && ps.position === 'absolute' ? parseFloat(ps.height) || 0 : 0;
        if (Math.max(r.width, pw) < 31.5 || Math.max(r.height, ph) < 31.5) add(3, e, Math.round(r.width * 10) / 10 + ' x ' + Math.round(r.height * 10) / 10 + ' px');
    }
    // 4
    for (const e of scope) {
        if (e.hasAttribute('title') && !e.classList.contains('bgabutton') && !e.closest('.bgabutton')) add(4, e, 'title="' + e.getAttribute('title') + '"');
    }
    // 5・8
    const lin = (c) => { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); };
    const lum = (rgb) => 0.2126 * lin(rgb[0]) + 0.7152 * lin(rgb[1]) + 0.0722 * lin(rgb[2]);
    const parse = (s) => { const m = s.match(/rgba?\(([^)]+)\)/); if (!m) return null; const p = m[1].split(/[ ,/]+/).filter(Boolean).map(Number); return [p[0], p[1], p[2], p.length > 3 ? p[3] : 1]; };
    for (const e of scope) {
        if (!visible(e) || e.closest('.bgabutton, svg defs')) continue;
        const hasText = [...e.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim() !== '');
        if (!hasText) continue;
        const cs = getComputedStyle(e);
        const fs = parseFloat(cs.fontSize);
        if (fs < 11.95) add(5, e, fs + ' px "' + e.textContent.trim().slice(0, 16) + '"');
        // 8: 卓の上の文字だけ（自分か祖先に背景がある文字は対象外）
        if (!e.closest('#pe-root') || e.closest('.pe-panel')) continue;
        let onTable = true;
        let opacity = 1;
        for (let n = e; n && n !== root.parentElement; n = n.parentElement) {
            const s = getComputedStyle(n);
            const bg = parse(s.backgroundColor);
            if ((bg && bg[3] > 0.05) || s.backgroundImage !== 'none' || n.tagName === 'BUTTON' || n.classList.contains('pe-card')) { onTable = false; break; }
            opacity *= parseFloat(s.opacity);
        }
        if (!onTable || e.closest('svg')) continue;
        const c = parse(cs.color);
        if (!c) continue;
        const a = c[3] * opacity;
        const mix = [0, 1, 2].map((i) => c[i] * a + BG[i] * (1 - a));
        const l1 = lum(mix), l2 = lum(BG);
        const ratio = (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
        if (ratio < 4.5) add(8, e, ratio.toFixed(2) + ':1 "' + e.textContent.trim().slice(0, 16) + '"');
    }
    // 6
    for (const e of root.querySelectorAll('*')) {
        const cs = getComputedStyle(e);
        const scrolls = (/(auto|scroll)/.test(cs.overflowX) && e.scrollWidth > e.clientWidth + 1) || (/(auto|scroll)/.test(cs.overflowY) && e.scrollHeight > e.clientHeight + 1);
        if (scrolls && !(e.id === 'pe-board-viewport' && layout === 'narrow')) add(6, e, 'scroll ' + e.scrollWidth + 'x' + e.scrollHeight + ' client ' + e.clientWidth + 'x' + e.clientHeight);
    }
    return out;
})()`;

/** ツールチップが出ているときの 4・5 番（ツールチップの中の title と文字の大きさ） */
const COLLECT_TIP = String.raw`(() => {
    const tip = document.getElementById('pe-tip');
    if (!tip || tip.hidden) return [];
    const out = [];
    for (const e of [tip, ...tip.querySelectorAll('*')]) {
        if (e.hasAttribute('title')) out.push({ check: 4, selector: '#pe-tip', value: 'title' });
        const hasText = [...e.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim() !== '');
        if (hasText && parseFloat(getComputedStyle(e).fontSize) < 11.95) out.push({ check: 5, selector: '#pe-tip ' + e.tagName.toLowerCase() + '.' + [...e.classList].join('.'), value: getComputedStyle(e).fontSize + ' "' + e.textContent.trim().slice(0, 16) + '"' });
    }
    return out;
})()`;

/** ツールチップの対象（014 §10 の 7 番） */
const TIP_TARGETS = [
    ['hand', '#pe-me .pe-hand-card'],
    ['paper', '#pe-me .pe-paper-card'],
    ['sat', '#pe-me .pe-sat-card'],
    ['reward', '.pe-reward-card'],
    ['counter', '.player-board .pe-counter-maya'],
    ['piece', '.pe-sat-piece'],
    ['round', '.pe-round-step:nth-child(3)'],
];
const TIP_VIEWS = [
    { width: 1366, height: 768, touch: false },
    { width: 1440, height: 900, touch: false },
    { width: 390, height: 844, touch: true },
    { width: 844, height: 390, touch: true },
];

/** @param {import('./chrome-driver.mjs').Page} page */
async function openPage(page, base, { width, height, touch }, query, state) {
    await page.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: touch ? 2 : 1, mobile: touch });
    if (touch) {
        await page.send('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 5 });
    }
    const q = new URLSearchParams(query);
    if (state !== 'none') {
        q.set('state', state);
    }
    if (touch && !q.has('layout')) {
        q.set('layout', 'narrow');
    }
    await page.send('Page.navigate', { url: `${base}/tools/preview/index.html?${q.toString()}` });
    let ready = false;
    for (let i = 0; i < 400 && !ready; i++) {
        await sleep(100);
        ready = await page.eval(`document.body?.dataset.ready === 'true'`).catch(() => false);
    }
    // レイアウトの再計算（ResizeObserver）と画像の読み込みを待つ
    await sleep(450);
    if (!ready) {
        throw new Error('描画が終わらない');
    }
}

async function main() {
    const args = parseArgs(process.argv.slice(2));
    const chromePath = findChrome();
    if (chromePath === null) {
        console.log('Chrome が見つからないので、UI の検査を飛ばします（環境変数 CHROME_PATH で実行ファイルを指定できます）。');
        return 0;
    }
    const widths = typeof args.widths === 'string' ? args.widths.split(',').map(Number) : Object.keys(WIDTHS).map(Number);
    const allStates = readdirSync(join(ROOT, 'tools', 'preview', 'samples', 'states')).filter((f) => f.endsWith('.json')).map((f) => f.replace(/\.json$/, ''));
    const states = typeof args.states === 'string' ? args.states.split(',') : allStates;
    const jobs = [];
    for (const width of widths) {
        const view = { width, height: /** @type {Record<number, number>} */ (WIDTHS)[width] ?? 900, touch: width <= 480 };
        for (const state of states) {
            jobs.push({ name: state, view, query: '', state });
        }
        if (typeof args.states !== 'string') {
            for (const [name, query, state] of EXTRAS) {
                jobs.push({ name, view, query, state: state ?? 'none' });
            }
        }
    }
    /** @type {{ name: string, width: number, check: number, selector: string, value: string }[]} */
    const found = [];
    let pages = 0;
    const { server, port } = /** @type {{ server: import('node:http').Server, port: number }} */ (await startServer());
    const base = `http://127.0.0.1:${port}`;
    const browser = launchChrome(chromePath);
    const record = (/** @type {string} */ name, /** @type {number} */ width, /** @type {{ check: number, selector: string, value: string }[]} */ list) => {
        for (const v of list) {
            const exception = EXCEPTIONS.find(([check, re]) => check === v.check && re.test(v.selector));
            if (exception === undefined) {
                found.push({ name, width, ...v });
            }
        }
    };
    const runJob = async (/** @type {typeof jobs[number]} */ job) => {
        const { page, close } = await browser.open();
        try {
            await openPage(page, base, job.view, job.query, job.state);
            record(job.name, job.view.width, await page.eval(COLLECT));
            const log = String(await page.eval(`(document.getElementById('pv-log')?.textContent ?? '')`));
            for (const line of log.split('\n').filter((l) => l.startsWith('ERROR'))) {
                record(job.name, job.view.width, [{ check: 0, selector: 'page', value: line.slice(0, 120) }]);
            }
        } catch (error) {
            record(job.name, job.view.width, [{ check: 0, selector: 'page', value: String(error instanceof Error ? error.message : error).slice(0, 120) }]);
        }
        pages++;
        await close();
    };
    const runTips = async (/** @type {{ width: number, height: number, touch: boolean }} */ view, /** @type {string} */ name, /** @type {string} */ sel, /** @type {string} */ state = 'none') => {
        const { page, close } = await browser.open();
        const label = `tip-${name}`;
        try {
            await openPage(page, base, view, '', state);
            const r = await page.rectOf(sel);
            if (r === null) {
                record(label, view.width, [{ check: 7, selector: sel, value: '対象が見つからない' }]);
            } else if (view.touch) {
                await page.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: r.x, y: r.y }] });
                // 押せる対象（衛星コマなど）は長押しで開く。押せない対象は離したときに開く。どちらも 800 ms 押し続けて確かめる
                await sleep(800);
                await page.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
                await sleep(350);
            } else {
                await page.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: r.x, y: r.y, pointerType: 'mouse' });
                await sleep(1100);
            }
            if (r !== null) {
                const result = await checkTip(page, sel);
                if (!result.startsWith('OK')) {
                    record(label, view.width, [{ check: 7, selector: sel, value: `${view.width}x${view.height} ${result}` }]);
                }
                record(label, view.width, await page.eval(COLLECT_TIP));
            }
        } catch (error) {
            record(label, view.width, [{ check: 0, selector: sel, value: String(error instanceof Error ? error.message : error).slice(0, 120) }]);
        }
        pages++;
        await close();
    };
    /** @type {(() => Promise<void>)[]} */
    const queue = (args['only-tips'] === true ? [] : jobs).map((job) => () => runJob(job));
    /** ツールチップは、ページを同時に開くとホバー・タップが落ちることがあるので、1 つずつ動かす */
    /** @type {(() => Promise<void>)[]} */
    const tipQueue = [];
    if (args['no-tips'] !== true && (typeof args.states !== 'string' || args['only-tips'] === true)) {
        for (const view of TIP_VIEWS) {
            for (const [name, sel] of TIP_TARGETS) {
                tipQueue.push(() => runTips(view, `${view.width}x${view.height}-${name}`, sel));
            }
        }
    }
    const WORKERS = 4;
    try {
        await Promise.all(Array.from({ length: WORKERS }, async () => {
            for (let job = queue.shift(); job !== undefined; job = queue.shift()) {
                await job();
            }
        }));
        for (const job of tipQueue) {
            await job();
        }
    } finally {
        await browser.close();
        server.closeAllConnections();
        server.close();
    }

    const NAMES = ['ページ内の例外', '横のはみ出し', 'fixed / sticky', '押せる要素の大きさ', 'title 属性', '文字の大きさ', '入れ子のスクロール', 'ツールチップ', 'コントラスト比'];
    const unique = new Map();
    for (const v of found) {
        const key = `${v.check}\t${v.selector}`;
        const entry = unique.get(key) ?? { v, cases: [], values: new Set() };
        entry.cases.push(`${v.name}@${v.width}`);
        entry.values.add(v.value);
        unique.set(key, entry);
    }
    for (const { v, cases, values } of [...unique.values()].sort((a, b) => a.v.check - b.v.check || a.v.selector.localeCompare(b.v.selector))) {
        const uniqueCases = [...new Set(cases)];
        const shown = uniqueCases.slice(0, 4).join(', ') + (uniqueCases.length > 4 ? ` ほか ${uniqueCases.length - 4} 件` : '');
        const shownValues = [...values].slice(0, 3).join(' / ') + (values.size > 3 ? ` ほか ${values.size - 3} 種` : '');
        console.log(`[${v.check} ${NAMES[v.check]}] ${v.selector} | ${shownValues} | ${shown}`);
    }
    console.log('---');
    for (let c = 0; c <= 8; c++) {
        const n = found.filter((v) => v.check === c).length;
        console.log(`${c} ${NAMES[c]}: ${n} 件（要素の種類 ${[...unique.keys()].filter((k) => k.startsWith(`${c}\t`)).length}）`);
    }
    for (const [, re, reason] of EXCEPTIONS) {
        console.log(`例外: ${re} ${reason}`);
    }
    console.log(`${pages} ページを検査。違反 ${found.length} 件。`);
    return found.length === 0 ? 0 : 1;
}

process.exitCode = await main();
