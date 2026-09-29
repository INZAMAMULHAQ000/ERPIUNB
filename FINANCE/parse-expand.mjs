import fs from 'fs';
const t = fs.readFileSync(new URL('./chart-expand-out.json', import.meta.url), 'utf8');
const seg = t.slice(t.indexOf('script'));
const open = seg.indexOf('{');
let depth = 0, inStr = false, end = -1;
for (let i = open; i < seg.length; i++) {
  const ch = seg[i];
  if (inStr) {
    if (ch === '\\') i++;
    else if (ch === '"') inStr = false;
    continue;
  }
  if (ch === '"') { inStr = true; continue; }
  if (ch === '{') depth++;
  else if (ch === '}') { depth--; if (depth === 0) { end = i; break; } }
}
const j = JSON.parse(seg.slice(open, end + 1));
for (const [k, v] of Object.entries(j.results)) {
  console.log('=== ' + k + ' ===');
  const first = v[0];
  console.log('visibleCount', first.visibleCount, '| pageScrollH', first.pageScrollH, '| innerH', first.innerH);
  v.forEach((s, idx) => {
    console.log('  t' + idx, 'rectH:', s.cards.map(c => c.rectH).join(','), '| svgH:', s.cards.map(c => c.svgH).join(','), '| csH:', s.cards.map(c => c.csH).join(','));
  });
  const c0 = first.cards[0];
  if (c0) console.log('  sample0[0]:', JSON.stringify({ cls: c0.cls, parentCls: c0.parentCls, parentH: c0.parentH, viewBox: c0.viewBox, overflowY: c0.overflowY, minH: c0.minH }));
}
