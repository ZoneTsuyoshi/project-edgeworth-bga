// 英語版ルールブック用の測定補助。原本 PNG の一部を拡大して切り出し、原本の座標の目盛りを重ねる。
//   node scripts/rulebook-en-crop.mjs <シートID> <x> <y> <w> <h> [--zoom 1.5] [--grid 50] [--out パス]
//   node scripts/rulebook-en-crop.mjs <シートID> <x> <y> <w> <h> --bbox
// --bbox   領域内の明るい画素（白っぽい文字など）の行ごとの外接矩形を表示する（文字の位置と行の高さを測る）
// --lines  領域内の白い罫線（表の枠など）の位置を表示する（--min 長さ。既定 100 px 以上）
// --dark   領域内の暗い画素（札や箱の中の黒い文字など）の左右の塊と縦の範囲を表示する（札の中の文字を消す範囲を測る）
// --colors 領域内の代表色を表示する（単色の箱の色、文字色を拾う）
// --scan x|y <固定座標> <始点> <終点>   1 本の線に沿って同じ色が続く区間を表示する（箱・帯の端、縞の位置を測る。<x> <y> <w> <h> は不要）
// 出力の既定は build/rulebook-en/tmp/crop-<ID>-<x>-<y>.png（Git 管理外）。
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';
import { SHEETS } from '../tools/rulebook-en/sheets.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const args = process.argv.slice(2);
const opt = (name, def) => {
  const i = args.indexOf(`--${name}`);
  if (i < 0) return def;
  return args.splice(i, 2)[1];
};
const takeFlag = (n) => { const i = args.indexOf(`--${n}`); if (i < 0) return false; args.splice(i, 1); return true; };
const bboxMode = takeFlag('bbox');
const linesMode = takeFlag('lines');
const darkMode = takeFlag('dark');
const colorsMode = takeFlag('colors');
const minLen = Number(opt('min', 100));
const scanIdx = args.indexOf('--scan');
const scanArgs = scanIdx >= 0 ? args.splice(scanIdx, 5).slice(1) : null;
const zoom = Number(opt('zoom', 1));
const grid = Number(opt('grid', 50));
const outOpt = opt('out', null);
const [id, ...nums] = args;
const [x, y, w, h] = nums.map(Number);
const sheet = SHEETS.find((s) => s.id === id);
if (!sheet || (!scanArgs && [x, y, w, h].some((n) => !Number.isFinite(n)))) {
  console.error('使い方: node scripts/rulebook-en-crop.mjs <シートID> <x> <y> <w> <h> [--zoom 1.5] [--grid 50] [--out パス] [--bbox|--lines|--dark|--colors]');
  console.error('        node scripts/rulebook-en-crop.mjs <シートID> --scan x|y <固定座標> <始点> <終点>');
  console.error('シートID:', SHEETS.map((s) => s.id).join(' '));
  process.exit(1);
}
const file = path.join(root, 'data/rulebooks', sheet.file);
if (scanArgs) {
  const [axis, fixed, a, b] = scanArgs;
  const { data, info } = await sharp(file).removeAlpha().raw().toBuffer({ resolveWithObject: true });
  const runs = [];
  let prev = null, start = Number(a);
  for (let t = Number(a); t <= Number(b); t++) {
    const px = axis === 'x' ? Number(fixed) : t, py = axis === 'x' ? t : Number(fixed);
    const i = (py * info.width + px) * 3;
    const c = `${data[i]},${data[i + 1]},${data[i + 2]}`;
    if (c !== prev) { if (prev !== null && t - start >= 3) runs.push(`${start}-${t - 1}:${prev}`); prev = c; start = t; }
  }
  runs.push(`${start}-${b}:${prev}`);
  console.log(`${id} ${axis}=${fixed} に沿った 3 px 以上同色の区間（始点-終点:R,G,B）:`);
  console.log(runs.join('  '));
  process.exit(0);
}
const meta = await sharp(file).metadata();
const cx = Math.max(0, x), cy = Math.max(0, y);
const cw = Math.min(w, meta.width - cx), ch = Math.min(h, meta.height - cy);

if (linesMode || darkMode || colorsMode) {
  const { data, info } = await sharp(file).extract({ left: cx, top: cy, width: cw, height: ch }).removeAlpha().raw().toBuffer({ resolveWithObject: true });
  const W = info.width, H = info.height;
  const at = (xx, yy) => (yy * W + xx) * 3;
  if (colorsMode) {
    const m = new Map();
    for (let yy = 0; yy < H; yy++) for (let xx = 0; xx < W; xx++) {
      const i = at(xx, yy);
      const k = ((data[i] >> 2) << 16) | ((data[i + 1] >> 2) << 8) | (data[i + 2] >> 2);
      m.set(k, (m.get(k) ?? 0) + 1);
    }
    const top = [...m].sort((p, q) => q[1] - p[1]).slice(0, 6).map(([k, c]) => `#${[(k >> 16) & 63, (k >> 8) & 63, k & 63].map((v) => (v * 4).toString(16).padStart(2, '0')).join('')}（${c} 画素）`);
    console.log(`${id} 領域 (${x},${y},${w},${h}) の代表色（4 刻みに丸めた値）: ${top.join(' ')}`);
  }
  if (linesMode) {
    const white = (xx, yy) => { const i = at(xx, yy); return data[i] > 235 && data[i + 1] > 235 && data[i + 2] > 235; };
    const hs = [], vs = [];
    for (let yy = 0; yy < H; yy++) { let run = 0, best = 0, bs = 0, rs = 0; for (let xx = 0; xx < W; xx++) { if (white(xx, yy)) { if (!run) rs = xx; run++; if (run > best) { best = run; bs = rs; } } else run = 0; } if (best >= minLen) hs.push(`y${yy + cy}:x${bs + cx}-${bs + cx + best}`); }
    for (let xx = 0; xx < W; xx++) { let run = 0, best = 0, bs = 0, rs = 0; for (let yy = 0; yy < H; yy++) { if (white(xx, yy)) { if (!run) rs = yy; run++; if (run > best) { best = run; bs = rs; } } else run = 0; } if (best >= minLen) vs.push(`x${xx + cx}:y${bs + cy}-${bs + cy + best}`); }
    console.log(`${id} 領域内の白い罫線（${minLen} px 以上。太さの分だけ連続した座標が並ぶ）`);
    console.log('横線:', hs.join(' ') || 'なし');
    console.log('縦線:', vs.join(' ') || 'なし');
  }
  if (darkMode) {
    const cols = [];
    let miny = Infinity, maxy = -1;
    for (let xx = 0; xx < W; xx++) { let any = false; for (let yy = 0; yy < H; yy++) { const i = at(xx, yy); if (data[i] + data[i + 1] + data[i + 2] < 250) { any = true; miny = Math.min(miny, yy); maxy = Math.max(maxy, yy); } } cols.push(any); }
    const runs = []; let st = -1;
    cols.forEach((c, i) => { if (c && st < 0) st = i; if (!c && st >= 0) { runs.push([st + cx, i - 1 + cx]); st = -1; } });
    if (st >= 0) runs.push([st + cx, cols.length - 1 + cx]);
    const merged = [];
    for (const r of runs) { if (merged.length && r[0] - merged[merged.length - 1][1] < 20) merged[merged.length - 1][1] = r[1]; else merged.push([...r]); }
    console.log(`${id} 暗い画素（R+G+B が 250 未満）: y ${miny + cy}〜${maxy + cy}、左右の塊（隙間 20 px 未満は結合）: ${merged.map((r) => r.join('〜')).join('  ') || 'なし'}`);
  }
  process.exit(0);
}

if (bboxMode) {
  const { data, info } = await sharp(file).extract({ left: cx, top: cy, width: cw, height: ch }).removeAlpha().raw().toBuffer({ resolveWithObject: true });
  // 地（暗い縞の色）でも灰・色付きの単色箱でもない画素 = 前景。ここでは「明るさが地より十分高い、または地と色相が違う」画素を前景とする
  const rows = [];
  for (let yy = 0; yy < info.height; yy++) {
    let min = Infinity, max = -1;
    for (let xx = 0; xx < info.width; xx++) {
      const i = (yy * info.width + xx) * 3;
      const r = data[i], g = data[i + 1], b = data[i + 2];
      if (r > 150 || g > 150 || b > 150) { if (xx < min) min = xx; if (xx > max) max = xx; }
    }
    rows.push(max >= 0 ? [min + cx, max + cx] : null);
  }
  let start = -1;
  const out = [];
  const flush = (end) => {
    const seg = rows.slice(start, end);
    const xs = seg.filter(Boolean);
    out.push(`y ${start + cy}〜${end - 1 + cy}（高さ ${end - start}）  x ${Math.min(...xs.map((r) => r[0]))}〜${Math.max(...xs.map((r) => r[1]))}`);
  };
  for (let yy = 0; yy <= rows.length; yy++) {
    const on = yy < rows.length && rows[yy];
    if (on && start < 0) start = yy;
    if (!on && start >= 0) { flush(yy); start = -1; }
  }
  console.log(`${id} 領域 (${x},${y},${w},${h}) の明るい画素（R/G/B のどれかが 150 超）の行ごとの範囲:`);
  console.log(out.join('\n'));
  process.exit(0);
}

const out = path.resolve(outOpt ?? path.join(root, `build/rulebook-en/tmp/crop-${id}-${x}-${y}.png`));
fs.mkdirSync(path.dirname(out), { recursive: true });
const zw = Math.round(cw * zoom), zh = Math.round(ch * zoom);
const base = await sharp(file).extract({ left: cx, top: cy, width: cw, height: ch }).resize({ width: zw, height: zh, kernel: 'lanczos3' }).png().toBuffer();
// 目盛り（grid px ごとの細線と座標の数字）を SVG で重ねる
const lines = [];
const g0x = Math.ceil(cx / grid) * grid, g0y = Math.ceil(cy / grid) * grid;
for (let gx = g0x; gx < cx + cw; gx += grid) {
  const px = (gx - cx) * zoom;
  lines.push(`<line x1="${px}" y1="0" x2="${px}" y2="${zh}" stroke="#ff0" stroke-opacity="${gx % (grid * 2) === 0 ? 0.5 : 0.25}" stroke-width="1"/>`);
  if (gx % (grid * 2) === 0) lines.push(`<text x="${px + 2}" y="12" font-size="12" font-family="monospace" fill="#ff0" stroke="#000" stroke-width="0.4">${gx}</text>`);
}
for (let gy = g0y; gy < cy + ch; gy += grid) {
  const py = (gy - cy) * zoom;
  lines.push(`<line x1="0" y1="${py}" x2="${zw}" y2="${py}" stroke="#0ff" stroke-opacity="${gy % (grid * 2) === 0 ? 0.5 : 0.25}" stroke-width="1"/>`);
  if (gy % (grid * 2) === 0) lines.push(`<text x="2" y="${py - 2}" font-size="12" font-family="monospace" fill="#0ff" stroke="#000" stroke-width="0.4">${gy}</text>`);
}
const svg = Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="${zw}" height="${zh}">${lines.join('')}</svg>`);
await sharp(base).composite([{ input: svg }]).png().toFile(out);
console.log(out);
