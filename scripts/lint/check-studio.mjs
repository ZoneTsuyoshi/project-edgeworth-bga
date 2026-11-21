// S3: BGA Studio のコミット時のコード検査が文字列照合で拾うものを、手元と CI で先に検出する（仕様書 004 §6.3、005 §2.2）。
// 外部依存なし。`git ls-files` の src/ にある *.php と *.css を、コメントや文字列も含めて生のテキストで調べる
// （Studio の検査がコメントや変数の呼び出しまで拾うため）。実行: npm run lint:repo
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const FORBIDDEN_FUNCTIONS = ['exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open', 'pcntl_exec'];

const PHP_RULES = [
  {
    // `$exec(` や `->exec(` も含む。`->execute(` のように後ろが続く名前は拾わない
    re: new RegExp(`(?<![A-Za-z0-9_])(${FORBIDDEN_FUNCTIONS.join('|')})\\(`, 'g'),
    reason: (m) => `Studio が禁止関数 ${m[1]}() の呼び出しとして拾います（変数名・メソッド名を変えてください）`,
  },
  {
    re: /(?<![A-Za-z0-9_$])_\(/g,
    reason: () => '非推奨の翻訳関数 _() です。clienttranslate() と NotificationMessage を使ってください',
  },
  {
    re: /(?<!`)player_score(?:_aux)?(?!`)/g,
    reason: (m) => `Studio が ${m[0]} を拾います（コメントは「BGA の得点」などに言い換え、SQL はバッククォートで囲む）`,
  },
];

const CSS_RULES = [
  { re: /@import/g, reason: () => 'Studio が @import を拾います（コメントでも書かないでください）' },
];

/** @param {{path: string, code: string}[]} sources */
export function checkSources(sources) {
  const errors = [];
  for (const { path: file, code } of sources) {
    const rules = file.endsWith('.php') ? PHP_RULES : file.endsWith('.css') ? CSS_RULES : [];
    const lines = code.split('\n');
    for (const rule of rules) {
      lines.forEach((text, i) => {
        for (const m of text.matchAll(rule.re)) errors.push(`${file}:${i + 1}: ${rule.reason(m)}`);
      });
    }
  }
  return { errors };
}

function main() {
  const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
  const files = execFileSync('git', ['ls-files', 'src'], { cwd: root, encoding: 'utf8' })
    .split('\n')
    .filter((f) => /\.(php|css)$/.test(f));
  const { errors } = checkSources(files.map((f) => ({ path: f, code: readFileSync(path.join(root, f), 'utf8') })));
  if (errors.length > 0) {
    console.error(errors.join('\n'));
    console.error(`\nStudio のコード検査に引っかかる文字列が ${errors.length} 件あります。`);
    process.exit(1);
  }
  console.log(`check-studio: ${files.length} ファイルに問題なし`);
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) main();
