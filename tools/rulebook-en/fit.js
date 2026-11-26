// ブラウザ内で動く。①フォントの読み込みを待つ ②文字の箱 .t の中身が --h（--w）をはみ出したら
// フォントサイズを少しずつ下げて収める（下限は指定値の --fit-min 倍、既定 0.85）③縞の地の消す四角 .e が
// 見開きの右ページにあるとき右ページ用の縞を使う ④結果を window.__rb に入れる（ビルドが読む）。
(() => {
  'use strict';
  const STEP = 0.5; // フォントサイズを下げる刻み（原本 px）
  const DEFAULT_MIN = 0.85;

  const num = (cs, name, def) => {
    const v = parseFloat(cs.getPropertyValue(name));
    return Number.isFinite(v) ? v : def;
  };

  async function loadFonts() {
    // 宣言されているフォントをすべて先に読み込む（使われて初めて読む挙動だと、測定が代替フォントで行われる）
    const faces = [...document.fonts];
    await Promise.all(faces.map((f) => f.load().catch(() => null)));
    await document.fonts.ready;
  }

  function overflows(el, tin, w, h) {
    if (getComputedStyle(el).writingMode.startsWith('vertical')) {
      // 縦書き（.vert）: 行の流れが縦。長さは scrollHeight、行数分の幅は offsetWidth
      return tin.scrollHeight > h + 1 || tin.offsetWidth > w + 1;
    }
    const tooTall = h > 0 && tin.offsetHeight > h + 1;
    const tooWide = tin.scrollWidth > w + 1 || el.scrollWidth > w + 1;
    return tooTall || tooWide;
  }

  function fitBox(el) {
    const cs = getComputedStyle(el);
    const base = num(cs, '--fs', 31);
    const w = num(cs, '--w', 0);
    const h = num(cs, '--h', 0);
    const min = base * num(cs, '--fit-min', DEFAULT_MIN);
    let tin = el.querySelector(':scope > .tin');
    if (!tin) {
      tin = document.createElement('div');
      tin.className = 'tin';
      while (el.firstChild) tin.appendChild(el.firstChild);
      el.appendChild(tin);
    }
    let fs = base;
    el.style.fontSize = '';
    const noFit = el.classList.contains('nofit');
    if (!noFit) {
      while (overflows(el, tin, w, h) && fs - STEP >= min - 1e-6) {
        fs -= STEP;
        el.style.fontSize = `${fs}px`;
      }
    }
    const over = overflows(el, tin, w, h);
    if (over) el.setAttribute('data-overflow', '1');
    else el.removeAttribute('data-overflow');
    const text = tin.textContent.replace(/\s+/g, ' ').trim();
    return {
      label: el.dataset.name || text.slice(0, 40),
      x: num(cs, '--x', 0), y: num(cs, '--y', 0), w, h,
      fontSize: base, finalFontSize: fs, shrunk: fs < base - 1e-6,
      contentHeight: tin.offsetHeight, overflow: over,
    };
  }

  function assignStripes(sheet) {
    const split = Number(sheet.dataset.split || 0);
    if (!split) return 0;
    let n = 0;
    for (const e of sheet.querySelectorAll('.e:not(.flat)')) {
      const cs = getComputedStyle(e);
      const cx = num(cs, '--x', 0) + num(cs, '--w', 0) / 2;
      e.classList.toggle('sr', cx >= split);
      n += 1;
    }
    return n;
  }

  // <i class="ic fame">5</i> の数字を data-n に移す（CSS が ::after で五角形の上に重ねる）
  function prepareFame() {
    for (const el of document.querySelectorAll('.ic.fame')) {
      const n = el.textContent.trim();
      if (n) el.setAttribute('data-n', n);
      el.textContent = '';
      el.classList.toggle('s2', n.length >= 2);
    }
  }

  async function run() {
    prepareFame();
    await loadFonts();
    const report = {};
    for (const sheet of document.querySelectorAll('.sheet')) {
      const id = sheet.dataset.id;
      const erases = assignStripes(sheet);
      const boxes = [...sheet.querySelectorAll('.t')].map(fitBox);
      report[id] = {
        boxes,
        erases: sheet.querySelectorAll('.e').length,
        stripeErases: erases,
        overflowCount: boxes.filter((b) => b.overflow).length,
        shrunkCount: boxes.filter((b) => b.shrunk).length,
      };
    }
    window.__rb = report;
    window.__rbDone = true;
  }

  const start = () => run().catch((err) => { window.__rbError = String(err && err.stack ? err.stack : err); window.__rbDone = true; });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
