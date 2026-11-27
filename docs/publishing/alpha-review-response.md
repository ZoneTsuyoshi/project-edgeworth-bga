# BGA への再審査の依頼（Public Alpha の審査への返信）

2026-11-27 に受け取った Public Alpha の審査（UI の差し戻し）に対し、UI を作り直した（仕様書 [014](../specs/014-ui-redesign.md)、タスク I-23）。作者が BGA の審査担当へ再審査を依頼するときに貼る英語の文面の案と、その日本語の説明をまとめる。

## 送る前に（作者の作業）

英語の文面は「実装したこと」と「手元のプレビューの自動検査で確かめたこと」だけを書いている。**Studio の実機で確かめたことは書いていない。** 次の順で進める。

1. [tasks.md](../tasks.md) の作業 17（Studio で UI の再設計を確認する）を済ませる。確認する点は作業 17 の一覧のとおり。
2. 確認して直す点があれば、直してから送る。実機で確認できたことは、英語の文面の最後の段落に「Studio でも確認した」と事実どおりに書き足してよい（確認していないことは書かない）。
3. 作業 18（再審査の依頼）として、下の文面を審査担当へ送る。

## 英語の文面（案）

```text
Hello,

Thank you for the detailed review. I redesigned the game interface as a whole, applying the guidelines as one system. What changed for each point:

1. Structure (A.1, C.4): The play area is now a dark table with the board, supply and player zones placed directly on it. White rounded boxes, shadows and pill badges are removed; zones are separated by thin rules and small labels. The layout is centered and fluid.

2. Tooltips (B.1): One tooltip component replaces all tooltips. It opens on mouse hover, and on touch by tap, long press or an info button on selected cards. It stays inside the viewport below the status bar and does not cover its target. All title attributes are removed.

3. Top area (A.1, A.2): The phase navigation and selection panel are replaced by a single-line round track. Instructions are one line in the status bar; secondary choices appear in a tray below the board.

4. Player areas (A.3): Satellites are shown as in the physical game, with project cards stacked on the satellite card; the list rows, gauges and status text are removed. Counters appear only in the standard player panels. Your own area has three rows; other players are smaller, below it. Each panel has a button that jumps to that player's area. The floating jump-to widget is removed.

5. Consistency and readability (A.5, E.1): Cards, pieces and controls share one visual style. Text is at least 12 px, and text on the table has at least 4.5:1 contrast. An optional preference shows player numbers on pieces, so color is not the only cue.

6. Floating controls and responsiveness (C.4, E.3): The play area has no fixed or sticky elements. Interactive elements are at least 32x32 px. The only nested scrolling is the zoomed board on narrow screens.

7. Affordance (C.2): One highlight style marks selectable, selected, unavailable and target items. Unselectable board areas are dimmed during a selection, and pressing an unavailable item shows the reason.

Points 2, 5 and 6 are checked by a script in a local preview, at widths from 360 to 2560 px across all game states (overflow, fixed/sticky elements, target size, title attributes, font size, nested scrolling, tooltip position, contrast). It reports no violations.
```

## 日本語の説明

審査の指摘 7 点（仕様書 014 の R1〜R7）と、文面の対応。

| 指摘 | 文面の番号 | 変えたこと | 確かめ方 |
| --- | --- | --- | --- |
| R1 全体の構造 | 1 | 暗い卓の上に部品を直接置く。白い角丸の面・影・丸い札をやめ、区切りは細い線と小さなラベルだけ | プレビューの画像（`npm run preview:shots`）で確認。CSS の参照を grep で確認 |
| R2 ツールチップ | 2 | 独自の 1 つのツールチップに統一。ホバー・タップ・長押し・情報の印。画面内、対象を覆わない。`title` 属性を全廃 | `check:ui` の 4・7 番（1366 × 768・1440 × 900・390 × 844・844 × 390） |
| R3 上部 | 3 | フェイズ帯と選択パネルをやめ、1 行のラウンドトラックに | 画像で確認 |
| R4 プレイヤーの領域 | 4 | 衛星は紙どおり（衛星カードの上にプロジェクトカードを積む）。一覧の行・ゲージ・状態の文字は廃止。数値はパネルだけ。自分の領域は 3 行。パネルに移動のボタン。bga-jump-to は廃止 | 画像で確認 |
| R5 一貫性・読みやすさ | 5 | 1 つの視覚体系。文字 12 px 以上、コントラスト比 4.5:1 以上。設定 100（席順の印） | `check:ui` の 5・8 番。設定 100 は `?marks=1` の画像で確認 |
| R6 浮いた操作・レスポンシブ | 6 | `#pe-root` の中に fixed / sticky が無い。押せる要素は 32 × 32 px 以上。入れ子のスクロールは narrow の盤面だけ | `check:ui` の 1・2・3・6 番（幅 360〜2560 × 全部の状態） |
| R7 操作の分かりやすさ | 7 | 5 つのクラス（`pe-can`・`pe-can-sell`・`pe-picked`・`pe-off`・`pe-subject`）で統一。選択中は選べない区域を暗くする。押せないものを押すと理由を出す | 画像で確認 |

- 文面が触れているガイドラインの節: A.1・A.2・A.3・A.5・B.1・C.2・C.4・E.1・E.3（節の要点は [014 の「審査が挙げたガイドラインの節」](../specs/014-ui-redesign.md)）。
- 自動検査（`npm run check:ui`）は手元の Chrome で動かす確認で、CI には入れていない。Studio の実機（BGA のフレームの中）では確かめていない点が残る（受け入れ条件の `[ ]`）。
- 設定 100（席順の印）は BGA の `gamepreferences` の再読み込み（作業 16）が必要。
