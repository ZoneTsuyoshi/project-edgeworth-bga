/**
 * Chrome を DevTools Protocol で動かす共通部品（shoot.mjs と check-ui.mjs が使う。仕様書 014 §10）。
 * 依存の追加なし（Node 組み込みの WebSocket・node:http・node:child_process だけ）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { tmpdir } from 'node:os';
import { dirname, extname, join, normalize, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

// PE_ROOT: 別のチェックアウト（例: main の複製）を配る（操作の前後比較用）
export const ROOT = process.env.PE_ROOT === undefined ? resolve(dirname(fileURLToPath(import.meta.url)), '..', '..') : resolve(process.env.PE_ROOT);

const MIME = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.mjs': 'text/javascript; charset=utf-8', '.css': 'text/css; charset=utf-8', '.json': 'application/json; charset=utf-8', '.webp': 'image/webp', '.png': 'image/png', '.svg': 'image/svg+xml' };

export const sleep = (/** @type {number} */ ms) => new Promise((r) => setTimeout(r, ms));

/** @returns {Record<string, string | boolean>} */
export function parseArgs(/** @type {string[]} */ argv) {
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
export function findChrome() {
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
export function startServer() {
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

export class Page {
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
export async function devtools(port, path, method = 'GET') {
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
 * ツールチップの矩形が見える領域（端から 8 px、上はステータスバーの下）の中にあり、対象の矩形と重ならないことを確かめる。
 * @param {Page} page
 * @param {string} selector 対象の css
 * @returns {Promise<string>} `OK ...` または `FAIL ...`
 */
export async function checkTip(page, selector) {
    return page.eval(`(() => {
        const tip = document.getElementById('pe-tip');
        const anchor = document.querySelector(${JSON.stringify(selector)});
        if (!tip || tip.hidden) return 'FAIL ツールチップが出ていない';
        if (!anchor) return 'FAIL 対象が無い';
        const t = tip.getBoundingClientRect();
        const a = anchor.getBoundingClientRect();
        const bar = document.getElementById('page-title');
        const top = Math.max(8, (bar ? Math.max(0, bar.getBoundingClientRect().bottom) : 0) + 8);
        const vw = Math.min(window.innerWidth, document.documentElement.clientWidth), vh = window.innerHeight;
        const problems = [];
        if (t.left < 8 - 0.5 || t.right > vw - 8 + 0.5 || t.top < top - 0.5 || t.bottom > vh - 8 + 0.5) problems.push('画面の外にはみ出している');
        const ox = Math.min(t.right, a.right) - Math.max(t.left, a.left), oy = Math.min(t.bottom, a.bottom) - Math.max(t.top, a.top);
        if (ox > 0.5 && oy > 0.5) problems.push('対象と重なっている');
        if (tip.scrollHeight > tip.clientHeight + 1) problems.push('中身が切れている（scrollHeight ' + tip.scrollHeight + ' > ' + tip.clientHeight + '）');
        const r = (x) => Math.round(x);
        return (problems.length ? 'FAIL ' + problems.join(' / ') : 'OK') + ' tip=' + [r(t.left), r(t.top), r(t.width), r(t.height)] + ' anchor=' + [r(a.left), r(a.top), r(a.width), r(a.height)] + ' view=' + vw + 'x' + vh + ' barBottom=' + r(top - 8);
    })()`);
}


/**
 * Chrome を起動して、ページを 1 枚ずつ開ける形にする。
 * @param {string} chromePath
 * @returns {{ debugPort: number, close: () => Promise<void>, open: () => Promise<{ page: Page, close: () => Promise<void> }> }}
 */
export function launchChrome(chromePath) {
    const debugPort = 9300 + Math.floor(Math.random() * 500);
    const profile = mkdtempSync(join(tmpdir(), 'pe-shoot-'));
    const chrome = spawn(chromePath, ['--headless=new', '--disable-gpu', '--hide-scrollbars', `--remote-debugging-port=${debugPort}`, `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', 'about:blank'], { stdio: 'ignore' });
    return {
        debugPort,
        async open() {
            const target = await devtools(debugPort, '/json/new?about:blank', 'PUT');
            const ws = new WebSocket(target.webSocketDebuggerUrl);
            await new Promise((ok, ng) => {
                ws.addEventListener('open', ok);
                ws.addEventListener('error', ng);
            });
            const page = new Page(ws);
            await page.send('Page.enable');
            await page.send('Runtime.enable');
            return {
                page,
                async close() {
                    ws.close();
                    await devtools(debugPort, `/json/close/${target.id}`).catch(() => {});
                },
            };
        },
        async close() {
            chrome.kill();
            await sleep(300);
            try {
                rmSync(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
            } catch {
                // 一時フォルダが消せなくても検査は済んでいる
            }
        },
    };
}
