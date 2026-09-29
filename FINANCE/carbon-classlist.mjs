// List the chart classes exposed by the Carbon Charts UMD (window.Charts)
// and confirm the bridge is loaded. Run against any page that includes header.php.
export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) (?:textbox|input)/)?.[1]
    || login.match(/@(e\d+) .*"Email/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  const submit   = login.match(/@(e\d+) button "Sign in"/)?.[1]
    || login.match(/@(e\d+) button "Submit"/)?.[1];
  if (!emailRef || !passRef || !submit) return { error: 'login form not found', login };
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(submit);
  // Wait for the POST navigation to finish, then confirm we left the login page.
  await page.waitForTimeout(2500);
  const postLogin = await page.evaluate(() => ({
    href: location.href,
    title: document.title,
    hasSidebar: !!document.querySelector('#sidebar, .sidebar, nav'),
    bodyStart: document.body.innerText.slice(0, 80),
  }));

  await page.goto('http://localhost/ERP/modules/assets_dashboard/index.php', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1500);
  const out = await page.evaluate(() => ({
    href: location.href,
    title: document.title,
    bodyStart: document.body.innerText.slice(0, 120),
    classes: window.Charts ? Object.keys(window.Charts).sort() : null,
    bridge: typeof window.renderCarbonChart,
    carbonCss: !!document.querySelector('link[href*="carbon"]'),
    chartCount: (window.renderCarbonChart && window.renderCarbonChart.count) ? window.renderCarbonChart.count() : -1,
  }));
  return { postLogin, assets: out };
}
