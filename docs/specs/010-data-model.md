# 010: データモデル・通知の設計

| 項目 | 内容 |
| --- | --- |
| ステータス | Approved |
| 対応タスク | T-010 |
| 作成日 | 2026-10-19 |
| 関連仕様書 | 001（ゲームルール）、002（カードデータ）、003（盤面データ）、004（BGA 構成・技術方針 §3・§6）、007（効果の処理順と同時処理）、008（アナログ特有の操作）、009（状態遷移）。後続: 011（カード効果）、012（UI・アセット） |

## 目的

サーバー側で永続化するデータ（DB スキーマ、カード・衛星・キューブ・盤面の保存方法、グローバル値、プレイヤーごとの値、同時選択の確定内容、効果の窓の状態）と、クライアントへ送る情報（`getAllDatas`、各状態の args、通知）を定める。009 の状態機械が読み書きするデータの置き場を決め、実装者がこの仕様書と承認済み仕様書だけで `dbmodel.sql`、`Data/` 層、通知を書けるようにする。最重要の要件は、非公開情報（手札、山札の順序、ミッション、伏せたアクション、同時選択の確定内容、効果の窓の途中経過）をどの経路でも漏らさないこと。

## スコープ

- 対象: 永続データの全体像、`dbmodel.sql`（10 テーブル＋ `player` の追加列）、カード・衛星・キューブ・盤面の保存方法、グローバル値とプレイヤーごとの値、同時選択の確定内容の保存形式、効果の窓の状態（プール、ステージ、保留通知）、非公開情報の守り方、`getAllDatas`、各状態の args、通知の一覧とログ文言の英語原文、名声の内訳の保存と表示、統計（`stats.jsonc`）の候補、整合性の制約、ゲーム開始時の初期化
- 対象外: 効果ごとの処理・問い（プロンプト）の組み立て・効果ごとの `pool_state` の中身（→ 011）、画面の見た目・スプライト・アニメーション・クライアントの通知ハンドラの実装（→ 012）、状態の遷移そのもの（009 が正）、ルールそのもの（001 が正）、処理順（007 が正）。この仕様書では PHP のコードは書かない（SQL と擬似コードは設計の説明用）

## 記法と調査の前提

- **【確認】** は BGA 公式ドキュメントで確認した事実。**【提案】** は本書の設計案。**【未確認】** は公式ドキュメントから判断できず、Studio での検証に回す事項（「Studio で検証する点」にまとめる）。
- 根拠の `001 §N` `002 §N` `003 §N` `004 §N` `007 §N` `008 §N` `009 §N` は各仕様書の節、`Q-NN` は [rule-questions.md](../development/rule-questions.md)、`未確定 N` は本書末尾の未確定事項の No.。
- 調査日は 2026-10-19。ページは WebFetch（本文を要約して返すツール）で取得したため要約を介した確認である。Game.php のページは長く、末尾 28,000 文字ほどは読み切れていない。API は更新が速いので実装時に再確認する。
- 参考にした姉妹リポジトリ: minimum-journey-bga の `docs/specs/007-data-model.md` と `src/dbmodel.sql`、`src/modules/php/Data/`（`Database` インターフェース、`GlobalValues`、`PlayerValues`、自前のリポジトリ）。

確認に使った BGA 公式ページ:

| 略称 | URL |
| --- | --- |
| DbModel | <https://en.doc.boardgamearena.com/Game_database_model:_dbmodel.sql> |
| GamePhp | <https://en.doc.boardgamearena.com/Main_game_logic:_Game.php> |
| StatesInc | <https://en.doc.boardgamearena.com/Your_game_state_machine:_states.inc.php> |
| Stats | <https://en.doc.boardgamearena.com/Game_statistics:_stats.json> |
| StateDir | <https://en.doc.boardgamearena.com/State_classes:_State_directory>（009 §1.1 で確認済みの B5 を引用） |

## 仕様

### 1. BGA の仕組みの確認結果

#### 1.1 【確認】した事実

| No. | 事実 | 出典 |
| --- | --- | --- |
| D1 | `dbmodel.sql` には `CREATE TABLE IF NOT EXISTS`・`ENGINE=InnoDB`・`utf8mb4` を使い、すべてのテーブルに主キーが要る。コードと同じ行にコメントを書かない（行全体が無効になりうる）。動的なデータだけを持ち、静的な性質は PHP のマスターデータに置く | DbModel |
| D2 | `player` テーブルには `ALTER TABLE` で列を足してよい。フレームワークが作った列は変えない。`global`・`stats`・`gamelog` と `bga_` で始まるテーブルは変更も直接アクセスもしない | DbModel |
| D3 | 列名はテーブル名と同じにしない（リプレイ機能が正規表現でクローンテーブルへ置換するため）。`AUTO_INCREMENT` の値は連続・最大値 + 1 とは限らない | DbModel |
| D4 | ゲーム中にスキーマは変えられない。リリース後の変更は `upgradeTableDb()` で行う。PHP から CREATE / ALTER を呼ぶと暗黙のコミットが起きるので、`dbmodel.sql` と `upgradeTableDb()` 以外では呼ばない。NOT NULL 列の追加は移行を慎重に計画する | DbModel |
| D5 | 全テーブル（システムテーブルを含む）の合計が 64 MB を超えるとゲームが止まる。最大の消費元は `gamelog` とリプレイ用のテーブルなので、args と通知を小さく保つ | DbModel |
| D6 | `$this->bga->globals` に `set` / `get`（`class:` 指定可）/ `has` / `delete` / `inc` / `getAll`。値は JSON で `bga_globals` に保存される。キーは 50 文字以内の文字列。関数と循環参照は保存できない | GamePhp |
| D7 | `getAllDatas` は連想配列を返す。キー `players` はフレームワーク用に予約されている。引数は魔法の引数 `int $currentPlayerId`（または `$currentPlayerNo`）だけが許される | GamePhp |
| D8 | `notify->all(type, message, args)` は全プレイヤーと観戦者へ、`notify->player(playerId, type, message, args)` は卓の 1 人へ（観戦者には送れない）。公開通知の args の `_private`（プレイヤー ID をキーにした連想配列）は、その本人にだけ別の値・別のログ文を渡せる。メッセージ中では `${_private.key}` で参照する（`_merge_private` で直接参照も可） | GamePhp |
| D9 | 通知はアクションが正常に終わった時点でキューから送られ、例外が起きると 1 つも送られない。通知の合計サイズの上限は 128 KB。翻訳対象の引数は `i18n` に列挙し、メッセージは `clienttranslate()` で包む。`player_name` は `i18n` に入れない。`preserve` はログ用の通知に残す引数の指定で、ログが大きくなる | GamePhp |
| D10 | `args`（状態の引数）の戻り値は既定で全員に公開される。本人だけに見せる値は `_private` に入れ、`active`（アクティブなプレイヤー全員）またはプレイヤー ID をキーにする（併用不可）。クライアントでは `args.args._private`。private データは性能に影響しうるので必要なときだけ使う | StatesInc |
| D11 | 統計は `stats.jsonc`（`table` / `player` の 2 節）。型は `int` / `float` / `bool`。ID は 10 以上で、各節の中で一意。削除した ID は再利用しない（過去データが壊れる）。キーは英数字のみ。(テーブル統計 + プレイヤー統計 × 最大人数) は 930 未満。`setupNewGame` で `init` する。未初期化・未更新の統計は「-」と表示される | Stats、GamePhp |
| D12 | `playerScore` / `playerScoreAux`（`PlayerCounter`、`set` / `inc`）。順位は `playerScore` の大きい順 | GamePhp |
| D13 | `bga_rand(min, max)` を乱数に使う（`rand()` / `mt_rand()` / SQL の `RAND()` は使わない） | GamePhp |
| D14 | DB 操作は `DbQuery` / `getCollectionFromDB` / `getObjectListFromDB` / `getUniqueValueFromDB` / `DbGetLastId`。プレイヤー由来の文字列は `escapeStringForDB()` で包む。変更はリクエストの終わりにトランザクションとして確定する | GamePhp |
| D15 | 非 GAME 状態の `getArgs` の中で DB を書かない。`MULTIPLE_ACTIVE_PLAYER` の API と `zombie()` は 009 §1.1（B1〜B12）で確認済み | 009 §1.1 B5 |

#### 1.2 【未確認】のまま設計に影響する点

| No. | 事項 | 設計上の扱い |
| --- | --- | --- |
| V-1 | `CHECK` 制約が Studio の DB（MariaDB）で効くか。公式ドキュメントに記述がない | `CHECK` に依存しない。整合性は DB のキーと `Data/` 層の検査で守る（§12）。使える場合だけ追加の保険として足す |
| V-2 | `getAllDatas` のキー `players`（D7）に、`player` テーブルの追加列（`maya` など）が含まれるか | 追加列は `players` に頼らず、本書は別キー `playerData` で返す（§8）。`players` に `fame_detail` が混ざらないことを Studio で確認する |
| V-3 | 公開通知（`notify->all`）の `_private` が、観戦者・リプレイでどう扱われるか | 本人だけに見せる情報は `notify->player` と `getArgs` の `_private`、`getAllDatas` の本人分で渡す。公開通知の `_private` は使わない（§9） |
| V-4 | `player` テーブルへの索引追加（`ALTER TABLE ... ADD KEY`）が許されるか | 索引は新規テーブルにだけ張る。`player` には列だけを足す（D2） |
| V-5 | 窓の保留通知を一斉に流したときの 128 KB の上限（D9） | §10.5 の圧縮規則で収める。実測は Studio（§16 V-6） |
| V-6 | 大量の `UPDATE` を 1 リクエストで行ったときの性能（山札のシャッフル） | 山札のシャッフルは `card_order` の全面書き換え 1 回。99 行なので問題にならない見込み |

### 2. 永続データの全体像

どの情報をどこに持つかの一覧。「保存先」は §3〜§7 で定義する。

| 分類 | データ | 保存先 | 公開範囲 |
| --- | --- | --- | --- |
| プロジェクトカード 99 枚 | 山札・捨て札・手札・引いた直後・衛星の何枚目か、起動効果の使用済み | `project_card` | 場所ごと（§4.2） |
| 衛星 20 機 | 色・形状・プラン面、座標（`sat_cell`）、向き、消費燃料 c、着陸猶予、起動効果の使用済み | `satellite` | 公開 |
| 通信確立（キューブ） | 区域・Z 順のスロット・重ね置きの段・世代 | `link` | 公開 |
| 通信確立のステージ | 窓の中で積まれた、まだコミットされていない通信確立と予約した支払い | `staged_link` | **非公開**（バリアまで） |
| 地域ボードの配置 | 位置・ボード・回転 | `region` | 公開 |
| アクションカード 20 枚 | 手札 / 縦 / 横 | `action_card` | 公開 |
| ミッションカード | 持ち主 | `mission_card` | 持ち主のみ（終了時に全員へ） |
| 同時選択の確定内容 | プレイヤー × 状態 × 内容 | `choice` | **非公開**（全員確定まで） |
| 効果のプール | 窓ごとのトリガー済み効果、順序、進み具合 | `pool` | 効果を持つカードは公開、選択の内容は非公開 |
| 窓の保留通知 | バリアまで送らない公開通知 | `held_notif` | **非公開**（バリアまで） |
| プレイヤーごとの値 | マヤ、予算、発射拠点、名声の内訳 | `player` の追加列 | マヤ・予算・発射拠点は公開、名声の内訳は終了時 |
| ゲーム全体の値 | ラウンド、フェイズ、`flow_step`、窓の状態、盤面の寸法、褒賞 ほか | `bga_globals`（`$this->bga->globals`） | サーバー内部。公開する値だけ args か通知で送る |
| 発射拠点 | 区域 | `player.pad_cell` | 公開 |
| 褒賞カード 2 枚 | 使う面（T001〜T024） | グローバル値 `reward_cards` | 公開 |
| 派生値（保存しない） | 稼働中か、燃料容量 C・残量 R、各山の枚数、通信確立数、残りキューブ、通信確立した地域、名声の途中値 | 必要なときに計算する | §7 |

保存しない（派生値にする）理由は、二重管理による不整合を避けるため。例: 稼働中かどうかは `satellite.sat_cell` が NULL かどうかが唯一の正で、別の列に複製しない。

### 3. `dbmodel.sql`【提案】

テーブルは 10 個。静的な定義（コスト・名声・効果・区域の種類）は持たず、`Material/`（002・003）を ID で引く。列名はテーブル名と同じにしない（D3）ため、各テーブルに接頭辞を付ける。

```sql
CREATE TABLE IF NOT EXISTS `project_card` (
  `card_id` SMALLINT UNSIGNED NOT NULL,
  `card_location` VARCHAR(12) NOT NULL,
  `card_location_arg` INT NOT NULL DEFAULT 0,
  `card_order` INT NOT NULL DEFAULT 0,
  `card_activation` TINYINT NOT NULL DEFAULT 0,
  `card_memo` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`card_id`),
  KEY `idx_card_loc` (`card_location`, `card_location_arg`, `card_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `satellite` (
  `sat_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sat_player` INT UNSIGNED NOT NULL,
  `sat_color` TINYINT UNSIGNED NOT NULL,
  `sat_shape` TINYINT UNSIGNED NOT NULL,
  `sat_plan` TINYINT UNSIGNED NOT NULL,
  `sat_card` VARCHAR(8) NOT NULL,
  `sat_cell` INT NULL DEFAULT NULL,
  `sat_dir` CHAR(2) NULL DEFAULT NULL,
  `sat_fuel_used` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `sat_grace` TINYINT(1) NOT NULL DEFAULT 0,
  `sat_activation` TINYINT NOT NULL DEFAULT 0,
  `sat_memo` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`sat_id`),
  UNIQUE KEY `uq_sat_owner_shape` (`sat_player`, `sat_shape`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `link` (
  `link_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `link_player` INT UNSIGNED NOT NULL,
  `link_cell` INT NOT NULL,
  `link_slot` TINYINT UNSIGNED NOT NULL,
  `link_level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `link_commit` INT UNSIGNED NOT NULL,
  `link_round` SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`link_id`),
  UNIQUE KEY `uq_link_owner_cell` (`link_player`, `link_cell`),
  UNIQUE KEY `uq_link_place` (`link_cell`, `link_slot`, `link_level`),
  KEY `idx_link_commit` (`link_commit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `staged_link` (
  `stage_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stage_player` INT UNSIGNED NOT NULL,
  `stage_cell` INT NOT NULL,
  `stage_sat` INT UNSIGNED NOT NULL,
  `stage_special` TINYINT(1) NOT NULL DEFAULT 0,
  `stage_source` VARCHAR(48) NOT NULL,
  `stage_area_cost` SMALLINT NOT NULL DEFAULT 0,
  `stage_special_cost` SMALLINT NOT NULL DEFAULT 0,
  `stage_pioneer` TEXT NULL DEFAULT NULL,
  `stage_reserved` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`stage_id`),
  KEY `idx_stage_player` (`stage_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `region` (
  `region_br` TINYINT UNSIGNED NOT NULL,
  `region_bc` TINYINT UNSIGNED NOT NULL,
  `region_board` CHAR(1) NOT NULL,
  `region_flip` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`region_br`, `region_bc`),
  UNIQUE KEY `uq_region_board` (`region_board`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `action_card` (
  `action_player` INT UNSIGNED NOT NULL,
  `action_no` TINYINT UNSIGNED NOT NULL,
  `action_state` TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`action_player`, `action_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mission_card` (
  `mission_no` TINYINT UNSIGNED NOT NULL,
  `mission_player` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`mission_no`),
  KEY `idx_mission_player` (`mission_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `choice` (
  `choice_player` INT UNSIGNED NOT NULL,
  `choice_state` SMALLINT UNSIGNED NOT NULL,
  `choice_data` TEXT NOT NULL,
  `choice_auto` TINYINT(1) NOT NULL DEFAULT 0,
  `choice_version` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`choice_player`, `choice_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pool` (
  `pool_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pool_player` INT UNSIGNED NOT NULL,
  `pool_window` VARCHAR(4) NOT NULL,
  `pool_kind` CHAR(1) NOT NULL,
  `pool_src` INT UNSIGNED NOT NULL,
  `pool_effect` VARCHAR(48) NOT NULL,
  `pool_event` VARCHAR(20) NOT NULL,
  `pool_context` TEXT NULL DEFAULT NULL,
  `pool_default_order` SMALLINT NOT NULL,
  `pool_chosen_order` SMALLINT NULL DEFAULT NULL,
  `pool_status` VARCHAR(8) NOT NULL DEFAULT 'pending',
  `pool_state` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`pool_id`),
  KEY `idx_pool_player` (`pool_player`, `pool_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `held_notif` (
  `held_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `held_origin` INT UNSIGNED NOT NULL,
  `held_type` VARCHAR(32) NOT NULL,
  `held_variant` VARCHAR(16) NOT NULL DEFAULT '',
  `held_args` TEXT NOT NULL,
  PRIMARY KEY (`held_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

ALTER TABLE `player` ADD `maya` INT NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `budget` INT NOT NULL DEFAULT 15;
ALTER TABLE `player` ADD `pad_cell` INT NULL DEFAULT NULL;
ALTER TABLE `player` ADD `fame_detail` TEXT NULL DEFAULT NULL;
```

- `AUTO_INCREMENT` の値は連続を仮定しない（D3）。`sat_id`・`link_id` などは識別にだけ使い、大小や連番に意味を持たせない。
- テーブルは `project_card`・`satellite`・`link`・`staged_link`・`region`・`action_card`・`mission_card`・`choice`・`pool`・`held_notif` の **10 個**。`player` には 4 列を足す。
- ゲーム中にスキーマは変えられない（D4）ので、列は最初に過不足なく定める。拡張の余地として `card_memo`・`sat_memo` を持つ（§4.5、効果ごとの永続状態。→ 011）。リリース後の変更は `upgradeTableDb()`。
- 各テーブルの説明の後ろに、コメントを同じ行に書かない（D1）。

### 4. プロジェクトカードの保存（`project_card`）

#### 4.1 列

| 列 | 意味 |
| --- | --- |
| `card_id` | カード番号そのもの（1〜99 = P001〜P099。002 §3.1）。`AUTO_INCREMENT` にしない。99 行を `setupNewGame` で固定生成する |
| `card_location` | 置き場所の種類（§4.2） |
| `card_location_arg` | 置き場所の引数（手札・引いた直後ではプレイヤー ID、衛星では `sat_id`。それ以外は 0） |
| `card_order` | 場所内の位置（§4.3） |
| `card_activation` | 起動効果の状態。`0` = 未使用（または起動効果でない）、`1` = 使用済み、`2` = 「今ラウンドは聞かない」（[Skip for this round]。008 §4、008 未確定 8）。`RoundStart`（20）で全カードを `0` に戻す（009 §7） |
| `card_memo` | 効果ごとの永続状態（JSON）。通常は NULL。中身は → 011 |

- カードの同一性は `card_id`（P 番号）で足りる。1 枚しかないカードなので、姉妹プロジェクトのような ID の付け替え（`changeIds`）は不要。**非公開なのは「どのカードがどの場所にあるか」であり、カード番号そのものは秘密ではない**。山札の順序と中身、他人の手札の内訳は、どの経路にも載せないことで守る（§6）。
- 衛星カード（R001〜R025）はプロジェクトカードではなく `satellite`（§5）で持つ。

#### 4.2 `card_location` の一覧

| location | location_arg | 意味 | order の使い方 | 公開範囲 |
| --- | --- | --- | --- | --- |
| `deck` | 0 | 共通の山札（裏向き） | 山の順（大きい値が上） | 枚数のみ。順序と中身は誰にも送らない |
| `discard` | 0 | 共通の捨て札（表向き） | 捨てた順（大きい値が上） | 全員に公開（ID の並び） |
| `hand` | プレイヤー ID | そのプレイヤーの手札 | 手札に入れた通し番号 | 本人: ID の一覧。他人・観戦者: 枚数のみ |
| `drawn` | プレイヤー ID | 企画（V）で引いた直後の、残すか捨てるかを選ぶ前のカード | 引いた順（zombie が「引いた順の先頭から残す」ために必要。009 §9.2） | 本人: ID の一覧。他人: 枚数のみ（008 §6） |
| `satellite` | `sat_id` | 衛星に配備されたカード | **配備の段（1〜4）**。1 = 最初に配備された（衛星カードに最も近い）、4 = 最も上 | 全員に公開（007 §6 の処理順に使う） |

- `card_order` を衛星では段として使い、燃料庫の積み上げ順・効果のデフォルトの処理順（衛星カード → 下に配備したカードから。001 §7-3、007 §6-3）を表す。段は 1〜4 の連続した整数。P022・P024 などでカードを動かすときは `Data/` 層が段を詰め直す。
- 売却・企画の捨てた・マリガンで捨てた・終了時の換金は `discard` へ。山札が尽きたときは `discard` 全体をシャッフルして `deck` にする（001 §2-1）。

#### 4.3 位置（order）の規約

- **山（`deck`・`discard`）**: 数値の向きに依存するコードを `ProjectCardRepository` の外に出さない。操作は `peekTop`・`drawTop`・`putOnTop`・`putOnBottom`・`shuffleDeck`・`reshuffleDiscard` だけ。`putOnBottom` は最小値 − 1 を割り当てる（符号付き INT）。
- シャッフルは Fisher–Yates を `bga_rand` で行い、`card_order` を 0〜n−1 に書き直す（D13）。結果の順序は通知・ログ・args のどこにも出さない。
- **引く順**: 複数人が同時に引く場合は、基準プレイヤーから時計回りの席順で直列に引く（007 §7-3、008 §3-8）。

#### 4.4 データアクセス層 `ProjectCardRepository`【提案】

`Data/` の唯一のカード読み書き口。状態クラスと `Flow/` はこのクラスの契約だけに依存する（004 §6）。

| メソッド | 動作 | 非公開情報の扱い |
| --- | --- | --- |
| `createAll()` | 99 行を `deck` に作る | `setupNewGame` でのみ |
| `countIn(location, arg)` | 枚数 | 公開してよい枚数（`deck`・`hand`・`drawn` を含む） |
| `idsIn(location, arg)` | ID の一覧 | `deck` は呼ばない。`hand`・`drawn` は本人の視点でのみ（`PublicView` 経由）。呼び出し箇所をコードレビューの検査項目にする |
| `drawTop(playerId, n, into)` | 山札の上から n 枚を `hand` または `drawn` へ。引いたカードを返す | 戻り値を公開通知に入れない。本人宛ての `handCardsAdded` にだけ使う |
| `discard(playerId, ids, reason)` | 手札などから `discard` へ | 捨てたカードは公開 |
| `deploy(playerId, id, satId)` | `hand` → `satellite`（段を最上段 + 1 にする）。4 枚を超えるときは例外 | 配備は公開 |
| `reshuffleDiscard()` | `discard` を `deck` へ移してシャッフル | 順序は送らない |
| `setActivation(id, value)`・`resetActivations()` | 起動効果の使用済み | 公開 |

#### 4.5 `card_memo`・`sat_memo`

効果が「そのカード（衛星）に付く永続状態」を必要とするときのための予約列（JSON）。本書では中身を定めない。001〜007 の範囲で必要になる永続状態は、すべて他の列（`card_activation`・`sat_grace`・`pool_state` など）で表せる。011 が必要と判断したときだけ使う（使わなければ NULL のまま）。

### 5. 衛星・キューブ・発射拠点・盤面

#### 5.1 `satellite`（20 行 = 4 人 × 5 機。人数が少なければその分だけ）

| 列 | 意味 |
| --- | --- |
| `sat_id` | 衛星の識別子（`project_card.card_location_arg` の参照先） |
| `sat_player` | 持ち主 |
| `sat_color` | 色（`0` = BLUE、`1` = GREEN、`2` = RED、`3` = YELLOW。002 §3.6） |
| `sat_shape` | 形状（`0` = HAMMER、`1` = RETRO、`2` = SCALLOP、`3` = CONCORDE、`4` = STEALTH。007 §6-3 のデフォルト順） |
| `sat_plan` | 使う面のプラン（`0` = MOON、`1` = PHOBOS、`2` = DEIMOS、`3` = EUROPA、`4` = GANYMEDE）。ゲームオプション 103（MOON / Player-variable。004 §7）で `setupNewGame` が決める。1 人の 5 機はすべて同じ（001 §3-4） |
| `sat_card` | 衛星カードの ID（`R001B` のような MOON 面、または `R006`〜`R025`。002 §3.1）。`sat_plan`・`sat_color`・`sat_shape` から決まるが、画像・効果の引きを速くするために持つ |
| `sat_cell` | 区域（`row × W + col`。003 §1.4）。**NULL = 非稼働**（衛星カード上）。NULL でなければ稼働中 |
| `sat_dir` | 向き（`NE` / `E` / `SE` / `SW` / `W` / `NW`。003 §4.1）。稼働中のときだけ値を持つ |
| `sat_fuel_used` | 消費した燃料 c（0 以上。燃料切れのとき `C + 1` で止まる。001 §4-4）。非稼働では 0 |
| `sat_grace` | 着陸猶予。着陸 / 着水判定で着陸した衛星に立て、循環フェイズの終わり（`OrbitDriver` ステップ 5）に外す。立っている間は「稼働中のみ」の効果が有効（FAQ 行 32、009 §3.8・§7）。稼働中の判定には使わない（`sat_cell` が正） |
| `sat_activation` | 衛星カードの起動効果の使用済み（`card_activation` と同じ意味。R011〜R025 の起動効果。002 §6.2） |
| `sat_memo` | 予約（§4.5） |

- **座標は `sat_cell`（区域 ID）1 つで持つ**。移動・隣接の計算はダブルド座標で行う（003 §1.2）ため、`Board/` が `cell ↔ (x, y)` を変換する（盤面の列数 W と行のずれ P は §7.2 のグローバル値）。DB に `x`・`y` を別に持たない。
- 派生値（保存しない）: 稼働中 = `sat_cell IS NOT NULL`、燃料容量 C = 衛星カードの燃料庫 + 配備された各カードの燃料庫、残量 R = C − c、燃料切れ = c > C。
- 衛星の入れ替え（P024）は、`sat_cell`・`sat_dir`・`sat_fuel_used` の値の交換で表す（稼働状態・燃料消費量・場所の入れ替え。007 §8-2）。
- 不変条件: `sat_cell IS NULL` ⇔ `sat_dir IS NULL`、非稼働なら `sat_fuel_used = 0`、`(sat_player, sat_shape)` は一意（UNIQUE）、`project_card` で `satellite` に置かれた枚数は 1 機あたり 0〜4（§12）。

#### 5.2 `link`（キューブ = 通信確立済みの区域）

置かれたキューブ 1 個につき 1 行。**放棄したキューブ（ボード外）は行を作らない**（使用済みにならず、通信確立数に数えない。001 §7-5、§10）。発射拠点は `link` に入れない（`player.pad_cell` に持つ）。ただし発射拠点は区域の `slot 0` を占める（作者回答 2026-10-20、003 未確定 5）ので、発射拠点のある区域のキューブは `slot 1` 以降になる。

| 列 | 意味 |
| --- | --- |
| `link_player` | キューブの持ち主 |
| `link_cell` | 区域 ID |
| `link_slot` | Z 順のスロット `0`〜`3`（0 = 左上、1 = 右上、2 = 左下、3 = 右下。003 §5.1）。**ボーナスは slot に印刷されている**（003 §3.2） |
| `link_level` | 同じスロットに重ねて置かれた段（0 = 一番下）。同時に通信確立したキューブは同じスロットで段だけが違う（003 §5.2）。段の順序は見た目だけで、得点・ミッションに影響しない |
| `link_commit` | コミット番号。通信確立をコミットするたびに、ゲーム全体で単調増加するカウンター（グローバル値 `commit_seq`）を 1 つ進めて付ける。**同じ `link_commit` = 同じタイミング**（007 §7-3 の「世代」を、ゲーム全体で一意に表したもの） |
| `link_round` | 通信確立したラウンド（統計・ログ用） |

- スロットの決め方: コミット時、その区域で埋まっているマスの数 n（すでにあるキューブの数 + 発射拠点がある区域なら 1。作者回答 2026-10-20）として、同じ `link_commit` で置く全員の `link_slot` = n（003 §5.3）。同じスロットの段は 0, 1, 2… を席順で割り当てる。
- `UNIQUE (link_player, link_cell)`: 同じ区域に自分のキューブは 1 つだけ（通信確立可能区域の条件 ii。001 §7-5）。`UNIQUE (link_cell, link_slot, link_level)`: 同じ場所に同じ段は 1 つ。
- 「2 番目以降に通信確立済み」（T007）は `link_slot > 0`、「最初」の区域ボーナスは `link_slot = 0` で判定できる。発射拠点のある区域のキューブは必ず `link_slot ≥ 1` なので、T007 では 2 番目以降として数え、「最初」のボーナスは誰も得られない。
- 007 の窓の「世代」（窓の中で 0, 1, 2…）は別にグローバル値 `window_gen`（§7.2）で持つ。ステージ（`staged_link`）をコミットして `link` に入れるたびに `commit_seq` を進めて `link_commit` に使う。

#### 5.3 `staged_link`（窓の中のステージ）

窓の並行処理の間に積まれた通信確立（カード効果によるもの。P047 ほか。007 §7-3）を、バリア（32）で一斉にコミットするまで持つ。コミットすると `link` に移して削除する。**他プレイヤーには見せない**（007 §7-1）。

| 列 | 意味 |
| --- | --- |
| `stage_player`・`stage_cell`・`stage_sat` | 通信確立するプレイヤー・区域・衛星 |
| `stage_special` | 特殊通信確立か（001 §7-5） |
| `stage_source` | 由来（カードの `name_id`。例: `ground_link`） |
| `stage_area_cost`・`stage_special_cost` | 予約した区域コスト（修正後）・特殊コスト（5） |
| `stage_pioneer` | 先駆コストの内訳（JSON `[{"to": プレイヤー ID, "amount": 3}]`）。ステージに積んだ時点で確定させる。同じ窓の中ではコミットが起きない（バリアだけ）ので、積んだ後に変わらない（007 §7-3） |
| `stage_reserved` | 予約した支払いの合計（区域 + 特殊 + 先駆）。キューブ 1 個の予約は行の存在そのもの |

- **予約**: 利用できるマヤ = `player.maya − SUM(stage_reserved)`。利用できるキューブ = `16 − COUNT(link) − COUNT(staged_link)`（16 個を超えるステージは積めない。007 §7-3）。
- コミット時、支払い（区域コスト・特殊コストはサプライへ、先駆コストは受け取り人へ）を全員分一括で行い、受け取りは全員の処理後に反映する（001 U-11）。
- 窓を出るとき（32 が `resume`）に残っているステージはない（空になるまで 31 ↔ 32 を繰り返すため。009 §3.5）。

#### 5.4 発射拠点

`player.pad_cell`（区域 ID。NULL = 未配置）。持ち主にとっては通信確立済みの区域として数える（001 §5 手順 10、Q-18）ので、**名声 III・IV・ミッション・褒賞の「通信確立した区域の集合 P」は `link` の区域 + `pad_cell`** から作る（`Board/` に渡す前に `Flow/` が組み立てる）。他プレイヤーの発射拠点がある区域への通信確立では、持ち主に先駆コスト 3 を払う（001 U-10）。

#### 5.5 `region`（地域ボードの配置）

`setupNewGame` で 1 回だけ決め、ゲーム中は変えない（004 エッジケース、003 §2）。盤面に置いたボード 1 枚につき 1 行（2×4 なら 8 行、2×3 なら 6 行、2×2 なら 4 行）。

| 列 | 意味 |
| --- | --- |
| `region_br`・`region_bc` | 段（0 = 上段、1 = 下段）と段内の位置（`0`〜`B−1`。003 §2.2） |
| `region_board` | 地域ボード（`A`〜`H`）。`UNIQUE` |
| `region_flip` | 180° 回転して置いたか（003 §2.3） |

- **面（表 / 裏）は全ボード共通**（1 ゲームで混ぜない。003 §2.4）なのでグローバル値 `board_face`（§7.2）に持つ。行のずれ P も全ボード共通のグローバル値（`row_shift`）。
- 区域の種類・区域ボーナス・地域は、`region` と `Material/`（003 §3 の 16 面の表）から `Board/` が計算する。区域ごとの行は持たない（72 行の重複を避ける）。UI にはゲーム開始時に `getAllDatas` で区域の一覧（`cell`・種類・地域）を計算して 1 回だけ送る（§8）。

### 6. アクションカード・ミッション・褒賞

#### 6.1 `action_card`（人数 × 5 行）

| 列 | 意味 |
| --- | --- |
| `action_player`・`action_no` | 持ち主とアクション（`1` = I 予算発行 〜 `5` = V 企画。A001〜A005。002 §9） |
| `action_state` | `0` = 手札、`1` = 縦（表向き。今ラウンドに選んだ）、`2` = 横（前ラウンドに選んだ I 以外）。001 §6-1 |

- 計画フェイズの回収（21）は SQL の 3 文: `state = 2` → `0`、`state = 1 AND action = 1` → `0`、残りの `state = 1` → `2`（この順。001 §6-1）。
- 公開（23）で、確定した選択のアクションを `1` にする。**プロットした（まだ公開していない）カードは `choice`（§9）に持ち、`action_card` は公開するまで変えない**。これにより、`action_card` は常に公開情報として全員に送れる（008 §6 の「手札のアクションカードは消去法で分かる公開情報」と整合）。
- 不変条件: 1 人の縦（`1`）は高々 1 枚、横（`2`）は高々 1 枚。

#### 6.2 `mission_card`（10 行）

`mission_player` が NULL のカードは配られなかった（使わない）カード。各プレイヤーに 2 枚（001 §5 手順 13）。**本人以外には ID を送らない**。配られなかったカードの存在も含めて送らない。終了時の名声計算（121）で全員のミッションを公開する（§11）。

#### 6.3 褒賞カード

ゲーム開始時に選ばれる 2 面は、グローバル値 `reward_cards`（`["T001", "T013"]` のようなカード ID の配列。002 §7）に持つ。公開情報。表裏の関係は気にしない（002 未確定 5 の回答: 24 面を独立に扱う）。

### 7. グローバル値とプレイヤーごとの値

#### 7.1 `player` の追加列

| 列 | 型 | 意味 | 書く状態 | 公開範囲 |
| --- | --- | --- | --- | --- |
| `maya` | int | 所持マヤ（初期資金は 001 §2-2 の表）。上限なし（001 U-1）。マイナスにしない（§12） | 支払い・獲得・売却をするすべての `Flow/` | 公開 |
| `budget` | int | 予算（初期値 15。上限なし。001 U-5） | `SetupFinish` 以前（setup）、予算を動かす効果 | 公開 |
| `pad_cell` | int / null | 発射拠点の区域（§5.4） | 11 `SetupLaunchPadPlace` | 公開 |
| `fame_detail` | text / null | 名声の内訳（JSON。§11） | 121 `FinalScoring` | ゲーム終了時に全員へ |

- `player_score` は 121 で名声の合計を `playerScore->set` する（D12）。**ゲーム中は更新しない**（0 のまま。途中の名声はゲームオプション 105 が Visible のとき `publicFame` / N36 で別に送る。001 §12、未確定 2）。`player_score_aux` は使わない（同点は同順位。Q-08）。

#### 7.2 グローバル値（`$this->bga->globals`）

D6 の API に保存する。キーは snake_case、50 文字以内。「書く状態」は 009 の状態番号。型 `[]` は配列を JSON で持つ。

| キー | 型 | 意味 | 初期値 | 書く状態 | 公開 |
| --- | --- | --- | --- | --- | --- |
| `round_no` | int | ラウンド番号（セットアップの配備フェイズは 0。009 §3.3） | 0 | 20 | 公開 |
| `flow_phase` | string | 現在のフェイズ: `SETUP` / `PLANNING` / `BUDGET` / `DEPLOY` / `LAUNCH_TURN` / `LINK` / `PROPOSAL` / `ORBIT` / `END`（アクションの種類は 002 §3.6 の `BUDGET` 〜 `PROPOSAL` と同じ綴り）。セットアップの配備フェイズは `DEPLOY`（009 §3.3） | `SETUP` | 17、20、23、40 | 公開 |
| `flow_step` | int | ドライバのステップ番号（009 §3.6〜§3.8）。窓から戻ったとき、次のステップから再開するために窓を開く側が先に進めておく（009 §6.2） | 0 | 各ドライバ、40 | 内部 |
| `flow_ctx` | object | ドライバがステップをまたいで持つ一時データ（メインのコミットの結果の要約など）。フェイズが変わるとき 40 が空にする | `{}` | 各ドライバ | 内部 |
| `window_id` | string / null | 開いている窓の種類（007 の `PL1`・`PL2`・`B0`・`B2`・`B3`・`XD`・`DE`・`LE`・`TE`・`KE`・`PA`・`PB`・`O0`・`O1`・`O4`） | null | ドライバ、30、32 | 公開 |
| `window_resume` | int / null | 窓が閉じたあとに遷移する状態 ID（009 §6.3）。入れ子にならないので 1 件 | null | ドライバ | 内部 |
| `window_gen` | int | 窓の中の世代（0 = 最初の並行処理、バリアのコミットのたびに +1）。安全弁の判定とログ用 | 0 | 30、32 | 内部 |
| `window_effects` | int | その窓で解決した効果の数（上限 200。007 §8-3） | 0 | 31、32 | 内部 |
| `window_snapshot` | object / null | 窓を開いた時点の公開情報（§9.4）。再読み込み時の他プレイヤー分の表示に使う | null | 30（作る）、32（消す） | 内部 |
| `commit_seq` | int | 通信確立のコミット番号の採番（§5.2）。ゲーム全体で単調増加 | 0 | コミットのたび | 内部 |
| `held_seq` | int | 保留通知の `ev_id` の採番（§10.5） | 0 | 保留のたび | 内部 |
| `bonus_holders` | object | アクション選択ボーナスの保持者 `{"1": [pid, …], …}`。**キーが存在するアクション = 選ばれたアクション**（009 の `selected_actions` はこのキー集合で表す）。EUROPA（R016〜R020）で保持者が増える（009 §3.4・§3.6） | `{}` | 23、各窓 | 公開（公開後） |
| `step_participants` | int[] | 現在の同時選択の状態の参加者（§9.2）。入場時に決める | `[]` | 同時選択の各状態の入場 | 公開 |
| `base_player` | int | 基準プレイヤー（席順の 1 番手。Q-07） | 1 番手 | setup | 公開 |
| `board_cols` | int | 盤面の列数 W（12 / 9 / 6）。行数 H は常に 6（003 §1.1） | — | setup | 公開 |
| `board_face` | string | `STANDARD`（表）/ `ADVANCED`（裏）。003 §2.4 | — | setup | 公開 |
| `row_shift` | int | 行のずれ P（0 / 1。003 §1.1） | — | setup | 公開 |
| `reward_cards` | string[] | 使う褒賞の 2 面（§6.3） | — | setup | 公開 |
| `end_condition_players` | int[] | 終了条件を満たしたプレイヤー（名声 IX の +3。複数可。009 §3.8） | `[]` | 110 | 公開 |
| `progress_high_water` | int | ゲーム進行度の単調増加用の最大値（009 §10） | 0 | 20、110 | 内部 |

- ゲームオプション（ボード配置、盤面の大きさ、褒賞、衛星プラン、手札）は `tableOptions` から読む（004 §7）。`board_cols` などの導出値だけを書く。
- 窓の最中に再読み込み・中断があっても、`window_id`・`window_resume`・`window_gen`・`pool`・`staged_link`・`held_notif`・`window_snapshot` が DB に残るので、同じ窓を続けられる（009 §6.3）。

#### 7.3 派生値（保存しない）

| 値 | 計算 | 公開 |
| --- | --- | --- |
| 稼働中 / 稼働中の衛星数 | `sat_cell IS NOT NULL` | 公開 |
| 燃料容量 C・残量 R・燃料切れ | §5.1 | 公開 |
| 山札・捨て札・手札・引いた直後の枚数 | `project_card` の `COUNT` | 公開（枚数のみ） |
| 通信確立数 | `COUNT(link WHERE link_player = p)`（発射拠点を含めない。001 §10・§11-5）。名声・褒賞・ミッションの対象集合は `link` + `pad_cell` | 公開 |
| 残りキューブ | `16 − COUNT(link) − COUNT(staged_link)`（窓の外ではステージは空） | 公開（ステージ分は窓の外のみ） |
| 通信確立した地域の種類 | `link` と `pad_cell` の区域の地域の異なる数 | 公開 |
| 選ばれたアクション | `bonus_holders` のキー集合 | 公開 |
| 予約済みマヤ | `SUM(staged_link.stage_reserved)` | 本人のみ |
| 名声の途中値 | 既定では計算しない。ゲームオプション 105 が Visible のときだけ、ミッション V を除いて計算して公開する（`getAllDatas.publicFame`、N36。未確定 2） | 公開（Visible のときだけ） |

### 8. `getAllDatas`

ページの再読み込み・途中参加・観戦開始のときに 1 回だけ呼ばれる（D7）。閲覧者の視点は魔法の引数 `$currentPlayerId` で受け取る。**観戦者は「`$currentPlayerId` が卓のプレイヤーに含まれない」で判定する**。キー `players` はフレームワーク用に予約されている（D7）ので、本ゲームのプレイヤーごとの値は別キー `playerData` に入れる（V-2）。

#### 8.1 視点ごとの違い

| 項目 | 本人 | 他プレイヤー（本人から見た相手） | 観戦者 |
| --- | --- | --- | --- |
| 盤面（`region`・区域の一覧）、衛星、配備されたカード、キューブ、発射拠点、褒賞、アクションカードの状態 | 送る | 同左 | 同左 |
| マヤ・予算 | 送る | 送る | 送る |
| 手札のプロジェクトカード | **ID の一覧** | 枚数のみ | 枚数のみ |
| 引いた直後（`drawn`）のカード | **ID の一覧** | 枚数のみ | 枚数のみ |
| 山札 | 枚数のみ | 枚数のみ | 枚数のみ |
| 捨て札 | ID の並び（公開） | 同左 | 同左 |
| ミッション | **自分の 2 枚の番号** | 枚数のみ（2） | 枚数のみ |
| 同時選択の確定内容（`choice`） | 送らない（現在の状態の `_private` が復元する） | 送らない | 送らない |
| プロットしたアクション | 送らない（同上） | 送らない | 送らない |
| 窓の中のプール・ステージ・保留通知 | 送らない（現在の状態の `_private` が復元する） | 送らない | 送らない |
| 名声の内訳（`fame_detail`） | ゲーム終了後だけ | 同左 | 同左 |

つまり、非公開情報は**本人の分（手札・引いた直後・ミッション）を除いて誰にも含まれない**。一時的な情報（プロットの確定内容、窓の問い）は `getAllDatas` ではなく、現在の状態の `getArgs` の `_private` が復元する（009 §5.2・§6.2）。

#### 8.2 戻り値の構造【提案】

数値は整数で送る。キーは camelCase。

```json
{
  "round": 3,
  "phase": "PLANNING",
  "basePlayer": 2310001,
  "board": {
    "cols": 12, "rows": 6, "rowShift": 0, "face": "ADVANCED",
    "regions": [{ "br": 0, "bc": 0, "board": "A", "flip": false }],
    "cells": [{ "cell": 0, "type": "SEA", "region": "A", "bonusSlots": [] }]
  },
  "rewards": ["T001", "T013"],
  "deckCount": 61,
  "discard": [12, 40, 7],
  "playerOrder": [2310001, 2310002],
  "myHand": [3, 18, 44],
  "myDrawn": [],
  "myMissions": [3, 9],
  "playerData": {
    "2310001": {
      "maya": 30, "budget": 15, "padCell": 17,
      "handCount": 5, "drawnCount": 0, "missionCount": 2,
      "cubesLeft": 16,
      "actionCards": { "1": 0, "2": 0, "3": 1, "4": 2, "5": 0 },
      "satellites": [{
        "satId": 11, "shape": "HAMMER", "plan": "MOON", "card": "R001B",
        "cell": null, "dir": null, "fuelUsed": 0, "fuelCapacity": 2,
        "grace": false, "activation": 0, "cards": [{ "id": 5, "activation": 0 }]
      }],
      "links": [{ "cell": 20, "slot": 0, "level": 0 }]
    }
  },
  "bonusHolders": { "3": [2310001] },
  "window": null,
  "finalScores": null,
  "publicFame": { "101": { "total": 12, "detail": { "total": 12, "I": { "points": 8, "budget": 8 } } } }
}
```

| キー | 内容 | 補足 |
| --- | --- | --- |
| `board.cells` | 区域の一覧（`cell`・種類・地域・ボーナスのスロット）。`Board/` が `region` から計算して 1 回だけ送る | 区域ごとの行は DB に持たない（§5.5） |
| `deckCount` / `discard` | 山札は枚数のみ、捨て札は ID の並び | — |
| `myHand` / `myDrawn` / `myMissions` | 本人の分だけ。観戦者には空配列 | — |
| `playerData[pid].satellites[].cards` | 配備されたカード（段の昇順）。`activation` は起動効果の使用済み（公開。008 §4） | — |
| `playerData[pid].cubesLeft` | 残りキューブ（窓の外ではステージは空） | 窓の最中は §9.4 のスナップショットから |
| `window` | 窓の最中だけ `{ "id": "DE" }`。プールの中身は含めない | — |
| `publicFame` | ゲームオプション 105 が Visible のときだけ入れるキー（Hidden ではキー自体がない）。プレイヤー ID => `{ total, detail }`。`detail` は §11 の I〜IX から**ミッション V を除いた**もの（`fame_detail` と同じキー名。`total` を含む）。ゲーム中の途中の名声: 手札は換金せず所持金のまま、IX は終了判定で満たした人だけ、VIII は現在の比較値での順位。窓（状態 30〜32）の最中は全員に窓開始時の値（`window_snapshot` の `publicFame`）を返す。DB には保存せず、`FinalScoring` と同じ入口（DB から `PlayerState` を組み立てて `FameCalculator`）で毎回計算する | Hidden ではキーなし |
| `finalScores` | ゲーム終了後（状態 121 以降）だけ、全員の `fame_detail`（§11）と全員のミッション（番号・達成状況） | それ以外は null |

- 山札の中身、`drawn` の他人分、他人の手札、`mission_card` の他人分、`choice`、`pool`、`staged_link`、`held_notif` は戻り値に含まれない。コードレビューでは、`getAllDatas` が `project_card` や `player` を丸ごと読んで整形していないこと（専用の読み出し `PublicView` を使っていること）を確認する。

### 9. 非公開情報の守り方

#### 9.1 守るべき情報と経路

| 情報 | 漏れる経路 | 防ぎ方 |
| --- | --- | --- |
| 山札の順序と中身 | `getAllDatas`、args、通知の引数、ログ文 | 山札の一覧を作るコードを作らない（`idsIn('deck')` を呼ばない）。送るのは枚数だけ。引いたカードは本人宛ての通知にだけ載せる |
| 他人の手札の内訳 | 手札を引く・売る通知 | 公開通知は枚数だけ（`cardsDrawn`）。ID は `notify->player`（`handCardsAdded`）で本人にだけ。**売却・配備・捨てるときは公開される**（008 §6）ので、そのときは公開通知に ID を載せる |
| ミッション | `getAllDatas`、`getArgs` | 持ち主にだけ番号を返す。他人には枚数のみ。終了時（121）に全員へ |
| 伏せたアクション（プロット） | `getArgs` の公開部分、`action_card`、通知 | プロットは `choice` に保存し、`action_card` は公開（23）まで変えない。公開の args には「確定済みのプレイヤー ID」だけ。本人の確定内容は `_private` |
| 同時選択の確定内容 | `getArgs`、通知、`player` 行 | `choice` の読み出しを `ChoiceRepository` に閉じ込める。公開側に出せるのは `confirmedPlayers()`（ID だけ）。**内容を返すメソッドは本人の視点でしか呼べない形にする**（引数にプレイヤー ID を取る）。公開通知（`choiceMarked`）に内容を載せない（§10.3） |
| 売却の同梱 | 確定内容の一部 | 同上（`choice_data` に含め、公開まで見せない。008 §2-6） |
| 窓の中の他人の選択・結果（引いたカード、選んだ対象、Use / Skip、支払い） | 通知、再読み込み後の `getAllDatas` | **保留通知**（§10.5）でバリアまで送らない。再読み込みでは `window_snapshot`（§9.4）から他人分を組み立てる |
| ステージされた通信確立 | 通知、`getAllDatas` | `staged_link` は本人にだけ見せる。バリアで全員に公開 |
| 乱数の結果 | ログ、デバッグ出力 | シャッフル後の順序を出さない |
| 他のタブ・再読み込み | args の再送 | `getArgs` は同じ状態の `_private` を毎回返す。DB は書かない（D15） |

#### 9.2 同時選択の参加者と確定の公開

- 参加者は入場時に決め、グローバル値 `step_participants` に保存する（009 §3.2）。**公開してよい情報は「参加者」と「確定済みのプレイヤー ID」だけ**。
- 確定済みか: `choice` に `(choice_player, choice_state)` の行があること。参加者のうち行がない人は未確定。「実行できる行動がない」人は、入場時にサーバーが `choice_auto = 1` の [Pass] 相当の行を作る（009 §3.2、008 §2-7）。`choice_auto` は公開側には出さない（ログに理由は出す）。
- 状態に入るとき、その状態の `choice` を空にする（前回の同じ状態の行を持ち越さない）。コミットが終わった後も行を消す。
- 再確定（[Change my choice]）は同じ行の上書きで、`choice_version` を +1 する。全員確定の判定は DB の行数で 1 回だけ行う（009 §5.2・§5.4）。

#### 9.3 `choice_data` の形式（JSON）【提案】

状態ごとに次の形にする。`sell` は「支払い不足の売却を同梱する」配列（008 §2-6）で、任意（空なら省略）。`cell` は区域 ID。向きは `NE` / `E` / `SE` / `SW` / `W` / `NW`。検証は確定のたびにサーバーが行う（008 §2-5）。

| 状態 | `choice_data` | 備考 |
| --- | --- | --- |
| 13 `SetupMulligan` | `{"discard": [カード ID, …]}`（0〜5 枚） | 0 枚 = [Keep all]。ID は本人の手札 |
| 22 `PlanningPlot` | `{"action": 1〜5}` | 手札のアクションカードのみ |
| 61 `DeployMainChoose` | `{"op": "deploy", "card": ID, "sat": sat_id, "sell": […], "useMods": […], "extra": {…}}` または `{"op": "pass", "extra": {…}}` | **配備のボーナスは通常の配備と同時に選ぶ**（作者回答 2026-10-20）。`extra` は**ボーナス保持者（セットアップは全員）だけ**で、追加の配備 `{"op": "deploy", "card": ID, "sat": sat_id, "useMods": […]}` または `{"op": "draw"}`（1 枚引く）。`extra` が追加の配備のときは、`op` が `pass` でもよい（通常の配備をしない）。`sell` は 2 枚分の合計コストに対する売却（配備する 2 枚は売れない）。`useMods`（外側）は通常の配備のカード、`extra.useMods` は追加の配備のカードに使う起動の修正で、同じ鍵を 2 枚には使えない。2 枚は同じ衛星でもよい（1 機 4 枚の上限は 2 枚を合わせて数え、稼働中の衛星への +5 は 1 枚ごと）。引いたカードは同時処理なので、同じ選択の配備には使えない。62 は欠番 |
| 71 `LaunchMainChoose` | `{"op": "launch", "sat": sat_id, "dir": "NE", "sell": […]}`、`{"op": "turn", "sat": sat_id, "dir": "SW"}`、`{"op": "pass"}` | 転回は稼働中の衛星、打ち上げは非稼働。向きを変えない転回も `turn`（FAQ 行 17） |
| 72 `LaunchBonusChoose` | `{"op": "turn", …}`、`{"op": "budget"}`、`{"op": "pass"}` | `budget` = 予算 +1（008 §3-3） |
| 81 `LinkMainChoose` | `{"op": "link", "cell": 区域, "sat": sat_id, "sell": […]}` または `{"op": "pass"}` | `pass` = キューブの放棄（Q-10） |
| 82 `LinkBonusChoose` | 81 と同じ | |
| 91 `ProposalKeepChoose` | `{"keep": [カード ID, …], "extra": {…}}` | `extra` は `PA` の選択効果（P072 の支払い、P068・P070 の追加の捨てなど）。中身は → 011（009 未確定 6） |

- 効果の窓（31）の答えは `choice` に入れない（答えた時点で解決し、取り消せない。009 §6.5）。順番の選択・Use / Skip・対象の選択は `pool` の列で持つ（§9.5）。
- 11 `SetupLaunchPadPlace`（ACTIVE_PLAYER）は逐次の単独選択で、`player.pad_cell` に直接書く（`choice` を使わない。009 §3.3）。

#### 9.4 窓の最中の再読み込み: `window_snapshot`【提案】

007 §7-2 は、窓の最中に他のプレイヤーへ公開しない情報（引いたカード、選んだ対象、Use / Skip、支払い）を定める。通知はバリアまで保留する（§10.5）が、**DB は各自の解決の都度すぐ更新される**。窓の途中で他のプレイヤーが再読み込みすると、`getAllDatas` がそれを読んで漏らしうる。

- 窓を開くとき（30）、そのときの**公開情報の全体**（`getAllDatas` の公開部分）を `window_snapshot` に JSON で保存する（数 KB）。
- 窓の最中（状態 30・31・32）の `getAllDatas` は、閲覧者**以外**のプレイヤーのマヤ・予算・手札枚数・衛星の状態・配備されたカード・燃料・山札と捨て札の枚数を `window_snapshot` から返す。閲覧者本人の分は DB の現在値を返す。
- バリア（32）で保留通知を全員に送ったあと、`window_snapshot` を消す。
- 窓の最中に変わりうる共有情報（捨て札）も、他人の変化は `window_snapshot` に従う。

これで、再読み込みしても通知と同じ見え方（窓の途中の他人の変化が見えない）になる。簡易案（保留せずに再読み込みで見えてもよいとする）との比較は未確定 1。

#### 9.5 効果のプール（`pool`）の列

| 列 | 意味 |
| --- | --- |
| `pool_player` | 効果を処理するプレイヤー（効果を持つカードの持ち主） |
| `pool_window` | 窓の種類（`window_id` と同じ値）。窓を閉じるとき（32）にこのテーブルを全消去する |
| `pool_kind`・`pool_src` | `P` = プロジェクトカード（`pool_src` = `card_id`）、`S` = 衛星カード（`pool_src` = `sat_id`） |
| `pool_effect` | 効果の識別子（所属カードの `name_id`。002 §3.1） |
| `pool_event` | トリガーしたイベント（`DEPLOYED` / `LAUNCHED` / `TURNED` / `LINKED` / `BUDGET_PHASE` など。002 §3.3） |
| `pool_context` | イベントの文脈（JSON: 対象の衛星・カード・区域・世代 など）。中身は → 011 |
| `pool_default_order` | デフォルトの処理順（衛星の形状順 → 衛星カード → 下に配備したカードから。007 §6-3） |
| `pool_chosen_order` | プレイヤーが選んだ順番（NULL = 未選択 / 選択不要）。確定後は変更できない（009 未確定 1） |
| `pool_status` | `pending`（未解決）、`asked`（[Use] / [Skip] を聞いている）、`done`（解決済み）、`skipped`（[Skip]）。窓の内側の状態 |
| `pool_state` | 効果ごとの進み具合（JSON）。多段の選択の途中経過など。中身は → 011 |

- 窓の問い（プロンプト）は**保存せず**、`pool`・`pool_state`・`Effects/` から `getArgs` のたびに組み立てる（009 §6.2）。
- 自動で解決する効果（選択がない強制効果）も `pool` に入れ、解決したら `done` にする。窓の安全弁の件数は `window_effects`（§7.2）。

### 10. 通知

#### 10.1 共通の約束

- 送信は `$this->bga->notify->all()`（全員・観戦者）、`$this->bga->notify->player()`（本人のみ。卓のプレイヤーに限る）。通知はアクションが正常に終わった時点でまとめて送られ、例外が起きると 1 つも送られない（D9）。GAME 状態の連鎖で複数の通知を出しても、リクエストの終わりに順序どおり届く。
- クライアントは `notif_<通知名>` ハンドラを持つ。**画面の更新は通知と `getAllDatas` だけで行う**（アクションの応答には頼らない。リプレイ・観戦で崩れないため。004 §5.4）。
- ログ文言は英語の原文を `clienttranslate()` で包む（CLAUDE.md）。プレイヤー名は `${player_name}`（`player_id` を同じ args に入れる）。カード名・衛星名・アクション名・区域名は `${card_name}` などに**英語名の文字列**を入れ、`i18n` に引数名を列挙する（D9）。`card_name` は `Material/` の `name_en`（002）。args に HTML を入れない。
- 画面だけを更新しログに出さない通知は、メッセージを空文字 `''` にする。
- **公開通知に載せてよいカードの ID は、その時点で公開されているもの**（配備・売却・捨てたカード、終了時の換金、ミッションの終了時公開）。手札に入れたカードは本人宛ての `handCardsAdded` にだけ載せる（§9.1）。
- 通知 1 件の目安は 2 KB 以内。1 リクエストの合計の上限は 128 KB（D9）。
- 通知のメッセージは `Flow/Notification` の 1 か所に `clienttranslate()` のリテラルとして集める（翻訳の抽出のため。§10.5 の保留通知もここから引く）。

#### 10.2 通知の一覧

「全員」は `notify->all`（観戦者含む）、「本人」は `notify->player`、「保留」は窓の中では §10.5 に従う公開通知。`reason` などの列挙は英語の識別子。

| No. | 通知名 | 送り先 | args | ログ文言（英語原文） | 送る処理・クライアントでの効果 |
| --- | --- | --- | --- | --- | --- |
| N1 | `launchBasePlaced` | 全員 | `player_id`、`player_name`、`cell` | `${player_name} places their launch base` | 11。発射拠点のコマを区域に置く。次の人の配置へ |
| N2 | `choiceMarked` | 全員 | `player_id`、`state_id` | `''` | 同時選択の確定（初回のみ。内容なし）。チェックマーク。変更では送らない |
| N3 | `choiceRecorded` | 本人 | `state_id`、`summary`（確定内容の要約） | `''` | 確定・再確定のたび。同じプレイヤーの別タブを同期（`getArgs` の `_private` と併用） |
| N4 | `roundStarted` | 全員 | `round_no` | `Round ${round_no} begins` | 20。起動効果の使用済みマークを全員分外す。進行度の更新 |
| N5 | `actionCardsCollected` | 全員 | `moves`（`{プレイヤー ID: {"toHand": [アクション番号], "toSideways": [アクション番号]}}`） | `''` | 21。アクションカードの回収をアニメーション（001 §6-1）。P001・P002 が縦向きのカードを手札に戻したとき（ジャーナル `actionKept`）も、同じ N5 で `toHand` を窓の保留の通知として送る |
| N6 | `actionRevealed` | 全員 | `player_id`、`player_name`、`action_no`、`action_name` | `${player_name} chose ${action_name}` | 23。プロットを一斉に表向きにする。席順（基準プレイヤーから）で 1 件ずつ。`bonus_holders` を更新 |
| N7 | `phaseStarted` | 全員 | `phase`、`phase_name`、`holders`（ボーナス保持者の ID 配列） | `${phase_name} phase begins` | 40 が次のフェイズへ進めるとき。フェイズ帯の点灯。保持者は公開 |
| N8 | `cardsDrawn` | 全員 | `player_id`、`player_name`、`count`、`zone`（`hand` / `drawn`）、`reason`、`deck_count`、`discard_count` | `${player_name} draws ${count} card(s)` | 引いたとき（配備のボーナス、企画、衛星の打ち上げ時、山岳ボーナス、効果）。枚数と山の枚数だけ。裏向きのカードが山札から動く |
| N9 | `handCardsAdded` | 本人 | `cards`（ID 配列）、`zone`（`hand` / `drawn`）、`reason` | `''` | N8 と同時に本人へ。ID を手札（または引いた直後の置き場）に加える |
| N10 | `cardsDiscarded` | 全員 | `player_id`、`player_name`、`cards`（ID 配列）、`reason`（`sell` / `proposal` / `mulligan` / `effect` / `deckRefill`）、`discard_count` | `sell`: `${player_name} sold ${count} card(s)`、`proposal`: `${player_name} discards ${count} card(s)`、`mulligan`: `${player_name} mulligans ${count} card(s)` | 売却・企画の捨てた・マリガンの捨てた・効果で捨てたとき。**ID を公開**（捨て札は表向き）。売却のマヤは N12 に続く |
| N11 | `deckReshuffled` | 全員 | `deck_count` | `The discard pile is shuffled into a new deck` | 山札が尽きて捨て札をシャッフルしたとき。順序は載せない |
| N12 | `mayaChanged` | 全員 | `player_id`、`player_name`、`delta`、`maya`（変更後）、`reason`（`budget` / `bonus` / `sell` / `landing` / `areaBonus` / `cost` / `effect` / `cashOut` ほか）、`reason_label`（i18n） | 正: `${player_name} gains ${amount} Maya (${reason_label})`、負: `${player_name} pays ${amount} Maya (${reason_label})` | マヤの増減。メッセージは `delta` の符号でサーバーが選ぶ |
| N13 | `mayaTransferred` | 全員 | `from_player_id`、`from_name`、`to_player_id`、`to_name`、`amount`、`maya_from`、`maya_to` | `${from_name} pays ${amount} Maya of pioneer cost to ${to_name}` | 先駆コスト。コミット時に全員分を席順で |
| N14 | `budgetChanged` | 全員 | `player_id`、`player_name`、`delta`、`budget`、`reason_label` | `${player_name} raises Budget by ${delta} (${reason_label})` | 予算トラックの円盤を動かす |
| N15 | `cardDeployed` | 全員 | `player_id`、`player_name`、`card`（ID）、`card_name`、`sat_id`、`sat_name`、`level`（段）、`fuel_capacity`、`cost`、`from_hand`（bool） | `${player_name} deploys ${card_name} to ${sat_name}` | 配備。カードが衛星に重なる。**配備するカードの ID を公開**。II 配備ではボーナス保持者が 1 回のコミットで最大 2 件（通常の配備、追加の配備の順）。売却（N10・N12 `sell`）は 2 枚分の合計コストに対して 1 回で最初の N15 の前、コストの N12（`cost`）は 1 枚ごと。ボーナスで引いた分は N8（`reason`: `bonus`）が続く（作者回答 2026-10-20） |
| N16 | `satelliteLaunched` | 全員 | `player_id`、`player_name`、`sat_id`、`sat_name`、`cell`、`dir`、`cost` | `${player_name} launches ${sat_name}` | 打ち上げ。衛星コマが発射拠点の区域に向きつきで現れる。`fuel_used = 0` |
| N17 | `satelliteTurned` | 全員 | `player_id`、`player_name`、`sat_id`、`sat_name`、`dir`、`same`（向きが変わらない転回か） | `${player_name} turns ${sat_name}` | 転回。向きを変える |
| N18 | `satellitesMoved` | 全員 | `moves`（`[{ "sat_id", "from_cell", "cell", "dir", "reflected" }]`） | `Satellites move one area` | 循環フェイズの移動（全員分を 1 件にまとめる）。反射のときは向きを反転 |
| N19 | `fuelChanged` | 全員 | `player_id`、`player_name`、`sat_id`、`sat_name`、`delta`（消費は正、回復は負）、`fuel_used`、`fuel_capacity`、`reason_label` | 消費: `${sat_name} uses ${delta} fuel (${reason_label})`、回復: `${sat_name} recovers ${amount} fuel (${reason_label})` | 燃料コマを動かす。燃料切れは `fuel_used > fuel_capacity` |
| N20 | `nearMiss` | 全員 | `cell`、`sats`（`[sat_id, …]`） | `Near miss! The satellites in the same area use 2 extra fuel` | ニアミス判定。続けて N19（`reason`: `nearMiss`） |
| N21 | `satelliteLanded` | 全員 | `player_id`、`player_name`、`sat_id`、`sat_name`、`cell`、`sea`（bool）、`maya`（獲得 0 または 10） | 海: `${sat_name} splashes down`、陸: `${sat_name} lands` | 着陸 / 着水。衛星コマを衛星カードに戻し、燃料コマを初期燃料庫へ。海のとき N12 が続く |
| N22 | `linkStaged` | 本人 | `stage_id`、`cell`、`sat_id`、`special`、`reserved`、`maya_available`、`cubes_left` | `''` | 窓の中のカード効果による通信確立をステージに積んだとき。本人にだけ予約額と残り |
| N23 | `linkEstablished` | 全員 | `player_id`、`player_name`、`cell`、`area_label`（i18n）、`slot`、`level`、`sat_id`、`special`、`commit`、`cubes_left` | `${player_name} establishes a link at ${area_label}` | 通信確立のコミット。キューブが Z 順の場所に現れる。区域ボーナスは続く N12・N19・N8 |
| N24 | `actionPassed` | 全員 | `player_id`、`player_name`、`kind`（`deploy` / `launch` / `link` / `bonus` / `keep`）、`auto`（bool） | `auto` でない: `${player_name} passes`（IV は `${player_name} abandons the link action`）。`auto`: `${player_name} has no available action` | [Pass]・キューブの放棄・自動パス。`kind = bonus` は打ち上げ / 転回のボーナスの [Pass]（配備のボーナスは 61 で通常の配備と同時に選ぶので、`deploy` に含める） |
| N25 | `drawnCardsKept` | 本人 | `kept`（ID 配列） | `''` | 企画の確定後。引いた直後の置き場から手札へ移す。捨てたカードは N10 |
| N26 | `windowClosed` | 全員 | `window_id`、`events`（このバリアで流した `ev_id` 配列） | `''` | 32 の最後（保留通知を流した後）。クライアントは適用済みの `ev_id` の記録を整理する（§10.5） |
| N27 | `effectUsed` | 全員（保留） | `player_id`、`player_name`、`ev_id`、`source_name`（カード名）、`sat_id`、`mark_used`（bool） | `${player_name} uses ${source_name}` | [Use]・自動の強制効果。使用済みマークを付ける。結果は N8・N12・N15・N19 などが続く |
| N28 | `effectSkipped` | 全員（保留） | `player_id`、`player_name`、`ev_id`、`source_name` | `${player_name} skips ${source_name}` | [Skip]（使用済みにしない。008 §4） |
| N29 | `satelliteChanged` | 全員（保留） | `ev_id`、`sats`（`[{ "sat_id", "cell", "dir", "fuel_used", "cards": [ID, …] }]`） | `${player_name} rearranges ${sat_name}` | カードの付け替え（P022）・衛星の入れ替え（P024）など、個別の通知にしにくい衛星の変化。細かい内容は → 011 |
| N30 | `handCashedOut` | 全員 | `player_id`、`player_name`、`cards`（ID 配列）、`maya_gain`、`maya` | `${player_name}'s hand was sold for ${maya_gain} Maya at game end` | 120。換金するカードを公開し、捨て札へ（008 §2-10） |
| N31 | `gameEndTriggered` | 全員 | `players`（終了条件を満たした ID 配列）、`reasons`（`satellites` / `links`） | `The game ends after this round` | 110 が終了と判定したとき。IX の +3 の対象は公開 |
| N32 | `finalScore` | 全員 | `player_id`、`player_name`、`fame`、`detail`（§11.2 の JSON）、`missions`（そのプレイヤーのミッション番号と達成状況） | `${player_name} scores ${fame} fame` | 121。全員分を席順に 1 件ずつ。内訳の表（bga-score-sheet 候補。004）を出す。ミッションをここで公開 |
| N33 | `countersUpdate` | 全員 | `deck_count`、`discard_count`、`players`（`{ID: {"handCount", "drawnCount", "cubesLeft"}}`。変更があった者だけ） | `''` | 各 GAME 状態の処理の最後とアクションの最後。山と手札の枚数カウンターを再同期 |
| N34 | `effectQuestions` | 本人 | 31 の args の `_private[playerId]` と同じ形（`windowId`、`pending`、`questions`、`hand`、`sellValue`）。答えた後で問いが無ければ `questions` は空 | `''` | 31 の `actUseEffect` / `actSkipEffect` / `actOrderEffects` の直後。31 の args は入場時のものなので、クライアントが残りの問い（ORDER の次の EFFECT、二段階の効果の二段目など）を知る。保留に積まず即時に送る（本人の問いは公開情報ではない） |
| N35 | `effectWaiting` | 全員 | `waiting`（`[{ "player_id", "sources": [カードキー] }]`。31 の args の公開部分と同じ） | `''` | N34 と同時、および zombie の処理の後。待機表示「Waiting for X (CARD_NAME)」（008 §5-1）を更新する。カードは公開情報 |
| N36 | `publicFameUpdated` | 全員 | `fame`（`{ID: {"total", "detail"}}`。`getAllDatas.publicFame` と同じ形。ミッション V を除く） | `''` | ゲームオプション 105 が Visible のときだけ。N33 を送る箇所（各 GAME 状態の処理の最後とアクションの最後、予算ボーナスの後）で、値が変わりうるとき N33 の直後に送る。窓の最中は送らず、バリアの公開（N26 windowClosed）の後の N33 と同時に最新値を送る。Hidden では送らない |

- 窓の外の GAME 状態（コミット）で出す通知は、その場で `notify->all` を呼ぶ（リクエストの終わりに順序どおり届く）。**窓の中（状態 31）の他人に見える通知**は §10.5 の規則に従う。
- N36 は全員宛て。ゲームオプション 105 が Visible のときだけ送る。
- 通知のうち「本人」宛てなのは N3・N9・N22・N25・N34 の 5 種。他は全員宛て。
- N8 + N9 の対、N10、N12 は他の通知と一緒に使われる共通部品（例: 山岳ボーナス = N8 + N9、森林ボーナス = N12、深海 = N19）。区域ボーナスを個別の通知にしない。

#### 10.3 公開通知に載せてはいけない項目

| 項目 | 理由 |
| --- | --- |
| N2 `choiceMarked` に確定内容 | 同時選択の内容は全員確定まで非公開（§9） |
| N8 `cardsDrawn` に引いたカードの ID | 手札は枚数だけが公開（N9 で本人に） |
| N11 `deckReshuffled` にシャッフル後の順序 | 山札の順序 |
| N6 より前の `choice` の内容（プロットしたアクション） | 公開の瞬間（23）まで伏せる |
| 窓の中の他人の結果（N27〜N29 と、それに続く N8・N12・N15・N19 など）を窓の途中で | 007 §7-2。保留通知（§10.5） |
| 全ての通知に `getArgs` の `_private` の中身 | 本人以外に漏れる |

#### 10.4 流れの例（アクションプロットから公開まで）

```text
22 PlanningPlot: actConfirmPlot (A, B)  -> N3 (本人), N2 (全員, 内容なし)
   全員確定で allConfirmed
23 PlanningReveal:                      -> N6 × 人数 (席順)
   窓 PL2 を開く / 開かないなら 40 へ
40 ActionPhaseRouter:                   -> N7 (最初の選ばれたフェイズ)
```

```text
60 DeployDriver ステップ 2 (メインのコミット)
   -> N15 (配備した人ごと), N12 (コスト), N10 (売却があれば), N33
   窓 DE を開く (window_snapshot を作る)
31 EffectWindowResolve: A が P013 を Use -> 保留: N27 (+ N8 / N12 / N15 …)。A 本人には同じ内容を先に送る
32 EffectWindowBarrier:                 -> 保留通知を全員に流す -> N26
```

#### 10.5 窓の中の通知: 保留と先送りの規則【提案】

007 §7-1・§7-2 は、窓の最中は結果を他のプレイヤーへ公開せず、バリアで一斉に公開するとする。BGA の通知はリクエストの終わりに必ず届くので、**窓の中（状態 31）の解決で生じた「他人にも見える通知」は、`notify->all` を呼ばずに `held_notif` に積む**。

1. 保留に積む対象: 31 の `act*`（`actUseEffect` / `actSkipEffect` / `actOrderEffects`）の解決で生じた全員宛ての通知（N8・N10・N12・N14・N15・N17・N19・N27・N28・N29 など）。`held_origin` = 解決したプレイヤー、`held_type` = 通知名、`held_variant` = メッセージの種類（正負の別など）、`held_args` = JSON。**`Flow/Notification` の `clienttranslate()` のリテラルを引く**ので、メッセージの文字列そのものは保存しない。
2. 本人への先送り: 積むと同時に、同じ内容を `notify->player`（本人）に**メッセージ `''` で**送る（自分の画面が即座に更新される）。args に `ev_id`（`held_seq` の採番）を付ける。手札の ID を伴う N9 は、窓の外と同じく即座に本人へ。
3. バリア（32）で、`held_notif` を `held_id` 昇順に全員へ `notify->all` で送る（本人にも届く）。各通知の args に同じ `ev_id` と `early`（本人には先送り済みか）を付ける。クライアントは**先送りで適用済みの `ev_id` の通知を画面には反映せず、ログだけ出す**。その後 N26 `windowClosed` を送って `held_notif` を空にする。
4. 流す順序: `held_id` の昇順（時系列）。ログの見やすさのため、同じバリアで複数プレイヤーの保留が混在するときは、**基準プレイヤーから時計回りの席順で、プレイヤーごとにまとめる**（008 §2-4 の表示順。処理順の意味は持たない）。同じプレイヤーの中は時系列を保つ。
5. 圧縮: 保留通知が多いとき（バリアの合計が約 64 KB を超えそうなとき）は、同じ種類の連続する通知（N12・N19 など）を 1 件にまとめる。それでも超える場合の最終手段は、衛星・マヤ・山の枚数を一括で再同期する N29 + N33 に置き換えること（V-6 で実測）。
6. 窓の外（ドライバのコミット）は即時に `notify->all` を呼んでよい。コミットは全員分を一斉に行うため、途中経過が見えるものではない。

保留に積まず即時に送るもの: 窓の開始・終了の表示に関わる N26、全員の状態の整合に必要な N33（ただし窓の最中は、他人の枚数の変化を反映しない値で送る。§9.4）。

### 11. 名声の内訳（001 §12 の I〜IX）

#### 11.1 計算と保存

- 計算は 121 `FinalScoring` で `Flow/FameCalculator` が行う。ゲーム中は計算しない（途中値は表示しない。未確定 2）。入力は終了時の状態（手札を換金した後。120）。
- 集合 P（III・IV・ミッション・褒賞の対象）= `link` の区域 + `player.pad_cell`（§5.4）。
- 結果を `player.fame_detail` に JSON で保存し、`playerScore->set` に合計を書く（D12）。N32 `finalScore` で全員に送る。`getAllDatas` は 121 以降、`finalScores` として全員分の内訳を返す（再読み込み・リプレイ・終了後の閲覧で復元するため）。

#### 11.2 `fame_detail` の形式【提案】

```json
{
  "total": 184,
  "I": { "points": 28, "budget": 28 },
  "II": { "points": 3, "maya": 33 },
  "III": { "points": 20, "land": 3, "sea": 14, "cells": [12, 15] },
  "IV": { "points": 28, "regions": ["A", "B", "C", "D", "E", "F", "G"] },
  "V": { "points": 23, "missions": [
    { "mission": 3, "tier": "EXTRA", "points": 18, "cells": [2, 3, 4] },
    { "mission": 9, "tier": "EASY", "points": 5, "cells": [30, 31, 32] }
  ] },
  "VI": { "points": 20, "sats": [{ "sat_id": 11, "fame": 5 }] },
  "VII": { "points": 35, "a": 20, "b": 5, "c": 10 },
  "VIII": { "points": 24, "rewards": [
    { "card": "T001", "value": 13, "rank": 1, "points": 18 },
    { "card": "T010", "value": 4, "rank": 2, "points": 6 }
  ] },
  "IX": { "points": 3 }
}
```

- 001 §12-1 のテレサの例（合計 184）を `FameCalculator` のテストベクタにする（004 §10）。
- `V.tier` は 002 §3.6 の `EASY` / `EXTRA`。ミッションの `cells` は、達成に使った区域（UI のハイライト用）。`VIII.rank` は同順位の分け合い後の順位（001 §12-2）。

#### 11.3 表示

- 終了画面で、プレイヤーごとの I〜IX の内訳を表（bga-score-sheet。004 §1）に出す。レイアウト・アニメーションは → 012。
- 終了時にだけミッションを全員に公開する（N32 の `missions`）。ゲーム中はどの経路にも全員宛てでは載せない（§9.1）。
- BGA 標準の最終得点は `playerScore`（合計）で表示される。同点は同順位（Q-08）。

### 12. 整合性の制約

#### 12.1 制約と守る場所

「DB」は `dbmodel.sql` のキー、「Data」は `Data/` 層（リポジトリ）での検査、「Test」は PHPUnit の結合テスト（004 §10）、「Debug」は `Game.php` の `debug_assertInvariants`（Studio のデバッグメニュー）。

| 制約 | 守る場所 | 備考 |
| --- | --- | --- |
| プロジェクトカードは 99 枚。各カードは 1 か所にだけある | DB（`card_id` が主キー） | |
| 1 衛星に配備できるカードは 4 枚まで（001 §7-3、RB p.20） | Data（`deploy` が 5 枚目を例外にする）、Test、Debug | 段は 1〜4。UNIQUE は付けない（付け替えの途中で重複するため） |
| 衛星は 1 人 5 機（形状ごとに 1 機） | DB（`UNIQUE (sat_player, sat_shape)`） | |
| 稼働中 ⇔ 向きがある。非稼働なら `sat_fuel_used = 0` | Data（`SatelliteRepository` の更新メソッドが両方を同時に書く）、Test、Debug | |
| 燃料: `sat_fuel_used` は 0〜`C + 1` | Data（消費・回復のメソッドで丸める。001 §4-4） | C は派生値 |
| キューブは 1 人 16 個まで | Data（`LinkRepository` と `StagedLinkRepository` が `16 − (link + staged)` を検査）、Test | 窓の外ではステージは空 |
| 同じ区域に自分のキューブは 1 つだけ | DB（`UNIQUE (link_player, link_cell)`） | 特殊通信確立でも 1 区域 1 キューブ（001 §7-5） |
| 同じ区域・スロット・段にキューブは 1 つだけ | DB（`UNIQUE (link_cell, link_slot, link_level)`） | |
| 1 区域のキューブは最大 4 スロット | Data（コミットで `slot ≤ 3` を検査）、Test | 人数 4 で 1 人 1 キューブなので n ≤ 3 |
| スロットの決め方: 同じコミット番号は同じスロット、次のコミットは既存の数を飛ばす | Data（`LinkRepository::commit`）、Test（003 §5.3 の例） | |
| マヤは負にならない。予約を超えて使えない | Data（`PlayerValues::addMaya` が負なら例外。予約は `staged_link` の合計） | 支払い可否は処理前の所持金で判定（001 U-11） |
| 発射拠点は陸系の区域で、他プレイヤーの発射拠点と重ならない（セットアップ） | Data（`placeLaunchBase`）、Debug | 001 §5 手順 10。`player` に索引を足さない（V-4） |
| 縦のアクションカードは 1 人 1 枚まで、横も 1 枚まで | Data（`ActionCardRepository`）、Test | |
| ミッションは各プレイヤー 2 枚で、重複しない | DB（`mission_no` が主キー）、Data（配札） | |
| 地域ボードは各 1 回だけ使う | DB（`UNIQUE (region_board)`） | |
| 同時選択の確定は参加者だけ・1 状態 1 行 | DB（`choice` の主キー）、Data（参加者の検査） | |
| 窓の終了時にプール・ステージ・保留通知・スナップショットが空 | Data（32 の最後に全消去して `COUNT = 0` を検査）、Test | |
| 公開 args・通知に非公開情報が入らない | コードレビュー（§9.1・§10.3 の検査項目）、Test（`PublicView` の出力にカード ID が含まれないこと） | |

- すべての書き込みは `Data/` 層のリポジトリ経由で行い、`Game.php` や状態クラスから生の SQL を書かない（004 §6.1）。
- 1 リクエストは 1 つのトランザクション（D14）。支払い・獲得・コミットは同じリクエストの中で完結させ、検査に失敗したら例外で全体を巻き戻す（通知も送られない。D9）。
- `CHECK` 制約は使える保証がない（V-1）ので、上の「DB」は主キーと `UNIQUE` だけに依存する。

#### 12.2 `Data/` 層の構成【提案】

004 §3 の方針（`Database` インターフェースとリポジトリ。姉妹の実績）に従う。

| クラス | 役割 |
| --- | --- |
| `Database`（インターフェース）、`BgaDatabase` | DB 操作の口（`execute`・`rows`・`value`・`lastInsertId`）。PHPUnit では PDO 実装に差し替える |
| `ProjectCardRepository` | §4.4 |
| `SatelliteRepository` | 衛星の読み書き、稼働・非稼働の遷移、燃料の増減、入れ替え |
| `LinkRepository`、`StagedLinkRepository` | キューブのコミット（世代・スロット・段）、ステージと予約 |
| `RegionRepository` | `region` の読み書き（`setupNewGame` のみ書く） |
| `ActionCardRepository` | 回収・公開・状態の読み出し |
| `MissionRepository` | 配札と本人の読み出し |
| `ChoiceRepository` | §9.2・§9.3。公開側には `confirmedPlayers()` だけ。内容は本人の視点でしか返さない |
| `PoolRepository`、`HeldNotificationRepository` | 窓のプールと保留通知 |
| `GlobalValues` | §7.2 のアクセサ（`$this->bga->globals`）。初期値を 1 か所に持つ |
| `PlayerValues` | §7.1 のアクセサ。`player` 行を丸ごと JSON にして返さない |
| `PublicView` | `getAllDatas`・`getArgs` の公開部分を作る唯一の入口（閲覧者の視点、`window_snapshot` の適用。§8・§9.4） |

### 13. 各状態の args

009 の状態表を基に、公開部分と `_private` の中身を示す。**`getArgs()` は DB を読むだけ**（D15）。キーは camelCase。`_private` は**参加者全員分**を作る（確定済みで待機中の人にも返す。009 §5.3 の案 B / 案 A を問わない）。`'active'` キーは使わない（プレイヤー ID 形式と併用できない。D10）。

| 状態 | 公開 args | `_private[プレイヤー ID]` | 備考 |
| --- | --- | --- | --- |
| 11 `SetupLaunchPadPlace` | `vacantCells`（空いている陸系の区域 ID。公開情報） | なし | |
| 13 `SetupMulligan` | `participants`、`confirmed`（確定済みの ID。内容なし） | `myChoice`（`{"discard": […]}`／null）、`hand`（手札 ID） | |
| 22 `PlanningPlot` | `participants`、`confirmed` | `myChoice`（`{"action": n}`／null）、`selectable`（手札のアクション番号の配列） | |
| 61 配備 | `participants`、`confirmed` | `myChoice`、`hand`、`targets`（カード番号 → 配備先の候補 `[{ satId, tier〔配備後の段〕, cost〔稼働中 +5、効果による修正後〕, blocked〔満杯 / 禁止の理由〕, minSales, selectable, useMods?, next? }]`）、`sellValue`（1 枚あたりの売却額）、`bonus`（配備のボーナス保持者か）、`canDraw`（保持者のみ。引けるか） | コストはサーバーが計算（008 §3-6）。保持者の各候補の `next` は、通常の配備で先に同じ衛星へ 1 枚配備した後の `{ tier, cost, blocked, useMods? }`（追加の配備を同じ衛星にするときに使う。クライアントは 2 枚のコストを足すだけ） |
| 71・72 打ち上げ / 転回 | 同上 | `myChoice`、`launchable`（打ち上げ可能な衛星とコスト）、`turnable`（転回可能な衛星と現在の向き）、`sellValue` | |
| 81・82 通信確立 | 同上 | `myChoice`、`linkable`（`[{ "cell", "sats": [{"sat_id", "cost": {"area", "pioneer": [{"to","amount"}], "total"}}] }]`。売却すれば払える区域のフラグ）、`sellValue` | 先駆コストは「すでに置かれているキューブ」に対する最悪値（008 未確定 10） |
| 91 `ProposalKeepChoose` | `participants`、`confirmed`、`keepCount`（各人の残す枚数。公開してよい: 引いた枚数だけが公開情報） | `myChoice`、`drawn`（引いたカード ID。引いた順）、`keep`（残す枚数）、`extra`（`PA` の選択効果の候補。→ 011） | |
| 31 `EffectWindowResolve` | `windowId`、`waiting`（`[{ "player_id", "sources": [カード名] }]`。効果を持つカードは公開情報。選択の内容は含めない）、`round` | `prompt`（現在の問い: 種類〔順番 / Use・Skip / 対象〕・候補・コスト。`Effects/` が組み立てる。→ 011）、`pending`（自分のプールの要約） | プールが空の人には `_private` を返さない |

- 窓の状態（31）の `waiting` は `window_snapshot` を使わない（誰が待っているかは公開情報）。
- 売却（`actSell`）の候補と額は、参加者の `_private.sellValue` と `hand` から出す（009 §8）。
- GAME 状態（10、12、14、16、17、20、21、23、30、32、40、50、60、70、80、90、100、110、120、121）は args を持たない。

### 14. 統計（`stats.jsonc`）の候補【提案】

D11 のとおり、ID は 10 以上、各節の中で一意、公開後は変更も再利用もしない。テーブル統計 10〜19、プレイヤー統計 20〜59 を割り当てる。名称（`name`）は英語の原文（BGA が自動で翻訳する）。`setupNewGame` で全統計を `init` する。

テーブル統計:

| キー | ID | 名称 | 型 | 記録タイミング |
| --- | --- | --- | --- | --- |
| `rounds_played` | 10 | Number of rounds | int | 110 の終了時 |
| `end_reason` | 11 | Game end condition | int | 110。`0` = 該当なし、`1` = 衛星 5 機が稼働中、`2` = キューブ 16 個、`3` = 両方（`value_labels` で表示） |
| `links_total` | 12 | Number of established links | int | 通信確立のコミット（全体の合計） |
| `near_misses` | 13 | Number of near misses | int | ニアミス判定 |

プレイヤー統計:

| キー | ID | 名称 | 型 | 記録タイミング |
| --- | --- | --- | --- | --- |
| `fame_budget` | 20 | Fame: Budget | int | 121（I） |
| `fame_maya` | 21 | Fame: Maya | int | 121（II） |
| `fame_links` | 22 | Fame: Linked areas | int | 121（III） |
| `fame_regions` | 23 | Fame: Linked regions | int | 121（IV） |
| `fame_missions` | 24 | Fame: Missions | int | 121（V） |
| `fame_satellites` | 25 | Fame: Active satellites | int | 121（VI） |
| `fame_projects` | 26 | Fame: Project cards | int | 121（VII） |
| `fame_rewards` | 27 | Fame: Awards | int | 121（VIII） |
| `fame_end` | 28 | Fame: End condition | int | 121（IX） |
| `links_count` | 29 | Established links | int | N23 のたび |
| `links_abandoned` | 30 | Abandoned link actions | int | IV の [Pass]（キューブの放棄） |
| `special_links` | 31 | Special links | int | 特殊通信確立 |
| `pioneer_paid` | 32 | Pioneer cost paid | int | N13（支払い側） |
| `pioneer_received` | 33 | Pioneer cost received | int | N13（受け取り側） |
| `cards_deployed` | 34 | Project cards deployed | int | N15 |
| `cards_sold` | 35 | Project cards sold | int | N10（`sell`） |
| `cards_discarded` | 36 | Project cards discarded (Proposal) | int | N10（`proposal`） |
| `satellites_launched` | 37 | Satellites launched | int | N16 |
| `satellites_turned` | 38 | Satellite turns | int | N17 |
| `landings` | 39 | Landings | int | N21（陸） |
| `splashdowns` | 40 | Splashdowns | int | N21（海） |
| `fuel_used` | 41 | Fuel used | int | N19（消費） |
| `fuel_recovered` | 42 | Fuel recovered | int | N19（回復） |
| `near_miss_count` | 43 | Near misses involved in | int | N20 |
| `maya_earned` | 44 | Maya earned | int | N12（正） |
| `maya_spent` | 45 | Maya spent | int | N12（負）。先駆コストを含む |
| `budget_final` | 46 | Final Budget | int | 121 |
| `actions_chosen_1`〜`actions_chosen_5` | 47〜51 | Times chose Budget / Deploy / Launch-Turn / Link / Proposal | int | N6（`action_no` ごと） |
| `bonus_gained` | 52 | Action selection bonuses gained | int | 61（ボーナス保持者）・72・82 などの参加（ボーナス保持者） |
| `effects_used` | 53 | Effects used | int | N27 |
| `effects_skipped` | 54 | Effects skipped | int | N28 |
| `active_satellites_final` | 55 | Active satellites at the end | int | 121 |
| `cubes_left_final` | 56 | Cubes left at the end | int | 121 |

- テーブル統計 4 + プレイヤー統計 37 = 41 個（D11 の上限 930 に対して 4 + 37 × 4 = 152）。
- 記録は `Stats/StatsRecorder`（`StatStore` インターフェースで BGA の統計 API を隠す。004 §6.1）を通す。通知の送信と同じ場所で記録して、数がずれないようにする。
- どの統計を残すかは未確定 4。

### 15. ゲーム開始時の初期化（`setupNewGame`）

009 §3.3 の手順に合わせて、DB を次の順に作る。

1. `project_card` に 99 行を `deck` で作り、`bga_rand` でシャッフルして `card_order` を割り当てる（§4.3）。
2. `region`・`board_cols`・`board_face`・`row_shift` をゲームオプション 100・101（004 §7）から決めて保存する（003 §2.4。乱数は `bga_rand`）。
3. `reward_cards` をオプション 102 から 2 面選んで保存する（001 §3-3、002 §7）。
4. 各プレイヤーについて: `satellite` を 5 行（形状ごと。色・プランはオプション 103。001 §3-4）、`action_card` を 5 行（`action_state = 0`）、`player.maya` を初期資金（001 §2-2、席順）、`player.budget = 15`、`mission_card` から 2 枚（`bga_rand`）を配る。
5. 手札を 5 枚引く（`hand`。基準プレイヤーから時計回り。ドラフトは初版では未実装）。
6. `base_player`・`round_no = 0`・`flow_phase = SETUP`・ほかのグローバル値を §7.2 の初期値で書く。
7. 統計を `init` する（§14）。

### 16. Studio で検証する点

| No. | 検証する点 |
| --- | --- |
| V-1 | `CHECK` 制約が効くか（効くなら §12 の保険に追加） |
| V-2 | `getAllDatas` の `players` に `player` の追加列が混ざらないか。`playerData` のキーを使えること |
| V-3 | `notify->player` の通知が観戦者・リプレイでどう見えるか（本人宛てのカード ID が他人に見えないこと） |
| V-4 | `player` への索引追加が許されるか（許されれば `pad_cell` に UNIQUE を足す） |
| V-5 | 1 リクエストで保留通知を 100 件流したときの通知サイズ。128 KB を超えたときの挙動 |
| V-6 | 窓の中で本人にだけ先送りした通知と、バリアで全員に流す通知の二重適用が、`ev_id` で防げること（§10.5） |
| V-7 | `window_snapshot` のサイズ（数 KB の見込み）と、`bga_globals` への保存で問題がないこと（JSON のサイズ制限は公式ドキュメントに記述がない） |

## エッジケース

| 状況 | 挙動 |
| --- | --- |
| 山札が尽きたとき（企画、配備のドローなど） | `reshuffleDiscard` で捨て札を山札にしてシャッフルし、N11 を送る。山札も捨て札も空なら引けない（001 §2-1）。引く順は席順の直列 |
| 山札を引いたカードの ID を、公開通知に誤って載せた | 起こらない設計（N8 は枚数のみ、ID は N9 で本人だけ）。`PublicView` のテストで検出する |
| 同じ区域に 3 人が同時に通信確立 | 同じ `link_commit`・同じ `link_slot`（n）で `link_level` を 0, 1, 2。次のコミットは n = 3（003 §5.3）。発射拠点のある区域では n が 1 から始まる（作者回答 2026-10-20） |
| 他プレイヤーの発射拠点がある区域への通信確立 | `link` に入れてよい（発射拠点は `link` に含まれない）。持ち主に先駆コスト 3（N13） |
| 窓の最中の再読み込み | 状態 31 に復元され、本人の問いは `getArgs` が `pool` から再構成する。他人の分は `window_snapshot` から（§9.4） |
| 窓の最中に処理が中断した | `window_id`・`window_resume`・`pool`・`staged_link`・`held_notif`・`window_snapshot` が DB に残る。同じ窓を続けられる。通知はリクエストの終わりに送られないので、保留通知は DB にだけ残る（まだ他人に送っていない） |
| ステージ中に予約が足りなくなった（他の効果でマヤが減った） | ステージは予約を確保した時点で確定している（007 §7-3）ので、足りなくなることは起きない。マヤの支払いはステージ後、予約を除いた額で判定する |
| `satellite` の入れ替え（P024）で燃料切れの衛星が移る | 値の交換のみで、着陸は起きない（007 §8-2）。着陸は循環の判定のときだけ |
| 着陸猶予が残っている衛星を再び打ち上げる | 起こらない（猶予は 100 のステップ 5 で外れ、打ち上げはアクションフェイズ。001 エッジケース） |
| 全員が zombie になった | 009 未確定 4 のとおり。`choice` は zombie の既定で自動確定される |
| 山札の中身を使う効果（山札の上を見る効果など） | 本人にだけ `_private` で渡す。山札全体の一覧は作らない（→ 011） |
| 名声の端数（II の 10 マヤ未満、VII-b の半分の切り捨て） | `FameCalculator` で切り捨て（001 §12） |
| 褒賞の比較内容が全員 0 | 誰も得点しない。`VIII.rewards` は空の配列 |

## 未確定事項

作者への質問。回答があるまで暫定案で仮置きする。

| No. | 質問 | 暫定案 | 回答 |
| --- | --- | --- | --- |
| 1 | 窓の最中（効果の解決中）に、他のプレイヤーがページを再読み込みしたとき、その窓で起きている他人の変化（マヤ、手札枚数、引いた枚数、配備の変化）を見せないために、窓の開始時点の公開情報のスナップショット（`window_snapshot`、§9.4）から返す方式でよいか。簡易案は、通知だけを保留し、再読み込みでは最新の DB の値が見えてしまうことを許す（実装は簡単だが、007 §7-2 の「窓の途中は公開しない」を再読み込みでは守れない） | スナップショット方式（007 に忠実） | 暫定案で了承（2026-10-19、作者からの指摘なし） |
| 2 | ゲーム中の名声（途中値）をプレイヤーに見せるか。001 §12 の名声は終了時に計算する内容が多く（ミッション形状、褒賞の順位など）、途中値の計算は複雑。BGA の標準スコア欄はゲーム中 0 のままになる（姉妹プロジェクトと同様に CSS で隠す）。参考表示を出したい場合は別途設計 | ゲーム中は表示しない。`playerScore` は 121 で一度だけ書く。スコア欄の 0 は 012 で隠す | 暫定案で了承（2026-10-19、作者からの指摘なし）。**作者回答 2026-10-20: オプションで公開**。「ミッション以外の名声は公開情報なので、オプションで見えるようにしたい。デフォルトは見えない」。ゲームオプション 105「Public fame (except missions)」（1: Hidden 既定 / 2: Visible）で Visible のときだけ、ミッション V を除く途中の名声を `getAllDatas.publicFame` と N36 で公開する。既定は従来どおり表示しない |
| 3 | 捨て札の中身（どのカードが捨てられたか）を、全員がいつでも一覧で見られてよいか（008 §6 は「捨て札は表向き」とする）。山札が尽きて捨て札をシャッフルしたあと、どのカードが山札に戻ったかは全員が推測できる（紙のゲームと同じ） | 見られる。`getAllDatas` で捨て札の ID の並びを送る | 暫定案で了承（2026-10-19、作者からの指摘なし） |
| 4 | 統計（§14）の項目と ID の割り当て。公開後は変更も再利用もできない | §14 のとおり（名声の内訳 I〜IX、通信確立・配備・打ち上げ・燃料・マヤなど 41 項目） | 暫定案で了承（2026-10-19、作者からの指摘なし） |
| 5 | 衛星の `sat_card`（衛星カードの ID）を、ゲームオプション 103 で MOON 面か色別プラン面かを決めた時点で 1 度だけ決めて固定する。ゲーム中に面を変えることはないが、これでよいか（001 §3-4 は 1 人の 5 枚を混在させない） | 固定。変更の手段は作らない | 暫定案で了承（2026-10-19、作者からの指摘なし） |
| 6 | 窓の保留通知が 1 リクエストの上限（128 KB）に近づいたときの扱い。通常のプレイでは起きない見込みだが、連鎖が長いと理論上起こりうる | §10.5 の圧縮（同種の通知を 1 件にまとめる）を実装し、最終手段は衛星・マヤ・山の枚数の一括再同期 | 暫定案で了承（2026-10-19、作者からの指摘なし） |

## 他の仕様書への申し送り

- → 011: 窓の問い（プロンプト）の組み立て（`pool`・`pool_state`・`pool_context` の中身）、`card_memo`・`sat_memo` の使い道、保留通知に積む効果ごとの通知（N27〜N29 と N29 の細目）、`PA` の `extra`（§9.3）、山札を見る効果の `_private` args。
- → 012: 通知ハンドラ（N1〜N36）、`ev_id` による二重適用の防止（§10.5）、`getAllDatas` の `board.cells` からの盤面描画、名声の内訳の表（bga-score-sheet）、スコア欄の 0 の非表示（未確定 2）、燃料ゲージ（`fuelUsed`・`fuelCapacity`）、使用済みマーク（`activation`）、横向きのアクションカード（`actionCards` の 2）。
- → 009: 本書のグローバル値（`bonus_holders`〔`selected_actions` をキー集合で表す〕、`window_snapshot`、`commit_seq`、`held_seq`、`step_participants`、`flow_ctx`）を状態機械が読み書きする。

## 受け入れ条件

- [ ] `dbmodel.sql` のテーブル（10 個）と `player` の追加列（4 列）が、列・型・キーとともに書かれ、公式ドキュメントの規則（主キー、列名とテーブル名の不一致、コメント、スキーマ変更の制約）に従っている
- [ ] プロジェクトカードの場所（山札・捨て札・手札・引いた直後・衛星の何枚目）、衛星（色・形状・プラン面・稼働中か・座標・向き・消費燃料 c）、キューブ（区域・Z 順のスロット・重ね置きの段・世代）、発射拠点、アクションカードの状態、ミッション、褒賞、地域ボードの配置、起動効果の使用済み、同時選択の確定、効果の窓の状態とプールの保存方法が定まっている
- [ ] 009 の「他の仕様書への申し送り → 010」の永続データの候補（`round_no`、`flow_phase`、`flow_step`、`selected_actions`、`bonus_holders`、`window_id`、`window_resume`、`window_gen`、プールと現在の問い、ステージされた通信確立、確定一覧、`progress_high_water`、`end_condition_players`、起動効果の使用済み）がすべて扱われている
- [ ] グローバル値の一覧とプレイヤーごとの値（マヤ、予算、発射拠点、名声の内訳）が定まっている
- [ ] 非公開情報（手札、ミッション、伏せたアクション、同時選択の内容、窓の途中経過）について、`getAllDatas`・`getArgs` の `_private`・通知の公開 / 本人の分け方が定まり、漏れる経路ごとの防ぎ方が書かれている
- [ ] 通知の一覧（名前・送り先・args・ログ文言の英語原文・クライアントでの効果）がある
- [ ] 名声 I〜IX の内訳の保存形式と表示の方針、統計の候補がある
- [ ] 整合性の制約（1 衛星 4 枚、キューブ 16 個 ほか）を守る場所が書かれている
- [ ] 未確定事項に作者が回答し、回答に合わせて更新されたうえでステータスが Approved になる
- [ ] `npm run lint:md` がエラーなしで通る
