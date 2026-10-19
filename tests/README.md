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
| `Integration/Data/` | データ層（`dbmodel.sql`、各リポジトリ、グローバル値、プレイヤーの値）。MariaDB が要る |
| `Support/` | 結合テストの共通部品: `PdoDatabase`（`Database` の PDO 実装）、`Schema`（`dbmodel.sql` を流す）、`DatabaseTestCase`、`ArrayGlobals`（BGA の globals の代用）、`SeededRandom`、`BgaStubs`（BGA のクラスの最小スタブ） |
| `bootstrap.php` | Composer のオートロードと BGA スタブの読み込み |
| `phpunit.xml.dist` | PHPUnit の設定（テストスイート `unit` と `integration`） |
| `run-unit.sh`・`run-integration.sh` | Docker でそれぞれのスイートを実行 |

## カード定義と 002 の整合性

PHP の定義と 002 の表の全項目の突き合わせは、PHP を実行せずに Node で行う。

```bash
npm run lint:cards
```
