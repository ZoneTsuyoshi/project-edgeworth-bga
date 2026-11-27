/**
 * 衛星 1 機の表示（仕様書 014 §4.1。012 §4.2〜§4.5 の詳細表示を唯一の表示にした）。
 * 紙どおりのロケット: 1 枚目は衛星カード上部の窪み（RB p.7 ①）に重ね、2 枚目以降はその上へ下側約 55 % が見える向きで重ね、燃料コマを燃料庫の位置に置く。
 * 待機中の衛星コマは衛星カードの格納庫の枠に置く。下に 2 行ぶんのキャプション（名前・燃料・状態）。
 * 起動効果の使用済みは歯車に × を重ね、カードを少し暗くする（008 §4）。歯車と × は画像に焼かない。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { createCard } from './CardView.js';
import { applySprite, COLOR_LETTERS, pieceSprite, projectKey } from './sprite-layout.js';
import { cardInfo, chipInfo, GEAR_PATH } from './card-text.js';
import { fuelSlotOrder, projectFuelSlots, SATELLITE_FUEL_SLOTS } from './fuel-slots.js';
import { SATELLITE_ROTATION } from '../board/geometry.js';
import { directionLabel } from '../board/Highlights.js';
import { el } from '../ui/dom.js';
import { refuse } from '../ui/refuse.js';
import { tipLine, tipText } from '../ui/Tips.js';
import { pickClass } from './pick-state.js';
import { format, satelliteLabel } from '../states/logic/common.js';

/** 衛星カードの燃料庫の数（002 §6。初期燃料庫 1 + 燃料庫 1） */
const SATELLITE_FUEL_COUNT = 2;

/**
 * @typedef {object} SatelliteData
 * @property {number} satId
 * @property {string} card 衛星カードの面（R001B など）
 * @property {string} [shape]
 * @property {number | null} cell
 * @property {string | null} dir
 * @property {number} fuelUsed
 * @property {number} fuelCapacity
 * @property {boolean} [grace]
 * @property {number} [activation] 衛星カードの起動効果の使用済み（0 / 1）
 * @property {{ id: number, activation: number }[]} cards 配備カード（段の昇順。id は P の番号）
 * @property {number} colorIndex 0〜3
 */

/**
 * 選択モードの衛星。state: selectable = 選べる（pe-can）、sellable = 売却すれば選べる（pe-can-sell）、blocked = 選べない（pe-off。理由は reason。押したときに出す）、
 * selected = 選択中（pe-picked）。選べるロケット・選択中のロケットには、土台の衛星カードの右上にコストの札（cost。014 §6.1）。
 * @typedef {object} SatellitePick
 * @property {'selectable' | 'sellable' | 'blocked' | 'selected'} state
 * @property {number} [cost] コスト（マヤ。あれば札を出す）
 * @property {string} [reason] 選べない理由（blocked のとき。無ければ一般の文）
 * @property {() => void} onPick
 */

/**
 * 衛星の上に出す「予定」（配備予定のカード、打ち上げ / 転回の予定など）。本人の画面だけ。
 * @typedef {object} SatellitePlan
 * @property {string} text 札の文言（例: 配備予定: カード名）
 * @property {string} [cardKey] 配備予定のカード（P001 形式。半透明の小さなカードを添える）
 */

/**
 * 衛星の表示名（形状とプラン。例: Stealth (Phobos)）。衛星カードの見出し・プルダウン・メッセージで同じ表記を使う。
 * @param {Pick<SatelliteData, 'satId' | 'card' | 'shape'>} sat
 * @returns {string}
 */
export function satelliteName(sat) {
  const info = cardInfo(sat.card);
  return satelliteLabel(sat.shape ?? info?.shape, info?.plan, `#${sat.satId}`);
}

/**
 * 燃料の表示値。c = 消費、C = 容量。R = C - c。燃料切れは c > C（008 §7）。
 * @param {number} fuelUsed
 * @param {number} capacity
 * @returns {{ remaining: number, outOfFuel: boolean, markerIndex: number }} markerIndex はスロットの添字（燃料切れは capacity = 枠の外）
 */
export function fuelGauge(fuelUsed, capacity) {
  const outOfFuel = fuelUsed > capacity;
  return {
    remaining: Math.max(0, capacity - fuelUsed),
    outOfFuel,
    markerIndex: outOfFuel ? capacity : Math.min(fuelUsed, Math.max(0, capacity - 1)),
  };
}

/**
 * 歯車（起動効果）のアイコン。使用済みは × を重ねる。
 * @param {boolean} used
 * @param {boolean} dim 稼働中のみ発動の効果が非稼働の衛星にあるとき（薄く）
 * @returns {HTMLElement}
 */
export function gearIcon(used, dim) {
  const node = el('span', {
    class: `pe-gear${used ? ' pe-gear-used' : ''}${dim ? ' pe-gear-dim' : ''}`,
    role: 'img',
    'aria-label': used ? _('Used this round') : _('Activated effect'),
  });
  tipText(node, used ? _('Used this round') : (dim ? _('Active satellites only') : _('Activated effect')));
  node.innerHTML = `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="${GEAR_PATH}" transform="scale(.9) translate(1.3 1.3)"/></svg>`
    + '<svg class="pe-gear-x" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19" stroke-width="3.2" stroke-linecap="round"/></svg>';
  return node;
}

/**
 * 24 × 24 の線（または塗り）の SVG（記号の文字は使わない。014 §1.2）。
 * @param {string} path
 * @param {string} cls
 * @param {{ transform?: string, fill?: boolean }} [opts]
 * @returns {SVGElement}
 */
function svgIcon(path, cls, opts = {}) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('class', cls);
    svg.setAttribute('aria-hidden', 'true');
    if (opts.transform !== undefined) {
        svg.style.transform = opts.transform;
    }
    const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    p.setAttribute('d', path);
    if (opts.fill === true) {
        p.setAttribute('fill', 'currentColor');
    } else {
        p.setAttribute('fill', 'none');
        p.setAttribute('stroke', 'currentColor');
        p.setAttribute('stroke-width', '2.4');
        p.setAttribute('stroke-linecap', 'round');
        p.setAttribute('stroke-linejoin', 'round');
    }
    svg.append(p);
    return svg;
}

export class SatelliteView {
    /**
     * @param {import('./CardView.js').Env} env
     * @param {import('../ui/Tips.js').Tips} tips
     * @param {SatelliteData} sat
     * @param {{ mine: boolean, ownerName?: string, compact?: boolean }} opts
     */
    constructor(env, tips, sat, opts) {
        this.env = env;
        this.tips = tips;
        this.sat = sat;
        this.opts = opts;
        this.root = el('div', { class: 'pe-sat', dataset: { satId: sat.satId } });
        /** @type {SatellitePick | null} */
        this.pick = null;
        /** @type {SatellitePlan[]} 自分の確定済み（または選択中）の予定。半透明の札で衛星の上に出す */
        this.plans = [];
        /** 問いの対象の衛星として強調するか（効果の窓。効果を持つカードのある衛星） */
        this.subject = false;
        /** @type {number | null} 問いの発生源の配備カード（効果の窓。pe-subject の破線で囲む） */
        this.subjectCard = null;
        /** @type {{ cards: Map<number, 'selectable' | 'selected'>, onPick: (card: number) => void } | null} 配備カードを押して選ぶ（ジャグラーの付け替え） */
        this.cardPicks = null;
        // 選択モードでの衛星のタップ（衛星カードの長押し・詳細ボタンの操作とは分ける）
        this.root.addEventListener('click', () => {
            if (this.pick === null) {
                return;
            }
            if (this.pick.state === 'blocked') {
                // 選べないロケットを押したら理由を出す（無反応にしない。014 §6.3）
                refuse(this.env, this.pick.reason);
                return;
            }
            this.pick.onPick();
        });
        this.render();
    }

    /**
     * 選択モードにする（配備先・打ち上げ / 転回の衛星・通信確立の衛星など。理由を note に出す）。null で解除。
     * @param {SatellitePick | null} pick
     */
    setPick(pick) {
        this.pick = pick;
        this.applyPick();
    }

    /** 選択モードの見た目（土台の衛星カードの 4 つのクラスと、コストの札）を今の pick に合わせる */
    applyPick() {
        const pick = this.pick;
        const card = this.root.querySelector(':scope .pe-sat-card');
        if (card === null) {
            return;
        }
        card.classList.remove('pe-can', 'pe-can-sell', 'pe-picked', 'pe-off');
        card.querySelector(':scope > .pe-cost-tag')?.remove();
        if (pick === null) {
            delete this.root.dataset.pick;
            return;
        }
        card.classList.add(pickClass(pick.state));
        this.root.dataset.pick = pick.state;
        if (pick.state !== 'blocked' && pick.cost !== undefined) {
            // 土台の衛星カードの右上にコストの札（マヤの硬貨 + 数。文字の行は出さない）
            const coin = el('span', { class: 'pe-icon pe-icon-maya', 'aria-hidden': 'true' });
            applySprite(coin, pieceSprite('maya_1'), this.env.imgUrl);
            card.append(el('span', { class: 'pe-cost-tag', role: 'img', 'aria-label': format(_('Cost ${cost}'), { cost: pick.cost }) }, [coin, String(pick.cost)]));
        }
    }

    /**
     * 問いの対象の衛星として強調する（枠と札）。
     * @param {boolean} subject
     */
    setSubject(subject) {
        this.subject = subject;
        this.applySubject();
    }

    /** 問いの対象の強調を今の subject に合わせる（土台の衛星カードの pe-subject と札） */
    applySubject() {
        this.root.querySelector(':scope .pe-sat-card')?.classList.toggle('pe-subject', this.subject);
        this.root.querySelector(':scope > .pe-subject-tag')?.remove();
        if (this.subject) {
            this.root.prepend(el('div', { class: 'pe-subject-tag', role: 'status' }, [_('This effect is for this satellite')]));
        }
    }

    /**
     * 問いの発生源の配備カードを強調する（`pe-subject`。このロケットに無いカードなら何も出ない）。null で解除。
     * @param {number | null} card プロジェクトカードの番号
     */
    setSubjectCard(card) {
        this.subjectCard = card;
        this.applySubjectCard();
    }

    /** 発生源の配備カードの強調を今の subjectCard に合わせる */
    applySubjectCard() {
        for (const node of /** @type {NodeListOf<HTMLElement>} */ (this.root.querySelectorAll('.pe-paper-card[data-key]'))) {
            const card = Number.parseInt((node.dataset.key ?? '').slice(1), 10);
            node.classList.toggle('pe-subject', this.subjectCard !== null && card === this.subjectCard);
        }
    }

    /**
     * 配備カードを押して選べるようにする（map に無いカードは対象外）。null で解除。
     * @param {{ cards: Map<number, 'selectable' | 'selected'>, onPick: (card: number) => void } | null} picks
     */
    setCardPicks(picks) {
        this.cardPicks = picks;
        this.applyCardPicks();
    }

    /** 配備カードの押せる状態を今の cardPicks に合わせる */
    applyCardPicks() {
        const picks = this.cardPicks;
        this.root.classList.toggle('pe-sat-card-picking', picks !== null && picks.cards.size > 0);
        for (const node of /** @type {NodeListOf<HTMLElement>} */ (this.root.querySelectorAll('.pe-paper-card[data-key]'))) {
            const card = Number.parseInt((node.dataset.key ?? '').slice(1), 10);
            const state = picks?.cards.get(card);
            node.classList.toggle('pe-can', state === 'selectable');
            node.classList.toggle('pe-picked', state === 'selected');
            node.onclick = state === undefined ? null : (/** @type {MouseEvent} */ e) => {
                e.stopPropagation();
                picks?.onPick(card);
            };
        }
    }

    /**
     * 予定の札を出す（空配列で消す）。
     * @param {SatellitePlan[]} plans
     */
    setPlans(plans) {
        this.plans = plans;
        this.applyPlans();
    }

    /** 予定の札を今の plans に合わせる（衛星の最上段に置く） */
    applyPlans() {
        this.root.querySelector(':scope > .pe-sat-plans')?.remove();
        this.root.classList.toggle('pe-sat-planned', this.plans.length > 0);
        if (this.plans.length === 0) {
            return;
        }
        const box = el('div', { class: 'pe-sat-plans', role: 'status' }, this.plans.map((plan) => {
            const children = [];
            if (plan.cardKey !== undefined) {
                children.push(createCard(this.env, plan.cardKey, { classes: ['pe-plan-card'] }));
            }
            children.push(el('span', { class: 'pe-plan-text' }, [plan.text]));
            return el('div', { class: 'pe-plan' }, children);
        }));
        this.root.prepend(box);
    }

    /**
     * @param {SatelliteData} sat
     */
    update(sat) {
        this.sat = sat;
        this.render();
    }

    /** 稼働中か（sat_cell が NULL でない。010 §5.1） */
    get active() {
        return this.sat.cell !== null && this.sat.cell !== undefined;
    }

    /** @returns {string[]} 配備カードのキー（P001 形式） */
    deployedKeys() {
        return this.sat.cards.map((c) => projectKey(c.id));
    }

    render() {
        const sat = this.sat;
        this.root.classList.toggle('pe-sat-active', this.active);
        this.root.dataset.color = COLOR_LETTERS[sat.colorIndex] ?? 'b';
        this.root.replaceChildren(this.renderDetail(), this.renderCaption());
        this.applyCardPicks();
        this.applyPick();
        this.applySubject();
        this.applySubjectCard();
        this.applyPlans();
    }

    /**
     * ロケットの下のキャプション（2 行ぶんの高さを確保して、ロケットの下端のそろいを崩さない）。
     * 1 行目 = 名前、2 行目 = 燃料（燃料コマ + 残り / 容量）・状態（稼働中は進行方向の矢印、待機中は Standby、燃料切れは警告）・起動効果の使用済み。
     */
    renderCaption() {
        const sat = this.sat;
        const g = fuelGauge(sat.fuelUsed, sat.fuelCapacity);
        const fuel = el('span', {
            class: `pe-cap-fuel${g.outOfFuel ? ' pe-cap-warn' : ''}`,
            role: 'img',
            'aria-label': `${_('Fuel')} ${g.remaining} / ${sat.fuelCapacity}`,
        }, [this.createFuelPiece('pe-cap-fuel-icon'), `${g.remaining}/${sat.fuelCapacity}`]);
        /** @type {HTMLElement} */
        let status;
        if (g.outOfFuel) {
            status = el('span', { class: 'pe-cap-status pe-cap-warn' }, [svgIcon('M12 3L2 21h20z M12 10v5 M12 17.5v1.5', 'pe-cap-icon'), el('span', { class: 'pe-cap-text' }, [_('Out of fuel')])]);
        } else if (this.active && sat.dir !== null && sat.dir !== undefined) {
            status = el('span', { class: 'pe-cap-status', role: 'img', 'aria-label': format(_('Active, heading ${direction}'), { direction: directionLabel(sat.dir) }) }, [
                svgIcon('M3 12l9-8v5h9v6h-9v5z', 'pe-cap-icon pe-cap-arrow', { transform: `rotate(${SATELLITE_ROTATION[sat.dir] ?? 0}deg)`, fill: true }),
            ]);
        } else {
            status = el('span', { class: 'pe-cap-status' }, [svgIcon('M9 5v14M15 5v14', 'pe-cap-icon pe-cap-standby'), el('span', { class: 'pe-cap-text' }, [_('Standby')])]);
        }
        return el('div', { class: 'pe-sat-caption' }, [
            el('span', { class: 'pe-cap-name' }, [satelliteName(sat)]),
            el('span', { class: 'pe-cap-row' }, [
                fuel,
                status,
                sat.activation === 1 ? el('span', { class: 'pe-sat-used' }, [gearIcon(true, false)]) : null,
            ]),
        ]);
    }

    /**
     * 衛星カードのツールチップの、本文の下に足す内容: 今の燃料・状態と、配備済みのカード名の一覧（014 §7）。
     * @returns {HTMLElement}
     */
    tipExtra() {
        const sat = this.sat;
        const g = fuelGauge(sat.fuelUsed, sat.fuelCapacity);
        const status = g.outOfFuel
            ? _('Out of fuel')
            : (this.active && sat.dir !== null && sat.dir !== undefined ? format(_('Active, heading ${direction}'), { direction: directionLabel(sat.dir) }) : _('Standby'));
        const box = el('div', { class: 'pe-tip-sat' }, [
            tipLine(g.outOfFuel ? `${_('Fuel')}: ${_('Out of fuel')}` : `${_('Fuel')} ${g.remaining}/${sat.fuelCapacity}`, this.createFuelPiece('pe-tip-fuel')),
            tipLine(status),
        ]);
        const names = this.sat.cards.map((c) => chipInfo(projectKey(c.id))?.name ?? projectKey(c.id));
        box.append(el('div', { class: 'pe-tip-deployed' }, [
            el('div', { class: 'pe-tip-sublabel' }, [_('Deployed cards')]),
            names.length === 0 ? el('div', {}, [_('None')]) : el('ol', {}, names.map((n) => el('li', {}, [n]))),
        ]));
        return box;
    }

    /** 衛星カード（横長）。ツールチップを結びつける */
    renderSatelliteCard() {
        const card = createCard(this.env, this.sat.card, { classes: ['pe-sat-card'] });
        // 待機中の衛星は、衛星コマを衛星カードの右下の衛星の形の枠に置く（稼働中はコマが盤面にある。作者要望 2026-10-20 その 3）
        if (!this.active) {
            const piece = el('span', { class: 'pe-sat-standby-piece', 'aria-hidden': 'true' });
            applySprite(piece, pieceSprite(`satellite_${(this.sat.shape ?? 'HAMMER').toLowerCase()}_${COLOR_LETTERS[this.sat.colorIndex] ?? 'b'}`), this.env.imgUrl);
            card.append(piece);
        }
        this.tips.card(card, this.sat.card, () => this.tipExtra());
        return card;
    }

    /** ロケット（紙どおり）。配備カードを下側約 55 % が見える向きで重ね、燃料コマを燃料庫の位置に置く */
    renderDetail() {
        const wrap = el('div', { class: 'pe-sat-paper' });
        const cards = this.sat.cards;
        const stack = el('div', { class: 'pe-paper-stack', style: { '--pe-paper-n': cards.length } });
        cards.forEach((c, i) => {
            stack.append(this.renderPaperCard(c, i));
        });
        const satCard = this.renderSatelliteCard();
        SATELLITE_FUEL_SLOTS.slice(0, SATELLITE_FUEL_COUNT).forEach((slot) => {
            satCard.append(el('span', {
                class: 'pe-fuel-slot',
                style: { left: `${slot.x * 100}%`, top: `${slot.y * 100}%`, width: `${slot.w * 100}%`, height: `${slot.h * 100}%` },
            }));
        });
        // 燃料コマを、下から数えた c 番目のスロットに置く（c が容量を超えたら枠の外）
        try {
            this.placePaperMarker(stack, satCard);
        } catch {
            // 燃料コマが置けなくても、カードの重なりは出す（キャプションの燃料で分かる）
        }
        if (cards.length > 0) {
            wrap.append(stack);
        }
        wrap.append(satCard);
        return wrap;
    }

    /**
     * ロケットの配備カード 1 枚。飾り（燃料庫・歯車・拡大表示）の組み立てで例外が出ても、カードそのものは必ず出す
     * （例外が出ると render() の途中で止まる。作者報告 2026-10-20）。
     * @param {{ id: number, activation: number }} c
     * @param {number} level 0 始まりの段
     * @returns {HTMLElement}
     */
    renderPaperCard(c, level) {
        const key = projectKey(c.id);
        const node = createCard(this.env, key, { classes: ['pe-paper-card', ...(c.activation === 1 ? ['pe-used'] : [])] });
        node.style.setProperty('--pe-paper-level', String(level));
        try {
            const info = chipInfo(key);
            projectFuelSlots(info?.fuelSlots ?? 0).forEach((slot) => {
                node.append(el('span', {
                    class: 'pe-fuel-slot',
                    style: { left: `${slot.x * 100}%`, top: `${slot.y * 100}%`, width: `${slot.w * 100}%`, height: `${slot.h * 100}%` },
                }));
            });
            // 紙のカードに歯車は印刷済み。使用済みのときだけ × を効果タイプの歯車の位置に重ねる
            if (info?.activated && c.activation === 1) {
                node.append(el('span', { class: 'pe-paper-used', role: 'img', 'aria-label': _('Used this round') }, [svgIcon('M5 5l14 14M19 5L5 19', 'pe-paper-used-x')]));
            }
            this.tips.card(node, key, () => (c.activation === 1 && info?.activated ? tipLine(_('Used this round'), null, 'pe-tip-reason') : null));
        } catch {
            // 飾りが付かなくても、カードの絵は出す
        }
        return node;
    }

    /**
     * @param {HTMLElement} stack
     * @param {HTMLElement} satCard
     */
    placePaperMarker(stack, satCard) {
        const slots = fuelSlotOrder(SATELLITE_FUEL_COUNT, this.sat.cards.map((c) => chipInfo(projectKey(c.id))?.fuelSlots ?? 0));
        const g = fuelGauge(this.sat.fuelUsed, slots.length);
        const target = g.outOfFuel ? null : slots[g.markerIndex];
        if (target === undefined || target === null) {
            return;
        }
        const host = target.source === 'satellite' ? satCard : /** @type {HTMLElement} */ (stack.children[target.source]);
        const rects = target.source === 'satellite' ? SATELLITE_FUEL_SLOTS : projectFuelSlots(chipInfo(projectKey(this.sat.cards[target.source].id))?.fuelSlots ?? 0);
        const r = rects[target.index];
        if (host === undefined || r === undefined) {
            return;
        }
        host.append(this.createFuelPiece('pe-fuel-piece pe-fuel-on-paper', { left: `${(r.x + r.w / 2) * 100}%`, top: `${(r.y + r.h / 2) * 100}%` }));
    }

    /**
     * 燃料コマ（所有者の色。コマ・アイコンのスプライト）。
     * @param {string} classes
     * @param {Record<string, string>} [style]
     * @returns {HTMLElement}
     */
    createFuelPiece(classes, style = {}) {
        const piece = el('span', { class: classes, style });
        applySprite(piece, pieceSprite(`fuel_${COLOR_LETTERS[this.sat.colorIndex] ?? 'b'}`), this.env.imgUrl);
        return piece;
    }
}
