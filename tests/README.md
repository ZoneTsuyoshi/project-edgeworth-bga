# テスト

## 単体テスト（PHPUnit）

BGA にも DB にも依存しない純粋なロジック（カード定義データ、盤面の計算など）のテスト。

```bash
npm run test:unit
```

必要なもの: Docker だけ。`tests/run-unit.sh` が次を行う。

1. `tests/Dockerfile` から PHP 8.4（Composer 入り）のイメージ `pe-test-php` を作る。
2. リポジトリを PHP コンテナにマウントし、`composer install` の後に `composer test`（PHPUnit）を実行する。コンテナは終了時に消える。

`vendor/` はリポジトリ直下に作られる（`.gitignore` 済み）。引数は PHPUnit に渡る（例: `bash tests/run-unit.sh --filter ProjectCardsTest`）。

## 結合テスト（PHPUnit + MariaDB）

データ層（`src/modules/php/Data/`）と `src/dbmodel.sql` を、実際の MariaDB で確かめるテスト。

```bash
npm run test:integration
```

必要なもの: Docker だけ。`tests/run-integration.sh` が次を行う。

1. 単体テストと同じイメージ `pe-test-php`（PHP 8.4 + `pdo_mysql` + Composer）を作る。
2. 専用ネットワークに MariaDB 11 のコンテナを立て、接続できるまで待つ。
3. PHP コンテナで `composer install` の後に `composer test:integration` を実行する。終了時に両コンテナとネットワークを消す。

引数は PHPUnit に渡る（例: `bash tests/run-integration.sh --filter LinkRepositoryTest`）。接続先は環境変数 `DB_HOST`・`DB_PORT`・`DB_NAME`・`DB_USER`・`DB_PASSWORD`（既定は `127.0.0.1:3306`、DB `edgeworth_test`、`root`、パスワードなし）。GitHub Actions の `PHPUnit (MariaDB)` ジョブは `services` で MariaDB を立てて同じ `composer test:integration` を実行する。

各テストは `Schema::reset()` で全テーブルを作り直す。`src/dbmodel.sql` をそのまま流し、BGA が先に作る `player` テーブルは必要な列だけの代用品で置き換える。colima で `/private/tmp` がコンテナから見えない場合があるため、一時ファイルは `/Users/` 配下に置く。

## 構成

| パス | 内容 |
| --- | --- |
| `Unit/Material/` | カード定義データ（`src/modules/php/Material/`）の件数・ID の網羅・002 の主要な値の抜き取り |
| `Unit/Scoring/` | 名声計算（`src/modules/php/Scoring/`）: 001 §12-1 の計算例（合計 184）、褒賞の同順位・比較内容 0、24 面の比較値、終了時の手札の換金 |
| `Unit/Setup/` | セットアップの部品: オプションの解釈（人数との組み合わせ検査）、褒賞 2 面の選び方（★/★★・Cube/Other・✕は Advanced のみ）、初期資金、プレイヤーの色 |
| `Integration/Setup/` | `GameSetup` の結合テスト（2〜4 人、全オプション、枚数、初期資金、種を固定した再現性）と発射拠点の配置の問い合わせ（`LaunchPadPlacement`） |
| `Unit/Flow/` | 循環フェイズのルール（`src/modules/php/Flow/`）: 移動・燃料・ニアミス・着陸の計算（RB p.18〜19 の例、FAQ の循環・燃料の裁定）、燃料の表現、終了判定と進行度 |
| `Integration/Flow/` | `OrbitPhase`（DB への反映・着陸猶予）、`RoundEnd`（終了判定・進行度の最高値）、`FinalScoring`（fame_detail の保存）。MariaDB が要る |
| `Unit/Flow/Action/` | 計画・アクションフェイズのルール層の純粋な部品: 支払い可否（売却の最小枚数）、配備・通信確立コストの内訳、既定のフック（修正なし） |
| `Integration/Flow/Action/` | 計画・アクションフェイズのルール層（`ActionPhases`）を `GameSetup` で始めたゲームと MariaDB で確かめる: 計画の回収と公開、I〜V の選択・コスト・処理、売却、通信確立の同時処理・先駆コスト・区域ボーナス、`StepRunner` の同時選択の流れ。`ConfigurableHooks` でフック（修正・禁止）を再現。`StagedLinkCommitTest`: 効果による通信確立のステージ（`StagedLinkConverter`）を本物の `LinkCommitter` でコミット（同じ世代は同じ置き場・先駆コストなし、次の世代は先駆コストあり、払えない依頼は `LINKED` を起こさない） |
| `Unit/Effects/` | 効果エンジン（`src/modules/php/Effects/`・`Material/` の `EffectSpec` など。011 の E-0）: 列挙の件数（フック 28・部品 29・条件 13）と代表 10 枚の定義が 002・007 §10 と一致すること、資源の衝突の判定（S01〜S04・S07・S08）、デフォルトの順、窓の解決（順序の問い・[Use] / [Skip]・売却を含む支払い・連鎖・使用済み・安全弁 200・多段・zombie・保存と復元・通信確立のステージ。S01〜S10 のうちエンジンで検証できるもの）、答えの検証、修正の問い合わせと `ActionHooks`・`OrbitModifiers` への接続 |
| `Unit/Effects/Cards/` | カードごとの効果のテスト（011 §11）。`E1CardsTest`: E-1（常時の修正・禁止）35 種を、本物のカード定義・部品・条件・盤面（Earth の標準面）をつないだ `E1Fixture` で検査（配備 / 打ち上げ / 通信確立のコスト、対象区域の拡張、禁止、企画の枚数、予算・売却額、燃料消費・ニアミス。FAQ のケース T03・T08・T12・T14・T16・T17・T19・T21〜T24・T27・T29・T32）。`Unit/Effects/Material/EffectMaterialE1Test`: 35 種の定義が 002・007 §10・011 §7 と一致すること |
| `Unit/Effects/Cards/E3EffectsTest` ほか | E-3（任意の効果と問い）22 種: [Use] / [Skip] と C1 の支払い・売却の同梱（P005・P006・P069・P086）、起動の修正 `USE_MODS` の候補・検証・コミット（P019〜P021・P036・R021〜R025、T19・T20）、企画のオプション（P068・P070・P072、T30）、2 段の効果 P087、EUROPA の宣言（R016〜R020、T24・T25）、問いの組み立てと答えの検証。`Unit/Effects/Material/E3MaterialTest` は定義が 002・007 §10・011 §7 と一致すること。補助は `Support/Effects/E3Fixture`・`E3Cards` |
| `Unit/Effects/Cards/E4EffectsTest` ほか | E-4（衛星の操作）11 種: 転回（P044・P048・P090・P095。向きを変えない 360 度、F01 の衛星、連鎖）、移動（P004・P034・P081・P082・P091。反射・左右の接続、庫外に出るところまで、ニアミス・着陸は行わない、`ORBIT_ENTERED` の連鎖。T01・T07）、燃料の移し替え（P038。T09）、入れ替え（P024。T06）。`Unit/Effects/Material/E4MaterialTest` は定義が 002・007 §10・011 §7 と一致すること。補助は `Support/Effects/E4Fixture`・`E4Cards`（`FakeGame::place()` で衛星を稼働させる） |
| `Unit/Effects/Cards/E5EffectsTest` ほか | E-5（配備）6 種: 手札からの配備（P011・P045・P074・R005。追加配備コスト +5 の有無、売却の同梱、起動の修正 `USE_MODS`、4 枚上限、F01 の衛星、`DEPLOYED` の連鎖、R005 の配備したカードの `ON_DEPLOY`・`ON_LAUNCH`。T10・T21・T28）、山札からの配備（P035。T05）、付け替え（P022。T04、`DEPLOYED` を起こさない）、燃料切れの衛星への配備（N-1）。`Unit/Effects/Material/E5MaterialTest` は定義が 002・007 §10・011 §7 と一致すること。補助は `Support/Effects/E5Fixture`・`E5Cards` |
| `Unit/Effects/Cards/E6EffectsTest` ほか | E-6（通信確立）14 種: 効果による通信確立のステージ（R11。P047・P050・P051・P056〜P061・P076・P096〜P099）。特殊通信確立コスト（+5）と P076 の通常コスト（FAQ 行 30）、対象区域（衛星の直下・前方 / 後方・直下に隣接する陸、対象を広げる効果は常に足す。FAQ 行 23〜27、P050 の「他の衛星」は居場所不問・F01 の衛星は選べない。FAQ 行 21・22・56）、K04 の地域・K08 の突入・K07 の発動条件、起動の修正 `USE_MODS`（R025）、売却の同梱、ステージの予約（マヤ・キューブ・同じ区域）、世代とバリア（同じ世代の同じ区域は先駆コストなし・次の世代は先駆コストあり。T18）、再発動の連鎖（007 未確定 8。P049 は世代ごと）、同一宿主の起動効果の重複排除（N-9）、T13 の連鎖（P076 → P048 → P037）。`Unit/Effects/Material/E6MaterialTest` は定義が 002・007 §10・011 §7 と一致すること。`Unit/Flow/Action/LinkCostTest` は `LinkCost::compute()`（余りを先駆コストから席順に引く）とステージの保存・復元（`StagedLinkConverter`）。補助は `Support/Effects/E6Fixture`（`commit()` でバリアの 1 周を再現）・`E6Cards` |
| `Integration/Data/` | データ層（`dbmodel.sql`、各リポジトリ、グローバル値、プレイヤーの値）。MariaDB が要る |
| `Support/` | 結合テストの共通部品: `PdoDatabase`（`Database` の PDO 実装）、`Schema`（`dbmodel.sql` を流す）、`DatabaseTestCase`、`ArrayGlobals`（BGA の globals の代用）、`SeededRandom`、`BgaStubs`（BGA のクラスの最小スタブ） |
| `Support/Effects/` | 効果エンジンのテスト用の基盤（011 §11-1）: `FakeGame`（`GameView` / `GameMutator` のメモリ実装）、`ArrayCatalog`、`TestSpecs`（疑似カードの `EffectSpec`）、`ScriptedResultPart`（クロージャの結果部品）、`SampleParts`（代表 10 枚の簡易な部品） |
| `bootstrap.php` | Composer のオートロードと BGA スタブの読み込み |
| `phpunit.xml.dist` | PHPUnit の設定（テストスイート `unit` と `integration`） |
| `run-unit.sh`・`run-integration.sh` | Docker でそれぞれのスイートを実行 |

## カード定義と 002 の整合性

PHP の定義と 002 の表の全項目の突き合わせは、PHP を実行せずに Node で行う。

```bash
npm run lint:cards
```
