// Carbon Charts foundation smoke test: login, verify globals, render a live chart.
export default async function run(page, ui) {
  const out = {};

  // 1. Login
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1]
    || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  const submit   = login.match(/@(e\d+) button "Sign in"/)?.[1]
    || login.match(/@(e\d+) button "Submit"/)?.[1];
  if (!emailRef || !passRef || !submit) return { error: 'login form not found', login };
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(submit);
  await page.waitForTimeout(1500);
  out.urlAfterLogin = page.url();

  // 2. Globals on a real page
  out.globals = await page.evaluate(() => ({
    chartsLib: typeof window.Charts,
    bridge: typeof window.renderCarbonChart,
    hasChartJS: typeof window.Chart,
  }));

  // 3. Render a real Carbon chart into a test container
  const renderResult = await page.evaluate(() => {
    const host = document.createElement('div');
    host.id = 'smoke-host';
    host.style.width = '400px';
    document.body.appendChild(host);
    const inst = window.renderCarbonChart(host, 'doughnut', {
      labels: ['Open', 'Closed', 'Pending'],
      datasets: [{ data: [5, 3, 2], backgroundColor: ['#f59e0b', '#22c55e', '#6366f1'] }]
    }, { legend: { position: 'bottom' } });
    return {
      returned: !!inst,
      destroyFn: !!(inst && inst.destroy),
      svgCount: host.querySelectorAll('svg').length,
      themeAttr: host.getAttribute('data-carbon-chart'),
      minHeight: getComputedStyle(host).minHeight,
      count: window.renderCarbonChart.count()
    };
  });
  out.render = renderResult;

  // 4. reTheme()
  out.reTheme = await page.evaluate(() => {
    const before = window.renderCarbonChart.count();
    document.body.setAttribute('data-theme', 'ibm-dark');
    window.renderCarbonChart.reTheme();
    const after = window.renderCarbonChart.count();
    const host = document.getElementById('smoke-host');
    return { before, after, stillHasSvg: host ? host.querySelectorAll('svg').length : 0 };
  });

  await page.screenshot({ path: 'carbon-smoke.png', fullPage: false });
  return out;
}
