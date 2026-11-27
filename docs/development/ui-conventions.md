# UI を変えるときの決まり

BGA の Public Alpha の審査（2026-11-27）を受けて作り直した UI（仕様書 [014](../specs/014-ui-redesign.md)、タスク I-23）を、あとから崩さないための 1 ページの要約。迷ったら 014 の該当の節を読む。

## 方針

公式バナーのように、**暗い卓の上に、盤面・コマ・カードを直接置く**。ゲーム領域（`#pe-root`）には部品と、卓に印刷された線・小さなラベルだけを置く。Web アプリ風の面は置かない。

## 使わないもの

- 白い角丸の箱、影（`box-shadow` の浮き）、丸い札、色つきの上線、オレンジ（`--pe-accent`・`--pe-surface*`）。
- `border-radius` は 3 px まで。それを超えるのは部品（カード・コマ・BGA 標準のボタン・ツールチップ）だけ。
- `filter: drop-shadow` は衛星コマの 3 か所だけ（`.dj_safari` では無効）。
- `#pe-root` の中の `position: fixed` / `sticky`、貼り付くボトムシート、浮いたボタン（bga-jump-to）。
- 独自の見た目のボタン（`.pe-btn` 系）。ボタンは BGA 標準（`addActionButton`。`ui/buttons.js`）だけ。
- `title` 属性（ツールチップは `ui/Tips.js`）。
- 記号の文字のアイコン（✓ ★ ◀ ▶ × など）。アイコンは SVG か CSS。
- 点滅・ループするアニメーション。
- 12 px 未満の文字。コントラスト比 4.5:1 未満の卓の上の文字。32 × 32 px 未満の押せる要素。
- 入れ子のスクロール（narrow の盤面 `#pe-board-viewport` だけが例外）。

## 操作の 5 つのクラス

カード・ロケット・アクションカード・コマ・盤面に、同じ意味で使う（014 §6.1）。

| クラス | 意味 |
| --- | --- |
| `pe-can` | 選べる |
| `pe-can-sell` | 売却すれば選べる |
| `pe-picked` | 選択中 |
| `pe-off` | 今は選べない（押すと理由をメッセージで出す。無反応にしない） |
| `pe-subject` | 効果の問いの対象 |

選択中は、選べない区域を暗くする。説明文を足さず、強調と、ステータスバーのタイトル 1 行で伝える。

## ツールチップとトレイ

- ツールチップは `tipText`（文字）・`tipCard`（カード）・`tipCustom` で対象に指定する。マウスは 350 ms のホバー、タッチはタップ・長押し・情報の印。位置は `placeTip`（画面内、対象を覆わない）。新しい `title` を付けない。
- 盤面の下の**トレイ**（`#pe-tray`、`ui/Tray.js`）は、選択の補助（ドラフト・企画のカードの行、コストの行、方向パッド、効果の窓、捨て札の一覧）の置き場。盤面の上や横に選択用のパネルを増やさない。主な操作ボタンはステータスバー。

## 確認の道具

UI を変えたら、PR の前に次を実行する。どちらも Chrome が必要（無ければ案内を出して終了）。

- `npm run preview:shots`: 幅 360 / 390 / 768 / 1024 / 1366 / 1440 / 1920 / 2560 × 主な状態の画像を `build/preview-shots/` に出す。画像を自分で見て、崩れ・折れ・重なりを確かめる。`-- --widths 390 --states planning`、`-- --query marks=1`、`-- --tips` で絞る。
- `npm run check:ui`: 審査の指摘を機械的に確かめる。幅 8 つ × 全部の状態の見本（`tools/preview/samples/states/`）で、横のはみ出し・`fixed` / `sticky`・押せる要素の大きさ・`title` 属性・文字の大きさ・入れ子のスクロール・ツールチップの位置・コントラスト比を調べる。**違反 0 を保つ。** 例外は `tools/preview/check-ui.mjs` の `EXCEPTIONS` に理由つきで書く（最小に）。`-- --widths 390 --states planning` で絞れる。CI には入れていない。

状態の見本（`tools/preview/make-state-samples.mjs`）を足したら、`node tools/preview/make-state-samples.mjs` で再生成する。

`npm run lint` と `npm run test:js` も通す。
