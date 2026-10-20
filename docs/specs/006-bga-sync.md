# 006: BGA Studio への自動同期

| 項目 | 内容 |
| --- | --- |
| ステータス | Approved |
| 対応タスク | T-006 |
| 作成日 | 2026-10-20 |
| 関連仕様書 | [004 BGA プロジェクト構成・技術方針](004-bga-architecture.md)（§3 構成、§4 JS、未確定事項 No.1）、[005 CI（lint）](005-ci-lint.md)（`Lint gate`、`workflow_call`）、[012 UI・アセットの設計](012-ui-assets.md)（§7 スプライト、未確定事項 No.9） |

## 目的

`main` にマージされた `src/` と、CI が `data/` から生成するスプライトを、GitHub Actions が SFTP で BGA Studio のプロジェクトフォルダ `/projectedgeworth` へ自動転送する仕組みを定める。姉妹プロジェクト minimum-journey-bga（以下「姉妹」）で稼働中の仕組みを流用するため、姉妹の実ファイルを読んだうえで、このリポジトリで変える点を明らかにする。あわせて、2026-10-20 に Studio からダウンロードした初期ファイル（雛形）の内容を調べ、004 の未確定事項 No.1（名前空間）に答える。

## スコープ

- 対象: 全体の流れ、接続情報、転送方式、同期の対象と除外、雛形の調査結果と `src/` との差分の扱い、初回同期の手順、ワークフロー、dry-run と手動実行、姉妹から変える点、実装時に追加・変更するファイル
- 対象外: ワークフロー・スクリプトの実装（本書の承認後に別 PR）、`src/` の作成（雛形の取り込みは別 PR。§4）、lint の内容（005）、スプライトの設計（012）、Studio 側の操作の自動化
- 本書の作成中は Studio へ**読み取りだけ**で接続した。Studio への書き込み・削除は一切していない。認証情報は記載しない。

## 記法

- **【確認】** 実ファイル・実機で確認した事実。確認日と確認方法を添える。
- **【提案】** 本書の提案。
- **【未確認】** 確認できなかった事項。「未確定事項」に挙げる。
- 姉妹の「実装」は 2026-10-20 時点の `/Users/zone/Developments/minimum-journey-bga` の実ファイル（`.github/workflows/bga-sync.yml`、`scripts/bga-sync/sync.sh`、`scripts/bga-sync/remote-delete.txt`、`docs/specs/005-bga-sync.md`）を指す。姉妹の仕様書と実装には差があり（§9）、差がある点は実装を正とする。

## 仕様

### 1. 全体の流れ

```text
PR（Lint gate 通過）──squash merge──▶ main
                                       │ push（src/・data/・生成スクリプト等の変更時）
                                       ▼
                    ┌────────────── bga-sync.yml ──────────────┐
                    │ job 1: lint（lint.yml を workflow_call）   │
                    │    └ Lint gate が失敗したら以降を実行しない │
                    │ job 2: sync                                │
                    │   0. 設定の有無を確認（未設定ならスキップ）│
                    │   1. checkout                              │
                    │   2. lftp を導入                           │
                    │   3. npm ci → build:sprites → check:sprites│
                    │      data/ → src/img/（コミットしない）    │
                    │   4. 鍵・known_hosts を一時ファイルに      │
                    │   5. sync.sh: src/ → /projectedgeworth     │
                    │   6. remote-delete.txt のファイルを削除    │
                    │   7. 結果を Job Summary に出力             │
                    │   8. 一時鍵を削除（always）                │
                    └────────────────────────────────────────────┘
                                       │ SFTP（ポート 2022、SSH 鍵）
                                       ▼
                       BGA Studio: /projectedgeworth（1 対 1）
                                       │
                       作者が Studio 側で必要な手動操作（§8）
```

- 同期の方向は常に GitHub → Studio の一方向。Studio 側で直接編集しない（次の同期で上書きされる）。
- 手動実行（`workflow_dispatch`）は同じワークフローで、`mode` を選ぶ（§6）。
- 姉妹と同じ構成。変える点は §9 に集約する。

### 2. 接続情報

**【確認】** 2026-10-20、`gh secret list` / `gh variable list` で、このリポジトリに次の名前が登録済みであることを確認した（値は表示していない）。鍵は姉妹と共用で、作者が登録した。

| 種別 | 名前 | 内容 | 値の出どころ |
| --- | --- | --- | --- |
| Secret | `BGA_SSH_PRIVATE_KEY` | SFTP 用の SSH 秘密鍵（RSA 4096、パスフレーズなし） | 作者の手元の鍵（姉妹と共用） |
| Secret | `BGA_SFTP_USER` | Studio の SFTP ログイン名 | 作者のアカウント（姉妹と同じ） |
| Variable | `BGA_SFTP_HOST` | `1.studio.boardgamearena.com` | BGA 公式ドキュメント |
| Variable | `BGA_SFTP_PORT` | `2022` | 同上 |
| Variable | `BGA_REMOTE_DIR` | `/projectedgeworth` | Studio のプロジェクト名 |
| Variable | `BGA_SSH_KNOWN_HOSTS` | Studio のホスト鍵（`[1.studio.boardgamearena.com]:2022` 形式の行） | 姉妹の Variable と同じ内容（`gh variable get BGA_SSH_KNOWN_HOSTS -R ZoneTsuyoshi/minimum-journey-bga`）。公開情報 |

- **【確認】** 手元の SFTP 接続（`sftp -P 2022 -i ~/.ssh/bga_studio_rsa -o IdentitiesOnly=yes`、ホスト鍵は上記 Variable を一時ファイルに書いて `StrictHostKeyChecking=yes`）で、`/projectedgeworth` を読み取れることを確認した（2026-10-20）。
- 秘密鍵・ユーザー名は Secrets だけに置く（CLAUDE.md の絶対ルール 7）。ホスト、ポート、リモートパス、known_hosts は公開情報なので Variables に置く。
- このリポジトリは非公開（`gh repo view` で確認）。姉妹と同様、`environment` は使わない（§9）。
- 鍵のローテーションは姉妹の運用に従う。鍵を差し替えるときは、姉妹と本リポジトリの両方の Secret を更新する。

### 3. 転送方式【提案】

姉妹の実装（lftp の `mirror --reverse`）を、そのまま流用する。

| 論点 | 方針 | 根拠 |
| --- | --- | --- |
| ツール | `lftp`（`ubuntu-latest` に `apt-get install`）。サードパーティ Action は使わない。使う Action は GitHub 公式の `actions/checkout`、`actions/setup-node` のみで、いずれもコミット SHA で固定する | 姉妹の実装 |
| 接続 | `sftp:connect-program` に `ssh -a -x -i <鍵> -o IdentitiesOnly=yes -o UserKnownHostsFile=<known_hosts> -o StrictHostKeyChecking=yes -o BatchMode=yes` を渡し、`open -p <ポート> -u <ユーザー>,dummy sftp://<ホスト>`。`dummy` は lftp のパスワード入力待ちを避けるための値で、鍵認証が使われる | 姉妹の実装。手元でも同じ形で接続できた |
| 差分判定 | 全転送を基本とし、lftp の既定の比較（サイズと時刻）に任せる。CI の checkout は時刻が毎回変わるため、実質は全転送 | 姉妹の実装 |
| 削除 | `--delete` は付けない。サーバー上にだけあるファイルは残る。消したいファイルだけを `scripts/bga-sync/remote-delete.txt` に列挙し、`sync` の転送後に `rm -f` する（ファイルのみ。ディレクトリは消さない） | 姉妹の実装。Studio が配置するファイル（`_ide_helper.php` など）を誤って消さない |
| 転送の段階 | (1) `src/img`（サブフォルダを含む）→ (2) `src/modules`（再帰）→ (3) `src/` 直下のファイルのみ（`--no-recursion`） | 姉妹の実装。画像を先にし、設定・スキーマを最後にして、部分失敗時の不整合を小さくする |
| オプション | `--no-perms --parallel=2 --verbose`。`net:max-retries 3`、`net:timeout 30`、`cmd:fail-exit yes` | 姉妹の実装 |
| 失敗時 | `sync` は転送が失敗したら 1 回だけ再実行する（冪等） | 姉妹の実装 |
| 入力の検査 | ユーザー、ホスト、ポート、リモートパス、鍵のパスは、`lftp -c` に文字列で渡す前に許可文字を絞る（コマンド注入の防止）。`remote-delete.txt` の各行は相対パスのみ許可し、絶対パスと `..` を拒否する | 姉妹の実装 |
| ログ | `set -x` を使わない。秘密鍵は `printf` でファイルに書き（権限 600）、`echo` しない。lftp の `debug` を有効にしない | 姉妹の実装 |

### 4. 同期の対象と除外

#### 4.1 対象

| 対象 | 内容 |
| --- | --- |
| `src/` のコミット済みファイル | `src/` と Studio の `/projectedgeworth` は 1 対 1（004 §3） |
| スプライト | 同期の直前に `npm run build:sprites` で `data/` から生成し、`src/img/`（と `src/img/zoom/`）に出力する。**コミットしない**（`.gitignore` 済み。012 §7.1）。生成後に `npm run check:sprites`（1 枚 2,000,000 B 以下、総量 15,000,000 B 以下、1 辺 4096 px 以下など。012 §7.5）を実行し、失敗したら転送しない |

- `docs/`、`data/`、`scripts/`、`tests/`、`tools/`、設定ファイル類は転送しない（`src/` 以外は転送の対象に入らない）。
- `build/sprites/manifest.json` など `src/` の外の生成物は転送しない。

#### 4.2 除外（Studio にだけあるファイルは触らない）

次のファイルは `src/` に置かず、`--delete` も使わないので、同期後も Studio に残る。

| Studio のファイル | 扱い |
| --- | --- |
| `_ide_helper.php`、`bga-framework.d.ts` | Studio が配置・更新する開発用スタブ（再配布不可）。`src/` に取り込まない。lint の `static` ジョブ（005 第 2b 段階）が使う場合は `tools/bga/` に置き、コミットしない |
| `README`（`img/`、`misc/`、`modules/`）、`misc/` | Studio の説明ファイル。残す |
| `package.json`、`rollup.config.mjs`、`tsconfig.json`、`jsconfig.json` | TypeScript テンプレート一式。使わないが残す（`src/` の外のリポジトリ直下にある同名ファイルとは無関係）。**【提案】** 削除しない |
| `modules/css/`（空のディレクトリ） | 残す |

#### 4.3 削除リスト（`scripts/bga-sync/remote-delete.txt`）【提案】

初回の本番同期で削除するのは、雛形のサンプル実装のうち、このゲームの状態クラスと衝突・混在するファイルと、使わないテンプレートの原本。1 行 1 パス、`src/` からの相対パス（Studio 上では `/projectedgeworth/` 配下の同じ相対パス）。

```text
modules/php/States/PlayerTurn.php
modules/php/States/NextPlayer.php
modules/php/States/EndScore.php
src-disabled/scss/Game.scss
src-disabled/ts/libs.ts
src-disabled/ts/types.d.ts
src-disabled/ts/Game.ts
src-disabled/ts/States/PlayerTurn.ts
```

- 先頭の 3 行（`States/*.php`）は姉妹も同じ行を列挙している。Studio の `Game.php` の雛形が `States\PlayerTurn` を参照するため、**このゲームの `Game.php` に置き換わった後**でなければ、サンプルの状態クラスを消してはならない（§5）。状態クラスのクラス名・ID が雛形（`PlayerTurn` = 10、`NextPlayer` = 90、`EndScore` = 98）と重なる場合は、上書きされるので削除リストから外す（009 の状態一覧で確定）。
- `src-disabled/` の 5 ファイルは姉妹の `remote-delete.txt` には**入っていない**（姉妹は残している）。本書は整理のために消す案とするが、残しても動作に影響しない。未確定事項 No.2。
- ディレクトリそのもの（`src-disabled/`、`States/` など）は `rm -f` では消えない。空のディレクトリは残る。

### 5. 初回同期の手順

#### 5.1 現状（2026-10-20 時点のリポジトリ）

**【確認】** このリポジトリの `src/` には `dbmodel.sql` と `modules/php/` 以下のクラス群（`Board`、`Data`、`Effects`、`Flow`、`Material`、`Scoring`、`Setup`）があるが、**`Game.php`、`modules/php/States/`、`modules/js/`、`gameinfos.jsonc`、`gameoptions.jsonc`、`gamepreferences.jsonc`、`stats.jsonc`、`projectedgeworth.css`、`LICENCE_BGA` が無い**。この状態で本番同期すると、Studio 側の雛形（`Game.php`、`States/*`、`Game.js`、設定ファイル）に新しいクラスだけが混在する。

#### 5.2 雛形と `src/` の差分の扱い

| 雛形のファイル | `src/` への取り込み | 理由 |
| --- | --- | --- |
| `LICENCE_BGA` | 取り込む | 004 §3 |
| `gameinfos.jsonc`、`gameoptions.jsonc`、`gamepreferences.jsonc`、`stats.jsonc` | 取り込み、004 §7・§8 の値に書き換える | 004 §3。初期値は `game_name: "My Great Game"` など（§7.4） |
| `dbmodel.sql` | 取り込まない（`src/dbmodel.sql` が既にある） | 雛形はコメントのみ。I-05 で作成済み |
| `projectedgeworth.css` | 取り込み、素の CSS として育てる | 004 §3。雛形は SCSS をコンパイルした出力（コメントのみ） |
| `modules/php/Game.php` | 取り込み、薄い入口に書き換える | 名前空間の変更を含む（§7.1、未確定事項 No.1）。雛形の `$CARD_TYPES`、`playerEnergy` などのサンプルは除く |
| `modules/php/States/*.php`（3 つ） | サンプルは取り込まない。状態クラスは 009 に従い新規作成 | 雛形の形（コンストラクタの `id:` / `type:`、`getArgs`、`#[PossibleAction]`、`zombie`）だけを参考にする（§7.3） |
| `modules/js/Game.js` | 取り込まない。004 §4 の方針（素の JS を分割、`export class Game`）で新規作成 | 雛形は TypeScript をバンドルした出力 |
| `src-disabled/`、`package.json`、`rollup.config.mjs`、`tsconfig.json`、`jsconfig.json` | 取り込まない | TypeScript テンプレート。004 §3 |
| `_ide_helper.php`、`bga-framework.d.ts` | 取り込まない | §4.2 |
| `img/README`、`misc/README`、`modules/README` | 取り込まない | Studio の説明 |

非推奨ファイル（`states.inc.php`、`material.inc.php`、`*.action.php`、`*.view.php`、`.tpl`）は、**雛形に含まれていなかった**（§7.1。004 §3 の確認事項に回答）。

#### 5.3 手順

| 順 | 作業 | 担当 | 備考 |
| --- | --- | --- | --- |
| 1 | 雛形の取得（読み取りのみ） | エージェント | 済（2026-10-20）。`/Users/zone/.cache/pe-studio-initial/projectedgeworth/` にある（リポジトリの外） |
| 2 | 本仕様書の承認 | 作者 | 未確定事項 No.1・No.2 の回答を含む |
| 3 | 実装 PR（段階 1）: 接続テスト（`mode: test`）と dry-run のワークフロー・スクリプト。**`push` トリガーは入れない** | エージェント | §10 |
| 4 | 作者が `workflow_dispatch` で `test`、`dry-run` を実行して結果を確認 | 作者 | dry-run は書き込まない |
| 5 | 雛形の取り込み PR: `Game.php`、`States/`、`Game.js`、設定ファイル、CSS、`LICENCE_BGA` を `src/` に作る。名前空間を決定どおりに揃える（§7.1） | エージェント | 009 の状態クラス、010 の `getAllDatas` などの実装範囲と合わせて決める。最小は「起動できる骨格」 |
| 6 | 実装 PR（段階 2）: `push` トリガーと `mode: sync`、`remote-delete.txt` を有効化 | エージェント | 5 の PR がマージされていること |
| 7 | 作者が `workflow_dispatch`（`mode: sync`）で初回の本番同期を、見ている状態で実行 | 作者 | 成功を確認してから `push` トリガーを有効にする |
| 8 | Studio で再読込・Express Start・動作確認（§8） | 作者 | `img/zoom/` のサブフォルダが使えるかもここで確認（未確定事項 No.3） |

- 手順 5 より前に本番同期をしない。`mode: sync` を手順 6 で初めて有効にすることで、これを防ぐ。
- 本番同期の前に、`mode: dry-run` の出力で「転送予定のファイル」と「削除リストの対象」が想定どおりかを作者が確認する。

### 6. ワークフロー【提案】

姉妹の `.github/workflows/bga-sync.yml` を土台にし、次のとおりにする。

| 項目 | 仕様 |
| --- | --- |
| ファイル | `.github/workflows/bga-sync.yml`（名前 `BGA sync`）。スクリプトは `scripts/bga-sync/sync.sh` |
| トリガー | `push`（`branches: [main]`、`paths` は下記）と `workflow_dispatch` |
| `workflow_dispatch` の入力 | `mode`: `test`（接続して `ls` のみ）/ `dry-run`（転送せず予定を表示）/ `sync`（本番）。既定は `test` |
| 権限 | ワークフロー全体は `contents: read`。`lint` は 005 の `lint.yml` が要求する `pull-requests: read` を追加（読み取りのみ） |
| concurrency | `group: bga-studio-sync`、`cancel-in-progress: false`（転送中は止めない。待機中は最新 1 件に置き換わる） |
| lint | job `lint` が `uses: ./.github/workflows/lint.yml`（`workflow_call` 対応済み。005）。job `sync` は `needs: lint`。`lint.yml` の集約ジョブ `Lint gate` が失敗すれば `sync` は動かない |
| 実行条件 | `sync` は `github.ref == 'refs/heads/main'` のときだけ（PR ブランチからの同期は許可しない） |
| 設定の確認 | 先頭の step で Secrets / Variables の有無を確認し、未登録なら警告を出して以降を飛ばし成功終了する（姉妹の実装。`main` への push が失敗し続けないため） |
| タイムアウト | `sync` は `timeout-minutes: 15` |
| Node | `mode` が `test` 以外のとき、`actions/setup-node`（`.nvmrc`、`cache: npm`）→ `npm ci` → `npm run build:sprites` → `npm run check:sprites` |
| 鍵 | `$RUNNER_TEMP` に一時ファイル（権限 600）を作る。形式を `ssh-keygen -y` で検査（鍵は出力しない）。最後に `if: always()` で削除 |

`push` の `paths`（スプライトの原本と生成スクリプトの変更でも同期する）:

```yaml
paths:
  - 'src/**'
  - 'data/**'
  - 'scripts/build-sprites.mjs'
  - 'scripts/check-sprites.mjs'
  - 'scripts/lib/**'
  - 'scripts/bga-sync/**'
  - 'package.json'
  - 'package-lock.json'
  - '.github/workflows/bga-sync.yml'
```

- `docs/**` や、他のワークフローだけの変更では同期しない。
- `paths` の判定が squash merge のマージコミットで期待どおりに働くかは、**【未確認】**（姉妹では動作している前提だが、本リポジトリで初回に確かめる）。

#### 6.1 モード

| モード | 動作 | 書き込み |
| --- | --- | --- |
| `test` | 接続して `ls`（ホーム、`$BGA_REMOTE_DIR`、末尾スラッシュ付き）。結果を Job Summary に出す。スプライトは生成しない | なし |
| `dry-run` | lint → スプライト生成と検査 → `mirror --dry-run` の出力（転送予定の一覧）を Job Summary に出す | なし |
| `sync` | lint → スプライト生成と検査 → 転送 → `remote-delete.txt` の削除 → 完了メッセージ | あり |
| `push`（`main`） | `sync` と同じ | あり |

- ローカルでも `scripts/bga-sync/sync.sh <mode>` を、必要な環境変数（`BGA_KEY_FILE`、`BGA_KNOWN_HOSTS_FILE`、`BGA_SFTP_USER`、`BGA_SFTP_HOST`、`BGA_SFTP_PORT`、`BGA_REMOTE_DIR`）を渡して実行できる。手元の確認は `dry-run` に限る。
- 失敗したら、同じコミットで `workflow_dispatch` から再実行する（冪等）。

### 7. 雛形の調査結果（Studio の初期ファイル、2026-10-20）

**【確認】** 2026-10-20、`sftp get -r /projectedgeworth` で `/Users/zone/.cache/pe-studio-initial/projectedgeworth/` へ取得し、全ファイルを読んだ（Studio には書き込んでいない）。

#### 7.1 名前空間とクラス（004 未確定事項 No.1 への回答の材料）

| 項目 | 雛形の値 |
| --- | --- |
| 名前空間 | **`Bga\Games\projectedgeworth`**（プロジェクト名と同じ**すべて小文字**）。状態クラスは `Bga\Games\projectedgeworth\States` |
| メインクラス | `Bga\Games\projectedgeworth\Game`（`\Bga\GameFramework\Table` を継承）。ファイルは `modules/php/Game.php` |
| 状態クラス | `modules/php/States/` に 3 つ。`PlayerTurn`（`id: 10`、`StateType::ACTIVE_PLAYER`）、`NextPlayer`（`id: 90`、`StateType::GAME`）、`EndScore`（`id: 98`、`StateType::GAME`。`const ST_END_GAME = 99` を同じファイルで定義） |
| 非推奨ファイル | **無い**（`states.inc.php`、`material.inc.php`、`*.action.php`、`*.view.php`、`*.tpl` は含まれていない） |
| ファイルの拡張子 | 設定は `.jsonc`（`gameinfos.jsonc`、`gameoptions.jsonc`、`gamepreferences.jsonc`、`stats.jsonc`）。004 の想定どおり |

**姉妹との違い:** 姉妹の雛形の名前空間は `Bga\Games\MinimumJourney`（大文字小文字あり）だった（姉妹 003 の U1、2026-10-14）。このリポジトリの雛形は小文字の `projectedgeworth` で、**004 の暫定案 `Bga\Games\ProjectEdgeworth` と一致しない**。現在の `src/modules/php/` の全クラス、`composer.json` の `autoload`、`phpcs.xml.dist` などは `ProjectEdgeworth` を使っている。

BGA のクラス読み込みが名前空間の大文字小文字に依存するかは、公式ドキュメントに記述が無く、読み取りだけでは確かめられない（**【未確認】**。PHP のクラス名の解決そのものは大文字小文字を区別しないが、BGA 側の読み込みの実装は不明）。そのため、**確実なのは雛形の値に合わせること**で、004 の No.1 の回答として次を提案する（未確定事項 No.1）。

- 名前空間は `Bga\Games\projectedgeworth`（雛形の値）に統一する。
- 置き換えは、`src/modules/php/` の全 `namespace` / `use`、`composer.json` の `autoload`、`phpstan.neon.dist`、`phpcs.xml.dist`、`tests/` の `use`、`scripts/lint/` と各仕様書の記述。機械的な置換で、雛形の取り込み PR（§5.3 手順 5）の前に別 PR で行う。
- テストの名前空間 `ProjectEdgeworthTests\` はテスト専用なので変えない。

#### 7.2 ファイル構成

```text
/projectedgeworth/
├── LICENCE_BGA
├── _ide_helper.php            # Studio のスタブ（約 4,000 行）
├── bga-framework.d.ts         # JS の型定義（約 1,600 行）
├── dbmodel.sql                # コメントのみ
├── gameinfos.jsonc  gameoptions.jsonc  gamepreferences.jsonc  stats.jsonc
├── projectedgeworth.css       # コメントのみ（SCSS のコンパイル出力）
├── jsconfig.json  package.json  rollup.config.mjs  tsconfig.json   # TypeScript テンプレート
├── img/README
├── misc/README                # 「コミットされない・デプロイされない」領域
├── modules/
│   ├── README
│   ├── css/                   # 空
│   ├── js/Game.js             # TypeScript のバンドル出力（約 7.6 KB）
│   └── php/
│       ├── Game.php
│       └── States/{PlayerTurn,NextPlayer,EndScore}.php
└── src-disabled/              # TypeScript のソース（無効化されている）
    ├── scss/Game.scss
    └── ts/{Game.ts,libs.ts,types.d.ts,States/PlayerTurn.ts}
```

- `img/README`: 「サブディレクトリを使わない」「ファイルは 10 未満、1 枚 4 MB まで」。012 の未確定事項 No.9（`img/zoom/`）に関わる（§7.5）。
- `misc/README`: 「`misc/` はソース管理されず、デプロイされない」。ここには何も置かない。

#### 7.3 `Game.php` の構造

- `declare(strict_types=1);`。`use` は `States\PlayerTurn` と `Bga\GameFramework\Components\Counters\PlayerCounter`。
- `class Game extends \Bga\GameFramework\Table`。静的プロパティ `$CARD_TYPES`、プロパティ `PlayerCounter $playerEnergy`（サンプル）。
- コンストラクタ: `parent::__construct()`、`$this->bga->counterFactory->createPlayerCounter('energy')`、`$CARD_TYPES` の初期化（サンプル）。通知デコレーター（`$this->bga->notify->addDecorator(...)`）はコメントアウトされたサンプル。
- メソッド: `getGameProgression()`、`upgradeTableDb($from_version)`、`getAllDatas(int $currentPlayerId): array`（`protected`）、`setupNewGame($players, $options = [])`（`protected`。最後に `activeNextPlayer()` して `PlayerTurn::class` を返す）、`debug_goToState(int $state = 3)`。
- 状態クラスの雛形: `GameState` を継承し、コンストラクタで `parent::__construct($game, id: ..., type: StateType::...)`。`getArgs(): array`、`#[PossibleAction]` を付けた `act*` メソッド（次の状態は `NextPlayer::class` のようなクラス名を返す）、`zombie(int $playerId)`、ゲーム状態は `onEnteringState(int $activePlayerId)`。遷移は**クラス名を返す**方式で、`states.inc.php` は使わない。
- すべて 005 の `pe` 接頭辞検査の許可リスト（`setupNewGame`、`getAllDatas`、`getGameProgression`、`upgradeTableDb`、`onEnteringState`、`getArgs`、`zombie`、`act*`＋`#[PossibleAction]`、`debug_*`）に収まる。雛形に `pe` の付かない独自メソッドは無い。

#### 7.4 JS・CSS・設定の雛形

- `modules/js/Game.js`: TypeScript（`src-disabled/ts/`）を Rollup で 1 ファイルにした出力。`export class Game` がある（`constructor(bga)`、`setup(gamedatas)`、`setupNotifications()`）。状態クラス `PlayerTurn` は同じファイルの中に含まれる（`import` なし）。004 §4 の「Game.js は `export class Game`、状態クラスは `this.bga.states.register`」と一致。
- `projectedgeworth.css`: コメントのみ。`@import` 禁止の注意書きがある。
- `gameinfos.jsonc` の既定値: `game_name: "My Great Game"`、`publisher: "My Publishing Company"`、`publisher_website: "http://www.mypublishingcompany.com/"`、`publisher_bgg_id: 0`、`bgg_id: 0`、`players: [2, 3, 4]`、`suggest_player_number: null`、`not_recommend_player_number: null`、`estimated_duration: 30`、`fast_additional_time: 30`、`medium_additional_time: 40`、`slow_additional_time: 50`、`tie_breaker_description: ""`、`losers_not_ranked: false`、`solo_mode_ranked: false`、`is_coop: 0`、`language_dependency: false`、`player_colors`（12 色）、`favorite_colors_support: true`、`disable_player_order_swap_on_rematch: false`、`game_interface_width: {"min": 740}`、`exception_on_warning: true`。004 §8 の値（`players` や `game_name` など）で上書きする。
- `gameoptions.jsonc`、`gamepreferences.jsonc`: 空のオブジェクト（コメントの例のみ）。`stats.jsonc`: `{"table": {}, "player": {}}`。`dbmodel.sql`: コメントのみ。

#### 7.5 004・012 の未確定事項への示唆

| 事項 | 雛形から分かること |
| --- | --- |
| 004 No.1 名前空間 | 小文字の `projectedgeworth`。§7.1 の提案 |
| 004 No.4 JS の相対 `import` | 雛形は 1 ファイルのバンドルで、分割の可否は示さない。**雛形からは判断できない**。最初の実装 PR で Studio で確認する（004 の暫定案のまま） |
| 004 No.9 / 012 No.9 `img/` のサブフォルダ | `img/README` は「サブディレクトリを使わない」と書く（012 が引用したとおり）。**使えるかは読み取りだけでは分からない**。同期の転送は `img/` を再帰するので、`src/img/zoom/` を置けば転送される。初回の本番同期で Studio 上の動作（拡大表示の画像が読めるか）を確認する。だめなら 012 §7.1 の代替（`img/` 直下に置く）に切り替える |

### 8. 同期後に Studio 側で必要な手動操作

CI が転送しても、Studio 側の再読込は人手が必要。姉妹の仕様書 005 §8 と同じ（姉妹の確認結果を流用）。

| 変更したファイル | 同期後に必要な操作 |
| --- | --- |
| `dbmodel.sql` | 進行中のゲームを破棄し、新しくゲームを開始する |
| `gameinfos.jsonc` | コントロールパネルの「Reload game informations」 |
| `gameoptions.jsonc`、`gamepreferences.jsonc`、`stats.jsonc` | コントロールパネルで該当の再読込 |
| `modules/js/**` | 開いているゲーム画面を再読込（F5）。他のタブも再読込 |
| `projectedgeworth.css` | 強制再読込（Cmd+Shift+R） |
| `modules/php/**` | 新しいゲームを開始（または進行中のゲームを再読込）。状態クラスの `id` や遷移を変えたら、進行中のゲームを破棄 |
| `img/` | 通常は即時反映。ファイルを削除したときはコントロールパネルでの追加操作が必要 |
| いずれの変更後も | Express Start で 1 回、新しいゲームを開始して動作確認 |

- 【提案】`sync` の完了時の Job Summary には、姉妹と同じく「Studio 側の手動操作は本書 §8 を確認」と表示する。変更ファイルから必要な操作を自動で列挙する案は後から足せる（未確定事項 No.5）。

### 9. 姉妹から変える点

| 項目 | 姉妹 | このリポジトリ | 理由 |
| --- | --- | --- | --- |
| プロジェクト名・リモートの場所 | `minimumjourney`、`/minimumjourney` | `projectedgeworth`、`/projectedgeworth` | 別プロジェクト。`BGA_REMOTE_DIR` で切り替わる（コードに名前を書かない） |
| 名前空間 | `Bga\Games\MinimumJourney` | `Bga\Games\projectedgeworth`（雛形の値。未確定事項 No.1） | 雛形の違い（§7.1） |
| スプライトの生成 | `npm run build:sprites` のみ | `build:sprites` の後に `check:sprites` | 012 §7.5 の上限検査を同期の前に実行（005 の未確定事項 No.6 の置き場所の暫定案。同期側で実行） |
| `push` の `paths` | `data/cards/**`、`data/icons/**`、`scripts/build-sprites.mjs`、`scripts/lib/**`、`scripts/bga-sync/**` ほか | `data/**`、`scripts/check-sprites.mjs` を追加し、`data/cards/` と `data/icons/` の細分は `data/**` に | 本ゲームのスプライトは `data/` 全体（盤面・カード・コマ。012 §2）から作る。`assets.yml` の `paths` と揃える |
| 画像のサブフォルダ | 無し（`img/` 直下のみ） | `img/zoom/` を含む（未確定事項 No.3） | 012 §7.1。転送は `src/img` を再帰するので追加の変更は不要 |
| JS の構成 | 単一の `Game.js`（約 2,800 行） | `modules/js/` を分割（`Game.js`、`states/`、`board/`、`cards/`、`ui/`） | 004 §4。転送は `src/modules` を再帰するので追加の変更は不要 |
| CSS ファイル名 | `minimumjourney.css` | `projectedgeworth.css` | プロジェクト名（`src/` 直下の転送でファイル名の指定は不要） |
| 削除リスト | `States/*.php` の 3 行 | `States/*.php` の 3 行に加え、`src-disabled/` の 5 ファイル（案。No.2） | §4.3 |
| lint | 004（姉妹の番号）の lint | 005 の `Lint gate`（`lint.yml` の `workflow_call`） | 本リポジトリの構成。005 でも同期ワークフローから呼ぶ前提で実装済み |
| `environment` | 仕様書は `bga-studio` を提案したが、実装ではコメントアウト（`# environment: bga-studio`） | 使わない | 非公開リポジトリで環境を使えるプランか未確認で、姉妹も使っていない。`main` 限定は `if: github.ref == 'refs/heads/main'` で担保 |
| 手動操作サマリー | 仕様書は `summary.sh` で自動列挙を提案、実装は固定の案内文のみ | 固定の案内文（§8）。自動列挙は後から（No.5） | 姉妹の実装に合わせる |

**姉妹の仕様と実装の差（実ファイルを読んで確認）:** 姉妹の仕様書は「`src-disabled/` などのサーバー専用ファイルは `--delete` を使わないので残る」と書き、実装の `remote-delete.txt` も `src-disabled/` を含まない。つまり姉妹では `src-disabled/` は削除対象にしていない。本書の削除リスト案（§4.3）は、これとは別に整理のために加えた提案である。

### 10. 段階導入【提案】

| 段階 | 内容 | 完了条件 |
| --- | --- | --- |
| 1 | `bga-sync.yml`（`workflow_dispatch` のみ）と `sync.sh`（`test`、`dry-run` のみ）、`docs/development/cicd.md` の更新。`mode: sync` と `push` トリガー、`remote-delete.txt` は**入れない** | 作者が `test` と `dry-run` を実行し、`ls /projectedgeworth` の成功と転送予定の一覧が想定どおり |
| 2 | 雛形の取り込み PR（§5.3 手順 5）のマージ後、`mode: sync`、`remote-delete.txt`、`push` トリガーを追加 | 作者が `mode: sync` を手動で 1 回成功させ、Studio で Express Start が動く。その後に `push` トリガーを有効にする |

- 段階 1 の実装 PR に `src/` の変更は含めない。

### 11. 実装時に追加・変更するファイル

| ファイル | 内容 |
| --- | --- |
| `.github/workflows/bga-sync.yml` | 姉妹のものをコピーし（`sync` と `push` は段階 2 から有効）、§6 の変更（`paths`、`check:sprites`、コメント内の仕様書番号 `006`）を反映 |
| `scripts/bga-sync/sync.sh` | 姉妹のものをコピー。仕様書番号の参照を `006` に。ほぼそのまま |
| `scripts/bga-sync/remote-delete.txt` | §4.3 の内容（承認された範囲） |
| `docs/development/cicd.md` | 「CD: BGA Studio への自動同期」の節を、実際の構成に更新（手順は本書へのリンク） |
| `docs/tasks.md` | T-006 の状態を更新 |

実装の PR は本書の承認後に作る。`src/`、`data/` は触らない。

## エッジケース

- **同期が途中で失敗した**: `sync.sh` が 1 回だけ再実行する。それでも失敗したら、同じコミットで `workflow_dispatch` から再実行する（冪等）。Studio は開発環境なので、一時的な中途半端な状態は許容する。
- **Secrets / Variables が欠けている**: 先頭の step が欠けている名前を警告として出し、以降を飛ばして成功終了する。`main` への push が失敗し続けない。
- **`Lint gate` が失敗している**: `sync` job は実行されない。
- **スプライトの検査（`check:sprites`）が失敗した**: 転送しない。
- **Studio にだけあるファイルとの衝突**: `--delete` を使わないので消えない。同名のファイルは `src/` の内容で上書きされる。
- **サンプルの状態クラスが残った**: `remote-delete.txt` で消すまで残る。`States/` に同じ名前の新しい状態クラスを置けば上書きされる。
- **画像を削除した**: `img/` のファイル削除は `remote-delete.txt` に入れる必要があり、さらにコントロールパネルでの操作が要る（§8）。スプライトのファイル名を変えたら旧名を列挙する。
- **Studio 側で直接編集した**: 次の同期で上書きされる。直接編集は禁止。
- **PR ブランチを Studio で試したい**: 許可しない（`main` 限定）。必要になったら別途仕様化する。
- **鍵の漏えい**: 公開鍵を Studio から外して差し替え、姉妹と本リポジトリの Secret を更新する。

## 未確定事項

| No. | 質問 | 暫定案 | 回答 |
| --- | --- | --- | --- |
| 1 | 名前空間を、Studio の雛形の値 `Bga\Games\projectedgeworth`（小文字）に統一してよいか。004 の暫定案 `ProjectEdgeworth` と異なり、`src/modules/php/` の全クラス、`composer.json` などの置換が要る（機械的、別 PR）。BGA が大文字小文字を区別するかは読み取りだけでは確かめられない | 雛形の値に統一する。確実に動く側に合わせる（姉妹も雛形の値を採用した） | 暫定案のとおり（技術的事項。メインエージェントが判断、2026-10-20） |
| 2 | 初回の削除リストに `src-disabled/` の 5 ファイルを入れてよいか（姉妹は入れていない。残しても動作に影響しない） | 入れる（整理のため）。`States/*.php` 3 つは、状態クラス名が衝突しなければ入れる | 暫定案のとおり（技術的事項。メインエージェントが判断、2026-10-20） |
| 3 | `src/img/zoom/`（サブフォルダ）を使ってよいか。Studio の `img/README` は「サブディレクトリを使わない」と書き、使えるかは読み取りだけでは分からない（012 の No.9 と同じ） | 初回の本番同期（段階 2）で Studio 上の動作を確認する。だめなら `img/` 直下に置く | 暫定案のとおり（技術的事項。メインエージェントが判断、2026-10-20） |
| 4 | スプライトの検査（`check:sprites`）を同期の job に置いてよいか（`assets.yml` の `Sprites` と二重生成になる。005 の No.6 の続き） | 同期の job で `build:sprites` の後に実行する。lint には入れない | 暫定案のとおり（技術的事項。メインエージェントが判断、2026-10-20） |
| 5 | `sync` 完了時の Job Summary に、変更ファイルから必要な手動操作を自動で列挙するか | 姉妹の実装と同じ固定の案内文のみ。自動列挙は運用してから足す | 暫定案のとおり（技術的事項。メインエージェントが判断、2026-10-20） |
| 6 | 設定未登録のときにスキップして成功終了する挙動（姉妹の実装）を残すか。Secrets / Variables は登録済みなので、失敗にする選択もある | 残す（フォークや鍵のローテーション中に `main` への push が失敗し続けない） | 暫定案のとおり（技術的事項。メインエージェントが判断、2026-10-20） |
| 7 | `package.json`、`rollup.config.mjs`、`tsconfig.json`、`jsconfig.json` など TypeScript テンプレートが Studio に残ることを許容してよいか | 許容する（削除しない） | 暫定案のとおり（技術的事項。メインエージェントが判断、2026-10-20） |

## 受け入れ条件

- [ ] 接続情報（Secrets / Variables の名前と値の出どころ）が、値を書かずに記載されている
- [ ] 転送方式、対象と除外、削除リスト、初回同期の手順、ワークフロー、モードが、実装者がこの仕様書だけで導入できる粒度で書かれている
- [ ] 雛形の調査結果（名前空間、`Game.php` の構造、状態クラス、JS、`gameinfos.jsonc` の既定値、`src-disabled/`）が実ファイルに基づいて記載され、004 の未確定事項 No.1 に答える材料になっている
- [ ] 姉妹から変える点が一覧になっている
- [ ] 判断できない点が「未確定事項」に挙がっている
- [ ] 本書の作成で Studio への書き込み・削除をしていない
- [ ] 作者が「未確定事項」に回答し、回答に合わせて更新されたうえでステータスが Approved になる
