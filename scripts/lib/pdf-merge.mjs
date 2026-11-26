// Chrome（Skia/PDF）が出した PDF を、ページごとの寸法を保ったまま 1 つに結合する（依存パッケージなし）。
// 1 つの文書に幅の違うページ（単ページ 420 pt・見開き 839 pt）を入れて一度に印刷すると、Chrome が全体のレイアウトを崩すため、
// シートごとに印刷した PDF（--only と同じもの）をここで結合する。
// 想定: 各入力は PDF 1.x、xref テーブルは 1 つ、オブジェクトストリーム・xref ストリームなし、ページは 1 つ以上。
// 想定と違うときは壊れた PDF を出さず、Error を投げる。

const fail = (msg) => { throw new Error(`PDF を結合できません: ${msg}`); };
const HEADER = '%PDF-1.4\n%\xd3\xeb\xe9\xe1\n';

// 1 つの PDF を { objects: Map(番号 → {dict(latin1 文字列), stream(Buffer|null)}), pages: [ページオブジェクトの番号…], keep: Set（ページから届くオブジェクトの番号）} に読む
function parsePdf(buf, label) {
  const text = buf.toString('latin1');
  if (!text.startsWith('%PDF-1.')) fail(`${label}: PDF のヘッダーがありません`);
  if (text.includes('/ObjStm') || text.includes('/Type /XRef') || text.includes('/Type/XRef')) fail(`${label}: オブジェクトストリームまたは xref ストリームがあります（想定外）`);
  const sx = text.lastIndexOf('startxref');
  const xrefPos = Number(/^startxref\s+(\d+)/.exec(text.slice(sx))?.[1]);
  if (sx < 0 || !Number.isFinite(xrefPos) || text.slice(xrefPos, xrefPos + 4) !== 'xref') fail(`${label}: xref テーブルが読めません`);
  const tr = text.indexOf('trailer', xrefPos);
  if (tr < 0) fail(`${label}: trailer がありません`);
  const offsets = new Map();
  const lines = text.slice(xrefPos + 4, tr).split(/\r?\n/).filter((l) => l.trim() !== '');
  for (let i = 0; i < lines.length;) {
    const head = /^(\d+) (\d+)$/.exec(lines[i].trim());
    if (!head) fail(`${label}: xref の形式が想定と違います`);
    const first = Number(head[1]), count = Number(head[2]);
    for (let k = 0; k < count; k++) {
      const e = /^(\d{10}) (\d{5}) ([nf])/.exec(lines[i + 1 + k] ?? '');
      if (!e) fail(`${label}: xref の行が想定と違います`);
      if (e[3] === 'n') offsets.set(first + k, Number(e[1]));
    }
    i += 1 + count;
  }
  const trailer = text.slice(tr, sx);
  const root = Number(/\/Root (\d+) 0 R/.exec(trailer)?.[1]);
  if (!root) fail(`${label}: /Root がありません`);

  // 各オブジェクトの範囲は「自分の位置から次のオブジェクトの位置（最後は xref）まで」
  const order = [...offsets.entries()].sort((a, b) => a[1] - b[1]);
  const objects = new Map();
  order.forEach(([num, off], idx) => {
    const end = idx + 1 < order.length ? order[idx + 1][1] : xrefPos;
    const m = new RegExp(`^${num} 0 obj`).exec(text.slice(off, off + 24));
    if (!m) fail(`${label}: オブジェクト ${num} の位置が合いません`);
    const endobj = buf.lastIndexOf('endobj', end);
    if (endobj < off) fail(`${label}: オブジェクト ${num} の endobj がありません`);
    const bodyBuf = buf.subarray(off + m[0].length, endobj);
    const bodyText = bodyBuf.toString('latin1');
    const sm = />>\s*stream\r?\n/.exec(bodyText);
    if (sm) {
      const dataStart = sm.index + sm[0].length;
      const endstream = bodyBuf.lastIndexOf('endstream');
      if (endstream < dataStart) fail(`${label}: オブジェクト ${num} の endstream がありません`);
      const dict = bodyText.slice(0, sm.index + 2);
      // データの長さは /Length（直接の数）で決める。圧縮データは末尾が改行と同じ値のバイトで終わりうるので、改行の除去では決めない
      const len = /\/Length (\d+)(?!\d| 0 R)/.exec(dict);
      let dataEnd = endstream;
      if (len) {
        dataEnd = dataStart + Number(len[1]);
        if (dataEnd > endstream || bodyBuf.subarray(dataEnd, endstream).toString('latin1').trim() !== '') fail(`${label}: オブジェクト ${num} のストリームの長さが /Length と合いません`);
      } else if (bodyBuf[dataEnd - 1] === 0x0a) dataEnd -= bodyBuf[dataEnd - 2] === 0x0d ? 2 : 1;
      objects.set(num, { dict, stream: bodyBuf.subarray(dataStart, dataEnd) });
    } else objects.set(num, { dict: bodyText, stream: null });
  });

  const pages = [];
  const walk = (num, depth) => {
    if (depth > 10) fail(`${label}: ページツリーが深すぎます`);
    const o = objects.get(num);
    if (!o) fail(`${label}: オブジェクト ${num} がありません`);
    if (/\/Type\s*\/Pages\b/.test(o.dict)) {
      const kids = /\/Kids\s*\[([^\]]*)\]/.exec(o.dict)?.[1];
      if (kids === undefined) fail(`${label}: /Pages ${num} に /Kids がありません`);
      for (const r of kids.matchAll(/(\d+) 0 R/g)) walk(Number(r[1]), depth + 1);
    } else if (/\/Type\s*\/Page\b/.test(o.dict)) pages.push(num);
    else fail(`${label}: オブジェクト ${num} がページでもページツリーでもありません`);
  };
  const pagesRef = /\/Pages (\d+) 0 R/.exec(objects.get(root)?.dict ?? '')?.[1];
  if (!pagesRef) fail(`${label}: カタログに /Pages がありません`);
  walk(Number(pagesRef), 0);
  if (!pages.length) fail(`${label}: ページがありません`);
  // ページから参照をたどって届くオブジェクトだけを残す（カタログ・ページツリー・情報・構造ツリー〈タグ付き PDF の飾り〉は結合しない）
  const keep = new Set(pages);
  const queue = [...pages];
  while (queue.length) {
    const o = objects.get(queue.pop());
    for (const r of o.dict.replace(/\/Parent\s+\d+ 0 R/, '').matchAll(/(\d+) 0 R/g)) {
      const n = Number(r[1]);
      if (!objects.has(n)) fail(`${label}: オブジェクト ${n} がありません`);
      if (!keep.has(n)) { keep.add(n); queue.push(n); }
    }
  }
  return { objects, pages, keep };
}

// buffers: 結合する PDF の Buffer の配列（順に並ぶ）、options.title: 文書のタイトル。結合した Buffer を返す
export function mergePdfs(buffers, { title = '' } = {}) {
  const docs = buffers.map((b, i) => parsePdf(b, `${i + 1} 番目の PDF`));
  // 番号の割り当て: 1 = カタログ、2 = ページツリー、3 = 情報、4 以降 = 各 PDF のオブジェクト
  const CATALOG = 1, PAGES = 2, INFO = 3;
  let next = 4;
  const maps = docs.map((d) => {
    const m = new Map();
    for (const num of [...d.objects.keys()].sort((a, b) => a - b)) if (d.keep.has(num)) m.set(num, next++);
    return m;
  });
  const parts = [];
  const offsets = new Map();
  let pos = 0;
  const push = (b) => { const buf = Buffer.isBuffer(b) ? b : Buffer.from(b, 'latin1'); parts.push(buf); pos += buf.length; };
  push(HEADER);
  const startObj = (n) => { offsets.set(n, pos); push(`${n} 0 obj\n`); };
  const kids = [];
  docs.forEach((d, di) => {
    const m = maps[di];
    const renumber = (s) => s.replace(/(\d+) 0 R/g, (_, n) => {
      const to = m.get(Number(n));
      if (to === undefined) fail(`${di + 1} 番目の PDF が、結合しないオブジェクト ${n} を参照しています`);
      return `${to} 0 R`;
    });
    for (const [num, newNum] of [...m.entries()].sort((a, b) => a[1] - b[1])) {
      const o = d.objects.get(num);
      let dict = o.dict;
      if (d.pages.includes(num)) {
        if (!/\/Parent\s+\d+ 0 R/.test(dict)) fail(`${di + 1} 番目の PDF のページに /Parent がありません`);
        // /Parent は新しいページツリーに付け替える（古いページツリーは結合しない）
        dict = renumber(dict.replace(/\/Parent\s+\d+ 0 R/, '/Parent @@P@@')).replace('/Parent @@P@@', `/Parent ${PAGES} 0 R`);
        kids.push(newNum);
      } else dict = renumber(dict);
      startObj(newNum);
      push(dict);
      if (o.stream) { push('\nstream\n'); push(o.stream); push('\nendstream'); }
      push('\nendobj\n');
    }
  });
  startObj(CATALOG); push(`<</Type /Catalog\n/Pages ${PAGES} 0 R>>\nendobj\n`);
  startObj(PAGES); push(`<</Type /Pages\n/Count ${kids.length}\n/Kids [${kids.map((k) => `${k} 0 R`).join(' ')}]>>\nendobj\n`);
  const hex = Buffer.from(String.fromCharCode(0xfeff) + title, 'utf16le').swap16().toString('hex').toUpperCase();
  startObj(INFO); push(`<</Title <${hex}>\n/Producer (Chrome, merged by build-rulebook-en)>>\nendobj\n`);
  const size = next;
  const xrefStart = pos;
  push(`xref\n0 ${size}\n0000000000 65535 f \n`);
  for (let n = 1; n < size; n++) push(`${String(offsets.get(n)).padStart(10, '0')} 00000 n \n`);
  push(`trailer\n<</Size ${size}\n/Root ${CATALOG} 0 R\n/Info ${INFO} 0 R>>\nstartxref\n${xrefStart}\n%%EOF\n`);
  return Buffer.concat(parts);
}
