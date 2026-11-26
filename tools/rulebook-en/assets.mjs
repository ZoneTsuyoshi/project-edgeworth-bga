// インラインアイコンの出どころ。ビルドのたびに build/rulebook-en/assets/ へ書き出し、icons.css を生成する。
// 切り出しは { page: '原本のシートID', x, y, w, h }（原本 PNG の座標）で再現できるように書く。
// クラス名は `<i class="ic NAME"></i>` の NAME。

export const ICON_HEIGHT = 160; // 書き出しの高さ上限（px）。インライン表示には十分

export const ICONS = {
  // data/icons/ の原本
  maya: { src: 'icons/マヤ@4x.png' },
  'maya-spend': { src: 'icons/マヤ消費@4x.png' },
  'maya-gain': { src: 'icons/マヤ獲得@4x.png' },
  budget: { src: 'icons/予算@4x.png' },
  'fame-base': { src: 'icons/名声@4x.png' }, // `ic fame` が使う（数字は文字で重ねる）
  cost: { src: 'icons/コスト@4x.png' },
  fuel: { src: 'icons/燃料@4x.png' },
  crew: { src: 'icons/crew@4x.png' },
  device: { src: 'icons/device@4x.png' },
  software: { src: 'icons/software@4x.png' },
  star: { src: 'icons/star@4x.png' },
  dagger: { src: 'icons/dagger@4x.png' },
  all: { src: 'icons/all@4x.png' },
  // 効果発動タイミング・効果タイプ
  'tm-budget': { src: 'icons/予算発行@4x.png' },
  'tm-deploy': { src: 'icons/配備@4x.png' },
  'tm-launch': { src: 'icons/打ち上げ@4x.png' },
  'tm-turn': { src: 'icons/転回@4x.png' },
  'tm-link': { src: 'icons/通信確立@4x.png' },
  'tm-proposal': { src: 'icons/企画@4x.png' },
  'tm-orbit': { src: 'icons/循環@4x.png' },
  constant: { src: 'icons/常時@4x.png' },
  activated: { src: 'icons/起動@4x.png' },
  // 地域アイコン A〜H（`ic region-a` など）
  ...Object.fromEntries('abcdefgh'.split('').map((c) => [`region-${c}`, { src: `icons/area_${c.toUpperCase()}@4x.png` }])),
  // 区域の種類ごとの六角形（data/boards/area/）
  'hex-sea': { src: 'boards/area/海@4x.png' },
  'hex-deep-sea': { src: 'boards/area/深海@4x.png' },
  'hex-reef': { src: 'boards/area/岩礁@4x.png' },
  'hex-land': { src: 'boards/area/陸@4x.png' },
  'hex-forest': { src: 'boards/area/森林@4x.png' },
  'hex-mountain': { src: 'boards/area/山岳@4x.png' },
  'hex-snowfield': { src: 'boards/area/雪原@4x.png' },
  // ここから先はルールブックのページ画像（p27 の用語表）から切り出す。keyOut: true は、切り出した範囲の
  // 「ページの地の色（暗い縞）」を透明にする（縞の線がアイコンの角に写り込まないように）
  'plan-a': { page: 'p27', x: 147, y: 1346, w: 50, h: 46, keyOut: true },      // 計画フェイズ中（黒い八角形に A）
  active: { page: 'p27', x: 456, y: 630, w: 90, h: 90, keyOut: true },        // 稼働中のみ効果発動（青緑の二重丸）
  inactive: { page: 'p27', x: 458, y: 745, w: 90, h: 90, keyOut: true },      // 灰色の丸（稼働有無を問わない・非稼働）
  'p08-hex-light': { page: 'p08', x: 964, y: 966, w: 59, h: 68, keyOut: true },  // ミッション説明の薄い六角形
  'p08-hex-dark': { page: 'p08', x: 1086, y: 1096, w: 58, h: 66, keyOut: true },  // 同じく濃い六角形
  'p21-fame-star': { page: 'p21', x: 937, y: 926, w: 66, h: 66, keyOut: true },  // 星入りの名声（五角形に星。数字なし）
  'p21-fame-star-pu': { page: 'p21', x: 507, y: 1645, w: 62, h: 60, keyOut: false },  // 同じ星入りの名声（紫の箱の上用。白い縁取り）
  // 担当者が必要に応じて足す。例: 'name': { page: 'p06', x: 0, y: 0, w: 100, h: 100, keyOut: false },
};
