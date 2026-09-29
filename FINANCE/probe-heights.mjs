// After catalog probe finishes, report the rendered svg height of each chart box.
export default async function run(page, ui) {
  await page.waitForFunction(
    () => document.getElementById('out') && document.getElementById('out').textContent.includes('DONE all types'),
    { timeout: 25000 }
  );
  await page.waitForTimeout(800);
  return await page.evaluate(() => {
    const ids = ['c-gauge','c-meter','c-bullet','c-radar','c-hist','c-box','c-treemap','c-tree','c-circlepack','c-pack','c-alluvial','c-cloud','c-scatter','c-bubble','c-combo','c-lollipop'];
    const out = {};
    for (const id of ids) {
      const el = document.getElementById(id);
      if (!el) { out[id] = 'missing'; continue; }
      const svg = [...el.querySelectorAll('svg')].find(s => s.getBoundingClientRect().width > 100) || el.querySelector('svg');
      out[id] = svg ? Math.round(svg.getBoundingClientRect().height) + 'px' : 'no-svg';
    }
    return out;
  });
}
