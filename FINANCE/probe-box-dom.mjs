// Inspect final boxplot DOM: count valid paths, NaN paths, box/whisker/median elements.
export default async function run(page, ui) {
  await page.waitForFunction(
    () => document.getElementById('out') && document.getElementById('out').textContent.includes('DONE boxplot'),
    { timeout: 20000 }
  );
  await page.waitForTimeout(800);
  return await page.evaluate(() => {
    const el = document.getElementById('S');
    if (!el) return { error: 'no #S' };
    const svg = [...el.querySelectorAll('svg')].find(s => s.getAttribute('class') && s.getAttribute('class').includes('layout-svg-wrapper')) || el.querySelector('svg');
    const paths = [...svg.querySelectorAll('path')].filter(p => p.getAttribute('d'));
    const nanPaths = paths.filter(p => /NaN|undefined/.test(p.getAttribute('d'))).length;
    const box = svg.querySelector('path.box');
    const median = svg.querySelector('path.median');
    const whisker = svg.querySelector('path.whisker.start');
    const outliers = svg.querySelectorAll('circle.outlier').length;
    const labels = [...el.querySelectorAll('.axis text, .x-label text, text')].map(t => t.textContent).filter(Boolean).slice(0, 8);
    return {
      svgCount: el.querySelectorAll('svg').length,
      chartPaths: paths.length,
      nanPaths,
      boxD: box ? box.getAttribute('d') : null,
      medianD: median ? median.getAttribute('d') : null,
      whiskerD: whisker ? whisker.getAttribute('d') : null,
      outliers,
      svgH: svg.getBoundingClientRect().height,
      axisLabels: labels,
    };
  });
}
