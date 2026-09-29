// Probe which globals actually exist on the assets dashboard.
export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) (?:textbox|input)/)?.[1]
    || login.match(/@(e\d+) .*"Email/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  const submit   = login.match(/@(e\d+) button "Sign in"/)?.[1]
    || login.match(/@(e\d+) button "Submit"/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(submit);
  await page.waitForTimeout(2500);

  await page.goto('http://localhost/ERP/modules/assets_dashboard/index.php', { waitUntil: 'networkidle' });
  await page.waitForTimeout(800);
  const out = await page.evaluate(() => ({
    tailwind: typeof window.tailwind,
    Chart: typeof window.Chart,
    Charts: typeof window.Charts,
    THEME_CYCLE: typeof window.THEME_CYCLE,
    renderCarbonChart: typeof window.renderCarbonChart,
    fluentDropdown: typeof window.fluentDropdown,
    dashboardCore: typeof window.DashboardWidget,
    bodyStart: document.body.innerText.slice(0, 60),
  }));
  return out;
}
