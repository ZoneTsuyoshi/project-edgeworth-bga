# 004: BGA プロジェクト構成・技術方針

| 項目 | 内容 |
| --- | --- |
| ステータス | Approved |
| 対応タスク | T-004 |
| 作成日 | 2026-10-19 |
| 関連仕様書 | 001（ゲームルール §3 オプション、§11 同時処理）、002（カードデータ）、003（盤面データ）。後続: T-005（CI lint）、T-006（Studio 同期）、T-009（状態遷移）、T-010（データモデル）、T-011（カード効果）、T-012（UI・アセット） |

## 目的

PROJECT EDGEWORTH を BGA Studio のプロジェクト `projectedgeworth` として実装するにあたり、`src/` に何を置くか、BGA フレームワークのどの機能を使うか、PHP・JS をどう構成するかを決める。姉妹プロジェクト minimum-journey-bga の仕様書 003 と実装を土台にし、本ゲーム固有の要件（六角形の盤面、同時選択の多用、99 枚の固有効果）に合わせて調整する。後続の CI・同期・状態遷移・UI の各仕様書はこの仕様書に依存する。

## スコープ

- 対象: `src/` のファイル構成、PHP の名前空間とクラス構成、JS の構成、状態機械の実装方式、ゲームオプションと `gameinfos` の値、盤面描画の方針、命名規則、テスト方針、姉妹プロジェクトとの流用関係
- 対象外: 状態遷移の詳細（T-009）、DB スキーマ・通知（T-010）、カード効果の実装方針（T-011）、盤面の描画詳細・スプライト設計（T-012）、CI・同期ワークフローの実装（T-005、T-006）。この仕様書ではコードもワークフローも作らない

## 記法と調査の前提

- **【確認】** は公式ドキュメント（`https://en.doc.boardgamearena.com/` 配下）で確認した事実。URL を添える。
- **【提案】** は本仕様書の提案。公式ドキュメントでは確認していない。
- **【未確認】** は確認を試みたが判断できなかった事項。「未確定事項」にまとめる。
- 調査日は 2026-10-19。ページは WebFetch（本文を要約して返すツール）で取得したため要約を介した確認であり、長いページ（Game.php、Game.js）は末尾まで読み切れていない箇所がある。コード例の細部は実装時に各 URL を再読する。
- 姉妹プロジェクトの仕様書 003（2026-10-10 作成、2026-10-14 に実機確認を反映）と比べて新しく分かった点は「公式ドキュメントで新たに分かった点」にまとめる。

主な参照 URL（以降は略称で記す）:

| 略称 | URL |
| --- | --- |
| Studio | <https://en.doc.boardgamearena.com/Studio> |
| FileRef | <https://en.doc.boardgamearena.com/Studio_file_reference> |
| GamePhp | <https://en.doc.boardgamearena.com/Main_game_logic:_Game.php> |
| StateDir | <https://en.doc.boardgamearena.com/State_classes:_State_directory> |
| StatesInc | <https://en.doc.boardgamearena.com/Your_game_state_machine:_states.inc.php> |
| Options | <https://en.doc.boardgamearena.com/Options_and_preferences:_gameoptions.json,_gamepreferences.json> |
| GameInfos | <https://en.doc.boardgamearena.com/Game_meta-information:_gameinfos.jsonc> |
| GameJs | <https://en.doc.boardgamearena.com/Game_interface_logic:_Game.js> |
| TsScss | <https://en.doc.boardgamearena.com/Using_Typescript_and_Scss> |
| CodeSharing | <https://en.doc.boardgamearena.com/BGA_Code_Sharing> |
| BgaZoom | <https://en.doc.boardgamearena.com/BgaZoom> |
| BgaCards | <https://en.doc.boardgamearena.com/BgaCards> |
| Checklist | <https://en.doc.boardgamearena.com/Pre-release_checklist> |
| 姉妹 003 | `../minimum-journey-bga/docs/specs/003-bga-architecture.md` |

## 仕様

### 1. 方針の要約

| 項目 | 決定 | 姉妹プロジェクトとの差 |
| --- | --- | --- |
| ファイル構成 | 現行構成のみ（`Game.php`、状態クラス、`Game.js`、JSONC 設定）。非推奨ファイルは作らない | 同じ |
| PHP | 8.4、全ファイル `declare(strict_types=1);` | 同じ |
| 状態機械 | 状態クラス（1 状態 1 クラス） | 同じ |
| クライアント | 素の JS（ES モジュール）＋ JSDoc ＋ `tsc --checkJs`。**複数ファイルに分割する** | 分割する点が違う（§4） |
| CSS | 素の CSS 1 ファイル（`projectedgeworth.css`） | 同じ |
| カード・駒の永続化 | 自前のデータ層（`Database` インターフェース＋リポジトリ）。ItemManager は使わない | 同じ。盤面（区域・キューブ・衛星）が加わる |
| 盤面の計算 | `Board/` に純粋な PHP クラスとして切り出し、PHPUnit で検査 | 新設（六角形の座標・移動・形状判定） |
| 独自メソッドの接頭辞 | `pe` | `mj` → `pe` |
| undo | 使わない（`db_undo_support` は false） | 同じ。さらに公式が multiactive では不可と明記（§5.4） |
| 盤面の拡縮 | bga-zoom を使う | 新規採用 |
| ゲーム終了時の名声内訳 | bga-score-sheet を候補にする | 新規候補 |

### 2. BGA の現行標準ファイル構成（確認した事実）

- **【確認】** 現行標準は `modules/php/Game.php`、`modules/php/States/`、`modules/js/Game.js`。`material.inc.php`、`states.inc.php`、`<game>.view.php`、`<game>_<game>.tpl` は非推奨（FileRef）。`states.inc.php` のページ冒頭にも「状態クラスを使うなら変更不要」とある（StatesInc）。
- **【確認】** `modules/` は追加の PHP・JS を置く場所で、バージョン管理される。`misc/` は本番に出ない Studio 用で合計 1 MB まで。それ以外の場所に置いた追加の `.js` / `.php` は本番に出ない（FileRef）。
- **【確認】** 変更後に必要な操作（FileRef）: `dbmodel.sql` はゲームの再起動、`gameoptions` / `stats` はコントロールパネルでの再読込、`gameinfos.jsonc` は「Reload game informations」（GameInfos）、CSS と `img/` は強制再読込、`Game.js` は F5。
- **【確認】** PHP は 8.4、Dojo は非推奨（Studio）。`Deck`（PHP）と `Stock`（JS）は非推奨で、公式の共有部品として bga-zoom / bga-animations / bga-cards / bga-dice / bga-autofit / bga-score-sheet / bga-jump-to が挙がっている（Studio、CodeSharing）。

### 3. `src/` の構成【提案】

`src/` の中身が BGA 側プロジェクトフォルダ `/projectedgeworth/` と 1 対 1 に対応する。BGA 側にだけあるファイル（`_ide_helper.php`、`bga-framework.d.ts`、各 README）は取り込まず、同期で削除しない既定とする（T-006 で確定）。スプライト生成物は姉妹プロジェクトと同様、コミットせず CI で `src/img/` に生成する（T-012 で設計）。

```text
src/
├── LICENCE_BGA                  # Studio の初期ファイル（取り込む）
├── gameinfos.jsonc              # メタ情報（§8）
├── gameoptions.jsonc            # ゲームオプション（§7）
├── gamepreferences.jsonc        # ユーザー設定（初期は空に近い。必要になれば T-012 で追加）
├── stats.jsonc                  # 統計（定義は T-010）
├── dbmodel.sql                  # DB スキーマ（T-010）
├── projectedgeworth.css         # スタイル（1 ファイル）
├── modules/
│   ├── php/
│   │   ├── Game.php             # Bga\Games\projectedgeworth\Game。薄く保つ（§5.1）
│   │   ├── Material/            # 不変のマスターデータ（カード定義、列挙）
│   │   ├── Board/               # 六角形の盤面の純粋な計算（BGA・DB に依存しない）
│   │   ├── Data/                # DB アクセス（Database、リポジトリ、グローバル値）
│   │   ├── Effects/             # カード効果（99 枚＋衛星 40 面。方式は T-011）
│   │   ├── Flow/                # ラウンド進行・各フェイズ・名声計算のルール計算と通知の組み立て
│   │   ├── Setup/               # setupNewGame の処理（オプション解釈、盤面生成、配札）
│   │   ├── Stats/               # 統計の記録
│   │   └── States/              # 状態クラス（T-009 で一覧を確定）
│   └── js/
│       ├── Game.js              # 入口（Game クラスを export）。他ファイルを import する（§4）
│       ├── states/              # 状態ごとのクライアント側クラス
│       ├── board/               # 六角形の盤面の描画・座標変換・選択
│       ├── cards/               # カード・手札・配備の表示（bga-cards の薄いラッパ）
│       └── ui/                  # プレイヤーパネル、通知、ダイアログ、定数
└── img/                         # サブディレクトリなし。スプライト（CI 生成）と箱・アイコン画像
```

- 名前空間は `Bga\Games\ProjectEdgeworth`【提案】。**【確認】** 追加クラスは PSR-4 に従えば自動で読み込まれ、名前空間は `Bga\Games\<GameName>`、サブフォルダも使える（GamePhp）。大文字小文字は Studio の初期ファイルで確定する（姉妹プロジェクトは `MinimumJourney` だった）。未確定事項 No.1。
- `Board/` は姉妹にない新設。`Flow/` は「ルールの計算」を持つが、フレームワーク（`Table`、`gamestate`）を直接呼ぶのは `States/` と `Game.php` だけにする（姉妹の実績）。
- `Effects/` は T-011 が方式を決めるまで空のディレクトリ予約とする。99 枚の効果を `Game.php` や状態クラスに書かない原則だけをここで決める。
- 非推奨ファイル（`states.inc.php`、`material.inc.php`、`*.action.php`、`*.view.php`、`.tpl`）は作らない。Studio の初期ファイルに含まれていた場合は、初回同期の前に確認して取り込まない（姉妹では含まれていなかった）。
- TypeScript テンプレート一式（`package.json`、`rollup.config.mjs`、`tsconfig.json`、`src-disabled/`）は `src/` に取り込まない（TsScss は `src/ts` → `modules/js/Game.js` のバンドルを前提にしており、本リポジトリの `src/` と衝突する）。
- リポジトリ直下（`src/` の外）に置くもの: `composer.json`、`package.json`、`phpstan.neon.dist`、`phpcs.xml.dist`、`eslint.config.mjs`、`tsconfig.json`、`tests/`、`scripts/`、`tools/bga/`（スタブ。コミットしない）。

### 4. JS の構成

**【確認】** Game.js は `modules/js` に置き、`export class Game` を export する。コンストラクタは `constructor(bga)` で、状態クラスは `this.bga.states.register(name, new StateClass(this, this.bga))` で登録する。複数のクラスを別ファイルに分け、`export class PlayerManager {...}` として export し、Game.js の先頭で `import { PlayerManager, PlayerTable } from "./PlayerManager.js";` と読み込める（GameJs「Including classes from external files」）。bga-cards などは `await importEsmLib('bga-cards', '1.x')` で読み込む（BgaCards、BgaZoom）。

**採用: 素の JS ＋ JSDoc ＋ `// @ts-check`（`tsc --noEmit --checkJs`）。ただしファイルを分割する。**

| 案 | 長所 | 短所 |
| --- | --- | --- |
| A. 素の JS を分割（採用） | `src/` が BGA と 1 対 1。ビルド不要。Node だけで ESLint と型チェックが回る | 型の厳密さは JSDoc 頼み。分割のたびに `import` の動作確認が要る |
| B. TypeScript を CI でバンドル | 型が書きやすい | `src/ts` の置き場が `src/` と衝突する。CD にビルド工程が要る。手元の試験同期にもビルドが要る |

- 姉妹プロジェクトは単一の `Game.js` が約 2,800 行になった。本ゲームは六角形の盤面・99 枚のカード効果の選択 UI・同時選択の状態が加わり、さらに大きくなる見込みなので、最初から分割する。これが姉妹から変える最大の点（姉妹 003 では「分割できるか未確認」だったが、上記のとおり公式が分割方法を示している）。
- 分割の単位【提案】: 状態ごとのクラスは `states/<StateName>.js`、盤面は `board/`、カード表示は `cards/`。`Game.js` は `setup`、状態クラスの登録、通知の購読だけを持つ。
- 本番用の minify 設定（Checklist の "Use minified JS"）でも相対 `import` が動くかは**【未確認】**。実装の最初の PR で Studio 上で確認する（未確定事項 No.4）。だめなら TsScss の方式（Rollup で `Game.js` 1 本にバンドル）に切り替える。
- ESLint のグローバルに `importEsmLib`、`getLibUrl`、`_`、`$` を足す設定は姉妹の `eslint.config.mjs` を流用する。`bga-framework.d.ts` は `tools/bga/` にローカルで置き、コミットしない。
- CSS は素の CSS 1 ファイル。複数ファイルへの分割（`@import`）が使えるかは**【未確認】**のため、1 ファイルのままセクションコメントで整理する。配色は CSS 変数で持ち、ダークモードに対応する（Checklist）。

### 5. 状態機械の実装方式

詳細な状態一覧・遷移は T-009。ここでは方式だけを決める。

#### 5.1 状態クラス

- **【確認】** 状態は `modules/php/States/` の `GameState` を継承したクラス。コンストラクタ引数は `id`・`type` が必須、`name`・`description`・`descriptionMyTurn`・`transitions`・`updateGameProgression`・`initialPrivate` が任意。`type` は `ACTIVE_PLAYER` / `MULTIPLE_ACTIVE_PLAYER` / `PRIVATE` / `GAME`。ID は正の整数で 1 と 99 は予約、稼働中のゲームで変えると壊れる。`onEnteringState` の戻り値にクラス名・状態 ID（int）・遷移名を返すと遷移でき、その場合 `nextState` は呼ばない。アクションは `act*` メソッドに `#[PossibleAction]`。`getArgs()` は戻り値が全員に公開され、本人だけに見せる値は `_private`（プレイヤー ID をキー）に入れる。非 GAME 状態には `zombie(int $playerId)` が必須（StateDir）。
- 1 状態 1 クラス。状態 ID は `Flow/StateId.php` の定数にまとめる（StateDir が名前付き定数を推奨）。
- 状態クラスは「入場時の準備（`onEnteringState`）」「表示用の引数（`getArgs`）」「プレイヤーの操作（`act*`）」「離脱時の自動行動（`zombie`）」だけを書く。ルールの判定・計算は `Board/`・`Flow/`・`Effects/` に委ね、状態クラスから呼ぶ。
- `getArgs` では DB を書かない（姉妹の実績）。

#### 5.2 同時選択 → 全員確定で一斉処理

本ゲームでは次が「全員が同時に選び、全員確定で一斉に公開・処理する」形になる（001 §11-3、Q-09・Q-10・Q-16・Q-17）: アクションカードの選択（計画フェイズ）、打ち上げ・転回の向きと位置、通信確立の区域、配備時効果などの選択、循環フェイズの追加移動、マリガン、ドラフト。

方式【提案】:

1. **収集状態**: `MULTIPLE_ACTIVE_PLAYER`。入場時（`onEnteringState`）に選択が必要なプレイヤーだけを `setPlayersMultiactive($players, 'allChosen', true)` でアクティブにする。選択内容は DB に保存するが、他プレイヤーへの通知・`getArgs` の公開部分には含めない。本人の選択は `_private` で返す。
2. **確定の通知**: 選び終えたプレイヤーは `setPlayerNonMultiactive($playerId, 'allChosen')` で非アクティブにする。全員が非アクティブになると遷移する。他プレイヤーには「選択済み」であること（内容なし）だけを見せる。
3. **一斉処理**: 遷移先は `GAME` 型の状態で、全員の選択を 1 つの `Flow/` のクラスに渡して結果を計算し、通知をまとめて送る。複数プレイヤーの効果が同時に起きる場合、結果は「処理前の状態」から全員分を計算して一斉に反映する（001 §11-3 の支払い判定の規則と整合）。順序は 1 人の中だけで決める（Q-15）。
4. **選び直し（Q-09）**: 全員が確定するまで取り消して選び直せる。姉妹プロジェクトは、非アクティブになった人のアクションを `#[CheckAction(false)]` と自前検証で受け付ける方式（案 B）を実装した。ただし姉妹の仕様書 006 の時点では Studio での実機確認が未了だった（姉妹 006 の U1）。**公式ドキュメントには、非アクティブにした人が選択を変えてよいかの記述がない**（StatesInc は「濫用するな」とだけ述べる）。本プロジェクトは最初に Studio で検証し、だめな場合は「選び終えても全員確定までアクティブのまま維持し、全員が埋まった時点で `setAllPlayersNonMultiactive` する」代替案（姉妹の案 A）に切り替える。T-009 が採用案と検証手順を確定する（未確定事項 No.2）。
5. **離脱（zombie）**: 各収集状態に、無難な既定の選択（パス・何もしない・最小コスト）を自動確定する `zombie()` を実装する。具体的な既定は T-009。

#### 5.3 カード効果による割り込みの選択

- 効果ごとの選択は「判定 → 必要なら収集状態（MULTIPLE_ACTIVE_PLAYER） → 解決」を呼び出し元の状態へ戻る形にする。姉妹では、割り込み状態を「効果のレベル」単位にまとめ、同時に解決するものだけを MULTIPLE_ACTIVE_PLAYER にした（姉妹 006）。
- private parallel states（`initialPrivate`、`initializePrivateStateForAllActivePlayers()`、`nextPrivateState()` など。StateDir）は**採用しない方針を既定**とする。公式ページ自体が「private 状態の例は新フレームワークでは未検証」と注記している（StateDir）。本ゲームは複数ステップの選択を持つ効果が姉妹より多い可能性があるため、T-009・T-011 で効果の一覧を見て再判断する（未確定事項 No.3）。
- 未解決の選択は DB の「保留中の決定」テーブルに持つ（T-010）。再読込・再接続で状態が復元できること。

#### 5.4 その他

- **undo は使わない**。**【確認】** `undoSavepoint()` は複数の保存点を持てず、multiactive 状態、および次の状態が multiactive の場合には使えない。有効化には `gameinfos` の `db_undo_support` が必要（GamePhp）。本ゲームは同時選択が中心であり、非公開情報も絡むため使わない（`db_undo_support` は false）。
- **進行度**: `getGameProgression()` を実装する（Checklist で必須）。終了条件（衛星 5 機の稼働、キューブ 16 個の使い切り。001 §10）への近さから算出する。式は T-009。
- **終了**: 最終名声の計算を専用の `GAME` 状態で行い、その後に終了する。**【確認】** `stGameEnd` / `argGameEnd` は `Game.php` でオーバーライドしない。最後の状態は 99 の代わりに `GameResult::individualRanking(...)` を返せる（StateDir、GamePhp）。同点は同順位で `player_score_aux` を使わない（Q-08）。順位の返し方は T-009。
- **乱数**: `bga_rand(min, max)` または `random_int()`。`rand()` / `mt_rand()` は使わない（GamePhp）。
- **通知**: `$this->bga->notify->all(type, message, args)`、本人のみは `notify->player(...)`（観戦者には送れない）。メッセージは `clienttranslate()` で包み、`${player_name}` などの置換を使う（GamePhp）。画面の更新は通知経由のみとし、リプレイ・観戦で崩れないようにする。通知の一覧は T-010。

### 6. PHP の構成

#### 6.1 クラスの役割【提案】

| ディレクトリ | 役割 | 例（名前は T-009〜T-011 で確定） |
| --- | --- | --- |
| `Material/` | 不変のマスターデータ。カード 99 枚（P001〜P099）・衛星 40 面・褒賞 24 面・ミッション 10 枚・アクション 5 枚の定義と列挙。識別子は 002 §3 に従う（`name_id` は snake_case、列挙値は `UPPER_SNAKE_CASE`） | `CardDefinitions`、`EffectTiming`、`ProjectType` |
| `Board/` | 盤面の純粋な計算。座標系（003 §1 のダブルド座標）、方向、地域ボードの写像と回転、衛星の移動と上下の反射（003 §4）、隣接（003 §6）、ミッション形状の一致と 2 枚の割り当て（003 §8）。BGA・DB・乱数に依存しない | `Hex`、`Direction`、`RegionLayout`、`Movement`、`Adjacency`、`MissionMatcher` |
| `Data/` | DB アクセス。`Database` インターフェース（BGA 実装とテスト用 PDO 実装の差し替え点）、リポジトリ、グローバル値、プレイヤー値 | `BgaDatabase`、`CardRepository`、`SatelliteRepository`、`CellRepository` |
| `Effects/` | カード効果。効果は所属カードの `name_id` で識別する（002 §3.1）。方式（タイミングごとのフック、効果同士の相互作用）は T-011 | T-011 で確定 |
| `Flow/` | フェイズごとのルール計算、名声計算、通知の組み立て。フレームワークを直接呼ばない | `PlanningPhase`、`LaunchPhase`、`FameCalculator`、`StateId`、`Notification` |
| `Setup/` | `setupNewGame` の本体。オプションの解釈、盤面生成（ランダム配置は `bga_rand` を注入）、配札 | `GameSetup`、`BoardSetup` |
| `Stats/` | 統計の記録。BGA の統計 API を `StatStore` インターフェースで隠し、テストでは配列実装に差し替える | `StatsRecorder` |
| `States/` | 状態クラス（フレームワークとの接点） | T-009 |

- 乱数・時刻・プレイヤー名の取得のように BGA に依存するものは、`Flow/` と `Setup/` のクラスにコンストラクタ（またはメソッド引数）で注入する。これでフレームワーク無しの PHPUnit で動かせる（姉妹の実績）。
- `Game.php` は、フレームワークが要求するメソッド（`setupNewGame`、`getAllDatas`、`getGameProgression`、`upgradeTableDb`）、依存の組み立て、通知送信・例外生成のような共通ヘルパー、`debug_` メソッドに限る。**【確認】** `debug_` で始まる `Game.php` のメソッドは Studio のデバッグメニューに出る（姉妹 003 が Practical_debugging で確認）。
- `getAllDatas` は非公開情報（他人の手札、ミッション、保存済みの未確定の選択、山札の順序）を含めない。本人分だけを返す。
- ItemManager を使わない理由: 姉妹で評価し、`dbmodel.sql` の自前スキーマと `Database` インターフェースの組み合わせのほうが PHPUnit（結合テストを含む）で検証しやすかった。本ゲームは区域・キューブ・衛星・保留中の決定など、カード以外の永続データが多く、ItemManager の対象（カード）に収まらない。

#### 6.2 命名規則・コーディング規約

- **独自メソッドの接頭辞は `pe`**（例: `peSendNotifications`）。フレームワークのクラス（`Table`、`GameState` など）を継承するクラスに足すメソッドすべてが対象で、`Game.php` だけでなく状態クラスの private メソッドにも付ける（姉妹の `mjRecord` の実績）。理由は CLAUDE.md の「BGA 実装の方針」のとおり: 同名のメソッドがあると Studio で致命的エラーになり、開発用スタブが古いため PHPStan では検出できない。フレームワークを継承しないクラス（`Board/`・`Flow/` など）には適用しない。
- 上記の検査を機械化する案【提案】: T-005 で、`Table` / `GameState` を継承するクラスの新規メソッドが `pe` で始まることを調べるスクリプトを足す（フレームワークが定めるメソッド名と `debug_` は許可リスト）。未確定事項 No.5。
- `declare(strict_types=1);` を全 PHP ファイルに付ける。**【確認】** 新規プロジェクトは strict で生成され、`self::` ではなく `$this->` を使う（姉妹 003 が Migration ガイドで確認）。型宣言、`enum`、属性を積極的に使う。
- PHP は 8.4 を前提にする（Studio）。CI の PHP も 8.4。`composer.json` の `config.platform.php` は `8.4.0`。
- 規約は PSR-12（行長 140 文字）、静的解析は PHPStan level 5 から開始（姉妹の設定）。具体は T-005。
- 識別子は 002 §3.1 に従う: カード ID は印刷番号（`P001` など）、列挙値は `UPPER_SNAKE_CASE`、クラス・メソッドは PSR-1。DB の列名は `snake_case`。
- UI 文言は英語を原文とし、PHP は `clienttranslate()`、JS は `_()` で包む。日本語訳はベータ入り後に BGA の翻訳ツールで行い、訳ファイルはリポジトリに置かない（姉妹 003 が Translations ページで確認）。
- すべてのソースファイルの冒頭に著作権表記を付ける（Checklist）。表記は「© だんじり協奏曲 (Danjiri Concerto)」、連絡先は https://x.com/danjiriconcerto（確定、未確定事項 No.9）。

### 7. ゲームオプション（`gameoptions.jsonc`）

**【確認】** 形式（Options）: オプション ID を文字列のキーにした JSON オブジェクトで、ID は 100 番台を使う（200〜299 は BGA 共通オプション用に予約）。各オプションは `name`、`values`（値のキーは文字列）、`default`、`level`（`base` / `major` / `additional`。ファンシーロビーの表示にだけ影響）、`displaycondition`（`minplayers` / `maxplayers` / `otheroption` / `otheroptionisnot`）、`startcondition`（値ごとの開始条件。`message` を付けられる）、`notdisplayedmessage`、値側の `tmdisplay` / `description` / `beta` / `alpha` などを持つ。値が yes/no / on/off のちょうど 2 つなら、チェックボックスで表示される。`gamestartonly` は使わない（Studio のバグがあるとして公式が禁止）。PHP は `$this->bga->tableOptions->get(int $optionId): ?int`（GamePhp）、JS は `this.bga.tableOptions.get(optionId)`（GameJs）。オプションはゲーム開始後に変更できない。

オプションの内容は 001 §3 が確定している。ID と値のキーは次のとおりに割り当てる【提案】。値のキーは一度決めたら変えない（`Material/OptionId.php`・`Setup/` の enum と一致させる）。

| ID | 名前（原文） | 値（キー: 名前） | 初期値 | 表示条件・開始条件 | 根拠 |
| --- | --- | --- | --- | --- | --- |
| 100 | Board layout | 1: Standard (Earth) / 2: Standard (Random w/o Flip) / 3: Standard (Random w/ Flip) / 4: Advanced (Earth) / 5: Advanced (Random w/o Flip) / 6: Advanced (Random w/ Flip) | 1 | なし。`level: major` 候補（未確定事項 No.7） | 001 §3-1、Q-02・Q-03 |
| 101 | Board size | 1: 2x4 / 2: 2x3 / 3: 2x2 | 1 | `displaycondition`: `maxplayers` 3。`startcondition`: 値 3（2x2）は `maxplayers` 2 | 001 §3-2、Q-06 |
| 102 | Award cards | 1: Initial Game / 2: Random (1: Cube, 1: Other) / 3: Random | 1 | なし | 001 §3-3、Q-04 |
| 103 | Satellite plan | 1: MOON / 2: Player-variable | 1 | なし | 001 §3-4、Q-05 |
| 104 | Starting hand | 1: None / 2: Mulligan / 3: Draft | 1 | 値 3（Draft）は実装するまで `values` に含めない（キーは予約） | 001 §3-5、Q-06 |
| 105 | Public fame (except missions) | 1: Hidden / 2: Visible | 1 | なし。`level: additional`。Visible のときだけ、ゲーム中にミッション V を除く名声を公開する（`getAllDatas.publicFame`、通知 N36。010 未確定事項 No.2、作者回答 2026-10-20） | 010 未確定 2 |

- 101 を 2 人または 3 人のときだけ表示するのは `displaycondition` の `maxplayers`（人数が 3 以下のとき表示）で表す。2x2 は 2 人だけなので、3 人のときに選べないよう `startcondition` を併用する（001 §3-2 の表）。人数の組み合わせの検証はサーバー側（`setupNewGame`）でも二重に行う。
- 100 は 6 択を 1 つのオプションで持つ（作者が示した名称のとおり。Q-02）。面（Standard / Advanced）と並べ方（Earth / Random w/o Flip / Random w/ Flip）を 2 つのオプションに分ける案もあるが、作者の回答の形を優先する。内部では `BoardFace`（STANDARD / ADVANCED）と `BoardPlacement`（EARTH / RANDOM_NO_FLIP / RANDOM_FLIP）の 2 つの enum に分解して扱う。
- 褒賞の ✕ 印のカードが候補に入るのは Advanced のときだけ（001 §3-3）。この依存は PHP で処理し、オプション側では条件にしない。
- ドラフト（104 の値 3）は後回し（Q-06）。**【確認】** 値に `alpha` / `beta` を付けると警告または開発者以外は訓練モードのみ、という挙動にできる（Options）ので、未完成の間に公開する場合に使える。初版ではそもそも値を載せない。
- 値の `tmdisplay` は初期値以外に付け、ロビーのテーブル説明に出す。初期値の「None」などは空文字列にする。
- ゲーム開始時に確定するランダムな配置（盤面の並び、回転、褒賞）は、`setupNewGame` で 1 度だけ決めて DB に保存する（再現・復元のため。T-010）。
- 乱数に関するオプション（シード指定など）は設けない。

### 8. `gameinfos.jsonc` の値

**【確認】** 次のフィールドは GameInfos で確認した: `players`、`suggest_player_number`、`not_recommend_player_number`、`player_colors`、`losers_not_ranked`、`tie_breaker_description` / `tie_breaker_split`、`fast/medium/slow_additional_time`、`disable_player_order_swap_on_rematch`。`designer` / `artist` / `year` / `tags` / `presentation` などは**もうこのファイルからは読まれず、Game Metadata Manager で管理する**。`estimated_duration`、`bgg_id`、`db_undo_support`、`game_interface_width` は GameInfos では説明を確認できなかった（姉妹の初期ファイルに存在する）。

| キー | 値 | 根拠・備考 |
| --- | --- | --- |
| `game_name` | `"PROJECT EDGEWORTH"` | game-description.md |
| `publisher` / `publisher_website` / `publisher_bgg_id` | `Danjiri Concerto` / `https://danjiri-concerto.github.io/index.html` / `0`（BGG の出版社 ID は未取得。取得したら設定） | 作者回答（未確定事項 No.8）。個人制作のゲーム |
| `bgg_id` | `0`（BGG の承認待ち） | 承認後に作者が BGG_ID を伝え、エージェントが設定する。docs/tasks.md の作者作業 1 |
| `players` | `[2, 3, 4]` | 001 §1（2〜4 人） |
| `suggest_player_number` | `null`（暫定） | 001・ルールブックに推奨人数の記載なし。初期オプションは 2 人から開始可能（未確定事項 No.6） |
| `not_recommend_player_number` | `null` | 同上 |
| `estimated_duration` | `50` | 40〜60 分の中央値（001 §1、game-description.md）。ロビーでの初期表示にだけ使われ、のちに実測に置き換わる（姉妹の初期ファイルのコメント） |
| `fast_additional_time` / `medium_…` / `slow_…` | 開発中は大きめ。**【確認】** ベータ入りの際に自動調整され、以後の変更は管理者の対応が要る（GameInfos） | 値は初期ファイルのままにして、アルファ申請前に見直す |
| `tie_breaker_description` | `""` | 同点は同順位（Q-08）。`player_score_aux` を使わない |
| `losers_not_ranked` | `false` | **【確認】** 2 人プレイや同点が多いゲームでは使わないよう公式が注意している（GameInfos） |
| `is_coop` | `0` | 対戦ゲーム |
| `db_undo_support` | `false` | §5.4 |
| `game_interface_width.min` | `740`（初期ファイルの値のまま。暫定） | 横長の盤面（12 列）の幅の扱いは T-012（bga-zoom の自動縮小）。未確定事項 No.10 |
| `player_colors` | 初期ファイルのまま | 駒の色（衛星の青・緑・赤・黄との対応）は T-012 で決める。BGA の既定色を 4 色に絞る必要がある |
| `language_dependency` | `false` | UI 文言は翻訳機構、カード券面は画像 |

- `gameinfos.jsonc` を変更したら、コントロールパネルの「Reload game informations」が必要（GameInfos）。同期後の手動操作の一覧は T-006。

### 9. 六角形の盤面の描画方式【提案。詳細は T-012】

前提: 盤面は pointy-top の六角形で 6 行 × 12 列（72 区域）、縮小時は 9 列・6 列（003 §1.1）。地域ボードは 8 枚 × 両面で、ゲーム開始時に配置と面が決まり、180° 回転して置かれることがある（003 §2）。左右の端は接続し、衛星は上下の端で反射する。区域内に最大 4 つのキューブのマス、衛星コマ、発射拠点コマが載る。

| 案 | 内容 | 長所 | 短所 |
| --- | --- | --- | --- |
| A. 地域ボード画像の背景 ＋ DOM の透明な区域要素（採用候補） | 印刷版の地域ボード画像（`data/boards/region/`）を 3×3 の地域ごとの背景に使い、その上に区域ごとの透明な六角形要素（`clip-path`）を絶対配置する。駒は DOM 要素 | 印刷版と同じ見た目。区域は DOM なのでクリック・ハイライト・アニメーションが容易。座標計算は 003 のダブルド座標 → ピクセル変換だけ | 画像の枚数と容量（16 面）に BGA の上限（`img/` の総量 15 MB、スプライト 4096×4096）がかかる。180° 回転した面の扱い |
| B. 区域を SVG で描く | 区域の種類のタイル（`data/boards/area/`）を SVG の `<image>` / パターンで並べる | 拡縮に強い。ハイライトが描きやすい | 画像の扱いが A と同等で、DOM の駒との座標合わせが増える |
| C. 区域ごとのタイル画像を CSS で並べる | 区域の種類（7 種）のタイルを 72 個並べる | 回転や縮小盤面に柔軟（印刷版の地域ボード画像を使わない） | 印刷版の地域アイコンや区域ボーナスの描き込みの再現が必要 |

- 方針: 盤面全体を 1 つの拡縮対象（bga-zoom の `Manager`、`autoZoom.expectedWidth` でボード幅を指定。BgaZoom）の中に置き、区域は DOM の絶対配置にする。座標は `Board/` のダブルド座標を JS でも同じ式で使う（式は 003 §1 を `board/` に写す。PHP と JS で二重実装になるので、003 の検証例を両方のテストで使う）。
- ハイライト（選択可能な区域、衛星の進路）と、区域上のキューブ・衛星の表示は DOM で行う。左右の端をまたぐ移動は、反対側からの出現として表現する（アニメーションの扱いは T-012）。
- 地域ボードを 180° 回転する場合の表示は、画像の回転か、回転後の区域の対応（003 §2.3）に基づく表示の切り替えのどちらかを T-012 が決める。
- スマートフォンでの操作（タップ領域、拡縮）は T-012。
- 画像の制約: **【確認】** Checklist は「1 枚 4 MB まで、総量 15 MB まで」（姉妹 003 が確認した Img ページの「1 枚 2 MB」とは食い違ったまま）。小さい 2 MB を上限として扱う。スプライトの最大サイズ 4096×4096、`img/` 直下はサブフォルダなしで 10 ファイル未満を目安にする（姉妹 003・009）。

### 10. テスト方針

- **純粋なルール計算を PHPUnit で検査する**。対象と検証データ:
  - `Board/`: 座標変換（オフセット ↔ ダブルド ↔ キューブ）、地域ボードのグローバル座標への写像と 180° 回転（003 §2）、移動と上下の反射・左右の接続（003 §4）、隣接（003 §6）、ミッション形状の一致と 2 枚の最適割り当て、左右の接続をまたぐ形（003 §8）。
  - `Flow/` の名声計算: 001 §12-1 の計算例（ルールブック p.22〜25）をテストベクタにする。褒賞・ミッションの得点、同点（同順位）。
  - ラウンド進行・終了判定・コスト計算（下限 0）・先駆コスト・同時支払いの規則（001 §11）。
  - `Setup/`: オプションごとの盤面生成（種を固定した乱数）、人数別の初期資金。
- 結合テスト（MariaDB）: 姉妹と同じく Docker（`php:8.4-cli` ＋ `mariadb`）で `Data/` と `Setup/` を実 DB で検査する。BGA のフレームワーク（`Table`）は使わない。姉妹の作者回答（2026-10-15）で、手元の PHP 確認に使い捨ての Docker を使ってよい。CI での実行有無は T-005 が決める。
- BGA 上でしか確認できないこと（状態遷移、通知、非公開情報の漏れ、zombie、リロード復元、観戦、リプレイ）は Studio での手動テスト表で確認する。表は T-009 以降の仕様書に含める。
- JS はテストを書かない（ESLint と `tsc --checkJs`）。ただし座標変換のように PHP と二重実装になる純粋関数は、003 の検証例を Node の簡単なスクリプトで検査する【提案。T-012 で判断】。
- 配置: `tests/Unit/`、`tests/Integration/`、`tests/Support/`（BGA スタブ、配列版の `globals`、種付き乱数）。名前空間 `ProjectEdgeworthTests\`。PHPUnit は姉妹と同じ系列（12.x）から開始し、T-005 でバージョンを再確認する。

### 11. lint と CI（T-005 で導入）

この仕様書では項目だけを決め、設定の詳細は T-005 に送る。姉妹の `docs/specs/004-ci-lint.md` の構成を流用する。

| 項目 | 内容 |
| --- | --- |
| Markdown | markdownlint-cli2（`.markdownlint-cli2.jsonc` は既存） |
| PHP | `php-parallel-lint`、PHP_CodeSniffer（PSR-12、行長 140）、`declare(strict_types=1)` の検査、PHPStan level 5（`tools/bga/_ide_helper.php` のスタブが必要。Studio から取得し、コミットしない） |
| JS | ESLint（BGA のグローバルを定義）、`tsc --noEmit --checkJs` |
| CSS / JSONC | Stylelint、`@eslint/json` による JSONC の構文検査 |
| 独自 | `pe` 接頭辞の検査（§6.2）、認証情報の混入、`src/` に開発用ファイルを置かない、画像の上限 |
| 実行環境 | PHP 8.4（Node は姉妹の `engines` に合わせる） |

### 12. 姉妹プロジェクトから流用するもの / しないもの

| 項目 | 扱い | 理由 |
| --- | --- | --- |
| `src/` の基本構成（`Material/`・`Data/`・`Flow/`・`Setup/`・`Stats/`・`States/`） | 流用 | 実績があり、PHPUnit でフレームワーク無しに検証できる |
| `Database` インターフェース、リポジトリ、`GlobalValues` / `PlayerValues`、`StateId`、`Notification` | 設計を流用（コードの再利用ではなく、同じ形で新規に書く） | ゲーム固有のデータが違う |
| `Board/`、`Effects/` | 新設 | 六角形の盤面計算と、99 枚の固有効果が本ゲーム固有 |
| JS の単一 `Game.js` | しない（分割する） | 姉妹で約 2,800 行まで肥大した |
| JSDoc ＋ `// @ts-check`、素の CSS、TypeScript/SCSS を使わない方針 | 流用 | `src/` が BGA と 1 対 1。ビルド不要 |
| 接頭辞 `mj` | `pe` に変更 | CLAUDE.md |
| 状態クラスの同時選択（`setPlayersMultiactive` ＋ `setPlayerNonMultiactive`、`_private`） | 流用。選び直しの方式は Studio で最初に検証 | §5.2 |
| private parallel states | 既定では不採用 | §5.3 |
| ItemManager / Deck / Stock | 使わない | 非推奨、または適さない |
| undo | 使わない | §5.4 |
| スプライトを CI で生成してコミットしない方針、`scripts/build-sprites.mjs` の構造、`sharp` の固定 | 流用（対象画像は T-012 で設計） | 作者の方針（姉妹 No.11） |
| SFTP 同期（ポート 2022、SSH 鍵認証、ホスト鍵固定、`lftp`）と GitHub Secrets / Variables の構成 | 流用（T-006）。鍵を使い回せるかは T-006 で確認 | 同じ作者・同じ Studio |
| lint 構成、`composer.json`、`phpstan.neon.dist`、`phpcs.xml.dist`、`eslint.config.mjs`、`tsconfig.json` | 流用（T-005）。名前空間・パスを置き換える | 同上 |
| `tests/` の構成（Unit / Integration / Support、Docker での MariaDB 結合テスト） | 流用 | 同上 |
| 統計 `stats.jsonc` の ID 採番の注意（10 以上、削除後の再利用禁止） | 流用 | 公式の規則 |
| 名声の合計を非表示にするオプション、山札の閲覧オプション | 流用しない | 本ゲームの名声は終了時に計算される内容が多く、必要なら別途 T-012 で判断 |

### 13. 公式ドキュメントで新たに分かった点（姉妹 003 からの更新）

- **JS の分割方法が公式に示された**: `export class` ＋ 先頭での `import { ... } from "./X.js"`（GameJs）。姉妹 003 では「`modules/js/` 内の複数ファイルを束ねられるか記述なし」だった。minify 時の動作は未確認。
- 追加クラスは `modules/php/` の PSR-4 規則で自動読み込みされ、サブフォルダも使える（GamePhp）。
- **undo は multiactive 状態（および次が multiactive の場合）では使えない**（GamePhp）。
- 状態クラスの `getArgs` に `_merge_private`、`alwaysMergePrivate()`、`_no_notify`（自動でスキップする状態をフロントに通知しない）が追加されている。private データは multiactive 状態を遅くしうる、という注意もある（StateDir）。
- JS の状態クラスに `onPlayerActivationChange(args, isCurrentPlayerActive)` があり、multiactive 状態で自分のアクティブ状態が変わったときに呼ばれる。multiactive では `onEnteringState` の時点でアクティブか分からないため、`onUpdateActionButtons` や上記で判定する（GameJs）。
- **状態の入場時に `setAllPlayersMultiactive()` などを呼ぶ**。状態の入場前にアクティブにしてはいけない（StatesInc）。
- 最終状態から `GameResult::individualRanking(...)` を返せる。`stGameEnd` / `argGameEnd` はオーバーライドしない（StateDir、GamePhp）。
- オプション: `displaycondition` に `minplayers` / `maxplayers`（配列可）/ `otheroption` / `otheroptionisnot`、`startcondition` の `message`、`notdisplayedmessage`、値の `alpha` / `beta` / `premium`、`gamestartonly` の使用禁止が明記されている（Options）。JS からは `this.bga.tableOptions.get(optionId)` で読める（GameJs）。
- 公式の共有部品に bga-zoom（盤面の拡縮と `autoZoom`）、bga-score-sheet（終了時の得点表）、bga-autofit、bga-jump-to がある（CodeSharing、BgaZoom）。姉妹 003 は bga-cards と bga-animations しか扱っていない。
- `gameinfos.jsonc` の `designer` / `artist` / `year` / `tags` / `presentation` などは読まれなくなり、Game Metadata Manager で管理する（GameInfos）。
- Checklist の画像上限は「1 枚 4 MB」のまま、Img ページの 2 MB との食い違いは変わらない。PHP 8.4 は Studio ページで再確認した。
- 変わっていない点: 現行ファイル構成、非推奨ファイルの一覧、Deck / Stock 非推奨、`gameoptions` の ID 範囲、`bga_rand`。

## エッジケース

- 非アクティブにした人が選択を変えられない場合、§5.2 の代替案（全員が確定するまでアクティブのまま維持）に切り替える。持ち時間が選び終えた人にも減り続けるかは姉妹でも未確認（姉妹 006 の U2）。
- 2 人プレイで 2x2 の盤面を選ぶと、六角形の盤面の行・列・隣接・左右の接続の周期が変わる（003 §1.1）。`Board/` は盤面の幅を引数に取り、固定値にしない。
- ランダム配置（w/ Flip）で盤面が生成された後に、プレイヤーがリロードしても同じ盤面になること。配置は `setupNewGame` で決めて DB に保存する。
- Studio の初期ファイルに非推奨ファイルが含まれていた場合は、状態クラスと二重定義にならないよう、初回同期の前に確認する。
- JSON 設定（`gameoptions.jsonc` など）が不正だと、Studio の再読込で初めて気づく。CI の JSONC 検査で防ぐ（T-005）。
- 値のキー（オプション ID と値のキー）を後から変えると稼働中のテーブルが壊れる。一度公開したら変えない。

## 未確定事項

| No. | 質問 | 暫定案 | 回答 |
| --- | --- | --- | --- |
| 1 | Studio の初期ファイル（作者の作業 6）に含まれる名前空間の大文字小文字、クラス名、非推奨ファイルの有無。初回同期の前に確認してよいか | `Bga\Games\ProjectEdgeworth`。初期ファイルを取得して確認する（姉妹と同じ手順） | 暫定案のとおり（2026-10-19） |
| 2 | 同時選択で、選び終えて非アクティブになった人の選び直しを `#[CheckAction(false)]` で受け付ける方式（姉妹の案 B）が Studio で動くか。公式ドキュメントに記述がない | 最初の実装 PR で Studio で検証する。だめなら全員確定までアクティブのまま維持する案 A に切り替える（T-009 が確定） | 暫定案のとおり（2026-10-19） |
| 3 | private parallel states を使うか | 使わない。T-009・T-011 で効果の一覧を見て再判断する | 暫定案のとおり（2026-10-19） |
| 4 | `modules/js/` の相対 `import` が、本番用の minify 設定でも動くか | 最初の実装 PR で Studio で確認する。だめなら Rollup で 1 本にバンドルする方式（TsScss）に切り替える | 暫定案のとおり（2026-10-19） |
| 5 | `pe` 接頭辞の検査スクリプトを T-005 の lint に入れてよいか | 入れる | 暫定案のとおり（2026-10-19） |
| 6 | 推奨人数（`suggest_player_number`）はあるか。ルールブックに記載がない | `null`（2 人から開始可能な初期オプション） | 暫定案のとおり（2026-10-19） |
| 7 | オプション 100（ボードの配置）を `level: major`（ロビーで目立つ）にしてよいか | `major` にしない（`base`）。他のオプションも `base`、ドラフト・マリガンのみ `additional` | 暫定案のとおり（2026-10-19） |
| 8 | `gameinfos` の `publisher`・`publisher_website`・`publisher_bgg_id` に入れる値。MINIMUM JOURNEY の登録と同じ英語表記は何か | 作者の回答待ち（姉妹の `gameinfos.jsonc` も初期値のまま） | `Danjiri Concerto`、`https://danjiri-concerto.github.io/index.html`、出版社の BGG ID は未取得（`0`）（2026-10-19） |
| 9 | ソースファイル冒頭の著作権表記に入れる氏名と連絡先 | 作者の回答待ち | 「© だんじり協奏曲 (Danjiri Concerto)」と https://x.com/danjiriconcerto（2026-10-19） |
| 10 | 横長の盤面（12 列）に対する `game_interface_width.min` の値。スマートフォンでの最小幅の方針 | `740`（初期ファイルのまま）。bga-zoom の自動縮小で対応し、T-012 で最終決定 | 暫定案のとおり（2026-10-19） |
| 11 | JS を TypeScript ではなく素の JS ＋ JSDoc で進めることを、本ゲームでも続けてよいか（姉妹では作者了承済み。ただし本ゲームは UI が複雑） | 続ける。ファイル分割で対応 | 暫定案のとおり（2026-10-19） |

## 受け入れ条件

- [ ] 各【確認】の記述に公式ドキュメントの URL が添えられている
- [ ] 確認できなかった事項が【未確認】として「未確定事項」に挙がっている
- [ ] `src/` の構成が BGA 側のパスと 1 対 1 で対応している
- [ ] PHP の名前空間・ディレクトリ構成、JS の分割方針、状態機械の方式（同時選択 → 全員確定で一斉処理）が書かれている
- [ ] ゲームオプションの ID・値のキー・表示条件が、001 §3 の 5 種類すべてについて決まっている
- [ ] `gameinfos` の値（人数 2〜4、プレイ時間、BGG_ID の扱い）が書かれている
- [ ] 命名規則（接頭辞 `pe`、strict_types、PHP 8.4）とテスト方針（PHPUnit の対象）が書かれている
- [ ] 姉妹プロジェクトから流用するもの / しないものの一覧がある
- [ ] T-005・T-006・T-009〜T-012 への申し送りが読み取れる
- [ ] 「未確定事項」に作者が回答し、回答に合わせて更新されたうえでステータスが Approved になる

## 追記（2026-10-20）

- 未確定事項 No.1: Studio の雛形の名前空間は `Bga\Games\projectedgeworth`（すべて小文字）だった（006 の調査）。雛形に合わせて小文字に統一する（E-4・E-5 のマージ後に別 PR で機械置換）。
