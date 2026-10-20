// cards/text-symbols.js（効果文の [記号] → アイコン）の単体テスト。sprites.js（生成物）には依存しない。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { SYMBOLS, SYMBOL_PIECES, symbolHtml, symbolsToHtml } from '../../src/modules/js/cards/text-symbols.js';

/** スプライトの代わり。名前をそのまま背景に入れる */
const resolve = (/** @type {string} */ piece) => ({ style: `background-image:url(${piece});`, aspect: 1 });

test('002 の英語文に出る記号がすべて置き換わる', () => {
  const names = ['Maya', 'Budget', 'Fuel', 'CREW', 'DEVICE', 'SOFTWARE', 'Land', 'Sea', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
  for (const n of names) {
    const out = symbolsToHtml(`+1 [${n}].`, resolve);
    assert.match(out, /^\+1 <span class="pe-glyph/, n);
    assert.ok(!out.includes('['), n);
    assert.ok(out.endsWith('</span>.'), n);
  }
});

test('記号ごとの画像と代替テキスト', () => {
  const out = symbolsToHtml('[Fuel]', resolve);
  assert.match(out, /aria-label="Fuel"/);
  assert.match(out, /title="Fuel"/);
  assert.match(out, /role="img"/);
  assert.match(out, /background-image:url\(icon_fuel\)/);
  assert.match(symbolsToHtml('[C]', resolve), /aria-label="Area C".*area_C/);
  assert.match(symbolsToHtml('[Land]', resolve), /aria-label="Land"/);
});

test('表記ゆれ（大文字小文字）は同じ記号', () => {
  assert.equal(symbolsToHtml('[CREW]', resolve), symbolsToHtml('[Crew]', resolve));
  assert.match(symbolsToHtml('[SOFTWARE]', resolve), /aria-label="Software"/);
});

test('予算はマヤのアイコンに枠を付ける', () => {
  const out = symbolsToHtml('[Budget]', resolve);
  assert.match(out, /pe-glyph pe-glyph-framed/);
  assert.match(out, /background-image:url\(maya_1\)/);
  assert.ok(!symbolsToHtml('[Maya]', resolve).includes('pe-glyph-framed'));
});

test('未知の記号はそのまま残す', () => {
  assert.equal(symbolsToHtml('[Unknown] and [constructor] and [Maya1] [ ]', () => null), '[Unknown] and [constructor] and [Maya1] [ ]');
  assert.equal(symbolsToHtml('[Unknown]', resolve), '[Unknown]');
  assert.equal(symbolsToHtml('[__proto__]', resolve), '[__proto__]');
});

test('画像が引けない記号は文字のまま残す', () => {
  assert.equal(symbolsToHtml('pay [Maya]', () => null), 'pay [Maya]');
});

test('エスケープが先: 記号の外の HTML は無効化される', () => {
  const out = symbolsToHtml('<b>"x"</b> & [Maya] <script>', resolve);
  assert.ok(out.startsWith('&lt;b&gt;&quot;x&quot;&lt;/b&gt; &amp; <span'));
  assert.ok(out.endsWith('</span> &lt;script&gt;'));
  assert.ok(!out.includes('<b>'));
  // 角括弧の中に HTML があっても記号にはならない
  assert.equal(symbolsToHtml('[<Maya>]', resolve), '[&lt;Maya&gt;]');
});

test('改行は <br> になる', () => {
  assert.equal(symbolsToHtml('a\nb', resolve), 'a<br>b');
});

test('span の組み立て: style は属性として安全にエスケープされる', () => {
  const html = symbolHtml(SYMBOLS.maya, { style: 'background-image:url("x.webp");', aspect: 0.9697 });
  assert.match(html, /style="background-image:url\(&quot;x\.webp&quot;\);--pe-glyph-aspect:0\.9697"/);
});

test('置き換え表: 全記号に英語名とコマ・アイコンがある', () => {
  for (const [key, def] of Object.entries(SYMBOLS)) {
    assert.ok(def.label.length > 0, key);
    assert.match(def.piece, /^(maya_1|icon_[a-z]+|area_[A-H])$/, key);
  }
  assert.equal(SYMBOL_PIECES.length, new Set(SYMBOL_PIECES).size);
  assert.ok(SYMBOL_PIECES.includes('icon_fuel') && SYMBOL_PIECES.includes('area_H'));
});

test('置き換え表のコマ・アイコンが、スプライトの規則（sprite-layout.mjs）にある', async () => {
  const { pieceCells } = await import('../../scripts/lib/sprite-layout.mjs');
  const names = new Set(pieceCells().map((c) => c.name));
  for (const piece of SYMBOL_PIECES) {
    assert.ok(names.has(piece), piece);
  }
});
