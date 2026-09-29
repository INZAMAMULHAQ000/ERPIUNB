export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(2500);

  const consoleLogs = [];
  page.on('console', m => consoleLogs.push(m.type() + ': ' + m.text().slice(0, 200)));
  page.on('pageerror', e => consoleLogs.push('PAGEERROR: ' + String(e.message || e).slice(0, 300)));

  await page.goto('http://localhost/ERP/index.php', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(4000);

  const out = await page.evaluate(() => {
    const grid = document.getElementById('dashboard-grid-container');
    return {
      gridHtml0: grid ? grid.innerHTML.slice(0, 300) : 'NO GRID',
      gridLen: grid ? grid.innerHTML.length : -1,
      gridChildren: grid ? [...grid.children].map(c => c.className || c.tagName).slice(0, 10) : [],
      dashboardCoreLoaded: typeof Dashboard !== 'undefined',
      dashboardInWindow: typeof window.Dashboard !== 'undefined',
      carbonBridge: typeof window.renderCarbonChart === 'function',
      chartsLib: typeof window.Charts !== 'undefined',
      chartJsLib: typeof window.Chart !== 'undefined',
    };
  });

  return { out, consoleLogs };
}
