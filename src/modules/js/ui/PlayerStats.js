/**
 * プレイヤーの数値（マヤ・予算・手札の枚数・稼働中の衛星・通信確立数・2 枚の褒賞の比較値・途中の名声）の部品。
 * プレイヤーパネル（PlayerPanel）と、自分の領域の見出しの行（PlayerArea）で同じ内容を出す（仕様書 012 §5）。
 * 名声は、ゲームオプション 105 が Visible のとき（getAllDatas の publicFame / 通知 N36）だけ出す。ミッションは含まない。ホバーで内訳（I〜IX。V を除く）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { applySprite, cardSprite, COLOR_LETTERS, pieceSprite } from '../cards/sprite-layout.js';
import { cardInfo } from '../cards/card-text.js';
import { fameBreakdown } from '../states/logic/score.js';
import { el } from './dom.js';
import { REGION_LETTERS } from './link-regions.js';
import { sectionLabels } from './ScoreSheet.js';

/** キューブの総数（001） */
export const CUBES_TOTAL = 16;
/** 衛星の数（1 人 5 機） */
export const SATELLITES_TOTAL = 5;

/**
 * アイコン付きのカウンター 1 つ。
 * @param {string} cls
 * @param {string} title
 * @param {HTMLElement | HTMLElement[]} icon
 * @param {string | number} value
 * @returns {HTMLElement}
 */
function counter(cls, title, icon, value) {
    return el('span', { class: `pe-counter ${cls}`, title }, [...(Array.isArray(icon) ? icon : [icon]), el('span', { class: 'pe-counter-value' }, [String(value)])]);
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
 * 地域 A〜H の通信確立の一覧（キューブを 1 つでも置いた地域は濃く、置いていない地域は薄く。作者要望 2026-10-20 その 3）。
 * @param {import('../cards/CardView.js').Env} env
 * @param {string[]} linked 通信確立済みの地域（A〜H）
 * @returns {HTMLElement}
 */
export function regionRow(env, linked) {
    return el('span', { class: 'pe-regions', role: 'group', 'aria-label': _('Linked regions') }, REGION_LETTERS.map((letter) => {
        const on = linked.includes(letter);
        const title = (on ? _('Region ${letter}: linked') : _('Region ${letter}: not linked')).replace('${letter}', letter);
        const node = el('span', { class: `pe-region${on ? ' pe-region-linked' : ''}`, title, role: 'img', 'aria-label': title });
        node.append(pieceIcon(env, `area_${letter}`, 'pe-icon-region'));
        return node;
    }));
}

/**
 * 数値のカウンターを 2 行に並べて返す（1 行目: マヤ・予算・手札、2 行目: 稼働中の衛星・通信確立数・褒賞・名声）。地域 A〜H の通信確立は 2 行目の後ろに足す。
 * 予算は、予算のアイコン（原本）とディスクを並べる（ディスクだけでは何の数か分かりにくいため。作者要望 2026-10-20）。
 * @param {import('../cards/CardView.js').Env} env
 * @param {import('./normalize.js').PlayerModel} p
 * @param {string[]} rewards 褒賞の 2 面
 * @param {{ total: number, detail: Record<string, any> } | null} fame 途中の名声（無ければ出さない）
 * @returns {{ first: HTMLElement[], second: HTMLElement[] }}
 */
export function buildStats(env, p, rewards, fame) {
    const letter = COLOR_LETTERS[p.colorIndex] ?? 'b';
    const active = p.satellites.filter((s) => s.cell !== null).length;
    const rewardCounters = rewards.map((key) => {
        const info = cardInfo(key);
        const icon = el('span', { class: 'pe-icon pe-icon-reward', 'aria-hidden': 'true' });
        applySprite(icon, cardSprite(key, 'base'), env.imgUrl);
        const value = p.rewardValues[key];
        const name = info?.name ?? key;
        const compare = /** @type {Record<string, any>} */ (/** @type {unknown} */ (info))?.compare ?? '';
        return counter('pe-counter-reward', `${name}: ${compare}`, icon, value === undefined ? '–' : value);
    });
    return {
        first: [
            counter('pe-counter-maya', _('Maya'), pieceIcon(env, 'maya_1', 'pe-icon-maya'), p.maya),
            counter('pe-counter-budget', _('Budget'), [pieceIcon(env, 'icon_budget', 'pe-icon-budget'), pieceIcon(env, `disk_${letter}`, 'pe-icon-disk')], p.budget),
            counter('pe-counter-hand', _('Cards in hand'), el('span', { class: 'pe-icon pe-icon-cardback', 'aria-hidden': 'true' }), p.handCount),
        ],
        second: [
            counter('pe-counter-sats', _('Active satellites'), pieceIcon(env, `satellite_hammer_${letter}`, 'pe-icon-sat'), `${active} / ${SATELLITES_TOTAL}`),
            counter('pe-counter-cubes', _('Cubes placed'), el('span', { class: 'pe-icon pe-icon-cube', dataset: { color: letter }, 'aria-hidden': 'true' }), `${CUBES_TOTAL - p.cubesLeft} / ${CUBES_TOTAL}`),
            ...rewardCounters,
            ...(fame === null ? [] : [counter('pe-counter-fame', fameTitle(fame), el('span', { class: 'pe-icon-fame', 'aria-hidden': 'true' }, ['★']), fame.total)]),
            regionRow(env, p.linkedRegions),
        ],
    };
}
