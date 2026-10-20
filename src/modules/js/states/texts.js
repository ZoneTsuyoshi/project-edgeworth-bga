/**
 * 操作 UI で使う文面（`_()` で翻訳できるようにまとめる）。理由の符号はサーバーの blocked / 純粋関数の reason に対応する。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { cardInfo } from '../cards/card-text.js';
import { projectKey } from '../cards/sprite-layout.js';
import { nameOfNameId, parseHostKey } from '../cards/name-id.js';
import { format } from './logic/common.js';

/**
 * プロジェクトカードの名前。
 * @param {number} id
 * @returns {string}
 */
export function cardName(id) {
    return cardInfo(projectKey(id))?.name ?? projectKey(id);
}

/**
 * 起動の修正（宿主の鍵）の表示名。
 * @param {string} hostKey
 * @returns {string}
 */
export function modName(hostKey) {
    const host = parseHostKey(hostKey);
    if (host.cardKey !== null) {
        return cardInfo(host.cardKey)?.name ?? host.cardKey;
    }
    return _('Satellite card');
}

/**
 * 配備先として選べない理由。
 * @param {string} blocked
 * @returns {string}
 */
export function deployBlockedText(blocked) {
    switch (blocked) {
        case 'full':
            return _('This satellite already has 4 cards');
        case 'forbidden':
            return _('Cards cannot be deployed to this satellite');
        default:
            return blocked;
    }
}

/**
 * 打ち上げられない理由。
 * @param {string} blocked
 * @returns {string}
 */
export function launchBlockedText(blocked) {
    switch (blocked) {
        case 'active':
            return _('Already in operation');
        case 'no_project':
            return _('No Project cards deployed');
        case 'no_launch_pad':
            return _('You have no launch base');
        default:
            return blocked;
    }
}

/**
 * 転回できない理由。
 * @param {string} blocked
 * @returns {string}
 */
export function turnBlockedText(blocked) {
    switch (blocked) {
        case 'no_inactive_satellite':
            return _('No inactive satellite to place');
        case 'forbidden':
            return _('This satellite cannot be turned');
        default:
            return blocked;
    }
}

/**
 * コストと支払いの注記（売却すれば払えるときは、不足と売る枚数を添える。008 §2-6）。
 * @param {number} cost
 * @param {{ kind: 'free' | 'sell' | 'short', need: number, short: number }} pay
 * @returns {string}
 */
export function costNote(cost, pay) {
    if (pay.kind === 'free') {
        return cost === 0 ? _('Free') : format(_('Cost ${cost}'), { cost });
    }
    if (pay.kind === 'sell') {
        return format(_('Cost ${cost}: needs ${short} more Maya (sell ${need} card(s))'), { cost, short: pay.short, need: pay.need });
    }
    return format(_('Cost ${cost}: not enough Maya (short ${short})'), { cost, short: pay.short });
}

/**
 * 使う起動の修正の一覧の文面。
 * @param {{ hostKey: string, cardKey: string, reduction: number, cost: number }} option
 * @returns {string}
 */
export function modOptionText(option) {
    return format(_('${name}: cost -${reduction} (${cost})'), { name: nameOfNameId(option.cardKey), reduction: option.reduction, cost: option.cost });
}
