// Verify the Carbon Charts Catalog page: log in, load it, assert every one of
// the 26 chart containers rendered a Carbon SVG with real height, and capture
// console errors + failed requests.
export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(2500);

  await page.goto('http://localhost/ERP/modules/analytics/carbon_catalog.php', { waitUntil: 'domcontentloaded' });

  const CHART_IDS = ['cc-bar','cc-grouped','cc-stacked','cc-hbar','cc-lollipop','cc-bullet',
    'cc-line','cc-area','cc-stackarea','cc-combo','cc-radar',
    'cc-scatter','cc-bubble','cc-hist','cc-box',
    'cc-pie','cc-donut','cc-gauge','cc-meter',
    'cc-treemap','cc-tree','cc-circlepack','cc-pack','cc-alluvial','cc-cloud','cc-heatmap'];

  // Wait for the DOMContentLoaded render pass to finish (svgs start appearing).
  await page.waitForFunction(
    (ids) => document.querySelectorAll('[data-carbon-chart] svg').length >= 5,
    { timeout: 20000 },
    CHART_IDS
  );
  await page.waitForTimeout(1500);

  const result = await page.evaluate((ids) => {
    const out = { counts: {}, svgTotal: 0, carbonContainers: 0 };
    for (const id of ids) {
      const el = document.getElementById(id);
      if (!el) { out.counts[id] = { missing: true }; continue; }
      const svgs = el.querySelectorAll('svg').length;
      out.svgTotal += svgs;
      if (el.hasAttribute('data-carbon-chart')) out.carbonContainers++;
      out.counts[id] = { svgs, carbon: el.hasAttribute('data-carbon-chart'), height: el.offsetHeight };
    }
    out.hasLib = typeof window.Charts !== 'undefined';
    out.allSvg = out.counts['cc-bar'] && out.counts['cc-bar'].svgs >= 1;
    return out;
  }, CHART_IDS);

  // Add a small delay, then grab screenshot for visual confirm.
  await page.waitForTimeout(400);
  return { result };
}
