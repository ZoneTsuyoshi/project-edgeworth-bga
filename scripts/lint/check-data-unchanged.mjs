// R1: data/（原本）の変更禁止の検査（仕様書 005 §2.11）。外部依存なし。実行: npm run lint:data
// 基準コミットの決め方:
//   - 引数 --base <sha> または環境変数 BASE_SHA（CI。PR は base.sha、push は before）
//   - どちらも無い場合（ローカル）: origin/main との merge-base
// 基準が全桁 0（新規ブランチの push）のとき、または CI_SKIP が 1 のときは検査しない。
// A（追加）は許可し、M（変更）・D（削除）・T（種別変更）は失敗にする。名前の変更は D + A として見えるので失敗になる。
import { execFileSync } from 'node:child_process';

// 作者が承知のうえで原本を差し替えるときに、パスと理由（日付）を足す PR を出す。作者の承認を経てマージされる記録。
// 例: ['data/cards/projects/P001.png', '理由（2026-01-01）']
const ALLOWED_CHANGES = new Map([]);

const git = (args) => execFileSync('git', args, { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });

const argIndex = process.argv.indexOf('--base');
let base = (argIndex >= 0 ? process.argv[argIndex + 1] : '') || process.env.BASE_SHA || '';

if (process.env.CI_SKIP === '1') {
  console.log('check-data-unchanged: スキップ（CI_SKIP=1）');
  process.exit(0);
}
if (base && /^0+$/.test(base)) {
  console.log('check-data-unchanged: 基準が無いため検査しません（新規ブランチの push）');
  process.exit(0);
}
if (!base) {
  try {
    base = git(['merge-base', 'origin/main', 'HEAD']).trim();
  } catch {
    console.error('check-data-unchanged: origin/main が見つかりません。git fetch origin を実行してください。');
    process.exit(1);
  }
}

// -z で読む（パス名に日本語・空白を含むため）。--no-renames で移動を D + A として見る
const out = git(['diff', '--name-status', '-z', '--no-renames', base, 'HEAD', '--', 'data/']);
const tokens = out.split('\0').filter(Boolean);
const changes = [];
for (let i = 0; i + 1 < tokens.length; i += 2) {
  changes.push({ status: tokens[i], file: tokens[i + 1] });
}

const violations = changes.filter((c) => c.status !== 'A' && !ALLOWED_CHANGES.has(c.file));
if (violations.length > 0) {
  console.error('check-data-unchanged: data/ の原本が変更されています。');
  for (const v of violations) console.error(`  ${v.status} ${v.file}`);
  process.exit(1);
}
const added = changes.filter((c) => c.status === 'A').length;
console.log(`check-data-unchanged: OK（基準 ${base.slice(0, 7)}、data/ の追加 ${added} 件、変更・削除なし）`);
