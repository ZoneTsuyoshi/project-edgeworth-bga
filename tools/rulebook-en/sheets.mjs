// 原本のシート（ページ画像 1 枚）の一覧と寸法。ビルドと補助スクリプトが共有する。
// 座標はすべて「原本 PNG のピクセル（左上が原点、塗り足しを含む）」。

export const SHEETS = [
  ...Array.from({ length: 9 }, (_, i) => ({ id: `p0${i + 1}`, file: `p${i + 1}@4x.png`, kind: 'single' })),
  { id: 'p10-11', file: 'p10&11@4x.png', kind: 'spread' },
  { id: 'p12', file: 'p12@4x.png', kind: 'single' },
  { id: 'p13', file: 'p13@4x.png', kind: 'single' },
  { id: 'p14-15', file: 'p14&15@4x.png', kind: 'spread' },
  { id: 'p16-17', file: 'p16&17@4x.png', kind: 'spread' },
  { id: 'p18-19', file: 'p18&19@4x.png', kind: 'spread' },
  { id: 'p20', file: 'p20@4x.png', kind: 'single' },
  { id: 'p21', file: 'p21@4x.png', kind: 'single' },
  { id: 'p22-23', file: 'p22&23@4x.png', kind: 'spread' },
  { id: 'p24-25', file: 'p24&25@4x.png', kind: 'spread' },
  { id: 'p26', file: 'p26@4x.png', kind: 'single' },
  { id: 'p27', file: 'p27@4x.png', kind: 'single' },
  { id: 'p28', file: 'p28@4x.png', kind: 'single' },
];

// 仕上がり枠（原本 PNG の座標。x, y は左上、w, h は大きさ）。
// 単ページ: 画像に焼き込まれた赤線（端から 32〜35 px）の内側 36 px から、右は x=1710、下は y=2412 まで。
// 見開き: 赤線が無いので、四隅の飾りを単ページ（左 = p12・p20、右 = p13・p21）と突き合わせて決めた。
//   左ページの左上の飾りが単ページと同じ位置（0,0）、右ページの右端の飾りが x=3426 に一致する
//   （p10-11 は画像が 3471 px 幅で、x=3426 より右の 45 px は余白。p14-15 は 2474 px 高で、y=2450 より下の 24 px は余白）。
//   よって単ページと同じ内側 36 px の枠: x 36〜3389、y 36〜2412。
export const TRIM = {
  single: { x: 36, y: 36, w: 1675, h: 2377 },
  spread: { x: 36, y: 36, w: 3354, h: 2377 },
};

// 出力ページの大きさ（pt）。A5（148×210 mm）は Chrome が整数 pt に丸めるので最初から 420×595 pt（= 148.17×209.89 mm）にし、
// 見開きはその 2 倍に近い 839×595 pt（= 295.99 mm 幅）にする。拡大率は高さから決める。
export const PAGE_PT = { single: { w: 420, h: 595 }, spread: { w: 839, h: 595 } };

// 縞模様の地の採取に使う列（原本の座標）。赤線・縁の飾りを避ける。見開きは左右のページで縞の位相が違うことがある。
export const STRIPE_COLUMNS = {
  single: { l: [100, 1640] },
  spread: { l: [100, 1640], r: [1790, 3330], split: 1713 },
};
