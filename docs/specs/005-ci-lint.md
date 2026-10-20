# 005: CI（lint）

| 項目 | 内容 |
| --- | --- |
| ステータス | Approved |
| 対応タスク | T-005 |
| 作成日 | 2026-10-19 |
| 関連仕様書 | 004（BGA プロジェクト構成・技術方針。特に §6.2 命名規則、§10 テスト方針、§11 lint と CI、§12 流用）。後続: T-006（Studio 同期）、T-012（UI・アセット） |

## 目的

`main` へ入る変更が、構文・書式・型・認証情報の混入・原本の改変・BGA フレームワークとのメソッド名の衝突といった、機械的に検出できる問題を含まないことを、PR ごとに GitHub Actions で自動検査するための仕様を定める。姉妹プロジェクト minimum-journey-bga（以下「姉妹」）で稼働中の lint CI を土台にし、実装者が本書と姉妹の実ファイルを見て、このリポジトリに導入できる粒度にする。姉妹で 2026-10-15 に起きた事故（`Game::sendNotifications` が BGA の `Table::sendNotifications` と衝突し、Studio で致命的エラーになった。PHPStan のスタブが古く検出できなかった。姉妹 PR #34）の再発防止として、`pe` 接頭辞の検査（§2.10）を新規に加える。

## スコープ

- 対象: 検査項目とツール、姉妹から流用する点と変える点、`pe` 接頭辞の検査スクリプトの仕様、`data/` の変更禁止の検査、段階導入の計画、ワークフロー構成、PR で必須にする運用、実装時に追加・コピーするファイル
- 対象外: ワークフロー・設定ファイル・スクリプトの実装（本書の承認後に別 PR で行う。本書ではコードもワークフローも作らない）、CD（`src/` の Studio 同期。T-006）、スプライト生成と画像の設計（T-012）、PHPUnit などのテストの CI 実行（§2.12 のとおり未確定事項 No.5）

## 記法と調査の前提

- **【姉妹の実装】** は、姉妹の実ファイルを読んで確認した事実（ファイルのパスを添える）。姉妹の仕様書 `docs/specs/004-ci-lint.md` の記述と実装が食い違う場合は、実装を正とした。
- **【提案】** は本書の提案。**【未確認】** は確認できなかった事項で、「未確定事項」に載せる。
- 調査日は 2026-10-19。姉妹は `main` の `ba13df6`（PR #58）時点。姉妹のワークフローと設定は PR #10（第 1 段階）と PR #20（第 2 段階）で導入されている。BGA の公式ドキュメントは今回は新たに調べていない（004 と姉妹の確認結果に依拠する）。
- 姉妹の実ファイルは、ツールのバージョンとアクションのコミット SHA まで確定している。実装時は、姉妹の値をコピーしたうえで最新を確認して更新する（Dependabot が追随する）。

### 姉妹で使っているバージョン（2026-10-19 に実ファイルで確認）

| 対象 | バージョン | 出典 |
| --- | --- | --- |
| actions/checkout | v7.0.1（SHA 固定） | `.github/workflows/lint.yml` |
| actions/setup-node | v7.1.0（SHA 固定） | 同上 |
| shivammathur/setup-php | 2.40.0（SHA 固定）、`php-version: '8.4'` | 同上 |
| actions/cache | v6.1.0（SHA 固定） | 同上 |
| gitleaks/gitleaks-action | v3.0.0（SHA 固定） | 同上 |
| lycheeverse/lychee-action | v2.9.0（SHA 固定）、lychee 本体 `v0.24.2` | 同上 |
| Node | `.nvmrc` が `24`。`engines.node` は `^22.13.0 \|\| >=24` | `.nvmrc`、`package.json` |
| ESLint / @eslint/js / @eslint/json | `^10.12.0` / `^10.0.1` / `^2.1.1` | `package.json` |
| Stylelint / stylelint-config-standard | `^17.16.0` / `^40.0.0` | 同上 |
| markdownlint-cli2 | `^0.23.3` | 同上 |
| TypeScript | `^7.0.2` | 同上 |
| PHP_CodeSniffer / PHPStan / php-parallel-lint | `^4.0` / `^2.3` / `^1.4` | `composer.json` |
| PHPUnit | `12.5.39`（固定） | 同上 |

## 仕様

### 1. 全体方針

姉妹の方針をそのまま採る。

- **【姉妹の実装】** ワークフロー `Lint`（`.github/workflows/lint.yml`）を `pull_request`（`main` 向け）、`main` への `push`、`workflow_dispatch`、`workflow_call` で実行する。`workflow_call` は T-006 の同期ワークフローが lint を再利用するために残す。`pull_request_target` は使わない。
- **【姉妹の実装】** チェックの実体は `package.json` の `scripts` と `composer.json` の `scripts` に置き、ワークフローはそれを呼ぶだけにする。ローカルでも同じコマンドで実行できる。例外は、Node 以外のバイナリが要る gitleaks と lychee のみ。
- **【姉妹の実装】** lint は検査のみで、ファイルを書き換えない。
- **【姉妹の実装】** 必須チェックは集約ジョブ `gate`（表示名 `Lint gate`）の 1 つだけ。ワークフローレベルの `paths` フィルタは付けない。ジョブは並列に動き、各ジョブに `timeout-minutes` を付ける。権限は `contents: read` を既定とする。サードパーティのアクションはコミット SHA で固定する。`concurrency` は PR の古い実行を取り消し、`main` への push は取り消さない。
- **【提案】** `data/`（原本。約 90 MB の画像）、`.worktrees/`、`node_modules/`、`vendor/`、`tools/bga/`（スタブ置き場）は、`data/` の変更禁止の検査（§2.11）を除く全ツールの対象外とする。

### 2. 検査項目の一覧と、姉妹からの流用・変更

| ID | 検査 | 対象 | ツール | 姉妹から | 段階 |
| --- | --- | --- | --- | --- | --- |
| M1 | Markdown の書式 | `**/*.md`（`data/` ほか除外） | markdownlint-cli2 | そのまま流用（設定は既存） | 1 |
| M2 | リンク切れ（内部リンク） | 同上 | lychee（`--offline`） | そのまま流用 | 1 |
| S1 | 認証情報の混入 | 全履歴と作業ツリー | gitleaks | 流用（タイトルのみ変更） | 1 |
| S2 | 禁止ファイル・`src/` 衛生 | `git ls-files` | `scripts/lint/check-repo.mjs` | 流用し、検査を足す | 1 |
| R1 | `data/` の変更禁止 | PR の差分 | `scripts/lint/check-data-unchanged.mjs` | 新規 | 1 |
| P1 | PHP 構文 | `src`（`tests` は存在すれば） | php-parallel-lint | 流用（対象ディレクトリの扱いを変更） | 2 |
| P2 | PHP 規約（PSR-12、行長 140） | 同上 | PHP_CodeSniffer | 流用（名前などを置換） | 2 |
| P2' | `declare(strict_types=1);` | `src/**/*.php` | `scripts/lint/check-strict-types.mjs` | そのまま流用 | 2 |
| P4 | `pe` 接頭辞 | `src/modules/php/**/*.php` | `scripts/lint/check-method-prefix.mjs` | 新規 | 2 |
| J1 | JS の lint | `src/modules/js/**/*.js` ほか | ESLint | 流用（対象が複数ファイルになる） | 2 |
| D1 | JSONC の構文 | `src/**/*.{json,jsonc}` | ESLint + `@eslint/json` | そのまま流用 | 2 |
| C1 | CSS の lint | `src/**/*.css` | Stylelint | そのまま流用 | 2 |
| P3 | PHP 静的解析（level 5） | `src/modules/php` | PHPStan | 流用（スタブの取得が前提。§3 の 2b） | 2b |
| J2 | JS の型検査 | `src/modules/js/**/*.js` | `tsc --noEmit` | 流用（同上） | 2b |
| A1 | アセットの上限 | 生成後の `src/img/**` | Node スクリプト | 姉妹も未着手。内容は T-012 | 3 |

#### 2.1 Markdown の書式（M1）

- **【姉妹の実装】** `.markdownlint-cli2.jsonc`: `globs: ["**/*.md"]`、`ignores: data/** .worktrees/** node_modules/** vendor/**`、`gitignore: false`。ルールは既定（全ルール有効）から MD013（行長）・MD034（裸の URL）・MD036・MD052・MD060 を無効、MD033 は許可要素 `br` のみ、MD024 は `siblings_only`。実行は `npm run lint:md`（`markdownlint-cli2`）。
- **変更**: なし。このリポジトリには同じ設定が既にある（姉妹とは先頭のコメントの参照先だけが違う）。2026-10-19 に `npx markdownlint-cli2 "**/*.md"` を `main` の 16 ファイルに実行し、0 件であることを確認した。
- 実装 PR では、設定ファイルの先頭コメントの参照先を「仕様書 005」に直すだけにする。

#### 2.2 リンク切れ（M2）

- **【姉妹の実装】** `lychee.toml`: `offline = true`、`include_fragments = "full"`、`no_progress = true`、`exclude_path = ['^\.worktrees/', '^node_modules/', '^data/', '^vendor/']`。CI は `lycheeverse/lychee-action` に `lycheeVersion: v0.24.2`、`args: --config lychee.toml './**/*.md'`、`fail: true` を渡す。外部 URL は検査しない（必須にしない）。ローカルは `npm run lint:links`（`brew install lychee` が前提で、`npm run lint` には含めない）。
- **変更**: なし（`lychee.toml` をそのままコピー）。
- 現状の確認: lychee はこの環境に無く実行していない。代わりに、`docs/` 等の Markdown が持つ相対リンクの先が存在するかを簡易スクリプトで調べ、不在 0 件を確認した（フラグメントの照合は未実施。日本語見出しのアンカーが GitHub と一致するかは姉妹でも実機確認項目だった）。実装時に `main` で実行して確認する。
- 注意: 仕様書は未マージの別仕様書へリンクすると、マージまで M2 が失敗する（姉妹と同じ。未マージの仕様書へのリンクは書かない運用）。

#### 2.3 認証情報の混入（S1）

- **【姉妹の実装】** `.gitleaks.toml`: `[extend] useDefault = true` で既定ルールを継承し、独自ルール `bga-sftp-json-password`（`sftp.json` 形式の `"password"` キー）を 1 つ足し、許可リストは `package-lock.json` のみ。ワークフローの `secrets` ジョブは `fetch-depth: 0` でチェックアウトし、`gitleaks/gitleaks-action` を `GITLEAKS_CONFIG: .gitleaks.toml`、`GITLEAKS_ENABLE_COMMENTS: false` で実行する。ジョブの権限は `contents: read` と `pull-requests: read`（PR のコミット範囲の取得に必要。コメントは無効のため write は不要）。
- **変更**: `title` を `project-edgeworth-bga gitleaks config` にする以外は流用する。
- **`data/` の扱い【提案】**: `data/` は PNG が約 280 ファイル、約 90 MB。gitleaks は既定でバイナリを読み飛ばす見込みだが、**【未確認】**。実装時に実行時間を測り、遅ければ許可リストに `^data/` を足す（原本は画像のみで、テキストの認証情報は入らない）。
- ライセンス: リポジトリの所有者は個人アカウント `ZoneTsuyoshi`（`origin` が `git@github.com:ZoneTsuyoshi/...`）なので、gitleaks-action のライセンスキーは不要（姉妹 004 §2.3 の確認に依拠）。
- 検出時の対応: 履歴から消すだけでなく、必ず鍵を失効・再発行する（姉妹と同じ）。

#### 2.4 禁止ファイル・`src/` 衛生（S2）

- **【姉妹の実装】** `scripts/lint/check-repo.mjs`（外部依存なし。`git ls-files -z` の出力を検査。`npm run lint:repo`）。検査は次の 4 つ。
  1. 認証情報などの禁止ファイル（`.env`、`.env.*`（`.env.example` を除く）、`sftp.json`、`*.pem`、`id_rsa*`、`id_ed25519*`）。`.gitignore` と同じ集合。
  2. `src/` 配下の開発用ファイル（`package.json`、`package-lock.json`、`composer.json`、`composer.lock`、`tsconfig*.json`、`eslint.config.*`、`stylelint.config.*`、`.*rc*`、`phpcs.xml*`、`phpstan.neon*`、`*.test.*`、`*.spec.*`）と開発用ディレクトリ（`node_modules`、`tests`）。
  3. `src/img/cards_*`（スプライト生成物）が追跡されていないこと。
  4. `src/` 配下のファイル名が `[A-Za-z0-9_.\-/]` だけであること（空白・括弧・非 ASCII の禁止。BGA の画像ファイル名の制約）。
- **変更【提案】**:
  - 4 は **`src/` のみ**に適用する（姉妹と同じ）。`data/` には `P037 @4x.png`（空白）や `山岳@4x.png`（非 ASCII）があるため、リポジトリ全体には適用しない。`git ls-files` は必ず `-z` で読む（姉妹の実装どおり。`-z` なしだと日本語名が `"data/boards/area/\345..."` と引用符つきの八進数で出る）。
  - 3 のスプライト生成物のパターンは、T-012 でファイル名が決まった時点で合わせる（姉妹は `src/img/cards_` 始まり）。第 1 段階では `src/` が無いので検査は空振りする。
  - **追加 5**: `src/` に非推奨ファイルを置かない（004 §3）。禁止: `states.inc.php`、`material.inc.php`、`*.action.php`、`*.view.php`、`*.tpl`、`src/ts/`、`rollup.config.mjs`、`src-disabled/`。Studio の初期ファイルに含まれていたものを誤って取り込むのを防ぐ。姉妹にない検査。
  - **追加 6**: BGA 配布のスタブ `_ide_helper.php` と `bga-framework.d.ts` が、リポジトリのどこにも追跡されていないこと（004 §11・§3: コミットしない）。`.gitignore` だけでは `git add -f` を防げない。姉妹にない検査。
- 失敗時は `check-repo: N 件の問題があります。` と各行 `<ファイル>: <理由>` を出して終了コード 1。`src/` が無い間もすべて成功する。

#### 2.5 PHP の構文・規約・`strict_types`（P1・P2・P2'）

- **【姉妹の実装】** ツールは `composer.json` の `require-dev` に固定し、`composer.lock` をコミットする。`config.platform.php` は `8.4.0`。CI は `setup-php`（`php-version: '8.4'`、`coverage: none`、`tools: composer`）。Composer のダウンロードキャッシュだけを `actions/cache` で保存し、`vendor/` はキャッシュしない。`composer lint` は `lint:syntax`（`parallel-lint src tests`）と `lint:cs`（`phpcs`）の 2 つ。
- **【姉妹の実装】** `phpcs.xml.dist`: PSR-12 ベース。行長は `lineLimit` 140・`absoluteLineLimit` 0、`Generic.Files.LineLength.TooLong` を error に引き上げる。`PSR1.Methods.CamelCapsMethodName` は `src/modules/php/Game.php` に限り除外する（`debug_` で始まるメソッドが Studio のデバッグメニューに出るため）。`ignore_warnings_on_exit` は設定しない（警告も失敗にする）。
- **【姉妹の実装】** `declare(strict_types=1);` の検査は `scripts/lint/check-strict-types.mjs`（`npm run lint:strict`）。`<?php` の次の最初の文（コメントと空白を除く）が `declare(strict_types=1);` であること。`src/` が無ければ対象 0 件で成功する。PHP が無いローカルでも実行できる。
- **変更**:
  - `composer.json` の名前を `zonetsuyoshi/project-edgeworth-bga` にし、`autoload` を `Bga\\Games\\ProjectEdgeworth\\` → `src/modules/php/`、`autoload-dev` を `ProjectEdgeworthTests\\` → `tests/` にする（004 §10。名前空間の大文字小文字は 004 の未確定事項 No.1 で Studio の初期ファイルから確定する）。`phpcs.xml.dist` の `ruleset name` と説明文も置換する。
  - **対象ディレクトリは存在するものだけにする**。姉妹の `parallel-lint src tests` と `phpcs.xml.dist` の `<file>tests</file>` は、ディレクトリが無いとエラーになる見込み（**【未確認】**）。このリポジトリは `tests/` が `src/` より後にできるため、第 2 段階では `src` だけを対象とし、`tests/` ができた PR で足す。
  - `PHPUnit` は `require-dev` に入れない（`tests/` ができるまで。§2.12）。
  - `lint:strict` は姉妹のとおり `node` ジョブで実行し、Composer の `lint` には含めない。

#### 2.6 PHP の静的解析（P3）と JS の型検査（J2）

- **【姉妹の実装】** `phpstan.neon.dist`: `level: 5`、`phpVersion: 80400`、`paths` と `scanDirectories` は `src/modules/php`、`scanFiles` は `tools/bga/_ide_helper.php`。`tsconfig.json`: `allowJs`・`checkJs`・`noEmit`・`strict`、`target`/`module` は `ES2022`、`moduleResolution: Bundler`、`include: ["src/modules/js/**/*.js", "tools/bga/bga-framework.d.ts"]`。
- **【姉妹の実装】** スタブ `_ide_helper.php`（PHP）と `bga-framework.d.ts`（JS の型）は BGA の配布物で、再配布の許諾が確認できないためコミットしない。CI の専用ジョブ `static`（表示名 `Static analysis (BGA stubs)`）が Studio から SFTP で取得して PHPStan と `tsc` を実行する（`scripts/lint/fetch-bga-stubs.sh`、`lftp`、鍵は `printf` で書いて最後に削除）。Secrets / Variables が無いとき（フォーク・Dependabot の PR など）は警告つきでスキップして成功する。`composer lint:stan` は別スクリプト、`npm run lint:types` は `scripts/lint/check-types.mjs` 経由（スタブが無ければ警告してスキップ。`--require` を付けると失敗にする。CI は `--require`）。
- **変更**:
  - 流用するが、**第 2 段階の本体とは切り離して「2b」とする**（§3）。理由: 取得に使う Secrets / Variables（`BGA_SSH_PRIVATE_KEY`、`BGA_SFTP_USER`、`BGA_SFTP_HOST`、`BGA_SFTP_PORT`、`BGA_REMOTE_DIR`、`BGA_SSH_KNOWN_HOSTS`）は、作者の作業 5・6 と T-006 で設定するものと共通で、まだ無い。2b は T-006 の接続が確立してから入れる。
  - `tsconfig.json` の `exclude` と `phpstan-baseline.neon` は、姉妹では Studio の初期ファイルを雛形として取り込んだ際の一時対応だった。このリポジトリでは最初から実装コードを書くので、**原則として作らない**。必要になったときだけ、理由を書いて足す。
  - `types/bga-globals.d.ts` は作らない（姉妹も作っていない。`bga-framework.d.ts` が同じグローバルを宣言し、併置すると二重宣言になるため）。

#### 2.7 JS の lint（J1）と JSONC の構文（D1）

- **【姉妹の実装】** `eslint.config.mjs`（flat config、`defineConfig` と `globalIgnores`）。無視: `node_modules/`、`vendor/`、`data/`、`.worktrees/`、`build/`、`src/img/`、`tools/bga/`。全 `*.{js,mjs}` に `@eslint/js` の `recommended` と `eqeqeq`・`no-var`・`prefer-const`・`no-shadow`・`no-unused-vars`（`_` 始まりは許可）。`src/modules/js/**/*.js` は ES モジュールで、`globals.browser` と BGA のグローバル（`_`、`$`、`dojo`、`ebg`、`define`、`gameui`、`getLibUrl`、`importDojoLibs`、`importEsmLib`、`g_gamethemeurl`、`g_replayFrom`、`g_archive_mode`、`g_tutorialwritten`）を `readonly` で宣言し、`dojo` の使用は警告。`scripts/**/*.mjs` と `*.config.mjs` は `globals.node`。`src/**/*.{json,jsonc}` は `@eslint/json` の `json/jsonc` と `recommended`（構文と重複キー。末尾カンマは不可）。実行は `npm run lint:js`（`eslint .`）。
- **変更**: そのまま流用する。注意点は 2 つ。(1) 004 §4 のとおり JS は `Game.js` を入口に `states/`・`board/`・`cards/`・`ui/` へ分割するため、ESLint の対象は複数ファイルになる（グロブは姉妹と同じ `src/modules/js/**/*.js` で足りる）。(2) `modules/js/` の相対 `import` が Studio の本番 minify で動くかは 004 の未確定事項 No.4（最初の実装 PR で Studio 確認）。lint の設定には影響しない。
- Node のバージョンは姉妹と同じ（`.nvmrc` は `24`、`engines.node` は `^22.13.0 || >=24`）。

#### 2.8 CSS（C1）

- **【姉妹の実装】** `stylelint.config.mjs`: `extends: ['stylelint-config-standard']` のみ。実行は `stylelint "src/**/*.css" --allow-empty-input`（`npm run lint:css`）。
- **変更**: なし。対象は `src/projectedgeworth.css`（004 §3）。CSS は 1 ファイル。

#### 2.9 アセット（A1）

- **【姉妹の実装】** 姉妹の仕様書 004 §2.9 は `scripts/lint/check-assets.mjs`（`image-size`）を構想したが、**実装されていない**（`scripts/lint/` にあるのは `check-repo.mjs`、`check-strict-types.mjs`、`check-types.mjs`、`fetch-bga-stubs.sh` の 4 つ）。第 3 段階は未着手で、`lint.yml` の `gate` の `needs` にも `assets` ジョブは無い。代わりに姉妹の仕様書 009 §5.4 に基づき、スプライト生成スクリプト群の一部として `scripts/check-sprites.mjs`（`npm run check:sprites`。`sharp` を使い、生成はしない）が実装されており、次の上限を持つ: 1 ファイル 2,000,000 バイト、`src/img/` の総量 15,000,000 バイト（目標 6,000,000 は警告）、幅・高さ 4096 px、ファイル名 `^[A-Za-z0-9_]+\.webp$`、`src/img/` 直下のファイル数 10 以上で警告。同期ワークフロー `bga-sync.yml` が `npm run build:sprites`（最後に同じ検査を実行）を呼ぶ。lint 側での実行は決まっていない。
- **変更【提案】**: このリポジトリでも第 3 段階とし、**T-012 でスプライトの設計と生成スクリプトが決まってから別 PR で実装する**。上限の値（2,000,000 B / 15,000,000 B / 4096 px / ファイル名 / 10 個以上で警告）は姉妹の実装と同じにする（BGA の公式ドキュメントの食い違い「1 枚 2 MB と 4 MB」は、厳しい方の 2 MB を採る。004 §13）。検査を `scripts/lint/` に置くか、姉妹と同様に生成スクリプトと同居させるか、lint の `assets` ジョブに入れるか同期側だけに置くかは T-012 と T-006 で決める（未確定事項 No.6）。

#### 2.10 `pe` 接頭辞の検査（P4。新規）

##### 目的

004 §6.2 のとおり、フレームワークのクラスを継承するクラスに足す独自メソッドの名前は `pe` で始める。同名のメソッドがフレームワークにあると Studio で致命的エラーになるが、開発用スタブが古いため PHPStan では見つけられない（姉妹の事故は `Game::sendNotifications` と `Table::sendNotifications` の衝突）。この規則を機械で守らせる。確定済み（004 の未確定事項 No.5）。

##### 実装形態

- `scripts/lint/check-method-prefix.mjs`。外部依存なしの Node スクリプトで、PHP を実行しない（作者のローカルに PHP が無くても、秘密情報が無い `node` ジョブでも動かすため。`check-strict-types.mjs` と同じ方針）。`npm run lint:prefix` で実行する。引数なしで `src/modules/php/` 以下の `*.php` を調べる。
- PHP パーサ（`nikic/php-parser`）を使う案は、PHP が要り CI でも Composer 経由になるため採らない。代わりに、コメントと文字列を除いた本文から `class` / `trait` / `use` / `function` を字句的に拾う簡易解析にする（下記の限界を仕様に含める）。未確定事項 No.2。

##### 対象クラス

1. `src/modules/php/**/*.php` の全ファイルから、名前空間（`namespace`）、`use` の取り込み（別名 `as` とグループ `use A\{B, C as D};` を含む）、`class` と `trait` の宣言を集める。
2. `extends` の親の名前を完全修飾名に解決する（先頭が `\` ならそのまま。先頭の要素が `use` の別名ならそれを展開。それ以外は現在の名前空間に付ける）。
3. 親の完全修飾名が `Bga\GameFramework\` で始まるクラスを**対象クラス**とする（`Table`、`States\GameState` ほか）。さらに、対象クラスを `extends` するプロジェクト内のクラス（基底クラスを挟んだ継承）も、不動点まで繰り返して対象に含める。
4. 対象クラスが `use` するトレイト（クラス本体の中の `use TraitName;`）も対象に含める（トレイトのメソッドはクラスに入るため）。
5. 対象にしないもの: フレームワークを継承しないクラス（`Board/`・`Flow/`・`Data/` などの純粋な計算・データ。004 §6.2）、インターフェース、enum、`tests/`。
6. 無名クラスで `new class extends <フレームワークのクラス>` が現れたら、解析できないので**失敗**にする（名前付きクラスに書き直す）。

##### 検査

- 対象クラスの本体の直下（波括弧の深さが 1）にある `function 名前(` をメソッドとして拾う。メソッド内のクロージャ（`function (`）や、深さが 2 以上のものは無視する。可視性・`static`・`abstract`・`final` は問わない（private も対象。姉妹の `mjRecord` の実績）。
- メソッド名が次のいずれかであれば合格。それ以外は失敗。
  1. 正規表現 `^pe[A-Z]` に合う（`peSendNotifications` は合格、`perform`・`peek` は不合格）。
  2. 許可リスト（次項）にある。
- 出力: 失敗ごとに `ファイル:行: クラス名::メソッド名 は pe で始まっていません（フレームワークの上書きなら許可リストへ）` の形で出し、件数を表示して終了コード 1。成功時は `check-method-prefix: OK（対象クラス N 件、メソッド M 件）`。

##### 許可リスト（フレームワークのメソッドの上書きを除外する方法）

フレームワークが呼び出す名前（上書きが必須のもの）は `pe` を付けられないため、スクリプトの先頭の定数で許可する。**許可リストは最小にし、足すときはその PR で根拠（公式ドキュメントの URL）を書く**。足し忘れると lint が失敗するだけで、Studio での事故にはならない（失敗側に倒れる）。

| 区分 | 許可するメソッド名 | 根拠 |
| --- | --- | --- |
| 全対象クラス | 魔術メソッド（`__` で始まるもの。`__construct` など） | PHP の言語仕様 |
| `Bga\GameFramework\Table` を継承するクラス（`Game.php`） | `setupNewGame`、`getAllDatas`、`getGameProgression`、`upgradeTableDb` | 004 §6.1。姉妹の `Game.php` が定義している |
| 同上 | `debug_` で始まる名前（`^debug_[A-Za-z0-9_]+$`） | Studio のデバッグメニューに出る（004 §6.1。姉妹 003 が確認） |
| `Bga\GameFramework\States\GameState` を継承するクラス（状態クラス） | `onEnteringState`、`getArgs`、`zombie` | 004 §5.1。姉妹の状態クラス 26 個が定義している |
| 同上 | `act` で始まり、**直前に `#[PossibleAction]` 属性が付いたメソッド** | プレイヤーの操作を受けるメソッドの規則（004 §5.1）。属性が無い `actXxx` は不合格にする |

- 初期リストは、004 と姉妹の実ファイルで使われている名前だけにした。公式ドキュメントにある他の上書き可能なメソッド（例: 状態クラスの `onLeavingState` など）は**【未確認】**で、使う時点で公式（StateDir、GamePhp）を確認して足す。実装者は初期リストを公式ドキュメントで見直す（未確定事項 No.3）。
- 区分は「親を辿った先のフレームワークのクラス」で決める（`Table` 系か `GameState` 系か）。それ以外の `Bga\GameFramework\` のクラスを継承するクラスは、魔術メソッドだけを許可する。
- 抜け道の注意: 許可リストにある名前は、フレームワークの上書きであるかをこのスクリプトでは確かめない（スタブが古いため確かめられない）。

##### 誤検出の扱い

- 誤検出は、(a) 上書きが正当なのに許可リストに無い場合と、(b) 簡易解析の限界で本来の対象でない箇所を拾う場合に起こる。
- (a) は許可リストへ足す（上記）。(b) は個別に行の直前へ `// pe-lint: allow <理由>` のコメントを書いて除外できる（理由は必須。理由が空だと失敗）。許可コメントは PR のレビューで見える。
- 許可リストと許可コメントの使用数は、成功時の出力に件数だけ表示する（増え続けていないかを見るため）。
- 簡易解析の限界（仕様として明記する）: (1) 文字列・ヒアドキュメント・コメント内の `function` は除外するが、PHP の全構文は解釈しない。(2) 1 ファイル 1 名前空間を前提とし、複数の `namespace` ブロックがあれば失敗にする。(3) `class_alias` や動的な継承は見ない。(4) メソッドの上書きかどうかは判定しない（名前だけを見る）。

##### 自己テスト

- `node --test scripts/lint/` で動く単体テスト `scripts/lint/check-method-prefix.test.mjs` を実装に含める（PHP の断片は文字列としてテスト内に持つ。`tests/` の PHP 構文検査や `src/` 衛生の検査に触れないようにするため）。テストする事例:

| 事例 | 期待 |
| --- | --- |
| `Game extends \Bga\GameFramework\Table` に `sendNotifications` | 失敗（姉妹の事故） |
| 同じクラスに `peSendNotifications` | 成功 |
| 状態クラスの `private function record(...)` | 失敗。`peRecord` は成功 |
| `onEnteringState`・`getArgs`・`zombie`・`__construct` | 成功 |
| `#[PossibleAction] public function actPass()` | 成功。属性なしの `actPass` は失敗 |
| `Game` の `debug_goToState` | 成功。状態クラスの `debug_x` は失敗 |
| `use Bga\GameFramework\States\GameState as Base;` ＋ `extends Base` | 対象と解釈される |
| 基底クラス `abstract class BaseState extends GameState` を `extends` するクラス | 対象と解釈される |
| `Flow/` のクラス（フレームワークを継承しない）に `calc` | 対象外で成功 |
| トレイトのメソッド（対象クラスが `use`） | 検査される |
| メソッド内のクロージャ、コメント・文字列中の `function foo(` | 無視される |
| `// pe-lint: allow 理由` の直後のメソッド | 成功。理由なしは失敗 |
| `new class extends Table` | 失敗（未対応の構文） |

##### 実行場所

- `node` ジョブ（§4）のステップとして実行し、`npm run lint` にも含める。`src/modules/php/` が無ければ対象 0 件で成功する（`check-strict-types.mjs` と同じ扱い）。

#### 2.11 `data/` の変更禁止（R1。新規）

> **2026-10-20 リポジトリ公開に伴い R1 は廃止した。** `data/` を Git の履歴から除いて作者の手元だけに置く（`.gitignore` 対象）ため、変更禁止の検査は不要になり、`check-data-unchanged.mjs` と `lint:data`・lint.yml の手順を削除した。代わりに `check-repo.mjs` が `data/` 配下の追跡を失敗にする（原本の混入防止）。以下は当時の設計の記録。

CLAUDE.md の絶対ルール 6（`data/` は原本。編集・移動・削除しない）を機械で守らせる。

- **実装**: `scripts/lint/check-data-unchanged.mjs`（外部依存なし。`npm run lint:data`）。比較の基準を環境変数または引数で受け取る。
  - `pull_request`: `git diff --name-status -z --no-renames <base.sha> HEAD -- data/`（`base.sha` は `github.event.pull_request.base.sha`。チェックアウトは `fetch-depth: 0`）。
  - `push`（`main`）: `github.event.before` と `github.sha` の差分。`before` が全桁 0（新規ブランチ）のときは検査しない。
  - ローカル（環境変数なし）: `origin/main` との差分（`git merge-base origin/main HEAD` から `HEAD` まで）。
  - `workflow_dispatch`: 検査しない（基準が無い）。
- **判定**: 差分の状態が `M`（変更）・`D`（削除）・`T`（種別変更）なら失敗。`--no-renames` で名前の変更は `D` ＋ `A` として見えるので、移動も失敗になる。`A`（追加）は許可する（暫定案。未確定事項 No.4）。
- 出力: `check-data-unchanged: data/ の原本が変更されています。` と各行 `<状態> <ファイル>`、終了コード 1。
- 作者が承知のうえで原本を差し替える場合は、スクリプトの定数 `ALLOWED_CHANGES` にパスと理由（日付）を足す PR を出す。作者の承認を経てマージされるので、抜け道ではなく記録になる。
- パス名は日本語・空白を含む（`data/boards/area/山岳@4x.png`、`data/cards/projects/P037 @4x.png`）。`-z` で読む。
- 実行場所: `repo` ジョブ（§4）。

#### 2.12 PHPUnit などのテスト（対象外）

- 004 §10 は PHPUnit（`tests/Unit/`、`tests/Integration/`）を方針とし、CI での実行有無を T-005 が決めるとしている。姉妹の `lint.yml` と `bga-sync.yml` は PHPUnit を実行していない（`composer test:integration` は Docker のローカル実行用）。
- **【提案】** 本書の lint には含めない。`tests/` ができた後に、別の `test` ジョブ（単体テストのみ。MariaDB 不要）を足すかを判断する。未確定事項 No.5。

### 3. 段階的な導入計画

姉妹と同じく、段階ごとに別 PR で導入する。このリポジトリは `src/` がまだ無く、実ファイルの無い設定を先に書くと検証できない設定が残るため、この順にする。

| 段階 | 追加する検査 | 前提 | ジョブ |
| --- | --- | --- | --- |
| 第 1 段階 | M1 Markdown、M2 リンク、S1 gitleaks、S2 禁止ファイル、R1 `data/` の変更禁止 | `src/` が無くてよい。Markdown と `data/` が既にある | `markdown`、`secrets`、`repo`、`gate` |
| 第 2 段階 | P1 構文、P2 規約、P2' `strict_types`、P4 `pe` 接頭辞、J1 ESLint、D1 JSONC、C1 CSS | `src/` に最初の PHP / JS / CSS / JSONC がマージ済み（雛形または最初の実装 PR）。Secrets は不要 | `php`、`node` |
| 第 2b 段階 | P3 PHPStan、J2 `tsc` | 第 2 段階が済み、T-006 の接続（作者の作業 5・6）が確立し、`static` に必要な Secrets / Variables が登録済み | `static` |
| 第 3 段階 | A1 アセット上限 | T-012 のスプライト生成スクリプトがマージ済み | `assets`（または同期側。未確定事項 No.6） |

- 第 1 段階は本書の承認後すぐに実装する。M1・M2・S1・S2・R1 は `src/` が無くても、既存の Markdown と `data/` に対して動作確認できる。
- 第 2 段階の実装を、`src/` の最初の PHP / JS / CSS を入れる実装 PR に同梱するか、その直後の別 PR にするかは、`src/` の最初の PR を計画するときに決める（姉妹は雛形の直後の別 PR だった）。同梱する場合も、`pe` 接頭辞の検査は最初の `Game.php` から効かせる。
- **`gate` は第 1 段階で作り、以降は `needs` にジョブを足すだけにする**（姉妹と同じ。必須チェックの指定を変えなくてよい）。
- 第 2 段階以降は、対象が揃っていない間もステップが失敗しないようにする。姉妹では `src/` が既にあり不要だったが、このリポジトリでは第 2 段階の PR で `src/` の各種ファイルが揃う前提にし、**ステップに `if: ${{ hashFiles('src/**/*.php') != '' }}` のようなガードを付ける**（PHP は `src/**/*.php`、JS は `src/modules/js/**/*.js`）。`hashFiles` はステップレベルの `if` で使う（ジョブレベルでは使えない見込み。姉妹 004 の U2）。
- 第 2b 段階の `static` は、姉妹どおり、Secrets / Variables が無いときは警告つきでスキップして成功する。したがって、`gate` にいつ入れても、フォークや Dependabot の PR が止まらない。ただし**スキップされている間は PHPStan と `tsc` が実行されていない**ことを、PR の画面で見落とさないこと（姉妹は `GITHUB_STEP_SUMMARY` に書く）。

### 4. ワークフロー構成

`.github/workflows/lint.yml`（ワークフロー名 `Lint`）。姉妹のものを土台にし、トリガー・`permissions`・`concurrency`・SHA 固定・`timeout-minutes` は同じにする。ジョブは次のとおり。

| ジョブ ID | 表示名 | 段階 | 内容 | 姉妹との違い |
| --- | --- | --- | --- | --- |
| `markdown` | `Markdown` | 1 | checkout → setup-node（`.nvmrc`、`cache: npm`）→ `npm ci` → `npm run lint:md` → lychee | 同じ |
| `secrets` | `Secrets` | 1 | checkout（`fetch-depth: 0`、`persist-credentials: false`）→ gitleaks。権限は `contents: read` と `pull-requests: read` | 姉妹の `lint:repo` を `repo` ジョブへ移す |
| `repo` | `Repository rules` | 1 | checkout（`fetch-depth: 0`）→ setup-node（`npm ci` は不要）→ `npm run lint:repo` → `npm run lint:data` | 新設。S2 と R1 をまとめる |
| `php` | `PHP` | 2 | checkout → setup-php（8.4）→ Composer キャッシュ → `composer install` → `composer lint` | 同じ |
| `node` | `JS / CSS / JSONC` | 2 | checkout → setup-node → `npm ci` → `lint:js` → `lint:strict` → `lint:prefix` → `lint:css`。各ステップに `if: ${{ !cancelled() }}`（先行ステップが失敗しても続けて、1 回で全エラーを見る） | `lint:prefix` を追加 |
| `static` | `Static analysis (BGA stubs)` | 2b | 姉妹と同じ（スタブを取得して `composer lint:stan` と `npm run lint:types -- --require`） | 同じ。T-006 の後 |
| `assets` | `Assets` | 3 | T-012 で決定 | 姉妹も未導入 |
| `gate` | `Lint gate` | 1 | `if: ${{ always() }}`、`needs` に上記すべて。結果に `failure` / `cancelled` / `skipped` があれば失敗 | 第 1 段階の `needs` は `[markdown, secrets, repo]` |

- `lint:prefix` と `lint:strict` は PHP を実行しないので、`node` ジョブ（Secrets なし）に置く。
- R1 は `pull_request` の `base.sha` と `push` の `before` を使うため、`repo` ジョブのステップに環境変数 `BASE_SHA`（PR は `github.event.pull_request.base.sha`、push は `github.event.before`）を渡す。具体的な渡し方は実装者が決める。
- ローカルの一括実行は姉妹と同じく `npm run lint`（最初の失敗で止まる `&&` 連結）。最終形は `lint:md && lint:repo && lint:data && lint:js && lint:strict && lint:prefix && lint:css`。`lint:types`（スタブが必要）は姉妹どおり別にする。第 1 段階では `lint:md`・`lint:repo`・`lint:data` のみ。`lint:links`（lychee）と gitleaks は Node 以外のバイナリが要るため `npm run lint` に含めない。
- pre-commit フックは導入しない（姉妹と同じ。コミット前に `npm run lint` を実行する運用）。

### 5. PR で必須にするチェックと、マージの運用

- **必須とするのは `Lint gate` の 1 つ**。ジョブ単位では選ばない（ジョブの追加・改名のたびに設定を直さずに済むため）。
- **`main` の保護は設定しない**。姉妹の作者は 2026-10-17 に「`main` の保護は設定しない」と判断している（姉妹 `docs/tasks.md`）。このリポジトリも同じ扱いとする。姉妹では、非公開リポジトリが Free プランだと必須チェックを強制できない可能性もあった（姉妹 004 の No.5）。このリポジトリの公開範囲とプランは未確認（未確定事項 No.7）。
- **代わりの担保（運用）**: CLAUDE.md に記載のとおり、**CI（`Lint gate`）が通らない PR はメインエージェントがマージしない**。マージ前に `gh pr checks` で `Lint gate` が成功であることを確かめる。サブエージェントはマージしない。依存更新の Dependabot の PR も同じ。
- `main` への push でも lint は実行される（`on: push`）。これは保護の代わりではなく、直接 push や運用ミスを事後に検知するためのもの。
- 実装 PR では、`docs/development/git-workflow.md` の「`main` を保護: PR 必須、ステータスチェック（lint）必須、force push 禁止」の推奨設定の文言を、「保護は設定しない。運用で担保する」に合わせて直す（このリポジトリの現状の文言は保護を推奨している）。
- 将来、保護を設定する場合に作者が行うこと（姉妹 004 §7 と同じ）: ワークフローが 1 回以上実行された後に、Settings → Branches（または Rulesets）で必須チェックに `Lint gate` を指定する。Actions の既定権限は Read にする。Squash merge のみ許可、マージ後のブランチ自動削除を有効にする。

### 6. 実装時に追加・コピーするファイル

「コピー」は姉妹の同名ファイルを元にし、記載の点だけ置換する。姉妹のパスは `/Users/zone/Developments/minimum-journey-bga/` 配下。

| パス | 段階 | 姉妹から | 内容・置換点 |
| --- | --- | --- | --- |
| `.github/workflows/lint.yml` | 1（以降は追記） | コピー＋変更 | §4 のジョブ構成。第 1 段階は `markdown`・`secrets`・`repo`・`gate`。アクションの SHA は姉妹の値をコピーして最新を確認 |
| `.github/dependabot.yml` | 1 | コピー | `github-actions` / `npm` / `composer` の週次更新（グループ化）。第 1 段階では `composer.json` が無いので `composer` は第 2 段階で足す |
| `package.json`、`package-lock.json` | 1 | コピー＋変更 | `name` を `project-edgeworth-bga`。第 1 段階の devDependencies は `markdownlint-cli2` のみ、`scripts` は `lint`・`lint:md`・`lint:repo`・`lint:data`・`lint:links`。第 2 段階で ESLint 系・Stylelint・TypeScript と `lint:js`・`lint:strict`・`lint:prefix`・`lint:css`・`lint:types` を足す。`sharp`・`build:sprites`・`check:*`・`test:integration` は T-012 以降 |
| `.nvmrc` | 1 | コピー | `24` |
| `.markdownlint-cli2.jsonc` | 1 | 既存 | コメントの参照先のみ直す（§2.1） |
| `lychee.toml` | 1 | コピー | 変更なし |
| `.gitleaks.toml` | 1 | コピー＋変更 | `title` のみ |
| `scripts/lint/check-repo.mjs` | 1 | コピー＋変更 | §2.4 の追加 5・6。スプライト生成物のパターンは T-012 で合わせる |
| `scripts/lint/check-data-unchanged.mjs` | 1 | 新規 | §2.11 |
| `.gitignore` | 1 | 追記 | `/tools/bga/_ide_helper.php`、`/tools/bga/bga-framework.d.ts`、`.phpunit.result.cache`（`tests/` を導入するとき）。スプライト生成物（`/build/` と `/src/img/` の生成物）は T-012 |
| `composer.json`、`composer.lock` | 2 | コピー＋変更 | §2.5。`phpunit` は入れない。`lint:syntax` の対象は `src` のみ |
| `phpcs.xml.dist` | 2 | コピー＋変更 | `ruleset name`・説明文の置換。`<file>` は `src` のみ |
| `scripts/lint/check-strict-types.mjs` | 2 | コピー | 変更なし |
| `scripts/lint/check-method-prefix.mjs`、`scripts/lint/check-method-prefix.test.mjs` | 2 | 新規 | §2.10 |
| `eslint.config.mjs` | 2 | コピー | 変更なし |
| `stylelint.config.mjs` | 2 | コピー | 変更なし |
| `phpstan.neon.dist` | 2b | コピー | 変更なし（`phpstan-baseline.neon` は作らない） |
| `tsconfig.json` | 2b | コピー | `exclude` は付けない |
| `scripts/lint/check-types.mjs`、`scripts/lint/fetch-bga-stubs.sh` | 2b | コピー | 変更なし。Secrets / Variables の名前は T-006 と共通 |
| `docs/development/cicd.md` | 1 | コピー＋書き直し | このリポジトリには無い。姉妹の `docs/development/cicd.md` を元に、導入済みの lint の内容に合わせて新設。`CLAUDE.md` の「CI/CD」節から参照 |
| `docs/development/git-workflow.md`、`docs/development/task-workflow.md` | 1 | 追記 | 「PR 作成前に `npm run lint` を実行する」。`main` の保護に関する文言の修正（§5） |
| `docs/specs/README.md`、`docs/tasks.md`、本書のステータス | 1 | 更新 | ステータスを Implemented に。T-005 を完了に |
| `scripts/lint/check-assets.mjs` など | 3 | T-012 で決定 | §2.9 |

- 姉妹の `.claude/settings.local.json` は個人設定で、コピーしない。
- 姉妹の `scripts/bga-sync/`、`.github/workflows/bga-sync.yml` は T-006 のもの。本書では扱わない。
- 追加する依存は、すべてロックファイル（`package-lock.json`、`composer.lock`）で固定する。

## エッジケース

- `src/` が存在しない間（第 1 段階）: S2 の `src/` 配下の検査は空振りして成功する。P4・P2'・ESLint・Stylelint は第 2 段階まで導入しない。
- `data/` のファイル名に空白・日本語がある: S2 の「ファイル名に使える文字」は `src/` のみに適用する。R1 と S2 は `-z` で読む。lychee が `data/` 内のファイルへの相対リンク（空白は `%20`）を解決できるかは**【未確認】**。実装時に確認する（現状の Markdown に `data/` へのリンクがあれば M2 が見る）。
- `tests/` が無い間: `parallel-lint` と `phpcs` は存在するディレクトリだけを対象にする（§2.5）。
- フォークや Dependabot の PR: Secrets が渡らないため、`static`（第 2b 段階）は警告つきでスキップして成功する。それ以外は Secrets を使わない。
- PR のベースが古い（`main` が進んだ）場合: R1 は PR のベースとの差分で判定するため、他の PR が `data/` を変えていても無関係。
- `main` への squash merge 直後の push: lint が再実行される。R1 は `before` との差分で、PR で通っていれば通る。
- `pe` 接頭辞の検査: Studio の初期ファイル（`Game.php` の雛形）を取り込む最初の PR は、雛形のメソッド名が許可リストにあるかを確認する。雛形の独自メソッドで `pe` が無いものがあれば、リネームするか許可リストに根拠つきで足す。
- BGA がフレームワークのメソッドを新しく追加した場合: 許可リストにあるメソッドは影響を受けないが、`pe` が付いていない独自メソッドは今後も衝突しうる。`pe` 接頭辞の規則そのものが安全策。
- R1 で作者が意図して原本を差し替える場合: §2.11 の `ALLOWED_CHANGES` に足す PR を出す。

## 未確定事項

| No. | 質問 | 暫定案 | 回答 |
| --- | --- | --- | --- |
| 1 | 段階導入（第 1 段階は承認後すぐ、第 2 段階は `src/` の最初のファイルがマージされた後、第 2b 段階は T-006 の接続確立後、第 3 段階は T-012 の後）でよいか。第 2 段階の実装は、`src/` の最初の実装 PR に同梱するか、その直後の別 PR か | 段階ごとに別 PR。第 2 段階は `src/` の最初の PR の直後の別 PR（ただし `pe` 検査は最初の `Game.php` から効くよう、同じタイミングで入れる） | 暫定案のとおり（2026-10-19） |
| 2 | `pe` 接頭辞の検査を、PHP パーサを使わない Node の簡易解析（本書の案）で実装してよいか。`nikic/php-parser` を使うと解析は正確になるが、PHP が要り、`php` ジョブに置くことになる | Node の簡易解析。限界（§2.10）は仕様に明記し、自己テストで固定する | 暫定案のとおり（2026-10-19） |
| 3 | `pe` 検査の許可リストの初期値（`setupNewGame`、`getAllDatas`、`getGameProgression`、`upgradeTableDb`、`onEnteringState`、`getArgs`、`zombie`、`act*`＋`#[PossibleAction]`、`debug_*`、魔術メソッド）は、004・姉妹の実ファイルから拾ったもの。公式ドキュメントで他に追加すべきものがあるか確認してよいか | 実装 PR で StateDir・GamePhp を再確認し、足りないものを根拠つきで足す | 暫定案のとおり（2026-10-19） |
| 4 | `data/` への**ファイルの追加**（新しいカード画像など）を R1 で許可してよいか。変更・削除・移動は失敗にする | 追加は許可、変更・削除・移動は失敗。意図した差し替えは `ALLOWED_CHANGES` に足す PR で行う | 暫定案のとおり（2026-10-19） |
| 5 | PHPUnit（`tests/Unit/`、必要なら `Integration/`）を CI で実行するか。姉妹の CI は実行していない | 本書の lint には含めない。`tests/` ができた時点で、単体テストのみの `test` ジョブを別途検討する | 暫定案のとおり（2026-10-19） |
| 6 | アセットの上限の検査（A1）の置き場所: lint の `assets` ジョブか、T-006 の同期ワークフロー内か。スプライトを CI で二重に生成してよいか（姉妹は同期側のみで生成し、lint では未実施） | T-012 の生成スクリプトが決まってから判断する。初期は同期側だけで検査する | 暫定案のとおり（2026-10-19） |
| 7 | このリポジトリの公開範囲（公開 / 非公開）と GitHub のプランは何か。`main` の保護を設定しないため、運用で担保することに変わりはないが、gitleaks や Actions の無料枠に影響しうる | 個人アカウント `ZoneTsuyoshi` の所有なので、gitleaks のライセンスキーは不要。公開範囲は作者に確認 | 暫定案のとおり（2026-10-19） |
| 8 | `static` ジョブ（PHPStan と `tsc`）が使う Studio からのスタブ取得で、姉妹と同じ SSH 鍵と Secrets 名（`BGA_SSH_PRIVATE_KEY` など）を使い回してよいか。T-006 の未確定事項と同じ | 姉妹と同じ名前で別リポジトリの Secrets に登録する。鍵の使い回しは T-006 で確認 | 使い回す（作者回答、2026-10-19） |
| 9 | `data/` を gitleaks の対象に含めたままでよいか（約 90 MB の画像） | 含める。実行時間が長ければ許可リストに `^data/` を足す | 暫定案のとおり（2026-10-19） |

## 受け入れ条件

この仕様が正しく実装されたと判断するための条件。段階ごとに満たす。

第 1 段階:

- [ ] `Lint` ワークフローが `pull_request`・`main` への `push`・`workflow_dispatch`・`workflow_call` で動き、`Lint gate` が `markdown`・`secrets`・`repo` の結果を集約する
- [ ] `main` の現在の Markdown に対して `npm run lint:md` と lychee（内部リンク）が 0 件で通る
- [ ] ダミーの秘密鍵（`-----BEGIN OPENSSH PRIVATE KEY-----`）や `sftp.json` をコミットした PR で、gitleaks または `lint:repo` が失敗する（確認後は取り消す）
- [ ] `data/` の既存ファイルを変更・削除・移動した PR で `lint:data` が失敗し、新規追加のみの PR では成功する
- [ ] `src/` が無くても全ジョブが成功する
- [ ] `main` の保護は設定せず、CLAUDE.md の運用（`Lint gate` が通らない PR はマージしない）が `docs/development/` に書かれている

第 2 段階:

- [ ] `composer lint` が `src` の PHP の構文と PSR-12（行長 140）を検査し、`npm run lint:js`・`lint:css`・`lint:strict` が通る
- [ ] `npm run lint:prefix` が、§2.10 の自己テストの全事例（`node --test scripts/lint/`）を満たす
- [ ] `Game` に `sendNotifications` のような `pe` の無いメソッドを足した PR で `lint:prefix` が失敗し、`peSendNotifications` にすると成功する
- [ ] `src/` に `package.json` や `states.inc.php` を置いた PR で `lint:repo` が失敗する

第 2b 段階（T-006 の後）:

- [ ] `static` ジョブが Studio からスタブを取得して PHPStan（level 5）と `tsc` を実行し、Secrets が無いときは警告つきでスキップして成功する

第 3 段階（T-012 の後）:

- [ ] 生成後の `src/img/` が、1 ファイル 2,000,000 バイト・総量 15,000,000 バイト・4096 px・ファイル名の規則を満たさないとき、検査が失敗する
