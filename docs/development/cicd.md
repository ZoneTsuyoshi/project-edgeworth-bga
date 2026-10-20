# CI/CD の方針

> ステータス: **lint は第 1〜2 段階、アセット（スプライト）の検査は第 3 段階を導入済み、BGA Studio への同期は段階 2（本番 `sync` と削除リスト）を導入済み。`push` トリガーは作者の手動 `sync` の成功後に有効化**。lint は [../specs/005-ci-lint.md](../specs/005-ci-lint.md) に基づく段階導入で、このドキュメントは方針を示し、具体的な設計は仕様書を正とします。同期（T-006）の設計は [../specs/006-bga-sync.md](../specs/006-bga-sync.md) を正とします。

## 全体像

```text
PR 作成・更新 ──▶ CI: lint ──▶（通過 + ユーザー承認）──▶ squash merge
                                                              │
main への push ──▶ CD: src/ を BGA Studio へ SFTP 同期 ◀──────┘（T-006。push は手動 sync の成功後に有効化。今は手動のみ）
```

## CI: lint チェック

GitHub Actions のワークフロー `Lint`（[../../.github/workflows/lint.yml](../../.github/workflows/lint.yml)）が、`main` 向けの PR、`main` への push、手動実行（`workflow_dispatch`）、他ワークフローからの呼び出し（`workflow_call`）で動きます。チェックの実体は `package.json` の `scripts` にあり、ワークフローはそれを呼ぶだけです。ローカルでも同じコマンドで実行できます。lint は検査のみで、ファイルを書き換えません。

### 導入済み（第 1〜2 段階）

| ID | 検査 | ジョブ | ローカルでの実行 |
| --- | --- | --- | --- |
| M1 | Markdown の書式（markdownlint-cli2） | `markdown` | `npm run lint:md` |
| M2 | Markdown 内の内部リンク切れ（lychee、オフライン） | `markdown` | `npm run lint:links`（`brew install lychee` が必要） |
| S1 | 認証情報の混入（gitleaks。全履歴） | `secrets` | ローカル実行は不要（CI のみ） |
| S2 | 禁止ファイル・`src/` 衛生（`scripts/lint/check-repo.mjs`） | `repo` | `npm run lint:repo` |
| R1 | `data/` の変更禁止（`scripts/lint/check-data-unchanged.mjs`） | `repo` | `npm run lint:data`（`origin/main` との差分） |
| P1・P2 | PHP の構文（php-parallel-lint）と規約（PHP_CodeSniffer。PSR-12 ベース、`phpcs.xml.dist`） | `php` | `composer lint`（PHP が無い環境では `bash tests/run-unit.sh` と同じ Docker イメージ `pe-test-php` で実行） |
| P2' | 全 PHP が `declare(strict_types=1);` で始まる（`scripts/lint/check-strict-types.mjs`） | `node` | `npm run lint:strict` |
| P4 | BGA のフレームワークを継承するクラスの独自メソッドが `pe` で始まる（`scripts/lint/check-method-prefix.mjs`。許可リストと `// pe-lint: allow <理由>` は仕様書 005 §2.10） | `node` | `npm run lint:prefix` |
| J1・D1 | JavaScript（ESLint）と JSONC の構文（`eslint.config.mjs`） | `node` | `npm run lint:js` |
| C1 | CSS（Stylelint） | `node` | `npm run lint:css` |
| — | lint スクリプトの自己テスト（`pe` 接頭辞の事例ほか。`node --test`） | `node` | `npm run test:lint` |

- `npm run lint` は `lint:md`・`lint:repo`・`lint:data`・`lint:cards`・`lint:js`・`lint:strict`・`lint:prefix`・`lint:css` を順に実行します（PHP の `composer lint` と自己テスト `test:lint` は含みません）。lychee と gitleaks は Node 以外のバイナリが要るため含みません。**PR を作る前に `npm run lint` を実行します。**
- `data/` はゲームの原本です。R1 は `data/` の既存ファイルの変更・削除・移動を失敗にし、新規追加は許可します。作者が承知のうえで差し替える場合は、`check-data-unchanged.mjs` の `ALLOWED_CHANGES` にパスと理由を足す PR を出します。
- 必須とするチェックは集約ジョブ `gate`（表示名 `Lint gate`）の 1 つです。`gate` は `markdown`・`secrets`・`repo`・`php`・`node` が成功したときだけ成功します。段階が進んだら `needs` にジョブを足します。
- `php` ジョブは `src/` に PHP が無い間、手順をスキップして成功します。`node` ジョブは対象ファイルが無くても成功します。
- `tsconfig.json` は第 2b 段階（`tsc`）用で、今は置いてあるだけです。

### 単体テストと結合テスト（PHPUnit）

別のワークフロー `Test`（[../../.github/workflows/test.yml](../../.github/workflows/test.yml)）に 2 つのジョブがあります。

- `PHPUnit`: `composer test`（スイート `unit`。DB 不要。ローカルは `npm run test:unit`）
- `PHPUnit (MariaDB)`: `composer test:integration`（スイート `integration`。`services` で MariaDB 11 を立て、`src/dbmodel.sql` を流してデータ層を検査する。ローカルは `npm run test:integration`）

仕様書 005 §2.12 の提案に沿って lint とは分けており、**`Lint gate` には含めません**（lint は `workflow_call` で同期ワークフローからも呼ばれるため、テストの失敗で同期が止まる範囲を別に決めたい）。ただし失敗した PR はマージしないので、マージ前の `gh pr checks` で `PHPUnit` と `PHPUnit (MariaDB)` も確認します。

### アセット（スプライト）

別のワークフロー `Assets`（[../../.github/workflows/assets.yml](../../.github/workflows/assets.yml)、ジョブ表示名 `Sprites`）が、`data/` の原本から webp のスプライトを生成して検査します。`data/`・スプライト生成スクリプト・`package.json`・ワークフロー自身を変えた PR と `main` への push だけで動きます（それ以外の PR では実行されず、表示も出ません）。

| 手順 | コマンド | 内容 |
| --- | --- | --- |
| 生成 | `npm run build:sprites` | `src/img/pe_*.webp`（base 9 + pieces）、`src/img/zoom/pe_zoom_*.webp`（6）、配置情報 `src/modules/js/cards/sprites.js` と `build/sprites/manifest.json`。生成の最後に検査も走る。`-- --flat-zoom` で zoom も `img/` 直下に出す（012 未確定事項 No.9。Studio でサブフォルダを確認するまでの逃げ道）、`-- --verify-determinism` で 2 回生成して一致を確かめる |
| 検査 | `npm run check:sprites` | 1 ファイル 2,000,000 B 以下、総量 15,000,000 B 以下（10,000,000 B 超で警告）、1 辺 4096 px 以下、ファイル名 `^pe_[a-z0-9_]+\.webp$`、`img/` 直下 10 個以上で警告、配置情報が並び順の規則と一致、JS の配置情報がマニフェストと一致 |

- 生成物はコミットしません（`.gitignore` 対象。`lint:repo` が追跡されていたら失敗させます）。ローカルでは初回に 1〜2 分かかります（約 200 枚の原本を読む）。
- **`Lint gate` には入れていません**（005 未確定事項 No.6 の暫定案の範囲）。理由は、(1) lint は `workflow_call` で同期ワークフローからも呼ばれ、生成の遅さ（約 1 分）や原本の読み込み失敗で同期が止まる範囲を広げたくない、(2) 同期（T-006）は同期の直前に `npm run build:sprites` を自分で実行して同じ検査を通す設計で、二重に検査する必要が薄い、(3) `PHPUnit` と同じく、`gh pr checks` で確認すれば足りる。マージ前に `Sprites` が成功であることを確かめます。gate への編入は T-006 の同期ワークフローの設計時に再検討します。

### 今後の段階（仕様書 005 §3。第 2 段階は導入済み）

| 段階 | 追加する検査 | 前提 |
| --- | --- | --- |
| 第 2b 段階 | PHPStan、`tsc`（BGA のスタブを Studio から取得） | T-006 の接続が確立し、Secrets / Variables が登録済み |
| 第 3 段階 | アセットの上限（A1） | 導入済み（下の「アセット（スプライト）」。`Lint gate` には未編入） |

## マージの運用

- **`main` の保護は設定しません。** 代わりに、**`Lint gate` が通らない PR はメインエージェントがマージしません。** マージ前に `gh pr checks <PR 番号>` で `Lint gate`・`PHPUnit`・`PHPUnit (MariaDB)` が成功であることを確かめます。サブエージェントはマージしません。Dependabot の PR も同じです。
- `main` への push でも lint は実行されます。直接 push や運用ミスを事後に検知するためです。

## CD: BGA Studio への自動同期（段階 2 導入済み。push は未有効）

ワークフロー `BGA sync`（[../../.github/workflows/bga-sync.yml](../../.github/workflows/bga-sync.yml)）と [../../scripts/bga-sync/sync.sh](../../scripts/bga-sync/sync.sh) で、`src/` を BGA Studio のプロジェクトフォルダ `/projectedgeworth` へ SFTP（lftp）で転送する仕組みです。設計は [../specs/006-bga-sync.md](../specs/006-bga-sync.md) を正とし、姉妹プロジェクト minimum-journey-bga の実装を流用しています。

| 段階 | 内容 | 状態 |
| --- | --- | --- |
| 1 | `workflow_dispatch` の `mode: test`（接続して `ls` のみ）と `mode: dry-run`（転送せず予定を表示）。Studio へは書き込まない | 導入済み |
| 2 | `mode: sync`（Studio へ書き込み、転送後に `remote-delete.txt` のファイルを削除）。雛形の取り込み（`src/` の `Game.php` など）と同時に導入 | 導入済み |
| 2（続き） | `push` トリガー（`main` への push で自動同期）。ワークフローにコメントアウトして置いてある | 未有効。作者が手動 `sync` を 1 回成功させ、Studio で Express Start を確認した後、別の PR でコメントを外す（仕様書 006 §5.3） |

- **手動実行:** GitHub の Actions タブで `BGA sync` を選び、`Run workflow`（`main` ブランチ）で `mode` を選ぶ。結果は Job Summary に出る。`main` 以外のブランチでは `sync` ジョブが動かない。
- **流れ:** `Lint`（`lint.yml` を `workflow_call`。`Lint gate` が失敗したら以降は動かない）→ `sync` ジョブ（設定の確認 → lftp 導入 → `test` 以外のとき `npm ci`・`build:sprites`・`check:sprites` → 一時鍵の作成 → `sync.sh` → 鍵の削除）。
- **設定が未登録のとき:** 警告を出してスキップし、成功終了する。
- 認証情報（秘密鍵 `BGA_SSH_PRIVATE_KEY`、ユーザー `BGA_SFTP_USER`）は GitHub Secrets、ホスト・ポート・パス・known_hosts（`BGA_SFTP_HOST`、`BGA_SFTP_PORT`、`BGA_REMOTE_DIR`、`BGA_SSH_KNOWN_HOSTS`）は Variables で管理し、リポジトリには置かない。
- 同期対象は **`src/` のみ**（スプライトは同期の直前に `data/` から生成し、コミットしない）。`--delete` は使わない。サーバーから消したいファイルは `scripts/bga-sync/remote-delete.txt`（1 行 1 パス、`src/` からの相対パス）に列挙し、`sync` の転送後に `rm -f` する（ファイルのみ）。`dry-run` は削除予定（サーバーにあるか）も表示する。`sync` の転送が失敗したら 1 回だけ再実行する。
- ローカルでも `scripts/bga-sync/sync.sh <test|dry-run>` を、環境変数（`BGA_KEY_FILE`、`BGA_KNOWN_HOSTS_FILE`、`BGA_SFTP_USER`、`BGA_SFTP_HOST`、`BGA_SFTP_PORT`、`BGA_REMOTE_DIR`）を渡して実行できる（要 lftp）。手元で実行してよいのは `test` と `dry-run` までで、`sync` は GitHub Actions から行う。
- **`push` トリガーを有効にする手順:** (1) Actions の `BGA sync` を `mode: sync` で手動実行して成功させる、(2) Studio で新しいゲームを作成して Express Start が動くことを確認する（仕様書 006 §8）、(3) `bga-sync.yml` の `# push:` から始まるコメントブロックを外す PR を出す。

## ユーザー側で必要な準備

BGA Studio への同期の導入前に、リポジトリオーナー側で SSH 鍵の登録と Secrets / Variables の設定が必要です（作者の作業 5・6。[../tasks.md](../tasks.md) を参照）。
