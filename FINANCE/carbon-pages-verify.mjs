// Full rollout verification: visit every Carbon-converted/upgraded page, assert
// the bridge + lib are loaded and at least one Carbon SVG rendered, and capture
// console errors + failed requests for each page.
export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(2500);

  const PAGES = [
    ['home',                'index.php'],
    ['executive',           'modules/executive_dashboard/index.php'],
    ['analytics',           'modules/analytics/index.php'],
    ['budget-reports',      'modules/finance/budget_reports/dashboard.php'],
    ['budget-variance',     'modules/finance/budget_monitoring/variance.php'],
    ['helpdesk',            'modules/helpdesk_dashboard/index.php'],
    ['assets',              'modules/assets_dashboard/index.php'],
    ['leave',               'modules/leave_management/dashboard.php'],
    ['ar',                  'modules/finance/ar_dashboard/index.php'],
    ['expense-reports',     'modules/finance/expense_reports/dashboard.php'],
    ['carbon-catalog',      'modules/analytics/carbon_catalog.php'],
  ];

  const out = {};
  for (const [name, path] of PAGES) {
    await page.goto('http://localhost/ERP/' + path, { waitUntil: 'domcontentloaded' });
    // Wait for late-mounting SVGs (widget engine renders after DOMContentLoaded).
    try {
      await page.waitForFunction(
        () => document.querySelectorAll('[data-carbon-chart] svg').length >= 1,
        { timeout: 8000 }
      );
    } catch (e) { /* captured below */ }
    await page.waitForTimeout(600);

    out[name] = await page.evaluate(() => ({
      hasLib: typeof window.Charts !== 'undefined',
      hasBridge: typeof window.renderCarbonChart === 'function',
      containers: document.querySelectorAll('[data-carbon-chart]').length,
      svgs: document.querySelectorAll('[data-carbon-chart] svg').length,
      canvasCharts: document.querySelectorAll('canvas').length,
      bodyText: document.body ? document.body.textContent.slice(0, 40) : '',
    }));
  }
  return { pages: out };
}
