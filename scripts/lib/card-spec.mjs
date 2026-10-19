// 002（カードデータ仕様）の表を読み、カードの定義データとして返す。
// scripts/check-card-definitions.mjs が使う。表の見出し・列の並びが変わったら、ここを直す。
import { readFileSync } from 'node:fs';

const cells = (line) => line.split('|').slice(1, -1).map((s) => s.trim());
const code = (s) => {
  const m = s.match(/`([^`]+)`/);
  if (!m) throw new Error(`バッククォートで囲まれた値がありません: ${s}`);
  return m[1];
};
const text = (s) => s.replaceAll('<br>', '\n');

/** 見出し（行頭の一致）から次の同レベル以上の見出しまでの行を返す。 */
function section(lines, headingPrefix, untilAnyHeading = false) {
  const start = lines.findIndex((l) => l.startsWith(headingPrefix));
  if (start < 0) throw new Error(`見出しが見つかりません: ${headingPrefix}`);
  const level = headingPrefix.match(/^#+/)[0].length;
  let end = lines.length;
  for (let i = start + 1; i < lines.length; i++) {
    const m = lines[i].match(/^(#+) /);
    if (m && (untilAnyHeading || m[1].length <= level)) { end = i; break; }
  }
  return lines.slice(start + 1, end);
}

/** セクション内の表の行のうち、第 1 列が first に一致するものを列の配列で返す。 */
function rows(sectionLines, first, width) {
  const out = [];
  for (const line of sectionLines) {
    if (!line.startsWith('|')) continue;
    const c = cells(line);
    if (!first.test(c[0])) continue;
    if (c.length !== width) throw new Error(`列数が ${width} ではありません（${c.length}）: ${line.slice(0, 80)}`);
    out.push(c);
  }
  return out;
}

export function readCardSpec(file) {
  const lines = readFileSync(file, 'utf8').split('\n');

  // --- プロジェクトカード（5.1〜5.3） ---
  const projects = [];
  const projectTexts = new Map();
  for (const h of ['#### 5.1 ', '#### 5.2 ', '#### 5.3 ']) {
    const sec = section(lines, h);
    for (const c of rows(sec, /^P\d{3}$/, 10)) {
      projects.push({
        id: c[0], nameJa: c[1], nameEn: c[2], nameId: code(c[3]),
        type: null, cost: Number(c[4]), fame: Number(c[5]), fuelSlots: Number(c[6]),
        timing: code(c[7]), effectKind: code(c[8]), activeCondition: code(c[9]),
      });
    }
    for (const c of rows(sec, /^P\d{3} /, 3)) {
      projectTexts.set(c[0].slice(0, 4), { ja: c[1], en: c[2] });
    }
  }
  for (const p of projects) {
    const n = Number(p.id.slice(1));
    p.type = n <= 33 ? 'CREW' : n <= 66 ? 'DEVICE' : 'SOFTWARE';
    const t = projectTexts.get(p.id);
    if (!t) throw new Error(`${p.id}: 効果文がありません`);
    p.textJa = t.ja;
    p.textEn = t.en;
  }

  // --- 衛星カード（6.1 MOON 面、6.2 色別プランの面） ---
  const satellites = [];
  const colorOfSuffix = { B: 'BLUE', G: 'GREEN', R: 'RED', Y: 'YELLOW' };
  for (const c of rows(section(lines, '#### 6.1 '), /^R00\d[A-Z]( \/ [A-Z])+$/, 11)) {
    const base = c[0].slice(0, 4);
    const suffixes = [c[0].slice(4, 5), ...c[0].split(' / ').slice(1)];
    for (const s of suffixes) {
      satellites.push({
        id: `${base}${s}`, plan: 'MOON', color: colorOfSuffix[s], shape: c[1], nameId: code(c[2]),
        launchCost: Number(c[3]), fame: Number(c[4]), fuelSlots: Number(c[5]),
        timing: code(c[6]), effectKind: code(c[7]), activeCondition: code(c[8]),
        textJa: c[9], textEn: c[10],
      });
    }
  }
  for (const c of rows(section(lines, '#### 6.2 '), /^R0\d\d$/, 11)) {
    satellites.push({
      id: c[0], plan: c[1], color: c[2], shape: c[3], nameId: code(c[4]),
      launchCost: Number(c[5]), fame: Number(c[6]), fuelSlots: 2,
      timing: code(c[7]), effectKind: code(c[8]), activeCondition: 'ACTIVE_ONLY',
      textJa: c[9], textEn: c[10],
    });
  }

  // --- 褒賞カード（7.1） ---
  const rewards = rows(section(lines, '#### 7.1 '), /^T\d{3}$/, 12).map((c) => ({
    id: c[0], nameJa: c[1], nameEn: c[2], nameId: code(c[3]), compareJa: c[4], compareEn: c[5],
    class: code(c[6]), mark: code(c[7]), points: [Number(c[8]), Number(c[9]), Number(c[10]), Number(c[11])],
  }));

  // --- ミッションカード（8.2） ---
  const cellList = (s) => [...s.matchAll(/\((-?\d+), (-?\d+)\)/g)].map((m) => [Number(m[1]), Number(m[2])]);
  const missions = rows(section(lines, '#### 8.2 '), /^M\d{3}$/, 7).map((c) => ({
    id: c[0], name: c[1], nameId: code(c[2]), fameEasy: Number(c[3]), fameFull: Number(c[4]),
    easyCells: cellList(c[5]), extraCells: cellList(c[6]),
  }));

  // --- アクションカード（9 の表、9.1、9.2） ---
  const sec9 = section(lines, '### 9. ', true);
  const main = new Map(rows(section(lines, '#### 9.1 '), /^A\d{3}$/, 3).map((c) => [c[0], { ja: text(c[1]), en: text(c[2]) }]));
  const bonus = new Map(rows(section(lines, '#### 9.2 '), /^A\d{3}$/, 3).map((c) => [c[0], { ja: text(c[1]), en: text(c[2]) }]));
  const actions = rows(sec9, /^A\d{3}$/, 6).map((c) => ({
    id: c[0], numeral: c[1], nameJa: c[2], nameEn: c[3], nameId: code(c[4]), kind: code(c[5]),
    mainJa: main.get(c[0]).ja, mainEn: main.get(c[0]).en, bonusJa: bonus.get(c[0]).ja, bonusEn: bonus.get(c[0]).en,
  }));

  return { projects, satellites, rewards, missions, actions };
}
