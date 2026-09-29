export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(2500);

  await page.goto('http://localhost/ERP/index.php', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1500);

  // Try to evaluate in the page context directly — Playwright page.evaluate runs
  // in the MAIN world, so if Dashboard is defined we'll see it.
  const mainWorld = await page.evaluate(() => ({
    dashboardDefined: typeof window.Dashboard !== 'undefined',
    dashboardOnWindow: 'Dashboard' in window,
    carbonBridge: typeof window.renderCarbonChart === 'function',
    charts: typeof window.Charts !== 'undefined',
    gridHasKids: (document.getElementById('dashboard-grid-container') || {}).children?.length ?? -1,
    gridDisplay: document.getElementById('dashboard-grid-container') ? getComputedStyle(document.getElementById('dashboard-grid-container')).display : null,
    oldDisplay: document.getElementById('dashboard-old-content') ? getComputedStyle(document.getElementById('dashboard-old-content')).display : null,
    dashGlobal: typeof window.__dashboard !== 'undefined',
  }));

  return { mainWorld };
}
