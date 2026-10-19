// スプライト生成物の検査（仕様書 012 第 7.5 節、005 第 2.9 節の A1）。生成はしない。
// 使い方: npm run check:sprites [-- --out <dir>] [--manifest <path>] [--js <path>]
import { readdir, readFile, stat } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';
import {
  SHEETS, PIECES_STEM, EFFECT_STRIP, PROJECT_KEYS, MISC_KEYS, REWARD_KEYS, ROCKET_KEYS, TILE_KEYS,
  cardPosition, sheetFile, sheetSize, pieceCells, piecesSize,
} from './lib/sprite-layout.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const MAX_FILE_BYTES = 2_000_000; // 1 ファイル 2 MB（005 §2.9、004 §13）
const MAX_TOTAL_BYTES = 15_000_000; // 総量 15 MB
const WARN_TOTAL_BYTES = 10_000_000; // 目標 10 MB（012 §7.2）
const MAX_DIM = 4096;
const WARN_DIRECT_FILE_COUNT = 10; // img/ 直下が 10 個以上で警告（img/README）
const NAME_RE = /^pe_[a-z0-9_]+\.webp$/; // 空白・括弧・大文字・他の拡張子を禁止
const IMAGE_EXT_RE = /\.(webp|png|jpe?g|gif|svg)$/i;

async function listFiles(dir, base = dir) {
  const out = [];
  let entries;
  try {
    entries = await readdir(dir, { withFileTypes: true });
  } catch {
    return out;
  }
  for (const e of entries) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) out.push(...(await listFiles(p, base)));
    else out.push(path.relative(base, p).split(path.sep).join('/'));
  }
  return out.sort();
}

const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

export async function checkSprites({ out, manifest, js }) {
  const errors = [];
  const warnings = [];
  const rows = [];

  let m = null;
  try {
    m = JSON.parse(await readFile(manifest, 'utf8'));
  } catch (e) {
    errors.push(`マニフェストを読めません: ${path.relative(ROOT, manifest)}（${e.message}）`);
  }
  const zoomDir = m?.zoomDir ?? 'zoom/';
  if (zoomDir !== '' && zoomDir !== 'zoom/') errors.push(`manifest.zoomDir が '' か 'zoom/' ではありません: ${zoomDir}`);

  // ファイルの存在・余計な画像・ファイル名
  const expected = [...SHEETS.map((s) => sheetFile(s, zoomDir)), `${PIECES_STEM}.webp`];
  const present = (await listFiles(out)).filter((f) => IMAGE_EXT_RE.test(f));
  for (const f of expected) if (!present.includes(f)) errors.push(`ファイルがありません: ${f}`);
  for (const f of present) {
    const base = f.split('/').pop();
    if (!NAME_RE.test(base)) {
      // 手書き画像は他の命名もありうるが、規則に合わない名前は BGA の制約（空白・括弧など）に触れうるので、使える文字だけ確かめる
      if (!/^[A-Za-z0-9_.-]+$/.test(base)) errors.push(`ファイル名に使えない文字があります: ${f}`);
      else if (/^pe_/.test(base)) errors.push(`ファイル名が規則 ${NAME_RE} に合いません: ${f}`);
      else warnings.push(`想定外の画像があります（手書き画像なら問題なし）: ${f}`);
    } else if (!expected.includes(f)) {
      errors.push(`余計な生成物があります: ${f}`);
    }
  }

  // 容量・寸法
  const dims = new Map();
  let total = 0;
  for (const f of present) {
    const size = (await stat(path.join(out, f))).size;
    total += size;
    let w = null;
    let h = null;
    try {
      const meta = await sharp(path.join(out, f)).metadata();
      w = meta.width;
      h = meta.height;
    } catch (e) {
      errors.push(`画像を読めません: ${f}（${e.message}）`);
    }
    if (size > MAX_FILE_BYTES) errors.push(`${f}: ${size} バイトが 1 ファイルの上限 ${MAX_FILE_BYTES} を超えています`);
    if (w > MAX_DIM || h > MAX_DIM) errors.push(`${f}: ${w}x${h} が ${MAX_DIM}px を超えています`);
    dims.set(f, { w, h });
    rows.push({ f, w, h, size });
  }
  if (total > MAX_TOTAL_BYTES) errors.push(`総量 ${total} バイトが上限 ${MAX_TOTAL_BYTES} を超えています`);
  else if (total > WARN_TOTAL_BYTES) warnings.push(`総量 ${total} バイトが目標 ${WARN_TOTAL_BYTES} を超えています`);
  const direct = present.filter((f) => !f.includes('/'));
  if (direct.length >= WARN_DIRECT_FILE_COUNT) warnings.push(`img/ 直下に ${direct.length} 個のファイルがあります（BGA の目安は 10 個未満）`);
  for (const f of present) {
    const depth = f.split('/').length - 1;
    if (depth > 1 || (depth === 1 && !f.startsWith('zoom/'))) errors.push(`想定外のサブフォルダです（zoom/ のみ）: ${f}`);
  }

  // マニフェストの整合
  if (m) {
    if (m.version !== 1) errors.push(`manifest.version が 1 ではありません: ${m.version}`);
    if (!same(m.effects?.crop, EFFECT_STRIP.crop) || !same(m.effects?.cell, EFFECT_STRIP.cell)) errors.push('manifest.effects が規則（sprite-layout）と違います');
    for (const s of SHEETS) {
      const ms = m.sheets?.[s.stem];
      if (!ms) { errors.push(`manifest.sheets.${s.stem} がありません`); continue; }
      const size = sheetSize(s);
      const file = sheetFile(s, zoomDir);
      if (ms.file !== file || ms.cols !== s.cols || ms.rows !== s.rows || ms.w !== size.w || ms.h !== size.h || !same(ms.cell, s.cell)
        || ms.count !== s.keys.length || ms.tier !== s.tier) {
        errors.push(`manifest.sheets.${s.stem} が規則と違います`);
      }
      if (s.keys.length > s.cols * s.rows) errors.push(`${s.stem}: キー ${s.keys.length} 個がセル ${s.cols * s.rows} 個に収まりません`);
      const d = dims.get(file);
      if (d && (d.w !== size.w || d.h !== size.h)) errors.push(`${file}: 実寸 ${d.w}x${d.h} が規則 ${size.w}x${size.h} と一致しません`);
    }
    const cardKeys = [...PROJECT_KEYS, ...MISC_KEYS, ...REWARD_KEYS, ...ROCKET_KEYS];
    const mkeys = new Set(Object.keys(m.cards ?? {}));
    if (mkeys.size !== cardKeys.length || cardKeys.some((k) => !mkeys.has(k))) {
      errors.push(`manifest.cards のキー（${cardKeys.length} 個）が規則と一致しません`);
    }
    const seen = new Set();
    const mark = (tier, p, key) => {
      const id = `${tier}/${p.sheet}/${p.col}/${p.row}`;
      if (seen.has(id)) errors.push(`セルが重複しています: ${id}（${key}）`);
      seen.add(id);
    };
    for (const key of cardKeys) {
      const tiers = PROJECT_KEYS.includes(key) ? ['base', 'zoom', 'effects'] : ['base', 'zoom'];
      for (const tier of tiers) {
        const exp = cardPosition(key, tier);
        const got = m.cards?.[key]?.[tier];
        if (!same(got, exp)) { errors.push(`manifest.cards.${key}.${tier} が並び順の規則（sprite-layout）と一致しません`); continue; }
        const sheet = SHEETS.find((s) => s.stem === got.sheet);
        if (!sheet || got.col < 0 || got.col >= sheet.cols || got.row < 0 || got.row >= sheet.rows) {
          errors.push(`manifest.cards.${key}.${tier} がシートの範囲外です`);
        }
        mark(tier, got, key);
      }
    }
    for (const key of TILE_KEYS) {
      if (!same(m.tiles?.[key], cardPosition(key, 'base'))) errors.push(`manifest.tiles.${key} が規則と一致しません`);
    }
    // コマ・アイコン
    const mp = m.pieces;
    const psize = piecesSize();
    if (!mp) errors.push('manifest.pieces がありません');
    else {
      if (mp.sheet?.file !== `${PIECES_STEM}.webp` || mp.sheet?.w !== psize.w || mp.sheet?.h !== psize.h) errors.push('manifest.pieces.sheet が規則と違います');
      const d = dims.get(`${PIECES_STEM}.webp`);
      if (d && (d.w !== psize.w || d.h !== psize.h)) errors.push(`${PIECES_STEM}.webp: 実寸 ${d.w}x${d.h} が規則 ${psize.w}x${psize.h} と一致しません`);
      const cells = pieceCells();
      if (Object.keys(mp.items ?? {}).length !== cells.length) errors.push(`manifest.pieces.items の数が規則（${cells.length}）と違います`);
      for (const c of cells) {
        const it = mp.items?.[c.name];
        if (!it) { errors.push(`manifest.pieces.items.${c.name} がありません`); continue; }
        if (!same(it.cell, { x: c.x, y: c.y, w: c.w, h: c.h })) errors.push(`pieces.${c.name}.cell が規則と違います`);
        const im = it.image;
        if (im.x < c.x || im.y < c.y || im.x + im.w > c.x + c.w || im.y + im.h > c.y + c.h) errors.push(`pieces.${c.name} の画像がセルからはみ出しています`);
      }
    }
  }

  // JS の配置情報（src/modules/js/cards/sprites.js）がマニフェストと同じ内容であること
  if (js && m) {
    try {
      const text = await readFile(js, 'utf8');
      const body = text.slice(text.indexOf('export const SPRITES = ') + 'export const SPRITES = '.length).replace(/;\s*$/, '');
      if (!same(JSON.parse(body), m)) errors.push(`${path.relative(ROOT, js)} の内容がマニフェストと一致しません`);
    } catch (e) {
      errors.push(`JS の配置情報を読めません: ${path.relative(ROOT, js)}（${e.message}）`);
    }
  }

  for (const r of rows) console.log(`  ${r.f}  ${r.w}x${r.h}  ${r.size} バイト`);
  console.log(`  合計 ${total} バイト（${present.length} ファイル）`);
  for (const w of warnings) console.warn(`check-sprites: 警告: ${w}`);
  for (const e of errors) console.error(`check-sprites: エラー: ${e}`);
  if (errors.length) {
    console.error(`check-sprites: 失敗（エラー ${errors.length} 件）`);
    return false;
  }
  console.log(`check-sprites: OK（警告 ${warnings.length} 件）`);
  return true;
}

function parseArgs(argv) {
  const opts = {
    out: path.join(ROOT, 'src/img'),
    manifest: path.join(ROOT, 'build/sprites/manifest.json'),
    js: path.join(ROOT, 'src/modules/js/cards/sprites.js'),
  };
  for (let i = 0; i < argv.length; i++) {
    if (argv[i] === '--out') opts.out = path.resolve(argv[++i]);
    else if (argv[i] === '--manifest') opts.manifest = path.resolve(argv[++i]);
    else if (argv[i] === '--js') opts.js = path.resolve(argv[++i]);
    else throw new Error(`不明なオプション: ${argv[i]}`);
  }
  return opts;
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  checkSprites(parseArgs(process.argv.slice(2)))
    .then((ok) => process.exit(ok ? 0 : 1))
    .catch((e) => {
      console.error(`check-sprites: ${e.message}`);
      process.exit(1);
    });
}
