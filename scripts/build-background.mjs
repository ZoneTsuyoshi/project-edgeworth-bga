// data/ の原本からテーマの背景画像 src/img/pe_background.webp を生成する（仕様書 012 追補 U-3）。
// 素材: プロジェクトカード裏面 PREV の回路の線のうち、文字と円の無い上部の帯。上下左右に反転した継ぎ目の無いタイルにし、
// 線の明るさを不透明度に変え（地は透明）、線の色を一定の青緑にして、ぼかす。出力は決定的（同じ入力から同じバイト列）。
// 使い方: npm run build:background [-- --out <dir>]（既定の出力先は src/img）。data/ は読み取りのみ。
import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
export const BACKGROUND_FILE = 'pe_background.webp';
const SOURCE = path.join(ROOT, 'data/cards/projects/PREV@4x.png');
const CROP = { left: 24, top: 16, height: 230 }; // 角の透過を避けて内側から、文字の無い上部 230 px
const LINE_COLOR = { r: 0x4a, g: 0xa3, b: 0xc8 };

export async function buildBackground(outFile) {
  const meta = await sharp(SOURCE).metadata().catch(() => {
    throw new Error('原本がありません、または読めません: data/cards/projects/PREV@4x.png');
  });
  const top = await sharp(SOURCE)
    .extract({ left: CROP.left, top: CROP.top, width: meta.width - CROP.left * 2, height: CROP.height })
    .png().toBuffer();
  const { width: w, height: h } = await sharp(top).metadata();
  const flop = await sharp(top).flop().toBuffer();
  const flip = await sharp(top).flip().toBuffer();
  const both = await sharp(top).flip().flop().toBuffer();
  const tile = await sharp({ create: { width: w * 2, height: h * 2, channels: 4, background: { r: 0, g: 0, b: 0, alpha: 1 } } })
    .composite([{ input: top, left: 0, top: 0 }, { input: flop, left: w, top: 0 }, { input: flip, left: 0, top: h }, { input: both, left: w, top: h }])
    .png().toBuffer();
  const { data, info } = await sharp(tile).greyscale().blur(2.5).raw().toBuffer({ resolveWithObject: true });
  const px = info.width * info.height;
  const rgba = Buffer.alloc(px * 4);
  for (let i = 0; i < px; i++) {
    const a = Math.max(0, Math.min(255, (data[i * info.channels] - 18) * 1.6));
    rgba[i * 4] = LINE_COLOR.r;
    rgba[i * 4 + 1] = LINE_COLOR.g;
    rgba[i * 4 + 2] = LINE_COLOR.b;
    rgba[i * 4 + 3] = Math.round(a * 0.55);
  }
  await mkdir(path.dirname(outFile), { recursive: true });
  await sharp(rgba, { raw: { width: info.width, height: info.height, channels: 4 } }).webp({ quality: 70, alphaQuality: 60 }).toFile(outFile);
  const o = await sharp(outFile).metadata();
  return { width: o.width, height: o.height };
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  let outDir = path.join(ROOT, 'src/img');
  const args = process.argv.slice(2);
  for (let i = 0; i < args.length; i++) {
    if (args[i] === '--out') outDir = path.resolve(args[++i]);
    else { console.error(`build-background: 不明なオプション: ${args[i]}`); process.exit(1); }
  }
  buildBackground(path.join(outDir, BACKGROUND_FILE))
    .then((d) => console.log(`build-background: 生成完了 ${d.width}x${d.height} → ${path.relative(ROOT, path.join(outDir, BACKGROUND_FILE))}`))
    .catch((e) => { console.error(`build-background: ${e.message}`); process.exit(1); });
}
