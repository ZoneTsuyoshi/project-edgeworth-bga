/**
 * トレイの中に置く BGA 標準のボタンの部品（仕様書 014 §5.1。独自の見た目のボタンは作らない）。
 * すべて `bga.statusBar.addActionButton(label, callback, { color: 'secondary', destination })`。
 * 選択肢・トグルは、選んだものを青にせず `secondary` のまま `pe-picked`（金の輪郭）と `aria-pressed` で示す。
 * 記号のボタン（◀ ▶ − + ×）のラベルは文字でなく SVG（`ICONS`）。語は `title` 引数と `aria-label`。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** 24 × 24 の線の SVG（ボタンのラベル用。文字の記号は使わない） */
export const ICONS = {
  left: 'M15 5l-7 7 7 7',
  right: 'M9 5l7 7-7 7',
  minus: 'M5 12h14',
  plus: 'M12 5v14M5 12h14',
  close: 'M6 6l12 12M18 6L6 18',
};

/**
 * アイコンの SVG の文字列。
 * @param {keyof typeof ICONS} name
 * @returns {string}
 */
export function iconSvg(name) {
  return `<svg viewBox="0 0 24 24" class="pe-bicon" aria-hidden="true"><path d="${ICONS[name]}" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
}

/**
 * ボタンの置き方（BGA の `addActionButton` の戻り値は要素。それ以外が返るときは null）。
 * @param {Bga} bga
 * @param {HTMLElement} container
 * @param {string} label ボタンの中身（文字、または SVG の文字列。BGA の label は HTML として入る）
 * @param {() => void} onClick
 * @param {{ title?: string, disabled?: boolean, on?: boolean | null }} [opts] on = null はトグルでない（`aria-pressed` を付けない）
 * @returns {HTMLElement | null}
 */
function place(bga, container, label, onClick, opts = {}) {
  const result = /** @type {unknown} */ (bga.statusBar.addActionButton(label, onClick, {
    color: 'secondary',
    destination: container,
    disabled: opts.disabled === true,
    title: opts.title,
    classes: opts.on === true ? 'pe-picked' : undefined,
  }));
  const button = result instanceof HTMLElement ? result : null;
  if (button !== null) {
    if (opts.on !== null && opts.on !== undefined) {
      button.setAttribute('aria-pressed', opts.on ? 'true' : 'false');
    }
    if (opts.title !== undefined) {
      button.setAttribute('aria-label', opts.title);
    }
  }
  return button;
}

/**
 * 選択肢・トグルのボタン（選んだものは `pe-picked` + `aria-pressed="true"`）。
 * @param {Bga} bga
 * @param {HTMLElement} container
 * @param {string} label 文字（`html` を渡すと SVG などの HTML）
 * @param {boolean} on
 * @param {() => void} onClick
 * @param {{ html?: string, title?: string, disabled?: boolean }} [opts] html = ラベルを HTML にする（SVG + 語。語は escape 済みの固定の文言だけ）
 * @returns {HTMLElement | null}
 */
export function pressButton(bga, container, label, on, onClick, opts = {}) {
  return place(bga, container, opts.html ?? label, onClick, { ...opts, on });
}

/**
 * 記号のボタン（◀ ▶ − + ×。ラベルは SVG、語は `title` 引数と `aria-label`）。
 * @param {Bga} bga
 * @param {HTMLElement} container
 * @param {keyof typeof ICONS} icon
 * @param {string} title 語（Move earlier / Move later / Decrease / Increase / Remove）
 * @param {() => void} onClick
 * @param {boolean} [disabled]
 * @returns {HTMLElement | null}
 */
export function iconButton(bga, container, icon, title, onClick, disabled = false) {
  return place(bga, container, iconSvg(icon), onClick, { title, disabled, on: null });
}
