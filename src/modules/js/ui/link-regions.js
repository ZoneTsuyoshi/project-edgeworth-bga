/**
 * 通信確立済みの地域（A〜H）の計算。プレイヤーのサマリー（PlayerStats）で、地域ごとに通信確立済みかを出すのに使う（仕様書 012 §5）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** 地域の文字（A〜H） */
export const REGION_LETTERS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

/**
 * キューブを 1 つでも置いている地域（通信確立済みの地域）を A〜H の順で返す。
 * @param {{ cell: number }[]} links プレイヤーのキューブ（区域ごと）
 * @param {{ cell: number, region: string }[]} cells 盤面の区域と、それが属する地域
 * @returns {string[]}
 */
export function linkedRegions(links, cells) {
  const regionOf = new Map(cells.map((c) => [Number(c.cell), String(c.region).toUpperCase()]));
  const found = new Set(links.map((l) => regionOf.get(Number(l.cell))));
  return REGION_LETTERS.filter((r) => found.has(r));
}

/**
 * プレイヤーの linkedRegions を links と盤面から計算し直す。
 * @param {{ links: { cell: number }[], linkedRegions: string[] }} player
 * @param {{ cells: { cell: number, region: string }[] } | null} board
 */
export function refreshLinkedRegions(player, board) {
  player.linkedRegions = board === null ? [] : linkedRegions(player.links, board.cells);
}
