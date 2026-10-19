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

結合テスト（MariaDB）は、データ層を作るタスク（I-05）で追加する。

## 構成

| パス | 内容 |
| --- | --- |
| `Unit/Material/` | カード定義データ（`src/modules/php/Material/`）の件数・ID の網羅・002 の主要な値の抜き取り |
| `bootstrap.php` | Composer のオートロード |
| `phpunit.xml.dist` | PHPUnit の設定 |

## カード定義と 002 の整合性

PHP の定義と 002 の表の全項目の突き合わせは、PHP を実行せずに Node で行う。

```bash
npm run lint:cards
```
