# ルール概要（Gamehelp）の文面と掲載手順

BGA のゲームページと対局画面に表示される「ルール概要（Rules summary）」の文面。中身は BGA の wiki のページ `Gamehelpprojectedgeworth` で、読者の言語のページがあればそれを、なければ英語版を表示する。このファイルを原本とし、wiki へは貼り付けて掲載する。

根拠（確認日 2026-11-10。minimum-journey-bga の同じ文書の確認を流用）:

- <https://en.doc.boardgamearena.com/Help>（wiki のヘルプ一覧）
- <https://en.doc.boardgamearena.com/Wiki_formatting>（wiki の書式）

ルールの内容は仕様書 [001](../specs/001-game-rules.md)（作者の裁定を含む）とルールブックに基づく。BGA 版で紙と扱いが違う点（売却は自分の行動中のみ、同時処理、セットアップの配備フェイズは全員同時、基準プレイヤーは席順の 1 番手）は BGA 版の扱いを書いている。画面の英語は `src/modules/js/`、`src/modules/php/`、`src/modules/php/Material/CardTexts.php`、`src/gameoptions.jsonc`、`src/gamepreferences.jsonc` の文字列に合わせている。

## 載せる場所

| 言語 | URL |
| --- | --- |
| 英語 | <https://en.doc.boardgamearena.com/Gamehelpprojectedgeworth> |
| 日本語 | <https://ja.doc.boardgamearena.com/Gamehelpprojectedgeworth> |

## 作者がやる手順

1. BGA のアカウントで wiki にログインする（未ログインでページを開くと「Login required」と表示される）。
2. 英語版の URL を開き、ページを作成する（英語版を先に作る）。
3. 下の「英語版」のコードブロックの中身をそのまま貼る。
4. プレビューで崩れがないか確認し（見出し、箇条書きの代わりの `•`、行間）、保存する。
5. 日本語版の URL でも同様に、「日本語版」の中身を貼って保存する。
6. ゲームページの「ルール概要」と対局画面で表示を確認する。
7. ルールや画面の用語が変わったら、このファイルを先に直してから wiki を更新する。

## 書き方の決まり

BGA での表示に合わせた制約（minimum-journey-bga と同じ）。

- 見出しは `== 見出し ==`（h2）と `==== 見出し ====`（h4）だけを使う。h1 はページのタイトル用、h3 は対局画面で前の節の文に重なるので使わない。
- 箇条書きの `*` `#` と字下げは消える。`•` や `1.` を文字として書き、項目を空行で区切る。
- 表は崩れやすいので使わない。画像も使わない。
- 対局画面では h2 の節ごとに枠が分かれるので、節単位で読んで完結させる。
- 外部リンクは公式サイトだけにし、最後に 1 か所入れる。
- 先頭に `__NOTOC__` を置き、自動の目次を出さない。
- 分量は英語版で 1,000〜1,600 語を目安にする（MINIMUM JOURNEY より規則が多いため）。全カードの効果は載せない（ゲーム中にカードのツールチップで見られる）。

## 英語版

```text
__NOTOC__
'''PROJECT EDGEWORTH''' is a game for 2 to 4 players (40-60 minutes). You run an organization that builds a network of communications satellites: equip satellites with Project cards, launch them, and establish links with the areas beneath them.

== Goal ==

At the end of the game, each player adds up fame from Budget, Maya, linked areas and regions, secret Missions, satellites, Project cards, two shared Rewards and the end condition. The most fame wins; equal totals share the victory.

== What you see ==

• '''Board.''' Double-sided region boards (A to H, 3x3 areas each) form the map, normally 2x4. The left and right edges connect around the globe, and satellites bounce off the top and bottom edges. A Sea area costs 2 Maya to link and gives 1 fame. A Land area costs 5 Maya and gives 2 fame. On the Advanced (back) side, some areas give a bonus when you link them: Deep sea recovers 2 fuel for the first link and 1 for the second, Forest gives 5 Maya and Mountain draws 1 Project card for the first link, Snowfield raises your Budget by 1 for every link, and a Reef lowers your cost for neighboring Land areas by 1.

• '''Satellites.''' Each player has 5 satellite cards. A satellite is '''active''' when its piece is on the board and on '''standby''' when the piece is on its card. Up to 4 Project cards can be deployed to each satellite.

• '''Project cards.''' Crew, Device and Software cards. Their effects appear in a tooltip.

• '''Action Cards.''' Five cards: I Budget, II Deploy, III Launch / Turn, IV Establish Link, V Proposal.

• '''Maya, Budget and fame.''' Maya is your money. Budget is the Maya you gain in the Budget phase, and it counts as fame at the end.

== Setup ==

• Each player starts with Budget 15, 5 standby satellites, 16 link cubes, 5 Action Cards and 2 secret Missions. Everyone also gets 5 Project cards and starting Maya by seat order: 30 / 31 / 32 / 33 (Standard boards) or 28 / 30 / 32 / 34 (Advanced boards).

• '''Launch bases.''' In seat order, starting with the base player (the first seat), each player chooses a Land area that holds no launch base yet and places their launch base there. It gives no area bonus, but counts as one of your linked areas.

• '''Starting hand.''' With the Mulligan option, all players at the same time discard any number of their 5 cards and draw the same number, once. With the Draft option, you do not start with cards in hand. Instead, all players at the same time choose 1 of their first 5 cards to keep and pass the rest to the player on their left. Then everyone chooses 1 card from the cards just passed to them, and keeps passing the rest. You choose 4 times in total, and the last card passed to you goes to your hand automatically, so you end with 5 cards. Mulligan and Draft are alternatives.

• '''Setup Deploy phase.''' Everyone performs one Deploy phase at the same time, with the Action selection bonus. It does not use up your Deploy Action Card, and there is no extra cost because no satellite is active yet.

== 1. Planning ==

Each round has four steps: Planning, the Action phases, the Orbit phase and the end check.

Everyone secretly chooses 1 Action Card and presses [Confirm]. You can use [Change my choice] until the last player confirms. Then all choices are revealed.

You cannot choose the card you played last round, except I Budget, which can be chosen every round. Such a card is shown sideways as unavailable for one round, then returns automatically.

== 2. Action phases ==

Only the actions chosen by at least one player take place, in order from I to V. '''Everyone''' performs the main action of every chosen phase, even if they did not choose it. Only players who chose that action also get its '''Action selection bonus'''.

When a phase asks for choices, all players decide at the same time and can change their minds until all have confirmed. Their effects are then resolved together.

==== I Budget ====

Main: gain Maya equal to your Budget. Bonus: +6 Maya.

==== II Deploy ====

Main: pay a Project card's cost and deploy it to one of your satellites (5 Maya extra for an active one). Deployed cards cannot be removed. Bonus: deploy 1 more card (it must already be in your hand) or draw 1 Project card. You choose both together.

==== III Launch / Turn ====

Main: either '''launch''' a standby satellite that has at least 1 Project card, or '''turn''' an active satellite. Launching: pay the launch cost, place it on your launch base and choose its direction (one of six); it starts with full fuel. Turning: choose a new direction; keeping the same one still counts as a turn. Bonus: turn an active satellite, or raise your Budget by 1. You cannot launch with the bonus.

==== IV Establish Link ====

Main: choose an area directly beneath one of your active satellites and place a cube there, or [Pass]. The cost is the area cost (Sea 2, Land 5) plus a '''pioneer cost''' of 3 Maya paid to each other player who already has a cube there. Players who link the same area at the same time pay each other nothing. You cannot link an area where you already have a cube or a launch base, but you can link another player's launch base area (paying its owner). Bonus: link one more area, which can also be an area next to a satellite, ahead of or behind its heading.

==== V Proposal ====

Main: draw 2 Project cards, keep 1 and discard 1. Bonus: instead, draw 5, keep 2 and discard 3.

== 3. Orbit phase ==

All active satellites are affected, including those launched this round. In order:

1. '''Move.''' Every satellite moves 1 area in its heading, all at once. At the top or bottom edge it bounces and changes its heading before moving; this is not a turn.

2. '''Fuel.''' Every satellite uses 1 fuel.

3. '''Near miss.''' Satellites in the same area (even your own) each use 2 more fuel.

4. '''Landing.''' A satellite whose fuel marker has gone beyond its highest fuel slot lands (or splashes down), returning to standby. Splashing down in a Sea area gives 10 Maya. Its Project cards stay deployed, and it can be launched again.

== 4. End of the game ==

At the end of the Orbit phase, the game ends if any player has all 5 satellites active or has placed all 16 cubes on the board. Each player who meets a condition gets the end condition bonus. Cards left in hand are sold automatically for 3 Maya each before scoring.

== Scoring ==

• '''I Budget:''' 1 fame per Budget.

• '''II Maya:''' 1 fame per 10 Maya.

• '''III Linked areas:''' 2 fame per Land area and 1 per Sea area where you have a cube, including your launch base area.

• '''IV Linked regions:''' for the number of regions (A to H) where you have a linked area: 1 / 3 / 6 / 10 / 15 / 21 / 28 / 36 for 1 to 8 regions.

• '''V Missions:''' a Mission scores if your linked areas (cubes and launch base) form its shape; it can be rotated but not flipped. The light hexes alone give the lower value; light and dark hexes together give the higher value instead. One cube or launch base cannot count for both Missions.

• '''VI Active satellites:''' the fame on each active satellite card.

• '''VII Project cards:''' cards on active satellites give their full fame; cards on standby satellites give half of their total, rounded down. Each satellite (active or not) with Crew, Device and Software gives 5 more.

• '''VIII Rewards:''' two Reward cards rank players by a comparison. Ranks give 18, 12, 6 and 0 fame. Tied players split the sum of their ranks, rounded down. A player scoring 0 on the comparison gets nothing.

• '''IX End condition:''' +3 fame for each player who met an end condition.

== Activated effects and selling ==

• '''Activated effects''' can be used once per round when their condition is met. Used ones are marked and reset at the end of the round. You choose [Use] or [Skip] for optional effects.

• '''Sell:''' during your own actions and choices (including simultaneous ones) you can discard a Project card from your hand for 3 Maya. If you lack Maya for a cost, the game prompts you to sell. You cannot sell during other players' turns.

== Game options ==

• '''Board layout:''' Standard (Earth, default), Standard (Random), Advanced (Earth) or Advanced (Random). Standard is the front side; Advanced is the back side with area bonuses. Earth places the region boards in the recommended order A to H; Random shuffles them (some may be rotated).

• '''Board size:''' 2x4 (default), 2x3 (up to 3 players) or 2x2 (2 players).

• '''Award cards:''' Initial Game (default; one ★ card and one ★★ card, for a first game), Random (1: Link, 1: Other) (one link-related card and one other) or Random.

• '''Satellite plan:''' MOON (default, shared plan) or Player-variable (a plan for each color).

• '''Starting hand:''' None (default), Mulligan or Draft. With Draft, everyone chooses 1 of their 5 cards at the same time, passes the rest to the left, and repeats with the cards they receive; the last card passed to you is added to your hand automatically.

• '''Public fame (except missions):''' Hidden (default) or Visible.

== Playing on BGA ==

• Simultaneous choices are hidden from other players until everyone has pressed [Confirm]. Use [Change my choice] to pick again before then.

• In the preferences you can show player marks on pieces and choose Compact or As printed satellite cards.

Official site: https://danjiri-concerto.github.io/project-edgeworth/
```

## 日本語版

```text
__NOTOC__
'''PROJECT EDGEWORTH''' は 2〜4 人で遊ぶゲームです（40〜60 分）。通信衛星のネットワークをつくる組織を率い、衛星にプロジェクトカードを配備し、打ち上げ、真下の区域を通信確立します。

== 目的 ==

ゲーム終了時に、予算・マヤ・通信確立した区域と地域・非公開のミッション・衛星・プロジェクトカード・共通の褒賞 2 枚・終了条件の名声を合計します。名声がもっとも多い人の勝ちです。同点なら勝利を分け合います。

== 画面にあるもの ==

• '''ボード。''' 両面の地域ボード（A〜H、各 3×3 区域）を並べて盤面にします（標準は 2×4）。左右の端はつながっていて、衛星は上下の端で反射します。海区域の通信確立コストは 2 マヤで名声 1、陸区域は 5 マヤで名声 2 です。裏面（Advanced）では、通信確立すると区域ボーナスを得られる区域があります。深海は 1 番目の通信確立で燃料 2 回復・2 番目で 1 回復、森林は 1 番目で 5 マヤ、山岳は 1 番目でプロジェクトカードを 1 枚引き、雪原は順番を問わず予算 +1、岩礁は隣の陸区域の区域コストが自分だけ 1 下がります。

• '''衛星。''' 各プレイヤーは衛星カードを 5 枚持ちます。衛星コマが盤面にあれば'''稼働中'''、衛星カードの上にあれば'''非稼働'''です。1 機に配備できるプロジェクトカードは最大 4 枚です。

• '''プロジェクトカード。''' CREW・DEVICE・SOFTWARE の 3 種類です。効果はツールチップで読めます。

• '''アクションカード。''' I 予算発行、II 配備、III 打ち上げ・転回、IV 通信確立、V 企画の 5 枚です。

• '''マヤ・予算・名声。''' マヤは所持金です。予算は予算発行で得るマヤの量で、終了時に名声にもなります。

== 準備 ==

• 各プレイヤーは、予算 15、非稼働の衛星 5 機、キューブ 16 個、アクションカード 5 枚、非公開のミッション 2 枚でスタートします。さらにプロジェクトカード 5 枚と、席順で決まる初期資金を受け取ります。標準（表面）は 30 / 31 / 32 / 33、Advanced（裏面）は 28 / 30 / 32 / 34 です。

• '''発射拠点。''' 基準プレイヤー（席順の 1 番手）から順に 1 人ずつ、まだ誰の発射拠点もない陸区域に自分の発射拠点を置きます。区域ボーナスは得られませんが、通信確立した区域として数えます。

• '''初期手札。''' オプションがマリガンのときは、全員が同時に、5 枚のうち好きな枚数を捨てて同じ枚数を引き直します（1 回だけ）。ドラフトのときは最初から手札はなく、全員が同時に、最初の 5 枚から 1 枚を選んで手札に加え、残りを左隣のプレイヤーへ回します。次は回ってきたカードから 1 枚を選び、残りをまた回します。選ぶのは全部で 4 回で、最後に回ってきた 1 枚は自動で手札に入るので、手札は 5 枚になります。マリガンとドラフトはどちらか一方です。

• '''セットアップの配備フェイズ。''' 全員が同時に、アクション選択ボーナス付きの配備フェイズを 1 回行います。アクションカードの II 配備は使ったことにならず、稼働中の衛星がないので追加コストもありません。

== 1. 計画フェイズ ==

1 ラウンドは、計画フェイズ、アクションフェイズ、循環フェイズ、終了判定の順に進みます。

全員が秘密裏にアクションカードを 1 枚選び、[Confirm] で確定します。最後の 1 人が確定するまでは [Change my choice] で選び直せます。全員が確定すると一斉に公開されます。

前のラウンドに選んだカードは選べません。ただし I 予算発行は毎ラウンド選べます。選べないカードは 1 ラウンドの間、横向きで表示され、その後は自動で手札へ戻ります。

== 2. アクションフェイズ ==

誰かが選んだアクションだけが、I から V の順に行われます。選ばれたフェイズのメインアクションは、そのアクションを選んでいない人も'''全員が'''行います。'''アクション選択ボーナス'''を得られるのは、そのアクションを自分で選んだ人だけです。

選択を求められるときは全員が同時に決め、全員が確定するまで選び直せます。その後、効果は一斉に処理されます。

==== I 予算発行 ====

メイン: 予算と同じ量のマヤを得ます。ボーナス: さらに 6 マヤ。

==== II 配備 ====

メイン: プロジェクトカードのコストを払い、自分の衛星に配備します（稼働中の衛星には追加で 5 マヤ）。配備したカードは外せません。ボーナス: カードをもう 1 枚配備する（手札にあるカードだけ）か、プロジェクトカードを 1 枚引きます。両方を一緒に選びます。

==== III 打ち上げ・転回 ====

メイン: プロジェクトカードが 1 枚以上ある非稼働の衛星を'''打ち上げる'''か、稼働中の衛星を'''転回'''します。打ち上げは、打ち上げコストを払って発射拠点に置き、向き（6 方向）を選びます。燃料は満タンです。転回は新しい向きを選びます（同じ向きでも転回です）。ボーナス: 稼働中の衛星を転回するか、予算を 1 上げます。ボーナスで打ち上げはできません。

==== IV 通信確立 ====

メイン: 稼働中の衛星の真下の区域を選んでキューブを置くか、[Pass] します。コストは区域コスト（海 2、陸 5）に'''先駆コスト'''を足したものです。先駆コストは、その区域にすでにキューブがある他のプレイヤーそれぞれに 3 マヤです。同時に同じ区域を通信確立した人どうしは払いません。自分のキューブや発射拠点がある区域は選べませんが、他のプレイヤーの発射拠点の区域は、持ち主に先駆コストを払って通信確立できます。ボーナス: もう 1 区域を通信確立します。衛星の進行方向の前後に隣り合う区域も選べます。

==== V 企画 ====

メイン: プロジェクトカードを 2 枚引き、1 枚を手札に加えて 1 枚を捨てます。ボーナス: 代わりに 5 枚引き、2 枚を手札に加えて 3 枚を捨てます。

== 3. 循環フェイズ ==

打ち上げたラウンドの衛星を含め、稼働中のすべての衛星が対象です。次の順に行います。

1. '''移動。''' 全衛星が同時に、向きの方向へ 1 区域進みます。上下の端では向きを反転してから進みます（転回ではありません）。

2. '''燃料消費。''' 各衛星が燃料を 1 消費します。

3. '''ニアミス。''' 同じ区域にいる衛星（自分のものどうしでも）は、それぞれ燃料をさらに 2 消費します。

4. '''着陸 / 着水。''' 燃料コマが最も上の燃料庫を超えた衛星は着陸（着水）し、非稼働に戻ります。海区域への着水では 10 マヤを得ます。配備したカードは残り、再び打ち上げられます。

== 4. ゲームの終了 ==

循環フェイズの終了時に、5 機とも稼働中のプレイヤーがいる、またはキューブ 16 個をすべて盤面に置いたプレイヤーがいれば、ゲームが終わります。条件を満たした人はそれぞれ終了条件の名声を得ます。手札に残ったカードは、得点計算の前に 1 枚 3 マヤで自動的に換金されます。

== 名声の計算 ==

• '''I 予算:''' 予算 1 につき 1。

• '''II 所持金:''' 10 マヤにつき 1。

• '''III 通信確立した区域:''' キューブのある区域（発射拠点の区域を含む）。陸 1 区域につき 2、海 1 区域につき 1。

• '''IV 通信確立した地域:''' 通信確立した区域がある地域（A〜H）の数が 1〜8 のとき、1 / 3 / 6 / 10 / 15 / 21 / 28 / 36。

• '''V ミッション:''' 通信確立した区域（キューブと発射拠点）が形状どおりに並んでいれば得点します（回転可、反転不可）。薄い色の六角形だけなら低い方、薄い色と濃い色を合わせた形なら高い方の名声を得ます（両方は得ません）。同じキューブや発射拠点を 2 枚のミッションに使うことはできません。

• '''VI 稼働中の衛星:''' 稼働中の衛星カードに書かれた名声。

• '''VII プロジェクトカード:''' 稼働中の衛星のカードは名声の全部、非稼働の衛星のカードは名声の合計の半分（切り捨て）。CREW・DEVICE・SOFTWARE がそろった衛星 1 機につき 5（稼働・非稼働を問わない）。

• '''VIII 褒賞:''' 褒賞カード 2 枚の比較内容で順位を決め、順位ごとに 18・12・6・0 の名声。同順位は順位の名声を合計して人数で割ります（切り捨て）。比較内容が 0 の人は名声を得られません。

• '''IX 終了条件:''' 終了条件を満たした人はそれぞれ +3。

== 起動効果と売却 ==

• '''起動''' の効果は、条件を満たしたとき各ラウンド 1 回まで使えます。使用済みはカードに印が付き、ラウンドの終わりに戻ります。任意の効果では [Use] か [Skip] を選びます。

• '''売却:''' 自分の行動中や選択中（同時選択を含む）は、手札のプロジェクトカードを 1 枚捨てて 3 マヤを得られます。コストのマヤが足りないときは売却を促されます。他のプレイヤーの手番中には売却できません。

== ゲームオプション ==

• '''Board layout（ボードの配置）:''' Standard (Earth)（初期設定）、Standard (Random)、Advanced (Earth)、Advanced (Random)。Standard は区域ボーナスのない表面、Advanced は区域ボーナスのある裏面です。Earth は地域ボードを推奨の A〜H の順に、Random はランダムに並べます（180° 回転することがあります）。

• '''Board size（ボードの大きさ）:''' 2x4（初期設定）、2x3（3 人まで）、2x2（2 人のみ）。

• '''Award cards（褒賞カード）:''' Initial Game（初期設定。★ と ★★ から 1 枚ずつ。初回プレイ向け）、Random (1: Link, 1: Other)（通信確立系から 1 枚、それ以外から 1 枚）、Random。

• '''Satellite plan（衛星プラン）:''' MOON（初期設定。全員共通）、Player-variable（色ごとのプラン）。

• '''Starting hand（初期手札）:''' None（初期設定）、Mulligan、Draft。Draft は、全員が同時に 5 枚から 1 枚を選んで残りを左隣へ回し、回ってきたカードからも同じように選ぶことを繰り返します。最後に回ってきた 1 枚は自動で手札に入ります。

• '''Public fame (except missions)（名声の公開）:''' Hidden（初期設定）、Visible。

== BGA での遊び方 ==

• 同時選択の内容は、全員が [Confirm] するまで他のプレイヤーには見えません。それまでは [Change my choice] で選び直せます。

• 設定で、コマに席順の数字を重ねる表示と、衛星カードの表示（Compact / As printed）を選べます。

公式サイト: https://danjiri-concerto.github.io/project-edgeworth/
```

## 記録

| 日付 | 内容 |
| --- | --- |
| 2026-11-24 | 英語版・日本語版の文面と掲載手順を作成。wiki への掲載は未 |
