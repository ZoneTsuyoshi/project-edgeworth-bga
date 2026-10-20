# ゲーム紹介文（英語）と BGG 登録の手順

BoardGameGeek（BGG）のゲーム登録と、BGA の Game Metadata Manager の説明文に使う英語の紹介文。ルールの内容はルールブック（`data/rulebooks/`）と公式ページ <https://danjiri-concerto.github.io/project-edgeworth/> に基づく。

2026-10-19 に作者が BGG へ申請し、2026-10-20 に承認された。

| 項目 | 値 |
| --- | --- |
| BGG_ID | 481869（<https://boardgamegeek.com/boardgame/481869/project-edgeworth>） |

## 登録の手順

1. BGG にログインし、メニューの **Community → Contribute → Board Game** から登録画面を開く（公式の手順: [BGG Guide to Game Submissions](https://boardgamegeek.com/wiki/page/BGG_Guide_to_Game_Submissions)、[Guide for Designers and Publishers](https://boardgamegeek.com/wiki/page/Guide_for_Designers_and_Publishers)）。
2. 下の「基本情報」「紹介文」「カテゴリーとメカニクス」を入力する。
3. デザイナー・出版社（だんじり協奏曲）は MINIMUM JOURNEY の登録（2026-10-15 申請）で作った項目を選ぶ。まだ承認待ちで検索に出ない場合は、同じ英語表記で入力する（表記ゆれで二重登録にならないよう注意）。
4. 申請後、BGG の管理者の承認を待つ。承認後のページの URL（`boardgamegeek.com/boardgame/<数字>/...`）の数字が BGG_ID。エージェントに伝えてもらえれば `src/gameinfos.jsonc` とこのファイルに記録する。
5. 承認後、ゲームのページに箱絵（`data/box/box@4x.png`）をアップロードする（画像は承認後に別途追加する仕組み）。

## 短い説明（Short description）

BGG の短い説明欄用（85 文字以内。下の文は 78 文字）。

```text
Launch satellites, follow rivals' actions, and build the best orbital network.
```

## 説明文（Description）

```text
Low Earth orbit is no longer empty space. Nations and corporations race for limited orbital slots and frequency bands, and a new international agreement has been signed: PROJECT EDGEWORTH, a worldwide communications infrastructure project led by the Orbital Communications Council (OCC). Selected organizations will build their own satellite networks, prove them in the field, and compete for the world's trust and fame.

In PROJECT EDGEWORTH, you command one of these organizations. Equip your satellites with CREW, DEVICE, and SOFTWARE project cards, launch them from your launch base, and let them circle the Earth one region per round. Only the region directly beneath a satellite can be connected, so where, when, and in which direction you launch shapes your whole network. The left and right edges of the modular board wrap around the globe, and satellites bounce off the poles.

Each round, all players secretly choose one of five action cards and reveal them at the same time: Budget, Deploy, Launch/Turn, Connect, and Plan. Only the actions chosen by someone are carried out this round, but everyone may perform them, while the players who chose an action also receive its bonus. You cannot play the same card again in the next round, so reading what your rivals will pick matters as much as deciding what you want to do.

Watch your fuel: satellites that share a region with another satellite suffer a near miss and burn extra fuel, and a satellite out of fuel lands or splashes down, leaving orbit until you launch it again.

At the end of the game, earn fame for your budget, money, connected regions, the variety of continents you reach, your secret mission shapes, your active satellites and their project cards, and two shared award cards. The game ends when a player has all five satellites in orbit or has placed all sixteen connection cubes.

With double-sided modular boards, 99 unique project cards, and player-specific satellite plans, every game builds a different network.
```

## 基本情報

| 項目 | 値 |
| --- | --- |
| 名前 | PROJECT EDGEWORTH |
| 別名 | プロジェクト・エッジワース（日本語表記は作者に確認） |
| 人数 | 2–4 |
| 対象年齢 | **作者に確認**（ルールブックに記載なし。案: 12+） |
| プレイ時間 | 40–60 分 |
| 発売年 | 2026（ゲームマーケット 2026 秋） |
| 複雑度 | ルールブック記載の 2.8 / 5（BGG の Weight はユーザー投票で決まるため入力欄はない） |
| デザイナー | だんじり協奏曲（ぞね、まや、ひのき、たづくり）。MINIMUM JOURNEY の登録と同じ英語表記にする |
| アーティスト | ぞね（ルールブックの記載は「ぞね、DALLE」。DALL-E を Artist に入れるかは作者に確認） |
| 出版社 | だんじり協奏曲（MINIMUM JOURNEY の登録と同じ英語表記） |

## カテゴリーとメカニクス（BGG の選択肢から）

| 種類 | 候補 | 理由 |
| --- | --- | --- |
| Category | Science Fiction | 近未来の通信衛星網が題材 |
| Category | Space Exploration | 衛星の打ち上げと運用 |
| Category | Territory Building | 区域に通信確立のキューブを置いて広げる |
| Mechanism | Simultaneous Action Selection | アクションカードを全員同時に伏せて選ぶ |
| Mechanism | Follow | 他のプレイヤーが選んだアクションを自分も実行できる |
| Mechanism | Action Drafting | 5 種のアクションから選ぶ（直前に使ったカードは使えない） |
| Mechanism | Hand Management | プロジェクトカードの手札をやりくりし、3 マヤに換金もできる |
| Mechanism | Modular Board | 両面の地域ボード 8 枚を 2×4 に並べる |
| Mechanism | Hexagon Grid | 区域は六角形 |
| Mechanism | Programmed Movement | 衛星は打ち上げた向きに毎ラウンド自動で進む |
| Mechanism | Pattern Building | ミッションカードの形に通信確立する |
| Mechanism | Hidden Victory Points | ミッションカードはゲーム終了まで非公開 |
| Mechanism | Area Majority / Influence | 褒賞カードで比較内容の順位に応じて名声 |
| Mechanism | Variable Player Powers | 色ごとに異なる衛星プラン（MOON 以外の面） |
| Mechanism | End Game Bonuses | 褒賞・ミッション・地域種類数の名声はゲーム終了時 |

## 作者に確認すること

申請時に作者が決めた値は未記録（必要になったら確認する）。

| No. | 質問 | 案 |
| --- | --- | --- |
| 1 | 対象年齢 | 12+ |
| 2 | 日本語の別名の表記 | プロジェクト・エッジワース |
| 3 | アーティスト欄に DALL-E を入れるか | 入れない（ぞねのみ） |
