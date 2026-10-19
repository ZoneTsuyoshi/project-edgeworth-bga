# 仕様書

実装の前に、必ずここへ仕様書を作成します。作り方は [../development/task-workflow.md](../development/task-workflow.md) を参照してください。

- ファイル名: `NNN-<slug>.md`（`NNN` は 3 桁の連番、`slug` は英小文字とハイフン）
- 雛形: [_template.md](_template.md)
- ステータス: `Draft`（作成中）→ `Approved`（PR マージ済み）→ `Implemented`（実装済み）

## 一覧

| No. | タイトル | ステータス | 対応タスク |
| --- | --- | --- | --- |
| 001 | [ゲームルール仕様](001-game-rules.md) | Approved | T-001 |
| 002 | [カードデータ仕様](002-card-data.md) | Approved | T-002 |
| 003 | [盤面データ](003-board-data.md) | Approved | T-003 |
| 004 | [BGA プロジェクト構成・技術方針](004-bga-architecture.md) | Approved | T-004 |
| 005 | [CI（lint）](005-ci-lint.md) | Approved | T-005 |
| 008 | [アナログ特有の操作の BGA での表現](008-analog-operations.md) | Approved | T-008 |

各仕様書は別ブランチ（`docs/spec-NNN-<slug>`）で作成し、PR のマージで `main` に入る。
