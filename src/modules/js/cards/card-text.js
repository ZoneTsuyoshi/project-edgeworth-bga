/**
 * カードの英語テキスト（ツールチップの本文と配備カードの情報。仕様書 014 §7、012 §4.2、§8.2）。
 * 元データは 002（`card-info.js`、scripts/build-card-info.mjs が生成）。ラベルは `_()` で翻訳できるようにする。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { CARD_INFO } from './card-info.js';
import { escapeHtml } from '../ui/dom.js';
import { pieceSprite } from './sprite-layout.js';
import { symbolsToHtml } from './text-symbols.js';

/**
 * @typedef {{ name: string, type?: string, cost?: number, fame?: number, fuelSlots?: number, timing?: string, kind?: string, active?: string, text?: string }} CardInfo
 */

/**
 * 効果タイミングの英語（002 §10 の用語から。表現は暫定）。
 * @returns {Record<string, string>}
 */
function timingLabels() {
  return {
    PLANNING_PHASE: _('Planning Phase'),
    BUDGET_PHASE: _('Budget Phase'),
    DEPLOYED: _('When deployed'),
    LAUNCHED: _('When launched'),
    TURNED: _('When turned'),
    LAUNCHED_OR_TURNED: _('When launched or turned'),
    LINKED: _('When a link is established'),
    PROPOSAL_PHASE: _('Proposal Phase'),
    ORBIT_PHASE: _('Orbit Phase'),
    ANYTIME: _('Anytime'),
  };
}

/** @returns {Record<string, string>} */
function kindLabels() {
  return {
    ACTIVATED: _('Activated'),
    CONSTANT: _('Constant'),
    ON_DEPLOY: _('On deploy'),
    ON_LAUNCH: _('On launch'),
  };
}

/** @returns {Record<string, string>} */
function typeLabels() {
  return { CREW: _('Crew'), DEVICE: _('Device'), SOFTWARE: _('Software') };
}

/**
 * カードのキー（P001・R001B・A001・M001・T001）から英語の情報を引く。無ければ null。
 * @param {string} key
 * @returns {(CardInfo & { group: 'project' | 'satellite' | 'reward' | 'mission' | 'action' }) | null}
 */
export function cardInfo(key) {
  const info = /** @type {Record<string, Record<string, any>>} */ (CARD_INFO);
  if (/^P\d{3}$/.test(key) && info.projects[key]) {
    return { group: 'project', ...info.projects[key], name: info.projects[key].name };
  }
  if (/^R\d{3}/.test(key) && info.satellites[key]) {
    const s = info.satellites[key];
    return { group: 'satellite', ...s, name: `${s.shape} (${s.plan})` };
  }
  if (/^T\d{3}$/.test(key) && info.rewards[key]) {
    return { group: 'reward', ...info.rewards[key] };
  }
  if (/^M\d{3}$/.test(key) && info.missions[key]) {
    return { group: 'mission', ...info.missions[key] };
  }
  if (/^A\d{3}$/.test(key) && info.actions[key]) {
    return { group: 'action', ...info.actions[key] };
  }
  return null;
}

/**
 * 配備カードのチップの記号。起動効果（歯車）・稼働中のみ発動（◎）・燃料庫の数。
 * @param {string} key P001 など
 * @returns {{ name: string, type: string, fame: number, fuelSlots: number, activated: boolean, activeOnly: boolean } | null}
 */
export function chipInfo(key) {
  const c = cardInfo(key);
  if (c === null || c.group !== 'project') {
    return null;
  }
  return {
    name: c.name,
    type: c.type ?? '',
    fame: c.fame ?? 0,
    fuelSlots: c.fuelSlots ?? 0,
    activated: c.kind === 'ACTIVATED',
    activeOnly: c.active === 'ACTIVE_ONLY',
  };
}

/**
 * 英語の効果文の HTML（エスケープ済み。`[Maya]` などの記号は小さなアイコンにする。改行は `<br>`）。
 * @param {string} text
 * @param {(file: string) => string} imgUrl 画像ファイル名 → URL（BGA では `bga.images.getImgUrl`）
 * @returns {string}
 */
export function effectTextHtml(text, imgUrl) {
  return symbolsToHtml(text, (piece) => {
    const sprite = pieceSprite(piece);
    if (sprite === null) {
      return null;
    }
    return {
      style: `background-image:url("${imgUrl(sprite.file)}");background-size:${sprite.size};background-position:${sprite.position};`,
      aspect: sprite.aspect,
    };
  });
}

/** 名声の星（SVG。記号の文字は使わない。014 §1.2） */
export const FAME_SVG = '<svg class="pe-tip-fame" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8z" fill="currentColor"/></svg>';
/** 歯車（起動効果）の線 */
export const GEAR_PATH = 'M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Zm8.5 5.2v-3.4l-2.1-.4a6.6 6.6 0 0 0-.9-2.1l1.2-1.8-2.4-2.4-1.8 1.2a6.6 6.6 0 0 0-2.1-.9L12.1 2H8.7l-.4 2.1a6.6 6.6 0 0 0-2.1.9L4.4 3.8 2 6.2l1.2 1.8c-.4.7-.7 1.4-.9 2.1l-2.1.4v3.4l2.1.4c.2.7.5 1.4.9 2.1L2 18.2l2.4 2.4 1.8-1.2c.7.4 1.4.7 2.1.9l.4 2.1h3.4l.4-2.1c.7-.2 1.4-.5 2.1-.9l1.8 1.2 2.4-2.4-1.2-1.8c.4-.7.7-1.4.9-2.1l2.1-.4Z';
const GEAR_SVG = `<svg class="pe-tip-gear" viewBox="0 0 24 24" aria-hidden="true"><path d="${GEAR_PATH}" transform="scale(.9) translate(1.3 1.3)" fill="currentColor"/></svg>`;

/**
 * ツールチップの本文（HTML 文字列。値は escape 済み。014 §7）。
 * 名前 → アイコンつきの数値の行（コスト・名声・燃料庫）→ 種類・効果のタイミング・タイプ・稼働条件（アイコン + 語）→ 英語の効果文。
 * @param {string} key
 * @param {(file: string) => string} imgUrl 画像ファイル名 → URL
 * @returns {string}
 */
export function cardTextHtml(key, imgUrl) {
  const c = cardInfo(key);
  if (c === null) {
    return `<div class="pe-tip-name">${escapeHtml(key)}</div>`;
  }
  const icon = (/** @type {string} */ symbol) => effectTextHtml(`[${symbol}]`, imgUrl);
  /** @type {string[]} */
  const stats = [];
  const stat = (/** @type {string} */ iconHtml, /** @type {string} */ label, /** @type {unknown} */ value) => {
    if (value !== undefined && value !== null && value !== '') {
      stats.push(`<li class="pe-tip-stat">${iconHtml}<b>${escapeHtml(value)}</b><span class="pe-tip-stat-label">${escapeHtml(label)}</span></li>`);
    }
  };
  /** @type {string[]} */
  const rows = [];
  const row = (/** @type {string} */ label, /** @type {unknown} */ value, /** @type {string} */ iconHtml = '') => {
    if (value !== undefined && value !== null && value !== '') {
      rows.push(`<dt>${escapeHtml(label)}</dt><dd>${iconHtml}${escapeHtml(value)}</dd>`);
    }
  };
  if (c.group === 'project') {
    stat(icon('Maya'), _('Cost'), c.cost);
    stat(FAME_SVG, _('Fame'), c.fame);
    stat(icon('Fuel'), _('Fuel slots'), c.fuelSlots);
    row(_('Type'), typeLabels()[c.type ?? ''] ?? c.type, icon(c.type ?? ''));
  } else if (c.group === 'satellite') {
    const s = /** @type {Record<string, any>} */ (/** @type {unknown} */ (c));
    stat(icon('Maya'), _('Launch cost'), s.launchCost);
    stat(FAME_SVG, _('Fame'), c.fame);
    stat(icon('Fuel'), _('Fuel slots'), c.fuelSlots);
  } else if (c.group === 'reward') {
    const r = /** @type {Record<string, any>} */ (/** @type {unknown} */ (c));
    row(_('Compared by'), r.compare);
    row(_('Fame by rank (1st / 2nd / 3rd / 4th)'), Array.isArray(r.points) ? r.points.join(' / ') : '', FAME_SVG);
  } else if (c.group === 'mission') {
    const m = /** @type {Record<string, any>} */ (/** @type {unknown} */ (c));
    row(_('Fame (partial / full)'), `${m.fameEasy} / ${m.fameFull}`, FAME_SVG);
  }
  if (c.group === 'project' || c.group === 'satellite') {
    row(_('Timing'), timingLabels()[c.timing ?? ''] ?? c.timing);
    row(_('Effect type'), kindLabels()[c.kind ?? ''] ?? c.kind, c.kind === 'ACTIVATED' ? GEAR_SVG : '');
    row(_('Condition'), c.active === 'ACTIVE_ONLY' ? _('Active satellites only') : _('Any satellite'));
  }
  let text = c.text ?? '';
  if (c.group === 'action') {
    const a = /** @type {Record<string, any>} */ (/** @type {unknown} */ (c));
    text = `${a.main}\n${_('Action Selection Bonus')}: ${a.bonus}`;
  }
  const name = c.group === 'action' ? `${/** @type {any} */ (c).numeral} ${c.name}` : c.name;
  return `<div class="pe-tip-name">${escapeHtml(name)}</div>`
    + (stats.length > 0 ? `<ul class="pe-tip-stats">${stats.join('')}</ul>` : '')
    + (rows.length > 0 ? `<dl class="pe-tip-facts">${rows.join('')}</dl>` : '')
    + (text === '' ? '' : `<p class="pe-tip-effect">${effectTextHtml(text, imgUrl)}</p>`);
}
