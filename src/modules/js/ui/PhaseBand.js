/**
 * フェイズ帯（仕様書 012 §6）。I〜V の 5 つのフェイズ。計画フェイズの間は全部通常、公開後は選ばれたフェイズが点灯、
 * 選ばれなかったフェイズは暗く、現在のフェイズを強調する（008 §3-1）。スマホはローマ数字だけにして 1 行に収める。
 * ラウンド番号と循環フェイズの表示も同じ帯に出す。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { el } from './dom.js';

/** flow_phase（010 §7.2）→ アクション番号（A001〜A005 = I〜V） */
export const PHASE_ACTION = { BUDGET: 1, DEPLOY: 2, LAUNCH_TURN: 3, LINK: 4, PROPOSAL: 5 };

/**
 * @returns {{ no: number, numeral: string, name: string }[]}
 */
function phases() {
  return [
    { no: 1, numeral: 'I', name: _('Budget') },
    { no: 2, numeral: 'II', name: _('Deploy') },
    { no: 3, numeral: 'III', name: _('Launch/Turn') },
    { no: 4, numeral: 'IV', name: _('Link') },
    { no: 5, numeral: 'V', name: _('Proposal') },
  ];
}

export class PhaseBand {
    /**
     * @param {HTMLElement} root
     */
    constructor(root) {
        this.root = root;
        root.classList.add('pe-phase-band');
        this.round = 0;
        this.phase = 'SETUP';
        /** @type {number[] | null} 選ばれたアクション（公開後。null = 公開前） */
        this.selected = null;
        this.endNote = false;
        this.render();
    }

    /**
     * @param {{ round?: number, phase?: string, selected?: number[] | null, endNote?: boolean }} state
     */
    set(state) {
        this.round = state.round ?? this.round;
        this.phase = state.phase ?? this.phase;
        if (state.selected !== undefined) {
            this.selected = state.selected;
        }
        this.endNote = state.endNote ?? this.endNote;
        this.render();
    }

    render() {
        const current = PHASE_ACTION[/** @type {keyof typeof PHASE_ACTION} */ (this.phase)];
        const list = el('ol', { class: 'pe-phases' });
        for (const p of phases()) {
            const chosen = this.selected !== null && this.selected.includes(p.no);
            const dim = this.selected !== null && this.selected.length > 0 && !chosen;
            list.append(el('li', {
                class: `pe-phase${chosen ? ' pe-phase-chosen' : ''}${dim ? ' pe-phase-dim' : ''}${current === p.no ? ' pe-phase-current' : ''}`,
                'aria-current': current === p.no ? 'step' : null,
                title: p.name,
            }, [
                el('span', { class: 'pe-phase-numeral' }, [p.numeral]),
                el('span', { class: 'pe-phase-name' }, [p.name]),
            ]));
        }
        this.root.replaceChildren(
            el('span', { class: 'pe-phase-label' }, [this.phaseTitle()]),
            list,
            ...(this.endNote ? [el('span', { class: 'pe-end-note' }, [_('The game ends after this round')])] : []),
        );
    }

    /** @returns {string} */
    phaseTitle() {
        const round = this.round > 0 ? `${_('Round')} ${this.round}` : _('Setup');
        if (this.phase === 'PLANNING') {
            return `${round} · ${_('Planning')}`;
        }
        if (this.phase === 'ORBIT') {
            return `${round} · ${_('Orbit')}`;
        }
        if (this.phase === 'END') {
            return _('Game end');
        }
        return round;
    }
}
