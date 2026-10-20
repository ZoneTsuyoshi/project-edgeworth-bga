// data/ の原本から BGA 用の webp スプライトと配置情報を生成する（仕様書 012 第 7 節）。
// 使い方: npm run build:sprites [-- --out <dir>] [--manifest <path>] [--js <path>] [--quality <n>] [--flat-zoom] [--verify-determinism]
// 出力: src/img/pe_*.webp、src/img/zoom/pe_zoom_*.webp、build/sprites/manifest.json、src/modules/js/cards/sprites.js（いずれも .gitignore 対象）。
// data/ は読み取りのみ。
import { createHash } from 'node:crypto';
import { mkdir, mkdtemp, readdir, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';
import {
  SHEETS, PIECES_STEM, EFFECT_STRIP, DEFAULT_ZOOM_DIR,
  cardSource, cardPosition, sheetFile, sheetSize, pieceCells, piecesSize,
  PROJECT_KEYS, MISC_KEYS, REWARD_KEYS, ROCKET_KEYS, TILE_KEYS,
} from './lib/sprite-layout.mjs';
import { checkSprites } from './check-sprites.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const DATA = path.join(ROOT, 'data');
const DEFAULT_QUALITY = 80;

function parseArgs(argv) {
  const opts = {
    out: path.join(ROOT, 'src/img'),
    manifest: path.join(ROOT, 'build/sprites/manifest.json'),
    js: path.join(ROOT, 'src/modules/js/cards/sprites.js'),
    quality: DEFAULT_QUALITY,
    zoomDir: DEFAULT_ZOOM_DIR,
    verifyDeterminism: false,
  };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a === '--out') opts.out = path.resolve(argv[++i]);
    else if (a === '--manifest') opts.manifest = path.resolve(argv[++i]);
    else if (a === '--js') opts.js = path.resolve(argv[++i]);
    else if (a === '--quality') opts.quality = Number(argv[++i]);
    else if (a === '--flat-zoom') opts.zoomDir = '';
    else if (a === '--verify-determinism') opts.verifyDeterminism = true;
    else throw new Error(`不明なオプション: ${a}`);
  }
  if (!Number.isInteger(opts.quality) || opts.quality < 1 || opts.quality > 100) {
    throw new Error('--quality は 1〜100 の整数で指定してください');
  }
  return opts;
}

// ---- 入力の検査（012 §7.5） ----

/** キーの種類ごとの寸法の許容範囲（原本の実測。012 §1） */
function expectedRange(key) {
  if (TILE_KEYS.includes(key)) return { w: [435, 436], h: [502, 503] };
  if (ROCKET_KEYS.includes(key)) return { w: [998, 999], h: [715, 715] };
  return { w: [715, 716], h: [998, 999] };
}

async function readInputs(keys) {
  const inputs = new Map();
  for (const key of keys) {
    const file = path.join(DATA, cardSource(key));
    let meta;
    try {
      meta = await sharp(file).metadata();
    } catch {
      throw new Error(`原本がありません、または読めません: data/${cardSource(key)}（${key}）`);
    }
    if (meta.channels !== 4 || !meta.hasAlpha) throw new Error(`${cardSource(key)} が RGBA ではありません`);
    const r = expectedRange(key);
    if (meta.width < r.w[0] || meta.width > r.w[1] || meta.height < r.h[0] || meta.height > r.h[1]) {
      throw new Error(`${cardSource(key)} の寸法が範囲外です: ${meta.width}x${meta.height}（想定 ${r.w.join('〜')} x ${r.h.join('〜')}）`);
    }
    inputs.set(key, file);
  }
  return inputs;
}

async function readPieceInputs(cells) {
  for (const c of cells) {
    const file = path.join(DATA, c.src);
    let meta;
    try {
      meta = await sharp(file).metadata();
    } catch {
      throw new Error(`原本がありません、または読めません: data/${c.src}（${c.name}）`);
    }
    if (!meta.hasAlpha) throw new Error(`${c.src} が RGBA ではありません`);
    if (!c.resize && (meta.width > c.w || meta.height > c.h)) {
      throw new Error(`${c.name} の原本 ${meta.width}x${meta.height} がセル ${c.w}x${c.h} に収まりません`);
    }
  }
}

// ---- 生成 ----

const canvas = (w, h) => ({ create: { width: w, height: h, channels: 4, background: { r: 0, g: 0, b: 0, alpha: 0 } } });
const rawOf = (data, info) => ({ input: data, raw: { width: info.width, height: info.height, channels: 4 } });

/** 効果アイコンの帯の左右と下の外周 2 px に明るい画素がないこと（枠の縦線・下線が欠けていないこと）を確かめる。上端と上 24 px の右側は文章欄の枠線が入るので見ない */
function ringIsClear(data, w, h) {
  const bright = (x, y) => {
    const o = (y * w + x) * 4;
    return data[o + 3] > 200 && Math.max(data[o], data[o + 1], data[o + 2]) > 140;
  };
  for (let t = 0; t < 2; t++) {
    for (let x = 0; x < w; x++) if (bright(x, h - 1 - t)) return false;
    for (let y = 24; y < h; y++) if (bright(t, y) || bright(w - 1 - t, y)) return false;
  }
  return true;
}

async function renderCell(sheet, key, file) {
  const { w, h } = sheet.cell;
  if (sheet.mode === 'fill') {
    return sharp(file).resize(w, h, { fit: 'fill', kernel: 'lanczos3' }).raw().toBuffer({ resolveWithObject: true });
  }
  if (sheet.mode === 'strip') {
    const adj = EFFECT_STRIP.adjust[key] ?? { dx: 0, dy: 0 };
    const c = { ...EFFECT_STRIP.crop, left: EFFECT_STRIP.crop.left + adj.dx, top: EFFECT_STRIP.crop.top + adj.dy };
    const cropped = await sharp(file).extract(c).raw().toBuffer({ resolveWithObject: true });
    if (!ringIsClear(cropped.data, cropped.info.width, cropped.info.height)) {
      throw new Error(`効果アイコンの帯の切り出し範囲が ${key} の枠からはみ出しています（EFFECT_STRIP.adjust に補正を足してください）`);
    }
    return sharp(cropped.data, { raw: { width: c.width, height: c.height, channels: 4 } })
      .resize(w, h, { fit: 'fill', kernel: 'lanczos3' }).raw().toBuffer({ resolveWithObject: true });
  }
  // paste: 実寸のまま中央寄せ（寸法が 1 px ずれる原本があるため切り出しはしない）
  const { data, info } = await sharp(file).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
  const out = await sharp(canvas(w, h))
    .composite([{ ...rawOf(data, info), left: Math.floor((w - info.width) / 2), top: Math.floor((h - info.height) / 2) }])
    .raw().toBuffer({ resolveWithObject: true });
  return out;
}

function webpOptions(lossless, quality) {
  return lossless ? { lossless: true, effort: 6 } : { quality, effort: 6, alphaQuality: 100, smartSubsample: true };
}

async function buildSheet(sheet, inputs, outDir, zoomDir, quality) {
  const size = sheetSize(sheet);
  // セルの縮小は互いに独立なので並行して行う（sharp は libuv のスレッドで処理する）。結果は配列の順＝セルの順
  const composites = await Promise.all(sheet.keys.map(async (key, i) => {
    const { data, info } = await renderCell(sheet, key, inputs.get(key));
    return { ...rawOf(data, info), left: (i % sheet.cols) * sheet.cell.w, top: Math.floor(i / sheet.cols) * sheet.cell.h };
  }));
  const dest = path.join(outDir, sheetFile(sheet, zoomDir));
  await mkdir(path.dirname(dest), { recursive: true });
  await sharp(canvas(size.w, size.h)).composite(composites).webp(webpOptions(sheet.lossless, quality)).toFile(dest);
  return {
    file: sheetFile(sheet, zoomDir), tier: sheet.tier, w: size.w, h: size.h, cols: sheet.cols, rows: sheet.rows,
    cell: { ...sheet.cell }, lossless: Boolean(sheet.lossless), count: sheet.keys.length,
  };
}

async function buildPieces(outDir) {
  const cells = pieceCells();
  const size = piecesSize();
  const composites = [];
  const items = {};
  for (const c of cells) {
    let img = sharp(path.join(DATA, c.src)).ensureAlpha();
    if (c.resize) img = img.resize(c.w, c.h, { fit: 'inside', kernel: 'lanczos3' });
    const { data, info } = await img.raw().toBuffer({ resolveWithObject: true });
    const left = c.x + Math.floor((c.w - info.width) / 2);
    const top = c.y + Math.floor((c.h - info.height) / 2);
    composites.push({ ...rawOf(data, info), left, top });
    items[c.name] = { cell: { x: c.x, y: c.y, w: c.w, h: c.h }, image: { x: left, y: top, w: info.width, h: info.height } };
  }
  const file = `${PIECES_STEM}.webp`;
  await mkdir(outDir, { recursive: true });
  await sharp(canvas(size.w, size.h)).composite(composites).webp({ lossless: true, effort: 6 }).toFile(path.join(outDir, file));
  return { sheet: { file, w: size.w, h: size.h }, items };
}

export async function buildAll({ out, manifest, js, quality, zoomDir }) {
  const keys = [...PROJECT_KEYS, ...MISC_KEYS, ...REWARD_KEYS, ...ROCKET_KEYS, ...TILE_KEYS];
  const inputs = await readInputs(keys);
  await readPieceInputs(pieceCells());

  const sheets = {};
  for (const sheet of SHEETS) sheets[sheet.stem] = await buildSheet(sheet, inputs, out, zoomDir, quality);

  const cards = {};
  for (const key of keys) {
    if (TILE_KEYS.includes(key)) continue;
    cards[key] = { base: cardPosition(key, 'base'), zoom: cardPosition(key, 'zoom') };
    if (PROJECT_KEYS.includes(key)) cards[key].effects = cardPosition(key, 'effects');
  }
  const tiles = Object.fromEntries(TILE_KEYS.map((k) => [k, cardPosition(k, 'base')]));
  const pieces = await buildPieces(out);

  const doc = {
    version: 1,
    zoomDir,
    options: { quality },
    effects: { crop: EFFECT_STRIP.crop, cell: EFFECT_STRIP.cell, adjust: EFFECT_STRIP.adjust },
    sheets,
    cards,
    tiles,
    pieces,
  };
  await mkdir(path.dirname(manifest), { recursive: true });
  await writeFile(manifest, `${JSON.stringify(doc, null, 2)}\n`);
  if (js) {
    await mkdir(path.dirname(js), { recursive: true });
    await writeFile(js, [
      '// 自動生成: scripts/build-sprites.mjs（npm run build:sprites）。編集しない。.gitignore 対象で、CI とローカルで生成する。',
      '// 位置の読み方: カード = SPRITES.cards[key].base / .zoom / .effects の { sheet, col, row } と SPRITES.sheets[sheet] の cols・rows・cell・file。',
      `export const SPRITES = ${JSON.stringify(doc, null, 2)};`,
      '',
    ].join('\n'));
  }
  return doc;
}

async function hashTree(dir) {
  const result = new Map();
  async function walk(d) {
    for (const e of (await readdir(d, { withFileTypes: true })).sort((a, b) => (a.name < b.name ? -1 : 1))) {
      const p = path.join(d, e.name);
      if (e.isDirectory()) await walk(p);
      else result.set(path.relative(dir, p), createHash('sha256').update(await readFile(p)).digest('hex'));
    }
  }
  await walk(dir);
  return result;
}

async function main() {
  const opts = parseArgs(process.argv.slice(2));
  const t0 = Date.now();
  await buildAll(opts);
  console.log(`build-sprites: 生成完了（${((Date.now() - t0) / 1000).toFixed(1)} 秒）→ ${path.relative(ROOT, opts.out)}`);
  if (!(await checkSprites({ out: opts.out, manifest: opts.manifest, js: opts.js }))) process.exit(1);

  if (opts.verifyDeterminism) {
    const tmp = await mkdtemp(path.join(tmpdir(), 'pe-sprites-'));
    try {
      await buildAll({ ...opts, out: tmp, manifest: path.join(tmp, 'manifest.json'), js: path.join(tmp, 'sprites.js') });
      const a = await hashTree(opts.out);
      const b = new Map([...(await hashTree(tmp))].filter(([k]) => k !== 'manifest.json' && k !== 'sprites.js'));
      const bad = [...b].filter(([k, h]) => a.get(k) !== h).map(([k]) => k);
      if (bad.length) {
        console.error(`build-sprites: 決定性の検査に失敗（2 回の出力が一致しません）: ${bad.join(', ')}`);
        process.exit(1);
      }
      console.log(`build-sprites: 決定性 OK（${b.size} ファイルが 2 回の生成で一致）`);
    } finally {
      await rm(tmp, { recursive: true, force: true });
    }
  }
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  main().catch((e) => {
    console.error(`build-sprites: ${e.message}`);
    process.exit(1);
  });
}
