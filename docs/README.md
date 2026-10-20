# 開発用ドキュメント

PROJECT EDGEWORTH の BGA 実装に関する開発用ドキュメントの索引です。

## 構成

| パス | 内容 |
| --- | --- |
| [tasks.md](tasks.md) | タスク一覧と進捗（作者の作業を含む） |
| [specs/](specs/README.md) | 仕様書。実装の前に必ずここへ作成する |
| [development/git-workflow.md](development/git-workflow.md) | Git 運用ルール（ブランチ・ワークツリー・コミット・PR・後始末） |
| [development/task-workflow.md](development/task-workflow.md) | タスクの進め方とサブエージェントへの委託方法 |
| [development/cicd.md](development/cicd.md) | CI/CD の方針（lint の段階導入、マージの運用、Studio 同期の予定） |
| [development/rule-questions.md](development/rule-questions.md) | 作者への質問（処理順・同時処理・アナログ特有の操作）と回答 |
| [development/faq-workflow.md](development/faq-workflow.md) | FAQ（サイト側の `faq.xlsx`）の起票・改修の手順 |
| [publishing/game-description.md](publishing/game-description.md) | 英語のゲーム紹介文と BGG 登録の手順 |
| [publishing/bga-license.md](publishing/bga-license.md) | BGA のライセンス申請（作者が権利者として許諾する）の手順とメールの文面 |

## 一次情報

- ルールブック: `data/rulebooks/`（ページ画像。作者の手元だけにあり、Git では管理しない）
- カード画像: `data/cards/`（同上）
- FAQ: <https://danjiri-concerto.github.io/project-edgeworth/faq.html>（原本は `../danjiri-concerto.github.io/project-edgeworth/data/faq.xlsx`）
- エラッタ: <https://danjiri-concerto.github.io/project-edgeworth/errata.html>
- BGA Studio 公式ドキュメント: <https://en.doc.boardgamearena.com/Studio>
- 姉妹リポジトリ（運用・CI/CD・BGA の知見）: `../minimum-journey-bga`

## ドキュメントを書くときのルール

- 日本語で書く。
- 仕様書と印刷物・FAQ が食い違う場合は印刷物・FAQ が正。どれにも記載のない点は「未確定事項」として明記し、作者に確認する。
- BGA フレームワークに関する記述には、確認した公式ドキュメントの URL を添える。
