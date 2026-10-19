# FAQ の起票・改修

PROJECT EDGEWORTH の FAQ の原本は、サイト側リポジトリの `../danjiri-concerto.github.io/project-edgeworth/data/faq.xlsx`（`faq` シート）。作者の方針により、新しい裁定はこの xlsx を直接編集して追加・改修する。

公開ページ: <https://danjiri-concerto.github.io/project-edgeworth/faq.html>（`scripts/convert_edgeworth_faq.py` が xlsx から `faq.json` を生成して表示している）

## `faq` シートの列

| 列 | 内容 |
| --- | --- |
| `main_card_ids` | 主な対象カードの番号。複数はカンマ区切り（例: `P052` / `M001,M003`）。ルール全般の質問は空欄 |
| `sub_card_ids` | 関連カードの番号（任意） |
| `question` | 質問文（「〜ですか？」） |
| `answer` | 回答文（「はい、〜」「いいえ、〜」から始める） |
| `importance` | 重要度 1〜5 |
| `category` | 既存の分類から選ぶ: 循環・燃料 / 通信確立 / 褒賞カード / 衛星 / 配備 / 打ち上げ・転回 / 企画・手札 / ミッションカード / 予算発行 / アクション選択 / 得点・ゲーム終了 |
| `status` | `既存` / `追加` / `修正` / `要確認` / `エラッタ`。`要確認` は公開ページに出ない。`エラッタ` はエラッタページに載せる |
| `note` | 内部メモ（公開されない）。BGA 実装の質問から起票した場合は `BGA rule-questions Q-NN` と書く |

カード番号は印刷物の番号（P001〜P099、R001B〜R025、T001〜T024、M001〜M010、A001〜A005）。

## 手順

1. 作者への質問（[rule-questions.md](rule-questions.md)）の回答のうち、プレイヤーにも有用な裁定を選ぶ。
2. 作者の回答前に下書きするときは `status` を `要確認`、回答で確定したら `追加`（既存の行を直すときは `修正`）。
3. xlsx を Excel で開いていないことを確認し、openpyxl で `faq` シートだけを編集する。

   ```bash
   cd ../danjiri-concerto.github.io
   uv run --with openpyxl python - <<'EOF'
   import openpyxl
   path = "project-edgeworth/data/faq.xlsx"
   wb = openpyxl.load_workbook(path)
   ws = wb["faq"]
   # 書式だけの空行が 1023 行目まであるため、ws.append は使わず最終データ行の次に書く
   last = max(r for r in range(1, ws.max_row + 1)
              if any(ws.cell(r, c).value is not None for c in range(1, 9)))
   row = ["P052", None, "質問文", "回答文", 3, "通信確立", "追加", "BGA rule-questions Q-NN"]
   for c, v in enumerate(row, 1):
       ws.cell(last + 1, c, v)
   wb.save(path)
   EOF
   ```

4. 追加した行を読み戻して確認し、作者に「どの行を追加・修正したか」を報告する。
5. サイト側のコミット・`faq.json` の再生成（`uv run --with openpyxl python scripts/convert_edgeworth_faq.py`）・公開は、作者の指示があるときだけ行う。

## 注意

- openpyxl で保存すると、数式セルのキャッシュ値が消える（`convert_edgeworth_faq.py` はこれを前提に行番号からカード番号を決めている）。`faq` シート以外のセルは変更しない。
- `faq` シートは書式だけの空行が 1023 行目まである（2026-10-19 時点でデータは 95 行目まで）。`ws.append` を使うと 1024 行目に追加されてしまうので、上の手順のとおり最終データ行の次に書く。
- FAQ の文体は既存の行に合わせる（です・ます調、カード名は「」で囲む）。
