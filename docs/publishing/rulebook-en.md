# 英語版ルールブック（PDF）

印刷版のルールブック（`data/rulebooks/` のページ画像、全 28 ページ）を英語にした PDF の作り方と、翻訳の決まり。BGA や BoardGameGeek で遊ぶ海外のプレイヤーに渡すためのもの。

- 成果物: `build/rulebook-en/PROJECT-EDGEWORTH-Rulebook-EN.pdf`（Git 管理外。原本の図版を含むためコミットしない）
- 原稿とビルド: [`tools/rulebook-en/`](../../tools/rulebook-en/README.md)、`npm run build:rulebook-en`
- ビルドには作者の手元の `data/` と Google Chrome が必要

## 1. 方式

原本のページ画像をそのまま下地にし、日本語の文字だけを背景と同じ模様・色で覆い、同じ位置に英語の文字（PDF 上は文字として選択・検索できる）を重ねる。図・枠・見出しの帯・ページ番号は原本のまま使う。ページ構成と見開きは原本（`data/rulebooks/rulebook.pdf`）と同じにする。

カードやコマの画像の中の日本語（カード名・効果文）は製品の姿なので、そのまま残す。

英語の文字は、原本の日本語と同じ大きさ・行送りを目標にする（本文・図の注釈は 38 px = 9.5 pt、例の箱は 34 px。原本 PNG の px）。英語は日本語より短く収まるので、小さい字にすると箱の中や段落の下に原本には無い空きができる。図や表で幅・高さが足りない所だけ、そのシートの同じ役割の文字をそろえて小さくする。原本より大きくはしない。図・見出しの札の位置は原本のままなので、短い段落の下の空きは原本と同じ程度に残る。

## 2. 翻訳の方針

1. **印刷版に忠実に訳す。** ルールを足さない、削らない、直さない。FAQ の裁定や BGA 版だけの扱い（売却は自分の行動中のみ、など）は入れない。
2. **エラッタは反映する。** 公式の[エラッタ](https://danjiri-concerto.github.io/project-edgeworth/errata.html)にあるルールブックの訂正（p.22、p.25）は訂正後の値で書く。
3. **用語は BGA 版の英語に合わせる。** 下の用語集と、仕様書 [002](../specs/002-card-data.md) のカード名・効果文、[rules-summary.md](rules-summary.md) の英語版に従う。
4. **読み取れない・二通りに読める文は推測で埋めない。** 仕様書 [001](../specs/001-game-rules.md) は日本語の意味を確かめるためだけに使い、そこにしかない内容は足さない。迷った箇所は報告する。
5. **文体。** アメリカ英語。短く平易な文。プレイヤーへの指示は命令形か "Each player …"。原本の助言（「〜するとよいでしょう」）は "We recommend …" などで残す。数は数字で書く。
6. **表記。**
   - アクション名は BGA と同じ `I Budget` / `II Deploy` / `III Launch / Turn` / `IV Establish Link` / `V Proposal`。
   - カードの種別は印刷どおり大文字の `CREW` / `DEVICE` / `SOFTWARE`。衛星のプラン名・形状名も印刷どおり（`MOON`、`HAMMER` など）。
   - カード名は仕様書 002 の英語名。原本がカード番号を添えている箇所は番号も書く（例: `Continental Antenna (P052)`）。
   - 例に出るプレイヤー名は Teresa (blue) / Lily (yellow) / Jessie (green) / Luna (red)。
   - `Maya`・`Budget` は大文字始まり、`fame` は文中では小文字。
   - カードの種類は `Project card` / `Mission card` / `Reward card`（card は小文字）。`Action Card` だけ C も大文字。`satellite card`・`region board`・`launch base` は小文字。見出しの札の中は各語の頭を大文字にしてよい。
   - 例の人物に she / he を使わない。名前を繰り返すか they / their を使う。
   - 「発動」は applies / takes effect / is triggered と訳し、activate は「起動」（Activated）だけに使う。
   - 六角形の 6 方向は upper right / lower right / upper left / lower left / left / right（"up-right" は使わない）。

## 3. 用語集

| 日本語 | 英語 | 備考 |
| --- | --- | --- |
| ラウンド | round | |
| 計画フェイズ | Planning Phase | |
| アクションフェイズ | Action Phase | |
| 予算発行フェイズ | Budget Phase | アクション名は I Budget |
| 配備フェイズ | Deploy Phase | II Deploy |
| 打ち上げ / 転回フェイズ | Launch / Turn Phase | III Launch / Turn |
| 通信確立フェイズ | Establish Link Phase | IV Establish Link |
| 企画フェイズ | Proposal Phase | V Proposal |
| 循環フェイズ | Orbit Phase | |
| 終了条件判定 / ゲーム終了判定 | End check | |
| メインアクション | Main Action | |
| アクション選択ボーナス | Action Selection Bonus | |
| アクションカード | Action Card | |
| アクションカードの回収 / アクションプロット / アクション公開 | Retrieve Action Cards / Plot an Action / Reveal Actions | |
| 縦向き / 横向き | upright / sideways | アクションカードの向き |
| 地域ボード / 地域 | region board / region | |
| 区域 | area | |
| 地域アイコン / 地域境界線 | region icon / region border | |
| 表面 / 裏面 | front side / back side | |
| 海 / 陸 | Sea / Land | |
| 標準海 / 深海 / 岩礁 | Standard Sea / Deep Sea / Reef | |
| 標準陸 / 森林 / 山岳 / 雪原 | Standard Land / Forest / Mountain / Snowfield | |
| 区域コスト / 区域名声 / 区域ボーナス | area cost / area fame / area bonus | |
| 通信確立（する） | establish a link（動詞は link も可） | 相手の区域は with（establish a link with an area）。BGA のカード文と同じ。例: link an area, linked areas |
| 通信確立コスト | link cost | 区域コスト＋先駆コスト |
| 先駆コスト | pioneer cost | |
| 特殊通信確立コスト / 特殊コスト | special link cost / special cost | |
| 通信確立可能区域 | linkable area | |
| キューブ | cube | |
| 発射拠点（コマ） | launch base | |
| 円盤コマ | disc | |
| 燃料コマ | fuel marker | |
| 衛星コマ | satellite piece | |
| マヤチップ | Maya chip | |
| サプライ | supply | |
| 予算トラック / 名声トラック | Budget track / Fame track | |
| 予算 | Budget | |
| マヤ / 資金 / 所持金 | Maya / funds / Maya on hand | |
| 名声 | fame | |
| プロジェクトカード | Project card | |
| 配備 / 配備コスト / 追加配備コスト | deploy / deployment cost / additional deployment cost | |
| 効果発動タイミング | effect timing | |
| 効果タイプ | effect type | |
| 常時 / 起動 / 配備時 / 打ち上げ時 | Constant / Activated / On deploy / On launch | |
| 稼働中 / 非稼働 | active / standby | BGA の表記に合わせる |
| 効果発動の稼働条件 | active condition | |
| 燃料 / 燃料庫 / 初期燃料庫 | fuel / fuel slot / initial fuel slot | |
| 衛星カード / プラン | satellite card / plan | |
| 衛星の形状名称（シリーズ名） | satellite shape name (series name) | |
| 衛星格納庫 | satellite hangar | |
| 打ち上げ / 打ち上げコスト | launch / launch cost | |
| 転回 | turn | |
| 予算上昇 | Budget increase | 打ち上げ / 転回のボーナス |
| ミッションカード | Mission card | |
| 褒賞カード（褒章カード） | Reward card | 原本は表記が揺れているが同じもの |
| 基準プレイヤー | base player | |
| 個人盤面 / 手札 / 捨て場 | player area / hand / discard pile | |
| 循環 | orbit | |
| 進行方向 | heading | |
| 進行方向前後の隣接する区域 | the adjacent areas directly ahead of or behind it | |
| 反射 | bounce | 転回（turn）とは別 |
| ニアミス | near miss | |
| 着陸 / 着水 | landing / splashdown | 動詞は land / splash down |
| 燃料切れ | out of fuel | |
| 得点計算例 | Scoring example | |
| 例 | Example | 「例 1:」は "Example 1:" |

## 4. 原本から変えるところ

| 箇所 | 原本 | 英語版 | 理由 |
| --- | --- | --- | --- |
| p.22 VIII | テレサは海区域を 13 箇所 | 14 | 公式エラッタ |
| p.25 合計 | = 164 | = 184 | 公式エラッタ |
| p.24 見出し | その他 (I,II,X) の名声 | Other fame (I, II, IX) | 項目は I・II・IX。誤記とみて直す（エラッタ未掲載。作者に報告） |
| p.20 例の右上（P056 の効果文の引用） | 「…進行方向**後方**の隣接する区域…」（P057 の文面） | P056 の文面（the adjacent area **ahead** of this satellite's heading）。BGA のカード文と同じ | 例は P056「前方広域送信機」と前方の区域を説明しており、印刷のカード P056 も「前方」。誤記とみて直す（エラッタ未掲載。作者に報告） |
| p.20 最後の助言 | プロジェクトカードは名声に繋がらないため、全て捨ててマヤと交換するとよい | Project cards **left in your hand** do not count toward fame, … | 配備したカードは名声になる（VII）。捨てられるのは手札だけなので、手札のことだと分かるよう補った |
| p.13 配備の例 | 太陽光パネル | Solar Panel | 同名のカードはない。コスト 6 が一致する P049「太陽電池パネル」のことと判断 |
| p.8 番号 3 | 「…入るわけではありません すなわち…」（句点なし） | 2 文に分けた | 句点の抜け |
| p.28 見出しの帯 | ACKOWLEGEMENT | ACKNOWLEDGEMENTS | 英語の綴り |
| p.28 謝辞の名前 | 日本語 | ローマ字（下の表） | 作者の決定（2026-11-26）。読みが確かでない名前は作者に確認中（6 節 No. 9） |
| p.28 FAQ | 公式サイトの FAQ | FAQ (in Japanese) と添えた | FAQ のページは日本語のみ |
| p.3 | — | "Component images show the Japanese edition." の注記を足した | カード・ボードの画像は日本語版のまま載せているため |
| 表記の揺れ | 褒賞カード / 褒章カード | Reward card に統一 | |

p.25 の合計は、原本の「164」の 6 の欠けを 1 か所塞いで「184」にしている（ほかの数字と同じ字形のまま）。

p.28 の謝辞の表記（ヘボン式、長音記号なし。名前の順は原本のまま）:

| 小見出し | 原本 | 英語版 |
| --- | --- | --- |
| Production & Copyright | だんじり協奏曲（ぞね、まや、ひのき、たづくり） | Danjiri Concerto (Zone, Maya, Hinoki, Tazukuri) |
| Artwork | ぞね、DALLE | Zone, DALLE |
| Playtesters | 國生ミカド、衛良タケル、しょーせい、まさと、KB、ふうか、しんちゃん、社長、みなみ、ガマ、Dason、かしゃい、サイトウクン、かいと、にわ、かれん、あっさい | Kokusho Mikado, Eira Takeru, Shosei, Masato, KB, Fuka, Shin-chan, Shacho, Minami, Gama, Dason, Kashai, Saito-kun, Kaito, Niwa, Karen, Assai |

## 5. 作り方と確認

1. 原稿（`tools/rulebook-en/pages/<シート>.html`）を直す。書き方は [`tools/rulebook-en/README.md`](../../tools/rulebook-en/README.md)。
2. `node scripts/build-rulebook-en.mjs --only <シート> --png` で、はみ出し 0・縮小 0 を確かめ、`build/rulebook-en/preview/` の画像を原本と見比べる。
3. `npm run build:rulebook-en` で全ページの PDF を作る。

PDF は原本の図版（カード・ボード・ルールブックの絵）を含むので、このリポジトリにはコミットしない。公開するときは作者が公式サイトなどに置く。

## 6. 作者に確認したい点

回答がなければ「案」のまま進める。

| No. | 箇所 | 確認したいこと | 案 | 状態 |
| --- | --- | --- | --- | --- |
| 1 | p.20 の例の右上、P056 の効果文の引用 | 原本は「進行方向後方」（P057 の文面）。誤記か | 誤記として、英語版は「前方」（P056 の文面）にした。日本語版のエラッタに足すかは作者の判断 | 返答なし（案のまま） |
| 2 | p.24 の見出し「その他 (I,II,X) の名声」 | X は IX の誤記か | 誤記として、英語版は (I, II, IX) にした。同上 | 返答なし（案のまま） |
| 3 | p.13 の例の「太陽光パネル」 | P049「太陽電池パネル」のことか | そう判断して Solar Panel と訳した | 返答なし（案のまま） |
| 4 | p.20 の最後の助言 | 「手札の」を補ってよいか | 補った（4 節） | 返答なし（案のまま） |
| 5 | p.28 の謝辞の名前 | ローマ字にするか | 日本語のまま | **回答（2026-11-26）: ローマ字にする。** 表記は 4 節、確かでない読みは No. 9 |
| 6 | 例のプレイヤー名 | テレサ / リリー / ジェシー / ルーナの綴り | Teresa / Lily / Jessie / Luna | **回答（2026-11-26）: 案のとおり** |
| 7 | 用語 | 用語集（3 節）の訳語。とくに 非稼働 = standby、基準プレイヤー = base player、先駆コスト = pioneer cost、循環 = orbit、反射 = bounce | BGA 版の英語に合わせた。変える場合は BGA 版の文言もそろえる | **回答（2026-11-26）: 案のとおり** |
| 8 | 公開のしかた | PDF をどこに置くか（公式サイト、BGG、BGA のゲームページのリンクなど） | 作者が決める。エージェントは PDF を作るところまで | **回答（2026-11-26）: 公開は作者が行う** |
| 9 | p.28 の謝辞のローマ字 | 読みが確かでない名前の表記。國生 = Kokusho、衛良 = Eira（Era かもしれない）、社長 = Shacho（そのままローマ字でよいか）、ぞね = Zone。長音は Shosei・Fuka（Shousei・Fuuka とも書ける） | 4 節の表のとおり | 確認待ち |

## 7. 記録

| 日付 | 内容 |
| --- | --- |
| 2026-11-26 | 方針・用語集を作成。全 28 ページの初版を作成（作者の確認待ち） |
| 2026-11-26 | 作者の確認を反映。空きを詰めるため、英語の文字の大きさ・行送りを原本に近づけた（本文・注釈 32・30 px → 38 px、例の箱 28 px → 34 px を上限に、シートごと・役割ごとに調整。訳文は変えていない）。謝辞の名前をローマ字にした。用語・プレイヤー名は確定。公開は作者が行う。初版からあった日本語の縁の消し残し（p.11、p.21、p.28）と、p.16 の "Area cost :" のコロン前の空白も直した |
