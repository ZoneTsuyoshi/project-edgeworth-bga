# BGA のライセンス申請（作者 = 権利者の場合）

PROJECT EDGEWORTH を BGA で公開するための、ライセンス（権利者からの許諾）の手続き。作者（だんじり協奏曲）がデザイナー兼出版社なので、**作者自身が BGA に許諾を出す**形になる。

## BGA の決まり（2026-10-20 時点の公式ドキュメント）

- アルファ（本番サーバーへの移行）には **BGA がそのゲームのライセンスを持っていること** が必須。ライセンスの状況が片付くまで、プロジェクトは移行されない（[Pre-release checklist](https://en.doc.boardgamearena.com/Pre-release_checklist)）。
- ベータには **出版社による実装の承認** が必須（同上）。
- ライセンスは無償。権利者は BGA に「go」を出すだけで、多くの場合は BGA と正式な契約を結ぶ（[BGA Game licences](https://en.doc.boardgamearena.com/BGA_Game_licences)、契約の雛形は [General terms and conditions of license](https://en.doc.boardgamearena.com/images/e/e0/BGA_TC_Pub_en.pdf)）。BGA に与えるのは、BGA 上でゲームを公開し、そのために素材を使う非独占の権利。
- BGA が公開するのは「どこかで買える、または近く買えるようになる」ゲームだけ。PROJECT EDGEWORTH は発売済みなので、販売先を示せば足りる。
- 連絡先: BGA Studio の窓口 `studio@boardgamearena.com`。アルファ申請のときは、申請の確認メールへの返信に許諾のメールを添える。

## 手順

1. **（済）BGG のゲームページ**: <https://boardgamegeek.com/boardgame/481869/project-edgeworth>（BGG_ID 481869。`src/gameinfos.jsonc` に設定済み）。
2. **BGA Studio のライセンスのページを確認する**: Studio にログインして <https://studio.boardgamearena.com/#!licensing> を開き、PROJECT EDGEWORTH が既に載っていないか確認する（載っていれば手続きは不要）。載っていなければ 3 へ。
3. **許諾のメールを送る**: 下の「メールの文面」を、**だんじり協奏曲として公開している連絡先のアドレス**から `studio@boardgamearena.com` に送る（権利者本人からの連絡だと分かるように。個人のアドレスしかなければそれでもよいが、公式サイトか X に同じアドレスか本人確認の手段があると早い）。`[ ]` の箇所を埋めてから送る。
4. **BGA からの返信を待つ**: 契約書（上の General terms and conditions）への署名や、追加の情報を求められることがある。届いたらエージェントに共有してもらえれば、内容の確認と返信の下書きをする。
5. **許諾のメールを保存する**: 送ったメール（と BGA の返信）を、アルファ申請のときに添付・転送できるようにしておく。リポジトリには入れない（アドレスが載るため）。
6. **アルファ申請**: Studio のコントロールパネルの「Request ALPHA status」から申請し、届く確認メールに、正式なゲーム名・本番の BGA ユーザー名・（非公開アルファの招待者）・許諾のメールを添えて返信する。ベータの前に、出版社として実装を承認する旨も伝える（作者 = 出版社なので、同じメールで承認しておいてよい）。

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
- Where to buy: [shop URL]
- Contact: https://x.com/danjiriconcerto

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
> 出版社として、この実装をアルファとベータの段階でも承認し、テストにも参加します。
>
> ほかに必要なものがあればお知らせください。

## 記録

| 日付 | 内容 |
| --- | --- |
| 2026-10-20 | BGG のゲームページが承認された（BGG_ID 481869）。この手順と文面を作成 |
