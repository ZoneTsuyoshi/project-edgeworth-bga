/**
 * ツールチップの管理役（仕様書 014 §7）。要素ごとの結び付け（リスナー）を持たず、`document` への委譲で動かす。
 * 画面の部品は頻繁に描き直されるため、要素には印の属性（`data-pe-tip`）だけを付け、文や中身の作り方は要素 → 内容の `WeakMap` で持つ。
 *   - カード: `tips.card(node, 'P003')` = `data-pe-tip="card:P003"`。第 3 引数は、カードの本文の下に足す内容（衛星カードの今の燃料、使用済み、選べない理由など。出すときに作る）
 *   - 文字だけ: `tips.text(node, 'Maya')`（文は関数でもよい。複数行は `\n`。空なら外す）
 *   - 中身を出すときに作る型: `tips.custom(node, () => HTMLElement)`
 * 描画の部品が管理役の実体を持たないときは、同じ名前のモジュール関数（`tipCard` / `tipText` / `tipCustom`）を使う（印と WeakMap だけで動く）。
 * 出し方: マウス = 350 ms のホバー（選べる / 選択中は 700 ms。クリックを奪わないよう `pointer-events: none`）。
 * タッチ = 選べない要素はタップ、選べる / 選択中は長押し 400 ms（8 px を超えて動いたら取り消す）。
 * タッチ端末では、選択中のカードの左上に 32 × 32 px の情報の印（ⓘ）を出し、押すと開く（選択は変えない）。
 * 消し方: マウスが離れる、外側を押す、スクロール、Esc、`hide()`（状態の切り替え）、対象が DOM から消えたとき。
 * 位置と大きさ: `placeTip`（tip-placement.js）が決める。画像と文を横に並べる → 幅を最大 640 px まで広げる → 画像を縮める（最小 96 px）の順で、
 * 見える領域に必ず収める。タッチで開いたときだけ、最後の手段として中のスクロールを許す。
 * 要素は `document.body` 直下（BGA の CSS zoom / transform の祖先を避ける。012 §10 教訓 4）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { applySprite, cardSprite } from '../cards/sprite-layout.js';
import { cardTextHtml } from '../cards/card-text.js';
import { el } from './dom.js';
import { placeTip } from './tip-placement.js';

/** マウスのホバーで出すまでの時間（ミリ秒）。選べる / 選択中の要素は通り過ぎるだけで出ないよう長めに待つ */
const HOVER_MS = 350;
const HOVER_ACTIONABLE_MS = 700;
/** タッチの長押し（選べる / 選択中の要素） */
const LONG_PRESS_MS = 400;
/** この距離を超えて動いたら、タップ・長押しではなくスクロールとみなす（px） */
const MOVE_THRESHOLD_PX = 8;
/** 幅の既定の上限と、収まらないときに広げる上限（px） */
const WIDTH_DEFAULT = 520;
const WIDTH_WIDE = 640;
/** これより狭い見える幅は narrow（画像 130 px + 右に文。幅は画面 − 16 px） */
const NARROW_VIEW = 480;
/** 画像の幅の既定（縦長 / 横長）と最小、narrow の既定（px） */
const IMG_PORTRAIT = 220;
const IMG_LANDSCAPE = 300;
const IMG_MIN = 96;
const IMG_NARROW = 130;

/**
 * 選べる / 選択中の要素を表すクラスの一覧（ここ 1 か所）。014 §6.1 の `pe-can`・`pe-can-sell`・`pe-picked`。
 * 旧クラスは PR 4 で消したので、`LEGACY_ACTIONABLE`・`LEGACY_PICKED` は空にした（互換の置き場は残さない）。
 */
const PICKED_CLASSES = ['pe-picked'];
const ACTIONABLE_CLASSES = ['pe-can', 'pe-can-sell', ...PICKED_CLASSES];
const ACTIONABLE_SELECTOR = ACTIONABLE_CLASSES.map((c) => `.${c}`).join(',');
const PICKED_SELECTOR = PICKED_CLASSES.map((c) => `.${c}`).join(',');

/**
 * @typedef {object} TipEntry
 * @property {string | (() => string)} [text]
 * @property {() => (HTMLElement | null)} [build]
 * @property {() => (HTMLElement | null)} [extra]
 */

/** @type {WeakMap<Element, TipEntry>} 要素 → 内容の作り方 */
const registry = new WeakMap();

/**
 * 要素にカードのツールチップを付ける。
 * @param {Element} node
 * @param {string} key カードのキー（P001・R001B・A001・M001・T001 など）
 * @param {() => (HTMLElement | null)} [extra] 本文の下に足す内容（出すときに作る。null なら足さない）
 */
export function tipCard(node, key, extra) {
  /** @type {HTMLElement} */ (node).dataset.peTip = `card:${key}`;
  if (extra === undefined) {
    registry.delete(node);
  } else {
    registry.set(node, { extra });
  }
}

/**
 * 要素に文字だけのツールチップを付ける。空の文（または null）なら外す。
 * @param {Element} node
 * @param {string | null | undefined | (() => string)} text 複数行は `\n`。関数は出すときに呼ぶ
 */
export function tipText(node, text) {
  if (text === null || text === undefined || text === '') {
    tipClear(node);
    return;
  }
  /** @type {HTMLElement} */ (node).dataset.peTip = 'text';
  registry.set(node, { text });
}

/**
 * 要素に、出すときに中身を作るツールチップを付ける。
 * @param {Element} node
 * @param {() => (HTMLElement | null)} build
 */
export function tipCustom(node, build) {
  /** @type {HTMLElement} */ (node).dataset.peTip = 'custom';
  registry.set(node, { build });
}

/**
 * ツールチップを外す。
 * @param {Element} node
 */
export function tipClear(node) {
  /** @type {HTMLElement} */ (node).removeAttribute('data-pe-tip');
  registry.delete(node);
}

/**
 * ツールチップの本文に足す 1 行（アイコンは任意）。
 * @param {string} text
 * @param {Node | null} [icon]
 * @param {string} [cls]
 * @returns {HTMLElement}
 */
export function tipLine(text, icon = null, cls = '') {
  return el('div', { class: `pe-tip-line ${cls}`.trim() }, [icon, el('span', {}, [text])]);
}

/** @param {Element | null} node @returns {boolean} 選べる / 選択中の要素か（祖先のクラスも見る。衛星のロケット全体など） */
function isActionable(node) {
  return node !== null && node.closest(ACTIONABLE_SELECTOR) !== null;
}

/**
 * 情報の印を付ける対象（選択中のカード）。衛星は、選択中の衛星の土台のカード。
 * @param {Element} picked
 * @returns {HTMLElement | null}
 */
function infoTarget(picked) {
  if (picked.matches('[data-pe-tip^="card:"]')) {
    return /** @type {HTMLElement} */ (picked);
  }
  return /** @type {HTMLElement | null} */ (picked.querySelector('.pe-sat-card[data-pe-tip^="card:"]'));
}

const INFO_SVG = '<svg viewBox="0 0 32 32" aria-hidden="true"><circle cx="16" cy="16" r="11" fill="#fff" stroke="#1d2430" stroke-width="2"/><circle cx="16" cy="11" r="1.7" fill="#1d2430"/><path d="M16 14.5v8" stroke="#1d2430" stroke-width="2.6" stroke-linecap="round"/></svg>';

export class Tips {
  /**
   * @param {import('../cards/CardView.js').Env} env
   */
  constructor(env) {
    this.env = env;
    /** @type {Set<string>} 読み込みが終わった拡大用シート */
    this.ready = new Set();
    /** @type {HTMLElement | null} 表示中（または表示待ち）の対象 */
    this.anchor = null;
    /** @type {number | null} */
    this.hoverTimer = null;
    /** @type {'hover' | 'touch'} */
    this.mode = 'hover';
    /** @type {string | null} 表示中のカードのキー（拡大用シートの読み込み完了で差し替える） */
    this.currentKey = null;
    this.imageNode = /** @type {HTMLElement | null} */ (null);
    /** @type {{ anchor: HTMLElement, x: number, y: number, timer: number | null, long: boolean, moved: boolean, actionable: boolean, reopen: boolean } | null} */
    this.press = null;
    this.root = el('div', { id: 'pe-tip', class: 'pe-tip', role: 'tooltip', hidden: true });
    document.body.append(this.root);
    this.bind();
  }

  /** 要素にカードのツールチップを付ける（モジュール関数 `tipCard` と同じ） @param {Element} node @param {string} key @param {() => (HTMLElement | null)} [extra] */
  card(node, key, extra) {
    tipCard(node, key, extra);
  }

  /** 要素に文字だけのツールチップを付ける（`tipText` と同じ） @param {Element} node @param {string | null | undefined | (() => string)} text */
  text(node, text) {
    tipText(node, text);
  }

  /** 要素に、出すときに中身を作るツールチップを付ける（`tipCustom` と同じ） @param {Element} node @param {() => (HTMLElement | null)} build */
  custom(node, build) {
    tipCustom(node, build);
  }

  /** @returns {boolean} 表示中か */
  get visible() {
    return !this.root.hidden;
  }

  // ---- 委譲のリスナー ----

  bind() {
    document.addEventListener('pointerover', (e) => this.onOver(e));
    document.addEventListener('pointerout', (e) => this.onOut(e));
    document.addEventListener('pointerdown', (e) => this.onDown(e), true);
    document.addEventListener('pointermove', (e) => this.onMove(e), { passive: true });
    document.addEventListener('pointerup', (e) => this.onUp(e), true);
    document.addEventListener('pointercancel', () => this.cancelPress(), true);
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        this.hide();
      }
    });
    // ページやどこかのスクロールで消す。ツールチップ自身の中のスクロール（タッチ）は除く
    document.addEventListener('scroll', (e) => {
      if (this.visible && !(e.target instanceof Node && this.root.contains(e.target))) {
        this.hide();
      }
    }, true);
    // 対象が DOM から消えたら消す。タッチ端末では、選択中のカードに情報の印を付け外しする
    let queued = false;
    new MutationObserver(() => {
      if (queued) {
        return;
      }
      queued = true;
      requestAnimationFrame(() => {
        queued = false;
        this.sync();
      });
    }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
    this.sync();
  }

  /** @param {EventTarget | null} target @returns {HTMLElement | null} ツールチップの対象（`data-pe-tip` を持ついちばん近い要素） */
  anchorOf(target) {
    if (!(target instanceof Element) || this.root.contains(target)) {
      return null;
    }
    return /** @type {HTMLElement | null} */ (target.closest('[data-pe-tip]'));
  }

  /** @param {PointerEvent} e */
  onOver(e) {
    if (e.pointerType !== 'mouse') {
      return;
    }
    const anchor = this.anchorOf(e.target);
    if (anchor === null) {
      return;
    }
    if (anchor === this.anchor) {
      return;
    }
    this.hide();
    this.anchor = anchor;
    this.hoverTimer = window.setTimeout(() => {
      this.hoverTimer = null;
      if (this.anchor === anchor && anchor.isConnected) {
        this.show(anchor, 'hover');
      }
    }, isActionable(anchor) ? HOVER_ACTIONABLE_MS : HOVER_MS);
  }

  /** @param {PointerEvent} e */
  onOut(e) {
    if (e.pointerType !== 'mouse' || this.anchor === null) {
      return;
    }
    const to = e.relatedTarget;
    if (to instanceof Node && this.anchor.contains(to)) {
      return;
    }
    this.hide();
  }

  /** @param {PointerEvent} e */
  onDown(e) {
    const target = e.target;
    if (!(target instanceof Element)) {
      return;
    }
    // 情報の印・ツールチップの中を押したときは、印のクリックの処理に任せる
    if (target.closest('.pe-tip-info') !== null || this.root.contains(target)) {
      return;
    }
    const anchor = this.anchorOf(target);
    const wasOpen = this.visible;
    const sameAnchor = wasOpen && this.anchor === anchor;
    this.hide();
    if (e.pointerType === 'mouse' || anchor === null) {
      return;
    }
    const actionable = isActionable(anchor);
    this.press = { anchor, x: e.clientX, y: e.clientY, timer: null, long: false, moved: false, actionable, reopen: !sameAnchor };
    if (actionable) {
      const press = this.press;
      press.timer = window.setTimeout(() => {
        press.timer = null;
        if (this.press === press && !press.moved && anchor.isConnected) {
          press.long = true;
          this.show(anchor, 'touch');
        }
      }, LONG_PRESS_MS);
    }
  }

  /** @param {PointerEvent} e */
  onMove(e) {
    const press = this.press;
    if (press !== null && !press.moved && Math.hypot(e.clientX - press.x, e.clientY - press.y) > MOVE_THRESHOLD_PX) {
      press.moved = true;
      this.clearPressTimer();
    }
  }

  /** @param {PointerEvent} e */
  onUp(e) {
    const press = this.press;
    if (press === null) {
      return;
    }
    this.clearPressTimer();
    this.press = null;
    if (Math.hypot(e.clientX - press.x, e.clientY - press.y) > MOVE_THRESHOLD_PX) {
      return;
    }
    if (press.long) {
      // 長押しで開いたあとの click で、カードの選択が動かないようにする
      const swallow = (/** @type {Event} */ ev) => {
        ev.stopPropagation();
        ev.preventDefault();
      };
      document.addEventListener('click', swallow, { capture: true, once: true });
      window.setTimeout(() => document.removeEventListener('click', swallow, true), 600);
      return;
    }
    if (!press.actionable && !press.moved && press.reopen && press.anchor.isConnected) {
      this.show(press.anchor, 'touch');
    }
  }

  clearPressTimer() {
    if (this.press?.timer !== null && this.press !== null) {
      clearTimeout(this.press.timer);
      this.press.timer = null;
    }
  }

  cancelPress() {
    this.clearPressTimer();
    this.press = null;
  }

  // ---- 表示 ----

  /**
   * 内容を作る。
   * @param {HTMLElement} anchor
   * @returns {{ kind: 'card' | 'text' | 'custom', content: HTMLElement, key?: string } | null}
   */
  buildContent(anchor) {
    const attr = anchor.dataset.peTip ?? '';
    const entry = registry.get(anchor);
    if (attr.startsWith('card:')) {
      const key = attr.slice(5);
      const body = el('div', { class: 'pe-tip-body' });
      body.innerHTML = cardTextHtml(key, this.env.imgUrl);
      const extra = entry?.extra?.() ?? null;
      if (extra !== null) {
        body.append(extra);
      }
      const landscape = /^R\d/.test(key);
      const image = el('div', { class: `pe-tip-img${landscape ? ' pe-tip-img-landscape' : ''}` });
      this.imageNode = image;
      this.currentKey = key;
      this.applyImage(image, key);
      return { kind: 'card', content: el('div', { class: 'pe-tip-card' }, [image, body]), key };
    }
    if (attr === 'text' && entry?.text !== undefined) {
      const text = typeof entry.text === 'function' ? entry.text() : entry.text;
      if (text === '') {
        return null;
      }
      return { kind: 'text', content: el('div', { class: 'pe-tip-lines' }, text.split('\n').map((line) => el('div', { class: 'pe-tip-line' }, [line]))) };
    }
    if (attr === 'custom' && entry?.build !== undefined) {
      const content = entry.build();
      return content === null ? null : { kind: 'custom', content };
    }
    return null;
  }

  /**
   * カードの画像。拡大用シートの読み込み前は通常のシートで代用し、読み込み後に差し替える。
   * @param {HTMLElement} image
   * @param {string} key
   */
  applyImage(image, key) {
    const zoom = cardSprite(key, 'zoom');
    const useZoom = zoom !== null && this.ready.has(zoom.file);
    applySprite(image, useZoom ? zoom : cardSprite(key, 'base'), this.env.imgUrl);
    if (zoom !== null && !useZoom) {
      const probe = new Image();
      probe.onload = () => {
        this.ready.add(zoom.file);
        if (this.currentKey === key && this.visible && this.imageNode === image) {
          applySprite(image, zoom, this.env.imgUrl);
        }
      };
      probe.src = this.env.imgUrl(zoom.file);
    }
  }

  /**
   * @param {HTMLElement} anchor
   * @param {'hover' | 'touch'} mode hover = クリックを通す、touch = 操作できる（中身がスクロールできる）
   */
  show(anchor, mode) {
    if (!anchor.isConnected) {
      return;
    }
    const built = this.buildContent(anchor);
    if (built === null) {
      this.hide();
      return;
    }
    this.cancelTimers();
    this.anchor = anchor;
    this.mode = mode;
    const { root } = this;
    root.className = `pe-tip pe-tip-${built.kind} pe-tip-${mode}`;
    root.replaceChildren(built.content);
    root.hidden = false;
    root.style.visibility = 'hidden';
    this.layout(anchor, built.kind, built.key !== undefined && /^R\d/.test(built.key), mode);
    root.style.visibility = '';
    anchor.setAttribute('aria-describedby', 'pe-tip');
  }

  /**
   * 位置と形を決める。画像と文を横に並べる → 幅を最大 640 px まで広げる → 画像を縮める（最小 96 px）の順に試し、最初に収まった形にする。
   * どれでも収まらなければ、置く側の最大の大きさに合わせる（タッチだけ中のスクロールを許す）。
   * @param {HTMLElement} anchor
   * @param {'card' | 'text' | 'custom'} kind
   * @param {boolean} landscape 横長のカード（衛星カード）か
   * @param {'hover' | 'touch'} mode
   */
  layout(anchor, kind, landscape, mode) {
    const { root } = this;
    const view = this.viewport();
    const viewW = view.width;
    const narrow = viewW < NARROW_VIEW;
    const maxW = Math.max(120, viewW - 16);
    /** @type {{ w: number | null, img: number }[]} */
    const attempts = [];
    const imgDefault = narrow ? IMG_NARROW : (landscape ? IMG_LANDSCAPE : IMG_PORTRAIT);
    if (narrow) {
      for (const img of [IMG_NARROW, 110, IMG_MIN]) {
        attempts.push({ w: maxW, img });
      }
    } else if (kind === 'card') {
      attempts.push({ w: Math.min(WIDTH_DEFAULT, maxW), img: imgDefault }, { w: Math.min(WIDTH_WIDE, maxW), img: imgDefault });
      for (const f of [0.8, 0.65, 0.5, 0.4, 0]) {
        attempts.push({ w: Math.min(WIDTH_WIDE, maxW), img: f === 0 ? IMG_MIN : Math.max(IMG_MIN, Math.round(imgDefault * f)) });
      }
      // 横向きのスマホなど、低くて横に余裕がある画面: 幅を狭めて対象の横（右・左）に置く
      for (const w of [440, 360]) {
        attempts.push({ w: Math.min(w, maxW), img: Math.max(IMG_MIN, Math.round(imgDefault * 0.65)) }, { w: Math.min(w, maxW), img: IMG_MIN });
      }
    } else {
      attempts.push({ w: null, img: imgDefault }, { w: Math.min(WIDTH_WIDE, maxW), img: imgDefault });
    }
    const rect = anchor.getBoundingClientRect();
    root.style.maxHeight = '';
    root.style.overflowY = '';
    /** @type {import('./tip-placement.js').TipPlacement | null} */
    let chosen = null;
    let last = attempts[attempts.length - 1];
    for (const attempt of attempts) {
      last = attempt;
      this.applyShape(attempt, maxW);
      const box = root.getBoundingClientRect();
      const p = placeTip(rect, { width: box.width, height: box.height }, view);
      chosen = p;
      if (p.fits) {
        break;
      }
    }
    if (chosen === null) {
      return;
    }
    if (!chosen.fits) {
      // 最後の手段: 置く側の最大の幅・高さに合わせる。タッチだけ中のスクロールを許す
      this.applyShape(last, Math.max(1, Math.min(maxW, chosen.maxWidth)));
      root.style.maxHeight = `${chosen.maxHeight}px`;
      root.style.overflowY = mode === 'touch' ? 'auto' : 'hidden';
      const box = root.getBoundingClientRect();
      chosen = placeTip(rect, { width: box.width, height: Math.min(box.height, chosen.maxHeight) }, view);
    }
    root.style.left = `${chosen.left}px`;
    root.style.top = `${chosen.top}px`;
  }

  /**
   * @param {{ w: number | null, img: number }} shape
   * @param {number} maxW
   */
  applyShape(shape, maxW) {
    const { root } = this;
    root.style.setProperty('--pe-tip-img', `${shape.img}px`);
    root.style.left = '0';
    root.style.top = '0';
    if (shape.w === null) {
      root.style.width = 'max-content';
      root.style.maxWidth = `${Math.min(WIDTH_DEFAULT, maxW)}px`;
    } else {
      root.style.width = `${Math.min(shape.w, maxW)}px`;
      root.style.maxWidth = '';
    }
  }

  /** @returns {{ width: number, height: number, top: number }} 見える領域（上端の制限は BGA のステータスバー `#page-title` の下端） */
  viewport() {
    const bar = document.getElementById('page-title');
    const barBottom = bar === null ? 0 : Math.max(0, bar.getBoundingClientRect().bottom);
    return {
      width: Math.min(window.innerWidth, document.documentElement.clientWidth || window.innerWidth),
      height: window.innerHeight,
      top: barBottom,
    };
  }

  cancelTimers() {
    if (this.hoverTimer !== null) {
      clearTimeout(this.hoverTimer);
      this.hoverTimer = null;
    }
  }

  /** 消す（状態の切り替え・描き直しの前にも呼べる） */
  hide() {
    this.cancelTimers();
    this.anchor?.removeAttribute('aria-describedby');
    this.anchor = null;
    this.currentKey = null;
    this.root.hidden = true;
  }

  // ---- 描き直し・情報の印 ----

  /** 対象が消えていたら消す。タッチ端末では、選択中のカードに情報の印を付け外しする */
  sync() {
    if (this.visible && this.anchor !== null && !this.anchor.isConnected) {
      this.hide();
    }
    const touch = document.getElementById('ebd-body')?.classList.contains('touch-device') === true;
    /** @type {Set<HTMLElement>} */
    const wanted = new Set();
    if (touch) {
      for (const picked of document.querySelectorAll(PICKED_SELECTOR)) {
        const target = infoTarget(picked);
        if (target !== null) {
          wanted.add(target);
        }
      }
    }
    for (const mark of document.querySelectorAll('.pe-tip-info')) {
      if (!(mark.parentElement !== null && wanted.has(mark.parentElement))) {
        mark.remove();
      }
    }
    for (const target of wanted) {
      if (target.querySelector(':scope > .pe-tip-info') === null) {
        target.append(this.createMark(target));
      }
    }
  }

  /**
   * 情報の印（32 × 32 px）。押すとその対象のツールチップが開く。カードの選択は変えない。
   * @param {HTMLElement} target
   * @returns {HTMLElement}
   */
  createMark(target) {
    const mark = el('button', {
      type: 'button',
      class: 'pe-tip-info',
      'aria-label': _('Card details'),
      onClick: (/** @type {Event} */ e) => {
        e.stopPropagation();
        e.preventDefault();
        if (this.visible && this.anchor === target) {
          this.hide();
        } else {
          this.show(target, 'touch');
        }
      },
    });
    mark.innerHTML = INFO_SVG;
    return mark;
  }
}
