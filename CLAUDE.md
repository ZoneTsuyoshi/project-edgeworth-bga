# CLAUDE.md

自作ボードゲーム PROJECT EDGEWORTH を Board Game Arena（BGA）に実装するリポジトリ。
ユーザーとのやり取り、ドキュメント、コミットメッセージ、PR は日本語で書く。

姉妹リポジトリ `../minimum-journey-bga`（同じ作者の MINIMUM JOURNEY の BGA 実装）で確立した運用・CI/CD・BGA の知見を流用する。迷ったら先にそちらの `docs/` と実装を参照する。

## 絶対に守るルール

1. **main に直接コミットしない。** すべての変更はタスク用ブランチ → PR → マージで行う。
2. **タスクごとにワークツリーを `.worktrees/` 配下に作成して作業する。** リポジトリ直下の作業ツリーは常に `main` のままにする。
3. **仕様書が先。** 実装の前に `docs/specs/` に仕様書を作成し、PR でユーザーの承認を得る。仕様書のない実装はしない。
4. **各タスクはサブエージェント（sonnet）に委託する。** メインエージェントは分解・指示・レビュー・Git 操作の統括を担当する。
5. **PR マージ後は、該当のワークツリー・ローカルブランチ・リモートブランチを削除する。**
6. **`data/` は原本。編集・移動・削除しない。** 加工が必要なら `src/` 側に生成物を作る。
7. **認証情報をコミットしない。** BGA Studio の認証情報（SSH 秘密鍵とユーザー名）は GitHub Secrets のみに置く。ホスト・ポート・リモートパス・known_hosts のような公開情報は GitHub Variables に置いてよい。
8. **ルールの不明点は推測で埋めない。** 効果の処理順、同時処理、アナログ特有の意思表示（同時公開・傾けて使用済みを示すなど）の BGA での表現は、必ず作者に質問して決める（[docs/development/rule-questions.md](docs/development/rule-questions.md)）。

## タスクの進め方（要約）

詳細は [docs/development/task-workflow.md](docs/development/task-workflow.md) と [docs/development/git-workflow.md](docs/development/git-workflow.md)。

```bash
# 1. ワークツリー作成（メインエージェントが実行）
git fetch origin
git worktree add .worktrees/<slug> -b <type>/<slug> origin/main

# 2. サブエージェント（sonnet）に委託 → コミット → push → PR 作成
git -C .worktrees/<slug> push -u origin <type>/<slug>
gh pr create --base main --head <type>/<slug>

# 3. マージ後の後始末
git worktree remove .worktrees/<slug>
git branch -D <type>/<slug>
git push origin --delete <type>/<slug>   # GitHub 側で自動削除済みなら不要
git fetch --prune && git pull --ff-only origin main
```

- ブランチ名: `<type>/<slug>`（`type` は `docs` / `feat` / `fix` / `ci` / `chore` / `refactor`）。ワークツリーのディレクトリ名は `<slug>`。
- コミットは Conventional Commits（例: `docs(specs): ゲームルール仕様を追加`）。
- 1 タスク = 1 ブランチ = 1 ワークツリー = 1 PR。仕様書 PR と実装 PR は分ける。
- PR は squash merge。CI が通り、ユーザーから異論がなければメインエージェントがマージと後始末まで行う。

### サブエージェントへの委託

- Agent ツールを `model: "sonnet"` で起動する。
- Agent ツールの `isolation: "worktree"` は使わない（`.worktrees/` 配下にならないため）。メインエージェントが `git worktree add` で作成し、その**絶対パス**を指示に含める。
- 指示には、目的・作業ディレクトリ（絶対パス）・読むべき資料（ルールブック、関連仕様書、FAQ）・成果物のパス・やらないこと・完了条件を明記する。
- サブエージェントの報告は鵜呑みにせず、メインエージェントが差分を確認してから PR を作成する。
- 互いに独立したタスクは、別々のワークツリーで並列に委託してよい。

## ドキュメント

- 開発用ドキュメントはすべて `docs/` で管理する。索引は [docs/README.md](docs/README.md)。
- 仕様書は `docs/specs/NNN-<slug>.md`（3 桁連番）。[docs/specs/_template.md](docs/specs/_template.md) から作成し、[docs/specs/README.md](docs/specs/README.md) の一覧に追記する。
- タスク一覧は [docs/tasks.md](docs/tasks.md)。開発タスクと**作者（ユーザー）側の作業**の両方を管理する。タスクの着手・完了時、作者の作業の進捗を聞いたときに更新する。
- 作者への質問と回答の記録は [docs/development/rule-questions.md](docs/development/rule-questions.md)。

## 一次情報と優先順位

1. 印刷物（カード画像 `data/cards/`、ボード画像 `data/boards/`、ルールブックのページ画像 `data/rulebooks/`）
2. エラッタ: <https://danjiri-concerto.github.io/project-edgeworth/errata.html>
3. FAQ: `../danjiri-concerto.github.io/project-edgeworth/data/faq.xlsx` の `faq` シート（公開版 <https://danjiri-concerto.github.io/project-edgeworth/faq.html>）
4. 仕様書 `docs/specs/`

- 仕様書と印刷物・FAQ が食い違う場合は印刷物・FAQ を正とする。どれにも書かれていない点は推測で埋めず作者に確認する。
- `faq.xlsx` の `project` / `crew` / `device` / `software` / `moon` / `blue` などのシートは制作時のマスターデータで、印刷版と名前・文面が違うことがある（例: P056・P057 の名前）。カードデータは必ず印刷画像と突き合わせる。

## FAQ の新規起票・改修

作者の方針: **新しい裁定は `../danjiri-concerto.github.io/project-edgeworth/data/faq.xlsx` を直接編集して追加・改修する。** 手順は [docs/development/faq-workflow.md](docs/development/faq-workflow.md)。

- 編集するのは `faq` シートのみ。列は `main_card_ids, sub_card_ids, question, answer, importance, category, status, note`。
- 作者の回答で確定した裁定を追記するときの `status` は `追加`（既存行を直すときは `修正`）。作者の確認前の下書きは `要確認` にする（公開ページには出ない）。
- 編集は openpyxl で `faq` シートの行だけを変更する。他のシートの数式・書式を壊さないよう、保存前後で他シートに差分がないことを確認する。Excel で開いている場合は編集しない。
- FAQ を足すたびに `faq.json` を再生成し、`faq.xlsx` と `faq.json` だけをサイト側の `gh-pages` にコミット・push して随時公開してよい（作者の方針、2026-10-19。このリポジトリの Git ルールの対象外）。エラッタは `errata.html` に手で追記する。

## ゲームの用語

仕様書・コードでは以下の用語に統一する（英語識別子は仕様書 002 で確定させる）。

| 用語 | 意味 |
| --- | --- |
| ラウンド | 計画フェイズ → アクションフェイズ（I〜V のうち選ばれたもの）→ 循環フェイズ → 終了判定 |
| アクションカード | I 予算発行 / II 配備 / III 打ち上げ・転回 / IV 通信確立 / V 企画。各プレイヤー 5 枚 |
| アクション選択ボーナス | そのアクションを自分で選んだプレイヤーだけが得る追加効果 |
| 地域ボード / 地域 | 3×3 の 9 区域からなる両面ボード（A〜H）。2×4 に並べて盤面を作る |
| 区域 | 盤面の 1 マス（六角形）。表面は海・陸、裏面は標準海・深海・岩礁・標準陸・森林・山岳・雪原 |
| 予算 | 予算トラックの値。予算発行で得るマヤの量 |
| マヤ | 資金（所持金） |
| 名声 | 勝利点 |
| プロジェクトカード | 衛星に配備するカード（CREW / DEVICE / SOFTWARE）。P001〜P099 |
| 衛星カード / 衛星コマ | 各プレイヤー 5 機。表面は共通の MOON プラン、裏面は色ごとのプラン |
| 稼働中 / 非稼働 | 衛星コマが盤面上にある / 衛星カード上にある |
| 発射拠点 | 各プレイヤーの打ち上げ地点（陸区域） |
| 通信確立 | 衛星の直下の区域にキューブを置くこと。区域コスト＋先駆コスト |
| 特殊通信確立コスト | 通信確立コスト＋特殊コスト 5 マヤ |
| 循環 | 衛星コマの移動 → 燃料消費 → ニアミス判定 → 着陸 / 着水判定 |
| 起動 | 条件を満たしたとき各ラウンド 1 回まで使える効果（紙では傾けて使用済みを示す） |
| 基準プレイヤー | 席順の 1 番手。紙では同時処理の順番を決めるが、BGA 版では複数プレイヤーの効果を全員同時に処理する（rule-questions Q-16） |
| ミッションカード | 各プレイヤー 2 枚の非公開の形状目標。M001〜M010 |
| 褒賞カード | 全体で 2 枚使う順位ボーナス。T001〜T024（12 枚の両面） |

## 画像ファイル名

`data/` の画像はすべて `@4x.png`（印刷解像度の書き出し）。ファイル名はカード右下などのカード番号に対応する。

- `cards/projects/P001`〜`P099`: プロジェクトカード（P001〜P033 CREW、P034〜P066 DEVICE、P067〜P099 SOFTWARE）。`PREV` は裏面。**`P037 @4x.png` だけ名前に空白が入っている**
- `cards/rockets/R001`〜`R005` + 色（`B` / `G` / `R` / `Y`）: 衛星カードの MOON 面（HAMMER / RETRO / SCALLOP / CONCORDE / STEALTH）
- `cards/rockets/R006`〜`R025`: 衛星カードの色別プランの面（PHOBOS / DEIMOS / EUROPA / GANYMEDE × 5 形状。色との対応は仕様書 002 で確定）
- `cards/missions/M001`〜`M010`、`MREV`: ミッションカード
- `cards/rewards/T001`〜`T024`: 褒賞カード（両面 12 枚の各面）
- `cards/actions/A001`〜`A005`、`AREV`: アクションカード
- `boards/region/A`〜`H`（表面）、`RA`〜`RH`（裏面）: 地域ボード
- `boards/area/`: 区域の種類ごとのタイル、`boards/track/`: 予算・名声トラック、`boards/board/`: ボード表裏
- `piecies/`: コマ（ディレクトリ名は原本のまま `piecies`）。`base` 発射拠点、`cube` キューブ、`disk` 円盤、`fuel` 燃料、`satellite` 衛星コマ 5 形状、`maya` マヤチップ
- `icons/area/`: 地域アイコン A〜H
- `rulebooks/p1`〜`p28`: ルールブックのページ画像（見開きは `p10&11` のように 1 ファイル）

## BGA 実装の方針

- BGA Studio に同期するゲーム本体は `src/` 配下に置き、`src/` の中身が BGA 側プロジェクトフォルダと 1 対 1 に対応するようにする。`docs/` や `data/` は同期しない。
- BGA フレームワークの構成（ファイル構成、状態クラス、通知、PHP バージョンなど）は更新が速い。記憶に頼らず、仕様書作成時に [BGA Studio の公式ドキュメント](https://en.doc.boardgamearena.com/Studio) で最新を確認する。minimum-journey-bga の仕様書 003 も参考にする。
- BGA Studio のプロジェクト名は **`projectedgeworth`**（作成済み・変更不可）。BGA のファイル名・クラス名・名前空間はこの名前に従う。
- UI 文言は英語を原文とし、BGA の翻訳機構で日本語化する（BGA の標準作法）。
- BGA のフレームワークのクラス（`Table`、`GameState` など）を継承するクラスに独自のメソッドを足すときは、名前に `pe` の接頭辞を付ける（例: `peSendNotifications`）。フレームワークに同名のメソッドがあると Studio で致命的エラーになり、開発用スタブが古いため PHPStan では検出できない（minimum-journey-bga で 2026-10-15 に発生）。

## CI/CD

minimum-journey-bga の構成（lint の CI、`main` へのマージで `src/` を BGA Studio へ SFTP 同期する CD）を流用する予定。導入はタスク T-004・T-005。方針と運用は [docs/development/cicd.md](docs/development/cicd.md)。`main` の保護は設定しないため、**CI（`Lint gate`）が通らない PR はマージしない**（`gh pr checks` で確認。サブエージェントはマージしない）。
