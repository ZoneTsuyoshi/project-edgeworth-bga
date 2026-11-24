# 013: 初期手札のドラフト（ゲームオプション 104 の値 3）

| 項目 | 内容 |
| --- | --- |
| ステータス | Draft |
| 対応タスク | I-22 |
| 作成日 | 2026-11-24 |
| 関連仕様書 | 001（§3-5、§11-1）、004（§7）、008（§2・§6・§8・§9-1）、009（§3.1〜3.3・§5・§9）、010（§4・§8・§9.3・§10.2・§13・§15）、012（パネル、`CardPicker`、未確定 17、追補 2026-11-21 U-1）。参照: rule-questions Q-06 |

## 目的

ゲームオプション 104「Starting hand」の値 3（Draft）を実装するための、データ・args・状態の処理・通知・画面・テスト・PR の分け方を決める。ルールと操作の骨組みは 001・008・009 で承認済みで、本書はそこで決まっていない部分を埋める。

## スコープ

- 対象: パックの保存、状態 12・15・16 の処理、15 の args、通知と `getAllDatas`、画面、オプションの公開、テスト、エッジケース、実装の PR の分け方。
- 対象外: ルール自体の変更（承認済みの内容は変えない）、非同期方式（008 未確定 11 で同期方式に確定済み）、マリガン（013 は触らない）。

## 仕様

### 1. すでに決まっていること（変えない）

| 項目 | 内容 | 出典 |
| --- | --- | --- |
| ルール | 配られた 5 枚から全員が秘密裏に 1 枚選び、残りを左隣に回す。次に渡された 4 枚から 1 枚選んで残りを左隣に回す。これを繰り返し、1 枚ずつ合計 5 枚が手札になる | ルールブック p.28（`data/rulebooks/p28@4x.png`）、001 §3-5 |
| オプション | 104 の値 3 = Draft（キーは予約済み）。マリガンとは排他（1 つのオプションの値）。`level: additional` | 001 §3-5、004 §7・004 未確定 7 |
| 進行 | 各回の全員の確定を待つ同期方式。全員が同時に自分の前のパックから 1 枚をクリックして [Confirm]。全員が確定するまでは選び直せる。全員が確定したら選んだカードが手札に入り、残りのパックを左隣に渡す | 008 §8-2、008 未確定 11、008 §2 |
| 左隣 | BGA の席順の次のプレイヤー（時計回り。基準プレイヤーの席順と合わせる）。席順の最後の人の左隣は先頭の人 | 008 §8-2、001 §2-2 |
| 見え方 | 他プレイヤーには確定済みの印だけ。パックの中身と選んだカードは非公開。選んだカードは手札に入った後も非公開 | 008 §6・§8-2 |
| zombie・タイムアウト | パックの先頭のカード。zombie になった人の左隣にも、パックは通常どおり渡る | 008 §9-1、009 §9.2 |
| 状態 | 12 `SetupHandRoute`: `draft` → 15。15 `SetupDraftPick`（MULTIPLE_ACTIVE_PLAYER。§5 の共通パターン。`allConfirmed` → 16）。16 `SetupDraftPass`（GAME。`next` → 15、`done` → 17）。実装すると状態は 31 個 | 009 §3.1・§3.3・§5・§9、009 未確定 8 |
| 売却 | 自分が行動中なら、同時選択中も含めて手札を [Sell] できる。ドラフト中も同じ規則で、取ったカードを含む手札を売れる（新しい規則ではない） | 001 §11-1、Q-11、009 §8（13・15 は可） |

### 2. 最後の 1 枚の扱い（既存の記述を直す）

4 回目に選んだ後、各パックに 1 枚が残る。**この 1 枚のパックを左隣に渡してから、受け取った人の手札に自動で入れる**。ルールブックの「渡された中から選ぶ」に合わせるためで、自分の 1 枚を自分の手札に入れるのではない。

- 選ぶ操作は **4 回**（パックが 5・4・3・2 枚のとき）。手札に入るのは **5 枚**（選んだ 4 枚 + 受け取った最後の 1 枚）。パックを渡すのは 4 回。
- 008 §8-2 の「これを 5 回行う」は、選ぶ回数ではなく「手札に入る枚数」と読み替える（修正済み）。009 §3.3 の 16 の「1 枚だけのパックは自動で手札に入れる」も、この意味に直した。
- 例（N 人）: 席順 `i` の人が、k 回目（1〜4）に選ぶパックは、最初に席順 `(i − (k − 1)) mod N` の人へ配られたパック。最後の 1 枚は `(i − 4) mod N` のパックから来る。2 人のときは 2 回目と 4 回目に相手のパックから選び、最後の 1 枚は自分が最初に配られたパックの残りになる。

### 3. データ

#### 3.1 パックの置き場所（010 §4.2）

`project_card` の `card_location` に **`pack`** を足す（`VARCHAR(12)` に収まる。スキーマ変更なし）。`CardLocation::Pack` を足す。

| location | location_arg | 意味 | order の使い方 | 公開範囲 |
| --- | --- | --- | --- | --- |
| `pack` | **今そのパックを持っているプレイヤーの ID** | ドラフトのパック（裏向き。ドラフト中だけ存在する） | パックの中の並び。**小さい順が「先頭」**（zombie の既定。配った順） | 本人: ID の一覧（並び順）。他人・観戦者: 枚数のみ（`hand` と同じ） |

- 配札: `setupNewGame` は、オプション 3 のとき `hand` の代わりに `pack` へ 5 枚ずつ配る。順序は手札と同じ（基準プレイヤーから時計回りに山札から直列に引く。010 §4.3）。`ProjectCardRepository::drawTop` の `$into` に `Pack` を許す（`nextOrder` で order は 0〜4）。
- 渡す: 全員のパックを**先に読んでから**書き換える。`UPDATE` は `card_id` 単位で `card_location_arg` だけを受け取る人の ID に変える（order は変えない）。`location_arg` を条件に順に書くと、渡した先のパックと混ざるので使わない。新メソッド `ProjectCardRepository::passPacks(array $order, bool $intoHand)`（`$intoHand` が真なら受け取る人の `hand` へ移す。最後の 1 枚用）と、`takeFromPack(int $playerId, int $cardId)`（`pack` → `hand`。`nextOrder(Hand)` で通し番号）を足す。
- 純粋関数 `Setup/PackPassing::receiver(array $order, int $holder): int`（席順の次の人。最後の人は先頭に戻る）を作り、渡し先の計算をここに閉じ込める（2・3・4 人を Unit でテストする）。
- 公開範囲の守り方: `idsIn(Pack, …)` は本人の視点でだけ呼ぶ（`hand`・`drawn` と同じ。010 §4.4）。`countIn(Pack, …)` は公開してよい。
- **何回目か（1〜4）は新しいグローバル値を作らない**。全員のパックの枚数は常に等しい（全員が 1 枚ずつ取り、全員が同じ枚数を受け取る）ので、`pickNo = HAND_SIZE − packSize + 1`（5 枚 → 1、4 枚 → 2、3 枚 → 3、2 枚 → 4）。全員の枚数が等しくないときは `LogicException`（整合性の検査）。総回数 `DRAFT_PICKS = HAND_SIZE − 1 = 4`。
- `choice_data` は **`{"card": ID}`**（010 §9.3 の表に 15 の行を足す）。ID は本人の今のパックのカード。
- 整合性（010 §12）: ドラフト中は `pack` の合計が `5 × 人数 − 渡して手札に入った枚数`、全員の `pack` の枚数が等しい。ドラフトが終わる（17 に入る）と `pack` は 0 枚。`deck` 99 − 5 × 人数はセットアップ直後から変わらない（売却は `discard` へ）。
- `Machine::sell` が確定内容の `card` を売却の予約に入れる処理は、15 では `card` がパックのカード（手札にない）なので結果に影響しない（手札の検査が先に失敗する）。

#### 3.2 オプションの解釈

- `StartingHand` に `case Draft = 3;` を足す。`GameOptions::fromTableOptions` は値 3 を受け付ける（今は `tryFrom` が null になり例外）。
- `SetupFlow::route(Draft)` は `'draft'`、`SetupHandRoute` の `transitions` に `'draft' => StateId::SETUP_DRAFT_PICK`。`StateId` に `SETUP_DRAFT_PICK = 15`、`SETUP_DRAFT_PASS = 16` を足す。

### 4. 状態の処理

#### 4.1 15 `SetupDraftPick`（MULTIPLE_ACTIVE_PLAYER）

`States/SetupDraftPick.php`。`ChooseState` を継承し、`SetupMulligan` と同じ作り（`allConfirmed` → 16）。

| 項目 | 内容 |
| --- | --- |
| 参加者 | 全員（パックは常に 1 枚以上あるので `canAct` は常に真。自動確定は起きない） |
| `actConfirmDraftPick(int $card)` | `#[PossibleAction] #[CheckAction(false)]`。`peConfirm(playerId, ['card' => $card])` |
| `actSell(?array $cardIds)` | `SetupMulligan` と同じ。`Machine::sellableStates()` に 15 を足す |
| 検証（`DraftPickStep::validate`） | `card` が整数。本人の今のパックにある。なければ `RuleViolation`（`Select a card from your pack` / `Card ${id} is not in your pack`） |
| zombie（`defaultChoice`） | 本人のパックの先頭（order の最小）の `{"card": ID}` |
| description | 他人: `Other players must pick a card from their pack`。本人: `${you} must pick a card from your pack`（詳しい指示は画面が `setTitle` で出す。§6） |

`Setup/DraftPickStep.php`（`ActionStep`）を作り、`ChoiceFlow::forState` に `SETUP_DRAFT_PICK` を足す。`resolve(mode, choices)` は 16 から呼ばれ、全員分を検証して（確定がない人は `defaultChoice`）、`takeFromPack` で選んだカードを手札へ移す。

#### 4.2 16 `SetupDraftPass`（GAME）

`States/SetupDraftPass.php`、処理は `SetupFlow::applyDraftPass()`（`applyMulligan` と同じ形）。

1. `DraftPickStep::resolve` で、全員の選んだカードを席順に手札へ入れる。
2. 残り枚数 `rest = packSize − 1`（全員同じ）を数える。
3. `rest >= 2`: `passPacks(order, intoHand: false)` で全パックを席順の次の人に渡し、`next`（→ 15）。
4. `rest == 1`（4 回目の後）: `passPacks(order, intoHand: true)` で、渡す先の人の手札に最後の 1 枚を入れ、`done`（→ 17）。
5. `rest == 0` は起こらない（5 枚から 4 回選ぶので、パックの最小は 2 枚）。起きたら `LogicException`。

通知は §5 の順に出す。

#### 4.3 12 `SetupHandRoute`

`draft` を返す（§3.2）。ドラフトとマリガンは 1 つのオプションの値なので、同時には選ばれない。

### 5. 通知と `getAllDatas`

#### 5.1 通知（010 §10.2 の続き。追補を含めて最後は N37 なので、N38 から）

| No. | 通知名 | 送り先 | args | ログ文言（英語原文） | 送る処理・クライアントでの効果 |
| --- | --- | --- | --- | --- | --- |
| N9（既存） | `handCardsAdded` | 本人 | `cards`、`zone` = `hand`、`reason` = **`draft`**（新）、`auto`（最後の 1 枚のとき真）、`card_name`、`i18n` | 通常: `You take ${card_name}`。`auto`: `You receive ${card_name} from the last pack`（**本人だけのログ**。他の reason は `''` のまま） | 16 の手順 1 と 4。取ったカード（最後の 1 枚）を手札に加える。他人には何も届かない |
| N38 | `draftPassed` | 全員 | `pick_no`（今終えた回。1〜4）、`pick_total`（4）、`last`（最後の 1 枚の配布なら真） | 通常: `Everyone took a card and passed the rest to the left (pick ${pick_no} of ${pick_total})`。`last`: `Everyone received the last card of the pack passed to them` | 16 の手順 3・4。**公開ログ**。カード ID は載せない |
| N39 | `draftPackReceived` | 本人 | `pack`（新しく前に来たパックの ID。並び順）、`pick_no`（次の回。`last` のときは送らない） | `''` | 16 の手順 3。クライアントのモデルの `myPack` を更新する（§5.3 の未確定 1） |
| N33（既存） | `countersUpdate` | 全員 | `players[pid].handCount` と、**`packCount`（新）** | `''` | 16 の最後に `ctx->sync()` が送る。手札・パックの枚数の更新 |

- 通知の順序（1 回の 16）: N9（席順に本人へ）→ N38 → N39（本人へ）→ N33。通知はリクエストの終わりにまとめて届く（010 §10.1）。
- 「本人」宛ての通知が N3・N9・N22・N25・N34 の 5 種（010 §10.2）から N39 を足して 6 種になる。N9 の `reason` の列挙に `draft` を足す。
- 全員の確定の印は既存の N2 `choiceMarked`、本人の確定内容の同期は N3 `choiceRecorded`（`summary` は `{"card": ID}`）。
- 公開通知に載せてよいのは、枚数と回の番号だけ（010 §10.3）。取ったカードの ID・パックの中身を N38・N33 に載せない（`PublicView` のテストで検出する）。

#### 5.2 `getAllDatas`（010 §8.2、`PublicView`）

- `myPack`: 本人のパックの ID（order の昇順）。他人・観戦者には空配列。010 §8.1 の表に「ドラフトのパック」の行を足す（本人: ID の一覧、他人・観戦者: 枚数のみ）。
- `playerData[pid].packCount`: 全員に枚数だけ。
- `handCount` は既存の `hand` の枚数のまま（ドラフト中は 0〜5）。

#### 5.3 args（010 §13）

15 の `getArgs`（`ChoiceArgs`。DB を読むだけ）:

| 公開 args | 内容 |
| --- | --- |
| `participants`、`confirmed` | 共通（`ChoiceArgs::build`） |
| `pickNo`、`pickTotal` | 何回目か（1〜4）と総回数 4。§3.1 の式で、全員のパックの枚数から求める |
| `packSize` | 各自のパックの枚数（全員同じ。公開してよい） |

| `_private[プレイヤー ID]` | 内容 |
| --- | --- |
| `myChoice` | `{"card": ID}` / null |
| `pack` | 本人のパックの ID（並び順。先頭が zombie の既定） |
| `hand` | 手札の ID（取ったカード。売却の候補） |
| `sellValue` | 1 枚あたりの売却額（`selling->price`） |

`ChoiceArgs::detail` に `SETUP_DRAFT_PICK => ['pack' => …, 'hand' => $hand, 'sellValue' => …]` を足し、公開部分（`pickNo`・`pickTotal`・`packSize`）は `build` の中の 91 の `keepCount` と同じ場所に足す。渡す相手・受け取る相手は args に載せず、クライアントが `getAllDatas.playerOrder` から計算する（席順は変わらない）。

### 6. 画面（012 と追補 2026-11-21 U-1）

状態クラス `src/modules/js/states/DraftState.js`（`ActionState` を継承）、ロジックは `src/modules/js/states/logic/draft.js`（Node で検査できる純粋関数）。`Game.js` に `this.bga.states.register('SetupDraftPick', new DraftState(this, bga))`。

| 要素 | 内容 |
| --- | --- |
| パック | パネル（`view.panel`。企画 91 の `keepPicker` と同じ作り）の中の `CardPicker`（`ids` = `pack`、`max: 1`、カード画像と名前）。前例は 012 未確定 17（引いたカードは手札のゾーンではなく `CardPicker`。作者回答 2026-11-15）。パックのカードは手札にないため |
| 見出し（パネルの欄ラベル） | `Pick ${pickNo} of ${pickTotal}: choose 1 of ${packSize} cards. You pass the rest to ${passTo}, and receive a pack from ${receiveFrom}`。2 人のときは相手が同じなので `…pass the rest to ${name} (and receive a pack from ${name})` と 1 人の名前に畳む。最後の回（`pickNo == pickTotal`）には `The last card of the next pack goes to your hand automatically` を添える |
| ステータスバー（タイトル） | `${you} must pick a card from your pack. Click the card you want to keep`（`setTitle`） |
| ボタン | **[Confirm]**（`primary`）。何も選んでいないときは押せない（`disabled`。U-1 の「今は使えない = 灰」）。確定後は共通パターン: [Change my choice]（`alert`）。選び直したときは U-1 の 22 と同じく [Confirm the new choice] |
| 確定済み | パネルのパックは残し、選んだカードを選択状態のまま操作不可にして `Picked` の札を付ける（`showPlan`。本人の画面だけ）。タイトル `Waiting for other players` |
| 手札の欄 | 既存の `ActionState` のまま（取ったカードが手札のゾーンに入る。0 枚のときは空）。[Sell cards] は手札の見出しの行（U-1 の 5）。売却中・全部売る確認も既存どおり。手札が 0 枚なら [Sell cards] は出さない（既存の挙動で確認する） |
| 他プレイヤー | タイトル `Waiting for ${names} to pick a card`（BGA 標準の待機表示）。パネルの確定の印は既存の N2 の処理。中身は何も見えない |
| プレイヤーパネル | 手札の枚数（既存）。パックの枚数は全員同じなので出さない（見出しに `packSize` がある） |
| 観戦者 | パックは出さない。`Waiting for players to pick a card` と確定の印だけ |
| 通知ハンドラ | `notifications.js` に `draftPassed`（全員の確定の印を外す。`pickNo` の更新はしない）と `draftPackReceived`（`m.myPack` を更新）。`handCardsAdded` は `reason` を見ない（既存どおり手札に足す）。`countersUpdate` は `packCount` を `m.players[pid].packCount` に入れる。モデルに `myPack`・`packCount` を足す |
| ロジック（`logic/draft.js`） | `draftNeighbors(order, me)`（渡す相手・受け取る相手）、`draftSelectionFrom(choice)`、`draftActionArgs(card)`（`{ card }`）、`draftPicks(picked, pack)`（パックにあるカードだけ残す）、`draftHeading(args, order, me)` |
| 012 の表 | 012 の「手札を使う選択の洗い出し」（B-1）にドラフトの行、U-1 の状態ごとのボタンの表に `15 ドラフト` の行を足した（本書の追記） |
| プレビュー | `tools/preview/make-state-samples.mjs` に `draft`（5 枚から選択中。クリックのステップ付き）、`draft-confirmed`（確定済み）、`draft-last-pick`（4 回目・2 枚・2 人プレイで相手の名前が 1 つ）を足し、`tools/preview/samples/states/` に生成物をコミットする |

### 7. オプションの表示（`src/gameoptions.jsonc`、004 §7）

- 104 の `values` に `"3": { "name": "Draft", "tmdisplay": "Draft" }` を足す（マリガンの `tmdisplay: "Mulligan"` と同じ形）。`default` は 1、`level` は `additional` のまま。
- 冒頭のコメント「値 104=3（Draft）は実装するまで載せない（キーは予約）」を、「値 104=3（Draft）は 013 で実装した」に直す。
- 004 §7 の表の 104 の行（「実装するまで `values` に含めない」）と、その下のドラフトの箇条書きも「013 の実装で載せる」に直した（本書の追記）。**載せるのは画面まで揃う PR**（§10）。

### 8. テスト

| 種類 | 対象 | 内容 |
| --- | --- | --- |
| PHPUnit Unit | `Setup/PackPassing` | 2・3・4 人で `receiver`（右回りでなく席順の次。最後の人は先頭へ）。席順が基準プレイヤーから始まること |
| PHPUnit Unit | `GameOptionsTest` | 104 = 3 が `StartingHand::Draft`（以前の「未実装は例外」のテストを直す）。1・2 は変わらない。0 などは例外 |
| PHPUnit Unit | `StateGraphTest` | 31 状態（`testTwentyNineStates…` を 31 に改題）。`EXPECTED` に 15（`allConfirmed → 16`）・16（`next → 15`、`done → 17`）を足し、12 に `draft → 15` を足す |
| PHPUnit Integration | `GameSetupTest` | オプション 3 で、全員の `pack` が 5 枚・`hand` が 0 枚。山札が 99 − 5 × 人数。オプション 1・2 では `pack` が 0 枚（既存の手札 5 枚のテストは変えない） |
| PHPUnit Integration（新規 `DraftTest`） | `DraftPickStep`・`SetupFlow` | 検証: パックにないカード・手札のカード・重複・未指定を拒否。zombie と確定のない参加者は先頭。2・3・4 人で、`(i − (k − 1)) mod N` のパックから選ぶこと（k = 1〜4）と、最後の 1 枚が `(i − 4) mod N` のパックから来ること。全員が最後は手札 5 枚、`pack` は 0 枚、カードの総数が保存される。ドラフト中に手札を全部売っても進み、最後の手札が 5 枚より少ないだけ。売却は 15 の参加者だけ可、パックのカードは売れない |
| PHPUnit Integration | `StateWalker`・`MachineRoundTest` | `StateWalker::enter` に `SETUP_DRAFT_PASS => applyDraftPass()` を足し、オプション 3 で 12 → 15 → 16 → 15 → … → 17 まで通る（15 は 4 回、16 は 4 回、`SETUP_MULLIGAN` は通らない）。既存のマリガンのテストは変えない |
| PHPUnit Integration | `PublicView` | 本人の `myPack` は ID、他人・観戦者は `[]` と `packCount` だけ。`getArgs` の公開部分にパックの中身がない。N38・N33 に ID がない（`handCardsAdded` は本人宛て 1 件だけ） |
| JS（`tests/js/state-logic.test.mjs`） | `logic/draft.js` | `draftNeighbors`（2 人のときは同じ相手、3・4 人、最後の人の次は先頭）、`draftPicks`（パックにないカードを落とす）、`draftActionArgs`、`draftSelectionFrom`、`draftHeading`（最後の回の添え書き） |
| 手動（Studio） | 実装 PR の後 | 2・3・4 人で通す。ドラフト中の再読み込み、zombie、売却（V-3・V-4 と合わせて確認） |

### 9. 影響を受ける既存の仕様書の注記

本書と同じ PR で、既存の本文は書き換えず、注記と最小の修正だけを入れた: 001 §3-5、004 §7、008 §8 の冒頭・§8-2・未確定 11、009 §3.1・§3.3・未確定 8、010 §4.2・§15、012 の表（B-1 とボタンの表）。PR #31 が 009・011・012・tasks.md の追補を編集しているので、衝突しないよう最小にとどめている。

### 10. 実装の PR の分け方（実装タスク I-22）

Studio は `main` へのマージごとに自動同期される。画面ができる前にロビーで Draft を選べると、テーブルが壊れる。値 3 を `gameoptions.jsonc` に載せるのは、画面まで揃う PR にする。

| PR | 内容 | `gameoptions.jsonc` |
| --- | --- | --- |
| 1/2 サーバー | `CardLocation::Pack`、`ProjectCardRepository`、`PackPassing`、`StartingHand::Draft`、`GameOptions`、`GameSetup`（パックの配札）、`StateId`、状態 15・16、`SetupHandRoute`、`SetupFlow`、`DraftPickStep`、`ChoiceFlow`・`ChoiceArgs`、`Notices`・`PublicView`・`FlowContext::counters`、PHP のテスト一式 | **載せない**（ロビーから選べない。`GameOptions` と状態は受け付けるのでテストはできる） |
| 2/2 クライアント + 公開 | `DraftState.js`、`logic/draft.js`、`Game.js`、`notifications.js`、モデル、CSS、プレビューのサンプル、JS のテスト、`gameoptions.jsonc` の値 3 とコメント、`docs/tasks.md`・仕様書のステータス（013 を Implemented） | **値 3 を載せる**（これで公開） |

- 案 B は 1 PR（サーバーとクライアントを一緒に）。差分が大きいので推奨しない（未確定事項 5）。
- 2/2 の後に Studio の Manage game でオプションの再読み込みが必要（作者の作業。tasks.md の作者の作業の表に足す）。

### 11. wiki のルール概要への申し送り

`docs/publishing/rules-summary.md`（PR #43 で作成中）の英語版・日本語版の「ゲームオプション」の節に、ドラフトを足す: 「最初の 5 枚のプロジェクトカードをドラフトで選ぶ（ルールブック p.28）。5 枚から全員が 1 枚選び、残りを左隣へ回し、4 枚・3 枚・2 枚と繰り返す。最後の 1 枚は左隣から回ってくる。BGA ではマリガンとどちらか一方を選べる」。追記は実装 PR（2/2）で行う（PR #43 がマージされていることが前提）。同じ文書の冒頭の注記と手順 8（「ドラフトの実装後に足す」）も消す。

## エッジケース

| 状況 | 挙動 |
| --- | --- |
| zombie・タイムアウト | パックの先頭を選んで確定する。そのプレイヤーの左隣にも、パックは通常どおり渡る（008 §9-1）。次の回の zombie も同じ |
| 途中の再読み込み | 15 の `getArgs`（`_private` の `pack`・`myChoice`、公開の `pickNo`）と `getAllDatas`（`myPack`・`packCount`・`myHand`）で、パックと確定内容と手札が復元される。16 は GAME 状態で一瞬なので、再読み込みで途中に入ることはない |
| 確定の後に再読み込み | `myChoice` で確定済みの表示（選んだカードに `Picked`、[Change my choice]）が復元される |
| ドラフト中に手札を全部売った | そのまま進む（売るのは取ったカードだけ。パックは売れない）。選ぶ回数は変わらず、最後の手札が 5 枚より少ないだけ。全部売る確認は既存のとおり |
| 2 人プレイ | 左隣は相手。パックは毎回入れ替わる。最後の 1 枚は自分が最初に配られたパックの残り（§2） |
| 3・4 人プレイ | 席順の次へ渡す。最後の人の次は先頭の人 |
| 全員が zombie | 009 未確定 4 のとおり。全員が既定（先頭）で進み、ドラフトは止まらない |
| 観戦者・リプレイ | パックの中身・取ったカード・確定内容は見えない。見えるのは確定の印、N38 の公開ログ、`packCount`・`handCount` だけ（取ったカードの N9 と N39 は本人宛て） |
| 同時の確定 | 全員確定の判定は DB を正とする（009 §5.4、008 V-4）。確定後の変更要求は他の同時選択と同じ穏当な文言で拒否（008 §2-8） |
| 確定内容の `card` を売ろうとした | 起きない（パックのカードは手札にないので、そもそも売却の候補ではない） |
| マリガンとの併用 | できない（オプションの値が 1 つ）。ドラフトの後にマリガンは行わない |
| 山札が少ない | 99 枚のうち 5 × 人数（最大 20）を配るだけ。ドラフトで山札・捨て札は変わらない |

## 未確定事項

作者への質問。答えがなければ案 A（推奨）で確定する。ルールそのものの疑問はない（ルールブック p.28・001 §3-5 で足りる）。表示・通知の選択を案にした。

| No. | 質問 | 案 A（推奨） | 案 B | 根拠の節 | 回答 |
| --- | --- | --- | --- | --- | --- |
| 1 | 新しいパックを本人に知らせる通知を別に作るか | **N39 `draftPackReceived`（本人宛て。パックの ID）を送る**。クライアントのモデルの `myPack` を通知で更新し、再読み込みは `getAllDatas` に揃える（010 §10.1「画面の更新は通知と `getAllDatas` で行う」。リプレイ・観戦で崩れないため） | 通知は作らず、15 の入場時の args（`_private.pack`）だけで更新する（通知が 1 つ減るが、状態の args に表示を頼る） | §5.1、010 §10.1 | |
| 2 | 取ったカード名の本人だけのログを、既存の N9 `handCardsAdded` に載せるか | **N9 に `reason: draft` を足し、`draft` のときだけメッセージ（`You take ${card_name}`）を付ける**（他の reason は `''` のまま）。新しい通知名が増えず、N9 が「手札に入る」唯一の経路のまま | ログ用に別の本人宛ての通知（例: `draftTaken`）を足し、N9 は `''` のまま（N9 の約束は崩れないが、通知の種類が増える） | §5.1、010 §10.2 | |
| 3 | 見出しに「渡す相手」と「受け取る相手」の両方を出すか | **両方出す**（`You pass the rest to X, and receive a pack from Y`）。どちらの方向か、誰のパックかが分かる。2 人プレイは 1 人の名前に畳む | 渡す相手（左隣）だけを出す（短い。受け取る相手は見出しに出ない） | §6 | |
| 4 | 確定した後、パックを画面に残すか | **残す**（選んだカードに `Picked`、操作不可）。何を選んだか本人が確認でき、[Change my choice] で戻せる | パックを隠し、タイトルに選んだカード名だけを出す（画面は短いが、他の候補が見えなくなる） | §6、012 未確定 17 | |
| 5 | 実装の PR の分け方 | **2 つ**（1/2 サーバー、2/2 クライアント + オプションの公開）。値 3 は 2/2 で載せ、画面前にロビーで選ばれてテーブルが壊れるのを避ける | 1 つ（差分が大きく、レビューが重い） | §10 | |
| 6 | ドラフトの値 3 を載せるとき、公開直後の数日は `alpha`（開発者・訓練向けの印）を付けるか | **付けない**（`level: additional` の通常の値。今の 104=2 と同じ扱い。004 §7 の「Public Alpha 前」の段階で他の人は少ない） | `"alpha": true` を付け、Studio の確認が済んでから外す（004 §7 の【確認】の手段） | §7、004 §7 | |

## 既存の仕様書と実装の食い違い（本書で直した、または実装で直す点）

| 場所 | 内容 | 対応 |
| --- | --- | --- |
| 008 §8-2 | 「これを 5 回行う。最後（1 枚だけのパック）は自動で手札に入る」。選ぶ操作は 4 回で、5 は手札に入る枚数 | 注記で直した |
| 009 §3.3（16） | 「1 枚だけのパックは自動で手札に入れる」。誰の手札かがあいまい（自分のパックの 1 枚と読めてしまう） | 「渡してから、受け取った人の手札に入れる」に直した |
| 009 §3.1・未確定 8 | 「ドラフトは初版では未実装」 | 013 で実装することを注記 |
| 010 §4.2・§15 | `pack` の行がない。手順 5 が「ドラフトは初版では未実装」 | 注記で足した |
| 010 §10.2 | 「本人」宛ての通知が 5 種。N9 の `reason` に `draft` がない | 本書の §5.1 で足す（実装時に 010 の表へ追記） |
| 実装 `StateGraphTest` | 29 状態を期待。15・16 がない | 実装 PR（1/2）で 31 状態に |
| 実装 `GameOptions`・`gameoptions.jsonc` | 値 3 は例外 / 載せない | 実装 PR（1/2・2/2）で |

## 受け入れ条件

- [ ] オプション 104 の値 3（Draft）でテーブルを作り、セットアップが 12 → 15 → 16 → 15 …（選ぶ 4 回）→ 17 まで進み、全員の手札が 5 枚になる（§2・§4）
- [ ] 最後の 1 枚が、パックを左隣に渡してから、受け取った人の手札に入る（自分の残りが自分に戻るのではない。2 人プレイを除く）。2・3・4 人のテストで、k 回目の取り元が `(i − (k − 1)) mod N`、最後の 1 枚が `(i − 4) mod N` のパックである（§2）
- [ ] パックは `card_location = pack`・`card_location_arg` = 今の持ち主・`card_order` の昇順で保存され、全員を読んでから書き換える。何回目かは新しいグローバル値なしで求める（§3.1）
- [ ] 15 の args が、公開（`participants`・`confirmed`・`pickNo`・`pickTotal`・`packSize`）と `_private`（`myChoice`・`pack`・`hand`・`sellValue`）に分かれ、他人のパックの中身・取ったカードがどの経路（args・通知・`getAllDatas`）にも載らない（§5）
- [ ] 全員が確定するまで選び直せ、確定後の変更は拒否される。zombie・タイムアウトはパックの先頭で進む（§4.1、008 §9-1）
- [ ] 15 の間、手札を [Sell] でき、パックのカードは売れない。手札を全部売ってもドラフトが進む（§1、エッジケース）
- [ ] 通知 N38・N39（本人）と、N9 の `reason: draft`、N33 の `packCount` が §5.1 のとおり送られ、公開ログに ID が出ない
- [ ] 再読み込み・観戦者・リプレイで、パックの中身が見えず、本人のパックと確定内容が復元される
- [ ] 画面が §6 のとおり（パネルの `CardPicker`、見出し、[Confirm]・[Change my choice] の色と並び、手札の欄と [Sell cards]、プレビューのサンプル）
- [ ] `gameoptions.jsonc` の値 3 は画面まで揃う PR（2/2）で載り、冒頭のコメントが直っている。状態は 31 個で `StateGraphTest` が通る（§7・§10）
- [ ] `npm run lint:md` がエラーなしで通る
