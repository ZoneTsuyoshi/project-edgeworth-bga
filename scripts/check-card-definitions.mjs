// 002（カードデータ仕様）の表と、PHP のカード定義（src/modules/php/Material/）の全項目を突き合わせる。
// PHP は実行せず、定義ファイルの配列リテラルを読む。実行: npm run lint:cards（差異があれば終了コード 1）
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { readCardSpec } from './lib/card-spec.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const materialDir = path.join(root, 'src/modules/php/Material');
const spec = readCardSpec(path.join(root, 'docs/specs/002-card-data.md'));

const errors = [];
const fail = (m) => errors.push(m);
const readPhp = (name) => readFileSync(path.join(materialDir, `${name}.php`), 'utf8');

// --- PHP の配列リテラル（文字列・整数・配列・`.` による連結だけ）の読み取り ---
function parseLiteral(src) {
  const tokens = [];
  const re = /\s*(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)"|(-?\d+)|(=>|[[\],.]))/gy;
  let pos = 0;
  let m;
  while (pos < src.length && (m = (re.lastIndex = pos, re.exec(src)))) {
    pos = re.lastIndex;
    if (m[1] !== undefined) tokens.push({ t: 's', v: m[1].replace(/\\(['\\])/g, '$1') });
    else if (m[2] !== undefined) tokens.push({ t: 's', v: m[2].replace(/\\n/g, '\n') });
    else if (m[3] !== undefined) tokens.push({ t: 'n', v: Number(m[3]) });
    else tokens.push({ t: m[4] });
  }
  if (src.slice(pos).trim() !== '') throw new Error(`読み取れない記述: ${src.slice(pos, pos + 40)}`);
  let i = 0;
  const expr = () => {
    let v = atom();
    while (tokens[i]?.t === '.') { i++; v += atom(); }
    return v;
  };
  const atom = () => {
    const tk = tokens[i++];
    if (tk.t === 's' || tk.t === 'n') return tk.v;
    if (tk.t !== '[') throw new Error(`予期しないトークン: ${tk.t}`);
    const items = [];
    const keyed = [];
    while (tokens[i].t !== ']') {
      let key = null;
      let v = expr();
      if (tokens[i].t === '=>') { i++; key = v; v = expr(); }
      keyed.push([key, v]);
      items.push(v);
      if (tokens[i].t === ',') i++;
    }
    i++;
    return keyed.some(([k]) => k !== null) ? new Map(keyed) : items;
  };
  return expr();
}

/** `const NAME = [ ... ];` の配列リテラルを読む。 */
function constArray(file, name) {
  const src = readPhp(file);
  const start = src.indexOf(`const ${name} = [`);
  if (start < 0) { fail(`${file}.php: const ${name} が見つかりません`); return new Map(); }
  const bodyStart = src.indexOf('[', start);
  const end = src.indexOf('\n    ];', bodyStart);
  return parseLiteral(src.slice(bodyStart, end + '\n    ]'.length));
}

/** enum の case を { 値 } の集合で返す。 */
function enumValues(name) {
  return new Set([...readPhp(name).matchAll(/^\s*case \w+ = '([A-Z0-9_]+)';/gm)].map((m) => m[1]));
}

const enums = {};
for (const n of ['ProjectType', 'EffectTiming', 'EffectKind', 'ActiveCondition', 'Region', 'SatelliteColor', 'SatelliteShape',
  'SatellitePlan', 'RewardClass', 'RewardMark', 'ActionKind']) enums[n] = enumValues(n);

// --- 列挙の検査: 002 で使われる値と PHP の case が一致する ---
const used = (list, key) => new Set(list.map((x) => x[key]));
const expectEnum = (name, values) => {
  const actual = enums[name];
  for (const v of values) if (!actual.has(v)) fail(`${name}: 002 の値 ${v} が PHP にありません`);
  for (const v of actual) if (!values.has(v)) fail(`${name}: PHP の値 ${v} は 002 にありません`);
};
expectEnum('ProjectType', new Set(['CREW', 'DEVICE', 'SOFTWARE']));
expectEnum('EffectTiming', new Set([...used(spec.projects, 'timing'), ...used(spec.satellites, 'timing'),
  'PLANNING_PHASE', 'BUDGET_PHASE', 'DEPLOYED', 'LAUNCHED', 'TURNED', 'LINKED', 'PROPOSAL_PHASE', 'ORBIT_PHASE']));
expectEnum('EffectKind', new Set([...used(spec.projects, 'effectKind'), ...used(spec.satellites, 'effectKind')]));
expectEnum('ActiveCondition', new Set([...used(spec.projects, 'activeCondition'), ...used(spec.satellites, 'activeCondition')]));
expectEnum('Region', new Set('ABCDEFGH'.split('')));
expectEnum('SatelliteColor', used(spec.satellites, 'color'));
expectEnum('SatelliteShape', used(spec.satellites, 'shape'));
expectEnum('SatellitePlan', used(spec.satellites, 'plan'));
expectEnum('RewardClass', used(spec.rewards, 'class'));
expectEnum('RewardMark', used(spec.rewards, 'mark'));
expectEnum('ActionKind', used(spec.actions, 'kind'));

// --- 突き合わせ ---
const compare = (label, id, field, expected, actual) => {
  if (JSON.stringify(expected) !== JSON.stringify(actual)) {
    fail(`${label} ${id}: ${field} が不一致（002=${JSON.stringify(expected)}, PHP=${JSON.stringify(actual)}）`);
  }
};
const compareIds = (label, specIds, actualIds) => {
  compare(label, '全体', 'ID の並び', specIds, actualIds);
  if (new Set(actualIds).size !== actualIds.length) fail(`${label}: ID が重複しています`);
};
const checkEnum = (label, id, field, name, value) => {
  if (!enums[name].has(value)) fail(`${label} ${id}: ${field} の値 ${value} が ${name} にありません`);
};

// プロジェクトカード
{
  const rows = constArray('ProjectCards', 'ROWS');
  const texts = constArray('CardTexts', 'PROJECT');
  compareIds('プロジェクト', spec.projects.map((p) => p.id), [...rows.keys()]);
  compareIds('プロジェクト文面', spec.projects.map((p) => p.id), [...texts.keys()]);
  for (const p of spec.projects) {
    const r = rows.get(p.id);
    if (!r) continue;
    compare('プロジェクト', p.id, '行', [p.nameJa, p.nameEn, p.nameId, p.type, p.cost, p.fame, p.fuelSlots, p.timing, p.effectKind, p.activeCondition], r);
    const t = texts.get(p.id);
    compare('プロジェクト', p.id, '効果文', [p.textJa, p.textEn], [t?.get('ja'), t?.get('en')]);
    checkEnum('プロジェクト', p.id, 'type', 'ProjectType', r[3]);
    checkEnum('プロジェクト', p.id, 'timing', 'EffectTiming', r[7]);
    checkEnum('プロジェクト', p.id, 'effectKind', 'EffectKind', r[8]);
    checkEnum('プロジェクト', p.id, 'activeCondition', 'ActiveCondition', r[9]);
  }
  const names = spec.projects.map((p) => p.nameId);
  if (new Set(names).size !== 99) fail('プロジェクト: name_id が 99 種ではありません');
}

// 衛星カード
{
  const rows = constArray('SatelliteCards', 'ROWS');
  const texts = constArray('CardTexts', 'SATELLITE');
  compareIds('衛星', spec.satellites.map((c) => c.id), [...rows.keys()]);
  const nameIds = [...new Set(spec.satellites.map((c) => c.nameId))];
  compareIds('衛星文面', nameIds, [...texts.keys()]);
  for (const c of spec.satellites) {
    const r = rows.get(c.id);
    if (!r) continue;
    compare('衛星', c.id, '行', [c.plan, c.color, c.shape, c.nameId, c.launchCost, c.fame, c.fuelSlots, c.timing, c.effectKind, c.activeCondition], r);
    const t = texts.get(c.nameId);
    compare('衛星', c.id, '効果文', [c.textJa, c.textEn], [t?.get('ja'), t?.get('en')]);
    checkEnum('衛星', c.id, 'plan', 'SatellitePlan', r[0]);
    checkEnum('衛星', c.id, 'color', 'SatelliteColor', r[1]);
    checkEnum('衛星', c.id, 'shape', 'SatelliteShape', r[2]);
    checkEnum('衛星', c.id, 'timing', 'EffectTiming', r[7]);
    checkEnum('衛星', c.id, 'effectKind', 'EffectKind', r[8]);
    checkEnum('衛星', c.id, 'activeCondition', 'ActiveCondition', r[9]);
  }
}

// 褒賞カード
{
  const rows = constArray('RewardCards', 'ROWS');
  compareIds('褒賞', spec.rewards.map((c) => c.id), [...rows.keys()]);
  for (const c of spec.rewards) {
    const r = rows.get(c.id);
    if (!r) continue;
    compare('褒賞', c.id, '行', [c.nameJa, c.nameEn, c.nameId, c.compareJa, c.compareEn, c.class, c.mark, c.points], r);
    checkEnum('褒賞', c.id, 'class', 'RewardClass', r[5]);
    checkEnum('褒賞', c.id, 'mark', 'RewardMark', r[6]);
  }
}

// ミッションカード
{
  const rows = constArray('MissionCards', 'ROWS');
  compareIds('ミッション', spec.missions.map((c) => c.id), [...rows.keys()]);
  for (const c of spec.missions) {
    const r = rows.get(c.id);
    if (!r) continue;
    compare('ミッション', c.id, '行', [c.name, c.nameId, c.fameEasy, c.fameFull, c.easyCells, c.extraCells], r);
  }
}

// アクションカード
{
  const rows = constArray('ActionCards', 'ROWS');
  const texts = constArray('CardTexts', 'ACTION');
  compareIds('アクション', spec.actions.map((c) => c.id), [...rows.keys()]);
  compareIds('アクション文面', spec.actions.map((c) => c.id), [...texts.keys()]);
  for (const c of spec.actions) {
    const r = rows.get(c.id);
    if (!r) continue;
    compare('アクション', c.id, '行', [c.numeral, c.nameJa, c.nameEn, c.nameId, c.kind], r);
    const t = texts.get(c.id);
    compare('アクション', c.id, '文面', [c.mainJa, c.mainEn, c.bonusJa, c.bonusEn],
      [t?.get('mainJa'), t?.get('mainEn'), t?.get('bonusJa'), t?.get('bonusEn')]);
    checkEnum('アクション', c.id, 'kind', 'ActionKind', r[4]);
  }
}

// --- 件数 ---
const counts = [['プロジェクト', spec.projects, 99], ['衛星', spec.satellites, 40], ['褒賞', spec.rewards, 24],
  ['ミッション', spec.missions, 10], ['アクション', spec.actions, 5]];
for (const [label, list, n] of counts) if (list.length !== n) fail(`002 の${label}が ${n} 件ではありません: ${list.length}`);

if (errors.length > 0) {
  console.error(`差異 ${errors.length} 件:`);
  for (const e of errors) console.error(`  - ${e}`);
  process.exit(1);
}
console.log(`差異 0: プロジェクト ${spec.projects.length}、衛星 ${spec.satellites.length}、褒賞 ${spec.rewards.length}、`
  + `ミッション ${spec.missions.length}、アクション ${spec.actions.length} を 002 と突き合わせました`);
