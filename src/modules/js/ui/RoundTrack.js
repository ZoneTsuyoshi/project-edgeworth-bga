/**
 * ラウンドトラック（仕様書 014 §3）。フェイズ帯（PhaseBand）の置き換え。箱・背景・枠なしで卓の上に 1 行、押せない見た目。
 * ROUND 3   Planning › I Budget › II Deploy › … › Orbit。現在の段 = 白・太字・金の下線、これから行う = 通常、行わない = 薄く取り消し線、済んだ = 補助色。
 * I〜V の後ろに、そのアクションを選んだプレイヤー（アクション選択ボーナスの保持者）の色の小さな四角。段のツールチップは仕様書 014 §7（PR 3）。
 * 状態の計算は round-track.js（純粋関数）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { el } from './dom.js';
import { tipCard, tipText } from './Tips.js';
import { roundTrackState } from './round-track.js';

/** @returns {Record<number, { numeral: string, name: string }>} 段の番号 → ローマ数字と名前 */
function stepLabels() {
  return {
    0: { numeral: '', name: _('Planning') },
    1: { numeral: 'I', name: _('Budget') },
    2: { numeral: 'II', name: _('Deploy') },
    3: { numeral: 'III', name: _('Launch/Turn') },
    4: { numeral: 'IV', name: _('Link') },
    5: { numeral: 'V', name: _('Proposal') },
    6: { numeral: '', name: _('Orbit') },
  };
}

export class RoundTrack {
    /**
     * @param {HTMLElement} root `#pe-round`
     * @param {(playerId: number) => { color: string, name: string } | null} lookup プレイヤーの色（b / g / r / y）と名前
     */
    constructor(root, lookup) {
        this.root = root;
        this.lookup = lookup;
        root.classList.add('pe-round');
        /** @type {Parameters<typeof roundTrackState>[0]} */
        this.input = { round: 0, phase: 'SETUP', bonusHolders: {}, playerOrder: [] };
        this.render();
    }

    /**
     * @param {Partial<Parameters<typeof roundTrackState>[0]>} input 変わった項目だけ
     */
    set(input) {
        this.input = { ...this.input, ...input };
        this.render();
    }

    render() {
        const state = roundTrackState(this.input);
        if (state.mode !== 'round') {
            const label = state.mode === 'end' ? _('Game end') : _('Setup');
            this.root.replaceChildren(el('span', { class: 'pe-round-label' }, [label]));
            return;
        }
        const labels = stepLabels();
        const list = el('ol', { class: 'pe-round-steps' });
        for (const step of state.steps) {
            const { numeral, name } = labels[step.no];
            const marks = step.holders.flatMap((id) => {
                const info = this.lookup(id);
                if (info === null) {
                    return [];
                }
                const mark = el('span', { class: 'pe-round-mark', role: 'img', 'aria-label': info.name, dataset: { color: info.color } });
                tipText(mark, info.name);
                return [mark];
            });
            const item = el('li', {
                class: `pe-round-step pe-round-${step.state}`,
                'aria-current': step.state === 'current' ? 'step' : null,
                'aria-label': numeral === '' ? name : `${numeral} ${name}`,
            }, [
                numeral === '' ? null : el('span', { class: 'pe-round-numeral', 'aria-hidden': 'true' }, [numeral]),
                el('span', { class: 'pe-round-name' }, [name]),
                ...marks,
            ]);
            // I〜V の段はそのアクションカードのツールチップ、計画・循環の段は文字（014 §7）
            if (step.no >= 1 && step.no <= 5) {
                tipCard(item, `A00${step.no}`);
            } else {
                tipText(item, step.no === 0 ? _('Planning Phase') : _('Orbit Phase'));
            }
            list.append(item);
        }
        this.root.replaceChildren(el('span', { class: 'pe-round-label' }, [`${_('Round')} ${state.round}`]), list);
    }
}
