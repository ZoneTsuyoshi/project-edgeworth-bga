/**
 * DOM の小さな補助（BGA に依存しない）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/**
 * @param {unknown} value
 * @returns {string}
 */
export function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

/**
 * 要素を作る。attrs の `class`・`dataset`・`style`（オブジェクト）・`on*` 以外は属性にする。
 * @param {string} tag
 * @param {Record<string, any>} [attrs]
 * @param {(Node | string | null | undefined | false)[]} [children]
 * @returns {HTMLElement}
 */
export function el(tag, attrs = {}, children = []) {
  const node = document.createElement(tag);
  for (const [key, value] of Object.entries(attrs)) {
    if (value === undefined || value === null || value === false) {
      continue;
    }
    if (key === 'class') {
      node.className = value;
    } else if (key === 'dataset') {
      for (const [k, v] of Object.entries(value)) {
        node.dataset[k] = String(v);
      }
    } else if (key === 'style') {
      for (const [k, v] of Object.entries(value)) {
        if (k.startsWith('--')) {
          node.style.setProperty(k, String(v));
        } else {
          /** @type {any} */ (node.style)[k] = v;
        }
      }
    } else if (key.startsWith('on') && typeof value === 'function') {
      node.addEventListener(key.slice(2).toLowerCase(), value);
    } else {
      node.setAttribute(key, value === true ? '' : String(value));
    }
  }
  for (const child of children) {
    if (child !== null && child !== undefined && child !== false) {
      node.append(child);
    }
  }
  return node;
}

/**
 * BGA は数値を文字列で渡すことがあるので、整数にそろえる。
 * @param {unknown} value
 * @param {number} [fallback]
 * @returns {number}
 */
export function int(value, fallback = 0) {
  const n = Number.parseInt(String(value), 10);
  return Number.isNaN(n) ? fallback : n;
}

/**
 * 子要素をすべて取り除く。
 * @param {Element} node
 */
export function clear(node) {
  while (node.firstChild !== null) {
    node.firstChild.remove();
  }
}
