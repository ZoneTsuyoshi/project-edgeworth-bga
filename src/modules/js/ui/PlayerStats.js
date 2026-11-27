/**
 * プレイヤーの数値（マヤ・予算・手札の枚数・キューブの残り・通信確立した地域の数・2 枚の褒賞の比較値・途中の名声）の部品。
 * 数値はプレイヤーパネル（PlayerPanel）にだけ出す。どれも値は 1 つの数（014 §8）。地域 A〜H の列は廃止し、通信確立した数を 1 つのカウンターにした（014 §4.4）。
 * 名声は、ゲームオプション 105 が Visible のとき（getAllDatas の publicFame / 通知 N36）だけ出す。ミッションは含まない。ホバーで内訳（I〜IX。V を除く）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { applySprite, cardSprite, COLOR_LETTERS, pieceSprite } from '../cards/sprite-layout.js';
import { cardInfo } from '../cards/card-text.js';
import { fameBreakdown } from '../states/logic/score.js';
import { el } from './dom.js';
import { format } from '../states/logic/common.js';
import { sectionLabels } from './ScoreSheet.js';

/** キューブの総数（001） */
export const CUBES_TOTAL = 16;
/** 地域の数（A〜H） */
export const REGIONS_TOTAL = 8;

/**
 * アイコン付きのカウンター 1 つ。
 * @param {string} cls
 * @param {string} title
 * @param {HTMLElement | HTMLElement[]} icon
 * @param {string | number} value
 * @returns {HTMLElement}
 */
function counter(cls, title, icon, value) {
    return el('span', { class: `pe-counter ${cls}`, title, role: 'img', 'aria-label': title }, [...(Array.isArray(icon) ? icon : [icon]), el('span', { class: 'pe-counter-value' }, [String(value)])]);
}

/**
 * コマ・アイコンのスプライトの要素。
 * @param {import('../cards/CardView.js').Env} env
 * @param {string} name
 * @param {string} cls
 * @returns {HTMLElement}
 */
function pieceIcon(env, name, cls) {
    const icon = el('span', { class: `pe-icon ${cls}`, 'aria-hidden': 'true' });
    applySprite(icon, pieceSprite(name), env.imgUrl);
    return icon;
}

/**
 * 途中の名声のホバーの文面（合計と I〜IX の内訳。ミッションは含まない）。
 * @param {{ total: number, detail: Record<string, any> }} fame
 * @returns {string}
 */
export function fameTitle(fame) {
    const labels = sectionLabels();
    return [
        `${_('Fame')} ${fame.total} (${_('without missions')})`,
        ...fameBreakdown(fame.detail).map((row) => `${labels[row.section] ?? row.section}: ${row.points}`),
    ].join('\n');
}

/**
 * 名声のアイコン（SVG。記号の文字は使わない。014 §1.2）。
 * @returns {HTMLElement}
 */
function fameIcon() {
    const icon = el('span', { class: 'pe-icon-fame', 'aria-hidden': 'true' });
    icon.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8z" fill="currentColor"/></svg>';
    return icon;
}

/**
 * パネルのカウンターを 1 行分、並べて返す（マヤ・予算・手札の枚数・キューブの残り・通信確立した地域の数・褒賞の比較値 2 つ・名声）。
 * 名声は fame が null のとき（ゲームオプション 105 が Hidden）出さない。稼働中の衛星の数・色の丸は出さない（012 追補 U-5）。
 * 予算は、予算のアイコン（原本）とディスクを並べる（作者要望 2026-10-20）。
 * @param {import('../cards/CardView.js').Env} env
 * @param {import('./normalize.js').PlayerModel} p
 * @param {string[]} rewards 褒賞の 2 面
 * @param {{ total: number, detail: Record<string, any> } | null} fame 途中の名声（無ければ出さない）
 * @returns {HTMLElement[]}
 */
export function buildStats(env, p, rewards, fame) {
    const letter = COLOR_LETTERS[p.colorIndex] ?? 'b';
    const linked = p.linkedRegions ?? [];
    const rewardCounters = rewards.map((key) => {
        const info = cardInfo(key);
        const icon = el('span', { class: 'pe-icon pe-icon-reward', 'aria-hidden': 'true' });
        applySprite(icon, cardSprite(key, 'base'), env.imgUrl);
        const value = p.rewardValues[key];
        const name = info?.name ?? key;
        const compare = /** @type {Record<string, any>} */ (/** @type {unknown} */ (info))?.compare ?? '';
        return counter('pe-counter-reward', `${name}: ${compare}`, icon, value === undefined ? '–' : value);
    });
    return [
        counter('pe-counter-maya', _('Maya'), pieceIcon(env, 'maya_1', 'pe-icon-maya'), p.maya),
        counter('pe-counter-budget', _('Budget'), [pieceIcon(env, 'icon_budget', 'pe-icon-budget'), pieceIcon(env, `disk_${letter}`, 'pe-icon-disk')], p.budget),
        counter('pe-counter-hand', _('Cards in hand'), el('span', { class: 'pe-icon pe-icon-cardback', 'aria-hidden': 'true' }), p.handCount),
        counter('pe-counter-cubes', format(_('Cubes left: ${n} of 16'), { n: p.cubesLeft }), el('span', { class: 'pe-icon pe-icon-cube', dataset: { color: letter }, 'aria-hidden': 'true' }), p.cubesLeft),
        counter('pe-counter-regions', format(_('Linked regions: ${n} of 8 (${letters})'), { n: linked.length, letters: linked.length === 0 ? '-' : linked.join(', ') }), pieceIcon(env, 'area_A', 'pe-icon-region'), linked.length),
        ...rewardCounters,
        ...(fame === null ? [] : [counter('pe-counter-fame', fameTitle(fame), fameIcon(), fame.total)]),
    ];
}
