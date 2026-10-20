/**
 * カードの英語テキスト（拡大表示のテキストパネルと配備カードのチップ。仕様書 012 §4.2、§4.6、§8.2）。
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

/**
 * 拡大表示のテキストパネル（HTML 文字列。値は escape 済み）。
 * 名前・種類・コスト・名声・効果タイミング・効果タイプ・稼働条件・英語の効果文（012 §4.6）。
 * @param {string} key
 * @param {(file: string) => string} imgUrl 画像ファイル名 → URL
 * @returns {string}
 */
export function cardTextHtml(key, imgUrl) {
  const c = cardInfo(key);
  if (c === null) {
    return `<div class="pe-tip-name">${escapeHtml(key)}</div>`;
  }
  /** @type {string[]} */
  const rows = [];
  const row = (/** @type {string} */ label, /** @type {unknown} */ value) => {
    if (value !== undefined && value !== null && value !== '') {
      rows.push(`<dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd>`);
    }
  };
  if (c.group === 'project') {
    row(_('Type'), typeLabels()[c.type ?? ''] ?? c.type);
    row(_('Cost'), c.cost);
    row(_('Fame'), c.fame);
    row(_('Fuel slots'), c.fuelSlots);
  } else if (c.group === 'satellite') {
    const s = /** @type {Record<string, any>} */ (/** @type {unknown} */ (c));
    row(_('Launch cost'), s.launchCost);
    row(_('Fame'), c.fame);
    row(_('Fuel slots'), c.fuelSlots);
  } else if (c.group === 'reward') {
    const r = /** @type {Record<string, any>} */ (/** @type {unknown} */ (c));
    row(_('Compared by'), r.compare);
    row(_('Fame by rank (1st / 2nd / 3rd / 4th)'), Array.isArray(r.points) ? r.points.join(' / ') : '');
  } else if (c.group === 'mission') {
    const m = /** @type {Record<string, any>} */ (/** @type {unknown} */ (c));
    row(_('Fame (partial / full)'), `${m.fameEasy} / ${m.fameFull}`);
  }
  if (c.group === 'project' || c.group === 'satellite') {
    row(_('Timing'), timingLabels()[c.timing ?? ''] ?? c.timing);
    row(_('Effect type'), kindLabels()[c.kind ?? ''] ?? c.kind);
    row(_('Condition'), c.active === 'ACTIVE_ONLY' ? _('Active satellites only') : _('Any satellite'));
  }
  let text = c.text ?? '';
  if (c.group === 'action') {
    const a = /** @type {Record<string, any>} */ (/** @type {unknown} */ (c));
    text = `${a.main}\n${_('Action Selection Bonus')}: ${a.bonus}`;
  }
  const name = c.group === 'action' ? `${/** @type {any} */ (c).numeral} ${c.name}` : c.name;
  return `<div class="pe-tip-name">${escapeHtml(name)}</div>`
    + (rows.length > 0 ? `<dl class="pe-tip-facts">${rows.join('')}</dl>` : '')
    + (text === '' ? '' : `<p class="pe-tip-effect">${effectTextHtml(text, imgUrl)}</p>`);
}
