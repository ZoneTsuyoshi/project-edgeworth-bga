// S2: 禁止ファイル・src/ 衛生の検査（仕様書 005 §2.4）。
// 外部依存なし。git ls-files の出力を検査する。実行: npm run lint:repo
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const files = execFileSync('git', ['ls-files', '-z'], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 })
  .split('\0')
  .filter(Boolean);

const errors = [];
const fail = (file, message) => errors.push(`${file}: ${message}`);

// 1. 認証情報などの禁止ファイル（.gitignore と同じ集合）
const forbiddenGlobal = [
  { test: (b) => b === '.env' || (b.startsWith('.env.') && b !== '.env.example'), label: '.env 系ファイル' },
  { test: (b) => b === 'sftp.json', label: 'sftp.json' },
  { test: (b) => b.endsWith('.pem'), label: '*.pem' },
  { test: (b) => b.startsWith('id_rsa'), label: 'id_rsa*' },
  { test: (b) => b.startsWith('id_ed25519'), label: 'id_ed25519*' },
];
for (const file of files) {
  const base = path.posix.basename(file);
  for (const rule of forbiddenGlobal) {
    if (rule.test(base)) fail(file, `コミット禁止のファイルです（${rule.label}）`);
  }
}

// 6. BGA 配布のスタブはリポジトリのどこにも追跡しない（仕様書 004 §11。.gitignore だけでは git add -f を防げない）
const stubFiles = ['_ide_helper.php', 'bga-framework.d.ts'];
for (const file of files) {
  const base = path.posix.basename(file);
  if (stubFiles.includes(base)) fail(file, 'BGA 配布のスタブはコミットできません（再配布不可）');
}

// 3. data/（原本画像）は作者の手元だけに置き、リポジトリに入れない（2026-10-20 リポジトリ公開に伴う。仕様書 005）
for (const file of files) {
  if (file === 'data' || file.startsWith('data/')) fail(file, 'data/（原本）はコミットできません（作者の手元だけに置く）');
}

// 2. src/ 配下に置いてはいけない開発用ファイル
const devFile = [
  { test: (b) => b === 'package.json' || b === 'package-lock.json', label: 'package.json 系' },
  { test: (b) => b === 'composer.json' || b === 'composer.lock', label: 'composer 系' },
  { test: (b) => /^tsconfig.*\.json$/.test(b), label: 'tsconfig*.json' },
  { test: (b) => /^eslint\.config\./.test(b), label: 'eslint.config.*' },
  { test: (b) => /^stylelint\.config\./.test(b), label: 'stylelint.config.*' },
  { test: (b) => /^\..*rc/.test(b), label: '.*rc* 設定ファイル' },
  { test: (b) => /^phpcs\.xml/.test(b), label: 'phpcs.xml*' },
  { test: (b) => /^phpstan\.neon/.test(b), label: 'phpstan.neon*' },
  { test: (b) => /\.(test|spec)\./.test(b), label: '*.test.* / *.spec.*' },
];
const devDirs = ['node_modules', 'tests'];

// 5. src/ に置かない BGA の非推奨ファイル（仕様書 004 §3。Studio の初期ファイルの誤取り込みを防ぐ）
const deprecatedFile = [
  { test: (b) => b === 'states.inc.php' || b === 'material.inc.php', label: 'states.inc.php / material.inc.php' },
  { test: (b) => /\.action\.php$/.test(b), label: '*.action.php' },
  { test: (b) => /\.view\.php$/.test(b), label: '*.view.php' },
  { test: (b) => /\.tpl$/.test(b), label: '*.tpl' },
  { test: (b) => b === 'rollup.config.mjs', label: 'rollup.config.mjs' },
];
const deprecatedDirs = ['src-disabled'];

// 4. src/ 配下のファイル名に使える文字（空白・括弧・非 ASCII を禁止）
const safeName = /^[A-Za-z0-9_.\-/]+$/;

for (const file of files) {
  if (!file.startsWith('src/')) continue;
  const parts = file.split('/');
  const base = parts[parts.length - 1];

  for (const rule of devFile) {
    if (rule.test(base)) fail(file, `src/ に開発用ファイルは置けません（${rule.label}）`);
  }
  for (const dir of devDirs) {
    if (parts.slice(0, -1).includes(dir)) fail(file, `src/ に開発用ディレクトリは置けません（${dir}/）`);
  }
  for (const rule of deprecatedFile) {
    if (rule.test(base)) fail(file, `src/ に非推奨のファイルは置けません（${rule.label}）`);
  }
  for (const dir of deprecatedDirs) {
    if (parts.slice(0, -1).includes(dir)) fail(file, `src/ に非推奨のディレクトリは置けません（${dir}/）`);
  }
  if (file.startsWith('src/ts/')) fail(file, 'src/ に非推奨のディレクトリは置けません（ts/）');
  if (!safeName.test(file)) {
    fail(file, 'ファイル名に空白・括弧・非 ASCII 文字などは使えません（使える文字: A-Z a-z 0-9 _ . -）');
  }
}

if (errors.length > 0) {
  console.error(`check-repo: ${errors.length} 件の問題があります。`);
  for (const e of errors) console.error(`  ${e}`);
  process.exit(1);
}
const srcCount = files.filter((f) => f.startsWith('src/')).length;
console.log(`check-repo: OK（追跡ファイル ${files.length} 件、うち src/ ${srcCount} 件）`);
