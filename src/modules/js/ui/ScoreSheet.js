/**
 * 名声の内訳の表（N32 finalScore。仕様書 010 §11.3、001 §12）。プレイヤーを列、区分 I〜IX を行にして、合計・順位・ミッションの達成を出す。
 * 終了時の通知で 1 人ずつ届くので、届いた分から表に足す。再読み込みでは getAllDatas の finalScores から全員分を復元する。
 * BGA 標準の最終得点（playerScore）は別に出る。この表は内訳の補足。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { CARD_INFO } from '../cards/card-info.js';
import { rankScores } from '../states/logic/score.js';
import { clear, el } from './dom.js';

/** @returns {Record<string, string>} 区分の見出し（001 §12） */
export function sectionLabels() {
    return {
        I: _('I Budget'),
        II: _('II Maya'),
        III: _('III Linked areas'),
        IV: _('IV Linked regions'),
        V: _('V Missions'),
        VI: _('VI Active satellites'),
        VII: _('VII Project cards'),
        VIII: _('VIII Rewards'),
        IX: _('IX End condition'),
    };
}

export class ScoreSheet {
    /**
     * @param {HTMLElement} root
     * @param {() => import('./normalize.js').GameModel} model
     * @param {(satId: number) => string} satName 衛星 ID → 表示名
     */
    constructor(root, model, satName) {
        this.root = root;
        this.model = model;
        this.satName = satName;
        /** @type {Map<number, import('../states/logic/score.js').PlayerScore>} */
        this.scores = new Map();
        root.classList.add('pe-scoresheet');
        root.hidden = true;
    }

    /**
     * 1 人分を足して描き直す。
     * @param {import('../states/logic/score.js').PlayerScore} score
     */
    add(score) {
        this.scores.set(score.playerId, score);
        this.render();
    }

    /**
     * @param {import('../states/logic/score.js').PlayerScore[]} list
     */
    setAll(list) {
        this.scores = new Map(list.map((s) => [s.playerId, s]));
        this.render();
    }

    /**
     * 内訳の部品（`kind:値`）の文面。
     * @param {string} part
     * @returns {string}
     */
    partText(part) {
        const [kind, rest = ''] = [part.slice(0, part.indexOf(':')), part.slice(part.indexOf(':') + 1)];
        const info = /** @type {Record<string, Record<string, any>>} */ (/** @type {unknown} */ (CARD_INFO));
        switch (kind) {
            case 'budget':
                return `${_('Budget')} ${rest}`;
            case 'maya':
                return `${rest} ${_('Maya')} (${_('1 fame per 10')})`;
            case 'land':
                return `${_('Land')} ${rest} × 2`;
            case 'sea':
                return `${_('Sea')} ${rest} × 1`;
            case 'regions':
                return `${rest} ${_('regions')}`;
            case 'regionNames':
                return rest.split('').join(' ');
            case 'mission': {
                const [no, tier, points] = rest.split('/');
                const name = info.missions[`M${String(no).padStart(3, '0')}`]?.name ?? `M${no}`;
                return `${name}: ${tier === 'extra' ? _('full') : _('partial')} +${points}`;
            }
            case 'sat': {
                const [satId, fame] = rest.split('/');
                return `${this.satName(Number(satId))}: ${fame}`;
            }
            case 'a':
                return `${_('Active')} ${rest}`;
            case 'b':
                return `${_('Standby (half)')} ${rest}`;
            case 'c':
                return `${_('Complete sets')} ${rest}`;
            case 'reward': {
                const [card, rank, points] = rest.split('/');
                return `${info.rewards[card]?.name ?? card}: #${rank} +${points}`;
            }
            default:
                return part;
        }
    }

    render() {
        clear(this.root);
        const model = this.model();
        const ranked = rankScores([...this.scores.values()].sort((a, b) => model.playerOrder.indexOf(a.playerId) - model.playerOrder.indexOf(b.playerId)));
        if (ranked.length === 0) {
            this.root.hidden = true;
            return;
        }
        this.root.hidden = false;
        const labels = sectionLabels();
        const head = el('tr', {}, [el('th', { scope: 'col' }, [_('Fame')]), ...ranked.map((s) => el('th', { scope: 'col', class: 'pe-score-player', dataset: { player: s.playerId } }, [
            model.players[s.playerId]?.name ?? String(s.playerId),
            el('span', { class: 'pe-score-rank' }, [`#${s.rank}`]),
        ]))]);
        const body = Object.keys(labels).map((section) => el('tr', { dataset: { section } }, [
            el('th', { scope: 'row' }, [labels[section]]),
            ...ranked.map((s) => {
                const row = s.rows.find((r) => r.section === section);
                return el('td', {}, [
                    el('span', { class: 'pe-score-points' }, [String(row?.points ?? 0)]),
                    ...(row?.parts ?? []).map((p) => el('span', { class: 'pe-score-part' }, [this.partText(p)])),
                ]);
            }),
        ]));
        const total = el('tr', { class: 'pe-score-total' }, [el('th', { scope: 'row' }, [_('Total')]), ...ranked.map((s) => el('td', {}, [el('span', { class: 'pe-score-points' }, [String(s.total)])]))]);
        const missions = el('tr', {}, [el('th', { scope: 'row' }, [_('Mission cards')]), ...ranked.map((s) => el('td', {}, s.missions.map((m) => {
            const name = /** @type {Record<string, any>} */ (/** @type {unknown} */ (CARD_INFO)).missions[`M${String(m.mission).padStart(3, '0')}`]?.name ?? `M${m.mission}`;
            return el('span', { class: `pe-score-part${m.achieved ? ' pe-score-ok' : ''}` }, [`${m.achieved ? '✓' : '–'} ${name}`]);
        })))]);
        this.root.append(
            el('div', { class: 'pe-choice-title' }, [_('Final fame')]),
            el('div', { class: 'pe-score-scroll' }, [el('table', { class: 'pe-score-table' }, [el('thead', {}, [head]), el('tbody', {}, [...body, total, missions])])]),
        );
    }
}
