import fs from 'fs';
function extract(jsonPath) {
  const t = fs.readFileSync(jsonPath, 'utf8');
  const s = t.slice(t.indexOf('script'));
  const open = s.indexOf('{');
  let d = 0, ins = false, end = -1;
  for (let i = open; i < s.length; i++) {
    const c = s[i];
    if (ins) { if (c === '\\') i++; else if (c === '"') ins = false; continue; }
    if (c === '"') { ins = true; continue; }
    if (c === '{') d++;
    else if (c === '}') { d--; if (d === 0) { end = i; break; } }
  }
  return JSON.parse(s.slice(open, end + 1));
}
const j = extract(new URL('./chart-expand2.json', import.meta.url));
for (const [label, snap] of [['t0', j.t0], ['t1', j.t1]]) {
  console.log('=== ' + label + ' holder rectH ' + snap.holder.rectH + ' ===');
  (snap.holder.children || []).forEach(k => console.log(
    '  '.repeat(Math.min(k.depth, 4)) + '<' + k.tag + '>' +
    (k.cls ? ' .' + k.cls.slice(0, 60) : '') +
    (k.id ? ' #' + k.id : '') +
    ' rectH=' + k.rectH + ' rectW=' + k.rectW +
    ' [style h=' + (k.inlineH || '') + ' w=' + (k.inlineW || '') + ']' +
    (k.hasSvg ? ' svgAttrH=' + k.svgAttrH + ' vb=' + k.svgViewBox : '')
  ));
}
