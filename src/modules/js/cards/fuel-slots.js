/**
 * 詳細表示（紙どおりの重なり。仕様書 012 §4.2 案 B）で、配備カードの燃料庫の位置。
 * 本来は 99 枚の画像から燃料庫の座標を測ってデータ化する（012 §4.2）。測定が済むまでは、プロジェクトカードの
 * 左の縦帯（ロケットの胴体）に並ぶ青い四角の位置を、P001・P003 の目視から求めた共通の値で代用する【暫定】。
 * 割合はカードの幅・高さに対する値（左上が原点）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** 燃料庫の四角（カードに対する割合）。1 つ目の上端と、1 つ分の間隔 */
export const PROJECT_FUEL_SLOT = { x: 0.057, y: 0.508, w: 0.093, h: 0.045, pitch: 0.067 };

/**
 * 配備カード 1 枚の燃料庫の位置（上から下へ。index 0 が一番上）。
 * @param {number} count 燃料庫の数（1〜4）
 * @returns {{ x: number, y: number, w: number, h: number }[]}
 */
export function projectFuelSlots(count) {
  const s = PROJECT_FUEL_SLOT;
  return Array.from({ length: count }, (_unused, i) => ({ x: s.x, y: s.y + i * s.pitch, w: s.w, h: s.h }));
}

/**
 * 紙どおりに重ねたとき、燃料を下（衛星カード）から上へ数えた順番に並べた全スロット。
 * 衛星カードの燃料庫 2 つ（初期燃料庫 + 燃料庫 1。右の縦帯）が一番下、その上に配備カードを段の昇順で積む。
 * @param {number} satelliteSlots 衛星カードの燃料庫の数（002 §6 で 2）
 * @param {number[]} deployedSlots 配備カードの燃料庫の数（段の昇順）
 * @returns {{ source: 'satellite' | number, index: number }[]} source は衛星カード、または配備カードの添字
 */
export function fuelSlotOrder(satelliteSlots, deployedSlots) {
  const order = [];
  for (let i = 0; i < satelliteSlots; i++) {
    order.push({ source: /** @type {const} */ ('satellite'), index: i });
  }
  deployedSlots.forEach((count, card) => {
    // 紙では下から上へ進むので、カードの中でも下の枠から数える
    for (let i = count - 1; i >= 0; i--) {
      order.push({ source: card, index: i });
    }
  });
  return order;
}

/**
 * 衛星カード（横長）の燃料庫 2 つ（右の縦帯の中。割合は衛星カードに対する値）。index 0 が初期燃料庫（下）、1 が燃料庫（上）【暫定。目視】。
 * @type {{ x: number, y: number, w: number, h: number }[]}
 */
export const SATELLITE_FUEL_SLOTS = [
  { x: 0.885, y: 0.37, w: 0.085, h: 0.12 },
  { x: 0.885, y: 0.245, w: 0.085, h: 0.12 },
];
