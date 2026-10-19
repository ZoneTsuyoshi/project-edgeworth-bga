# 002: カードデータ仕様

| 項目 | 内容 |
| --- | --- |
| ステータス | Approved |
| 対応タスク | T-002 |
| 作成日 | 2026-10-19 |
| 関連仕様書 | — |

## 目的

PROJECT EDGEWORTH の全カード（プロジェクトカード 99 枚、衛星カード 40 面、褒賞カード 24 面、ミッションカード 10 枚、アクションカード 5 枚）の番号・名称・コスト・名声・効果発動タイミング・効果タイプ・稼働条件・効果文を、**印刷物を正として**1 つの仕様書にまとめる。BGA の material 定義と翻訳文（英語原文）の原本とし、`CLAUDE.md` の用語集で保留していた英語識別子を確定させる。

## スコープ

- 対象: カードのデータ項目の定義、列挙値と英語識別子、全カードの一覧と印刷どおりの効果文、英語識別子・英語文の案、制作マスター（`faq.xlsx`）との差分、カード個別の FAQ 裁定の転記、解釈が割れる点の洗い出し。
- 対象外: 効果の処理順・同時処理（T-007、仕様書 007）、効果同士の相互作用と実装方針（T-011、仕様書 011）、起動効果の「使用済み」の UI 表現（歯車アイコンに「×」を重ねて示す、と作者回答済み。ここでは効果タイプの識別子 `ACTIVATED` のみ決める）。効果文を本書の言葉で解釈・再定義することもしない。解釈が必要な点は「未確定事項」として作者に質問する。

## 仕様

### 1. 根拠と読み方

- **印刷物が正**。カード画像（`data/cards/` の `@4x.png`）の全 99 + 40 + 24 + 10 + 5 枚を 1 枚ずつ目視で確認して転記した（ルールブック p5〜p9・p27 のアイコン凡例を併用）。
- 制作マスター（`faq.xlsx` の `project` / `crew` / `device` / `software` / `moon` / `blue` / `green` / `red` / `yellow` / `king` / `action` シート）は下書きとして使い、印刷物と食い違う点は「12. マスターと印刷物の差分」に一覧にした。差分は多数あるため、**マスターの値を直接使わない**こと。
- 効果文の日本語は印刷どおり。カード上のアイコンは `[マヤ]` のような角括弧表記に置き換えた（凡例は 2 節）。句読点・全角半角は画像に合わせた（末尾の「。」の有無もカードによって異なる）。
- エラッタ（<https://danjiri-concerto.github.io/project-edgeworth/errata.html>）: P098「バックリンク」の効果発動タイミングは印刷が企画フェイズ（巻物）のアイコンだが、**循環フェイズ中（循環矢印）が正**。本書は訂正後の値（`ORBIT_PHASE`）を採用し、印刷値を備考に残した。
- FAQ（`faq.xlsx` の `faq` シート、全 94 行、`status` が「要確認」の行は無い）のうち `main_card_ids` に挙がるカード個別の裁定は「11. FAQ（カード個別の裁定）」に転記した。
- **カード番号の食い違い**: 印刷では P048 が「可変軌道装置」、P049 が「太陽電池パネル」だが、マスターと FAQ（`faq.xlsx` の ID、`faq.json` の `cards`）では逆（P048 = 太陽電池パネル、P049 = 可変軌道装置）。本書は印刷に従い、FAQ 行はカード名で突き合わせた（未確定事項 No.1）。
- 一般則（FAQ より）: 稼働中のみ発動する効果は、衛星が稼働中でなければ発動しない。「配備コスト」はカードのコスト＋稼働中の衛星なら稼働コスト。通信確立コスト・配備コストは割引で 0 未満にならない（FAQ 行 45）。

### 2. 表記の凡例

効果文中のアイコンは次の角括弧表記で記す。

| 表記 | アイコン | 英語文での表記 |
| --- | --- | --- |
| `[マヤ]` | 丸いコイン（マヤ） | `[Maya]` |
| `[予算]` | 四角いコイン（予算） | `[Budget]` |
| `[燃料]` | 炎（燃料） | `[Fuel]` |
| `[CREW]` `[DEVICE]` `[SOFTWARE]` | プロジェクトのタイプのアイコン | 同じ |
| `[配備コスト]` | 配備コストの円環アイコン | `deployment cost` と文で表す |
| `[陸]` `[海]` | 陸区域・海区域の六角形アイコン | `[Land]` `[Sea]` |
| `[A]`〜`[H]` | 地域アイコン（A〜H の装飾文字） | `[A]`〜`[H]` |

- 「予算」アイコンと「マヤ」アイコンは形が違う（予算は角の丸い四角、マヤは円）。マスターの `[マネー]` は印刷では `[マヤ]` または `[予算]`。
- 地域アイコンは装飾文字で読みにくいが、マスターの文面・FAQ の記述と全箇所で一致することを確認した。

### 3. 識別子の規則と列挙

#### 3.1 識別子の規則

| 対象 | 規則 | 例 |
| --- | --- | --- |
| カード ID | 印刷の番号をそのまま使う（大文字、`P` + 3 桁） | `P001`、`R001B`、`R006`、`T001`、`M001`、`A001` |
| カードの英語識別子（`name_id`） | 英語名案の snake_case。プロジェクトカードは**効果の識別子を兼ねる** | `aviation_engineer` |
| 列挙値 | `UPPER_SNAKE_CASE` | `PLANNING_PHASE` |
| 衛星の形状 | 印刷の英字をそのまま大文字で | `HAMMER` `RETRO` `SCALLOP` `CONCORDE` `STEALTH` |
| 衛星プラン | 印刷のプラン名 | `MOON` `PHOBOS` `DEIMOS` `EUROPA` `GANYMEDE` |
| 衛星プランのゲームオプション | 作者回答（2026-10）に従う | `MOON` / `PLAYER_VARIABLE` |
| 衛星の色 | `BLUE`（`B`）`GREEN`（`G`）`RED`（`R`）`YELLOW`（`Y`） | |
| 衛星カードの `name_id` | `<形状>_<プラン>` の小文字（MOON 面は色を付けない） | `hammer_moon`、`hammer_phobos` |
| 効果 | 効果は所属するカードの `name_id` で識別する（1 カード 1 効果）。MOON 面の色違いは同じ `name_id` | |

#### 3.2 プロジェクトのタイプ

| 日本語 | 英語識別子 | 備考 |
| --- | --- | --- |
| CREW | `CREW` | 青の人型アイコン。P001〜P033（主に計画・予算発行・配備フェイズ） |
| DEVICE | `DEVICE` | 黄のチップアイコン。P034〜P066（主に打ち上げ/転回・通信確立フェイズ） |
| SOFTWARE | `SOFTWARE` | 緑のモニタアイコン。P067〜P099（主に企画・循環フェイズ） |

根拠: ルールブック p6、p27。

#### 3.3 効果発動タイミング

カード中央上部のアイコン 3 つのうち左端。ルールブック p6・p27 の凡例は 8 種。

| 日本語（凡例） | 英語識別子 | 備考 |
| --- | --- | --- |
| 計画フェイズ中 | `PLANNING_PHASE` | 「A」のアイコン |
| 予算発行フェイズ中 | `BUDGET_PHASE` | |
| 自分のカードが配備されたとき | `DEPLOYED` | パズルピースのアイコン |
| 自分の衛星が打ち上げられたとき | `LAUNCHED` | |
| 自分の衛星が転回したとき | `TURNED` | |
| 自分の衛星が通信確立したとき | `LINKED` | |
| 企画フェイズ中 | `PROPOSAL_PHASE` | 巻物のアイコン |
| 循環フェイズ中 | `ORBIT_PHASE` | 循環矢印のアイコン |

凡例にない値（実カードにのみ存在）:

| 日本語 | 英語識別子 | 備考 |
| --- | --- | --- |
| 「ALL」アイコン（いつでも） | `ANYTIME` | P077、R009。凡例に無い（確定: 未確定事項 No.2・No.3） |
| 打ち上げ/転回 | `LAUNCHED_OR_TURNED` | R019（EUROPA CONCORDE）のみ。打ち上げと転回の 2 アイコンを斜線で結合 |

#### 3.4 効果タイプ

カード中央上部のアイコン 3 つのうち中央。

| 日本語 | 英語識別子 | アイコン | 意味 |
| --- | --- | --- | --- |
| 常時 | `CONSTANT` | ∞ | 常に発動する |
| 起動 | `ACTIVATED` | 歯車 | 条件を満たしたとき各ラウンド 1 回まで発動できる（使用済みは歯車＋×のアイコンで示す） |
| 配備時 | `ON_DEPLOY` | パズルピース | そのカードが配備されたとき |
| 打ち上げ時 | `ON_LAUNCH` | 打ち上げのアイコン | そのカードが配備された衛星が打ち上げられたとき。衛星カードの MOON 面でも使う |

根拠: ルールブック p6、p7、p27。

#### 3.5 稼働条件

カード中央上部のアイコン 3 つのうち右端（◎）。

| 日本語 | 英語識別子 | 表示 | 意味 |
| --- | --- | --- | --- |
| 稼働中のみ | `ACTIVE_ONLY` | ◎ あり | 配備されている衛星が稼働中のときのみ効果を発動する |
| なし | `ANY` | 何も無い | 稼働の有無に依らず効果を発動する |

根拠: ルールブック p6、p7、p27。

#### 3.6 その他の列挙

| 列挙 | 値 |
| --- | --- |
| 地域 | `A`〜`H` |
| 区域の種類（効果文中） | `LAND`（陸）、`SEA`（海） |
| 衛星の色 | `BLUE` `GREEN` `RED` `YELLOW` |
| 衛星の形状 | `HAMMER` `RETRO` `SCALLOP` `CONCORDE` `STEALTH` |
| 衛星プラン | `MOON` `PHOBOS` `DEIMOS` `EUROPA` `GANYMEDE` |
| 褒賞の分類 | `CUBE`（通信確立系）、`OTHER`（それ以外）。7 節 |
| 褒賞の目印 | `STAR1`（★）、`STAR2`（★★）、`ADVANCED_ONLY`（✕、地域ボード裏面のゲームのみ）、`NONE` |
| ミッションの段階 | `EASY`（薄い色）、`EXTRA`（網掛け） |
| アクションの種類 | `BUDGET`（I）、`DEPLOY`（II）、`LAUNCH_TURN`（III）、`LINK`（IV）、`PROPOSAL`（V） |

### 4. データ項目の定義

#### 4.1 プロジェクトカード（`P001`〜`P099`）

| 項目 | 型 | 説明 |
| --- | --- | --- |
| `id` | 文字列 | カード番号（`P001`〜`P099`）。画像ファイル名と一致（`P037` のみファイル名が `P037 @4x.png`） |
| `name` | 文字列 | 日本語名（印刷） |
| `name_en` / `name_id` | 文字列 | 英語名案 / snake_case の識別子 |
| `type` | 列挙 | `CREW` / `DEVICE` / `SOFTWARE`（番号の範囲で決まる） |
| `cost` | 整数 | 配備コスト（右上の円環内の数字。7 セグメント風フォント） |
| `fame` | 整数 | 名声（右下のペンタゴン内の数字） |
| `fuel_slots` | 整数 | 燃料庫の数（カード左側の青い四角の数） |
| `timing` | 列挙 | 効果発動タイミング（3.3） |
| `effect_kind` | 列挙 | 効果タイプ（3.4） |
| `active_condition` | 列挙 | 稼働条件（3.5） |
| `text_ja` / `text_en` | 文字列 | 効果文（印刷どおり）/ 英語文案 |

#### 4.2 衛星カード

| 項目 | 型 | 説明 |
| --- | --- | --- |
| `id` | 文字列 | MOON 面は `R001B` のように番号＋色、色別プラン面は `R006`〜`R025` |
| `shape` / `plan` / `color` | 列挙 | 形状 / プラン / 色（MOON 面は色が 4 通り） |
| `launch_cost` | 整数 | 打ち上げコスト（右上） |
| `fame` | 整数 | 名声。ゲーム終了時、その衛星が稼働中であれば獲得できる（ルールブック p7） |
| `fuel_slots` | 整数 | 燃料庫の数。**初期燃料庫（色付きの四角）を含む**（FAQ 行 18 の「燃料庫2の衛星RETRO」と一致） |
| `timing` / `effect_kind` / `active_condition` | 列挙 | プロジェクトカードと同じ |
| `text_ja` / `text_en` | 文字列 | 効果文 |

#### 4.3 褒賞・ミッション・アクション

7〜9 節の各表の列を参照。

### 5. プロジェクトカード（99 枚）

タイプ別に 2 つの表で示す。1 つ目はデータ項目、2 つ目は効果文（印刷どおり）と英語文案。

#### 5.1 CREW（P001〜P033）

| 番号 | 名前 | 英語名案 | `name_id` | コスト | 名声 | 燃料庫 | タイミング | 効果タイプ | 稼働条件 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| P001 | 航空技師 | Aviation Engineer | `aviation_engineer` | 5 | 2 | 2 | `PLANNING_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P002 | 航空指揮官 | Aviation Commander | `aviation_commander` | 5 | 2 | 2 | `PLANNING_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P003 | ロンサムラビット | Lonesome Rabbit | `lonesome_rabbit` | 6 | 1 | 3 | `PLANNING_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P004 | 軌道修正士 | Course Corrector | `course_corrector` | 3 | 1 | 2 | `BUDGET_PHASE` | `ACTIVATED` | `ANY` |
| P005 | 調達担当官 | Procurement Officer | `procurement_officer` | 5 | 2 | 2 | `BUDGET_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P006 | 予算折衝官 | Budget Negotiator | `budget_negotiator` | 7 | 2 | 2 | `BUDGET_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P007 | 折衝術士 | Master Negotiator | `master_negotiator` | 9 | 2 | 4 | `BUDGET_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P008 | 統合運用士 | Integrated Operator | `integrated_operator` | 10 | 2 | 2 | `BUDGET_PHASE` | `CONSTANT` | `ACTIVE_ONLY` |
| P009 | ファーストペンギン | First Penguin | `first_penguin` | 3 | 2 | 2 | `DEPLOYED` | `ON_DEPLOY` | `ANY` |
| P010 | 地上整備技師 | Ground Mechanic | `ground_mechanic` | 4 | 1 | 1 | `DEPLOYED` | `CONSTANT` | `ANY` |
| P011 | 双配備技師 | Twin Deploy Technician | `twin_deploy_technician` | 4 | 1 | 2 | `DEPLOYED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P012 | ドッキング整備技師 | Docking Mechanic | `docking_mechanic` | 4 | 2 | 2 | `DEPLOYED` | `CONSTANT` | `ACTIVE_ONLY` |
| P013 | クルー指揮官 | Crew Commander | `crew_commander` | 7 | 2 | 2 | `DEPLOYED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P014 | クルー調整官 | Crew Coordinator | `crew_coordinator` | 7 | 2 | 2 | `DEPLOYED` | `CONSTANT` | `ANY` |
| P015 | 機器搭載士 | Device Installer | `device_installer` | 7 | 2 | 2 | `DEPLOYED` | `CONSTANT` | `ANY` |
| P016 | システム・アーキテクト | System Architect | `system_architect` | 7 | 2 | 2 | `DEPLOYED` | `CONSTANT` | `ANY` |
| P017 | 主任機器士 | Chief Device Officer | `chief_device_officer` | 7 | 3 | 2 | `DEPLOYED` | `CONSTANT` | `ACTIVE_ONLY` |
| P018 | スクワドロン補佐官 | Squadron Aide | `squadron_aide` | 7 | 3 | 2 | `DEPLOYED` | `CONSTANT` | `ACTIVE_ONLY` |
| P019 | フリート指揮官 | Fleet Commander | `fleet_commander` | 7 | 3 | 2 | `DEPLOYED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P020 | フリートエンジニア | Fleet Engineer | `fleet_engineer` | 7 | 3 | 2 | `DEPLOYED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P021 | フリートアーキテクト | Fleet Architect | `fleet_architect` | 7 | 3 | 2 | `DEPLOYED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P022 | ジャグラー | Juggler | `juggler` | 8 | 3 | 2 | `DEPLOYED` | `ON_DEPLOY` | `ANY` |
| P023 | 統合プランナー | Integrated Planner | `integrated_planner` | 9 | 2 | 2 | `DEPLOYED` | `ACTIVATED` | `ANY` |
| P024 | スワップ技師 | Swap Technician | `swap_technician` | 10 | 2 | 2 | `DEPLOYED` | `ON_DEPLOY` | `ANY` |
| P025 | 先駆的ゴリラ | Pioneering Gorilla | `pioneering_gorilla` | 10 | 3 | 2 | `DEPLOYED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P026 | フレンチブルドッグ | French Bulldog | `french_bulldog` | 10 | 6 | 4 | `DEPLOYED` | `ON_DEPLOY` | `ANY` |
| P027 | 人事プランナー | Personnel Planner | `personnel_planner` | 11 | 2 | 3 | `DEPLOYED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P028 | 機器プランナー | Equipment Planner | `equipment_planner` | 11 | 2 | 3 | `DEPLOYED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P029 | ITプランナー | IT Planner | `it_planner` | 11 | 2 | 3 | `DEPLOYED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P030 | 人材育成官 | Talent Development Officer | `talent_development_officer` | 12 | 2 | 2 | `DEPLOYED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P031 | 機器投資官 | Equipment Investment Officer | `equipment_investment_officer` | 12 | 2 | 2 | `DEPLOYED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P032 | IT投資官 | IT Investment Officer | `it_investment_officer` | 12 | 2 | 2 | `DEPLOYED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P033 | 第七王子 | Seventh Prince | `seventh_prince` | 8 | 6 | 3 | `DEPLOYED` | `CONSTANT` | `ANY` |

| 番号 | 効果文（印刷） | 英語文案 |
| --- | --- | --- |
| P001 航空技師 | 計画フェイズの「アクションカードの回収」において、自分は縦向きのアクションカードが「II 配備」「III 打ち上げ/転回」であれば手札に戻す。すなわち、「II 配備」「III 打ち上げ/転回」を複数ラウンド連続してアクション選択できる | In the Planning Phase "Retrieve Action Cards" step, if your upright Action Card is "II Deploy" or "III Launch/Turn", return it to your hand. That is, you can choose "II Deploy" or "III Launch/Turn" in consecutive rounds. |
| P002 航空指揮官 | 計画フェイズの「アクションカードの回収」において、自分は縦向きのアクションカードが「IV 通信確立」「V 企画」であれば手札に戻す。すなわち、「IV 通信確立」「V 企画」を複数ラウンド連続してアクション選択できる | In the Planning Phase "Retrieve Action Cards" step, if your upright Action Card is "IV Establish Link" or "V Proposal", return it to your hand. That is, you can choose "IV Establish Link" or "V Proposal" in consecutive rounds. |
| P003 ロンサムラビット | 他のどのプレイヤーともアクション選択が被らなければ+2[マヤ] | If your action choice does not overlap with that of any other player, +2 [Maya]. |
| P004 軌道修正士 | 予算発行フェイズ中、2[マヤ]支払ってもよい。そうした場合、自分の衛星を1機選んで、[燃料]を1消費して1区域進める | During the Budget Phase, you may pay 2 [Maya]. If you do, choose one of your satellites, spend 1 [Fuel], and move it forward 1 area. |
| P005 調達担当官 | 予算発行フェイズで4[マヤ]支払ってもよい。そうした場合、プロジェクトカードを1枚引く | In the Budget Phase, you may pay 4 [Maya]. If you do, draw 1 Project card. |
| P006 予算折衝官 | 予算発行フェイズの初めに5[マヤ]支払ってもよい。そうした場合、+1[予算] | At the start of the Budget Phase, you may pay 5 [Maya]. If you do, +1 [Budget]. |
| P007 折衝術士 | 予算発行フェイズで、アクション選択ボーナスを獲得するとき、+2[マヤ] | In the Budget Phase, when you gain an Action Selection Bonus, +2 [Maya]. |
| P008 統合運用士 | [CREW][DEVICE][SOFTWARE]が揃っている自分の衛星1機につき、予算発行フェイズで獲得できる[マヤ]+2 | For each of your satellites that has [CREW], [DEVICE] and [SOFTWARE] all deployed, the [Maya] you can gain in the Budget Phase is +2. |
| P009 ファーストペンギン | このカードを配備したとき、プロジェクトカードを1枚引く | When this card is deployed, draw 1 Project card. |
| P010 地上整備技師 | この衛星への[配備コスト]-2 | Deployment cost for this satellite -2. |
| P011 双配備技師 | この衛星に[DEVICE]のカードを配備したとき、配備コストを支払って[DEVICE]のカードを1枚自分の好きな衛星に配備してもよい | When a [DEVICE] card is deployed to this satellite, you may pay its deployment cost to deploy 1 [DEVICE] card to any satellite of yours. |
| P012 ドッキング整備技師 | この衛星への[配備コスト]-3 | Deployment cost for this satellite -3. |
| P013 クルー指揮官 | この衛星に[CREW]を配備したとき、+1[予算] | When a [CREW] is deployed to this satellite, +1 [Budget]. |
| P014 クルー調整官 | この衛星への[CREW]のカードの[配備コスト]-3 | Deployment cost of [CREW] cards deployed to this satellite -3. |
| P015 機器搭載士 | この衛星への[DEVICE]のカードの[配備コスト]-3 | Deployment cost of [DEVICE] cards deployed to this satellite -3. |
| P016 システム・アーキテクト | この衛星への[SOFTWARE]のカードの[配備コスト]-3 | Deployment cost of [SOFTWARE] cards deployed to this satellite -3. |
| P017 主任機器士 | この衛星への[DEVICE]の[配備コスト]-4 | Deployment cost of [DEVICE] deployed to this satellite -4. |
| P018 スクワドロン補佐官 | 自分の全ての衛星の[配備コスト]-1 | Deployment cost for all your satellites -1. |
| P019 フリート指揮官 | 自分の全ての衛星の[CREW]の[配備コスト]-2 | Deployment cost of [CREW] for all your satellites -2. |
| P020 フリートエンジニア | 自分の全ての衛星の[DEVICE]の[配備コスト]-2 | Deployment cost of [DEVICE] for all your satellites -2. |
| P021 フリートアーキテクト | 自分の全ての衛星の[SOFTWARE]の[配備コスト]-2 | Deployment cost of [SOFTWARE] for all your satellites -2. |
| P022 ジャグラー | このカードを配備したとき、自分の待機中の衛星のプロジェクトカードを全て好きなように付け替える。ただし、配備時の効果は発動しない | When this card is deployed, rearrange all Project cards on your standby (inactive) satellites as you like. However, "On Deploy" effects are not triggered. |
| P023 統合プランナー | この衛星にプロジェクトカードを配備したとき、プロジェクトカードを1枚引く | When a Project card is deployed to this satellite, draw 1 Project card. |
| P024 スワップ技師 | このカードを配備したとき、自分の稼働中の衛星を1機選んでもよい。そうした場合、その衛星とこの衛星の稼働状態・燃料消費量・場所を入れ替える | When this card is deployed, you may choose one of your active satellites. If you do, swap its active state, fuel consumption and location with this satellite. |
| P025 先駆的ゴリラ | この衛星に[SOFTWARE]を配備したとき、プロジェクトカードを2枚引く | When a [SOFTWARE] is deployed to this satellite, draw 2 Project cards. |
| P026 フレンチブルドッグ | このカードを配備するとき、配備対象の衛星についているカード1枚につき[配備コスト]-2 | When deploying this card, deployment cost -2 for each card already on the target satellite. |
| P027 人事プランナー | 自分のいずれかの衛星に[CREW]を配備したとき、プロジェクトカードを1枚引く | When a [CREW] is deployed to any of your satellites, draw 1 Project card. |
| P028 機器プランナー | 自分のいずれかの衛星に[DEVICE]を配備したとき、プロジェクトカードを1枚引く | When a [DEVICE] is deployed to any of your satellites, draw 1 Project card. |
| P029 ITプランナー | 自分のいずれかの衛星に[SOFTWARE]を配備したとき、プロジェクトカードを1枚引く | When a [SOFTWARE] is deployed to any of your satellites, draw 1 Project card. |
| P030 人材育成官 | 自分のいずれかの衛星に[CREW]を配備したとき、[予算]+1 | When a [CREW] is deployed to any of your satellites, [Budget] +1. |
| P031 機器投資官 | 自分のいずれかの衛星に[DEVICE]を配備したとき、[予算]+1 | When a [DEVICE] is deployed to any of your satellites, [Budget] +1. |
| P032 IT投資官 | 自分のいずれかの衛星に[SOFTWARE]を配備したとき、[予算]+1 | When a [SOFTWARE] is deployed to any of your satellites, [Budget] +1. |
| P033 第七王子 | この衛星にプロジェクトカードを配備できない | Project cards cannot be deployed to this satellite. |

#### 5.2 DEVICE（P034〜P066）

| 番号 | 名前 | 英語名案 | `name_id` | コスト | 名声 | 燃料庫 | タイミング | 効果タイプ | 稼働条件 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| P034 | 加速ブースター | Acceleration Booster | `acceleration_booster` | 5 | 2 | 2 | `LAUNCHED` | `ON_LAUNCH` | `ANY` |
| P035 | ギャンブルシステム | Gamble System | `gamble_system` | 7 | 0 | 2 | `LAUNCHED` | `ON_LAUNCH` | `ANY` |
| P036 | 軽量化フレーム | Lightweight Frame | `lightweight_frame` | 7 | 2 | 2 | `LAUNCHED` | `ACTIVATED` | `ANY` |
| P037 | 姿勢制御装置 | Attitude Controller | `attitude_controller` | 7 | 3 | 3 | `TURNED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P038 | 燃料交換管 | Fuel Transfer Pipe | `fuel_transfer_pipe` | 7 | 3 | 3 | `LAUNCHED` | `ACTIVATED` | `ANY` |
| P039 | センサーアレイ | Sensor Array | `sensor_array` | 9 | 2 | 3 | `LAUNCHED` | `ON_LAUNCH` | `ANY` |
| P040 | 射出補助装置 | Launch Assist Unit | `launch_assist_unit` | 9 | 2 | 3 | `LAUNCHED` | `CONSTANT` | `ANY` |
| P041 | 打ち上げ契約装置 | Launch Contract Unit | `launch_contract_unit` | 12 | 1 | 3 | `LAUNCHED` | `ACTIVATED` | `ANY` |
| P042 | ダンスドッグロボット | Dancing Dog Robot | `dancing_dog_robot` | 15 | 2 | 3 | `LAUNCHED` | `ON_LAUNCH` | `ANY` |
| P043 | 回生推進機 | Regenerative Thruster | `regenerative_thruster` | 5 | 2 | 3 | `TURNED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P044 | 編隊同期装置 | Formation Sync Unit | `formation_sync_unit` | 6 | 3 | 3 | `TURNED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P045 | 軌道整備ドッグ | Orbital Repair Dock | `orbital_repair_dock` | 7 | 2 | 3 | `TURNED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P046 | 固定軌道装置 | Fixed Orbit Unit | `fixed_orbit_unit` | 7 | 5 | 4 | `TURNED` | `CONSTANT` | `ACTIVE_ONLY` |
| P047 | トルネードビット | Tornado Bit | `tornado_bit` | 9 | 2 | 3 | `TURNED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P048 | 可変軌道装置 | Variable Orbit Unit | `variable_orbit_unit` | 5 | 2 | 3 | `LINKED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P049 | 太陽電池パネル | Solar Panel | `solar_panel` | 6 | 2 | 2 | `LINKED` | `CONSTANT` | `ACTIVE_ONLY` |
| P050 | 欧阿中継ネットワーク | Euro-Africa Relay Network | `euro_africa_relay_network` | 6 | 1 | 2 | `LINKED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P051 | 米亜中継ネットワーク | Americas-Asia Relay Network | `americas_asia_relay_network` | 6 | 1 | 2 | `LINKED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P052 | 大陸アンテナ | Continental Antenna | `continental_antenna` | 7 | 2 | 2 | `LINKED` | `CONSTANT` | `ACTIVE_ONLY` |
| P053 | 面舵アンテナ | Starboard Antenna | `starboard_antenna` | 7 | 2 | 2 | `LINKED` | `CONSTANT` | `ACTIVE_ONLY` |
| P054 | 取舵アンテナ | Port Antenna | `port_antenna` | 7 | 2 | 2 | `LINKED` | `CONSTANT` | `ACTIVE_ONLY` |
| P055 | ステルス艦 | Stealth Ship | `stealth_ship` | 7 | 2 | 3 | `LINKED` | `CONSTANT` | `ACTIVE_ONLY` |
| P056 | 前方広域送信機 | Forward Wide-Area Transmitter | `forward_wide_area_transmitter` | 8 | 2 | 2 | `LINKED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P057 | 後方広域送信機 | Rear Wide-Area Transmitter | `rear_wide_area_transmitter` | 8 | 2 | 2 | `LINKED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P058 | 真紅遠隔通信装置 | Crimson Remote Comm Unit | `crimson_remote_comm_unit` | 8 | 1 | 3 | `LINKED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P059 | 瑠璃遠隔通信装置 | Lapis Remote Comm Unit | `lapis_remote_comm_unit` | 8 | 1 | 3 | `LINKED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P060 | 北洋波形転送装置 | North Sea Waveform Relay | `north_sea_waveform_relay` | 8 | 2 | 3 | `LINKED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P061 | 南露波形転送装置 | South Russia Waveform Relay | `south_russia_waveform_relay` | 8 | 2 | 3 | `LINKED` | `ACTIVATED` | `ACTIVE_ONLY` |
| P062 | 塵芥海洋アンテナ | Dust Ocean Antenna | `dust_ocean_antenna` | 6 | 2 | 2 | `LINKED` | `CONSTANT` | `ACTIVE_ONLY` |
| P063 | 智利海洋アンテナ | Chile Ocean Antenna | `chile_ocean_antenna` | 6 | 2 | 2 | `LINKED` | `CONSTANT` | `ACTIVE_ONLY` |
| P064 | 前方ビームアンテナ | Forward Beam Antenna | `forward_beam_antenna` | 10 | 2 | 2 | `LINKED` | `CONSTANT` | `ACTIVE_ONLY` |
| P065 | 後方ビームアンテナ | Rear Beam Antenna | `rear_beam_antenna` | 10 | 2 | 2 | `LINKED` | `CONSTANT` | `ACTIVE_ONLY` |
| P066 | 電波遮断装置 | Signal Blocker | `signal_blocker` | 10 | 8 | 2 | `LINKED` | `CONSTANT` | `ACTIVE_ONLY` |

| 番号 | 効果文（印刷） | 英語文案 |
| --- | --- | --- |
| P034 加速ブースター | この衛星を打ち上げたとき、[マヤ]を2/3/4/5支払ってもよい。そうした場合、この衛星を1/2/3/4区域分進め、1/2/3/4[燃料]を消費する。その際に通過した各区域のニアミス判定は行われず、着陸/着水判定は行われる | When this satellite is launched, you may pay 2/3/4/5 [Maya]. If you do, move this satellite forward 1/2/3/4 areas and spend 1/2/3/4 [Fuel]. No near-miss check is made for the areas passed through, but the landing/splashdown check is made. |
| P035 ギャンブルシステム | この衛星を打ち上げたとき、このカードを捨てる。代わりにプロジェクトカードの山札の1番上のカードをこの衛星に配備コストを支払わずに配備する。新しく配備した衛星の「配備時」「打ち上げ時」効果は発動する | When this satellite is launched, discard this card. Instead, deploy the top card of the Project deck to this satellite without paying its deployment cost. The newly deployed card's "On Deploy" and "On Launch" effects are triggered. |
| P036 軽量化フレーム | 自分の衛星を打ち上げるとき、その衛星に配備されているカード1枚につき打ち上げコスト-1 | When you launch a satellite, launch cost -1 for each card deployed to that satellite. |
| P037 姿勢制御装置 | この衛星が転回したとき、プロジェクトカードを1枚引く | When this satellite turns, draw 1 Project card. |
| P038 燃料交換管 | 自分の衛星を打ち上げたとき、その衛星の搭載燃料以内で[燃料]を好きなだけ減らしてもよい。そうした場合、自分の他の稼働中の衛星を1機選び、[燃料]を減らした分だけその衛星の燃料を回復する。ただし、初期燃料庫を超えて回復することはできない | When you launch a satellite, you may reduce [Fuel] by any amount up to that satellite's loaded fuel. If you do, choose another of your active satellites and recover its fuel by the amount reduced. However, it cannot recover beyond its initial fuel slot. |
| P039 センサーアレイ | この衛星を打ち上げたとき、この衛星に配備されている[DEVICE]のカードの枚数だけプロジェクトカードを引く | When this satellite is launched, draw Project cards equal to the number of [DEVICE] cards deployed to this satellite. |
| P040 射出補助装置 | 自分の衛星の打ち上げコスト-3 | Launch cost of your satellites -3. |
| P041 打ち上げ契約装置 | 自分の衛星を打ち上げたとき、+1[予算] | When you launch a satellite, +1 [Budget]. |
| P042 ダンスドッグロボット | この衛星を打ち上げたとき、この衛星に配備されている[DEVICE]のカードの枚数1枚につき[予算]+1 | When this satellite is launched, [Budget] +1 for each [DEVICE] card deployed to this satellite. |
| P043 回生推進機 | この衛星が転回したとき、[燃料]を1回復する | When this satellite turns, recover 1 [Fuel]. |
| P044 編隊同期装置 | 自分の他の衛星が転回したとき、この衛星は転回してもよい | When another of your satellites turns, this satellite may turn. |
| P045 軌道整備ドッグ | この衛星が転回したとき、配備コストを支払ってプロジェクトカードを1枚この衛星に配備してもよい | When this satellite turns, you may pay deployment cost to deploy 1 Project card to this satellite. |
| P046 固定軌道装置 | この衛星は転回できない | This satellite cannot turn. |
| P047 トルネードビット | この衛星が転回したとき、特殊通信確立コスト支払って通信確立してもよい | When this satellite turns, you may pay the special link cost to establish a link. |
| P048 可変軌道装置 | この衛星が通信確立したとき、転回してもよい | When this satellite establishes a link, it may turn. |
| P049 太陽電池パネル | この衛星が通信確立するたび、[燃料]を1回復する | Each time this satellite establishes a link, recover 1 [Fuel]. |
| P050 欧阿中継ネットワーク | この衛星が地域[A][C][E]で通信確立したとき、追加で特殊通信確立コストを支払ってもよい。そうした場合、自分の地域[A][C][E]内にいる他の衛星を1機選び通信確立する | When this satellite establishes a link in region [A][C][E], you may additionally pay the special link cost. If you do, choose another of your satellites in region [A][C][E] and establish a link with it. |
| P051 米亜中継ネットワーク | この衛星が地域[B][D][F]で通信確立したとき、追加で特殊通信確立コストを支払ってもよい。そうした場合、自分の地域[B][D][F]内にいる他の衛星を1機選び通信確立する | When this satellite establishes a link in region [B][D][F], you may additionally pay the special link cost. If you do, choose another of your satellites in region [B][D][F] and establish a link with it. |
| P052 大陸アンテナ | この衛星は通信確立する際、隣接する[陸]区域も通信確立対象区域に加えられる | When this satellite establishes a link, adjacent [Land] areas are also added to the target areas. |
| P053 面舵アンテナ | この衛星は通信確立する際、進行方向に向かって右側の隣接する2区域も通信確立対象区域に加えられる | When this satellite establishes a link, the 2 adjacent areas on the right side relative to its heading are also added to the target areas. |
| P054 取舵アンテナ | この衛星は通信確立する際、進行方向に向かって左側の隣接する2区域も通信確立対象区域に加えられる | When this satellite establishes a link, the 2 adjacent areas on the left side relative to its heading are also added to the target areas. |
| P055 ステルス艦 | この衛星が、他プレイヤーが通信確立済みの区域を通信確立する際、他プレイヤーに先駆コストを支払わなくてよい | When this satellite establishes a link to an area that another player has already linked, you do not pay the pioneer cost to that player. |
| P056 前方広域送信機 | この衛星が通信確立したとき、追加で特殊通信確立コストを支払ってもよい。そうした場合、この衛星の進行方向前方の隣接する区域を追加で通信確立する | When this satellite establishes a link, you may additionally pay the special link cost. If you do, additionally establish a link with the adjacent area ahead of this satellite's heading. |
| P057 後方広域送信機 | この衛星が通信確立したとき、追加で特殊通信確立コストを支払ってもよい。そうした場合、この衛星の進行方向後方の隣接する区域を追加で通信確立する | When this satellite establishes a link, you may additionally pay the special link cost. If you do, additionally establish a link with the adjacent area behind this satellite's heading. |
| P058 真紅遠隔通信装置 | 自分の他の衛星が地域[C][E]で通信確立したとき、特殊通信確立コストを支払ってこの衛星も通信確立してもよい | When another of your satellites establishes a link in region [C][E], you may pay the special link cost to have this satellite establish a link too. |
| P059 瑠璃遠隔通信装置 | 自分の他の衛星が地域[D][F]で通信確立したとき、特殊通信確立コストを支払ってこの衛星も通信確立してもよい | When another of your satellites establishes a link in region [D][F], you may pay the special link cost to have this satellite establish a link too. |
| P060 北洋波形転送装置 | この衛星が地域[A][G]で通信確立したとき、特殊通信確立コストを支払ってもよい。そうした場合、隣接する[陸]区域を1つ選び追加で通信確立する | When this satellite establishes a link in region [A][G], you may pay the special link cost. If you do, choose 1 adjacent [Land] area and additionally establish a link there. |
| P061 南露波形転送装置 | この衛星が地域[B][H]で通信確立したとき、特殊通信確立コストを支払ってもよい。そうした場合、隣接する[陸]区域を1つ選び追加で通信確立する | When this satellite establishes a link in region [B][H], you may pay the special link cost. If you do, choose 1 adjacent [Land] area and additionally establish a link there. |
| P062 塵芥海洋アンテナ | この衛星が地域[E][G]で通信確立する際、隣接する[海]区域を通信確立対象区域に加える | When this satellite establishes a link in region [E][G], adjacent [Sea] areas are added to the target areas. |
| P063 智利海洋アンテナ | この衛星が地域[F][H]で通信確立する際、隣接する[海]区域を通信確立対象区域に加える | When this satellite establishes a link in region [F][H], adjacent [Sea] areas are added to the target areas. |
| P064 前方ビームアンテナ | この衛星が通信確立する際、進行方向に向かって前方の隣接する3区域を通信確立対象区域に加える | When this satellite establishes a link, the 3 adjacent areas ahead of its heading are added to the target areas. |
| P065 後方ビームアンテナ | この衛星が通信確立する際、進行方向に向かって後方の隣接する3区域を通信確立対象区域に加える | When this satellite establishes a link, the 3 adjacent areas behind its heading are added to the target areas. |
| P066 電波遮断装置 | この衛星は通信確立できない | This satellite cannot establish a link. |

#### 5.3 SOFTWARE（P067〜P099）

| 番号 | 名前 | 英語名案 | `name_id` | コスト | 名声 | 燃料庫 | タイミング | 効果タイプ | 稼働条件 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| P067 | チュリュモフ | Churyumov | `churyumov` | 3 | 2 | 2 | `PROPOSAL_PHASE` | `CONSTANT` | `ACTIVE_ONLY` |
| P068 | シャノンエントロピー | Shannon Entropy | `shannon_entropy` | 4 | 2 | 2 | `PROPOSAL_PHASE` | `CONSTANT` | `ACTIVE_ONLY` |
| P069 | ロスコスモス | Roscosmos | `roscosmos` | 6 | 2 | 2 | `PROPOSAL_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P070 | スピンオフ | Spin-off | `spin_off` | 6 | 2 | 2 | `PROPOSAL_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P071 | チャンドラセカール | Chandrasekhar | `chandrasekhar` | 6 | 2 | 2 | `PROPOSAL_PHASE` | `CONSTANT` | `ACTIVE_ONLY` |
| P072 | ランチェスター | Lanchester | `lanchester` | 6 | 2 | 3 | `PROPOSAL_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P073 | テスラメモリー | Tesla Memory | `tesla_memory` | 8 | 2 | 3 | `PROPOSAL_PHASE` | `CONSTANT` | `ACTIVE_ONLY` |
| P074 | ゼロデイデプロイ | Zero-Day Deploy | `zero_day_deploy` | 9 | 3 | 3 | `PROPOSAL_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P075 | ファラデー | Faraday | `faraday` | 10 | 1 | 3 | `PROPOSAL_PHASE` | `CONSTANT` | `ACTIVE_ONLY` |
| P076 | グラウンドリンク | Ground Link | `ground_link` | 10 | 3 | 3 | `PROPOSAL_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P077 | オライオンリワード | Orion Reward | `orion_reward` | 7 | 3 | 2 | `ANYTIME` | `CONSTANT` | `ACTIVE_ONLY` |
| P078 | アポロダウン | Apollo Down | `apollo_down` | 3 | 2 | 2 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P079 | ゼログラビティα | Zero Gravity Alpha | `zero_gravity_alpha` | 4 | 1 | 1 | `ORBIT_PHASE` | `CONSTANT` | `ACTIVE_ONLY` |
| P080 | ゼログラビティβ | Zero Gravity Beta | `zero_gravity_beta` | 4 | 1 | 1 | `ORBIT_PHASE` | `CONSTANT` | `ACTIVE_ONLY` |
| P081 | ホーマンブースター | Hohmann Booster | `hohmann_booster` | 4 | 2 | 2 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P082 | トライアドブースター | Triad Booster | `triad_booster` | 4 | 2 | 2 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P083 | アルベドリフィル | Albedo Refill | `albedo_refill` | 4 | 2 | 2 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P084 | ジオサーマルリフィル | Geothermal Refill | `geothermal_refill` | 4 | 2 | 2 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P085 | ケスラー回避システム | Kessler Avoidance System | `kessler_avoidance_system` | 4 | 2 | 2 | `ORBIT_PHASE` | `CONSTANT` | `ACTIVE_ONLY` |
| P086 | データダウンリンク | Data Downlink | `data_downlink` | 4 | 2 | 3 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P087 | データローリング | Data Rolling | `data_rolling` | 4 | 2 | 3 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P088 | ハイゲンスダウン | Huygens Down | `huygens_down` | 5 | 2 | 2 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P089 | ランディングダウン | Landing Down | `landing_down` | 5 | 2 | 2 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P090 | ギムバル制御 | Gimbal Control | `gimbal_control` | 5 | 2 | 2 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P091 | カスケードブースト | Cascade Boost | `cascade_boost` | 5 | 2 | 2 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P092 | スプラッシュダウン | Splashdown | `splashdown` | 6 | 2 | 2 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P093 | オービタル72 | Orbital 72 | `orbital_72` | 6 | 2 | 2 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P094 | デコンフリクト | Deconflict | `deconflict` | 6 | 2 | 2 | `ORBIT_PHASE` | `CONSTANT` | `ACTIVE_ONLY` |
| P095 | フリップマニューバ | Flip Maneuver | `flip_maneuver` | 7 | 2 | 2 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P096 | アルファリンク | Alpha Link | `alpha_link` | 7 | 2 | 3 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P097 | ベータリンク | Beta Link | `beta_link` | 7 | 2 | 3 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P098 | バックリンク | Back Link | `back_link` | 9 | 2 | 2 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |
| P099 | フライバイリンク | Flyby Link | `flyby_link` | 11 | 2 | 2 | `ORBIT_PHASE` | `ACTIVATED` | `ACTIVE_ONLY` |

| 番号 | 効果文（印刷） | 英語文案 |
| --- | --- | --- |
| P067 チュリュモフ | 企画フェイズでプロジェクトカードを引く枚数が1枚増える。ただし、手札に残す枚数は変わらない | In the Proposal Phase, the number of Project cards you draw increases by 1. However, the number you keep in hand does not change. |
| P068 シャノンエントロピー | 企画フェイズでプロジェクトカードを捨てる際、企画フェイズ中に引いたカード以外を捨てることができる | When discarding Project cards in the Proposal Phase, you may discard cards other than those drawn during the Proposal Phase. |
| P069 ロスコスモス | 企画フェイズで4[マヤ]支払ってもよい。そうした場合、+1[予算] | In the Proposal Phase, you may pay 4 [Maya]. If you do, +1 [Budget]. |
| P070 スピンオフ | 企画フェイズでプロジェクトカードを追加で1枚捨ててもよい。そうした場合、+7[マヤ] | In the Proposal Phase, you may additionally discard 1 Project card. If you do, +7 [Maya]. |
| P071 チャンドラセカール | 企画フェイズのアクション選択ボーナスにおいて、引く枚数・手札に残す枚数それぞれ1枚増える | For the Proposal Phase Action Selection Bonus, both the number drawn and the number kept in hand increase by 1. |
| P072 ランチェスター | 企画フェイズで4/7/10[マヤ]支払ってもよい。そうした場合、手札に残す枚数が1/2/3枚増える | In the Proposal Phase, you may pay 4/7/10 [Maya]. If you do, the number you keep in hand increases by 1/2/3. |
| P073 テスラメモリー | 企画フェイズで手札に残す枚数が1枚増える | In the Proposal Phase, the number you keep in hand increases by 1. |
| P074 ゼロデイデプロイ | 企画フェイズでプロジェクトカードを1枚捨ててもよい。そうした場合、プロジェクトカードを1枚選んで配備コストを支払って自分の好きな衛星に配備する | In the Proposal Phase, you may discard 1 Project card. If you do, choose 1 Project card, pay its deployment cost and deploy it to any of your satellites. |
| P075 ファラデー | 企画フェイズでプロジェクトカードを引いたとき、引いた枚数1枚につき+1[マヤ] | When you draw Project cards in the Proposal Phase, +1 [Maya] for each card drawn. |
| P076 グラウンドリンク | 企画フェイズでプロジェクトカードを2枚捨ててもよい。そうした場合、自分の衛星を1機選び、その衛星の隣接する[陸]区域のうち1箇所を通信確立コストを支払って、通信確立する。 | In the Proposal Phase, you may discard 2 Project cards. If you do, choose one of your satellites, pay the link cost, and establish a link with 1 of the [Land] areas adjacent to that satellite. |
| P077 オライオンリワード | いつでも自分はプロジェクトカードを捨てたときにもらえる[マヤ]+2 | At any time, the [Maya] you receive when you discard a Project card is +2. |
| P078 アポロダウン | 自分の衛星が着陸/着水したとき、プロジェクトカードを1枚引く | When any of your satellites lands or splashes down, draw 1 Project card. |
| P079 ゼログラビティα | この衛星に[CREW][DEVICE][SOFTWARE]が全て配備されていれば、この衛星は[A][H]上空で[燃料]を消費しない | If [CREW], [DEVICE] and [SOFTWARE] are all deployed to this satellite, this satellite does not spend [Fuel] while above region [A][H]. |
| P080 ゼログラビティβ | この衛星に[CREW][DEVICE][SOFTWARE]が全て配備されていれば、この衛星は[B][E]上空で[燃料]を消費しない | If [CREW], [DEVICE] and [SOFTWARE] are all deployed to this satellite, this satellite does not spend [Fuel] while above region [B][E]. |
| P081 ホーマンブースター | この衛星が自分の通信確立済み区域の上空に突入したとき、2[マヤ]支払ってもよい。そうした場合、この衛星を進行方向に1区域進め、[燃料]を1消費する。ただし、ニアミス判定は行われず、着陸/着水判定は行われる。 | When this satellite enters the airspace above an area you have linked, you may pay 2 [Maya]. If you do, move this satellite forward 1 area in its heading and spend 1 [Fuel]. However, no near-miss check is made, and the landing/splashdown check is made. |
| P082 トライアドブースター | 循環フェイズで、この衛星に[CREW][DEVICE][SOFTWARE]が配備されているならば、この衛星は1区域追加で循環してもよい。そうした場合、[燃料]を1消費し、移動した先の区域でニアミス判定は行われる | In the Orbit Phase, if [CREW], [DEVICE] and [SOFTWARE] are deployed to this satellite, it may orbit 1 additional area. If you do, spend 1 [Fuel]; the near-miss check is made in the area moved to. |
| P083 アルベドリフィル | この衛星が別地域から地域[A][C][H]の上空に突入したとき、[燃料]を2回復する。 | When this satellite enters the airspace above region [A][C][H] from a different region, recover 2 [Fuel]. |
| P084 ジオサーマルリフィル | この衛星が別地域から地域[D][F][H]の上空に突入したとき、[燃料]を2回復する。 | When this satellite enters the airspace above region [D][F][H] from a different region, recover 2 [Fuel]. |
| P085 ケスラー回避システム | この衛星は、他の衛星とニアミスしたとき消費する[燃料]は1少なくなる。すなわち、ニアミス時に[燃料]を1回復する | The [Fuel] this satellite spends when it near-misses another satellite is 1 less. That is, recover 1 [Fuel] on a near miss. |
| P086 データダウンリンク | この衛星が自分の通信確立済み区域の上空に突入したとき、3[マヤ]支払ってもよい。そうした場合、プロジェクトカードを1枚引く | When this satellite enters the airspace above an area you have linked, you may pay 3 [Maya]. If you do, draw 1 Project card. |
| P087 データローリング | 循環フェイズ開始時、プロジェクトカードを1枚引く。その後、手札からプロジェクトカードを1枚捨てる | At the start of the Orbit Phase, draw 1 Project card. Then discard 1 Project card from your hand. |
| P088 ハイゲンスダウン | 自分の衛星が着陸/着水したとき、+8[マヤ] | When any of your satellites lands or splashes down, +8 [Maya]. |
| P089 ランディングダウン | 自分の衛星が[陸]区域に着陸したとき、+15[マヤ] | When any of your satellites lands on a [Land] area, +15 [Maya]. |
| P090 ギムバル制御 | この衛星が地域間を移動したとき、転回してもよい | When this satellite moves between regions, it may turn. |
| P091 カスケードブースト | この衛星が地域間を移動したとき、この衛星を1区域追加で進めてもよい。そうした場合、[燃料]を1消費し、移動した先の区域でニアミス判定は行われる | When this satellite moves between regions, you may move it 1 additional area. If you do, spend 1 [Fuel]; the near-miss check is made in the area moved to. |
| P092 スプラッシュダウン | 自分の衛星が[海]区域に着水したとき、+10[マヤ] | When any of your satellites splashes down on a [Sea] area, +10 [Maya]. |
| P093 オービタル72 | この衛星が自分の通信確立済み区域の上空に突入したとき、+3[マヤ] | When this satellite enters the airspace above an area you have linked, +3 [Maya]. |
| P094 デコンフリクト | この衛星に[CREW][DEVICE][SOFTWARE]が配備されているならば、この衛星はニアミスしない。その区域にある他の衛星が1機以下であれば、その衛星もニアミスしたとみなされない | If [CREW], [DEVICE] and [SOFTWARE] are deployed to this satellite, this satellite does not near-miss. If there is 1 or fewer other satellite in that area, that satellite is also not considered to have near-missed. |
| P095 フリップマニューバ | この衛星が自分の通信確立済み区域の上空に突入したとき、転回してもよい | When this satellite enters the airspace above an area you have linked, it may turn. |
| P096 アルファリンク | この衛星が別地域から地域[A][C][F]の上空に突入したとき、特殊通信確立コストを支払ってもよい。そうした場合、この衛星は通信確立する | When this satellite enters the airspace above region [A][C][F] from a different region, you may pay the special link cost. If you do, this satellite establishes a link. |
| P097 ベータリンク | この衛星が別地域から地域[B][D][G]の上空に突入したとき、特殊通信確立コストを支払ってもよい。そうした場合、この衛星は通信確立する | When this satellite enters the airspace above region [B][D][G] from a different region, you may pay the special link cost. If you do, this satellite establishes a link. |
| P098 バックリンク | この衛星が自分の通信確立済み区域の上空に突入したとき、特殊通信確立コストを支払ってもよい。そうした場合、この衛星の進行方向後方の区域を通信確立する | When this satellite enters the airspace above an area you have linked, you may pay the special link cost. If you do, establish a link with the area behind this satellite's heading. |
| P099 フライバイリンク | この衛星が地域間を移動したとき、特殊通信確立コスト支払って通信確立してもよい | When this satellite moves between regions, you may pay the special link cost to establish a link. |

### 6. 衛星カード（40 面）

衛星カードは 1 枚の両面。**表面は全員共通の MOON プラン**（形状 5 種 × 色 4 色 = 20 枚。画像 `R001B` 〜 `R005Y`）、**裏面は色ごとのプラン**（PHOBOS / DEIMOS / EUROPA / GANYMEDE × 形状 5 種 = 20 枚。画像 `R006`〜`R025`）。ゲームオプション名は MOON / PLAYER_VARIABLE（作者回答）。1 人が使う 5 枚は、すべて MOON 面か、すべて色別プラン面に揃える（FAQ 行 64）。

共通事項（全 40 面）:

- 燃料庫の数は**すべて 2**（初期燃料庫 1 + 燃料庫 1）。初期燃料庫の四角は色付きで、色が衛星の色を示す。
- カード右下のシルエットが形状（HAMMER / RETRO / SCALLOP / CONCORDE / STEALTH）と色を表す。形状名は左右のブースターの文字（左がプラン名、右が形状名）。
- 打ち上げコスト・名声のフォントはプロジェクトカードと同じ。

#### 6.1 MOON 面（R001〜R005、色 B / G / R / Y）

色違いの 4 枚は、**画像の目視比較で効果・コスト・名声・アイコンがすべて同一**（枠線・初期燃料庫・シルエットの色だけが違う）。そのため 1 行で記す。

| 番号 | 形状 | `name_id` | 打ち上げコスト | 名声 | 燃料庫 | タイミング | 効果タイプ | 稼働条件 | 効果文（印刷） | 英語文案 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| R001B / G / R / Y | HAMMER | `hammer_moon` | 10 | 5 | 2 | `LAUNCHED` | `ON_LAUNCH` | `ANY` | 打ち上げ時、プロジェクトカードを1枚引く | When launched, draw 1 Project card. |
| R002B / G / R / Y | RETRO | `retro_moon` | 15 | 5 | 2 | `LAUNCHED` | `ON_LAUNCH` | `ANY` | 打ち上げ時、[予算]+2 | When launched, +2 [Budget]. |
| R003B / G / R / Y | SCALLOP | `scallop_moon` | 20 | 10 | 2 | `LAUNCHED` | `ON_LAUNCH` | `ANY` | 打ち上げ時、プロジェクトカードを2枚引く | When launched, draw 2 Project cards. |
| R004B / G / R / Y | CONCORDE | `concorde_moon` | 30 | 10 | 2 | `LAUNCHED` | `ON_LAUNCH` | `ANY` | 打ち上げ時、[予算]+4 | When launched, +4 [Budget]. |
| R005B / G / R / Y | STEALTH | `stealth_moon` | 30 | 15 | 2 | `LAUNCHED` | `ON_LAUNCH` | `ANY` | 打ち上げ時、手札からプロジェクトカードを2枚まで選んで配備コストを支払ってこの衛星に配備してもよい。配備したカードの配備時・打ち上げ時効果は発動する | When launched, you may choose up to 2 Project cards from your hand and pay their deployment costs to deploy them to this satellite. The "On Deploy" and "On Launch" effects of the deployed cards are triggered. |

色と画像の対応: `B` = 青（BLUE）、`G` = 緑（GREEN）、`R` = 赤（RED）、`Y` = 黄（YELLOW）。2 つのアイコンは「打ち上げ」と「打ち上げ時」で、稼働条件アイコン（◎）は無い。

**一次情報の注記**: R005 は `data/cards/rockets/R005*.png` の画像が旧版（印刷前）のため、サイト側の印刷版画像（`danjiri-concerto.github.io/images/project-edgeworth/cards/R005B@4x.png`、G / R / Y も同じ）を正とする（2026-10-19 確認）。印刷版の R005 は、打ち上げコスト 30・名声 15、効果文は上表のとおり（配備は2枚まで）で、下部のアイコン説明は「打ち上げ ⇒ 配備（パズルピース）× 2」。他のカード画像はサイト側と一致する。

#### 6.2 色別プランの面（R006〜R025）

プラン名と色の対応は画像で確認した（枠線・初期燃料庫・シルエットの色とプラン名が一致）: PHOBOS = 青、DEIMOS = 緑、EUROPA = 赤、GANYMEDE = 黄。番号は「プラン × 形状」の順（形状の並びは HAMMER, RETRO, SCALLOP, CONCORDE, STEALTH）。稼働条件はすべて `ACTIVE_ONLY`（◎あり）、燃料庫の数はすべて 2。

**表裏の対応**: 画像からは直接読めない（表面と裏面は別ファイル）。**同じ色・同じ形状が表裏**と推定する（例: HAMMER・青の MOON 面 `R001B` の裏が PHOBOS・HAMMER の `R006`）。確認が必要（未確定事項 No.4）。

| 番号 | プラン | 色 | 形状 | `name_id` | 打ち上げコスト | 名声 | タイミング | 効果タイプ | 効果文（印刷） | 英語文案 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |

| R006 | PHOBOS | BLUE | HAMMER | `hammer_phobos` | 15 | 5 | `LAUNCHED` | `CONSTANT` | 自分の全ての衛星の打ち上げコスト-3 | Launch cost of all your satellites -3. |
| R007 | PHOBOS | BLUE | RETRO | `retro_phobos` | 20 | 10 | `DEPLOYED` | `CONSTANT` | 自分の全ての衛星への配備コスト-1 | Deployment cost for all your satellites -1. |
| R008 | PHOBOS | BLUE | SCALLOP | `scallop_phobos` | 20 | 10 | `LINKED` | `CONSTANT` | 自分の全ての衛星の通信確立コスト-1 | Link cost of all your satellites -1. |
| R009 | PHOBOS | BLUE | CONCORDE | `concorde_phobos` | 20 | 10 | `ANYTIME` | `CONSTANT` | プロジェクトカードを捨てたときに得られる[マヤ]+2 | The [Maya] you receive when you discard a Project card is +2. |
| R010 | PHOBOS | BLUE | STEALTH | `stealth_phobos` | 30 | 10 | `BUDGET_PHASE` | `ACTIVATED` | 予算発行フェイズ開始時、[マヤ]+2 | At the start of the Budget Phase, +2 [Maya]. |
| R011 | DEIMOS | GREEN | HAMMER | `hammer_deimos` | 15 | 5 | `PROPOSAL_PHASE` | `ACTIVATED` | 企画のアクション選択ボーナスは「6枚引いて3枚を手札に残す」になる | The Proposal Action Selection Bonus becomes "draw 6 cards and keep 3 in hand". |
| R012 | DEIMOS | GREEN | RETRO | `retro_deimos` | 20 | 10 | `LAUNCHED` | `ACTIVATED` | 打ち上げのアクション選択ボーナスとして、[予算]+1 | As the Launch Action Selection Bonus, +1 [Budget]. |
| R013 | DEIMOS | GREEN | SCALLOP | `scallop_deimos` | 20 | 10 | `DEPLOYED` | `ACTIVATED` | 配備のアクション選択ボーナスとして、プロジェクトカードをもう1枚追加で引く | As the Deploy Action Selection Bonus, draw 1 additional Project card. |
| R014 | DEIMOS | GREEN | CONCORDE | `concorde_deimos` | 20 | 10 | `LINKED` | `ACTIVATED` | 通信確立のアクション選択ボーナスで選択できる区域は、進行方向上下に加え、隣接する全区域を含める | For the Establish Link Action Selection Bonus, the areas you can choose include all adjacent areas, in addition to the areas ahead of and behind the heading. |
| R015 | DEIMOS | GREEN | STEALTH | `stealth_deimos` | 30 | 10 | `BUDGET_PHASE` | `ACTIVATED` | 予算発行のアクション選択ボーナスで得られる[マヤ]+3 | The [Maya] gained from the Budget Action Selection Bonus is +3. |
| R016 | EUROPA | RED | HAMMER | `hammer_europa` | 15 | 5 | `PROPOSAL_PHASE` | `ACTIVATED` | この衛星に[CREW][DEVICE][SOFTWARE]が配備されているならば、3[マヤ]支払えば自分で企画アクションを選択していなくてもアクション選択ボーナスを得られる | If [CREW], [DEVICE] and [SOFTWARE] are deployed to this satellite, you can gain the Action Selection Bonus by paying 3 [Maya], even if you did not choose the Proposal action yourself. |
| R017 | EUROPA | RED | RETRO | `retro_europa` | 25 | 10 | `DEPLOYED` | `ACTIVATED` | この衛星に[CREW][DEVICE][SOFTWARE]が配備されているならば、3[マヤ]支払えば自分で配備アクションを選択していなくてもアクション選択ボーナスを得られる | If [CREW], [DEVICE] and [SOFTWARE] are deployed to this satellite, you can gain the Action Selection Bonus by paying 3 [Maya], even if you did not choose the Deploy action yourself. |
| R018 | EUROPA | RED | SCALLOP | `scallop_europa` | 30 | 10 | `LINKED` | `ACTIVATED` | この衛星に[CREW][DEVICE][SOFTWARE]が配備されているならば、3[マヤ]支払えば自分で通信確立アクションを選択していなくてもアクション選択ボーナスを得られる | If [CREW], [DEVICE] and [SOFTWARE] are deployed to this satellite, you can gain the Action Selection Bonus by paying 3 [Maya], even if you did not choose the Establish Link action yourself. |
| R019 | EUROPA | RED | CONCORDE | `concorde_europa` | 30 | 10 | `LAUNCHED_OR_TURNED` | `ACTIVATED` | この衛星に[CREW][DEVICE][SOFTWARE]が配備されているならば、3[マヤ]支払えば自分で打ち上げ/転回アクションを選択していなくてもアクション選択ボーナスを得られる | If [CREW], [DEVICE] and [SOFTWARE] are deployed to this satellite, you can gain the Action Selection Bonus by paying 3 [Maya], even if you did not choose the Launch/Turn action yourself. |
| R020 | EUROPA | RED | STEALTH | `stealth_europa` | 35 | 10 | `BUDGET_PHASE` | `ACTIVATED` | この衛星に[CREW][DEVICE][SOFTWARE]が配備されているならば、3[マヤ]支払えば自分で予算発行アクションを選択していなくてもアクション選択ボーナスを得られる | If [CREW], [DEVICE] and [SOFTWARE] are deployed to this satellite, you can gain the Action Selection Bonus by paying 3 [Maya], even if you did not choose the Budget action yourself. |
| R021 | GANYMEDE | YELLOW | HAMMER | `hammer_ganymede` | 15 | 5 | `LINKED` | `ACTIVATED` | この衛星が地域[A][C][E][G]の通信確立する際、通信確立コスト-2 | When this satellite establishes a link in region [A][C][E][G], link cost -2. |
| R022 | GANYMEDE | YELLOW | RETRO | `retro_ganymede` | 15 | 5 | `LINKED` | `ACTIVATED` | この衛星が地域[B][D][F][H]の通信確立する際、通信確立コスト-2 | When this satellite establishes a link in region [B][D][F][H], link cost -2. |
| R023 | GANYMEDE | YELLOW | SCALLOP | `scallop_ganymede` | 20 | 10 | `LINKED` | `ACTIVATED` | この衛星が[陸]区域の通信確立する際、通信確立コスト-3 | When this satellite establishes a link on a [Land] area, link cost -3. |
| R024 | GANYMEDE | YELLOW | CONCORDE | `concorde_ganymede` | 20 | 10 | `LINKED` | `ACTIVATED` | この衛星が[海]区域の通信確立する際、通信確立コスト-2 | When this satellite establishes a link on a [Sea] area, link cost -2. |
| R025 | GANYMEDE | YELLOW | STEALTH | `stealth_ganymede` | 30 | 15 | `LINKED` | `ACTIVATED` | この衛星が通信確立する際、通信確立コスト-2 | When this satellite establishes a link, link cost -2. |

備考（衛星カード）:

- R019 のタイミング欄は、打ち上げと転回の 2 つのアイコンを斜線で結合した 1 つのアイコン（`LAUNCHED_OR_TURNED`）。
- R009 のタイミング欄は印刷では空欄だったが、ALL アイコンの掲載漏れ（作者回答、2026-10-19）。作者が `data/cards/rockets/R009@4x.png` を ALL アイコン入りに差し替えた（PR #4）。
- R002 / R004 の「[予算]」は角の丸い四角のアイコンで印刷されている（円形の `[マヤ]` ではない）。R012・A003 の `[予算]` も同様。
- マスターとの差分は 12 節に一覧した（MOON 面の RETRO・SCALLOP・CONCORDE など）。

### 7. 褒賞カード（24 面）

褒賞カードは**両面 12 枚**（24 面）で、ゲーム開始時に全体で 2 枚選ばれる（ルールブック p9）。比較内容の値が大きいプレイヤーから順に 1 位〜4 位の名声を獲得する。順位の名声は全カード共通で **1st 18 / 2nd 12 / 3rd 6 / 4th 0**（画像で全 24 面を確認）。同順位が複数いるときは該当順位の名声を合計して人数で割る（小数点切り捨て）。比較内容が 0 のプレイヤーは名声を獲得できない。

#### 7.1 カードと目印

左下の目印: `★`（初めてのゲームで 1 枚ずつ使うことを推奨）、`★★`（同）、工具のような ✕ アイコン（地域ボード裏面のゲームでのみ使用）、無印（推奨なし）。

分類（`class`）は、ゲームオプション「Random (1: Cube, 1: Other)」（通信確立系の褒賞から 1 枚、それ以外から 1 枚）のための列で、マスター `king` シートの `type` 列を手がかりにした。`CUBE` = `type` が「通信確立」、`OTHER` = それ以外（プロジェクト・金・燃料）。**✕ の褒賞は Advanced（地域ボード裏面）のときだけ使う**（作者回答）。

| 番号 | 名前 | 英語名案 | `name_id` | 比較内容（印刷） | 英語文案 | 分類 | 目印 | 1st | 2nd | 3rd | 4th |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |

| T001 | 海上覇権 | Sea Hegemony | `sea_hegemony` | 海区域の通信確立数 | Number of linked [Sea] areas | `CUBE` | ★ `STAR1` | 18 | 12 | 6 | 0 |
| T002 | 大陸覇権 | Land Hegemony | `land_hegemony` | 陸区域の通信確立数 | Number of linked [Land] areas | `CUBE` | ★ `STAR1` | 18 | 12 | 6 | 0 |
| T003 | 独立覇権 | Independent Hegemony | `independent_hegemony` | 非連結な通信確立済み区域のまとまりの数 | Number of separate groups of linked areas | `CUBE` | — `NONE` | 18 | 12 | 6 | 0 |
| T004 | 連鎖覇権 | Chain Hegemony | `chain_hegemony` | 通信確立済み区域の最大連結数 | Largest number of connected linked areas | `CUBE` | — `NONE` | 18 | 12 | 6 | 0 |
| T005 | 欧阿太平覇権 | Euro-Africa Pacific Hegemony | `euro_africa_pacific_hegemony` | 地域[A][C][E][G]の通信確立数 | Number of links in regions [A][C][E][G] | `CUBE` | — `NONE` | 18 | 12 | 6 | 0 |
| T006 | 露亜南北覇権 | Russia-Asia North-South Hegemony | `russia_asia_north_south_hegemony` | 地域[B][D][F][H]の通信確立数 | Number of links in regions [B][D][F][H] | `CUBE` | — `NONE` | 18 | 12 | 6 | 0 |
| T007 | 虎視覇権 | Watchful Tiger Hegemony | `watchful_tiger_hegemony` | 2番目以降に通信確立済み区域数 | Number of linked areas that you were not the first to link | `CUBE` | — `NONE` | 18 | 12 | 6 | 0 |
| T008 | 要塞覇権 | Fortress Hegemony | `fortress_hegemony` | 拠点に隣接した区域の通信確立済み区域数 | Number of linked areas adjacent to a base | `CUBE` | — `NONE` | 18 | 12 | 6 | 0 |
| T009 | 連隊覇権 | Regiment Hegemony | `regiment_hegemony` | [CREW]のプロジェクトカードの配備数 | Number of deployed [CREW] Project cards | `OTHER` | ★★ `STAR2` | 18 | 12 | 6 | 0 |
| T010 | 駆動覇権 | Drive Hegemony | `drive_hegemony` | [DEVICE]のプロジェクトカードの配備数 | Number of deployed [DEVICE] Project cards | `OTHER` | ★★ `STAR2` | 18 | 12 | 6 | 0 |
| T011 | 電脳覇権 | Cyber Hegemony | `cyber_hegemony` | [SOFTWARE]のプロジェクトカードの配備数 | Number of deployed [SOFTWARE] Project cards | `OTHER` | ★★ `STAR2` | 18 | 12 | 6 | 0 |
| T012 | 軌道覇権 | Orbit Hegemony | `orbit_hegemony` | [CREW][DEVICE][SOFTWARE]のプロジェクトカードが配備されている衛星数 | Number of satellites with [CREW], [DEVICE] and [SOFTWARE] Project cards all deployed | `OTHER` | — `NONE` | 18 | 12 | 6 | 0 |
| T013 | 星脈覇権 | Star Vein Hegemony | `star_vein_hegemony` | 予算値 | Budget value | `OTHER` | — `NONE` | 18 | 12 | 6 | 0 |
| T014 | 摩耶覇権 | Maya Hegemony | `maya_hegemony` | 所持[マヤ]合計 | Total [Maya] held | `OTHER` | — `NONE` | 18 | 12 | 6 | 0 |
| T015 | 火焔覇権 | Flame Hegemony | `flame_hegemony` | 稼働中の衛星の残燃料の合計値 | Total remaining fuel of your active satellites | `OTHER` | — `NONE` | 18 | 12 | 6 | 0 |
| T016 | 業魔覇権 | Fiend Hegemony | `fiend_hegemony` | 特定の衛星の最大積載燃料 | Maximum loaded fuel of a single satellite | `OTHER` | — `NONE` | 18 | 12 | 6 | 0 |
| T017 | 平原覇権 | Plains Hegemony | `plains_hegemony` | 標準陸区域・森林区域の通信確立数 | Number of linked Standard Land and Forest areas | `CUBE` | ✕ `ADVANCED_ONLY` | 18 | 12 | 6 | 0 |
| T018 | 夕凪覇権 | Evening Calm Hegemony | `evening_calm_hegemony` | 標準海区域の通信確立数 | Number of linked Standard Sea areas | `CUBE` | ✕ `ADVANCED_ONLY` | 18 | 12 | 6 | 0 |
| T019 | 山麓覇権 | Foothills Hegemony | `foothills_hegemony` | 山岳区域に隣接した海区域の通信確立数 | Number of linked [Sea] areas adjacent to a Mountain area | `CUBE` | ✕ `ADVANCED_ONLY` | 18 | 12 | 6 | 0 |
| T020 | 氷霧覇権 | Ice Mist Hegemony | `ice_mist_hegemony` | 雪原区域に隣接した海区域の通信確立数 | Number of linked [Sea] areas adjacent to a Snowfield area | `CUBE` | ✕ `ADVANCED_ONLY` | 18 | 12 | 6 | 0 |
| T021 | 植栽覇権 | Planting Hegemony | `planting_hegemony` | 森林区域に隣接した海区域の通信確立数 | Number of linked [Sea] areas adjacent to a Forest area | `CUBE` | ✕ `ADVANCED_ONLY` | 18 | 12 | 6 | 0 |
| T022 | 神域覇権 | Sacred Realm Hegemony | `sacred_realm_hegemony` | 連続した通信確立済み区域における区域種数の最大 | Largest number of area types within a connected group of linked areas | `CUBE` | ✕ `ADVANCED_ONLY` | 18 | 12 | 6 | 0 |
| T023 | 深淵覇権 | Abyss Hegemony | `abyss_hegemony` | 深海区域に隣接した海区域の通信確立数 | Number of linked [Sea] areas adjacent to a Deep Sea area | `CUBE` | ✕ `ADVANCED_ONLY` | 18 | 12 | 6 | 0 |
| T024 | 常盤覇権 | Evergreen Hegemony | `evergreen_hegemony` | 岩礁区域に隣接した陸区域の通信確立数 | Number of linked [Land] areas adjacent to a Reef area | `CUBE` | ✕ `ADVANCED_ONLY` | 18 | 12 | 6 | 0 |

- 目印の内訳: ★ = T001・T002、★★ = T009・T010・T011、✕ = T017〜T024（8 面）、無印 = T003〜T008・T012〜T016。
- 分類の内訳: `CUBE` = T001〜T008 と T017〜T024（✕ は Advanced のみ）、`OTHER` = T009〜T016。✕ を除くと CUBE 8 面・OTHER 8 面で釣り合う。
- 英語名案は暫定（日本語の造語が多く、意味を保てない箇所がある）。確認が必要（作者確認事項 7、タスク一覧参照）。
- 比較内容の表現は印刷どおり（T007「2番目以降に通信確立済み区域数」、T008「拠点に隣接した区域の通信確立済み区域数」は FAQ 行 82・83 に裁定がある）。

#### 7.2 両面の対応

**表裏でどの T 番号が対になるかは画像から読めない**（各面は別ファイルで、裏面の絵柄も表と同じ構図）。ルールブックにも記述が無い。「12 枚の両面」であることだけが分かっている。確認が必要（未確定事項 No.5）。目印からは、✕ の 8 面（T017〜T024）が、✕ でない面（T001〜T016）と同じカードの表裏になる可能性があるが、推測にとどめる。

### 8. ミッションカード（10 枚）

各プレイヤーにゲーム開始時 2 枚ずつ配られる非公開の形状目標（ルールブック p8）。ゲームボード上で、条件の形どおりに自分の通信確立キューブ／発射拠点を配置していれば達成。**条件は回転可能で、反転は不可**。区域の種類は問わない（FAQ 行 89）。カードには 2 つの段階がある。

- **易しい段階**（薄い色の六角形のマス、`EASY`）のみ達成 → 下表「名声（易）」。
- **追加段階**（網掛けのマス、`EXTRA`）まで含めて達成 → 下表「名声（全）」。易しい段階の名声は追加されない（高い方だけ、FAQ 行 90）。

#### 8.1 座標系

- **アキシャル座標 (q, r)**、**pointy-top**（六角形の頂点が上下。画像で、六角形の高さが幅より大きいことと、同じ行の六角形が辺で左右に隣り合うことから判断した）。
- 原点 (0, 0) は、形状のマス（易しい段階・追加段階を合わせたもの）のうち**最も上の行の最も左**のマス。`q` は右へ増え、`r` は下の行へ増える。
- 隣接マスの差分は `(+1, 0)` 右、`(-1, 0)` 左、`(0, +1)` 右下、`(-1, +1)` 左下、`(0, -1)` 左上、`(+1, -1)` 右上。
- 座標は画像の色（薄い色 / 網掛け）を画素単位で解析して取得し、画像と目視で照合した。
- 回転は盤面側の座標系で処理する（本書では扱わない）。

#### 8.2 カード一覧

| 番号 | 名前 | `name_id` | 名声（易） | 名声（全） | 易しい段階のマス (q, r) | 追加段階のマス (q, r) |
| --- | --- | --- | --- | --- | --- | --- |

| M001 | LOOP | `loop` | 5 | 17 | (0, 0), (-1, 1), (-1, 2) | (1, 0), (1, 1), (0, 2) |
| M002 | BUMP | `bump` | 5 | 18 | (0, 0), (-1, 1), (0, 1) | (2, 0), (1, 1), (2, 1) |
| M003 | ARCH | `arch` | 5 | 18 | (0, 0), (0, 1), (-1, 2) | (1, 0), (1, 1), (0, 2) |
| M004 | PILE | `pile` | 5 | 18 | (-2, 2), (-1, 2), (0, 2) | (0, 0), (-1, 1), (0, 1) |
| M005 | RECOIL | `recoil` | 8 | 15 | (0, 0), (1, 0), (-1, 1), (1, 1) | (-2, 2), (1, 2) |
| M006 | WING | `wing` | 7 | 16 | (-2, 1), (-1, 1), (0, 1), (1, 1) | (0, 0), (-1, 2) |
| M007 | FISH | `fish` | 9 | 17 | (-2, 1), (-1, 1), (-3, 2), (-2, 2) | (0, 0), (0, 1) |
| M008 | EDGE | `edge` | 9 | 17 | (0, 0), (1, 0), (-1, 1), (0, 1) | (2, 0), (1, 1) |
| M009 | LOCK | `lock` | 5 | 24 | (-1, 1), (0, 1), (1, 1) | (0, 0), (1, 0), (-1, 2), (0, 2) |
| M010 | TURTLE | `turtle` | 5 | 24 | (-1, 1), (0, 1), (1, 1) | (0, 0), (1, 0), (-2, 2), (1, 2) |

- 名声（易）は易しい段階だけを達成したときの名声、名声（全）は追加段階まで達成したときの名声（カード下部の「六角形 : 名声」「六角形 + 網掛け六角形 : 名声」の表示）。
- ミッションカードに効果文は無い。名称は印刷の英字（ロゴ文字）。裏面は `MREV`（共通）。
- 形状の例（M001 LOOP）: 易しい 3 マスと追加 3 マスが、空白の 1 マス (0, 1) を囲む輪になる（ルールブック p8 の図と一致）。
- 検算: 易しい段階のマス数 + 追加段階のマス数 = 6（M001〜M008）、7（M009・M010 は 3 + 4）。

### 9. アクションカード（5 枚）

各プレイヤー 5 枚（ルールブック p5）。カード上部に「ローマ数字 / アクション名 / 対応するフェイズのアイコン」、中央に**メインアクション**、下部に **BONUS（アクション選択ボーナス）** が印刷されている。裏面は `AREV`（共通）。

| 番号 | ローマ数字 | 名前 | 英語名案 | `name_id` | 種類 |
| --- | --- | --- | --- | --- | --- |
| A001 | I | 予算発行 | Budget | `budget` | `BUDGET` |
| A002 | II | 配備 | Deploy | `deploy` | `DEPLOY` |
| A003 | III | 打ち上げ / 転回 | Launch / Turn | `launch_turn` | `LAUNCH_TURN` |
| A004 | IV | 通信確立 | Establish Link | `establish_link` | `LINK` |
| A005 | V | 企画 | Proposal | `proposal` | `PROPOSAL` |

#### 9.1 メインアクション

| 番号 | 文面（印刷） | 英語文案 |
| --- | --- | --- |
| A001 | [予算]だけ+[マヤ]。<br>このカードは複数ラウンド連続してプロットすることができる。 | Gain [Maya] equal to your [Budget].<br>This card can be plotted in consecutive rounds. |
| A002 | 自分の衛星を1機選び、配備コストを支払ってプロジェクトカードを配備する。稼働中の衛星に配備する場合、配備コスト+5。 | Choose 1 of your satellites, pay the deployment cost, and deploy a Project card. When deploying to an active satellite, deployment cost +5. |
| A003 | 以下のうちいずれかを選ぶ。<br>打ち上げ：非稼働の衛星を1機選ぶ。自分の発射基地から、方向を指定し衛星を打ち上げる。<br>転回：稼働中の衛星を1機選ぶ。その衛星の方向を転回する。 | Choose one of the following.<br>Launch: Choose 1 inactive satellite. Launch it from your launch base in a direction of your choice.<br>Turn: Choose 1 active satellite. Turn that satellite's direction. |
| A004 | 稼働中の衛星を1機選ぶ。通信確立コストを支払って、その衛星の直下のタイルを通信確立する。 | Choose 1 active satellite. Pay the link cost and establish a link with the tile directly below that satellite. |
| A005 | プロジェクトカードを2枚引き、そのうち1枚を手札に残し、1枚を捨てる。 | Draw 2 Project cards, keep 1 of them in hand, and discard 1. |

#### 9.2 BONUS（アクション選択ボーナス）

| 番号 | 文面（印刷） | 英語文案 |
| --- | --- | --- |
| A001 | +6[マヤ]。 | +6 [Maya]. |
| A002 | 以下のうち、いずれかを選ぶ。<br>i) 追加で1枚配備する。<br>ii) プロジェクトカードを1枚引く。 | Choose one of the following.<br>i) Deploy 1 additional card.<br>ii) Draw 1 Project card. |
| A003 | 以下のうちいずれかを選ぶ。<br>i) 稼働中の衛星を1機選び転回する。<br>ii) +1[予算]。 | Choose one of the following.<br>i) Choose 1 active satellite and turn it.<br>ii) +1 [Budget]. |
| A004 | 稼働中の衛星を1機選ぶ。通信確立コストを支払って、その衛星の直下または進行方向前後の隣接するタイルを通信確立する。 | Choose 1 active satellite. Pay the link cost and establish a link with the tile directly below it or an adjacent tile ahead of or behind its heading. |
| A005 | 上記の代わりに、プロジェクトカードを5枚引き、そのうち2枚を手札に残し、3枚を捨てる。 | Instead of the above, draw 5 Project cards, keep 2 of them in hand, and discard 3. |

- A001 のメインアクションの先頭のアイコンは `[予算]`（四角いコイン）、`+` の後ろは `[マヤ]`（丸いコイン）。
- A003 のメインアクションでは「発射基地」と印刷されている（用語集の「発射拠点」と表記が異なる。そのまま転記）。A004 は「タイル」と印刷（用語集の「区域」と同義と思われるが、そのまま転記）。
- 印刷のマスター（`action` シート）との差分は 12 節。

### 10. 英語文の用語集（案）

英語文案で使った用語の対応。BGA の翻訳（原文は英語）の用語統一に使う。確認は作者確認事項 7（アルファ申請まで）。

| 日本語 | 英語案 | 備考 |
| --- | --- | --- |
| マヤ | Maya | 資金。`[Maya]` |
| 予算 | Budget | |
| 名声 | Fame | 勝利点 |
| 燃料 / 燃料庫 / 初期燃料庫 | Fuel / fuel slot / initial fuel slot | |
| プロジェクトカード | Project card | |
| 衛星 | satellite | |
| 区域 / 地域 | area / region | |
| 配備 / 配備コスト | deploy / deployment cost | |
| 打ち上げ / 打ち上げコスト | launch / launch cost | |
| 転回 | turn | 衛星の向きを変える |
| 通信確立 | establish a link（名詞は link） | 通信確立コスト = link cost、先駆コスト = pioneer cost |
| 特殊通信確立コスト | special link cost | 特殊コスト（5 マヤ）＋通信確立コスト |
| 企画 | Proposal | 計画（Planning）と区別するため |
| 循環 | Orbit | フェイズ名は Orbit Phase |
| 予算発行 | Budget | フェイズ名は Budget Phase |
| 計画フェイズ | Planning Phase | |
| アクション選択ボーナス | Action Selection Bonus | |
| 稼働中 / 非稼働・待機中 | active / inactive（standby） | |
| 起動 / 常時 | activated / constant | 識別子は `ACTIVATED` / `CONSTANT` |
| ニアミス | near miss | |
| 着陸 / 着水 | land / splash down | |
| 発射拠点 | launch base | |
| 先駆コスト | pioneer cost | |

### 11. FAQ（カード個別の裁定）

`faq.xlsx` の `faq` シート（全 94 行）のうち、`main_card_ids` に挙がるカードごとの裁定。「行」は `faq` シートの行番号、「区分」は `status` 列（既存 / 修正 / 追加 / エラッタ）。`status` が「要確認」の行は無いため「未確定」の付く行は無い。回答の全文は `faq.xlsx` を参照（ここは要約）。`main_card_ids` が空の全般的な裁定（行 2・3・37〜51 など）は本書の対象外。

FAQ 上のカード ID は、P048 と P049 が印刷と逆だったため、2026-10-19 に `faq.xlsx` の行 14・19・29 の ID を印刷の番号に直した（未確定事項 No.1）。

| カード | 行 | 区分 | 質問 → 回答（要約） |
| --- | --- | --- | --- |
| A004 | 36 | 既存 | 通信確立のボーナスで、最上段で右上を向く衛星の「進行方向前方」に右下の区域は含まれない |
| M001 | 88 | 追加 | ミッションは回転して達成してよいが、反転はできない |
| M001 | 89 | 追加 | ミッションの形は区域の種類を問わない（キューブ/発射拠点が形どおりに配置されていればよい） |
| M001 | 90 | 追加 | 薄い色の部分と濃い色の部分の両方を達成した場合は、高い方（濃い色まで達成）の名声のみ獲得する |
| M001 | 91 | 追加 | 2枚のミッションを両方達成すれば両方の名声を獲得できる。ただし同じキューブ/発射拠点を複数のミッションに使えない |
| M001 | 92 | 追加 | LOOP の内側の空白区域の状態は問わない |
| M001 | 94 | 追加 | 他プレイヤーのキューブがある区域でも、自分のキューブがあれば何番目でも達成の対象になる |
| M001 | 95 | 追加 | ミッションカードはゲーム終了時の名声計算まで非公開 |
| M003 | 93 | 追加 | 同じミッションの形を2か所で達成しても、名声は1回のみ |
| P004 軌道修正士 | 4 | 既存 | 軌道修正士で1区域進めた先に別の衛星がいても燃料は消費しない（消費はニアミス判定のときのみ） |
| P006 予算折衝官 | 5 | 既存 | マヤを獲得した後に予算折衝官の効果は使えない（予算発行フェイズの始めに発動する効果） |
| P006 予算折衝官 | 6 | 既存 | 予算折衝官で予算を上げると、上昇後の予算に対応するマヤを獲得できる |
| P010 地上整備技師 | 7 | 既存 | ドッキング整備技師は稼働中のみ、地上整備技師は非稼働でも発動する。上位互換ではない |
| P010 地上整備技師 | 8 | 既存 | 常時/起動の効果は配備後すぐ発動する。同ラウンドの追加配備にも地上整備技師の-2が効く |
| P012 ドッキング整備技師 | 7 | 既存 | ドッキング整備技師は稼働中のみ、地上整備技師は非稼働でも発動する。上位互換ではない |
| P019 フリート指揮官 | 53 | 追加 | フリート指揮官の-2は効果タイプが「起動」のため各ラウンド1回のみ |
| P022 ジャグラー | 9 | 既存 | ジャグラーによる付け替えでは、効果タイプが「配備時」「起動」のカードのいずれも配備時の効果は発動しない |
| P022 ジャグラー | 10 | 既存 | 「待機中の衛星」は非稼働の衛星のこと |
| P023 統合プランナー | 11 | 既存 | 統合プランナー自身の配備では発動しない（配備時に起動する効果は自身の配備では発動しない） |
| P024 スワップ技師 | 12 | 既存 | 入れ替え後に最大積載燃料を超えても即着陸せず、循環フェイズの着陸/着水判定で着陸する |
| P024 スワップ技師 | 13 | 修正 | 燃料庫からはみ出した燃料コマは、新たな配備でも回復効果でも戻らない。循環フェイズの着陸/着水判定で着陸する |
| P024 スワップ技師 | 14 | 修正 | 燃料庫からはみ出した燃料コマは、アルベドリフィル等の回復で残燃料が増えることはない |
| P027 人事プランナー | 52 | 追加 | 人事プランナーなど効果タイプ「起動」は、同ラウンドに条件を満たしても1回まで（CREW 2枚配備でも引くのは1枚） |
| P033 第七王子 | 55 | 追加 | 第七王子の衛星には、アクションでもカード効果でもプロジェクトカードを配備できない |
| P034 加速ブースター | 15 | 既存 | 燃料コマを燃料庫の外に出すところまでしか進めない。燃料を3消費した時点で庫外に出るため、この例では3区域分まで |
| P034 加速ブースター | 59 | 追加 | 加速ブースターで燃料コマが庫外に出ても、その時点では着陸/着水しない（循環フェイズの着陸/着水判定で判定） |
| P035 ギャンブルシステム | 16 | 修正 | ギャンブルシステムで配備したカードにも、自分の他のカードの「配備したとき」効果（人材育成官）は発動する。配備した人事プランナー自身の効果は発動しない |
| P037 姿勢制御装置 | 17 | 修正 | 向きを変えない360度転回でも、転回時の効果（姿勢制御装置）は発動できる |
| P037 姿勢制御装置 | 29 | 修正 | 企画フェイズ中の効果（グラウンドリンク→可変軌道装置→姿勢制御装置）で引いたカードには、チュリュモフの+1枚は適用されない（メインアクション/ボーナスで引く枚数のみ）。注: FAQ 上の「可変軌道装置」は P049 だが印刷では P048 |
| P038 燃料交換管 | 18 | 修正 | 「搭載燃料」は、打ち上げ時点での燃料庫の数（衛星カードと配備カードの燃料庫の合計） |
| P038 燃料交換管 | 19 | 修正 | 「初期燃料庫を超えて回復できない」は、衛星カードの初期燃料庫より下に燃料コマを動かせない（満タン以上に回復できない）という意味 |
| P045 軌道整備ドッグ | 20 | 既存 | 軌道整備ドッグで配備するときも、稼働中の衛星への追加配備コスト（+稼働コスト）を支払う |
| P046 固定軌道装置 | 54 | 追加 | 固定軌道装置の衛星も盤面の上下端では反射する。反射は転回ではないので「転回したとき」の効果は発動しない |
| P048 可変軌道装置 | 29 | 修正 | 企画フェイズ中の効果（グラウンドリンク→可変軌道装置→姿勢制御装置）で引いたカードには、チュリュモフの+1枚は適用されない（メインアクション/ボーナスで引く枚数のみ）。注: FAQ 上の「可変軌道装置」は P049 だが印刷では P048 |
| P050 欧阿中継ネットワーク | 21 | 既存 | 効果の対象の他の衛星と、この衛星は同一地域にいる必要はない |
| P050 欧阿中継ネットワーク | 22 | 修正 | 「地域A,C,Eで通信確立したとき」は通信確立した区域がその地域内にあることを指す（衛星のいる地域ではない） |
| P050 欧阿中継ネットワーク | 23 | 既存 | 対象の他の衛星は基本的に直下の区域のみ。通信確立対象区域を広げる効果を持てばそれらも対象 |
| P052 大陸アンテナ | 24 | 既存 | 通信確立対象区域の拡大は通信確立アクション以外（カード効果による特殊通信確立など）でも常に適用される |
| P052 大陸アンテナ | 25 | 修正 | 後方広域送信機で指定した区域を通信確立する際も、大陸アンテナ等の対象区域拡大は適用される |
| P053 面舵アンテナ | 26 | 既存 | 最上段で左上を向く場合、面舵アンテナの右側隣接は右隣の区域のみ（反射を考慮して左隣を右側と捉えない） |
| P055 ステルス艦 | 57 | 追加 | ステルス艦で不要になるのは他プレイヤーへの先駆コストのみ（区域コストは必要） |
| P056 前方広域送信機 | 27 | 修正 | 最上段で右上を向く場合、前方広域送信機の「前方」に右下の区域は含まれない（反射を考慮しない） |
| P057 後方広域送信機 | 25 | 修正 | 後方広域送信機で指定した区域を通信確立する際も、大陸アンテナ等の対象区域拡大は適用される |
| P066 電波遮断装置 | 56 | 追加 | 電波遮断装置の衛星は、カード効果による通信確立の対象にも選べない |
| P067 チュリュモフ | 29 | 修正 | 企画フェイズ中の効果（グラウンドリンク→可変軌道装置→姿勢制御装置）で引いたカードには、チュリュモフの+1枚は適用されない（メインアクション/ボーナスで引く枚数のみ）。注: FAQ 上の「可変軌道装置」は P049 だが印刷では P048 |
| P070 スピンオフ | 28 | 既存 | スピンオフで捨てるカードは企画フェイズ中に引いたカードである必要はない（手札の1枚でよい） |
| P076 グラウンドリンク | 29 | 修正 | 企画フェイズ中の効果（グラウンドリンク→可変軌道装置→姿勢制御装置）で引いたカードには、チュリュモフの+1枚は適用されない（メインアクション/ボーナスで引く枚数のみ）。注: FAQ 上の「可変軌道装置」は P049 だが印刷では P048 |
| P076 グラウンドリンク | 30 | 修正 | グラウンドリンクの通信確立では区域コスト＋先駆コスト（通常の通信確立コスト）を支払う。特殊コスト（5マヤ）は不要 |
| P077 オライオンリワード | 31 | 修正 | オライオンリワードの+2は、企画アクションで捨てたカードには適用されない（カードを3マヤと交換するルールのみ） |
| P077 オライオンリワード | 60 | 追加 | オライオンリワードと CONCORDE（PHOBOS）を両方持つ場合、カードを捨てて7マヤ（3+2+2） |
| P078 アポロダウン | 32 | 既存 | アポロダウンはこの衛星自身の着陸/着水でも発動する（循環フェイズ終了までは稼働中のみの効果が有効） |
| P079 ゼログラビティα | 33 | 既存 | 循環フェイズは移動の後に燃料消費を行うため、燃料消費時点で直下にある地域が参照される |
| P081 ホーマンブースター | 34 | 修正 | ホーマンブースターで進めた場合、ニアミスは進めた後の区域のみで通常どおり判定される（進める際の判定はない） |
| P085 ケスラー回避システム | 35 | 既存 | ケスラー回避システムは、ニアミス時の消費が1になるという意味。移動分の燃料消費は別途あり、合計2 |
| P094 デコンフリクト | 58 | 追加 | デコンフリクトの衛星はニアミスしない。同じ区域の他の衛星が1機だけならその衛星もニアミスしない。3機いる場合、残り2機はニアミスする |
| P098 バックリンク | 63 | エラッタ | バックリンクのタイミングアイコンは誤り。正しくは循環フェイズ中（効果文どおり） |
| R001B（MOON 全般） | 65 | 追加 | 衛星カードの名声は、ゲーム終了時にその衛星が稼働中のときのみ獲得できる |
| R002B | 68 | 追加 | 着陸/着水後に再び打ち上げた場合、MOON の「打ち上げ時」効果は再度発動する |
| R005B | 69 | 追加 | STEALTH（MOON）の打ち上げ時の配備には稼働中の追加配備コスト(+5)は不要。手札から最大2枚まで配備でき、配備したカードの配備時・打ち上げ時効果は発動する（印刷版と一致） |
| R006 | 66 | 追加 | ◉マークのある衛星カード効果は稼働中のみ発動する。色別プランの効果はすべて◉付き |
| R006 | 67 | 追加 | HAMMER（PHOBOS）の打ち上げコスト-3は、HAMMER自身の打ち上げには適用されない（非稼働のため） |
| R009 | 70 | 追加 | CONCORDE（PHOBOS）の+2は、カードを捨てて3マヤと交換するルールのみに適用（企画で捨てる場合は対象外） |
| R011 | 61 | 追加 | HAMMER（DEIMOS）とチャンドラセカールを両方持つ場合、企画のボーナスは7枚引いて4枚残す |
| R011 | 71 | 追加 | HAMMER（DEIMOS）は、自分が企画アクションを選択してボーナスを得るときのみ適用される |
| R014 | 72 | 追加 | CONCORDE（DEIMOS）は、直下の区域に加え、直下に隣接する6区域すべてが通信確立ボーナスの対象になる |
| R016 | 62 | 追加 | EUROPA の効果でも、誰も選択していないアクションのボーナスは得られない（そのフェイズが実行されないため） |
| R021 | 73 | 追加 | HAMMER（GANYMEDE）の「地域A,C,E,Gの通信確立」は、通信確立する区域の地域を指す（衛星のいる地域ではない） |
| R025 | 74 | 追加 | GANYMEDE の通信確立コスト削減は特殊通信確立コストにも効く（コストは0未満にならない） |
| T001 | 75 | 追加 | 褒賞カードは全体で2枚選ぶ。★/★★は初回プレイで各1枚を推奨、工具（✕）マークは地域ボード裏面のゲームのみ |
| T001 | 76 | 追加 | 同順位が複数いるときは、該当順位の名声を合計して人数で割る（小数点切り捨て） |
| T001 | 77 | 追加 | 比較内容が0のプレイヤーは名声を獲得できない |
| T001 | 78 | 追加 | 2〜3人プレイでも順位どおり（3人なら3位が6、2人なら2位が12）。比較内容0を除く |
| T002 | 79 | 追加 | 大陸覇権の陸区域の通信確立数に、自分の発射拠点区域も含む |
| T003 | 81 | 追加 | 独立覇権では、孤立した1区域もまとまり1つと数える |
| T004 | 80 | 追加 | 連鎖覇権・独立覇権で区域のつながりを数えるとき、ボードの左右端はつながっているとみなす |
| T007 | 82 | 追加 | 虎視覇権は、自分のキューブが区域の1番目（左上）以外に置かれている区域を数える。同時に通信確立して1番目に重ねた区域は数えない |
| T008 | 83 | 追加 | 要塞覇権は、自分・他プレイヤーを問わずいずれかの発射拠点に隣接する区域のうち、自分が通信確立済みの数（自分の発射拠点区域は数えない） |
| T009 | 84 | 追加 | 配備数を比べる褒賞では、非稼働の衛星に配備されたカードも数える |
| T015 | 85 | 追加 | 火焔覇権で燃料コマが庫外にある衛星の残燃料は0として数える |
| T016 | 86 | 追加 | 業魔覇権は、自分の衛星のうち燃料庫の数が最大の1機の値を比べる。非稼働の衛星も対象 |
| T019 | 87 | 追加 | 山麓覇権等は、隣接元の区域（山岳など）自体を通信確立している必要はない。複数に隣接する海区域も1回だけ数える |

### 12. マスターと印刷物の差分

印刷物を正とした差分の一覧（マスター → 印刷）。カード番号はマスターの並び順（`convert_edgeworth_faq.py` の `build_cards` と同じ、`project` シートの `exclude` 行を除いた行番号）で数えた。

#### 12.1 番号・名前・面の対応

| 対象 | マスター | 印刷 |
| --- | --- | --- |
| P048 / P049 | P048 = 太陽電池パネル（コスト 4）、P049 = 可変軌道装置 | P048 = 可変軌道装置、P049 = 太陽電池パネル（コスト 6）。名前・効果・コスト・燃料庫がカードごと入れ替わっている。FAQ の ID もマスター側の番号 |
| P056 / P057 | P056 の行は「後方広域送信機」の効果（名声 2）、P057 の行は「前方広域送信機」の効果（名声 3）。`convert_edgeworth_faq.py` は名前だけを上書き | P056 = 前方広域送信機、P057 = 後方広域送信機（名声はどちらも 2） |

#### 12.2 数値・条件

| 項目 | カード | マスター → 印刷 |
| --- | --- | --- |
| 配備コスト | P005 | 4 → 5 |
| | P008 | 11 → 10 |
| | P013 | 6 → 7 |
| | P030 / P031 / P032 | 14 → 12 |
| | P041 | 13 → 12 |
| | P043 | 4 → 5 |
| | P049（印刷の番号。太陽電池パネル） | 4 → 6 |
| | P064 | 9 → 10 |
| | P073 | 9 → 8 |
| | P074 | 10 → 9 |
| | P098 | 8 → 9 |
| 名声 | P041 | 0 → 1 |
| | 前方広域送信機（印刷の P056） | 3 → 2（マスターでは P057 の行。12.1 参照） |
| | P064 | 3 → 2 |
| | P074 | 2 → 3 |
| 燃料庫の数 | P007 | 2 → 4 |
| | P034 | 3 → 2 |
| | P041 | 2 → 3 |
| | P050 / P051 | 3 → 2 |
| | P074 | 2 → 3 |
| | P091 | 3 → 2 |
| 稼働条件 | P035 | 稼働中のみ → なし（◎なし） |
| | P055 | なし → 稼働中のみ（◎あり） |
| 効果タイプ | P001 / P002 | 常時 → 起動 |
| | P079 / P080 | 起動(特定地域上空) → 常時 |
| 効果発動タイミング | P033・P043〜P047・P056・P057・P062〜P066・P077 | マスターの `phase` は 7（常時）。印刷ではそれぞれ配備 / 転回 / 通信確立 / 通信確立 / 「ALL」のアイコンが付く |
| | P098 | 印刷は企画フェイズ（巻物）だがエラッタで循環フェイズ中が正（マスターも循環） |
| 衛星 MOON 面 | R002（RETRO） | 打ち上げコスト 20 → 15 |
| | R005（STEALTH） | マスター `moon` シート: 配備は「1枚」→ 印刷版: 「2枚まで」、「配備したカードの配備時・打ち上げ時効果は発動する」を追記（`satellite` シートの「最大2枚」とは一致。`data/` の画像は旧版で「1枚」） |
| | R003（SCALLOP） | 打ち上げ時、プロジェクトカードを3枚引く → 2枚引く |
| | R004（CONCORDE） | 打ち上げ時、予算+3 → +4 |

マスター側の `project` シートの `name_old` / `name2` など旧名の列は、印刷との突き合わせに使っていない。

#### 12.3 効果文

| カード | マスター | 印刷 |
| --- | --- | --- |
| P001 | アクション選択フェイズの「アクションカードの回収」において…手札に戻す | 「計画フェイズの…」に変更し、末尾に「すなわち、「II 配備」「III 打ち上げ/転回」を複数ラウンド連続してアクション選択できる」を追記 |
| P002 | 同上（「IV 通信確立」「V 企画」） | 同上（「すなわち、「IV 通信確立」「V 企画」を複数ラウンド連続してアクション選択できる」を追記） |
| P022 | 自分の**非稼働の**衛星の…発動しない。 | 自分の**待機中の**衛星の…発動しない（句点なし）。FAQ 行 10 で待機中 = 非稼働 |
| P026 | この**衛星を**配備するとき… | この**カードを**配備するとき… |
| P034 | …この衛星は1/2/3/4区域分**即座に進行し、該当する燃料を消費する**。その際に通過した区域のニアミス判定は行われない。 | …この衛星を1/2/3/4区域分**進め、1/2/3/4[燃料]を消費する**。その際に通過した**各**区域のニアミス判定は行われず、**着陸/着水判定は行われる** |
| P037 | 転回したとき、プロジェクトカードを1枚引く | **この衛星が**転回したとき… |
| P038 | …燃料を**消費**してもよい。…燃料を消費した分だけ…。ただし、**搭載燃料**を超えて回復することはできない | …[燃料]を**減らして**もよい。…[燃料]を減らした分だけ…。ただし、**初期燃料庫**を超えて回復することはできない |
| P042 | …[Device]のカードの枚数**だけ[予算]上昇** | …[DEVICE]のカードの枚数**1枚につき[予算]+1** |
| P047 | 特殊通信確立コスト**を**支払って… | 特殊通信確立コスト支払って…（「を」なし） |
| P050 / P051 | この衛星が地域A,C,E（B,D,F）**上空にいるときに**通信確立したとき | この衛星が地域[A][C][E]（[B][D][F]）**で**通信確立したとき（FAQ 行 22 で「通信確立した区域がその地域内」と裁定） |
| P055 | この衛星が**通信確立する際、**他プレイヤーが通信確立済みの区域を通信確立する際、他プレイヤーに**先駆者への通信確立コスト**を支払わない | この衛星が、他プレイヤーが通信確立済みの区域を通信確立する際、他プレイヤーに**先駆コスト**を支払わなくてよい |
| P058 / P059 | 自分の他の衛星が地域C,E（D,F）**上空にいるときに**通信確立したとき | 自分の他の衛星が地域[C][E]（[D][F]）**で**通信確立したとき |
| P067 | **企画アクションにおいて**… | **企画フェイズで**… |
| P068 | **企画アクションで**… | **企画フェイズで**… |
| P070 | 企画フェイズでプロジェクトカードを1枚捨ててもよい。 | 企画フェイズでプロジェクトカードを**追加で**1枚捨ててもよい。 |
| P072 | **企画アクション**で… | **企画フェイズ**で… |
| P073 | **企画アクションにおいて**… | **企画フェイズで**… |
| P075 | 企画アクションでプロジェクトカードを引いた枚数1枚につき+1 | 企画フェイズでプロジェクトカードを引いた**とき、**引いた枚数1枚につき+1 |
| P076 | …隣接する陸区域のうち1箇所を通信確立する。先駆者への通信確立コストは支払う必要がある。 | …隣接する[陸]区域のうち1箇所を**通信確立コストを支払って、**通信確立する。 |
| P077 | いつでも自分はプロジェクトカードを[マネー]に**変換する際に獲得できる**[マネー]+2 | いつでも自分はプロジェクトカードを**捨てたときにもらえる**[マヤ]+2 |
| P081 | …[マネー]…燃料を1消費する | …[マヤ]…[燃料]を1消費する。**ただし、ニアミス判定は行われず、着陸/着水判定は行われる。**（追記） |
| P082 | **この衛星に**[Crew],[Device],[Software]が配備されているならば、**循環フェイズで**この衛星は1区域追加で**移動**してもよい | **循環フェイズで、この衛星に**[CREW][DEVICE][SOFTWARE]が配備されているならば、この衛星は1区域追加で**循環**してもよい |
| P085 | …消費する燃料は**1で済む** | …消費する[燃料]は**1少なくなる。すなわち、ニアミス時に[燃料]を1回復する** |
| P091 | …この衛星は1区域追加で**循環**してもよい。そうした場合、移動した先の区域で… | …この衛星を1区域追加で**進め**てもよい。そうした場合、**[燃料]を1消費し、**移動した先の区域で… |
| P093 | …+**2**[マネー] | …+**3**[マヤ] |
| T012 | Software, Crew, Deviceタイプのプロジェクトカードの配備数の最小値 | [CREW][DEVICE][SOFTWARE]のプロジェクトカードが配備されている衛星数（未確定事項 No.7） |
| A004 | （`action` シート）通信確立(陸5マネー、海2マネー)／ボーナス: 追加通信確立（追加の通信確立は進行方向前後も可能） | メインアクション: 「通信確立コストを支払って…」（金額の記載なし）／ボーナス: 稼働中の衛星を1機選び、直下**または**進行方向前後の隣接するタイルを通信確立（「追加」ではない） |
| A005 | 2ドロー1捨て／代わりに5ドロー3捨て | 2枚引き1枚残し1枚捨てる／代わりに5枚引き**2枚を手札に残し**3枚を捨てる |

- 上記以外のプロジェクトカード（名前・コスト・名声・燃料庫・稼働条件・効果タイプ・効果文）は、マスターと印刷が一致した。マスターの `[マネー]` は印刷では `[マヤ]`（P034 ほか多数）または `[予算]`、`[Crew]` などは `[CREW]` などの大文字表記。
- 衛星カードの色別プラン面（R006〜R025）は、マスターの `blue` / `green` / `red` / `yellow` シートと全項目一致した（`satellite` シートは旧版で、コストが一部異なる。使っていない）。
- 褒賞カードは名前・分類・目印（`initial` / `rev` 列）がマスターの `king` シートと一致した（順位の名声 18/12/6/0 も一致）。
- ミッションカードの名称（LOOP, BUMP, ARCH, PILE, RECOIL, WING, FISH, EDGE, LOCK, TURTLE）は `convert_edgeworth_faq.py` の `MISSIONS` と一致した。マスターに形状・名声のデータは無く、本書の座標・名声は画像から取得した。

## エッジケース

- **P048 / P049 の番号**: 印刷の番号を正とする（確定、未確定事項 No.1）。`faq` シートの ID は修正済み。`project` シートの行順（と、それに依存する公開 FAQ ページのカード名）はマスター側の順のまま。
- **P098 のタイミング**: 印刷（企画フェイズ中）とエラッタ（循環フェイズ中）が違う。エラッタの値を採用する。
- **効果発動タイミングと効果タイプの組合せ**: P009・P022・P024・P026 は、タイミング（配備されたとき）と効果タイプ（配備時）がどちらもパズルピースのアイコン。P033 は、タイミングが配備、効果タイプが常時（プロジェクトカードを配備できないという制約の効果）。
- **稼働条件なしの常時・起動**: 非稼働の衛星に配備されていても発動する（例: P010 は非稼働でも発動、P012 は稼働中のみ。FAQ 行 7）。
- **衛星カードの効果が全員同じか**: MOON 面は色が違っても効果は同一。色別プランは色ごとに効果が違う（未確定事項 No.4 の対応が決まるまで、MOON 面と色別プラン面を実装上は独立した定義にする）。
- **燃料庫の数**: 衛星カードは常に 2（初期燃料庫 + 1）。配備したプロジェクトカードの燃料庫が加算される。燃料コマの庫外へのはみ出しは FAQ 行 12〜15・19 に裁定がある（処理順は T-007）。
- **褒賞の ✕ カード**: 地域ボード裏面（Advanced）のゲームでのみ候補に入れる。
- **ミッションの座標**: 回転は許容、反転は不可。原点は形状の最上行の最も左（8.1 節）。回転後の再正規化は実装側。

## 未確定事項

画像・ルールブック・FAQ から判断できなかった点。推測で確定させず、作者への質問として列挙する。

| No. | 質問 | 暫定案 | 回答 |
| --- | --- | --- | --- |
| 1 | 印刷では P048 = 可変軌道装置、P049 = 太陽電池パネルだが、`faq.xlsx` の `project` シートと `faq` シートの ID、`faq.json` の `cards` はこれが逆になっている（FAQ 行 14・19 の P048 は太陽電池パネルの意味、行 29 の P049 は可変軌道装置の意味）。どちらの番号を正としてよいか。`faq.xlsx` の ID を直すか。 | 印刷の番号を正とする。`faq.xlsx` の行 14・19・29 の ID の修正は作者に確認する | 印刷の番号を正とする。`faq.xlsx` の `faq` シート行 14・19・29 の ID を修正済み（変換スクリプトの名前の割り当ては直さない）（2026-10-19） |
| 2 | P077「オライオンリワード」の効果発動タイミング欄に、凡例（ルールブック p6・p27）にない「ALL」アイコンがある。「いつでも発動」の意味でよいか。 | `ANYTIME`（いつでも）。効果タイプは常時、稼働条件は稼働中のみ（◎あり） | 暫定案のとおり（2026-10-19） |
| 3 | R009（衛星 CONCORDE・PHOBOS）の効果発動タイミング欄が空欄。P077 と同じ「ALL」の脱落か、意図的な空欄か。 | `NONE`（空欄）。効果は P077 と同じ趣旨でカードを捨てるときの+2 | ALL アイコンの掲載漏れ。画像を差し替えた（`ANYTIME`）（2026-10-19） |
| 4 | 衛星カードの表裏の対応。MOON 面の 4 色（`R001B` など）の裏が、色別プランのどの面か（画像では読めない）。 | 同じ色・同じ形状が表裏（B→PHOBOS、G→DEIMOS、R→EUROPA、Y→GANYMEDE。例: `R001B` の裏が `R006`） | 暫定案のとおり（2026-10-19） |
| 5 | 褒賞カードの表裏の対応。T001〜T024 のどの 2 面が 1 枚の両面か（画像・ルールブックからは読めない）。 | 24 面を独立した褒賞として扱い、両面の対応は定義しない（同じカードの両面が同時に選ばれない制約が必要なら回答を受けて追加） | BGA 上では表裏の関係を気にしなくてよい（24 面を独立に扱う）（2026-10-19） |
| 6 | ゲームオプション「Random (1: Cube, 1: Other)」の分類。通信確立系（CUBE）= T001〜T008（Advanced では T017〜T024 も）、それ以外（OTHER）= T009〜T016 でよいか（マスター `king` シートの `type` 列に従った分類）。 | その分類を採用 | 暫定案のとおり（2026-10-19） |
| 7 | T012「軌道覇権」の比較内容。印刷は「[CREW][DEVICE][SOFTWARE]のプロジェクトカードが配備されている衛星数」、マスターは「3 タイプの配備数の最小値」。 | 3 タイプすべてが配備されている衛星の数 | 印刷どおり（2026-10-19） |
| 8 | アクションカード A003 は「発射基地」、A004 は「タイル」と印刷。用語集の「発射拠点」「区域」と同じ意味でよいか。 | 同義（英語文は launch base / area と訳す） | 暫定案のとおり（2026-10-19） |

## 受け入れ条件

- [ ] プロジェクトカード 99 枚（P001〜P099）が、番号・名前・英語識別子・タイプ・配備コスト・名声・燃料庫・タイミング・効果タイプ・稼働条件・効果文・英語文案とともにすべて載っている。
- [ ] 衛星カード 40 面（MOON 面 R001〜R005 × 4 色、色別プラン R006〜R025）が載り、色違いの MOON 面は同一効果であることが明記されている。
- [ ] 褒賞カード 24 面（T001〜T024）の比較内容・順位ごとの名声・目印・分類（CUBE / OTHER）が載っている。
- [ ] ミッションカード 10 枚（M001〜M010）の形状が座標系の定義とともに載り、易しい段階と追加段階が区別されている。
- [ ] アクションカード 5 枚（A001〜A005）のメインアクション・ボーナスが載っている。
- [ ] 列挙（タイプ・タイミング・効果タイプ・稼働条件）と英語識別子の規則が定義されている。
- [ ] マスターと印刷物の差分が一覧にされ、読み取れなかった箇所・解釈が割れる点が「未確定事項」に挙がっている。
- [ ] `npx -y markdownlint-cli2 "docs/**/*.md"` がエラーなしで通る。
