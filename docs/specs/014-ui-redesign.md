# 014: UI の再設計（Public Alpha 審査の指摘への対応）

| 項目 | 内容 |
| --- | --- |
| ステータス | Approved |
| 対応タスク | I-23 |
| 作成日 | 2026-11-27 |
| 関連仕様書 | 012（§2 レイアウト、§3.7〜§3.9 盤面の見せ方、§4 カードの表示、§5 パネル、§6 ステータスバー、§8.2 カード券面の言語、§10 スマホの教訓、未確定事項、追補 2026-11-21 U-0〜U-11）、008（§2 共通方針、§3-1 計画、§4 起動効果、§5 効果の選択、§7 燃料）、009（§3・§5・§9 状態と同時選択）、010（§8〜§10 getAllDatas・args・通知）、004（§3・§4・§12 構成と JS）、013（ドラフトの画面）、001（§6〜§7 アクションカード）。参照: [development/rule-questions.md](../development/rule-questions.md)、[tasks.md](../tasks.md)（I-21・I-22・I-23） |

## 目的

BGA の Public Alpha の審査（2026-11-27 に作者が受領）で、UI が全面的に差し戻された。審査は「ゲームの部品の周りの UI が、汎用的で一貫性が無く、卓上のゲームではなく独自のアプリに見える」ことを主な理由に挙げ、ガイドラインを個別の見た目の修正ではなく 1 つの UX の体系として当てはめ直すよう求めている。

この仕様書は、その再設計を決める。方針は「公式バナーのように、暗い卓の上に、盤面・コマ・カードを直接置く」こと。ゲーム領域には部品と、卓に印刷された線と小さなラベルだけを置き、Web アプリ風の面（白い角丸の箱・影・丸い札）、独自のボタン、独自のツールチップ、重複した数値をやめる。サーバー（PHP）の規則・状態・通知は変えない。

## 審査の指摘（2026-11-27）

審査の返信（英語）の全文は仕様書に貼らず、要約する。審査の結論は「Public Alpha にはまだ早い。徹底した UX/UI の見直しを行い、再申請の前にガイドラインを 1 つの体系として適用すること」。ゴールは「盤面と部品を主役にし、明確でアクセシブルな UI で卓上の体験を支える」こと。

| # | 指摘の要約 | 審査の言葉（原文から 1 文） | 本書の節 |
| --- | --- | --- | --- |
| R1 | 全体の構造を作り直す。大きな角丸の枠、浮いたボタン、ばらばらの情報の領域、不揃いの見た目。独自のアプリに見える（返信には画像が 1 枚添付されていた。内容は未確認） | "It feels more like a custom application than a coherent tabletop adaptation." | §1、§2、§4 |
| R2 | ツールチップを直す。読める・正しい位置・画面内に収まる・マウスでもタッチでも出せる・大事な部品を不必要に隠さない | "Tooltips must be readable, properly positioned, constrained to the viewport and accessible through both mouse and touch interactions." | §7 |
| R3 | 上部を簡素にする。大きなセットアップ・フェイズのナビゲーション風のパネルが場所を取り、情報が重複している。部品と、説明・補助の移動を区別する | "Please distinguish actual gameplay components from instructions and secondary navigation." | §3、§5 |
| R4 | プレイヤーの領域を見直す。盤面の下の大量のカードと細かい情報に階層が要る。コンパクトな情報は標準のプレイヤーパネルへ。紙のゲームが必要としない重複をやめる | "Keep compact player information in the standard BGA player panels and avoid duplicating it elsewhere unless the physical game requires it." | §4、§8 |
| R5 | 見た目の一貫性と部品の読みやすさ。盤面・カード・操作・周囲の UI を 1 つの視覚体系に。カードの文字が特に小さい | "Some card text is particularly small, and the gameplay components should remain readable without excessive zooming." | §1、§4 |
| R6 | 浮いた操作とレスポンシブ。左のボタンが切り離された UI に見え、スクロールで BGA のフッターに重なる。PC・ワイド・スマホを確認し、不要な入れ子のスクロールをやめる | "The buttons on the left should not behave as detached interface elements or overlap the BGA footer when scrolling." | §2、§6、§9 |
| R7 | 操作を分かりやすく。できる操作・置ける場所・選んだ部品を、追加の説明文ではなく、一貫したハイライトと文脈に応じた反応で伝える | "Available actions, legal destinations and selected components should be communicated through consistent highlighting and contextual feedback rather than relying primarily on additional instructions." | §6 |

### 審査が挙げたガイドラインの節

節番号と要点は公式 PDF（および、その非公式の要約である旧 wiki）による。【確認】2026-11-27。

| 節 | 要点 | 本書の対応 |
| --- | --- | --- |
| A.1 Layout arrangement | 遊び場を全端末で中央に置く。操作しない余白を周りに残す。卓で見えるはずの部品をポップアップの裏に隠さない。スコア・手番・目的は、ボタンやタブで開けるようにし、プレイヤーパネルだけには置かない。関連する情報を 3 隅に散らさない。縦スクロールは副次的な情報（他プレイヤーなど）に使う。「中央 = 共有の操作、上下 = 全体の情報、パネル = 私的な資源」 | §2 |
| A.2 Action Bar | バーは現在の状態を示す。盤上の部品の操作をバーのボタンで置き換えない。主な操作を中央、取り消し・戻るを右に離す。ボタンは最大 4 個。「Round in progress」の文字をバーに出さない | §2.3、§5、§6.5 |
| A.3 Player panels | 4 人でもスマホで画面の 1/4 を超えない。設定・題名・ラウンド数などの重複した情報を置かない。主な部品へ移る HTML のアンカー（ショートカット）は置いてよい | §4.4、§8 |
| A.5 Clarity & Zoom | 核となるゲームは 100 % の表示で遊べる。ズームのボタンは、大きな副次的な盤や文字だらけのカードなど、どうしても必要なときだけ。置くなら +、−、ホーム（初期の表示に戻す） | §2.5、§4.2 |
| B.1 Tooltips | 画面にある情報を同じ見せ方で繰り返さない。小さな文字・アイコンの拡大や、使う時機の助言を載せる。モバイルでは外をタップすると閉じる | §7 |
| C.2 Highlights & Glowing auras | プレイヤーが「何を押すのか」を迷わない。選べる選択肢を強調し、確定する前に結果を見せる | §6 |
| C.4 Sizing & responsiveness | 中央寄せ、流動的な幅（固定幅にしない）、盤の周りの余白。デスクトップは盤全体を見せる。モバイルは副次的な要素を主な操作の下に積む。100 % の表示で読める。ブラウザの拡大縮小で崩れない。32 × 32 px 未満の操作要素を作らない。縦のレイアウトにはジャンプのリンクを置く | §2、§6 |
| E.1 Design for all | 色にアイコン・形・模様を添える。コントラスト比 4.5:1 以上。ボタン・アイコン・操作要素すべてにラベル（スクリーンリーダー用）。点滅や素早い切り替えをしない。細部や微妙な陰影だけで部品を見分けさせない | §1、§6、§12 |
| E.3 Tap targets | 操作要素は最小 32 × 32 px、目標 40〜44 px。ボタン・コマ・カードの間に間隔。場所が足りなければスクロールを足し、部品を最小未満に縮めない | §2、§6、§8 |

本書は、審査が挙げた節に加えて次を参照する。B.2（できない操作は灰色にする）、C.3（ボタンの色: 青 = `primary`、白 = `secondary`、赤 = `alert`、灰 = 無効。ゲームの色をボタンに使わない）、C.5（背景は質感のある卓。平坦な単色にしない）、D.1（失敗したら必ず理由を出す。無反応にしない）、D.3（通常のアニメーションは 0.5 秒、最大 0.8 秒。装飾の点滅・ループをしない）、E.4（設定は BGA の標準の設定に置く）。

## スコープ

- 対象:
  - 視覚体系（背景・色・形・文字・ゾーンの示し方・ボタン・アイコン）
  - 画面の構造と 3 つのレイアウト（wide / medium / narrow）、盤面と場と自分・他プレイヤーの領域
  - ラウンドトラック（フェイズ帯の置き換え）
  - 衛星の見せ方（紙どおりのロケットだけにする）、カードの大きさ、場、パネルとの重複の整理
  - トレイ（盤面の外で選ぶ・読むための 1 つの領域）と、効果の窓（状態 31）を 1 件ずつにする設計
  - 操作の見た目（共通のクラス、選べない区域を暗くする、押したときの理由）
  - ツールチップの作り直し、プレイヤーパネルの見直し
  - 設定 100（席順の印）の実装（§3。宣言だけで実装が無い不具合の修正）
  - 廃止するもの、確認の道具（`tools/preview` と自動検査）、実装の PR の分け方、ユーザー向け文言
- 対象外:
  - ルール、サーバー（PHP）の状態遷移・通知・データ。サーバーで変えるのは、`src/gamepreferences.jsonc` の設定 101 の削除だけ（最終ラウンドのバナーは出さないので、`getAllDatas` への項目の追加も無い。§3）
  - カードの券面（日本語の印刷）の加工、スプライトの再生成、新しい画像の作成
  - BGA の wiki のルール概要、メタ画像（012 §9）
  - 得点表（`#pe-score`）の中身（内訳の行・列・値）。中身は変えず、見た目と幅だけを新しい体系に合わせる（§2.2）

## 記法と調査の前提

- **【確認】** は公式ドキュメントまたはこのリポジトリのコードで確認した事実。**【実測】** は本タスクで数えた・計算した値。**【提案】** は本書の提案。**【未確認】** は確認できなかった事項。
- 調査日は 2026-11-27。BGA の API は更新が速いので、実装時に再確認する。
- 審査の返信（原文）は作者の手元にある。添付されていた画像は仕様書の作成時点で共有されていない（作者の作業に追加。[tasks.md](../tasks.md)）。
- 2026-11-27 に、リポジトリの外の作業用コピーで試作を作り、幅 1366 / 1440 / 1920 / 2560 px と 390 px のスクリーンショットで方向を確かめた（公式バナーに近い見た目になること、盤面と行 A が 1 画面に入ること）。試作のコードは本番に持ち込まず、PR 1 以降で書き直す。本書の §2.2・§2.3・§4.1・§5・§6 のうち「試作で決めた」と書いた点は、この確認による。

参照した公式文書（すべて 2026-11-27 に確認）:

| 略称 | URL | 確認した内容 |
| --- | --- | --- |
| ガイドライン | <https://doc.studio.boardgamearena.com/framework-documentation/guidelines/> | UX ガイドライン（旧 wiki <https://en.doc.boardgamearena.com/BGA_Studio_Guidelines> は上の URL への移転の案内つき。旧 wiki の本文は非公式の要約） |
| 公式 PDF | <https://doc.studio.boardgamearena.com/assets/framework-documentation/guidelines/Guidelines_UX.pdf> | 節 A〜F の本文（審査が挙げた節の要点の出典） |
| gameui | <https://doc.studio.boardgamearena.com/framework-documentation/front-end/game-interface-logic/gameui/> | ツールチップ（`addTooltip`・`addTooltipHtml`。対象の要素に id が要る。「重要な情報をツールチップだけに置かない」）、`bgaAnimationsActive()` |
| status-bar | <https://doc.studio.boardgamearena.com/framework-documentation/front-end/game-interface-logic/status-bar/> | `setTitle(title, args)`（`${you}` と任意の変数）、`addActionButton(label, callback, params)` の `color`（`primary` = 青 = 既定、`secondary`、`alert`）・`id`・`classes`・`destination`・`title`・`disabled`・`tooltip`・`confirm`・`autoclick`、`removeActionButtons()` |
| player-panels | <https://doc.studio.boardgamearena.com/framework-documentation/front-end/game-interface-logic/player-panels/> | `playerPanels.getElement(player_id)`（パネルの中に自分の要素を足す）、`disablePlayerPanel` |
| game-area | <https://doc.studio.boardgamearena.com/framework-documentation/front-end/game-interface-logic/game-area/> | `gameArea.addLastTurnBanner(message?, args?)`（既定の文は "This is the last turn!"）、`removeLastTurnBanner()` |
| dialogs | <https://doc.studio.boardgamearena.com/framework-documentation/front-end/game-interface-logic/dialogs/> | `dialogs.showMessage(msg, type)`: `error` は赤い背景でログにも載る。「介入的なので多用しない」。サーバーに送る前に無効な操作を止める例が載っている |
| stylesheet | <https://doc.studio.boardgamearena.com/framework-documentation/front-end/game-interface-stylesheet/> | 背景は `body` に置く（既定の背景は `html` にあり、`body` の背景が覆う）。ダークは `[data-theme="dark"]`。`filter: drop-shadow` は Safari で重い（`box-shadow`、画像に焼く、`.dj_safari` で無効化） |
| bga-jump-to | <https://doc.studio.boardgamearena.com/framework-documentation/front-end/libs/bga-jump-to/> | 「盤が複数あって画面に収まらないゲームだけで使う。要素が少ないときは画面を散らかす」 |
| モバイル | <https://en.doc.boardgamearena.com/Your_game_mobile_version> | `game_interface_width.min`、autoscale（CSS `zoom`）、モバイルではパネルがゲームの上へ移る、`#ebd-body` の `mobile_version` / `desktop_version` と `touch-device` / `notouch-device`、ホバーだけに必須の情報を置かない、Pointer Events。ゲーム領域の外に出す要素の位置は `getBoundingClientRect()`（見た目の位置）で測る |

## 仕様

節の番号は指示書（メインエージェントが 2026-11-27 に決めた設計）の §0〜§11 と同じにしてある。「作者に確認すること」（指示書 §12）は「未確定事項」の Q1〜Q8。各節の「変更するファイル」は、本書の作成時点（`main` の `9374f6c`）のコードを読んで洗い出した。

### 0. 審査の指摘と原因（コードで確かめた事実）

| # | 現状の原因（根拠のファイル） | 対応する節 |
| --- | --- | --- |
| R1 | 白い角丸の面（`.pe-phase-band`、`.pe-choice`、`.pe-player`、`.pe-sheet`）、プレイヤー色の上線、オレンジの枠、丸いバッジ、配備カードを一覧の行（チップ）で見せる簡略表示（`cards/SatelliteView.js` の `renderChips`）。`src/projectedgeworth.css` に `border-radius` の宣言が **47 か所**（コメントの 1 行を含む grep で 48 行。値は 8px が 11、6px が 7、2px が 6、50% が 5、4px が 5、10px が 4、3px が 3、22% が 2、9px・16px・12px・`4.2% / 3%` が各 1）【実測】 | §1、§2、§4 |
| R2 | 拡大表示（`cards/Tooltip.js`）の位置は、ホバーでは「ホバー中のカードがない側の画面の端（左右）」に固定。高さの上限（`--pe-zoom-avail`）で内側が切れるが、ホバー表示は `pointer-events: none` なのでスクロールできない。タッチは、選択中だけ長押し 500 ms、それ以外はタップ。カウンターなどはブラウザの `title` 属性で、タッチでは出ない。`title` 属性を付けているコードは **39 か所**（ソース上の数。実行時の要素数はもっと多い。一覧は §7）【実測】 | §7 |
| R3 | フェイズ帯（`ui/PhaseBand.js`。白い帯に丸い札 5 つ）と、盤面の上の選択パネル（`ui/ChoicePanel.js`）。選択パネルを使うのは、計画（22、`PlacementStates.js`）・ドラフト（15、`DraftState.js`）・企画（91、`ProposalState.js`）・効果の窓（31、`EffectWindowState.js`）と、足りないマヤを売却で補う配備・打ち上げ・通信確立（`ActionState.costBox`）。**マリガン（13、`MulliganState.js`）は選択パネルを使わず、すでに手札のゾーンで直接選んでいる**【実測】 | §3、§5 |
| R4 | 自分と他プレイヤーが同じ白い面で同じ重みで並ぶ。衛星ごとにチップ・「+n」（空の配備枠の数。`pe-chips-more`）・燃料ゲージ（`renderFuel`）・「Fuel 7 / 9」（`renderFuelLabel`）・「Active NE」（`renderFooter`）。見出しに地域 A〜H のアイコン 8 つ（`PlayerArea.regionsBox`、`PlayerStats.regionRow`）。パネルに確定の印（`✓`、`pe-check`）と待機の回転（`pe-waiting`）。アクションカードの裏の `✓`（`cards/HandView.js` の `ActionRow.render`） | §4、§8 |
| R5 | 明るい青灰の背景 + 白い面 + オレンジ（`--pe-accent`）は、部品（黒に近い紺 + シアンの線 + 金・紫）と別の体系。カードの大きさ: 手札 120 × 168（narrow 88 × 123）、場のカード 60（`--pe-field-card-w`）、他プレイヤーのアクションカード 48 × 67（narrow 36 × 50）。他プレイヤーのアクションカードの大きさの変数が効いていない不具合がある: `Layout.update` が入れる CSS 変数名は `--pe-${name}-w` の `name` が `actionOther`（`--pe-actionOther-w`）だが、CSS が読むのは `--pe-actionother-w`。CSS のカスタムプロパティは大文字小文字を区別するため、JS の値（narrow の 36 px など）は届かず、CSS の既定値 48 px のまま（`layout-math.js` の `cardSizes`、`projectedgeworth.css` の 49・983 行付近。【実測】。PR 1 で直す）。`font-size` が 12 px 未満の宣言が 13 か所（11 px が 12、10 px が 1）と、`max(9px, …)` の 1 か所（`.pe-stack-badge`）【実測】 | §1、§4 |
| R6 | 左のボタンは bga-jump-to（`Game.js` の `setupJumpTo`。`position: fixed` はライブラリ側のスタイルで、このリポジトリからは確認できない。【未確認】）。盤面の右上に重なる拡縮ボタン（`ui/Layout.js` の `renderTools`。narrow だけ。CSS `.pe-board-tools` が `position: absolute`）。[Sell cards] は手札の見出しの行に単独で置かれている（`ActionState.addSellButton`）。CSS の `position: fixed` は `.pe-modal`（捨て札）と `.pe-zoom`（拡大表示）、`position: sticky` は `.pe-sheet`（ボトムシート）の 3 か所。実際にスクロールしうる要素: 盤面 `.pe-board-viewport`（`overflow: auto hidden`）、手札 `.pe-hand`（`overflow-x: auto`。narrow のみ有効）、他プレイヤーの衛星 `.pe-player-other .pe-sats`（`overflow-x: auto`。全レイアウト）、捨て札のモーダル `.pe-modal-body`、拡大表示 `.pe-zoom`、**得点表 `.pe-score-scroll`（`overflow-x: auto`。指示書の一覧に無かった）** | §2、§6、§9 |
| R7 | 選べる / 選択中の見た目が部品ごとに違う（盤面は白 + 濃色の二重線と金、カードは `.pe-selectable` / `.pe-selected`、衛星は `pe-sat-*` の枠）。選べない区域は何も変わらない（`ui/StateFrame.js` の `handleCellPick`）。選べる区域は 0.8 秒周期の点滅（`.pe-hl-selectable` の `pe-pulse`）。説明文が多い（選択パネルの hint = `pe-choice-hint`、ボトムシートの行 = `pe-sheet-line`、`pe-assign-hint`、`pe-effect-need`、`pe-sellprompt-text`、`pe-handfield-note`）。選べないカードを押しても無反応（`HandView.render` は選べないカードに `title` を付けるだけでクリックの処理を持たない） | §6 |

### 1. 視覚体系（卓と部品）

**考え方**: 公式バナー（黒に近い地に、地域ボードと、カードを積んだロケットが直接置かれている）をそのまま画面にする。ゲーム領域にあるのは「部品」（盤面・コマ・カード・チップ）と、「卓に印刷された線と小さなラベル」だけ。Web アプリ風の面（白い角丸の箱、影、丸い札、色つきの上線）は使わない。

#### 1.1 背景（卓）

- `body` の背景は、ライト・ダークのどちらのテーマでも**同じ暗い卓**: `url("img/pe_background.webp") repeat top center / 900px auto, linear-gradient(180deg, #0e172b 0%, #0a1122 55%, #070c18 100%)`、`background-color: #0a1122`。`html[data-theme="dark"] body` の別指定は削除（同じ値になる）。新しい画像は作らない（`src/img/pe_background.webp`、12,842 バイトを使う）。
- 盤面の濃紺の箱（`.pe-board` の背景・角丸・上下のジグザグの帯）をやめ、区域を卓の上に直接置く（印刷版の地域ボードは白い縁の付いた六角形の塊で、板の地は無い）。上下の端の「跳ね返り」の印（012 §3.7。現状は `.pe-reflect` の 6 px の帯）は、細い線 1 本（`--pe-line`）にする。
- 【確認】背景は `body` に置く（stylesheet の文書）。ダークの別指定が要らなくなるのは、テーマで変えない（Q1）ため。

#### 1.2 トークン（`.pe-root`。テーマで変えない）

```css
--pe-ink: #e9eef7;        /* 卓の上の文字 */
--pe-ink-dim: #9aa9c1;    /* 補助の文字。卓 #0a1122 に対して 4.5:1 以上 */
--pe-line: rgb(126 200 232 / 32%);   /* 卓の線（細い） */
--pe-line-strong: #6fd0ea;           /* 卓の線（強調） */
--pe-mat: rgb(14 24 44 / 82%);       /* 文字の下に敷く面が要るときだけ（トレイの文章など） */
--pe-can: #fff;           /* 選べる */
--pe-can-glow: rgb(92 200 255 / 75%);
--pe-pick: #ffd23f;       /* 選択中 */
--pe-warn: #ff8f80;       /* 警告（必ずアイコンか文字を併用） */
--pe-ok: #6fdc9a;
/* プレイヤー色（コマの色。既存）と、暗い卓の上の文字用の明るい版 */
--pe-c-b: #0b528d; --pe-c-g: #007633; --pe-c-r: #ad0003; --pe-c-y: #b9881c;
--pe-c-b-ink: #6db3f2; --pe-c-g-ink: #5fd68a; --pe-c-r-ink: #ff7a70; --pe-c-y-ink: #f2c75c;
```

- 旧トークン `--pe-bg`、`--pe-surface`、`--pe-surface-2`、`--pe-text`、`--pe-text-dim`、`--pe-accent`（オレンジ）は廃止。`--pe-board-bg`・`--pe-border-line`（盤面の箱用。指示書の廃止の一覧に無いが、箱をやめるので不要）も廃止する【提案】。`--pe-line`・`--pe-ok`・`--pe-warn` は新しい値で置き換える。`html[data-theme="dark"] .pe-root` のトークンの上書きは削除する。
- 現状の参照数: `var(--pe-accent)` が 16 か所、`var(--pe-surface…)` が 15 か所、`var(--pe-bg)`・`var(--pe-text…)` が 33 か所【実測】。
- 暗い卓（`#0a1122`、グラデーションの両端 `#0e172b`・`#070c18`）に対するコントラスト比【実測。計算値。背景画像の線が重なる場合は未実測】:

| トークン | `#0a1122` | `#0e172b` | `#070c18` |
| --- | --- | --- | --- |
| `--pe-ink` `#e9eef7` | 16.2 | 15.3 | 16.8 |
| `--pe-ink-dim` `#9aa9c1` | 7.9 | 7.5 | 8.2 |
| `--pe-c-b-ink` `#6db3f2` | 8.4 | 8.0 | 8.7 |
| `--pe-c-g-ink` `#5fd68a` | 10.3 | 9.8 | 10.7 |
| `--pe-c-r-ink` `#ff7a70` | 7.4 | 7.0 | 7.7 |
| `--pe-c-y-ink` `#f2c75c` | 11.7 | 11.1 | 12.2 |
| `--pe-warn` `#ff8f80` | 8.5 | 8.1 | 8.8 |
| `--pe-ok` `#6fdc9a` | 11.1 | 10.5 | 11.5 |

- **形**: 部品以外の UI は角丸 0〜3 px。丸い札（pill）、`border-radius: 50%` のバッジは使わない（枚数の札は角丸 2 px の小さな長方形）。影は部品（カード・コマ）の接地の影（`box-shadow: 0 2px 6px rgb(0 0 0 / 55%)`）だけ。Safari の負荷のため `filter: drop-shadow` は原則使わない（BGA の stylesheet の文書）。現状の `drop-shadow` は 3 か所（`.pe-sat-piece`、`.pe-sat-piece.pe-sat-piece-subject`、`.pe-sat-standby-piece`。回るコマの輪郭に沿う影）。**この 3 か所（コマ）だけ `filter: drop-shadow` を残し、公式の文書のとおり `.dj_safari` では `filter: none` にする**（輪郭に沿う影は `box-shadow` で代用できないため。メインエージェントの決定、2026-11-27）。それ以外では使わない。
- **文字**: BGA の既定のフォントのまま（`font-family` を指定しない。現状も `src/projectedgeworth.css` に `font-family` の指定は無い）。本文 14 px、補助 13 px、ゾーンのラベルは 12 px・大文字・字間 0.08em・`--pe-ink-dim`。12 px 未満の文字を使わない（現状の 14 か所を直す）。
- **ゾーンの示し方（レール）**: 領域の区切りは、箱で囲まず、上端の 1 px の線（`--pe-line`）と、その左端の小さなラベルで示す。プレイヤーの領域のレールは、左端 48 px だけをプレイヤー色の太い線（3 px）にし、名前をプレイヤー色（`--pe-c-*-ink`）で出す。
- **ボタン**: ゲーム領域に独自の見た目のボタンを作らない。必要なボタンは `bga.statusBar.addActionButton(label, cb, { color, destination })` で BGA 標準のボタンを置く（トレイ・手札のレールの中のボタンは `destination` で置く）。`.pe-btn` 系の CSS は廃止（盤面の方向の矢印と、スマホの盤面の拡縮は「盤面の操作具」として例外。§2.5、§6.4）。現状の `.pe-btn` の使用箇所と置き換え先: `FieldView.js` の捨て札のモーダルの [Close]（トレイのラベルの行の右端の `secondary`。§4.3）、`EffectWindowState.js` の順番の上下ボタン 3 つ（§5.1 の ◀ ▶ の 2 つに）、`HandTargets.js` の [Choose from hand]（§5.1 の「欄」の共通の形）と選んだカードの [×]（廃止。もう一度押すと外れる）。
- **絵文字・記号の文字をアイコンに使わない**（✓ ★ ⌂ など）。印はコマ・アイコンのスプライト（`pe_pieces.webp`）か、インライン SVG で描く。現状の箇所:

| ファイル | 文字 | 用途 |
| --- | --- | --- |
| `ui/Layout.js`（`renderTools`） | `−` `⌂` `+` | スマホの盤面の拡縮 |
| `ui/PlayerStats.js`（`buildStats`） | `★` | 名声のカウンターのアイコン |
| `ui/PlayerPanel.js`（`render`）、`cards/HandView.js`（`ActionRow.render`） | `✓` | 確定の印（§4.4 で廃止） |
| `ui/ScoreSheet.js`（ミッションの行） | `✓` `–` | 達成の有無（文字と併記） |
| `cards/SatelliteView.js` | `◎`（`pe-chip-active-only`）、`×`（`pe-paper-used`）、`◀`（`pe-dir-arrow`、回転で向きを示す） | 稼働中のみ発動、使用済み、進行方向 |
| `board/Highlights.js`（`directionGlyph`） | `↗ → ↘ ↙ ← ↖`、売却バッジの `¥`（SVG の `text`） | 方向の矢印・方向パッド・効果の窓の方向の選択肢、売却すれば選べる区域のバッジ |
| `states/HandTargets.js` | `×` | 選んだカードを外す |
| `states/EffectWindowState.js`（`orderBlock`） | `⤒` `▲` `▼` | 順番の並べ替え |

変更するファイル:

| ファイル | 変更 |
| --- | --- |
| `src/projectedgeworth.css` | 背景、トークン、角丸・影・文字の大きさ・`font-size` 12 px 未満の整理、旧トークンの全置換、`pe-pulse` の廃止 |
| `src/modules/js/` の上の表の各ファイル | 記号の文字を SVG（またはコマ・アイコン）に置き換える |
| `src/img/pe_pieces.webp` | 変えない（新しいアイコンが要るときは SVG をインラインで持つ。画像は作らない） |

### 2. レイアウト

#### 2.1 領域と DOM の順

```text
#pe-root
  #pe-round       ラウンドトラック（§3）
  #pe-board-wrap  盤面（narrow は拡縮の行つき）
  #pe-supply      場（褒賞 2 枚・山札・捨て札）
  #pe-tray        トレイ（§5。必要なときだけ）
  #pe-score       得点表（終了時）
  #pe-me          自分の領域
  #pe-others      他プレイヤーの領域
```

- `#pe-phase-band`、`#pe-field`、`#pe-choice-panel`、`#pe-sheet` は廃止（`#pe-round`、`#pe-supply`、`#pe-tray` に置き換える）。現状は `ui/GameView.js` の `bandBox`・`fieldBox`・`panelBox`・`sheetBox`（`#pe-sheet` は `boardWrap` の子）で、表示の順は CSS の grid（`grid-template-areas`）が決めている。
- `#pe-root`: `max-width: 1400px`、中央寄せ、左右の余白 12 px（narrow は 8 px）。`position: fixed` / `sticky` をゲーム領域の中で使わない。現状の `.pe-root` は `max-width: 1400px`・`margin-inline: auto`・`padding: 8px 12px` で、これは変えない（narrow の余白だけ 12 → 8 px）。

#### 2.2 3 つのレイアウト（`data-layout`。`#pe-root` の内側の幅で決める）

| | wide（960 px 以上） | medium（640〜959 px） | narrow（640 px 未満） |
| --- | --- | --- | --- |
| 並び | round / [board \| supply] / tray / score / me / others | round / board / supply / tray / score / me / others | round / board / tray / score / me / supply / others |
| 場（supply） | 盤面の右の縦 1 列（4 枚）。カードの高さ = min(117, ⌊(盤面の高さ − 104) ÷ 4⌋)（ラベル 3 つと間隔のぶんを引く。試作で決めた） | 盤面の下の横 1 行（中央寄せ、72 × 101） | 自分の領域の下の横 1 行（64 × 90） |
| 区域の幅 | min(112, 幅に収まる値, 高さの上限) | 幅に収まる値（高さの上限あり） | 段階: 概観（幅に収める。見るだけの段で、32 px 未満でもよい。§2.5）/ 標準 56 / 大 80。盤面だけ横スクロール |
| 自分の衛星カードの幅 S | min(230, (W − 48) ÷ 5) | (W − 40) ÷ 5 | 3 列: (W − 16) ÷ 3 |
| 他プレイヤーの S | 他が 2 人以上かつ W ≥ 1180 なら 2 列で min(150, (列幅 − 32) ÷ 5)。それ以外は 1 列で min(150, (W − 32) ÷ 5) | min(150, (W − 32) ÷ 5) | (W − 24) ÷ 5（5 機を 1 行。横スクロールにしない。幅 390 px で約 68 px） |
| 手札のカード | 150 × 210 | 132 × 184 | 104 × 145 |
| 自分のアクションカード・ミッション | 92 × 129 | 84 × 117 | 64 × 90。アクションカード 5 枚は 1 行に入る幅 (W − 24) ÷ 5（上限 64。折り返さない）、ミッションは次の行 |
| 他プレイヤーのアクションカード・ミッション裏 | 44 × 62 | 44 × 62 | 36 × 50 |

- W = `#pe-root` の内側の幅。「幅に収まる値」= 盤面に使える幅 ÷ (列数 + 0.5)。wide の盤面に使える幅 = W − 場の列の幅 − 12。
- **高さの上限**（wide・medium）: 区域の幅 ≤ `window.innerHeight × 0.62 ÷ 5.49`（盤面の高さ = 5.49 × 区域の幅）。下限 56。ねらい: 盤面と、その下の自分のアクションカードの行が 1 画面に入る（A.1・C.4）。【実測】5.49 は、区域の高さ H = W × 503 ÷ 435（012 §3.2）と盤面の高さ 4.75 H の積（4.75 × 1.156 = 5.49）。
- しきい値を 1180 / 700 から 960 / 640 に変える（1366 px のノート PC で場を盤面の横に置くため）。値は `ui/layout-math.js` の定数にし、`tests/js` で検証する。
- 盤面と場は合わせて中央寄せ。小さい盤面（9 列・6 列）も同じ規則。
- **折り返す・スクロールしない**: 手札・他プレイヤーの衛星は `flex-wrap` で折り返す。入れ子のスクロールは narrow の盤面（`#pe-board-viewport`）だけ。
- **得点表**（`#pe-score`）: narrow でも横スクロールなしで収める。`table-layout: fixed`、行のラベルの列は 34 %、プレイヤーの列は均等。見出しのプレイヤー名は 1 行で省略記号（全文はツールチップ）。行のラベルはローマ数字 + 短い語。4 人・360 px で収まること。`.pe-score-scroll` の `overflow-x: auto` は廃止する（§10 の自動検査 6 の対象に含める）。中身（内訳の行・列・値）は変えない。
- narrow の他プレイヤーのロケット（幅 約 68 px）のキャプションは、燃料（アイコン + `n/m`）と状態のアイコンだけ。名前はツールチップ（試作で決めた。§4.1）。

現状との差（値が変わる箇所。`ui/layout-math.js` の定数）:

| 項目 | 現状 | 新しい値 |
| --- | --- | --- |
| `layoutOf` のしきい値 | 1180 / 700 | 960 / 640 |
| `HEX_W_MAX`（wide の区域の幅の上限） | 150 | 112 |
| `NARROW_STEPS`（標準 / 大） | 52 / 76 | 56 / 80 |
| `cardSizes`（手札、wide・medium / narrow） | 120 × 168 / 88 × 123 | 150 × 210 または 132 × 184 / 104 × 145（レイアウトごと。上の表） |
| アクションカード・ミッション（自分、wide・medium / narrow） | 84 × 117 / 60 × 84 | 92 × 129・84 × 117 / 64 × 90 |
| 他プレイヤーのアクションカード（wide・medium / narrow） | 48 × 67 / 36 × 50 | 44 × 62 / 36 × 50 |
| 褒賞カード | 108 × 151（narrow 76 × 106） | 場のカード（`#pe-supply`）に統一（上の表。4 枚の列 / 72 × 101 / 64 × 90） |
| 衛星カードの幅の上限（`SAT_W_MINE_MAX`、`SAT_W_OTHER_MAX`） | 240 / 160（narrow: 自分 200、他 120） | 230 / 150（narrow は上の表の式） |
| 衛星の並べ方 | 自分は 5 機 1 行（幅が 200 未満なら 3 機ずつ）、他プレイヤーは narrow で横スクロール | 全員が折り返しで下端をそろえる。他プレイヤーは narrow でも 5 機 1 行 |
| ズームの段（narrow） | 概観 / 標準 / 大 | 変わらない（値だけ上の表） |
| `HEX_W_MIN`（区域の幅の下限 32 px） | 概観にも当てる（幅 400 px 未満では概観が収まらない） | 概観には当てない（32 px 未満でもよい。§2.5）。標準・大・wide・medium は 32 px 以上 |

変更するファイル:

| ファイル | 変更 |
| --- | --- |
| `src/modules/js/ui/layout-math.js` | 上の表の定数と式（`layoutOf`、`computeHexWidth` に高さの上限と場の列の幅を渡す、`cardSizes`、`satelliteWidths`、`othersColumns`、`ROOT_GUTTER` を narrow で 8 に、概観に `HEX_W_MIN` を当てない、場のカードの高さの式）。`zoomWidth`・`zoomAvailableHeight` は §7 の `placeTip` に置き換わる |
| `src/modules/js/ui/Layout.js` | `update`（新しい変数を `#pe-root` に入れる。`window.innerHeight` を使う。変数名 `--pe-actionOther-w` を CSS が読む `--pe-actionother-w` と食い違わないようにそろえる不具合の修正を含む）、`renderTools`（§2.5） |
| `src/modules/js/ui/GameView.js` | DOM の順（§2.1）、`bandBox`・`fieldBox`・`panelBox`・`sheetBox` を `#pe-round`・`#pe-supply`・`#pe-tray` に |
| `src/modules/js/ui/PlayerArea.js` | 行 A・B・C（§2.3）、他プレイヤーの形（§2.4） |
| `src/modules/js/cards/HandView.js` | 手札の折り返し（narrow の重ね・横スクロールをやめる）、キャプション |
| `src/projectedgeworth.css` | `.pe-root` の grid、3 つのレイアウト、`.pe-hand`（`overflow-x: auto` を除く）、`.pe-player-other .pe-sats`（同） |
| `src/modules/js/ui/ScoreSheet.js`、`src/projectedgeworth.css`（`.pe-scoresheet`・`.pe-score-*`） | 得点表の見た目と幅（枠・色を新しい体系に。`.pe-score-scroll` の廃止。列幅の固定。名前の省略記号とツールチップ） |
| `tests/js/geometry.test.mjs` | `layoutOf`・`computeHexWidth`・`satelliteWidths` などの期待値を新しい値に |

#### 2.3 自分の領域（上から。盤面に近いほど共有、下ほど自分の手元）

```text
[レール + 名前]
行 A  ACTION CARDS [I][II][III][IV][V]      MISSIONS [M][M]
行 B  衛星 5 機（紙どおりに積んだロケット。下端をそろえる）
行 C  HAND  ……………………………… [Sell cards]
      [手札のカード …（折り返す）]
```

- 行 A を盤面のすぐ下に置くのは、毎ラウンドの計画（アクションカードを 1 枚選ぶ）を盤面を見ながら行うため。計画のためにアクションカードを別の場所へ移す処理（`PlayerArea.moveActionsTo`）は廃止。
- 行 B の下に行 C（配備は手札 → 衛星で、隣り合う）。
- 自分のアクションカードの下に、英語名のキャプション（`I Budget` など。12 px、`--pe-ink-dim`）。手札のカードの下にも英語名のキャプション（2 行まで）。カードの券面が日本語の印刷のため（ログのカード名と照らせるようにする）。現状、キャプションがあるのは `ui/CardPicker.js` の `pe-picker-name` だけ（手札・アクションカードには無い）。
- [Sell cards] は手札のレールの右端に置く（BGA 標準の `secondary`。`addActionButton` の `destination`）。手札 0 枚のときは出さない。現状は `PlayerArea.handTools`（手札の上端の行）に置いており（`ActionState.addSellButton`）、置き場所をレールの右端に移すだけで、`destination` の仕組みは変わらない。
- 並びの中央寄せ（試作で決めた）: 行 A は、アクションカードのグループとミッションのグループを隣り合わせ（間 32 px）にし、行全体を中央寄せにする（ミッションを右端へ離さない）。ゾーンのラベル（`ACTION CARDS`・`MISSIONS`）は各グループの左上に置く。ロケットの行・手札の行も中身を中央寄せ。手札のラベル `HAND` は手札の中身の左上、[Sell cards] は同じ行の右端。

計画（22）の流れの変更（012 追補 U-1 の 8 を変更。Q4）:

| 現状 | 変更後 |
| --- | --- |
| `PlanningPlot.peShowActionsPanel` が、計画の間、行 A の要素そのものを選択パネル（盤面の上）へ移す。`peRestoreActions`・`clearUi`・`onLeavingState`・`refresh` の上書きが、描き直しのたびに元へ戻す | 行 A はずっと自分の領域にあり、そこで選ぶ（`ActionRow.setSelectable` はそのまま）。上の移す処理（`PlanningPlot` の 5 つの上書きと `PlayerArea.moveActionsTo`）を削除 |
| 選択パネルの題 `Choose your Action Card` と hint | ステータスバーのタイトル（既存の `${you} must choose an Action Card`）に任せる。hint の文 `Sideways cards were used last round and cannot be chosen` は、横向きのカードの見た目（`pe-off`）とツールチップ `Used last round. Returns to your hand next round.`（既存）に置き換える |
| 確定後も 22 の間はパネルに置く（確定した札に `Planned`） | 行 A に置いたまま、確定した札に `Planned`（`ActionRow.render` の `pe-plan-badge`。既存） |

#### 2.4 他プレイヤーの領域

```text
[レール + 名前]  [アクションカード 5 枚（小）] [ミッション裏 2 枚（小）]
[衛星 5 機（小さいロケット。下端をそろえる）]
```

- 自分の領域より明らかに小さく（S は自分の 6 割程度）、ページの下（二次的な情報。A.1 の「Extended display zone」）。手札の欄は無い（枚数はパネル）。
- 観戦者は自分の領域が無く、全員をこの形で席順に並べる。

#### 2.5 盤面の拡縮（narrow だけ）

- 盤面の上に 1 行（高さ 36 px、右寄せ）の拡縮の行 `[−][⌂][+]` を**通常の流れの中に**置く（盤面に重ねない）。各 36 × 36 px、`aria-label` つき、アイコンは SVG。wide・medium では出さない（ブラウザのズームに任せる。A.5）。
- 現状との差: 現状も narrow だけだが、`.pe-board-tools` を盤面の枠（`.pe-board-wrap`）の右上に `position: absolute` で重ねており、ボタンは 32 × 32 px の文字（`−` `⌂` `+`）。段（概観 / 標準 / 大）の動き（`Layout.setNarrowStep`、`shiftNarrowStep`）は変えない。【確認】A.5 は「+」「−」「ホーム」を許す。
- **概観の段**（narrow の「概観」= 幅に収める段）は見るだけの段にする。区域の幅が 32 px 未満になってよい（`HEX_W_MIN` を概観には当てない）。概観では区域・コマを押す操作を受けず、盤面をタップすると、その位置を中心に「標準」へ切り替わる。自動検査の 3（32 × 32 px）は概観の区域を対象外にする（§10）。

### 3. ラウンドトラック（`#pe-round`。フェイズ帯の置き換え）

ステータスバーのすぐ下の 1 行（高さ 32 px 程度）。箱・背景・枠なしで卓の上に直接、中央寄せ。押せない（タブに見せない）。

```text
ROUND 3    Planning › I Budget › II Deploy › III Launch/Turn › IV Link › V Proposal › Orbit
```

| 状態 | 見た目 |
| --- | --- |
| 現在の段 | 白（`--pe-ink`）、太字、下に金の線 2 px |
| これから行う（選ばれた） | `--pe-ink` |
| このラウンドは行わない（誰も選ばなかった。公開後） | 不透明度 0.4 + 取り消し線 |
| 済んだ | `--pe-ink-dim` |

- I〜V の数字の後ろに、そのアクションを選んだプレイヤー（アクション選択ボーナスの保持者。`bonusHolders`）の色の小さな四角（8 px。設定 100 がオンのときは席順の数字入りの 14 px）。ツールチップに名前。
- 名前（Budget など）は wide では全部、medium・narrow では現在の段だけ。
- セットアップ中は `SETUP` の文字だけ。「このラウンドで終了」のバナー（`bga.gameArea.addLastTurnBanner()`）は出さない（下の「最終ラウンドの表示」のとおり、そう知らせられる時点がサーバーに無いため。2026-11-28 に訂正）。独自の文字（`pe-end-note`）は廃止。通知 N31 `gameEndTriggered` と N32 `finalScore` では、ラウンドトラックを `Game end` の表示にするだけ（ログには通知の文 `The game ends after this round` が出る）。
- 段にホバー / タップで、そのアクションカード（A001〜A005）のツールチップ（§7）。
- 状態の計算は純粋関数（`ui/round-track.js` など）にし、`tests/js` で検証する。

コードで確かめた前提:

- 現在の段の判定の入力は、`flow_phase`（`FlowPhase`: `SETUP`・`PLANNING`・`BUDGET`・`DEPLOY`・`LAUNCH_TURN`・`LINK`・`PROPOSAL`・`ORBIT`・`END`）と `round`（`GameView.refreshBand`、`notifications.js` の `phaseStarted`）。I〜V は `PHASE_ACTION`（`BUDGET` = 1 … `PROPOSAL` = 5）で対応する。
- 「選ばれた」「行わない」の判定は `bonusHolders`（アクション番号 → 保持者の ID の配列。キーがある = 選ばれたアクション。公開前は `{}`）。`GameView.refreshBand` が今も同じ入力で「選ばれたアクション」を出している。
- 設定 100（コマに席順の数字を重ねる）は `gamepreferences.jsonc` に宣言があるが、**クライアントにも CSS にも実装が無い**（`pe_marks_on` の参照が `src` のどこにも無い。【実測】）。宣言だけで実装が無いのは不具合で、E.1・E.2（色だけに頼らない）に必要なので、**今回実装する**（PR 6。メインエージェントの決定、2026-11-27）。オンのとき、コマ（衛星・発射拠点・キューブ）、ラウンドトラックの四角、プレイヤーの領域のレールの名前の前、パネルの移動のボタンの横に、席順の数字（1〜4）の印を出す。既定はオフのまま（紙のコマには印が無いため）。印は SVG / CSS で描き、画像は作らない。`cssPref`（`pe_marks_on` / `pe_marks_off`）のクラスで切り替える。席順の数字は `normalize.js` のプレイヤーのモデルに無いので、`playerOrder` の添字（1 始まり）から作る。`cssPref` が付けるクラスの付け先は、本書で読んだ公式文書には記載が無い（【未確認】。実装時に Studio で確かめる）。
- **最終ラウンドの表示（2026-11-28 に訂正。実装時にサーバーのコードで確かめた）**: 当初は「最終ラウンドを `bga.gameArea.addLastTurnBanner()` で出し、再読み込みでも出るよう `getAllDatas` に終了条件を満たしたかの真偽値を足す」と書いたが、取り消す。`EndFlow::check`（状態 110。`src/modules/php/Flow/Machine/EndFlow.php`）は、ラウンドの終わりの終了判定（`RoundEnd::check`）で終了が決まった時点で、`setFlowPhase(FlowPhase::End)` と N31 `gameEndTriggered` を同時に出し、その後すぐ換金（120）と名声の計算（121）へ進む。終了条件を満たしたプレイヤーの一覧（`endConditionPlayers`）を書き込むのも、この終了判定（`RoundEnd::check`）だけで、ラウンドの途中では更新されない（終了条件は、終了判定の時点の稼働中の衛星の数と通信確立の数で決まる）。したがって「このラウンドが最後」とラウンドの途中で確定して知らせる時点は無く、バナーを出せる時点が無い。再読み込みは `getAllDatas` の `phase === 'END'`（既に公開済み）でラウンドトラックを `Game end` に復元できるので、新しい項目も要らない。**バナーは出さない。サーバーは変えない（設定 101 の削除だけ）。**

変更するファイル:

| ファイル | 変更 |
| --- | --- |
| `src/modules/js/ui/PhaseBand.js` | 削除。`PHASE_ACTION` は `round-track.js` へ移す |
| `src/modules/js/ui/round-track.js`（新規） | 純粋関数（入力: `round`・`phase`・`bonusHolders`・`playerOrder`、出力: 段ごとの状態と保持者の一覧） |
| `src/modules/js/ui/RoundTrack.js`（新規） | 描画。ツールチップの指定（`data-pe-tip`） |
| `src/modules/js/ui/GameView.js` | `band`・`bandBox`・`refreshBand` を `roundTrack` に |
| `src/modules/js/notifications.js` | `gameEndTriggered`・`finalScore` はラウンドトラックを `Game end` にするだけ（バナーは出さない）。`refreshBand` の呼び出しは `refreshRound` に |
| `src/modules/js/Game.js` / `cards/CardView.js`（`Env`） | `Env` に BGA 標準のボタンを置く `addButton(label, callback, opts)` を足す（§4.3 の捨て札の [Close]。BGA の外のプレビューでは代用品）。バナーの `lastTurnBanner` は不要 |
| `src/projectedgeworth.css` | `.pe-phase-*`・`.pe-end-note` を `.pe-round-*` に |
| `src/modules/js/board/PieceLayer.js`・`ui/PlayerArea.js`・`ui/PlayerPanel.js`・`ui/RoundTrack.js`、`src/projectedgeworth.css`、`Game.js`（設定 100 の読み取り） | 設定 100 の印（PR 6） |
| なし（サーバーは変えない） | 最終ラウンドのバナーと `getAllDatas` への真偽値は取り消し（上の「最終ラウンドの表示」）。`010-data-model.md` の変更も無い |
| `tests/js/round-track.test.mjs`（新規） | 状態の計算 |

### 4. 部品の見せ方

#### 4.1 衛星 = 紙どおりのロケット（既定。全員）

- 012 §4.2 の「詳細表示」（案 B。1 枚目は衛星カード上部の窪みに重ね、2 枚目以降は下側約 55 % が見える向きで上へ積む。燃料コマを実際の燃料庫の位置に置く。待機中のコマは格納庫の枠に置く）を、**自分・他プレイヤーとも唯一の表示**にする。簡略表示（チップの行・効果アイコンの帯・横一列の燃料ゲージ・「+n」の印）と、ゲームの設定 101「Satellite cards」は廃止する（作者の確認事項 Q2）。
- 1 人分の 5 機は**下端をそろえて**並べる（`align-items: flex-end`。衛星カードが発射台の線に並び、ロケットが上へ伸びる。公式バナーと同じ）。
- 各ロケットの下に 1 行のキャプション: 衛星の名前（`Hammer (Moon)`）、燃料（燃料コマのアイコン + `7/9`）、稼働中は進行方向の矢印（SVG。`aria-label` に「Active, heading north-east」）、待機中は `Standby`。燃料切れは `Out of fuel`（警告色 + 警告のアイコン）。それ以外の文字（「+3」、「Active NE」の別行、燃料ゲージの行）は出さない。
- 起動効果の使用済み（歯車に ×、カードを少し暗く）、稼働中のみ発動の効果の薄い表示、予定（Planned）の半透明の札は今のまま（見た目だけ新しい体系に合わせる）。
- キャプションは、幅が足りないとき 2 行まで。行の高さは 2 行ぶんを確保して、ロケットの下端のそろいを崩さない（試作で決めた）。narrow の他プレイヤー（幅 約 68 px）は、燃料（アイコン + `n/m`）と状態のアイコンだけで、名前はツールチップ。

コードで確かめた前提と、設定 101 の削除で変わる場所:

- 衛星の名前（`Hammer (Moon)`）は既存の `satelliteName`（`satelliteLabel(shape, plan)`）が返す形と同じ（`R001B` は `HAMMER` / `MOON`）。燃料は `fuelGauge(fuelUsed, capacity)` の `remaining` と `fuelCapacity`。
- 現状の `SatelliteView.render` は `pe-sat-name`（見出し）+ `renderSimple` または `renderDetail` + `renderFuel`（ゲージの行と `Fuel R / C`）+ `renderFooter`（`Active ${dir}` と矢印 `◀`、`Standby`、衛星カードの起動効果の `Used`）で組み立てる。新しい形は、`renderDetail` を唯一の本体にし、キャプションの行（名前・燃料・向き / `Standby`）を足す。見出し `pe-sat-name`・燃料ゲージ・`renderFooter` の `Active` の行は、キャプションに統合して消える。
- 詳細表示の燃料コマの置き場（`placePaperMarker`）と、燃料庫の座標（`cards/fuel-slots.js`）は既存のまま使う。
- 進行方向の矢印の `aria-label`: 指示書は「Active, heading north-east」。既存の方向の語は `directionLabel`（`Up-right`・`Right` など）で `north-east` とは違うため、`Active, heading ${direction}` とし、`${direction}` は既存の `directionLabel` の語（`Up-right` など）を使う（既存の文言にそろえる。§12）。

| ファイル | 変更 |
| --- | --- |
| `src/modules/js/cards/SatelliteView.js` | `renderSimple`・`renderChips`・`typeIcon`・`renderFuel` のゲージの枠・`renderFooter` の `Active` の行・`STRIP_BOX`・`CHIP_SLOTS`・`detail` フラグ・`setDetail` を削除。キャプションを足す。`gearIcon` のチップ用の分岐は消える（詳細表示の `pe-paper-used` は残す） |
| `src/modules/js/ui/PlayerArea.js` | `detail`・`setDetail` を削除。`refresh` で `view.setDetail` を呼ばない |
| `src/modules/js/ui/GameView.js` | `detailAll`・`setDetailAll`、`setup` 内の `area.setDetail(this.detailAll)` を削除 |
| `src/modules/js/Game.js` | `PREF_SATELLITE_CARDS`、`setupPreferences` の設定 101 の読み取りと `onChange` を削除（`userPreferences` を使わなくなるなら関数ごと） |
| `src/gamepreferences.jsonc` | 設定 101 を削除し、先頭のコメントの 101 の説明も消す。設定 100 は残す（反映には Studio で再読み込み。作者の作業 9 と同じ種類の作業） |
| `tools/preview/main.js` | デモ `detail`（`view.setDetailAll(true)`）と `clear` の `view.setDetailAll(false)` を削除 |
| `tests/js/` | 設定 101 とチップを直接テストしているものは無い（grep で確認）。`fuelGauge` などの純粋関数は残す |
| `src/projectedgeworth.css` | `.pe-chip*`・`.pe-strip`・`.pe-fuel-row`・`.pe-fuel-cell`・`.pe-sat-simple` などを削除し、キャプションの CSS を足す |
| `docs/specs/012-ui-assets.md` | 注記のみ（→ 014 で変更） |

実装で決めた細部（PR 2。2026-11-28）:

- キャプションは、名前の行と、燃料・状態の行の 2 行。narrow の他プレイヤーは名前と状態の文字を画面から外し（読み上げには残す）、待機中の状態は一時停止の形のアイコン（SVG）、燃料切れは警告のアイコン、稼働中は矢印にする。このとき 5 機とも同じ 1 行の高さ（18 px）になる。
- 稼働中のみ発動の効果の薄い表示（`◎`）は、チップの行と一緒に廃止した（詳細表示には元々無く、配備カードの絵に印刷済みのため）。使用済みの歯車の × は詳細表示のまま SVG にした。
- 衛星の選択・強調（`pe-sat-selectable` など）の枠は、土台の衛星カードの上に重ねた枠（配備カードの積みより手前）にした。クラス名・注記の文字は PR 4 まで今のまま。
- 衛星カード・カードの間隔: 自分は wide 12 px・medium 10 px・narrow 8 px、他プレイヤーは 8 px（narrow は 6 px）。§2.2 の衛星の幅の式の 48 / 40 / 16 / 32 / 24 はこの間隔から出した値（`layout-math.js` の `SAT_ROW_PAD`・`SAT_OTHER_PAD`）。他プレイヤーの 2 列の間隔は 24 px（`OTHERS_GAP`。以前の定数 10 は CSS と食い違っていた）。
- narrow の自分のアクションカードの間隔は 6 px（幅 (W − 24) ÷ 5 の 24 は 4 つの間隔のぶん）。横向きの札は、回しながら札の幅に収まる大きさに縮める（narrow と他プレイヤー）。
- パネルのカウンターの `title` は、全廃（PR 3）まで既存の書き方のまま（文面だけ `Cubes left: …`・`Linked regions: …` に変更）。

#### 4.2 カードの大きさ

§2.2 の表。券面の小さな文字を読む手段はツールチップ（§7）。部品を大きくして、絵・コスト・名声・効果アイコンを 100 % の表示で見分けられるようにする。

#### 4.3 場（`#pe-supply`）

- 褒賞 2 枚（表）、山札（裏面 + 枚数の札）、捨て札（一番上の 1 枚 + 枚数の札）。枠・見出しの箱なし。各スロットの上に 12 px のラベル（`REWARDS`・`DECK`・`DISCARD`）。
- 捨て札を押すと、**モーダルではなくトレイ（§5）に一覧を開く**（もう一度押すか、トレイの [Close] で閉じる）。`pe-modal`（`position: fixed`）は廃止。

| ファイル | 変更 |
| --- | --- |
| `src/modules/js/cards/FieldView.js` | `openDiscard`・`closeDiscard`・`modal` を、トレイへ一覧を出す処理に置き換える（トレイの API は §5）。ラベルの足し方。`title`（5 か所）を `aria-label` とツールチップへ |
| `src/projectedgeworth.css` | `.pe-modal*` を削除。`.pe-field-group` の枠・見出しの文字は無し（現状も見出しの文字は無く、`aria-label` と `title`） |

#### 4.4 プレイヤーパネル（§8）と重複の整理

| 情報 | 置く場所（1 か所） | やめる場所 |
| --- | --- | --- |
| 地域 A〜H の通信確立 | パネルに「n/8」のカウンター 1 つ（ツールチップに地域の一覧） | 領域の見出しのアイコン 8 つの列 |
| 確定済み / 選択中 | BGA 標準のアクティブ表示（確定すると非アクティブになる。`setPlayerNonMultiactive`） | パネルの ✓ と回転のアイコン、アクションカードの裏の ✓ |
| 衛星の燃料・状態 | ロケットのキャプション 1 行 + 燃料コマの位置 | 燃料ゲージの行、「Active NE」の行 |
| 配備の残り枠 | 出さない（4 枚で満杯のときだけ、選択中に選べない表示と理由） | 「+n」の印 |

コードで確かめた事実:

- **地域 n/8**: 今の `linkedRegions` は、プレイヤーのモデルの配列（`PlayerModel.linkedRegions`: A〜H の文字の配列）。`ui/link-regions.js` の `linkedRegions(links, cells)` がキューブのある区域と盤面の `cells[].region` から計算し、`normalize.js`（`setup`）と `notifications.js` の `linkEstablished`（`refreshLinkedRegions` の後で `view.panels.get(p.id)?.update({})` と `view.areas.get(p.id)?.renderRegions()`）が更新する。現状、表示するのは `PlayerStats.regionRow`（`PlayerArea.renderRegions`）だけで、パネルの `buildStats` は出さない。新しいカウンターは `p.linkedRegions.length`（0〜8）で、表示は `buildStats` に 1 つ足せば済む（`linkEstablished` が既にパネルも更新している）。ツールチップの一覧は既存の `Region ${letter}: linked` / `not linked` の語を使い回せる。
- **確定のアクティブ表示**（サーバー: `src/modules/php/Game.php` の `peAfterConfirm`、`Flow/ConfirmMode.php`、`Flow/Machine/ChooseState.php`）: 案 B（`ConfirmMode::CURRENT = MODE_B`）では、**最初の確定**で `setPlayerNonMultiactive`（その人だけ非アクティブ。最後の 1 人なら遷移）。**選び直しの確定**は `ConfirmAction::None`（アクティブ状態を変えない）。クライアントの [Change my choice]（`ActionState.renderConfirmed`）は `editing = true` にして描き直すだけで、**BGA 上の本人は非アクティブのまま**選び直し、再確定は `#[CheckAction(false)]` の操作で受ける（008 §2-2 の「確定を保ったまま内容だけ変えられる」と一致）。例外: 配備のステージの取り消し（`ChooseState::peStage`、`StageResult::wasConfirmed`）は `setPlayersMultiactive([$playerId], …)` で本人を再びアクティブに戻す。したがって「確定済み」の BGA 標準の表示は、選び直している間も「非アクティブ」のままになる（指示書の「[Change my choice] で再びアクティブになる」は、配備のステージ以外では起きない）。サーバーは変えないので、本書はこの挙動をそのまま採る（§13 の決定）。
- 効果の窓（31）は確定の仕組みが別で、答えるたびに `peAct` が（問いが無くなった人を）`setPlayerNonMultiactive` する（§5.1）。
- **本人への見え方**（メインエージェントの決定、2026-11-27: そのまま）: 選び直し中も BGA 上は非アクティブ（= 有効な確定がある）で、他のプレイヤーから見て正しい。本人には、ステータスバーの [Change my choice] と、アクションカードなどの「Planned」の予定の表示（`showPlan`）で確定済みだと分かる。サーバーは変えない。

| ファイル | 変更 |
| --- | --- |
| `src/modules/js/ui/PlayerPanel.js` | `pe-check`・`pe-waiting`・`setWaiting`・`isBase` 以外の印を削除（基準プレイヤーの「1」は残す） |
| `src/modules/js/ui/StateFrame.js` | `enter` / `leave` の `panels.get(id)?.setWaiting(…)` を削除 |
| `src/modules/js/ui/PlayerStats.js` | `regionRow` と `pieceIcon(area_*)` の地域の列を削除し、`n/8` のカウンターを `buildStats` に足す |
| `src/modules/js/ui/PlayerArea.js` | `regionsBox`・`renderRegions` を削除 |
| `src/modules/js/notifications.js` | `linkEstablished` の `renderRegions()` の呼び出しを削除 |
| `src/modules/js/cards/HandView.js` | `ActionRow.render` の確定の `pe-check`（裏向きのカードの `✓`）を削除（`Planned` の札は残す） |
| `src/projectedgeworth.css` | `.pe-check`・`.pe-waiting`・`@keyframes pe-spin`・`.pe-regions`・`.pe-region*` |

### 5. トレイ（`#pe-tray`。盤面の下。選択パネルとボトムシートの置き換え）

盤面の外で、**卓に無い部品を選ぶ・読むとき**だけ出す。箱なし。レールとラベル、その下に中身。盤面の上には何も足さない（盤面が上がったり下がったりしない）。

| 中身 | 使う場面 | 見た目 |
| --- | --- | --- |
| 配られた / 回ってきた / 引いたカードの行 | ドラフト（15）、企画（91）、効果の窓の `CARDS`（手札に無い候補） | 手札と同じ大きさのカード + 英語名のキャプション。選べる / 選択中は §6 |
| コストと結果の行 | 配備・打ち上げ・通信確立の選択中 | 文字だけ（14 px）。**1 行にまとめる**（収まらないときだけ 2 行）。例: `Link cost 8 Maya (Area 5 + Pioneer 3 to Bruno) · 22 Maya left`。警告はアイコン + 警告色 |
| 効果の問い（1 件ずつ） | 効果の窓（31）。§5.1 | 発生源のカード（小）+ 効果文 + 必要な入力 |
| 方向パッド | 区域の幅 90 px 未満のときの打ち上げ / 転回 | 2 列 × 3 行、各 56 × 48 px |
| 捨て札の一覧 | 捨て札を押したとき | カードの行（折り返す） |

- マリガン（13）は、トレイではなく**手札のゾーンで直接**選ぶ（引き直すカードに印）。現状もすでに手札のゾーンで選んでおり（`MulliganState.renderEdit` が `hand.setSelectable` / `setChosen`）、選択パネルを使っていない。印は既存の `hand.setPlanned`（`Discard`）。
- 計画（22）は自分の領域の行 A で選ぶ。トレイは使わない。
- 説明文は原則ステータスバーのタイトル 1 行にまとめ、トレイに hint の文を重ねない。

現状の呼び出し箇所と置き換え（`view.panel.show` → トレイ、`view.sheet.show` / `StateFrame.chooseDirection` / `showCost` → トレイ）:

| 呼び出し | 場所 | トレイでの扱い |
| --- | --- | --- |
| 選択パネル（`view.panel.show`） | `PlacementStates.js`（22）、`DraftState.js`（15）、`ProposalState.js`（91。2 か所）、`EffectWindowState.js`（31）、`ActionState.costBox`（売却の欄） | 22 は使わない（§2.3）。15・91 は「カードの行」。31 は §5.1。`costBox` の売却の欄は手札のゾーンの欄（`handField`）なのでトレイには「コストの行」だけ |
| ボトムシート（`view.sheet.show`） | `StateFrame.chooseDirection`・`showCost`、`ActionState.costBox`、`LinkState.js`、`DeployState.js`（2 か所）、`LaunchState.js`（3 か所） | 「コストと結果の行」と「方向パッド」。`BottomSheet.directionPad` の 2 列 × 3 行（各 56 × 48 px）をトレイへ移す |

| ファイル | 変更 |
| --- | --- |
| `src/modules/js/ui/Tray.js`（新規） | `ChoicePanel` と `BottomSheet` を 1 つにしたトレイ（ラベル + 本体。`show` / `hide`。開いたとき画面外なら中央へ寄せる動き（`ChoicePanel.scrollToView`）は、盤面を上下させない方針に合わせて見直す） |
| `src/modules/js/ui/ChoicePanel.js`、`ui/BottomSheet.js` | 削除 |
| `src/modules/js/ui/GameView.js` | `panelBox`・`sheetBox`・`panel`・`sheet` を `tray` に |
| `src/modules/js/states/*.js` | 上の表の呼び出し箇所すべて |
| `src/modules/js/states/MulliganState.js` | 変更なし（確認のみ） |
| `src/projectedgeworth.css` | `.pe-choice*`・`.pe-sheet*`・`.pe-dpad*` をトレイの CSS に |

#### 5.1 効果の窓（31）を 1 件ずつにする

- 複数の問いがあっても、**表示は 1 件**。トレイの左に「未解決の問いの発生源のカード」を小さく並べ（順番待ちの列）、注目中の 1 件を選択中の見た目にする。別のカードを押すと注目を切り替えられる（`ORDER` の問いが先にあるときはそれが先）。
- ステータスバー: タイトル「${you} may use ${card_name}」（必須は「${you} must resolve ${card_name}」、順番は「${you} must choose the order of your card effects」）、ボタンは **[Use]（青）・[Skip]（白）**、順番の問いは **[Confirm order]（青）**。パネル内のボタン（`pePanelButton`）はやめる（012 追補 U-1 の 6 を変更）。**入力が足りないあいだ**は、タイトルを次の形にする。通常は「${you} may use ${card_name}: ${todo}」、必須のときは「${you} must resolve ${card_name}: ${todo}」。ここで ${todo} は、今の `needText` の中身（`choose a card to deploy and a satellite` など。先頭の「Choose: 」を外して小文字で始める）にする。入力がそろったら `${todo}` なしのタイトル。トレイには出さない（BGA の標準: タイトルが「次に何をするか」を言う。メインエージェントの決定、2026-11-27）。
- 入力は部品で行う: 区域 = 盤面、衛星 = ロケット、手札のカード = 手札のゾーン、手札に無いカード = トレイのカードの行。**欄の共通の形**（メインエージェントの決定、2026-11-27）: 手札・盤面・ロケットのどれで入力する欄でも、トレイの中に、欄ごとに 1 行「ラベル + 今の値（文字。カード名・衛星名・区域）」を出す。入力先になっている欄（アクティブな欄）は 1 つだけで、`pe-picked` の見た目（金の輪郭）にする。欄が 2 つ以上あるとき、アクティブでない欄の行に BGA 標準の `secondary` ボタン（`addActionButton` の `destination`）を置く。文言は手札の欄は既存の `Choose from hand`、それ以外は新しい `Choose`。欄が 1 つだけならボタンは出さない。最初の未入力の欄が自動でアクティブになり、入力が済むと次の未入力の欄へ移る（012 追補 2026-11-14 B-2 の案 A と同じ動き。それを `AREA`・`SATELLITE` の欄にも広げる）。これで、`AREA`・`SATELLITE` が 1 つの問いに複数あるときに最初の 1 つしか盤面で選べない（今の `areaDriver ??= driver`）問題を解消する。補助のチップ（`pe-chip-btn`）は廃止。選んだカードの [×] は廃止し、手札のカード（または盤面・ロケット）をもう一度押すと外れる（トグル）。欄の行は名前の文字だけ。
- 選択肢のボタン（`AMOUNT` の候補、`DIRECTION`、配備以外の `PAIRS` の選択肢）: BGA 標準の `secondary`。選んだものは青にせず、`secondary` のまま `pe-picked`（金の輪郭 2 px）と `aria-pressed="true"`（C.3 の「青 = 前進」と混ぜない。指示書の「選んだものは `primary`」は取り消した）。
- 候補の無い数値（`<input type="number">`）: 値が 8 通り以下なら選択肢のボタン、それより多ければ [−] 値 [+] の 3 つ（BGA 標準の `secondary`、ラベルは SVG、`title` に `Decrease` / `Increase`）。
- 順番の並べ替え（`ORDER`）: 効果のカードを小さく横に並べ、各カードに順番の数字。カードの下に [◀][▶]（BGA 標準の `secondary`、ラベルは SVG、`title` は `Move earlier` / `Move later`）。「先頭へ」（⤒）は廃止。`ORDER` は 1 組ずつ表示し、待ち列は出さない（確定すると N34 で次が届く）。
- 付け替え（`ASSIGNMENT`）: `<select>` は廃止し、部品だけで操作する（配備カードを押す → 移す先のロケットを押す。今の `assignDriver`）。トレイには決めた移動を 1 行ずつ文字で出し（`${card} → ${satellite}`）、各行に取り消しの小さなボタン（BGA 標準の `secondary`、SVG の ×、`title` は `Remove`）。
- `USE_MODS`（起動の修正のチェックボックス）: トレイの中に BGA 標準の `secondary` のトグルボタン（オンは `pe-picked` と `aria-pressed`）。
- 捨て札の一覧の [Close]: BGA 標準の `secondary`（`destination` はトレイのラベルの行の右端）。
- 発生源のカード（衛星の上の配備カード）を「対象」の見た目（§6）で強調する。
- サーバーの操作（`actUseEffect`・`actSkipEffect`・順番の確定）と通知は変えない。

コードで確かめた今の構造（`states/EffectWindowState.js`、`states/logic/effects.js`、`src/modules/php/States/EffectWindowResolve.php`）:

- **問いの種類**は 2 つ。`ORDER`（衝突する効果の処理順。`entries`・`defaultOrder`）と `EFFECT`（1 つの効果。`entryId`・`cardKey`・`mandatory`・`cost`・`fields`・`preview`・`hostSat`）。サーバーは、あるプレイヤーの問いを「ORDER の組の一覧」か「EFFECT の一覧」のどちらか一方で返す（`EffectEngine::questionsFor` の `PlanKind::Order` / `PlanKind::Ask`。混ざらない）。したがって「ORDER が先」は、設計上、混在しないので自然に満たされる。ORDER の組が複数あるときは 1 組ずつ表示し、待ち列は出さない（確定すると N34 で次が届く。§5.1、§13）。
- **フィールドの種類**は 8 つ: `SATELLITE` / `AREA` / `DIRECTION` / `AMOUNT` / `CARDS` / `PAIRS` / `ASSIGNMENT` / `USE_MODS`（`logic/effects.js` の冒頭。`fieldEditor` が種類ごとの入力を作る）。**指示書の `CHOICE` という種類は存在しない**。「数量などの選択肢」に当たるのは `AMOUNT`（候補がある場合のチップ）と `DIRECTION`・`PAIRS`（配備以外）。
- **サーバーの操作**（`EffectWindowResolve.php`、`#[PossibleAction]`）: `actUseEffect(int $entryId, array $fields, ?array $sell)`（[Use]）、`actSkipEffect(int $entryId)`（[Skip]）、`actOrderEffects(array $entryIds)`（[Confirm order]）、`actSell(?array $cardIds)`（売却。`#[CheckAction(false)]`）。答えるたびに `peAct` が通知 N34（残りの問い）と N35（待機表示）を送り、問いが無くなった人を `setPlayerNonMultiactive`。クライアントは `update`（N34）・`setWaiting`（N35）・`close`（N26）で受ける。
- **今のボタン**: `ActionState.pePanelButton(container, label, callback, opts)` が `addActionButton(..., { destination: container })` を呼ぶ。呼び出しは `EffectWindowState` の 3 か所だけ（`orderBlock` の [Confirm order]、`effectBlock` の [Use] と [Skip]）。

どの関数をどう変えるか:

| 関数（`EffectWindowState`） | 変更 |
| --- | --- |
| 新しい状態 `focus` | 注目中の問いの ID（`EFFECT` は `entryId`、`ORDER` は組の識別子）。問いが入れ替わったとき（`load` / `update`）に、残っていれば維持、無ければ先頭（`ORDER` があればそれ）にする |
| `renderWindow()` | 全問いの本体を積むのをやめ、注目中の 1 件だけをトレイに描く。待ち列（発生源のカードを小さく並べる）を足す。区域・衛星・付け替え・配備の組の「ドライバ」（`areaDriver`・`satDriver`・`assignDriver`・`pairsDriver`）も、注目中の問いのものだけを集める。ステータスバーのタイトルを問いごとに `bga.statusBar.setTitle(title, args)`（`card_name` を渡す）にする。[Use]・[Skip]・[Confirm order] を `bga.statusBar.addActionButton`（`destination` なし）で置く |
| `orderBlock(q)` | 並べ替えの行（効果のカードを小さく横に並べ、順番の数字、各カードの下に [◀][▶]）だけを返し、`buttons` と `pePanelButton` を削除。`ORDER` は 1 組ずつ表示する。[Confirm order] は `renderWindow` が置く（`act('actOrderEffects', { entryIds: order }, sig.length)` は同じ）。`moveEntry` の `'top'` は使わなくなる |
| `effectBlock(q, …)` | 発生源のカード + 効果文（`head`）、コストの行（`costLine`）、欄の行（フィールド）、結果の見込み（`previewLine`）、売却の欄（`sellPrompt`）を返す。`buttons`・`pe-effect-need`（`needText`）を削除。[Use]（`disabled: !ready`）と [Skip]（必須でなければ）は `renderWindow` がバーに置く。`needText` の中身は `renderWindow` が `${todo}` としてタイトルに使う |
| `ActionState.pePanelButton` | 呼び出しが 0 件になるので削除 |
| `states/HandTargets.js` | `section` の [Choose from hand]（`pe-btn`）と選んだカードの [×] を、欄の共通の形（`addActionButton` の `destination`）に置き換え、トグルで外す。`pe-handfield-*` を新しい欄の行に |
| `fieldEditor(…)` | 補助の `pe-chip-btn` の行を廃止し、欄の共通の形（ラベル + 今の値の 1 行、アクティブな欄の `pe-picked`、アクティブでない欄の `Choose` / `Choose from hand`）に置き換える。`AREA`・`SATELLITE` の欄が複数あっても、アクティブな欄が盤面・ロケットの入力先になる（`areaDriver`・`satDriver` を「最初の 1 つ」から「アクティブな欄」に）。`AMOUNT`（候補あり）・`DIRECTION`・`PAIRS`（配備以外）は `secondary` のボタン（選んだものは `pe-picked` + `aria-pressed`）。候補の無い `AMOUNT` は 8 通り以下ならボタン、多ければ [−] 値 [+]。`CARDS`（候補が手札）は手札のゾーン（`handField`）のまま、手札にない候補は `CardPicker` をトレイのカードの行に。`ASSIGNMENT` は `<select>` を廃止して部品だけ（トレイに `${card} → ${satellite}` の行と取り消しボタン）。`USE_MODS` は `secondary` のトグルボタン |
| `showSubjects(cellsInUse)` | 発生源のカード（`q.cardKey` の配備カード）を `pe-subject` にする（新しい `SatelliteView.setSubjectCard`。配備カードの要素は `data-key` を持つ）。衛星の枠と札 `This effect is for this satellite` は、対象の衛星があるときの強調として残す（衛星の枠は土台の衛星カードの `pe-subject`。§4.1・§6.1） |
| `refresh()` | `view.panel.hide()` を `view.tray` の操作に。`this.focus` の維持 |
| `waitingText`・`act`・`update`・`setWaiting`・`close`・`valuesOf`・`sells`・`orders`・`pairPending`・`pickedCards` | 変更なし（入力値は問いごと（`entryId`）に保持されるので、注目を切り替えても失われない） |

[Use] が押せないとき（`validateQuestion` が `ok: false`、売却が足りない、`busy`）は、ボタンを無効（灰色）にする。理由は、ステータスバーのタイトルの `${todo}`（上のとおり）に出す。`tooltip` には出さない。

実装で決めた細部（PR 5。2026-11-28）:

- 注目中の問い・待ち列・数値の形・移動の抽出は `states/logic/effects.js` の純関数（`questionKey`・`pickFocus`・`queueQuestions`・`numberChoices`・`stepNumber`・`lowerFirst`・`assignmentMoves`）。待ち列は EFFECT が 2 件以上で ORDER が無いときだけ出し、トレイの左に発生源のカード（56 px。選択中は `pe-picked`、他は `pe-can`、`role="button"`・`aria-pressed`・Enter / Space）を並べる。narrow は問いの上に横並び。注目は `focus`（questionKey）で持ち、`load`（`update`）で `pickFocus`、切り替えると `handActive` を捨てて最初の未入力の欄に戻る。入力値は従来どおり `entryId` ごと。
- 「欄」は `HandTargets` に `kind: 'direct'`（手札を使わない欄）を足して、区域・衛星・手札に無いカードも同じ仕組み（入力先は 1 つ、次の未入力の欄へ進む、アクティブでない欄に `Choose`）にした。区域・衛星の欄は `selected` に区域・衛星の番号を 1 つ（必須。`need: 1`）、手札に無いカードの欄は `extra` の関数（選択中のときだけトレイのカードの行 `CardPicker`）。手札の印・他の欄に取られたカードの判定は手札の欄だけ（`handStates`）。盤面・ロケットの入力口は `drivers`（欄の id → `area` / `satellite` / `pairs`）に持ち、入力先の欄が決まった後（`mountHandTargets` の後）に、入力先の欄のものだけを出す。盤面・ロケットを押すと `handTargets.change(id, …)` で次の未入力の欄へ進む。配備の組が未入力でも、組にした衛星の予定の札（`plans`）は入力先でなくても出す。付け替えは欄でなく常に入力先（行は常に `pe-picked`）。
- ステータスバー: `fixedTitle`（`ActionState`）を立て、欄のタイトル（`HandTarget.title`）でバーを上書きしない。タイトルは §12 のとおり `${card_name}`・`${todo}` を args で渡す（プレビューの `setTitle` も args を埋める）。足りないのが売却だけのときは、今までの売る枚数の案内（`Choose at least ${need} card(s) to sell …`）をタイトルにする。配備の組の途中（カードだけ・衛星だけ）の案内は、`${todo}` に反映せず `a card to deploy and a satellite` のまま（押せる部品の強調が次の入力を示す）。[Use] と [Confirm order] の `tooltip` は `This choice is final`。タイトルから `Your choices are final once you press the button` を外した。
- ボタンは `ui/buttons.js`（`pressButton` = 選択肢・トグル、`iconButton` = ◀ ▶ − + ×）。どちらも `addActionButton(…, { color: 'secondary', destination })`。選んだものは `classes: 'pe-picked'` と `aria-pressed`、記号のボタンは SVG のラベル + `title` 引数 + `aria-label`（戻り値が要素のとき）。方向は SVG の矢印 + 語。**注意**: `destination` で `#pe-root` の中に置いた `title` 引数のボタンは、BGA が DOM の `title` 属性にするため、PR 6 の自動検査 §10 の 4（`#pe-root` の `[title]` が 0）に入る。PR 6 は、トレイの中の標準ボタンの `title` を検査の例外にするか、`title` 引数をやめて `aria-label` のみにするかを決める。
- 付け替え: 移動の行は `${card} → ${sat}`（`assignmentMoves` = もとの置き先と違うカード）。取り消し [×] はそのカードをもとの置き先へ戻す（`moveCard`。置き先が 4 枚で戻せないときは変わらない）。カードを押して選んだ状態は `${card} — choose a satellite` の行。何も無いときは `None`。
- 数値: 候補の無い `AMOUNT` は `numberChoices`（`max - min + 1` が 8 以下）でボタン、それより多ければ [−] 値 [+]（値が無いときは `None`。最初の [+] / [−] は下限の値）。上限・下限では押せない。
- 発生源の強調: `SatelliteView.setSubjectCard` / `PlayerArea.setSubjectCard`。発生源が自分の衛星に配備されたプロジェクトカードのときだけ、その配備カードを `pe-subject`（衛星カードが発生源の効果は、これまでの土台の衛星カードの強調のまま）。強調は注目中の問いだけ。
- 欄の行のラベル（新）: `Satellite`・`Area`・`Direction`（既存）・`Payment`・`Amount`・`Cards`・`Option`・`Moves`。待ち列の `aria-label`: `Card effects to resolve`。新しい操作の文: `This choice is final`、`Choose`（§12）。ORDER の説明文（`The order of these effects changes the result. …`）は hint なので出さない。`Move to the top`・`Move up`・`Move down`・`Choose: ${field}`・`Your choices are final once you press the button`・`${you} must resolve your card effects` は使わなくなった。
- 代価の行・結果の行は、コストと結果の行（`costLine`）の形。足りないときの文は警告（アイコン + 警告色）。
- 撮影の道具: 見本の steps に `notify`（`[通知名, args]`）、`?nosteps=1`（見本の steps を再生しない）を足した。効果の窓の見本は、入力をそろえて [Use]（[Confirm order]・[Skip]）を押すところまで進める。

### 6. 操作の見た目（1 つの言語）

#### 6.1 共通のクラス（カード・ロケット・アクションカード・コマに同じ意味で使う）

| クラス | 意味 | 見た目 | 色以外の手がかり |
| --- | --- | --- | --- |
| `pe-can` | 選べる | 白の輪郭 2 px + シアンの外側の光（`box-shadow: 0 0 0 2px var(--pe-can), 0 0 12px 4px var(--pe-can-glow)`）。マウスは pointer、ホバーで 4 px 持ち上がる（`.notouch-device` のみ） | 輪郭の有無 |
| `pe-can-sell` | 売却すれば選べる | 白の破線の輪郭 + マヤの硬貨の小さな印 | 破線、印 |
| `pe-picked` | 選択中 | 金の輪郭 3 px + 角にチェックの印（SVG） | 太さ、印 |
| `pe-off` | 今は選べない | 暗く（`filter: brightness(0.55) saturate(0.6)`）。押すと理由をメッセージで出す | 暗さ + 押したときの文 |
| `pe-subject` | 問いの対象・発生源 | 金の破線の輪郭 | 破線 |

- 旧クラス（`pe-selectable`、`pe-selected`、`pe-sat-selectable`、`pe-sat-selected`、`pe-sat-blocked` など）はこの 5 つに統一する。点滅（0.8 秒周期）はやめる（D.3・E.1）。
- **選択中のロケットの注記**（試作で決めた）: 選べるロケットには、土台の衛星カードの右上にコストの札（マヤの硬貨のアイコン + 数。例 `8`）を出す。選べないロケットは暗くするだけで文は出さない（理由は押したとき・ツールチップ）。今の「Cost 8」「Cannot reach this area」などの文字の行（`SatelliteView` の `pe-sat-note`）は廃止する。
- **ロケットに付ける場所**（試作で決めた）: `pe-can`・`pe-can-sell`・`pe-picked`・`pe-off`・`pe-subject` は、ロケット全体を囲む四角ではなく、**土台の衛星カード**に付ける（上に積んだ配備カードは枠で囲まない）。配備カードを直接選ぶ場面（付け替え）は、その配備カードに付ける。

旧クラスの対応と使っているファイル【実測】:

| 旧 | 新 | 使っているファイル |
| --- | --- | --- |
| `pe-selectable` | `pe-can` | `cards/HandView.js`（手札・アクションカード）、`ui/CardPicker.js` |
| `pe-unselectable` | `pe-off` | `cards/HandView.js`、`ui/CardPicker.js`、`states/DraftState.js` |
| `pe-selected` | `pe-picked` | `cards/HandView.js`、`ui/CardPicker.js`、`ui/BottomSheet.js`（方向パッド）、`board/Highlights.js`（矢印）、`states/EffectWindowState.js`（`pe-chip-btn`） |
| `pe-sat-selectable` / `pe-sat-pickable`、`pe-sat-sellable`、`pe-sat-blocked`、`pe-sat-selected` | `pe-can`、`pe-can-sell`、`pe-off`、`pe-picked` | `cards/SatelliteView.js`（`applyPick` が `pe-sat-${state}` を動的に作る） |
| `pe-card-pickable` / `pe-card-picked` | `pe-can` / `pe-picked` | `cards/SatelliteView.js`（`applyCardPicks`。配備カードを押して選ぶ付け替え） |
| `pe-sat-subject`（+ `pe-sat-subject-tag`） | `pe-subject` | `cards/SatelliteView.js`（`applySubject`） |
| `pe-hl-selectable` / `pe-hl-sellable` / `pe-hl-selected`（盤面の SVG） | 盤面の実装として残し、見た目を上の 5 つの意味に合わせる | `board/Highlights.js`、CSS |
| `pe-planned`（確定後の予定の札） | 変えない（選択の状態ではない） | `cards/HandView.js`、`states/DraftState.js` |

#### 6.2 盤面

- 区域の選択中は、**選べない区域を暗くする**（盤面に `pe-board-selecting`、選べる区域以外に暗い幕。不透明度 0.5）。選べる区域は白 + 濃色の二重の輪郭、ホバーで明るく、選択中は金の太い輪郭 + 半透明のコマの予告（今のまま）。012 §3.9 の「選べない: 何も出さない」を変更。
- 衛星コマ・発射拠点・キューブにツールチップ（§7）。

実装の手がかり: ハイライトは `board/Highlights.js` の `set(list)` / `draw()`（盤面と同じ大きさの SVG に六角形を描く。左右のゴーストも描く）。暗い幕は、選択中（`StateFrame.selecting` が真で `pickable()` が空でないとき）に、`entries` に無い区域（ゴーストを含む）を暗い六角形で描く形になる【提案】。`StateFrame.selectCells` / `restoreHighlights` / `markSelected` はいずれも `set` を呼ぶので、幕もそこで更新される。

#### 6.3 選べないものを押したとき（D.1）

- 選択中に `pe-off` の部品や選べない区域を押したら、`bga.dialogs.showMessage(理由, 'info')` で理由を出す（例: `This satellite already has 4 cards`、`You cannot reach this area`）。無反応にしない。理由の文は、サーバーの args の `blocked` の符号を `states/texts.js` で文にしたもの（`SatellitePick.note`、手札の `reasons`）を使い、無ければ一般の文（`You cannot choose this now`）。`error` はログに載るため使わない（指示書の `'error'` は取り消した）。同じ文は 2 秒以内に繰り返さない。

コードで確かめた事実:

- **理由の元**: サーバーの args が持つのは理由の**符号**（`blocked`: `full`・`forbidden`・`active`・`no_project`・`no_launch_pad`・`no_inactive_satellite` など。`LaunchOption::BLOCKED_*`、`Flow/Action/*`）。英語の文にするのはクライアントの `states/texts.js`（`deployBlockedText`、`launchBlockedText`、`turnBlockedText`、`costNote`）で、その結果が `SatellitePick.note` に入る（`note` はクライアント側のフィールド名）。指示書の「サーバーの args にある `note`」は、正しくは「args の `blocked` を `texts.js` で文にしたもの（`SatellitePick.note`・`HandView` の `reasons`）」。
- **今の無反応**: `ui/StateFrame.js` の `handleCellPick`（選べない区域は何もしない）、`cards/SatelliteView.js` の click（`pick.state === 'blocked'` は `return`）、`cards/HandView.js` / `ui/CardPicker.js`（選べないカードにはクリックの処理が無く `title` だけ）。
- **種類**: 【確認】`showMessage` の `error` は画面の上に赤く出てログにも載り、文書は「介入的なので多用しない」と書く。誤クリックのたびにログへ載せないよう、`info`（白い背景。ログに載らない）にする（メインエージェントの決定、2026-11-27）。

| ファイル | 変更 |
| --- | --- |
| `src/modules/js/Game.js`、`cards/CardView.js`（`Env`） | `Env` に `showMessage(message)` を足す（BGA では `this.bga.dialogs.showMessage(message, 'info')`。プレビューでは代用品） |
| `src/modules/js/ui/StateFrame.js`、`cards/SatelliteView.js`、`cards/HandView.js`、`ui/CardPicker.js` | 選べないものを押したときに `env.showMessage` を呼ぶ（理由が無ければ一般の文。2 秒以内の同じ文は出さない） |

#### 6.4 方向の矢印

- 区域の周囲の 6 つの矢印（区域の幅 90 px 以上）は、白い円 + 濃色の矢印、選んだ向きは金。40 × 40 px。それ未満はトレイの方向パッド。
- 現状: 矢印は 36 × 36 px の円（`.pe-arrow`）で、矢印は文字 `↗ → ↘ ↙ ← ↖`（`directionGlyph`）。SVG の矢印に置き換える（§1.2）。出し分けは `Layout.update` の `data-hex-size`（90 px 以上 `large`）が既にある。

#### 6.5 ステータスバー

- ボタンの色・並び・数（4 個以内）は 012 追補 U-1 のまま。効果の窓だけ §5.1 のとおりバーへ移す。

実装で決めた細部（PR 4。2026-11-28）:

- 5 つのクラスの CSS は、カード（`.pe-card`）の `::after`（輪郭 + 角の印）に当てる。印は CSS 変数の SVG（`--pe-coin`・`--pe-check`）。タッチの情報の印は左上、チェックの印は右上なので重ならない。ロケットの土台の衛星カードは、右上がコストの札なので、チェックの印だけ左下にした。売却すれば選べる衛星は、コストの札が硬貨を持つので、カードの隅の硬貨の印は付けない。盤面の矢印・方向パッドは、`pe-picked` を金の塗りにする（チェックの印は付けない。盤面の操作具）。
- ロケットの選択の状態は、土台の衛星カードの 4 つのクラス（`pe-can` / `pe-can-sell` / `pe-picked` / `pe-off`）と、ロケットの根の `data-pick`（`selectable` / `sellable` / `blocked` / `selected`）。選べないロケットを暗くするのは、根の子（積んだカードとキャプション）にかける。`SatellitePick` の `note` は、数値の `cost`（札）と、選べない理由の `reason`（押したとき）に分けた。
- 区域の暗い幕は `Highlights.draw`。区域を選ぶ（`pickable()` が空でない）間だけ、`entries` に無い区域と、そのゴーストを暗い六角形（不透明度 0.5）で描き、盤面に `pe-board-selecting` を付ける。方向の選択（選択中の区域 1 つだけ）や効果の窓の対象の強調では幕を出さない。選べる区域のホバーは、押せない SVG の下のタイル（`pe-hl-pick`）を明るくする。選べない区域を押したときの理由は、`StateFrame.selectCells` の第 3 引数（通信確立は `You cannot reach this area`、ほかは一般の文）。選択中の区域を押し直しても何も出さない。
- 理由のメッセージは `ui/refuse.js`（`refuse(env, reason)`）。同じ文は 2 秒以内に繰り返さない（モジュールの記録）。選択中でない部品にはクリックの処理を付けないので、操作の番でないときは何も出ない。カード選択の上限に達したときの追加（`CardPicker`）は `You can choose up to ${max} card(s)`。
- トレイ（`ui/Tray.js`）は `show(sections)` / `hide()` と、捨て札の `showList` / `hideList`。節は `{ label?, body }`（`label` はレールのラベル）。部品は `costLine(parts, { warn?, ok? })`（`·` でつないだ 1 行。警告は SVG のアイコン + 警告色）、`directionPad`、`CardPicker`（手札と同じ大きさ。効果の窓の小さな一覧は `small: true`）、欄の行（`HandTargets.section`）。効果の窓（PR 5 まで）は、今の一覧を 1 つの節として載せる。
- 欄は `pe-field`（ラベル + 今の値 + 右端のボタン）。選択中の欄は `pe-picked`（金の輪郭 + 左端のチェックの印）。欄が 2 つ以上のとき、選択中でない欄にだけ `Choose from hand`。値が空のときは `None`、使えないときは理由を値の位置に出す。選んだカードの [×] は無く、手札のカードをもう一度押すと外れる。
- 売却の促しの文（`Needs ${short} more Maya. Choose at least …`）はトレイから外した。足りないぶんは、コストの行の警告（`Needs ${short} more Maya: sell at least ${need} card(s)`。売るカードの欄の名前は `Cards to sell`）に、売る枚数の案内はステータスバーのタイトル（今までどおり）に出す。使えるマヤが足りず売っても届かないときは、警告 `Not enough Maya (short ${short})`。
- 通信確立のコストの行は `Link cost ${total} Maya (Area ${area} + Pioneer ${pioneer}) · ${left} Maya left`。§12 の `to ${name}`（先駆コストの支払い先）は、サーバーの args がその名前を持たない（先駆コストは最悪値）ので付けていない。「同時に通信確立した場合は先駆コストが無い」の説明は、コストの行のツールチップへ移した。他の行動のコストの行も、既存の文を `·` でつなぎ、末尾に `${left} Maya left` を足す（`You will have … left` は使わなくなった）。
- 配備のカード・衛星の「次にすること」の案内（カードだけ / 衛星だけを選んだとき）は、トレイのシートからステータスバーのタイトルに移した（`Selected: ${card}. Choose a satellite to deploy to`、`Choose a card to deploy to ${sat}`）。ドラフトの回し方の説明（`Keep 1 of … cards and pass the rest to …`）もタイトルへ（`${you} must keep 1 card. …`）。トレイのラベルは何枚目か（`Pick ${pickNo} of ${pickTotal}`）。
- 計画（22）は、`PlayerArea.moveActionsTo` と `PlanningPlot` の移す処理を削除し、行 A をそのまま使う。確定後は、自分の行 A の選んだ札に `Planned`（`pe-planned` + `pe-plan-badge`）。自分の領域には裏向きのスロットを出さない（どの状態でも。他プレイヤーの領域は今のまま）。`ActionRow.setPlot` は、確定済みのまま `actionNo` が `null`（他の通知の描き直し）のとき、前の値を保つ。
- 効果の窓（PR 5 で作り替える）の旧クラスは、`pe-chip-btn` の選択を `pe-picked` に、`pe-choice-section` / `pe-choice-label` を `pe-effect-sec` / `pe-effect-label` に置き換えただけ。ステータスバーのタイトルには、トレイから外した hint の要点を足した（`… Your choices are final once you press the button`）。方向の選択肢のチップは、文字の矢印をやめて名前だけにした（PR 5 でボタンに）。
- `Env.selectionMode` は呼び出しが無いので削除し、`Env.showMessage` を足した（BGA では `bga.dialogs.showMessage(message, 'info')`。プレビューは `bga.dialogs.showMessage`）。`Tips.js` の `LEGACY_ACTIONABLE`・`LEGACY_PICKED` は削除した。

### 7. ツールチップ（作り直し）

`cards/Tooltip.js` を、**1 つの管理役（委譲）**に作り直す（仮に `ui/Tips.js`）。BGA 標準の `addTooltipHtml` は使わない（理由: 画面の部品を頻繁に描き直すため id の管理が要る・タッチの動きを確かめられない・背の高い内容が画面の外へ切れる。自前なら `tools/preview` で位置と画面内の収まりを自動で確かめられる）。見た目は BGA 標準のツールチップに合わせる。

- **指定**: 要素に `data-pe-tip="card:P003"`（カード）か、文字だけのツールチップ（要素 → 文の対応を管理役が持つ。`tips.setText(node, text)`）。描き直しても結び直しが要らないよう、`document` への委譲（`pointerover` / `pointerout` / `pointerdown` / `keydown`）で動かす。
- **出し方**: マウス = 350 ms のホバー（選択中の `pe-can` / `pe-picked` は 700 ms）。タッチ = 選べない要素はタップ、選べる要素（`pe-can`・`pe-picked`）は長押し 400 ms。加えてタッチ端末（`.touch-device`）では、選択中（`pe-picked`）のカードの左上に 32 × 32 px の情報の印（ⓘ を SVG で）を出し、押すとツールチップが開く。
- **位置と形**: 対象の要素の**すぐ横**（右 → 左 → 下 → 上の順で、入る側）。対象を覆わない。画面の端から 8 px 以内に入らない。上は BGA のステータスバー（`#page-title` の下端）より下。位置の計算は純粋関数（`placeTip(anchor, size, viewport)`）にして `tests/js` で検証する。**背の高いツールチップは、ホバーで出したものがスクロールできなくても読めるよう、必ず収める**（メインエージェントの決定、2026-11-27）。`placeTip` は位置と一緒に、使える最大の幅と高さを返し、管理役が次の順で形を選ぶ: (1) 画像と文を横に並べる（見える幅 480 px 以上）、(2) 収まらなければ幅を最大 640 px まで広げて文の行数を減らす、(3) それでも収まらなければ画像を縮める（最小の幅 96 px）。タッチで開いたときだけ、最後の手段として中のスクロールを許す。ツールチップの画像は、卓の上の同じカードより大きいこと（縦長 220 × 307、衛星カード 300 × 215。B.1「違いが十分に大きいこと」）。
- **消し方**: マウスが離れる、外側を押す、スクロール、Esc、状態の切り替え。ホバーで出したものは `pointer-events: none`（クリックを奪わない）。
- **見た目**: 白い面（`html[data-theme="dark"]` は `#22262f`）、1 px の枠、角丸 6 px、影、本文 13 px・名前 15 px 太字。幅は内容に合わせ最大 520 px（narrow は 画面幅 − 16 px）。`document.body` 直下、`position: fixed`、`z-index` は BGA のバーより上。
- **カードの中身**: 左に拡大したカード（縦長 220 × 307、衛星カードは上に 300 × 215。拡大用シートを使う）、右に 名前、アイコンつきの数値の行（コスト・名声・燃料庫）、種類、効果のタイミング・タイプ・稼働条件（アイコン + 語）、英語の効果文（翻訳可能）。narrow は画像 130 px + 右に文。衛星カードは、今の燃料・状態と、配備済みのカード名の一覧も出す。配備済みのカードは「Used this round」も出す。
- **文字だけのツールチップ**: パネルのカウンター、ラウンドトラックの段、山札・捨て札、衛星コマ（持ち主・名前・燃料・向き）、発射拠点、キューブ（重なりの持ち主）。
- **`title` 属性は全廃**（`aria-label` は残す）。

コードで確かめた事実:

- 今の `cards/Tooltip.js` は `bind(node, key, opts)` を、要素ごとに `pointerenter` などのリスナーを付ける形で提供し、呼び出しは **13 か所**（`CardPicker.js`、`HandView.js` ×2、`FieldView.js` ×3、`SatelliteView.js` ×4、`EffectWindowState.js` ×2、`DeployState.js` ×1）。各クラスは `tooltip` をコンストラクターの引数で受け取る（`FieldView`・`PlayerArea`・`HandView`・`ActionRow`・`SatelliteView`、`GameView.tooltip`）。この引数と `bind` の呼び出しを、`data-pe-tip` の指定に置き換える。
- 拡大用シート（`pe_cards_*` の拡大版、`src/img/zoom/`）と `cardSprite(key, 'zoom')`、`cardTextHtml`（右側の英語の文）、衛星カードの配備カード一覧（`deployedKeys`、タップで切り替える `pe-zoom-chips`）は今の実装にある。【確認】ゲーム領域の外に出す要素の位置は、CSS `zoom` の影響を避けるため `document.body` 直下に置いて `getBoundingClientRect()`（見た目の位置）で測る（モバイルの文書、012 §10 教訓 4）。
- 高さの上限は、今は `zoomAvailableHeight(window.innerHeight, barBottom)`（`ui/layout-math.js`）。`placeTip` に統合する。

`title` 属性のソース上の箇所（39 か所。【実測】）:

| ファイル | 箇所 | 置き換え |
| --- | --- | --- |
| `ui/Layout.js` | 拡縮ボタン（`renderTools`） | `aria-label` のみ（ボタンに名前がある） |
| `ui/BottomSheet.js`、`board/Highlights.js` | 方向の矢印・方向パッド | `aria-label` のみ（既に併記） |
| `ui/CardPicker.js`、`cards/HandView.js`（2 か所） | 選べない理由、横向きのアクションカード | §6.3 のメッセージ + ツールチップ（アクションカードは `data-pe-tip`） |
| `ui/PhaseBand.js` | フェイズの名前 | `ui/RoundTrack.js` のツールチップ |
| `ui/PlayerPanel.js`（3 か所） | 基準プレイヤー、確定、待機 | 基準プレイヤーのみ残してツールチップ（確定・待機は廃止） |
| `ui/PlayerStats.js`（`counter`、`regionRow`） | パネルのカウンター、地域 | ツールチップ（地域は「n/8」の 1 つにまとまる） |
| `ui/ScoreSheet.js` | ミッションの枠の切り替え | ツールチップ |
| `board/BoardView.js`（2 か所） | 跳ね返りの帯 | 帯を細い線にする（§1.1）。説明はツールチップか削除 |
| `board/PieceLayer.js`（3 か所） | 発射拠点、重なったキューブ、衛星コマの燃料 | ツールチップ（衛星コマは持ち主・名前・燃料・向き） |
| `cards/FieldView.js`（5 か所） | 山札・捨て札・褒賞のグループ | ツールチップ（`aria-label` は残す） |
| `cards/SatelliteView.js`（7 か所） | 歯車、種別アイコン、チップ、`+n`、`×`、燃料切れ | 簡略表示の廃止（§4.1）で大半が消える。詳細表示に残る歯車・`×` はツールチップ |
| `cards/text-symbols.js`（HTML の文字列の `title`） | 効果文の中の記号 | `aria-label` のみ（ツールチップの中の文字） |
| `states/ActionState.js`（1 か所） | 起動の修正の名前 | ツールチップ |
| `states/EffectWindowState.js`（5 か所） | 必須の印、順番のボタン 3 つ、選択肢のボタン | §5.1 で置き換え |
| `states/HandTargets.js`（2 か所） | 取り外す [×]、[Choose from hand] の無効の理由 | [×] は廃止（トグル）。無効の理由は欄の行の文字（§5.1） |

`addActionButton` に渡す `title` / `tooltip` の引数は DOM の `title` 属性ではない（`DeployState.js` の [Reset]、`pePanelButton` の `tooltip`）。BGA 標準のボタンの引数なので、全廃の対象外（バーのボタンは `#pe-root` の外）。ただし `destination` で `#pe-root` の中（トレイ・手札のレール）に置くボタンは、自動検査（§10 の 4）の対象に入る点に注意する（`title` を付けない）。

| ファイル | 変更 |
| --- | --- |
| `src/modules/js/ui/Tips.js`（新規） | 管理役 |
| `src/modules/js/ui/tip-placement.js`（新規） | `placeTip(anchor, size, viewport)`（純粋関数） |
| `src/modules/js/cards/Tooltip.js` | 削除。`card-text.js`・`sprite-layout.js` の部品は再利用 |
| `src/modules/js/ui/layout-math.js` | `zoomWidth`・`zoomAvailableHeight` を削除（`placeTip` に統合） |
| `src/modules/js/ui/GameView.js` ほか上の 13 か所のクラス | `tooltip` の引数をやめ、`data-pe-tip` を付ける |
| `src/projectedgeworth.css` | `.pe-zoom*` を `.pe-tip*` に |
| `tests/js/tip-placement.test.mjs`（新規） | `placeTip`（右・左・下・上、端 8 px、バーの下、収まらないとき） |

実装で決めた細部（PR 3。2026-11-28）:

- 管理役は `ui/Tips.js`。要素 → 内容の対応は `WeakMap` で、要素には `data-pe-tip`（`card:<キー>` / `text` / `custom`）だけを付ける。部品側は `tips.card(node, key, extra?)`・`tips.text(node, text)`・`tips.custom(node, build)`。管理役の実体を持たない部品（パネル・ラウンドトラック・得点表・コマの層・状態クラス）は、同じ内容のモジュール関数 `tipCard` / `tipText` / `tipCustom` を使う。`card` の第 3 引数 `extra` は、本文の下に足す内容を出すときに作る関数（衛星カードの今の燃料・状態・配備済みのカード名、使用済み、選べない理由）。文は関数でもよい（出すときに作る）。
- 形は、画像と文を横に並べる（幅 520）→ 幅 640 → 画像を 220（衛星カードは 300）から 96 まで縮める、の順で最初に収まったもの。衛星カードも横並び（§7 の「衛星カードは上に」は、(1) の「画像と文を横に並べる」を優先した）。narrow（見える幅 480 px 未満）は幅 = 画面 − 16、画像 130 → 110 → 96。どれでも収まらないときだけ、置く側の最大の高さに切り、タッチだけ `overflow-y: auto`。
- 「選べる / 選択中」の判定は、`Tips.js` の定数（`LEGACY_ACTIONABLE`・`LEGACY_PICKED`）に旧クラス、`pe-can`・`pe-can-sell`・`pe-picked` を並べた 1 か所の一覧。PR 4 で旧クラスを消したら 2 つの配列を空にするだけ。判定は対象の祖先のクラスも見る（`pe-sat-selectable` はロケット全体に付くため、積んだ配備カードも「選べる」扱いで長押し）。
- タッチの長押しで開いたあとの `click`（カードの選択）は、1 回だけ止める。選べない要素のタップは、開く代わりに閉じる動き（同じ対象を開いたまま押したとき）を含む。右クリックの動きは廃止。
- 情報の印は、`MutationObserver`（子の増減と `class`）で、タッチ端末（`#ebd-body.touch-device`）のとき選択中のカード（衛星は選択中のロケットの土台のカード）の子として付け外しする（描き直しで消えても付け直す）。印の `click` は伝播を止めるので、カードの選択は変わらない。
- キューブ・発射拠点・「×n」の札はコマの層（`pointer-events: none`）の中にあるため、ツールチップを出すこの 3 つだけ `pointer-events: auto` にした。区域のタップは、これらの上でも通るよう `BoardView` の判定を `.pe-pbox-cube` / `.pe-pbox-base` まで広げ、コマの層でも受ける。キューブのツールチップは重なりの持ち主の一覧（上から）。
- ラウンドトラックの計画・循環の段（文字だけ）は、段の名前（`Planning Phase` / `Orbit Phase`）。I〜V の段は A001〜A005 のカード。
- `title:` のうち DOM の `title` 属性だったものはすべて置き換えた（プレビューの全見本で `#pe-root` と `.player-board` の `[title]` は 0 件）。`title:` の文字列がコードに残るのは、BGA 標準のボタンの引数（`addActionButton`）と、ステータスバーのタイトル・トレイ / ボトムシート / 選択パネルの見出しの引数名（DOM の属性ではない。PR 4 のトレイ移行で置き換わる）。方向の矢印・方向パッド・順番のボタン・[×] は `aria-label` のみ。
- 撮影の道具（`npm run preview:shots -- --tips`、既定の撮影にも含む）は、撮るたびに `TIP <名前>: OK|FAIL` を出す（見える領域の中、対象と重ならない、中身が切れていない）。`check:ui` の 7 番（PR 6）はこの `checkTip` を再利用する。

### 8. プレイヤーパネル

- カウンター: マヤ、予算、手札の枚数、キューブ、通信確立した地域、褒賞の比較値 2 つ（+ 名声。ゲームオプション 105 のとき）。**どれも値は 1 つの数にする**（幅を詰める）。キューブは「残りの数」（`cubesLeft`。紙の手元のキューブと同じ。ツールチップ `Cubes left: ${n} of 16`）、地域は通信確立した数（ツールチップ `Linked regions: ${n} of 8 (${letters})`）。アイコンはスプライト、数値 14 px 太字。（現状の「置いたキューブ n/16」と、指示書の「地域 n/8」を、この形に置き換えた。メインエージェントの決定、2026-11-27）
- 印: 基準プレイヤーの「1」だけ（四角い札）。✓ と回転は廃止（§4.4）。
- **移動**: 各パネルに、そのプレイヤーの領域へ移る小さなボタン（32 × 32 px、下向きの矢印の SVG、`aria-label`「Go to ${player_name}'s area」）。**カウンターと同じ行の右端に置く**（別の行にしない）。押すと `#pe-player-<id>` へ `scrollIntoView`。bga-jump-to（左の浮いたボタン）は廃止（A.3「パネルから主な部品への移動を置いてよい」）。
- スマホ（390 px）で 1 行（名声のオプションがオンのときだけ 2 行まで）。4 人分で画面の 1/4 程度。

コードで確かめた事実:

- 今のパネル（`ui/PlayerPanel.js`、`ui/PlayerStats.js` の `buildStats`）は、マヤ・予算（アイコン + ディスク）・手札の枚数・置いたキューブ `n/16`・褒賞の比較値 2 つ・名声（ゲームオプション 105 のとき）のカウンターと、基準プレイヤーの「1」・確定の `✓`・待機の回転を 1 行（`pe-panel-row`）に並べる。
- 移動先の `#pe-player-<id>` は今の `PlayerArea.root` に既にある（`id: pe-player-${player.id}`）。観戦者は全員の領域があり、自分の領域は無い（自分のパネルのボタンは自分の領域へ）。
- パネルは `bga.playerPanels.getElement(playerId)` の中に足す（`Env.playerPanel`）。BGA は、モバイルでパネルをゲームの上へ移す。
- 1 行に収まるか: カウンターを 1 つの数にし（上）、移動のボタンを同じ行の右端に置いても、幅 390 px で収まるかは実機で確かめる。**Studio で確認すること**（作者の作業に追加。[tasks.md](../tasks.md)）: 幅 390 px の実機で、BGA のパネルが 1 列か 2 列か、4 人のときパネル全体が画面の高さの何割か。1/4 を大きく超えるなら、`game_interface_width.min` を 490 以上にして BGA の 2 列のパネルにする案（ゲーム領域は BGA が縮める）を検討する。【未確認】のため本書では決めない。

| ファイル | 変更 |
| --- | --- |
| `src/modules/js/ui/PlayerPanel.js` | 移動のボタンを足す。印を基準プレイヤーだけに。`aria-label` |
| `src/modules/js/ui/PlayerStats.js` | キューブを残りの数、地域を通信確立した数の 1 つの数にし（§4.4・上）、`title` をツールチップに。席順の印（設定 100）。`title` をツールチップに |
| `src/modules/js/ui/GameView.js` | 移動のボタンの `scrollIntoView`（`areas.get(id).root`） |
| `src/modules/js/Game.js` | `setupJumpTo`・`JUMP_TO_FOLDED_KEY` を削除 |

### 9. 廃止するもの

bga-jump-to、フェイズ帯の丸い札、盤面の上の選択パネル、貼り付くボトムシート（`position: sticky`）、捨て札のモーダル、盤面に重なる拡縮ボタン、`.pe-btn` 系の独自ボタン、白い面と `--pe-accent`、簡略表示（チップ）と設定 101、見出しの地域の列、パネルの ✓ と回転、`title` 属性、点滅のアニメーション、`PlayerArea.moveActionsTo`。

| 廃止するもの | コードの場所 | 置き換え |
| --- | --- | --- |
| bga-jump-to | `Game.js` の `setupJumpTo`、`JUMP_TO_FOLDED_KEY`（端末に残る `projectedgeworth-jumpto-folded` は害が無い） | パネルのボタン（§8） |
| フェイズ帯 | `ui/PhaseBand.js`、`GameView` の `bandBox`・`band`・`refreshBand`、CSS `.pe-phase*`・`.pe-end-note` | ラウンドトラック（§3） |
| 選択パネル | `ui/ChoicePanel.js`、`GameView` の `panelBox`・`panel`、CSS `.pe-choice*` | トレイ（§5） |
| ボトムシート | `ui/BottomSheet.js`、`GameView` の `sheetBox`・`sheet`、CSS `.pe-sheet*`（`position: sticky`） | トレイ（§5） |
| 捨て札のモーダル | `FieldView.openDiscard` / `closeDiscard`、CSS `.pe-modal*`（`position: fixed`） | トレイの一覧（§4.3） |
| 盤面に重なる拡縮ボタン | `Layout.renderTools`、CSS `.pe-board-tools`（`position: absolute`） | 流れの中の拡縮の行（§2.5） |
| `.pe-btn` 系 | CSS `.pe-btn`・`.pe-btn-small`・`[aria-pressed]`、`FieldView`・`EffectWindowState`・`HandTargets` | BGA 標準のボタン（§1.2・§5.1） |
| 白い面と `--pe-accent` | CSS のトークン（§1.2） | 新しいトークン |
| 簡略表示と設定 101 | §4.1 の表 | ロケット |
| 見出しの地域の列 | `PlayerArea.regionsBox`・`renderRegions`、`PlayerStats.regionRow`、`notifications.linkEstablished` | パネルの `n/8`（§4.4） |
| パネルの ✓ と回転 | `PlayerPanel`、`HandView.ActionRow`、CSS `.pe-check`・`.pe-waiting` | BGA 標準のアクティブ表示（§4.4） |
| `title` 属性 | §7 の表 | ツールチップ |
| 点滅 | CSS `.pe-hl-selectable` の `animation: pe-pulse`、`@keyframes pe-pulse` | 輪郭と光（§6.1）。`pe-bounce`（衛星の跳ね返り 0.2 秒）は残す |
| `PlayerArea.moveActionsTo` | `ui/PlayerArea.js`、`PlacementStates.js` の `peRestoreActions`・`peShowActionsPanel`・`clearUi`・`onLeavingState`・`refresh` の上書き | 行 A で直接選ぶ（§2.3） |

### 10. 確認の道具

- **プレビューを BGA の枠に近づける**（`tools/preview`）: 上部バー、ステータスバー（`#page-title`。BGA と同じく上に貼り付く）、右の列（`#right-side`。幅 240 px のパネルとログ）、フッター。`#ebd-body` に `desktop_version` / `mobile_version` と `touch-device` / `notouch-device`。`bga.statusBar.addActionButton` の代用品は BGA のボタンのクラス（`bgabutton bgabutton_blue` など）と `destination`・`disabled`・`tooltip` を再現。`bga.dialogs.showMessage`。
- **スクリーンショット**（`npm run preview:shots`）: Chrome を DevTools Protocol で動かす Node スクリプト（依存の追加なし。`tools/preview/shoot.mjs`）。幅 360 / 390 / 768 / 1024 / 1366 / 1440 / 1920 / 2560 × 主な状態。出力は `build/preview-shots/`（Git 管理外）。ホバー・タップ・スクロールを再現できる。
- **UI の自動検査**（`npm run check:ui`。同じ道具）: (1) ページの横のはみ出しが無い、(2) `#pe-root` の中に `position: fixed` / `sticky` が無い、(3) 押せる要素が 32 × 32 px 以上、(4) `title` 属性が無い、(5) 文字が 12 px 以上、(6) 実際にスクロールする入れ子の要素は narrow の盤面だけ、(7) ツールチップが画面内にあり対象を覆わない。Chrome が無い環境では skip（CI には入れない。手元と PR の確認用）。

コードで確かめた事実と補足:

- 今のプレビュー（`tools/preview/index.html`・`main.js`）は、簡易な 2 列の枠（`#pv-layout`）、`#page-title` の代用、`?state=<見本名>` のときだけ `bga.statusBar.addActionButton`（色の見分けは `style` で直接指定。`bgabutton_*` のクラス名は付けている）と `bga.actions.performAction` の代用を持つ。`bga.dialogs`・`bga.gameArea` の代用は無い。`#ebd-body` と `touch-device` / `notouch-device`、フッター、右の列の `#right-side` も無い。静的サーバー（`npx http-server . -p 8765`）は手で起動する。
- 見本は `tools/preview/samples/`（盤面 2 つ: `three-players-advanced.json`・`two-players-standard-6col.json`）と `samples/states/`（状態 48 本。`make-state-samples.mjs` が生成）。`?theme=dark`・`?layout=narrow`・`?spectator=1`・`?fame=1`・`?demo=` が使える。
- Chrome のスクリプトは、Node 22 以降に組み込みの `WebSocket` と `node:http`（静的サーバー）で書ける（`package.json` の `engines` は Node 22.13 以降）。【確認】Chrome の `Input.dispatchMouseEvent` / `dispatchTouchEvent` によるホバー・タップは、メインエージェントが 2026-11-27 に試作の撮影で動くことを確認した。`build/preview-shots/` は `.gitignore` の `/build/*` に既に含まれる。
- 自動検査の (4) は `#pe-root` の中の DOM の `title` 属性を数える。ただし、`destination` で `#pe-root` の中に置く BGA 標準のボタンの `title`（◀ ▶・[−][+]・取り消しの × のアクセシビリティ用。§5.1 の `Move earlier` など）は、BGA の `addActionButton` の `title` 引数であり、ここでは許す（`title` 属性の全廃は、ツールチップの代わりに付けていたものが対象）。(2) の対象は `#pe-root` の中だけで、ツールチップ（`document.body` 直下の `position: fixed`）と BGA の要素は対象外。(3) は概観の段の区域を対象外にする（§2.5）。(6) は得点表の横スクロールも対象に含める（§2.2）。
- 新しい npm スクリプト（`preview:shots`、`check:ui`）は `package.json` に足す。`npm run lint` には入れない。

### 11. 実装の分け方（1 PR ずつ順に。CSS が 1 ファイルのため並行しない）

| PR | 範囲 |
| --- | --- |
| 1 土台 | プレビューの BGA 風の枠とスクリーンショットの道具（§10）、トークンと背景（§1）、DOM と 3 レイアウト（§2.1・§2.2）、ラウンドトラック（§3）、場（§4.3。捨て札の一覧はトレイ）、盤面の地・拡縮の行、bga-jump-to の廃止（最終ラウンドのバナーと `getAllDatas` の真偽値は取り消し。§3）、CSS 変数名 `--pe-actionOther-w` の不具合の修正 |
| 2 プレイヤーの領域 | ロケットを唯一の表示に（§4.1）、行 A・B・C（§2.3）、他プレイヤー（§2.4）、キャプション、パネル（§8）、重複の整理（§4.4）、設定 101 の廃止 |
| 3 ツールチップ | §7 全部（`title` の全廃を含む） |
| 4 操作 | 共通のクラス（§6.1）、盤面を暗くする（§6.2）、押したときの理由（§6.3）、トレイ（§5。ドラフト・企画・コストの行・方向パッド）、マリガンを手札で、計画を行 A で、欄の共通の形・選択肢のボタン・[×] の廃止（§5.1 のうち効果の窓以外の欄: `HandTargets`） |
| 5 効果の窓 | §5.1 全部（欄の共通の形、選択肢のボタン、`ORDER` の ◀ ▶、`ASSIGNMENT` の部品だけの操作、`USE_MODS` のトグル、タイトルの `${todo}`）|
| 6 仕上げ | narrow の調整、設定 100 の印（§3）、得点表の幅（§2.2）、UI の自動検査（§10）、012・008 などへの注記の最終確認、作者の作業（Studio の再読み込み）の整理 |

各 PR で `tools/preview` の見本とスクリーンショットを更新し、`npm run lint`・`npm run test:js` を通す。サーバー（PHP）は変えない（`gamepreferences.jsonc` の 101 の削除だけ）。

各 PR の変更するファイルと、更新するプレビューの見本:

| PR | 変更するファイル | 更新するプレビューの見本 |
| --- | --- | --- |
| 1 | `src/projectedgeworth.css`、`src/modules/js/ui/GameView.js`・`Layout.js`・`layout-math.js`・`round-track.js`（新規）・`RoundTrack.js`（新規）・`PhaseBand.js`（削除）・`Tray.js`（捨て札の一覧に必要な最小限。新規）、`cards/FieldView.js`・`CardView.js`（`Env`）、`board/BoardView.js`（地と跳ね返りの線）、`notifications.js`（`refreshRound`。N31 / N32 はラウンドトラックを `Game end` に）、`Game.js`（`setupJumpTo` の削除）、`tools/preview/index.html`・`main.js`・`shoot.mjs`（新規）、`package.json`、`tests/js/geometry.test.mjs`・`round-track.test.mjs`（新規）、`ui/Layout.js`（`--pe-actionother-w` の修正） | 盤面 2 つ（`three-players-advanced`・`two-players-standard-6col`）、状態の見本のうち `launchpad`・`planning`・`planning-confirmed`・`deploy-main`・`final-score`。確認の幅 8 つ × ライト・ダーク |
| 2 | `cards/SatelliteView.js`・`HandView.js`・`FieldView.js`（`renderMissions`）、`ui/PlayerArea.js`・`PlayerPanel.js`・`PlayerStats.js`・`StateFrame.js`・`GameView.js`、`notifications.js`（`linkEstablished`）、`Game.js`（設定 101）、`src/gamepreferences.jsonc`、`src/projectedgeworth.css`、`tools/preview/main.js`（デモ `detail` の削除）、`tests/js/`（レイアウトの値） | 盤面 2 つ、`?spectator=1`、`?fame=1`、`?layout=narrow`、`planning`・`planning-confirmed`・`deploy-bonus-stage`・`effects-subject-satellite` |
| 3 | `ui/Tips.js`・`tip-placement.js`（新規）、`cards/Tooltip.js`（削除）、`ui/layout-math.js`、上の `title` の 39 か所と `bind` の 13 か所、`tools/preview/shoot.mjs`（ホバー・タップ）、`tests/js/tip-placement.test.mjs`（新規） | `planning`、`deploy-main`、`effects-fields`、`launchpad`（ツールチップの出た状態）。タッチ（`touch-device`）・マウス |
| 4 | `ui/Tray.js`、`ui/ChoicePanel.js`・`BottomSheet.js`（削除）、`board/Highlights.js`・`BoardView.js`、`cards/HandView.js`・`SatelliteView.js`、`ui/CardPicker.js`・`StateFrame.js`、`states/HandTargets.js`・`states/PlacementStates.js`・`ActionState.js`・`DraftState.js`・`ProposalState.js`・`DeployState.js`・`LaunchState.js`・`LinkState.js`・`MulliganState.js`（確認）、`Game.js`・`CardView.js`（`showMessage`）、`src/projectedgeworth.css` | `planning`・`mulligan`・`draft`・`draft-confirmed`・`proposal`・`proposal-plain`・`link-main`・`link-main-sell`・`launch-main`・`launch-board-turn-direction`・`deploy-main`・`deploy-bonus-stage`・`sell-pick`・`sell-all` |
| 5 | `states/EffectWindowState.js`・`states/logic/effects.js`（`moveEntry` の `top` を除く）・`ActionState.js`（`pePanelButton` の削除）、`cards/SatelliteView.js`（`setSubjectCard`）、`ui/Tray.js`、`src/projectedgeworth.css`、`tests/js/state-logic.test.mjs`（必要なら） | `effects-use`・`effects-fields`・`effects-order`・`effects-direct-hand`・`effects-direct-satellite`・`effects-juggler`・`effects-subject-satellite`・`effects-waiting` |
| 6 | 全体の調整（`src/projectedgeworth.css`）、設定 100 の印（`board/PieceLayer.js`・`ui/PlayerArea.js`・`PlayerPanel.js`・`RoundTrack.js`・`Game.js`）、`ui/ScoreSheet.js`（幅）、`tools/preview/check-ui.mjs`（新規）、`package.json`、`docs/specs/012-ui-assets.md`・`008-analog-operations.md` の注記の最終確認、`docs/tasks.md` | 全部の見本（`preview:shots` の一括）。`check:ui` の 7 項目 |

### 12. ユーザー向け文言（英語の原文）

UI 文言は英語を原文とし、`_()` で包む（012 §8.1）。既存の文字列（コードの `_('…')`）で使えるものはそのまま使い、新しく決めるものを太字で示す。

| 場所 | 文言 | 備考 |
| --- | --- | --- |
| ラウンドトラック | `Round` + 数字（例 `Round 3`。表示は大文字）、`Setup`、`Planning`、`Budget`・`Deploy`・`Launch/Turn`・`Link`・`Proposal`（既存。ローマ数字は `I`〜`V`）、`Orbit`、`Game end` | 既存。指示書の図は `Plan` だが既存は `Planning`（`PhaseBand.js`）。既存に合わせた |
| ラウンドトラックの四角のツールチップ | `${player_name}` | プレイヤー名のみ（そのアクションの保持者）。設定 100 がオンのとき、四角に席順の数字 |
| 最終ラウンドの文 | `The game ends after this round` | 既存（`Notices.php` の通知の文。ログにだけ出る。バナーは出さない） |
| ゾーンのラベル | `Rewards`（既存）、**`Deck`**、`Discard`（既存）、**`Action cards`**、`Missions`（既存）、`Hand`（既存） | 12 px・大文字（CSS）。既存の `Actions`（`aria-label`）と別に、ラベル用の文言を足す |
| ロケットのキャプション | `Hammer (Moon)` 形（`satelliteName`）、燃料 `${remaining}/${capacity}`、`Standby`（既存）、`Out of fuel`（既存）、**`Active, heading ${direction}`**（`aria-label`） | `${direction}` の語は §4.1 の注。燃料のアイコンの `aria-label` は既存の `Fuel ${remaining} / ${capacity}` |
| カードのキャプション | カード名（`card-info.js` の英語名）、アクションカードは `I Budget` 形 | 既存の名前 |
| パネルのカウンター | `Maya`・`Budget`・`Cards in hand`・`Cubes placed`（既存）、`Cubes placed`（既存。ツールチップは上の `Cubes left` に置き換える）、`Linked regions`（既存の `aria-label` と同じ語。ツールチップは上の `Linked regions: …`）、`Region ${letter}: linked` / `not linked`（既存） | 褒賞の比較値のツールチップは既存（`${name}: ${compare}`） |
| パネルのボタン | **`Go to ${player_name}'s area`**（`aria-label`） | |
| 欄の共通の形のボタン | `Choose from hand`（既存）、**`Choose`** | アクティブでない欄の行に置く |
| 数値・順番・付け替えのボタン | **`Decrease`**、**`Increase`**、**`Move earlier`**、**`Move later`**、**`Remove`**（いずれも `title` / `aria-label`。ラベルは SVG） | BGA 標準の `secondary` ボタン |
| 効果の窓のタイトル（入力が足りないとき） | **`${you} may use ${card_name}: ${todo}`**、**`${you} must resolve ${card_name}: ${todo}`** | `${todo}` は既存の `needText` の中身（小文字始まり。例 `choose a card to deploy and a satellite`） |
| パネルのカウンターのツールチップ | **`Cubes left: ${n} of 16`**、**`Linked regions: ${n} of 8 (${letters})`** | キューブは残りの数、地域は通信確立した数 |
| 基準プレイヤーの印 | `Base player (first in turn order)` | 既存 |
| 選べないときの文 | `This satellite already has 4 cards`（既存）、**`You cannot reach this area`**、**`You cannot choose this now`**（一般） | `showMessage(…, 'info')`（2 秒以内の同じ文は出さない）。サーバーの `blocked` の符号を `texts.js` で文にしたものがあればそれを使う |
| 効果の窓のタイトル | **`${you} may use ${card_name}`**、**`${you} must resolve ${card_name}`**、`${you} must choose the order of your card effects`（既存）。入力が足りないときの `${todo}` つきは下の行 | `setTitle(title, args)` に `card_name` を渡す |
| 効果の窓のボタン | `Use`・`Skip`・`Confirm order`（既存） | `Skip` の `tooltip` は既存の `The effect is not used and cannot be used for this trigger later` |
| トレイのコストの行 | **`Link cost ${total} Maya (Area ${area} + Pioneer ${pioneer} to ${name}) · ${left} Maya left`** | 既存の `Link cost: Area ${area} + Pioneer ${pioneer}${mods} = ${total} Maya` と `You will have ${left} Maya left` を 1 行にまとめた形（指示書の例）。他の行動（配備・打ち上げ）の同様の行は既存の文を `·` でつなぐ |
| 捨て札 | `Discard pile`（既存）、`Close`（既存）、`Empty`（既存） | 一覧の見出しは `Discard pile (${n})` |
| 拡縮ボタン | `Zoom out`・`Default zoom`・`Zoom in`（既存の `aria-label`） | |
| 手札 0 枚 | `No cards in hand` | 既存 |
| ツールチップの定型文 | `Used this round`・`Active satellites only`・`Activated effect`・`Used last round. Returns to your hand next round.`（既存） | カードの本文は `cardTextHtml`（英語。002） |

### 13. 指示書で決まっていなかった点と決定（メインエージェント、2026-11-27）

指示書に無かった点を、コードを読んだレビューで決めた。決定は各節の本文に反映してある（ここは一覧）。

| 節 | 点 | 決定 |
| --- | --- | --- |
| §1.2 | 回るコマ 3 か所の `filter: drop-shadow` と「使わない」の整合 | コマ（3 か所）だけ `drop-shadow` を残し、`.dj_safari` では `filter: none`。それ以外では使わない |
| §1.2・§5.1 | `.pe-btn`・記号の文字を使う [Choose from hand]・[×]・順番の `⤒ ▲ ▼`・`pe-chip-btn`・数値入力・`<select>` の置き換え | 「欄」の共通の形（ラベル + 今の値の 1 行、アクティブな欄は `pe-picked`、アクティブでない欄に `Choose from hand` / `Choose`）。[×] は廃止（トグル）。選択肢は `secondary` + `pe-picked` + `aria-pressed`（青にしない。指示書の「選んだものは `primary`」を取り消し）。数値は 8 通り以下ならボタン、多ければ [−] 値 [+]。`ORDER` は小さなカードの横並び + 各カードの下に [◀][▶]、「先頭へ」は廃止、1 組ずつ表示。`ASSIGNMENT` は `<select>` を廃止して部品だけ + 移動の行と取り消し。`USE_MODS` は `secondary` のトグル。捨て札の [Close] は `secondary`。§5.1 |
| §2.2 | 得点表の入れ子スクロール | narrow でも横スクロールなしで収める（`table-layout: fixed`、ラベル列 34 %、名前は省略記号 + ツールチップ）。`.pe-score-scroll` を廃止。中身は変えない |
| §3 | 設定 100（席順の数字）の実装が無い | 今回実装する（PR 6）。コマ・ラウンドトラック・レールの名前の前・パネルの移動のボタンの横に 1〜4 の印。既定はオフ。SVG / CSS、`cssPref` で切り替え |
| §3 | 最終ラウンドの表示が再読み込みで消える | バナーは出さず、`getAllDatas` も変えない（2026-11-28 に取り消し。N31 は終了判定の時点にだけ出て、ラウンドの途中で「最後」と知らせる時点が無い。再読み込みは `phase = END` で復元）。サーバーの変更は設定 101 の削除だけ |
| §4.4 | [Change my choice] で BGA 上のアクティブに戻らない | そのまま（サーバーは変えない）。選び直し中も非アクティブ（= 有効な確定がある）で、他のプレイヤーから見て正しい。本人は [Change my choice] と「Planned」で分かる |
| §5.1 | [Use] が押せない理由の置き場 | ステータスバーのタイトルに `${todo}` として出す。トレイにも `tooltip` にも出さない |
| §6.3 | `showMessage` の `error` がログに載る | `info` にする。同じ文は 2 秒以内に繰り返さない |
| §7 | ホバーのツールチップがスクロールできず、背の高い内容が切れる | 必ず収める: 横並び（480 px 以上）→ 幅を 640 px まで広げる → 画像を縮める（最小 96 px）。タッチだけ最後の手段としてスクロール。画像は卓の同じカードより大きく |
| §8 | パネルが 1 行に収まるか | カウンターを 1 つの数にする（キューブは残りの数、地域は通信確立した数）。移動のボタンは同じ行の右端。Studio の実機で確認（作者の作業）。1/4 を大きく超えるなら `game_interface_width.min` を 490 以上にする案を検討（未確認のため決めない） |
| §10 | ホバー・タッチの自動再現 | Chrome の `Input.dispatch*` で動くことを確認済み |
| §2.5 | 概観の段が 32 px 未満になる | 概観は見るだけの段。`HEX_W_MIN` を当てない。盤面をタップすると、その位置を中心に標準へ切り替わる。自動検査 3 は概観の区域を対象外 |
| §2.3・§2.2・§4.1・§5・§6 | 試作で決めた点 | 行 A はグループを隣り合わせ（間 32 px）にして中央寄せ。ロケットの選択の見た目は土台の衛星カードに付ける。選べるロケットは右上にコストの札（硬貨 + 数）、選べないロケットは暗くするだけ。コストの行は 1 行。場のカードの高さ（wide）は `min(117, ⌊(盤面の高さ − 104) ÷ 4⌋)`。narrow の他プレイヤーのキャプションは燃料と状態のアイコンだけ。narrow の自分のアクションカードは 1 行（(W − 24) ÷ 5、上限 64）。キャプションは 2 行まで |
| §0・§2.2 | `--pe-actionOther-w` と `--pe-actionother-w` の変数名の不整合（既存の不具合） | PR 1 で直す |

## エッジケース

- **観戦者**: `#pe-me` が無く、全員を `#pe-others` に席順で、他プレイヤーと同じ小さい形で並べる（§2.4）。手札・ミッションの表・プレビュー・[Sell cards]・計画の行は出さない。パネルの移動のボタンは全員分。ラウンドトラック・場・盤面・ツールチップは同じ。観戦者は `spectatorMode`（012 §11）で判定する。
- **設定 100（席順の印）**: オンのとき、コマ・ラウンドトラックの四角・レールの名前の前・パネルの移動のボタンの横に席順の数字（1〜4）が出る。既定はオフ。観戦者でも全員分出る。
- **手札 0 枚**: 手札のレールは出し、本体は `No cards in hand`（既存の文）。[Sell cards] は出さない。マリガン・ドラフトの取り直しで 0 になる状態は無い。
- **衛星に配備カードが 4 枚**: 4 枚積んだロケットが 1 人分の行で一番背が高くなる。下端をそろえるので、他の衛星の上は空く。配備の選択中は `pe-off` で暗くし、押すと `This satellite already has 4 cards`（§6.3）。空き枠の「+n」の印は無い（§4.4）。
- **9 列・6 列の盤面**: 盤面の幅は `(列数 + 0.5) × 区域の幅`。区域の幅の上限（wide は 112）で盤面は 6 列でも約 730 px。盤面と場（wide）を 1 つの塊として中央に寄せる。高さの上限の式は列数によらない（盤面の高さは同じ）。左右の接続とゴーストは 012 のまま。
- **概観の段（narrow）**: 区域が 32 px 未満になり、区域・コマは押せない（見るだけ）。盤面をタップすると、その位置を中心に標準へ切り替わる（§2.5）。
- **2〜4 人**: 他プレイヤーは 1〜3 人。wide で W ≥ 1180 かつ他が 2 人以上のときだけ 2 列（§2.2）。パネルの 1 行は人数分（スマホ 4 人で画面の 1/4 を目指す。§8）。
- **名声の公開オプション（ゲームオプション 105）**: パネルのカウンターに名声が増える（§8）。ツールチップは既存の内訳（ミッションを除く。`fameTitle`）。
- **リプレイ・アニメーション無効**（`bgaAnimationsActive()` が偽）: 盤面・ラウンドトラック・トレイは最終状態をそのまま描く。ツールチップの表示・消去の遅延はリプレイでも動く（アニメーションではない）。持ち上がる動き（`pe-can` のホバー）は CSS の遷移のみ。
- **再読み込み**: 画面は `getAllDatas` と、今の状態の `getArgs` から作り直す。ラウンドトラックは `round`・`phase`・`bonusHolders` から復元する（012 エッジケースと同じ）。トレイは、選択中の状態（args がある）のときだけ再び開き、捨て札の一覧・ツールチップは閉じた状態から。ゲームの終了（`phase = END`）のラウンドトラックは `getAllDatas` の `phase` から復元される（バナーは出さない。§3）。
- **ダークテーマ**: 卓はテーマによらず同じ暗い背景（Q1）。ツールチップだけ白 / `#22262f` で切り替える。プレイヤー色の文字は暗い卓の上用の明るい版（`--pe-c-*-ink`）で、コントラスト比は §1.2 の表のとおり。BGA 側のパネルとバーはテーマに従う。
- **タッチ端末**: ホバー専用の見た目は `.notouch-device` で前置する。ツールチップは、選べない要素はタップ、選べる要素は長押し 400 ms、選択中のカードの左上に 32 × 32 px の情報の印（§7）。押せる要素は 32 × 32 px 以上（E.3）。区域のタップは Pointer Events で、移動が 8 px を超えたらスクロールとみなす（012 §10）。
- **ブラウザの拡大縮小**: 拡大しても、`#pe-root` の幅が変わるので `ResizeObserver`（`Layout`）が `data-layout` と寸法を更新する。固定幅にしない（C.4）。ツールチップは `placeTip` が毎回、見える領域から位置と高さを決める。
- **`prefers-reduced-motion`**: 点滅は廃止するので影響は無い。残るアニメーション（衛星の移動、跳ね返りの 0.2 秒、ホバーの持ち上がり）は、現状どおり `prefers-reduced-motion: reduce` で止める（`@media` の規則は CSS に既にある）。
- **スプライトの読み込み失敗**: カードの背面にカード名の文字（`pe-card::before` の `content: attr(data-name)`）が出る（012 エッジケース）。キャプションと名前の表示は DOM の文字なので影響を受けない。
- **効果の窓の問いの数**:
  - 0 件（自分に問いが無い）: トレイは出さず、ステータスバーは既存の `Waiting for …`（`waitingText`）。
  - 1 件: 待ち列は出さず（1 枚だけ）、その問いをそのまま表示する。
  - 複数（`EFFECT`）: 待ち列に発生源のカードを並べ、注目中を選択中（`pe-picked`）にする。別のカードで切り替え。入力値は問いごとに保たれる。
  - `ORDER` あり: サーバーは ORDER の組だけを返す（`EFFECT` と混ざらない）ので、待ち列は出さず、1 組ずつ表示する。確定（`actOrderEffects`）の後、通知 N34 で次の組か `EFFECT` が届く。
  - 必須（`mandatory`）: [Skip] を出さない。[Use] だけ。入力が足りないあいだは [Use] が無効。
- **ツールチップが画面より大きいとき**: ホバーで出したものがスクロールできなくても読めるよう、`placeTip` が返す最大の幅と高さに対して、(1) 画像と文を横に並べる（見える幅 480 px 以上）、(2) 幅を最大 640 px まで広げて行数を減らす、(3) 画像を縮める（最小の幅 96 px）の順で収める（§7）。タッチで開いたときだけ、最後の手段として中のスクロールを許す。
- **画面の高さが低い（ノート PC など）**: wide・medium の区域の幅は高さの上限（`innerHeight × 0.62 ÷ 5.49`、下限 56）で小さくなる。下限（56）を割る高さでは、盤面の下の行 A は 1 画面に入らず、縦にスクロールする。
- **`prefers` ではなく BGA の autoscale**: `game_interface_width.min`（360。012 追補 U-6 で設定済み）より狭いとき、BGA が全体を縮める。360 px 以上では縮まない。

## 未確定事項

作者への質問と回答。Q1〜Q8 はすべて暫定案（推奨案）のとおりで確定した（作者回答、2026-11-27）。「変更される既存の決定」は、承認された場合に置き換わる 012・008 の節。

| No. | 質問 | 暫定案 | 回答 |
| --- | --- | --- | --- |
| Q1 | 卓の背景を、ライト・ダークのどちらでも暗い色（公式バナーと同じ、黒に近い紺 + 回路の線）にしてよいか。変更される既存の決定: 012 追補 2026-11-21 U-3（ライトは明るい青灰のグラデーション + タイル、ダークは濃紺）。012 §11（ダークモード。盤面の背景を両方で一定の暗い紺にする記述は変えない） | する。代わりの案: ライトでは今の明るい青灰のまま（部品と体系が分かれたままになる） | 暫定案のとおり（作者回答、2026-11-27） |
| Q2 | 衛星を「紙どおりのロケット」だけにし、簡略表示（チップの一覧）と設定 101 を廃止してよいか。変更される既存の決定: 012 未確定事項 No.8（「簡略を既定」。作者回答 2026-10-19）、012 §4.2 の確定（案 A 既定 + 紙どおりの切り替え）と作者回答 2026-10-20（詳細表示の 3 点のうち切り替えの仕組み）、012 追補 2026-11-21 U-8（設定 101）、012 §4.3（燃料ゲージ）、012 追補 2026-11-14 A（簡略表示のチップの種別アイコン）、008 §7（燃料のゲージ表示） | 廃止する。代わりの案: 既定をロケットにして、簡略表示を設定として残す（見た目を新しい体系に直す作業が増える） | 暫定案のとおり（作者回答、2026-11-27） |
| Q3 | 効果の窓を 1 件ずつの表示にし、[Use] / [Skip] をステータスバーへ移してよいか。変更される既存の決定: 008 §5-1（画面に効果を全部載せる）、008 §4（[Use] / [Skip] の表と「候補のカードを全部並べ、それぞれに [Use] / [Skip]」の記述）、008 §5-2（順番の選択を同じ画面に載せる）、012 追補 2026-11-21 U-1 の 6（パネル内のボタン）と U-1 の状態ごとのボタンの表の 31 の行、012 §6 | する。代わりの案: 一覧のまま、見た目だけ直してトレイへ移す | 暫定案のとおり（作者回答、2026-11-27） |
| Q4 | 計画フェイズで、アクションカードを盤面の上のパネルへ移すのをやめ、盤面のすぐ下の自分の行で選ぶ。変更される既存の決定: 012 追補 2026-11-21 U-1 の 8（「選択パネルに出す」）と U-10 の最後の項目（計画の行を選択パネルへ移している間の描き直し）、012 追補 U-6 の narrow の並び（選択パネルが先頭近く） | する | 暫定案のとおり（作者回答、2026-11-27） |
| Q5 | bga-jump-to（左のボタン）をやめ、プレイヤーパネルのボタンで各プレイヤーの領域へ移る。変更される既存の決定: 012 追補 2026-11-21 U-7（bga-jump-to でショートカット）。【確認】bga-jump-to の文書は「盤が複数あり画面に収まらないゲームだけで使う」と書く | する | 暫定案のとおり（作者回答、2026-11-27） |
| Q6 | パネルの ✓（確定済み）と回転（選択中）をやめ、BGA 標準のアクティブ表示に任せる。地域 A〜H は「n/8」のカウンター 1 つにする。変更される既存の決定: 012 追補 2026-11-21 U-5（確定の印をパネルに置く、地域 A〜H を領域の見出しに置く）、012 §5（パネルの表の「確定の印」「通信確立済みの地域」）、012 §6（待機表示の「パネルの小さな回転アイコン（確定済みの人はチェック）」）、012 §4.4（裏向きのアクションカードの確定の印）、008 §2-2・§2-4（プレイヤーパネルのチェックマーク）、008 §3-1（裏向きのアクションカードに付くチェックマーク） | する。代わりの案: ✓ を残す | 暫定案のとおり（作者回答、2026-11-27） |
| Q7 | 捨て札の一覧を、ポップアップではなく盤面の下（トレイ）に開く。変更される既存の決定: 012 §4.7（捨て札: タップで全一覧を開く。【提案】）と 012 追補 2026-11-21 U-4（「捨て札を押すと一覧が開くのは今のまま」） | する。代わりの案: BGA 標準のダイアログ（`bga.dialogs.generic`）で開く | 暫定案のとおり（作者回答、2026-11-27） |
| Q8 | カード券面の日本語の印刷はそのままにし、英語はツールチップと、手札・アクションカードの下の英語名のキャプションで補う。券面に翻訳した文字を重ねること（012 §8.2 の案 B）は今回は行わない。変更される既存の決定: なし（012 §8.2 の案 A を踏襲し、キャプションを足す。012 未確定事項 No.10 の回答のとおり） | 今回は行わない。代わりの案: 拡大表示のカードにだけ翻訳した効果文を重ねる（別タスク） | 暫定案のとおり（作者回答、2026-11-27） |

## 受け入れ条件

この仕様が正しく実装されたと判断するための条件。確認する幅は 360 / 390 / 768 / 1024 / 1366 / 1440 / 1920 / 2560 px（ライト・ダーク、2〜4 人、観戦、6 列・9 列の盤面）。

審査の指摘ごとの条件:

- [ ] **R1（構造）**: ゲーム領域に白い角丸の面・影・丸い札・色つきの上線・オレンジが無い（`--pe-accent`・`--pe-surface*` の参照が 0）。ライト・ダークで同じ暗い卓。ゾーンの区切りはレールとラベルだけ。`border-radius` を 3 px 超で使うのは部品（カードの輪郭など）だけ
- [ ] **R2（ツールチップ）**: マウス（350 ms のホバー）とタッチ（タップ / 長押し 400 ms / 情報の印）の両方で出る。出た位置が画面内（端から 8 px 以上、ステータスバーの下）で、対象を覆わない。`title` 属性が 0
- [ ] **R3（上部）**: フェイズ帯・選択パネルが無く、ラウンドトラック 1 行（高さ 32 px 程度）だけ。盤面が選択のたびに上下しない
- [ ] **R4（プレイヤーの領域）**: 数値はパネルにだけ（地域は `n/8`）。✓・回転・「Active NE」・燃料ゲージ・「+n」が無い。自分の領域は行 A・B・C の階層、他プレイヤーは小さく下。同じ値が 2 か所に出ない
- [ ] **R5（読みやすさ）**: カードの大きさが §2.2 の表のとおり。文字が 12 px 以上。コントラスト比 4.5:1 以上（§1.2）。100 % の表示で絵・コスト・名声・効果アイコンが見分けられる
- [ ] **R6（浮いた操作・レスポンシブ）**: bga-jump-to が無く、パネルの移動のボタンで各領域へ移れる。`#pe-root` の中に `position: fixed` / `sticky` が無い。BGA のフッターに重なる要素が無い。入れ子のスクロールは narrow の盤面だけ。上の 8 つの幅で横のはみ出しが無い
- [ ] **R7（操作の分かりやすさ）**: 選べる（`pe-can`）・売却すれば選べる（`pe-can-sell`）・選択中（`pe-picked`）・選べない（`pe-off`）・対象（`pe-subject`）が、盤面・カード・ロケット・アクションカード・コマで同じ言語。選択中は選べない区域が暗い。点滅が無い。選べないものを押すと理由が出る（無反応が無い）。トレイの説明文（hint）が無く、説明はステータスバーのタイトル 1 行

機能の条件:

- [ ] 効果の窓（31）が 1 件ずつの表示で、（`EFFECT` が複数のとき）待ち列から注目を切り替えられ、入力値が保たれる。[Use]・[Skip]・[Confirm order] がステータスバーの BGA 標準のボタン（青・白・青）で、パネル内のボタンが無い。サーバーの操作（`actUseEffect`・`actSkipEffect`・`actOrderEffects`）と通知は変わらない
- [ ] 計画（22）が行 A で直接選べ、`moveActionsTo` が無い。マリガン（13）が手札のゾーンで選べる
- [ ] 設定 101 が `gamepreferences.jsonc` から消え、衛星が全員とも紙どおりのロケット。5 機の下端がそろう
- [ ] N31 / N32 でラウンドトラックが `Game end` になり、再読み込みでも（`phase = END`）同じ（バナーは出さない。§3 の訂正）
- [ ] 設定 100 をオンにすると、コマ・ラウンドトラック・レール・パネルに席順の数字が出る（既定はオフ）
- [ ] 効果の窓: `AREA`・`SATELLITE` が 1 つの問いに複数あるとき、アクティブな欄に応じて盤面・ロケットで選べ、欄の `Choose` で切り替わる。[×]・`<select>`・補助のチップ・`pe-btn` が無い。`ORDER` は ◀ ▶ で並べ替えられる。入力が足りないあいだ、タイトルに `${todo}` が出る
- [ ] 得点表が narrow（4 人・360 px）で横スクロールなしに収まる
- [ ] ホバーで出したツールチップが、スクロールなしで読める形に収まる（画面の高さが低いときも。§7 の (1)〜(3)）
- [ ] 概観の段で盤面をタップすると、その位置を中心に標準へ切り替わる
- [ ] `--pe-actionother-w` の変数名の不整合が直り、narrow で他プレイヤーのアクションカードが 36 px になる

自動検査と lint:

- [ ] `npm run check:ui`（Chrome があるとき）の 7 項目が、上の幅で通る: (1) ページの横のはみ出しが無い、(2) `#pe-root` の中に `position: fixed` / `sticky` が無い、(3) 押せる要素が 32 × 32 px 以上、(4) `title` 属性が無い、(5) 文字が 12 px 以上、(6) 実際にスクロールする入れ子の要素は narrow の盤面だけ、(7) ツールチップが画面内にあり対象を覆わない
- [ ] `npm run preview:shots` が `build/preview-shots/` に上の幅 × 主な状態の画像を出す
- [ ] `npm run lint` と `npm run test:js` が通る（`layoutOf`・`computeHexWidth`・ラウンドトラックの状態・`placeTip` のテストを含む）
- [ ] `npx -y markdownlint-cli2 "docs/**/*.md"` がエラーなしで通る
- [ ] 012・008 の、本書で置き換わる節に「→ 014 で変更」の注記があり、実装の完了時に注記を「→ 014 で変更済み」にする
