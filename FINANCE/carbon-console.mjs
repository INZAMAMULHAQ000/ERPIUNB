// Attach raw Playwright console + pageerror listeners to catch EVERYTHING
// during the assets page load, plus network response statuses for the CDN scripts.
export default async function run(page, ui) {
  const logs = [];
  const pageErrs = [];
  page.on('console', msg => logs.push('[' + msg.type() + '] ' + msg.text()));
  page.on('pageerror', err => pageErrs.push('PAGEERROR: ' + String(err)));
  page.on('requestfailed', req => logs.push('[requestfailed] ' + req.url() + ' :: ' + String(req.failure())));

  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) (?:textbox|input)/)?.[1]
    || login.match(/@(e\d+) .*"Email/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  const submit   = login.match(/@(e\d+) button "Sign in"/)?.[1]
    || login.match(/@(e\d+) button "Submit"/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(submit);
  await page.waitForTimeout(3000);

  const resp = await page.goto('http://localhost/ERP/modules/assets_dashboard/index.php', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(3000);

  const out = await page.evaluate(() => {
    const names = ['@carbon/charts', 'chart.umd.min.js', 'tailwindcss.com', 'dashboard-core.js', 'fluent-dropdown.js', 'carbon-charts.js'];
    const res = {};
    performance.getEntriesByType('resource').forEach(e => {
      names.forEach(n => { if (e.name.includes(n)) res[n] = { dur: Math.round(e.duration), size: e.transferSize || 0 }; });
    });
    return {
      Charts: typeof window.Charts,
      Chart: typeof window.Chart,
      renderCarbonChart: typeof window.renderCarbonChart,
      resources: res,
    };
  });

  return { status: resp ? resp.status() : null, out, pageErrs, logs: logs.slice(-40) };
}
