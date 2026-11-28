# BGA へのカード画像の差し替えの相談

BGA 用にカード画像を差し替えてよいかを、再審査の依頼の前に、BGA の審査担当へ相談する。2026-11-27 の Public Alpha の審査で、指摘 5 は "Some card text is particularly small, and the gameplay components should remain readable without excessive zooming." だった。UI の作り直し（仕様書 [014](../specs/014-ui-redesign.md)、I-23）は 2026-11-28 に完了している。再審査の依頼の文面は [alpha-review-response.md](alpha-review-response.md)。

## 背景

- 印刷版のカードは日本語だけ。今の BGA 版は印刷版のカード画像（日本語の文字入り）をそのまま使い、英語のカード名を手札・アクションカードの下のキャプションに、英語の名前と効果文をツールチップに出している（[012 §8.2](../specs/012-ui-assets.md) の案 A、[014](../specs/014-ui-redesign.md) Q8）。
- 日本語の文字が印刷されている面は、`data/cards/` の画像を見て数えると 168 面。
  - プロジェクトカード 99（P001〜P099。カード名と効果文）
  - 衛星カード 40（MOON 面 20: R001〜R005 の 4 色、色ごとのプランの面 20: R006〜R025。いずれも効果文。ロケット名の HAMMER・PHOBOS などはラテン文字で焼き込まれている）
  - アクションカード 5（A001〜A005。名前と効果文）
  - 褒賞カード 24（T001〜T024。名前と説明。1st〜4th の順位の文字はラテン文字）
  - ミッションカード 10（M001〜M010）は形状の絵と数字、ラテン文字の名前（LOOP など）だけで、日本語の文字は無い。裏面（PREV・AREV・MREV）も英字（PROJECT・ACTION・MISSION）だけ。
- **今のカード名・効果文は BGA の翻訳の対象になっていない。** `src/modules/js/cards/card-info.js` の名前・効果文は素の文字列で `_()` に包まれておらず、サーバーの `src/modules/php/Material/` のカード名・効果文（`ProjectCards.php`・`CardTexts.php` など）も `clienttranslate()` に包まれていない（`Notices.php` の通知は `i18n` に `card_name` を入れているが、元の文字列が翻訳の対象に登録されていない）。翻訳できるのは、タイミング・効果の種類などのラベル（`src/modules/js/cards/card-text.js` の `_()`）だけ。対応は [tasks.md](../tasks.md) の N-13。
- BGA の文書（2026-11-28 に確認）:
  - [Game art の "Text on game art"](https://doc.studio.boardgamearena.com/framework-documentation/front-end/game-art/): "Cards, boards, and other components may have text on them. It's best to use images without text in your project and overlay translatable text." / "If the publisher sends you images without text, remember to request the English version as well, so you can use the official card text as the source for translations."
  - [Translations](https://en.doc.boardgamearena.com/Translations): 翻訳の対象は "Every text that can be visible by the player when the game is running normally. This includes tooltips, texts on cards, error messages."
  - [UX ガイドライン](https://doc.studio.boardgamearena.com/framework-documentation/guidelines/) C.1: "All text must be translatable using BGA's built-in translation system. Never embed words into graphics."
  - [bga-autofit](https://doc.studio.boardgamearena.com/framework-documentation/front-end/libs/bga-autofit/): "Its main use is to make translated text on game cards fit the dedicated space in the card image."
- 画面上のカードの大きさ（[014 §2.2](../specs/014-ui-redesign.md)）は、手札が PC 150 × 210 px、スマホ 104 × 145 px。カードには効果の要約が、言語に依存しないアイコンの列（ルールブック p.6「カード効果のアイコン」）として印刷されている。
- 作者（だんじり協奏曲）はデザイナー兼出版社で、BGA へのライセンスの申請も権利者として行った（[bga-license.md](bga-license.md)）。差し替え用の画像は、作者が印刷用の原本データから文字を抜いて書き出す。
- 英語のカード文は [002](../specs/002-card-data.md) に案があるが、作者の確認（[tasks.md](../tasks.md) 作業 7）は未着手。差し替える場合、英語が翻訳の元の文になるので、作者の確認が前提になる。

## 送る時期と方法

- [tasks.md](../tasks.md) の作業 19。作業 18（再審査の依頼）の**前**に、2026-11-27 の審査の返信のスレッドへの返信として送る。
- 画像の差し替えには時間がかかる。返事を見てから、作業の順番（再審査の依頼を先にするか、差し替えを先にするか）を決める。
- 返信のスレッドにそのまま続ければ件名は変えなくてよい。新しい件名を付ける場合の案: `PROJECT EDGEWORTH: question about replacing card images (follow-up to the Public Alpha review)`

## 英語の文面（案）

```text
Hello,

Thank you for the detailed review. I have redesigned the interface for all seven points and will request a re-review soon. First, I would like to ask about the card images, related to point 5 (small card text).

PROJECT EDGEWORTH was published in Japan, so the printed cards are in Japanese only. The BGA version currently uses the printed card images, shows the English card names under the cards in hand, and shows the English name and effect text in tooltips. The Japanese text printed on the cards cannot be translated and is not readable at on-screen sizes.

Following the Game art documentation ("It's best to use images without text in your project and overlay translatable text."), I would like to replace them with BGA-specific card images. Artwork, icons and numbers would stay as printed; only the Japanese text would be removed, and the card name and effect text would be overlaid as translatable text (with the Japanese text as its Japanese translation). This affects about 168 card faces: project, satellite, action and reward cards. Mission cards and card backs have no Japanese text. As the designer and publisher, I can provide the modified images.

I have three questions:

1. Is it acceptable for the BGA version to use card images that differ from the printed ones in this way?
2. Long effect texts cannot be made fully legible at on-screen card sizes (cards in hand are 150 px wide on desktop). Each card already carries a language-independent icon summary of its effect. Is it acceptable to show the name and icons clearly, overlay the effect text in small type, and give the full text in the tooltip? Or do you expect a different layout, such as a larger text area?
3. Timing: the images will take time to create. May I proceed with the re-review using the current images and replace them during the alpha, or should they be replaced before the re-review?

Best regards,
[Your name]
Danjiri Concerto (designer and publisher of PROJECT EDGEWORTH)
```

- 実機で確かめていないことは書いていない。今の英語のカード文が翻訳できるとも書いていない（翻訳の対象になっていないため、N-13 で直す）。
- 期限は迫っていない。

## 日本語訳（確認用）

```text
こんにちは。

詳しい審査をありがとうございます。7 つの指摘のすべてについて UI を作り直しました。近く再審査を依頼します。その前に、指摘 5（カードの文字が小さい）に関わるカード画像について、1 つ相談させてください。

PROJECT EDGEWORTH は日本で出版したゲームなので、印刷版のカードは日本語だけです。BGA 版は今、印刷版のカード画像を使い、英語のカード名を手札のカードの下に、英語の名前と効果文をツールチップに出しています。カードに印刷された日本語の文字は翻訳できず、画面上の大きさでは読めません。

Game art の文書（「文字なしの画像を使い、翻訳できる文字を重ねるのがよい」）に沿って、BGA 用のカード画像に差し替えたいと考えています。絵・アイコン・数字は印刷版のまま、日本語の文字だけを抜き、カード名と効果文は翻訳できる文字として重ねます（日本語は日本語訳として入れます）。対象はプロジェクト・衛星・アクション・褒賞のカードの約 168 面です。ミッションカードと裏面には日本語の文字がありません。私はデザイナー兼出版社なので、修正した画像は私が用意できます。

質問が 3 つあります。

1. BGA 版のカードが、このように印刷版と違う画像でもよいでしょうか。
2. 長い効果文は、画面上のカードの大きさ（手札は PC で幅 150 px）では、全部を読める大きさにはできません。各カードには、効果の要約が言語に依存しないアイコンで載っています。カードの上では名前とアイコンをはっきり見せ、効果文は小さな字で重ね、全文はツールチップに出す形でよいでしょうか。それとも、文字の欄を大きくするなど、別の配置を期待されますか。
3. 時期: 画像の作成には時間がかかります。今の画像のまま再審査に進み、差し替えはアルファの間に行ってよいでしょうか。それとも再審査の前に差し替えるべきでしょうか。

よろしくお願いします。

[お名前]
だんじり協奏曲（PROJECT EDGEWORTH のデザイナー兼出版社）
```

## 返事ごとの次の作業

どの場合も、返事の内容を [tasks.md](../tasks.md) の作業 19 の状態欄と [012 §8.2](../specs/012-ui-assets.md) に記録する。

### (a) 差し替えてよい

1. 作者が、印刷用の原本データから文字を抜いた画像を書き出す。対象は 168 面（プロジェクト 99・衛星 40・アクション 5・褒賞 24）。ミッションと裏面は対象外。
2. 英語のカード文の確認（[tasks.md](../tasks.md) 作業 7）。英語が翻訳の元の文になる。
3. カード名・効果文を翻訳の対象にする（JS は `_()`、PHP は `clienttranslate()`。N-13）。
4. 実装の前に仕様書を作る（[012 §8.2](../specs/012-ui-assets.md) の追補、または新しい仕様書）。作者の承認を得る。
5. スプライトを作り直す（`npm run build:sprites`）。
6. 名前と効果文を画像に重ねる表示を実装する（bga-autofit で、枠に収まるよう文字の大きさを調える）。
7. 日本語の訳を BGA の翻訳画面で登録する。

### (b) 今の画像のままでよい

- カード名・効果文を翻訳の対象にする作業（N-13）だけは必要（ガイドライン C.1、Translations）。画像と作者の作業は不要。
- 時期の質問への返事に応じて、再審査の依頼（作業 18）を進める。

### (c) 別の案を求められた

- 返事の内容をそのまま記録し、作者と相談して方針を決める。決まるまで、再審査の依頼（作業 18）の前に進める範囲を確認する。
