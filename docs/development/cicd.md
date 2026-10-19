# CI/CD の方針

> ステータス: **lint は第 1 段階を導入中、BGA Studio への同期は未着手**。lint は [../specs/005-ci-lint.md](../specs/005-ci-lint.md) に基づく段階導入で、このドキュメントは方針を示し、具体的な設計は仕様書を正とします。同期（T-006）の仕様書はまだありません。

## 全体像

```text
PR 作成・更新 ──▶ CI: lint ──▶（通過 + ユーザー承認）──▶ squash merge
                                                              │
main への push ──▶ CD: src/ を BGA Studio へ SFTP 同期 ◀──────┘（T-006。未導入）
```

## CI: lint チェック

GitHub Actions のワークフロー `Lint`（[../../.github/workflows/lint.yml](../../.github/workflows/lint.yml)）が、`main` 向けの PR、`main` への push、手動実行（`workflow_dispatch`）、他ワークフローからの呼び出し（`workflow_call`）で動きます。チェックの実体は `package.json` の `scripts` にあり、ワークフローはそれを呼ぶだけです。ローカルでも同じコマンドで実行できます。lint は検査のみで、ファイルを書き換えません。

### 導入済み（第 1 段階）

| ID | 検査 | ジョブ | ローカルでの実行 |
| --- | --- | --- | --- |
| M1 | Markdown の書式（markdownlint-cli2） | `markdown` | `npm run lint:md` |
| M2 | Markdown 内の内部リンク切れ（lychee、オフライン） | `markdown` | `npm run lint:links`（`brew install lychee` が必要） |
| S1 | 認証情報の混入（gitleaks。全履歴） | `secrets` | ローカル実行は不要（CI のみ） |
| S2 | 禁止ファイル・`src/` 衛生（`scripts/lint/check-repo.mjs`） | `repo` | `npm run lint:repo` |
| R1 | `data/` の変更禁止（`scripts/lint/check-data-unchanged.mjs`） | `repo` | `npm run lint:data`（`origin/main` との差分） |

- `npm run lint` は `lint:md`・`lint:repo`・`lint:data` を順に実行します。lychee と gitleaks は Node 以外のバイナリが要るため含みません。**PR を作る前に `npm run lint` を実行します。**
- `data/` はゲームの原本です。R1 は `data/` の既存ファイルの変更・削除・移動を失敗にし、新規追加は許可します。作者が承知のうえで差し替える場合は、`check-data-unchanged.mjs` の `ALLOWED_CHANGES` にパスと理由を足す PR を出します。
- 必須とするチェックは集約ジョブ `gate`（表示名 `Lint gate`）の 1 つです。段階が進んだら `gate` の `needs` にジョブを足します。

### 今後の段階（仕様書 005 §3）

| 段階 | 追加する検査 | 前提 |
| --- | --- | --- |
| 第 2 段階 | PHP の構文・規約、`strict_types`、`pe` 接頭辞、ESLint、JSONC、Stylelint | `src/` の最初の PHP / JS / CSS がマージ済み |
| 第 2b 段階 | PHPStan、`tsc`（BGA のスタブを Studio から取得） | T-006 の接続が確立し、Secrets / Variables が登録済み |
| 第 3 段階 | アセットの上限 | T-012 のスプライト生成がマージ済み |

## マージの運用

- **`main` の保護は設定しません。** 代わりに、**`Lint gate` が通らない PR はメインエージェントがマージしません。** マージ前に `gh pr checks <PR 番号>` で `Lint gate` が成功であることを確かめます。サブエージェントはマージしません。Dependabot の PR も同じです。
- `main` への push でも lint は実行されます。直接 push や運用ミスを事後に検知するためです。

## CD: BGA Studio への自動同期（未導入）

T-006 で導入します。`main` へのマージをトリガーに `src/` 配下を BGA Studio のプロジェクトフォルダへ SFTP で転送する予定です。

- 同期対象は **`src/` のみ**。`docs/`、`data/`、CI 設定などは転送しない。
- 認証情報（秘密鍵・ユーザー名）は GitHub Secrets、ホスト・ポート・パス・known_hosts は Variables で管理し、リポジトリには置かない。
- 手動実行（`workflow_dispatch`）もできるようにする。

## ユーザー側で必要な準備

BGA Studio への同期の導入前に、リポジトリオーナー側で SSH 鍵の登録と Secrets / Variables の設定が必要です（作者の作業 5・6。[../tasks.md](../tasks.md) を参照）。
