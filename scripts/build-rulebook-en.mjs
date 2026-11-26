// 英語版ルールブック PDF のビルド。使い方・方針は tools/rulebook-en/README.md、docs/publishing/rulebook-en.md。
//   node scripts/build-rulebook-en.mjs [--only p04,p12] [--png] [--debug] [--scale 0.8] [--quality 80]
// 原本ページ画像（data/rulebooks/、Git 管理外）を下地にし、tools/rulebook-en/pages/<id>.html の断片を重ね、
// ヘッドレス Chrome（DevTools プロトコル）で PDF にする。出力は build/rulebook-en/（Git 管理外）。
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import sharp from 'sharp';
import { SHEETS, TRIM, PAGE_PT, STRIPE_COLUMNS } from '../tools/rulebook-en/sheets.mjs';
import { ICONS, ICON_HEIGHT } from '../tools/rulebook-en/assets.mjs';
import { mergePdfs } from './lib/pdf-merge.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const toolsDir = path.join(root, 'tools/rulebook-en');
const dataDir = path.join(root, 'data');
// 部品の試験用に、出力先と原稿の置き場を環境変数で切り替えられる（担当者の出力を上書きしないため。通常は使わない）
const outDir = process.env.RULEBOOK_OUT_DIR ? path.resolve(process.env.RULEBOOK_OUT_DIR) : path.join(root, 'build/rulebook-en');
const pagesDir = process.env.RULEBOOK_PAGES_DIR ? path.resolve(process.env.RULEBOOK_PAGES_DIR) : path.join(root, 'tools/rulebook-en/pages');
const CHROME = process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const BOOK_NAME = 'PROJECT-EDGEWORTH-Rulebook-EN.pdf';
const BOOK_TITLE = 'PROJECT EDGEWORTH Rulebook (English)';
// 下地の既定。1747 px 幅の原本を 0.8 倍（A5 で約 230 dpi 相当）・JPEG 品質 80 にする（根拠は README の「下地の画質」）
const DEFAULT_SCALE = 0.8;
const DEFAULT_QUALITY = 80;

// ---------- 引数 ----------
const argv = process.argv.slice(2);
const flag = (n) => argv.includes(`--${n}`);
const value = (n, def) => {
  const i = argv.indexOf(`--${n}`);
  return i >= 0 && argv[i + 1] !== undefined ? argv[i + 1] : def;
};
const known = new Set(['--only', '--png', '--debug', '--scale', '--quality', '--help']);
for (const a of argv) if (a.startsWith('--') && !known.has(a)) fail(`不明なオプション: ${a}\n使い方: node scripts/build-rulebook-en.mjs [--only p04,p12] [--png] [--debug] [--scale 0.8] [--quality 80]`);
if (flag('help')) { console.log('node scripts/build-rulebook-en.mjs [--only p04,p12] [--png] [--debug] [--scale 0.8] [--quality 80]'); process.exit(0); }
const scale = Number(value('scale', DEFAULT_SCALE));
const quality = Number(value('quality', DEFAULT_QUALITY));
const onlyArg = value('only', null);
const wantPng = flag('png');
const debug = flag('debug');

// 出力先（パイプ）が先に閉じられても（`| head` など）EPIPE で落ちず、最後まで走って後始末する
for (const stream of [process.stdout, process.stderr]) stream.on('error', (e) => { if (e.code !== 'EPIPE') throw e; });

// Chrome の一時ディレクトリは、どの終わり方（fail・例外・シグナル）でも消す
let chromeChild = null;
let profileDir = null;
function cleanup() {
  try { chromeChild?.kill('SIGKILL'); } catch { /* すでに終了 */ }
  chromeChild = null;
  if (profileDir) {
    try { fs.rmSync(profileDir, { recursive: true, force: true, maxRetries: 10, retryDelay: 100 }); } catch { /* 次回の掃除に任せる */ }
    profileDir = null;
  }
}
process.on('exit', cleanup);
for (const sig of ['SIGINT', 'SIGTERM', 'SIGHUP']) process.on(sig, () => process.exit(1));

function fail(msg) { console.error(`エラー: ${msg}`); process.exit(1); }

const onlyIds = onlyArg ? onlyArg.split(',').map((s) => s.trim()).filter(Boolean) : null;
if (onlyIds) {
  const bad = onlyIds.filter((id) => !SHEETS.some((s) => s.id === id));
  if (bad.length) fail(`不明なシートID: ${bad.join(', ')}\n有効なID: ${SHEETS.map((s) => s.id).join(' ')}`);
}
if (!(scale > 0.2 && scale <= 1)) fail('--scale は 0.2 より大きく 1 以下');
if (!(quality >= 30 && quality <= 100)) fail('--quality は 30〜100');
if (!fs.existsSync(path.join(dataDir, 'rulebooks'))) fail('data/rulebooks/ が見つかりません。原本画像は作者の手元の data/ にだけあります（ワークツリーでは ../../data へのシンボリックリンク）。');
if (!fs.existsSync(CHROME)) fail(`Chrome が見つかりません: ${CHROME}\n環境変数 CHROME_PATH に Google Chrome の実行ファイルのパスを指定してください。`);

const targets = onlyIds ? SHEETS.filter((s) => onlyIds.includes(s.id)) : SHEETS;
for (const d of ['bg', 'assets', 'partial', 'preview', 'tmp']) fs.mkdirSync(path.join(outDir, d), { recursive: true });

// 同時に別の担当者がビルドしても壊れないよう、成果物は一時名に書いてから置き換える
function writeAtomic(file, buf) {
  const tmp = `${file}.tmp-${process.pid}`;
  fs.writeFileSync(tmp, buf);
  fs.renameSync(tmp, file);
}

// ---------- 下地（JPEG）と縞の見本 ----------
async function prepareBackground(sheet) {
  const src = path.join(dataDir, 'rulebooks', sheet.file);
  const jpg = path.join(outDir, 'bg', `${sheet.id}.jpg`);
  const meta = path.join(outDir, 'bg', `${sheet.id}.json`);
  const stat = fs.statSync(src);
  const key = JSON.stringify({ m: stat.mtimeMs, s: stat.size, scale, quality, v: 11 });
  const size = await sharp(src).metadata();
  const info = { width: size.width, height: size.height, key };
  const stripeFiles = sheet.kind === 'spread' ? ['l', 'r'] : ['l'];
  const fresh = fs.existsSync(meta) && fs.existsSync(jpg) && JSON.parse(fs.readFileSync(meta, 'utf8')).key === key
    && stripeFiles.every((s) => fs.existsSync(path.join(outDir, 'bg', `${sheet.id}-${s}.png`)));
  if (!fresh) {
    const w = Math.round(size.width * scale);
    const h = Math.round(size.height * scale);
    const buf = await sharp(src).removeAlpha().resize({ width: w, height: h, kernel: 'lanczos3' })
      .jpeg({ quality, mozjpeg: true, chromaSubsampling: '4:4:4' }).toBuffer();
    // 縞の見本は、実際に PDF に入る JPEG から採る（消した跡と周囲が同じ解像度・同じ劣化になる）
    const raw = await sharp(buf).removeAlpha().raw().toBuffer({ resolveWithObject: true });
    const cols = STRIPE_COLUMNS[sheet.kind];
    for (const side of stripeFiles) {
      const strip = stripeProfile(raw.data, raw.info.width, raw.info.height, cols[side].map((x) => Math.round(x * scale)), scale);
      writeAtomic(path.join(outDir, 'bg', `${sheet.id}-${side}.png`), await sharp(strip, { raw: { width: 8, height: raw.info.height, channels: 3 } }).png().toBuffer());
    }
    writeAtomic(jpg, buf);
    writeAtomic(meta, JSON.stringify(info));
  }
  return { jpg, ...info };
}

// 縞の見本（各行の地の色）を作る。
// ① 幅 30 px の帯を列方向に並べ、帯ごと・行ごとに「縞の地らしい暗い色（R 30〜105 など）」で 8 割が占められているか調べる。
//    上下 1 行（縮小で滲む分）も同じ条件のときだけ「純粋な地」の行とし、純粋な帯が 3 本以上一致した色（±8）をその行の実測値にする。
//    表の罫線や文字・図の周りの行は実測できない。
// ② 実測できた行から線（明るい行）の位置を求め、線の間隔（原本で 20.4 px × 倍率）の等間隔の並びを最小二乗で当てはめる。
//    線の断面（中心からの距離ごとの色）は実測値から平均して作る。
// ③ ②が十分当てはまったページは、全行をこの並びと断面から作る（表の罫線の縁など、線の色に見える偽の線を実測値に入れないため）。
//    当てはまらないページ（縞の地が無い・灰色や青の大きな面のページ）は実測できた行だけを使い、残りは暗い地の色にする。
// 縞は横方向に一様なので 8 px 幅の縦長画像にして、背景として引き伸ばして使う。
// 縞の地の色は「暗い地 ≈ (35,25,22)」と「明るい線 ≈ (89,93,103)」と、その間の混ざった色（縮小で滲んだ線の縁）だけ。
// この 2 点を結ぶ線分からの距離が小さい画素だけを地とみなす（カードの暗い青緑・灰色の箱・紺の飾りなどは外れる）
const SEG_A = [35, 25, 22], SEG_B = [89, 93, 103];
const SEG_D = [SEG_B[0] - SEG_A[0], SEG_B[1] - SEG_A[1], SEG_B[2] - SEG_A[2]];
const SEG_LEN2 = SEG_D[0] ** 2 + SEG_D[1] ** 2 + SEG_D[2] ** 2;
function onStripeSegment(r, g, b, tol = 9) {
  const t = Math.min(1, Math.max(0, ((r - SEG_A[0]) * SEG_D[0] + (g - SEG_A[1]) * SEG_D[1] + (b - SEG_A[2]) * SEG_D[2]) / SEG_LEN2));
  const dr = r - (SEG_A[0] + t * SEG_D[0]), dg = g - (SEG_A[1] + t * SEG_D[1]), db = b - (SEG_A[2] + t * SEG_D[2]);
  return dr * dr + dg * dg + db * db <= tol * tol;
}

function stripeProfile(data, W, H, [x0, x1], s) {
  const BAND = 30;
  const starts = [];
  for (let bx = x0; bx + BAND <= x1; bx += BAND) starts.push(bx);
  const pure = (y, bx) => {
    const hist = new Map();
    for (let x = bx; x < bx + BAND; x++) {
      const i = (y * W + x) * 3;
      const r = data[i], g = data[i + 1], b = data[i + 2];
      if (!onStripeSegment(r, g, b)) continue;
      const k = ((r >> 3) << 10) | ((g >> 3) << 5) | (b >> 3);
      hist.set(k, (hist.get(k) ?? 0) + 1);
    }
    let bestK = -1, best = 0;
    for (const [k, c] of hist) if (c > best) { best = c; bestK = k; }
    if (bestK < 0 || best < BAND * 0.8) return null;
    let sr = 0, sg = 0, sb = 0, c = 0;
    for (let x = bx; x < bx + BAND; x++) {
      const i = (y * W + x) * 3;
      if ((((data[i] >> 3) << 10) | ((data[i + 1] >> 3) << 5) | (data[i + 2] >> 3)) === bestK) { sr += data[i]; sg += data[i + 1]; sb += data[i + 2]; c++; }
    }
    return [sr / c, sg / c, sb / c];
  };
  const cand = starts.map((bx) => Array.from({ length: H }, (_, y) => pure(y, bx)));
  const rows = new Array(H).fill(null);
  for (let y = 1; y < H - 1; y++) {
    const cs = [];
    for (const col of cand) if (col[y - 1] && col[y] && col[y + 1]) cs.push(col[y]);
    let bestGroup = [];
    for (const c of cs) {
      const grp = cs.filter((d) => Math.abs(d[0] - c[0]) <= 8 && Math.abs(d[1] - c[1]) <= 8 && Math.abs(d[2] - c[2]) <= 8);
      if (grp.length > bestGroup.length) bestGroup = grp;
    }
    if (bestGroup.length >= 3) rows[y] = [0, 1, 2].map((ch) => bestGroup.reduce((a, d) => a + d[ch], 0) / bestGroup.length);
  }
  // ② 線の位置（実測できた連続行のうち R が地より高い塊の重心）
  const BASE = [35, 25, 22];
  const centers = [];
  for (let y = 2; y < H - 2;) {
    if (rows[y] && rows[y][0] > 45 && rows[y - 1] && rows[y - 2]) {
      let ye = y, sw = 0, sy = 0;
      while (ye < H - 2 && rows[ye] && rows[ye][0] > 40) { const w = rows[ye][0] - 36; sw += w; sy += w * (ye + 0.5); ye++; }
      if (rows[ye] && rows[ye + 1] && ye + 1 < H) centers.push(sy / sw);
      y = ye + 1;
    } else y++;
  }
  const P = 20.4 * s;
  // 線の並びを「間隔が P の整数倍（±1 px）でつながる塊」に分ける。表の罫線の縁などの偽の線は、前後の線から見て間隔が合わないので飛ばす。
  // ページの途中で縞の位相がずれているページでも、塊ごとに当てはめれば各部分を再現できる
  const okGap = (g) => { const m = Math.round(g / P); return m >= 1 && m <= 6 && Math.abs(g - m * P) <= 1.0; };
  const groups = [];
  let cur = centers.length ? [centers[0]] : [];
  for (let i = 1; i < centers.length; i++) {
    if (okGap(centers[i] - cur[cur.length - 1])) cur.push(centers[i]);
    else if (i + 1 < centers.length && okGap(centers[i + 1] - cur[cur.length - 1])) continue; // 偽の線を飛ばす
    else { groups.push(cur); cur = [centers[i]]; }
  }
  if (cur.length) groups.push(cur);
  const fits = [];
  for (const g of groups) {
    if (g.length < 6) continue;
    let k = 0;
    const use = g.map((c, i) => { if (i) k += Math.round((c - g[i - 1]) / P); return [k, c]; });
    const n = use.length;
    const sx = use.reduce((t, u) => t + u[0], 0), sy = use.reduce((t, u) => t + u[1], 0);
    const sxx = use.reduce((t, u) => t + u[0] * u[0], 0), sxy = use.reduce((t, u) => t + u[0] * u[1], 0);
    const pp = (n * sxy - sx * sy) / (n * sxx - sx * sx);
    const a = (sy - pp * sx) / n;
    fits.push({ a, p: pp, lo: g[0], hi: g[g.length - 1], use: use.filter((u) => Math.abs(u[1] - (a + pp * u[0])) < 0.7) });
  }
  let model = null;
  if (fits.reduce((t, f) => t + f.use.length, 0) >= 12) {
    // 断面: 線の中心からの距離（0.25 刻み）ごとの色の平均（地との差）。全部の塊から集める
    const prof = new Map();
    for (const f of fits) for (const [k] of f.use) {
      const cc = f.a + f.p * k;
      for (let y = Math.floor(cc) - 3; y <= Math.floor(cc) + 3; y++) {
        if (!rows[y]) continue;
        const key = Math.round((y + 0.5 - cc) * 4);
        const e = prof.get(key) ?? { n: 0, v: [0, 0, 0] };
        e.n++; for (let ch = 0; ch < 3; ch++) e.v[ch] += rows[y][ch] - BASE[ch];
        prof.set(key, e);
      }
    }
    model = { fits, prof };
  }
  const modelRow = (y) => {
    if (!model) return BASE;
    // y に最も近い塊の当てはめを使う（塊の外では前後の塊の並びを周期で延ばす）
    let f = model.fits[0], best = Infinity;
    for (const t of model.fits) { const d = y < t.lo ? t.lo - y : y > t.hi ? y - t.hi : 0; if (d < best) { best = d; f = t; } }
    const k = Math.round((y + 0.5 - f.a) / f.p);
    const v = [...BASE];
    for (const kk of [k - 1, k, k + 1]) {
      const cc = f.a + f.p * kk;
      const key = Math.round((y + 0.5 - cc) * 4);
      const e = model.prof.get(key);
      if (e && e.n >= 3) for (let ch = 0; ch < 3; ch++) v[ch] += e.v[ch] / e.n;
    }
    return v;
  };
  const out = Buffer.alloc(8 * H * 3);
  for (let y = 0; y < H; y++) {
    // 模型が当てはまったページは全行を模型で作る（実測の行には、表の罫線の縁など線の色に見える偽の線が混ざりうるため）
    const m = model ? modelRow(y) : (rows[y] ?? BASE);
    for (let x = 0; x < 8; x++) { const o = (y * 8 + x) * 3; out[o] = Math.round(m[0]); out[o + 1] = Math.round(m[1]); out[o + 2] = Math.round(m[2]); }
  }
  return out;
}

// ---------- アイコン ----------
// ページの地（暗い縞の色）に近い画素を透明にする（stripeProfile と同じ判定）
async function keyOutBackground(img) {
  const { data, info } = await img.ensureAlpha().raw().toBuffer({ resolveWithObject: true });
  for (let i = 0; i < data.length; i += 4) {
    const r = data[i], g = data[i + 1], b = data[i + 2];
    if (r >= 30 && r <= 105 && g >= 20 && g <= 110 && b >= 18 && b <= 120 && b - r <= 40 && Math.max(r, g, b) - Math.min(r, g, b) <= 40) data[i + 3] = 0;
  }
  return sharp(data, { raw: { width: info.width, height: info.height, channels: 4 } });
}

async function prepareAssets() {
  const css = [];
  for (const [name, def] of Object.entries(ICONS)) {
    let img;
    if (def.src) {
      const f = path.join(dataDir, def.src);
      if (!fs.existsSync(f)) fail(`アイコンの原本が見つかりません: data/${def.src}（assets.mjs の ${name}）`);
      img = sharp(f);
    } else {
      const sheet = SHEETS.find((s) => s.id === def.page);
      if (!sheet) fail(`assets.mjs の ${name}: 不明なシートID ${def.page}`);
      img = sharp(path.join(dataDir, 'rulebooks', sheet.file)).extract({ left: def.x, top: def.y, width: def.w, height: def.h });
    }
    if (def.keyOut) img = await keyOutBackground(img);
    const buf = await img.resize({ height: ICON_HEIGHT, fit: 'inside', withoutEnlargement: true }).png({ compressionLevel: 9 }).toBuffer();
    const m = await sharp(buf).metadata();
    writeAtomic(path.join(outDir, 'assets', `${name}.png`), buf);
    css.push(`.ic.${name} { background-image: url("${name}.png"); width: calc(var(--ich, 1.15em) * ${(m.width / m.height).toFixed(4)}); }`);
    if (name === 'fame-base') css.push('.ic.fame { background-image: url("fame-base.png"); }');
  }
  writeAtomic(path.join(outDir, 'assets', 'icons.css'), `/* build-rulebook-en.mjs が生成 */\n${css.join('\n')}\n`);
}

// ---------- HTML ----------
function sheetHtml(sheet, bg) {
  const trim = TRIM[sheet.kind];
  const pt = PAGE_PT[sheet.kind];
  const pxPerPt = 96 / 72; // 1 pt あたりの CSS px
  // 仕上がり枠が出力ページを隙間なく覆う拡大率（cover）。はみ出る分（1 px 未満）は中央から均等に切る
  const k = Math.max((pt.w * pxPerPt) / trim.w, (pt.h * pxPerPt) / trim.h);
  const left = -((trim.w * k) - pt.w * pxPerPt) / 2;
  const top = -((trim.h * k) - pt.h * pxPerPt) / 2;
  const fragFile = path.join(pagesDir, `${sheet.id}.html`);
  const fragment = fs.existsSync(fragFile) ? fs.readFileSync(fragFile, 'utf8') : '';
  const split = sheet.kind === 'spread' ? STRIPE_COLUMNS.spread.split : 0;
  // カスタムプロパティの url() は使う側の CSS ファイルを基準に解決されるので、絶対 URL にする
  const bgUrl = pathToFileURL(path.join(outDir, 'bg')).href;
  const stripeR = sheet.kind === 'spread' ? `${sheet.id}-r.png` : `${sheet.id}-l.png`;
  return `<section class="outer ${sheet.kind}" data-id="${sheet.id}"><div class="page" style="width:${trim.w}px;height:${trim.h}px;left:${left.toFixed(3)}px;top:${top.toFixed(3)}px;transform:scale(${k.toFixed(6)})">
<div class="sheet" data-id="${sheet.id}" data-split="${split}" data-w="${bg.width}" data-h="${bg.height}" style="--ox:${trim.x};--oy:${trim.y};--sw:${bg.width};--sh:${bg.height};--stripe-l:url('${bgUrl}/${sheet.id}-l.png');--stripe-r:url('${bgUrl}/${stripeR}')">
<img class="bg" src="bg/${sheet.id}.jpg" alt="">
${fragment}
</div></div></section>`;
}

function buildHtml(sheets, bgs) {
  const rel = path.relative(outDir, toolsDir).split(path.sep).join('/');
  return `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>PROJECT EDGEWORTH Rulebook (English)</title>
<link rel="stylesheet" href="${rel}/rulebook.css">
<link rel="stylesheet" href="assets/icons.css">
<style>@page single { size: ${PAGE_PT.single.w}pt ${PAGE_PT.single.h}pt; margin: 0; } @page spread { size: ${PAGE_PT.spread.w}pt ${PAGE_PT.spread.h}pt; margin: 0; }</style>
</head><body${debug ? ' class="debug"' : ''}>
${sheets.map((s) => sheetHtml(s, bgs[s.id])).join('\n')}
<script src="${rel}/fit.js"></script>
</body></html>
`;
}

// ---------- Chrome（DevTools プロトコル） ----------
class Cdp {
  constructor(ws) {
    this.ws = ws; this.seq = 0; this.pending = new Map(); this.listeners = [];
    ws.addEventListener('message', (ev) => {
      const msg = JSON.parse(ev.data);
      if (msg.id && this.pending.has(msg.id)) {
        const { resolve, reject } = this.pending.get(msg.id);
        this.pending.delete(msg.id);
        if (msg.error) reject(new Error(`${msg.error.message} (${JSON.stringify(msg.error.data ?? '')})`)); else resolve(msg.result);
      } else if (msg.method) {
        for (const l of this.listeners) l(msg);
      }
    });
  }
  send(method, params = {}, sessionId) {
    const id = ++this.seq;
    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      this.ws.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
    });
  }
  waitEvent(method, timeoutMs = 120000) {
    return new Promise((resolve, reject) => {
      const t = setTimeout(() => reject(new Error(`${method} を待っている間にタイムアウト`)), timeoutMs);
      const l = (msg) => { if (msg.method === method) { clearTimeout(t); this.listeners = this.listeners.filter((x) => x !== l); resolve(msg.params); } };
      this.listeners.push(l);
    });
  }
}

async function launchChrome(dir) {
  const child = chromeChild = spawn(CHROME, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--hide-scrollbars',
    '--remote-debugging-port=0', `--user-data-dir=${dir}`, 'about:blank'], { stdio: 'ignore' });
  let exited = false;
  child.on('exit', () => { exited = true; });
  const portFile = path.join(dir, 'DevToolsActivePort');
  const t0 = Date.now();
  while (!fs.existsSync(portFile) || fs.readFileSync(portFile, 'utf8').split('\n').length < 2) {
    if (exited) fail('Chrome が起動直後に終了しました。');
    if (Date.now() - t0 > 30000) { child.kill(); fail('Chrome の起動がタイムアウトしました。'); }
    await new Promise((r) => setTimeout(r, 100));
  }
  const [port, wsPath] = fs.readFileSync(portFile, 'utf8').trim().split('\n');
  const ws = new WebSocket(`ws://127.0.0.1:${port}${wsPath}`);
  await new Promise((res, rej) => { ws.addEventListener('open', res); ws.addEventListener('error', () => rej(new Error('Chrome への接続に失敗'))); });
  return { child, cdp: new Cdp(ws), ws };
}

const evalJs = async (cdp, sid, expression) => {
  const r = await cdp.send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }, sid);
  if (r.exceptionDetails) throw new Error(`ページ内の式でエラー: ${r.exceptionDetails.text} ${r.exceptionDetails.exception?.description ?? ''}`);
  return r.result.value;
};

// ---------- 本体 ----------
const bgs = {};
for (const s of targets) bgs[s.id] = await prepareBackground(s);
await prepareAssets();

const tag = onlyIds ? onlyIds.join('+') : 'all';
const htmlPath = path.join(outDir, `book-${tag}.html`);
fs.writeFileSync(htmlPath, buildHtml(targets, bgs));

profileDir = fs.mkdtempSync(path.join(os.tmpdir(), 'rulebook-en-chrome-'));
let chrome;
try {
  chrome = await launchChrome(profileDir);
  const { cdp } = chrome;
  const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
  const { sessionId: sid } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
  await cdp.send('Page.enable', {}, sid);
  const loaded = cdp.waitEvent('Page.loadEventFired');
  await cdp.send('Page.navigate', { url: `file://${htmlPath}` }, sid);
  await loaded;
  const t0 = Date.now();
  while (!(await evalJs(cdp, sid, 'window.__rbDone === true'))) {
    if (Date.now() - t0 > 20000) {
      const diag = await evalJs(cdp, sid, "JSON.stringify({ fonts: [...document.fonts].map(f => f.family + ' ' + f.weight + ' ' + f.status), scripts: document.scripts.length, err: window.__rbError || null })");
      fail(`fit.js が完了しませんでした（20 秒）。${diag}`);
    }
    await new Promise((r) => setTimeout(r, 100));
  }
  const err = await evalJs(cdp, sid, 'window.__rbError || null');
  if (err) fail(`fit.js のエラー: ${err}`);
  const report = JSON.parse(await evalJs(cdp, sid, 'JSON.stringify(window.__rb)'));

  // はみ出しの報告
  let totalOver = 0;
  for (const s of targets) {
    const r = report[s.id];
    const file = path.join(outDir, `report-${s.id}.json`);
    fs.writeFileSync(file, `${JSON.stringify({ sheet: s.id, ...r }, null, 2)}\n`);
    totalOver += r.overflowCount;
    const flagText = r.boxes.length || r.erases ? `文字の箱 ${r.boxes.length} / 縮小 ${r.shrunkCount} / はみ出し ${r.overflowCount} / 消す四角 ${r.erases}` : '原稿なし（下地のみ）';
    console.log(`${s.id}: ${flagText}`);
    for (const b of r.boxes) {
      if (b.overflow) console.log(`  はみ出し: 「${b.label}」 (${b.x},${b.y}) ${b.w}x${b.h} 指定 ${b.fontSize}px → ${b.finalFontSize}px でも内容の高さ ${b.contentHeight}px`);
      else if (b.shrunk) console.log(`  縮小: 「${b.label}」 (${b.x},${b.y}) ${b.fontSize}px → ${b.finalFontSize}px`);
    }
  }

  // 確認用 PNG（原本と同じ画素寸法・座標系）
  if (wantPng) {
    for (const s of targets) {
      const { width, height } = bgs[s.id];
      await evalJs(cdp, sid, `document.body.classList.add('preview'); document.querySelectorAll('.outer').forEach(e => e.classList.toggle('off', e.dataset.id !== ${JSON.stringify(s.id)}));`);
      await cdp.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false }, sid);
      const shot = await cdp.send('Page.captureScreenshot', { format: 'png', clip: { x: 0, y: 0, width, height, scale: 1 }, captureBeyondViewport: true }, sid);
      writeAtomic(path.join(outDir, 'preview', `${s.id}.png`), Buffer.from(shot.data, 'base64'));
      console.log(`  preview/${s.id}.png (${width}x${height})`);
    }
    await cdp.send('Emulation.clearDeviceMetricsOverride', {}, sid);
    await evalJs(cdp, sid, "document.body.classList.remove('preview'); document.querySelectorAll('.outer').forEach(e => e.classList.remove('off'));");
  }

  // PDF
  const print = async () => {
    const r = await cdp.send('Page.printToPDF', { printBackground: true, preferCSSPageSize: true, displayHeaderFooter: false }, sid);
    return Buffer.from(r.data, 'base64');
  };
  if (onlyIds) {
    for (const s of targets) {
      await evalJs(cdp, sid, `document.querySelectorAll('.outer').forEach(e => e.classList.toggle('off', e.dataset.id !== ${JSON.stringify(s.id)}));`);
      const pdf = await print();
      const f = path.join(outDir, 'partial', `${s.id}.pdf`);
      writeAtomic(f, pdf);
      console.log(`PDF: ${path.relative(root, f)} (${(pdf.length / 1048576).toFixed(2)} MB)`);
    }
  } else {
    // 通しの PDF: 幅の違うページ（単ページ 420 pt・見開き 839 pt）を 1 回の印刷に混ぜると Chrome がレイアウトを崩すため、
    // シートごとに印刷し（--only と同じ PDF）、scripts/lib/pdf-merge.mjs で結合する
    const pdfs = [];
    for (const s of targets) {
      await evalJs(cdp, sid, `document.querySelectorAll('.outer').forEach(e => e.classList.toggle('off', e.dataset.id !== ${JSON.stringify(s.id)}));`);
      pdfs.push(await print());
    }
    let pdf;
    try { pdf = mergePdfs(pdfs, { title: BOOK_TITLE }); } catch (e) { fail(e.message); }
    const f = path.join(outDir, BOOK_NAME);
    writeAtomic(f, pdf);
    console.log(`PDF: ${path.relative(root, f)} (${(pdf.length / 1048576).toFixed(2)} MB)`);
  }
  if (totalOver > 0) console.log(`警告: はみ出した文字の箱が ${totalOver} 個あります。言い回しを短くするか箱を広げてください。`);
  chrome.ws.close();
} finally {
  if (chrome) {
    const exited = new Promise((r) => chrome.child.once('exit', r));
    chrome.child.kill();
    await Promise.race([exited, new Promise((r) => setTimeout(r, 3000))]);
  }
  fs.rmSync(profileDir, { recursive: true, force: true, maxRetries: 10, retryDelay: 200 });
  profileDir = null;
  chromeChild = null;
}
