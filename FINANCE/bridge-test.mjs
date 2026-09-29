// Bridge correctness test: load Carbon bundle + bridge in-page, exercise renderCarbonChart.
import { createRequire } from 'node:module'
import { readdirSync, existsSync } from 'node:fs'
import { homedir } from 'node:os'
import { join } from 'node:path'

function resolveChromium() {
  const roots = []
  for (const base of [join(homedir(), '.vscode-server/extensions'), join(homedir(), '.vscode/extensions')]) {
    if (!existsSync(base)) continue
    const dirs = readdirSync(base).filter(d => d.startsWith('danielsanmedium.dscodegpt-'))
      .sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }))
    const newest = dirs[dirs.length - 1]
    if (newest) roots.push(join(base, newest, 'standalone') + '/')
  }
  roots.push('/home/mike/src/codegpt/codegpt-nextjs/', process.cwd() + '/')
  for (const root of roots) {
    try {
      const mod = createRequire(root)('patchright')
      const chromium = mod?.chromium ?? mod?.default?.chromium
      if (chromium) return { chromium, root }
    } catch {}
  }
  throw new Error('no patchright')
}
const { chromium } = resolveChromium()

const browser = await chromium.launch({
  headless: true,
  executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
  args: ['--no-sandbox'],
})
const page = await (await browser.newContext()).newPage()
const errors = []
page.on('console', m => { if (m.type() === 'error') errors.push(m.text().slice(0, 120)) })
page.on('pageerror', e => errors.push(String(e).split('\n')[0].slice(0, 160)))
await page.goto('http://localhost/ERP/FINANCE/cdn-test.html', { waitUntil: 'domcontentloaded' })

const result = await page.evaluate(`(async () => {
  const B = 'https://cdn.jsdelivr.net/npm/@carbon/charts@1.27.18/dist/umd/bundle.umd.js';
  const C = 'https://cdn.jsdelivr.net/npm/@carbon/charts@1.27.18/dist/styles.min.css';
  const BR = 'http://localhost/ERP/assets/js/carbon-charts.js';
  const load = async (u) => (0, eval)(await (await fetch(u)).text());
  const css = await (await fetch(C)).text();
  const st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
  await load(B);
  await load(BR);
  const out = {};
  out.bridge = typeof window.renderCarbonChart;
  out.count = window.renderCarbonChart.count();

  // 1. Chart.js-style doughnut with backgroundColor array
  const host1 = document.createElement('div'); host1.id = 't1';
  document.body.appendChild(host1);
  const i1 = window.renderCarbonChart(host1, 'doughnut', {
    labels: ['Open', 'Closed', 'Pending'],
    datasets: [{ data: [5, 3, 2], backgroundColor: ['#f59e0b', '#22c55e', '#6366f1'], borderWidth: 0 }]
  }, { legend: { position: 'bottom' } });
  out.doughnut = { ok: !!i1, svg: host1.querySelectorAll('svg').length, theme: host1.getAttribute('data-carbon-chart') };

  // 2. Grouped bar (2 datasets) → GroupedBarChart
  const host2 = document.createElement('div'); host2.id = 't2'; host2.style.height = '260px';
  document.body.appendChild(host2);
  const i2 = window.renderCarbonChart(host2, 'bar', {
    labels: ['Jan', 'Feb'],
    datasets: [{ label: 'Budget', data: [100, 200], backgroundColor: 'rgba(59,130,246,0.7)' },
               { label: 'Spent', data: [80, 150], backgroundColor: 'rgba(239,68,68,0.7)' }]
  }, {});
  out.bar = { ok: !!i2, svg: host2.querySelectorAll('svg').length };

  // 3. Horizontal bar
  const host3 = document.createElement('div'); host3.id = 't3'; host3.style.height = '260px';
  document.body.appendChild(host3);
  const i3 = window.renderCarbonChart(host3, 'horizontal', {
    labels: ['IT', 'HR'], datasets: [{ label: 'Headcount', data: [12, 5] }]
  }, {});
  out.horizontal = { ok: !!i3, svg: host3.querySelectorAll('svg').length };

  // 3b. Line chart (cartesian → needs the injected axes) + label colors survive
  const host3b = document.createElement('div'); host3b.id = 't3b'; host3b.style.height = '260px';
  document.body.appendChild(host3b);
  const i3b = window.renderCarbonChart(host3b, 'line', {
    labels: ['Jan', 'Feb', 'Mar'],
    datasets: [{ label: 'Revenue', data: [100, 200, 150], borderColor: '#0f62fe', backgroundColor: 'rgba(15,98,254,0.1)' }]
  }, {});
  const svgText3b = host3b.querySelector('svg') ? host3b.innerHTML : '';
  out.line = {
    ok: !!i3b,
    svg: host3b.querySelectorAll('svg').length,
    hasIndigo: svgText3b.indexOf('#0f62fe') !== -1 || svgText3b.indexOf('rgb(15, 98, 254)') !== -1
  };

  // 4. Empty data → no-op
  const host4 = document.createElement('div'); host4.id = 't4';
  document.body.appendChild(host4);
  const i4 = window.renderCarbonChart(host4, 'line', { labels: [], datasets: [] }, {});
  out.empty = { ok: i4 === null, noSvg: host4.querySelectorAll('svg').length === 0 };

  // 5. reTheme (dark) → all re-rendered, still have SVGs
  const before = window.renderCarbonChart.count();
  document.body.setAttribute('data-theme', 'ibm-dark');
  window.renderCarbonChart.reTheme();
  out.reTheme = { before, after: window.renderCarbonChart.count(), host1Svg: host1.querySelectorAll('svg').length, host3bSvg: host3b.querySelectorAll('svg').length };

  return out;
})()`)
console.log(JSON.stringify({ result, errors }, null, 1))
await browser.close()
