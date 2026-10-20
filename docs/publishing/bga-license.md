# BGA のライセンス申請（作者 = 権利者の場合）

PROJECT EDGEWORTH を BGA で公開するための、ライセンス（権利者からの許諾）の手続き。作者（だんじり協奏曲）がデザイナー兼出版社なので、**作者自身が BGA に許諾を出す**形になる。

## BGA の決まり（2026-10-20 時点の公式ドキュメント）

- アルファ（本番サーバーへの移行）には **BGA がそのゲームのライセンスを持っていること** が必須。ライセンスの状況が片付くまで、プロジェクトは移行されない（[Pre-release checklist](https://en.doc.boardgamearena.com/Pre-release_checklist)）。
- ベータには **出版社による実装の承認** が必須（同上）。
- ライセンスは無償。権利者は BGA に「go」を出すだけで、多くの場合は BGA と正式な契約を結ぶ（[BGA Game licences](https://en.doc.boardgamearena.com/BGA_Game_licences)、契約の雛形は [General terms and conditions of license](https://en.doc.boardgamearena.com/images/e/e0/BGA_TC_Pub_en.pdf)）。BGA に与えるのは、BGA 上でゲームを公開し、そのために素材を使う非独占の権利。
- BGA が公開するのは「どこかで買える、または近く買えるようになる」ゲームだけ。PROJECT EDGEWORTH は発売済みなので、販売先を示せば足りる。
- 連絡先: BGA Studio の窓口 `studio@boardgamearena.com`。アルファ申請のときは、申請の確認メールへの返信に許諾のメールを添える。

## 権利者であることの示し方

個人の制作者には会社の登記やドメインのメールのような分かりやすい証明がないので、**「だんじり協奏曲として公開している場所」と「BGA に連絡する本人」を結び付ける**ことで示す。BGA 側が確かめられるように、公開の場所にある情報で裏付けるのがよい。強い順に:

1. **公式サイトに BGA 版のことを書く（推奨）**: だんじり協奏曲のサイト（GitHub Pages。サークルだけが更新できる）の PROJECT EDGEWORTH のページに、「BGA 版は作者による公式の実装（Studio プロジェクト `projectedgeworth`、開発者 `ZoneTsuyoshi`）」と、BGA への連絡に使うメールアドレス（または連絡は X の DM で受ける旨）を載せる。サイトの持ち主しか書けないので、メールの送り主が権利者本人だと BGA が自分で確かめられる。
2. **公式の X（@danjiriconcerto）で同じことを告知する**: BGA に許諾を出したこと・BGA 版の開発者が `ZoneTsuyoshi` であることをポストし、その URL をメールに入れる。サイトと X の両方にあると確実。
3. **BGG の出版社・デザイナーのページ**: 出版社のページ（<https://boardgamegeek.com/boardgamepublisher/64662/danjiri-concerto>）に公式サイトのリンクがあれば、BGG → 公式サイト → BGA 版の告知とたどれる。デザイナーとして BGG のアカウントをページに紐付ける（BGG の designer/publisher の claim）と、さらに強い。
4. **販売の実績**: 頒布したイベント（ゲームマーケット 2026 秋）の出展ページ、通販のページ、製品の写真。「どこかで買える」ことの証明も兼ねる。
5. **原本のデータを持っていること**: 印刷用の原本（カード・ボードの高解像度データ）から BGA 用の画像を作っていることは、メールにも書いておく。求められれば原本の一部を渡せる。

メールの送信元は、1 の公式サイトに載せたアドレスにする（個人の Gmail でも、サイトに載っていれば裏付けになる）。サイトに載せたくなければ、「連絡は X の DM でも確認できる」と書き、BGA から X に DM してもらえるようにする。

## 手順

1. **（済）BGG のゲームページ**: <https://boardgamegeek.com/boardgame/481869/project-edgeworth>（BGG_ID 481869。`src/gameinfos.jsonc` に設定済み）。
2. **BGA Studio のライセンスのページを確認する**: Studio にログインして <https://studio.boardgamearena.com/#!licensing> を開き、PROJECT EDGEWORTH が既に載っていないか確認する（載っていれば手続きは不要）。載っていなければ 3 へ。
3. **権利者の裏付けを公開する**: 上の「権利者であることの示し方」の 1・2（公式サイトと X に BGA 版のことを載せる）。サイトの文面はエージェントが作れる（サイト側のリポジトリ `danjiri-concerto.github.io` に追記して公開する）。
4. **許諾のメールを送る**: 下の「メールの文面」を、公式サイトに載せたアドレスから `studio@boardgamearena.com` に送る。`[ ]` の箇所を埋めてから送る。
5. **BGA からの返信を待つ**: 契約書（上の General terms and conditions）への署名や、追加の情報を求められることがある。届いたらエージェントに共有してもらえれば、内容の確認と返信の下書きをする。
6. **許諾のメールを保存する**: 送ったメール（と BGA の返信）を、アルファ申請のときに添付・転送できるようにしておく。リポジトリには入れない（アドレスが載るため）。
7. **アルファ申請**: Studio のコントロールパネルの「Request ALPHA status」から申請し、届く確認メールに、正式なゲーム名・本番の BGA ユーザー名・（非公開アルファの招待者）・許諾のメールを添えて返信する。ベータの前に、出版社として実装を承認する旨も伝える（作者 = 出版社なので、同じメールで承認しておいてよい）。

MINIMUM JOURNEY（姉妹リポジトリ）も同じ手続きなので、1 通のメールで 2 作品まとめて許諾してもよい。そのときは下の文面の「ゲームの情報」を 2 作品分に増やす。

## メールの文面（英語。送る原文）

件名:

```text
License for BGA: PROJECT EDGEWORTH (designer and publisher: Danjiri Concerto)
```

本文:

```text
Dear Board Game Arena Studio team,

I am writing on behalf of Danjiri Concerto, the designer and publisher of the board game PROJECT EDGEWORTH. We hold all rights to the game (rules, card texts, and artwork).

We would like PROJECT EDGEWORTH to be available on Board Game Arena, and we hereby authorize Board Game Arena to publish an adaptation of the game on its platform and to use the game's materials (rules, texts, and artwork) for that purpose. We are happy to sign your standard license agreement if needed.

The adaptation is being developed by our own team in BGA Studio:
- Studio project name: projectedgeworth
- Developer (BGA Studio username): ZoneTsuyoshi
All graphics used in the adaptation are provided by us from the original print files.

Game information:
- Title: PROJECT EDGEWORTH
- Designer / Publisher: Danjiri Concerto
- Players: 2-4, about 40-60 minutes
- Released: 2026 (Game Market 2026 Autumn, Japan)
- BoardGameGeek: https://boardgamegeek.com/boardgame/481869/project-edgeworth
- Official page: https://danjiri-concerto.github.io/project-edgeworth/
- BoardGameGeek (publisher): https://boardgamegeek.com/boardgamepublisher/64662/danjiri-concerto
- Where to buy: [shop URL]
- Contact: https://x.com/danjiriconcerto

To confirm that this message comes from the rights holder:
- Our official website states that the BGA adaptation is ours and lists this email address: [URL of the official page with the BGA note]
- We announced it on our official X account: [URL of the post]
- The BGA graphics are made by us from the original print files; we can provide samples of the original files if needed.

As the publisher, we also approve this implementation for the Alpha and Beta phases, and we will take part in testing it.

Please let us know if you need anything else from us.

Best regards,
[Name]
Danjiri Concerto
```

## 文面の和訳（確認用。送らない）

> Board Game Arena Studio チーム御中
>
> ボードゲーム PROJECT EDGEWORTH のデザイナー兼出版社である、だんじり協奏曲よりご連絡します。私たちはこのゲームのすべての権利（ルール、カードの文、アートワーク）を持っています。
>
> PROJECT EDGEWORTH を Board Game Arena で遊べるようにしたいと考えており、Board Game Arena がこのゲームの翻案を自社のプラットフォームで公開すること、およびそのためにゲームの素材（ルール、文、アートワーク）を使うことを、ここに許諾します。必要であれば、標準のライセンス契約に署名します。
>
> 翻案は私たちのチームが BGA Studio で開発しています（プロジェクト名 projectedgeworth、開発者 ZoneTsuyoshi）。翻案で使う画像はすべて、私たちが印刷用の原本から提供しています。
>
> （ゲームの情報）
>
> このメールが権利者からのものであることの確認: 公式サイトに BGA 版が私たちのものであることとこのアドレスを載せています（URL）。公式の X でも告知しました（URL）。BGA 用の画像は私たちが印刷用の原本から作っており、必要なら原本の一部を提供できます。
>
> 出版社として、この実装をアルファとベータの段階でも承認し、テストにも参加します。
>
> ほかに必要なものがあればお知らせください。

## 記録

| 日付 | 内容 |
| --- | --- |
| 2026-10-20 | BGG のゲームページが承認された（BGG_ID 481869）。この手順と文面を作成 |
| 2026-10-20 | BGG の出版社のページ（ID 64662）を設定。権利者であることの示し方を追加 |
