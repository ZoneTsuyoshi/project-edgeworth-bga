/**
 * プレビューのスクリーンショット（仕様書 014 §10）。`npm run preview:shots`。
 * Chrome を DevTools Protocol で動かす（依存の追加なし。Node 組み込みの WebSocket・node:http・node:child_process だけ）。
 * tools/preview/index.html を、リポジトリ直下を配る小さな静的サーバーで開き、幅 × 状態の画像を `build/preview-shots/`（Git 管理外）に書く。
 *
 * 使い方:
 *   npm run preview:shots                                  既定: 幅 360 / 390 / 768 / 1024 / 1366 / 1440 / 1920 / 2560 × 状態 none / launchpad / planning / deploy-main / link-main / draft / effects-use / final-score
 *                                                          + 観戦者・2 人 6 列・名声オン（1440）と、名声オン（390）
 *   npm run preview:shots -- --widths 1440,390 --states planning,deploy-main
 *   npm run preview:shots -- --query "theme=dark" --suffix dark --states planning --widths 1440
 *   npm run preview:shots -- --query "sample=two-players-standard-6col" --suffix 2p6col --states none --widths 1440
 *   npm run preview:shots -- --spec path/to/spec.json      自分で書いた手順（steps）の再生
 *
 * 状態 `none` は状態の操作 UI なし（?state= を付けない）。360・390 は mobile + dpr 2 + ?layout=narrow。
 * --viewport で、ページ全体ではなく画面 1 枚分だけを撮る（スクロール・sticky の確認用）。
 * spec = { shots: [{ name, width, height?, mobile?, dpr?, query?, state?, fullPage?, steps?: [...] }] }
 * steps（PR 3 のツールチップの確認に使う）:
 *   { eval: "js", log?: true } | { hover: "css", dx?, dy? } | { click: "css" } | { tap: "css" } | { wait: ms } | { scrollTo: "css", block? } | { scrollY: px } | { shot: "name" }
 * 終了時に、ページの横のはみ出し（scrollWidth > innerWidth）と、ページ内の例外（#pv-log の ERROR 行）を標準出力に出す。Chrome が無いときは案内を出して終了コード 0。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { tmpdir } from 'node:os';
import { dirname, extname, join, normalize, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const OUT_DIR = join(ROOT, 'build', 'preview-shots');

/** 既定の幅 → 画面の高さ（ページ全体を撮るので、高さは sticky などの見え方にだけ効く） */
const WIDTHS = { 360: 780, 390: 844, 768: 1024, 1024: 768, 1366: 768, 1440: 900, 1920: 1080, 2560: 1440 };
const STATES = ['none', 'launchpad', 'planning', 'deploy-main', 'link-main', 'draft', 'effects-use', 'final-score'];
/** 既定の撮影に足す組み合わせ（014 §10。観戦者・2 人 6 列・名声オン） */
const EXTRA_SHOTS = [
    { name: 'w1440-none-spectator', width: 1440, state: 'none', query: 'spectator=1' },
    { name: 'w1440-none-2p6col', width: 1440, state: 'none', query: 'sample=two-players-standard-6col' },
    { name: 'w1440-none-fame', width: 1440, state: 'none', query: 'fame=1' },
    { name: 'w390-none-fame', width: 390, state: 'none', query: 'fame=1' },
];
const MIME = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.mjs': 'text/javascript; charset=utf-8', '.css': 'text/css; charset=utf-8', '.json': 'application/json; charset=utf-8', '.webp': 'image/webp', '.png': 'image/png', '.svg': 'image/svg+xml' };

const sleep = (/** @type {number} */ ms) => new Promise((r) => setTimeout(r, ms));

/** @returns {Record<string, string | boolean>} */
function parseArgs(/** @type {string[]} */ argv) {
    /** @type {Record<string, string | boolean>} */
    const out = {};
    for (let i = 0; i < argv.length; i++) {
        if (!argv[i].startsWith('--')) {
            continue;
        }
        const key = argv[i].slice(2);
        const next = argv[i + 1];
        if (next === undefined || next.startsWith('--')) {
            out[key] = true;
        } else {
            out[key] = next;
            i++;
        }
    }
    return out;
}

/** @returns {string | null} Chrome の実行ファイル（見つからなければ null） */
function findChrome() {
    const candidates = [
        process.env.CHROME_PATH,
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/Applications/Chromium.app/Contents/MacOS/Chromium',
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    ];
    for (const c of candidates) {
        if (c !== undefined && c !== '' && existsSync(c)) {
            return c;
        }
    }
    for (const name of ['google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser', 'chrome']) {
        const r = spawnSync(process.platform === 'win32' ? 'where' : 'which', [name], { encoding: 'utf8' });
        if (r.status === 0 && r.stdout.trim() !== '') {
            return r.stdout.trim().split('\n')[0];
        }
    }
    return null;
}

/** リポジトリ直下を配る静的サーバー（ディレクトリの外は配らない） */
function startServer() {
    const server = createServer((req, res) => {
        const url = new URL(req.url ?? '/', 'http://localhost');
        const file = normalize(join(ROOT, decodeURIComponent(url.pathname)));
        if (file !== ROOT && !file.startsWith(ROOT + sep)) {
            res.writeHead(403).end();
            return;
        }
        try {
            const body = readFileSync(file);
            res.writeHead(200, { 'content-type': MIME[/** @type {keyof typeof MIME} */ (extname(file))] ?? 'application/octet-stream', 'cache-control': 'no-store' });
            res.end(body);
        } catch {
            res.writeHead(404).end();
        }
    });
    return new Promise((resolvePort) => {
        server.listen(0, '127.0.0.1', () => resolvePort({ server, port: /** @type {import('node:net').AddressInfo} */ (server.address()).port }));
    });
}

class Page {
    /** @param {WebSocket} ws */
    constructor(ws) {
        this.ws = ws;
        this.id = 0;
        /** @type {Map<number, { resolve: (v: any) => void, reject: (e: Error) => void }>} */
        this.pending = new Map();
        ws.addEventListener('message', (m) => {
            const msg = JSON.parse(String(m.data));
            if (msg.id !== undefined) {
                const p = this.pending.get(msg.id);
                this.pending.delete(msg.id);
                if (msg.error) {
                    p?.reject(new Error(JSON.stringify(msg.error)));
                } else {
                    p?.resolve(msg.result);
                }
            }
        });
    }

    /** @returns {Promise<any>} */
    send(/** @type {string} */ method, params = {}) {
        const id = ++this.id;
        return new Promise((resolveSend, reject) => {
            this.pending.set(id, { resolve: resolveSend, reject });
            this.ws.send(JSON.stringify({ id, method, params }));
        });
    }

    /** @returns {Promise<any>} */
    async eval(/** @type {string} */ expression) {
        const r = await this.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (r.exceptionDetails) {
            throw new Error(r.exceptionDetails.exception?.description ?? JSON.stringify(r.exceptionDetails));
        }
        return r.result.value;
    }

    /** 要素を画面の中央へスクロールして、その中心の座標を返す */
    rectOf(/** @type {string} */ selector, /** @type {boolean} */ scroll = true) {
        return this.eval(`(() => { const e = document.querySelector(${JSON.stringify(selector)}); if (!e) return null; if (${scroll}) e.scrollIntoView({ block: 'center', inline: 'center' }); const r = e.getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2, w: r.width, h: r.height }; })()`);
    }

    async capture(/** @type {string} */ out, /** @type {boolean} */ fullPage) {
        /** @type {Record<string, unknown>} */
        let params = { format: 'png' };
        if (fullPage) {
            const m = await this.send('Page.getLayoutMetrics');
            const size = m.cssContentSize ?? m.contentSize;
            params = { format: 'png', captureBeyondViewport: true, clip: { x: 0, y: 0, width: Math.ceil(size.width), height: Math.min(Math.ceil(size.height), 12000), scale: 1 } };
        }
        const r = await this.send('Page.captureScreenshot', params);
        writeFileSync(out, Buffer.from(r.data, 'base64'));
        console.log('wrote', out.replace(`${ROOT}${sep}`, ''));
    }
}

/**
 * @param {number} port DevTools のポート
 * @param {string} path
 * @param {string} [method]
 * @returns {Promise<any>}
 */
async function devtools(port, path, method = 'GET') {
    for (let i = 0; i < 60; i++) {
        try {
            const res = await fetch(`http://127.0.0.1:${port}${path}`, { method });
            const text = await res.text();
            // /json/close は JSON でない文字列を返す
            try {
                return JSON.parse(text);
            } catch {
                return text;
            }
        } catch {
            await sleep(200);
        }
    }
    throw new Error('Chrome に接続できませんでした');
}

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

/** @param {Page} page @param {Shot} shot @param {string} base @param {boolean} fullPageDefault */
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
    if (typeof args.spec === 'string') {
        shots = JSON.parse(readFileSync(resolve(args.spec), 'utf8')).shots;
    } else {
        const widths = typeof args.widths === 'string' ? args.widths.split(',').map(Number) : Object.keys(WIDTHS).map(Number);
        const states = typeof args.states === 'string' ? args.states.split(',') : STATES;
        const suffix = typeof args.suffix === 'string' ? `-${args.suffix}` : '';
        const query = typeof args.query === 'string' ? args.query : '';
        shots = widths.flatMap((width) => states.map((state) => ({ name: `w${width}-${state}${suffix}`, width, state, query })));
        if (typeof args.widths !== 'string' && typeof args.states !== 'string') {
            shots.push(...EXTRA_SHOTS.map((shot) => ({ ...shot, query: [shot.query, query].filter((q) => q !== '').join('&') })));
        }
    }
    const fullPage = args.viewport !== true;
    mkdirSync(OUT_DIR, { recursive: true });

    const { server, port: webPort } = /** @type {{ server: import('node:http').Server, port: number }} */ (await startServer());
    const debugPort = 9300 + Math.floor(Math.random() * 500);
    const profile = mkdtempSync(join(tmpdir(), 'pe-shoot-'));
    const chrome = spawn(chromePath, ['--headless=new', '--disable-gpu', '--hide-scrollbars', `--remote-debugging-port=${debugPort}`, `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', 'about:blank'], { stdio: 'ignore' });
    let overflows = 0;
    let errors = 0;
    try {
        for (const shot of shots) {
            const target = await devtools(debugPort, '/json/new?about:blank', 'PUT');
            const ws = new WebSocket(target.webSocketDebuggerUrl);
            await new Promise((ok, ng) => {
                ws.addEventListener('open', ok);
                ws.addEventListener('error', ng);
            });
            const page = new Page(ws);
            await page.send('Page.enable');
            await page.send('Runtime.enable');
            try {
                const r = await runShot(page, shot, `http://127.0.0.1:${webPort}`, fullPage);
                overflows += r.overflow ? 1 : 0;
                errors += r.errors;
            } catch (error) {
                console.log(`FAILED ${shot.name}: ${error instanceof Error ? error.message : error}`);
                errors++;
            }
            ws.close();
            await devtools(debugPort, `/json/close/${target.id}`).catch(() => {});
        }
    } finally {
        chrome.kill();
        server.closeAllConnections();
        server.close();
        await sleep(300);
        try {
            rmSync(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
        } catch {
            // 一時フォルダが消せなくても撮影は済んでいる
        }
    }
    console.log(`${shots.length} 枚。横のはみ出し ${overflows} 件、ページ内の例外 ${errors} 件。出力: build/preview-shots/`);
}

await main();
