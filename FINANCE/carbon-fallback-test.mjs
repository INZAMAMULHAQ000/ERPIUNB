// Chart.js fallback test: Carbon CDN is commented out in header.php. The pages
// must still render charts (via Chart.js fallback / native paths), not blanks.
export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(2500);

  const PAGES = [
    ['home',           'index.php'],
    ['executive',      'modules/executive_dashboard/index.php'],
    ['analytics',      'modules/analytics/index.php'],
    ['budget-reports', 'modules/finance/budget_reports/dashboard.php'],
    ['budget-variance','modules/finance/budget_monitoring/variance.php'],
  ];

  const out = {};
  for (const [name, path] of PAGES) {
    await page.goto('http://localhost/ERP/' + path, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1800);
    out[name] = await page.evaluate(() => {
      // Carbon lib present?
      const carbonLib = typeof window.Charts !== 'undefined';
      const carbonSvgs = document.querySelectorAll('[data-carbon-chart] svg').length;
      // Chart.js present? canvas charts?
      const chartJs = typeof window.Chart !== 'undefined';
      const canvases = document.querySelectorAll('canvas').length;
      const canvasRendered = [...document.querySelectorAll('canvas')].filter(c => c.width > 0 && c.height > 0).length;
      return { carbonLib, carbonSvgs, chartJs, canvases, canvasRendered };
    });
  }
  return { out };
}
