// 002（カードデータ仕様）の英語名・数値・効果文を、クライアント（拡大表示のテキストパネルと配備カードのチップ）用の
// `src/modules/js/cards/card-info.js` に書き出す（仕様書 012 §4.6、§8.2）。日本語の文は出さない（カード画像に印刷されている）。
// 実行: npm run build:card-info（生成） / npm run check:card-info（docs と一致するかの検査。lint に含む）
import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { readCardSpec } from './lib/card-spec.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(ROOT, 'src/modules/js/cards/card-info.js');

export function buildSource() {
  const spec = readCardSpec(path.join(ROOT, 'docs/specs/002-card-data.md'));
  const byId = (list, pick) => Object.fromEntries(list.map((c) => [c.id, pick(c)]));
  const info = {
    projects: byId(spec.projects, (c) => ({
      name: c.nameEn, type: c.type, cost: c.cost, fame: c.fame, fuelSlots: c.fuelSlots,
      timing: c.timing, kind: c.effectKind, active: c.activeCondition, text: c.textEn,
    })),
    satellites: byId(spec.satellites, (c) => ({
      shape: c.shape, plan: c.plan, color: c.color, launchCost: c.launchCost, fame: c.fame, fuelSlots: c.fuelSlots,
      timing: c.timing, kind: c.effectKind, active: c.activeCondition, text: c.textEn,
    })),
    rewards: byId(spec.rewards, (c) => ({ name: c.nameEn, compare: c.compareEn, points: c.points })),
    missions: byId(spec.missions, (c) => ({ name: c.name, fameEasy: c.fameEasy, fameFull: c.fameFull })),
    actions: byId(spec.actions, (c) => ({ numeral: c.numeral, name: c.nameEn, main: c.mainEn, bonus: c.bonusEn })),
  };
  return [
    '// 自動生成: scripts/build-card-info.mjs（npm run build:card-info）。編集しない。元は docs/specs/002-card-data.md（英語名・数値・英語の効果文）。',
    `export const CARD_INFO = ${JSON.stringify(info, null, 1)};`,
    '',
  ].join('\n');
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const source = buildSource();
  if (process.argv.includes('--check')) {
    let current = '';
    try {
      current = readFileSync(OUT, 'utf8');
    } catch {
      // 無ければ不一致として扱う
    }
    if (current !== source) {
      console.error('card-info.js が 002 と一致しません。npm run build:card-info で再生成してください。');
      process.exit(1);
    }
    console.log('check-card-info: OK');
  } else {
    writeFileSync(OUT, source);
    console.log(`card-info: ${path.relative(ROOT, OUT)}（${source.length} 文字）`);
  }
}
