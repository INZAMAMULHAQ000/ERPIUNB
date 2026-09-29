// Widget-engine verification: with a customized layout stored in the DB, the
// 3 chartjs widgets on the main dashboard must render through the Carbon bridge
// (SVG divs) rather than falling back to Chart.js canvas — and nothing blank.
export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(2500);

  await page.goto('http://localhost/ERP/index.php', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(3000);

  const out = await page.evaluate(() => {
    const grid = document.getElementById('dashboard-grid-container');
    const oldContent = document.getElementById('dashboard-old-content');
    const carbonDivs = document.querySelectorAll('#dashboard-grid-container [data-carbon-chart]');
    const carbonSvgs = document.querySelectorAll('#dashboard-grid-container [data-carbon-chart] svg');
    const canvases = document.querySelectorAll('#dashboard-grid-container canvas');
    // Collect widget titles so we know which widgets actually rendered.
    const widgetCards = [...document.querySelectorAll('#dashboard-grid-container .widget-card, #dashboard-grid-container [class*="widget"]')]
      .map(c => (c.textContent || '').trim().slice(0, 40));
    return {
      gridVisible: grid ? getComputedStyle(grid).display : 'no-grid',
      hasCustomLayout: grid ? grid.dataset.hasCustomLayout : null,
      oldContentHidden: oldContent ? getComputedStyle(oldContent).display : null,
      carbonContainers: carbonDivs.length,
      carbonSvgs: carbonSvgs.length,
      canvasCharts: canvases.length,
      firstWidgetTexts: widgetCards.slice(0, 8),
      chartHeights: [...carbonDivs].map(c => Math.round(c.getBoundingClientRect().height)).slice(0, 6),
    };
  });

  // Also poke the dashboard engine for the count of widgets it thinks exist.
  const engine = await page.evaluate(() => {
    const d = window.__dashboard;
    return {
      hasEngine: !!d,
      renderTypes: d && d.widgets ? d.widgets.map(w => (w && w.body && w.body.querySelector('svg')) ? 'svg' : (w && w.body && w.body.querySelector('canvas')) ? 'canvas' : 'other') : [],
    };
  });

  return { out, engine };
}
