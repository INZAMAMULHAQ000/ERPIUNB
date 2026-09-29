// Decisive test: does a reload or cache-buster make the scripts actually run?
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
  await page.waitForTimeout(3000);

  const checks = [];

  // 1. Direct goto
  await page.goto('http://localhost/ERP/modules/assets_dashboard/index.php?cb=1', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2500);
  checks.push(await page.evaluate(() => ({ step: 'goto', Charts: typeof window.Charts, renderCarbonChart: typeof window.renderCarbonChart, href: location.href })));

  // 2. Reload the same URL
  await page.reload({ waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2500);
  checks.push(await page.evaluate(() => ({ step: 'reload', Charts: typeof window.Charts, renderCarbonChart: typeof window.renderCarbonChart, href: location.href })));

  // 3. goto index.php (home) with cache-buster
  await page.goto('http://localhost/ERP/index.php?cb=2', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2500);
  checks.push(await page.evaluate(() => ({ step: 'home', Charts: typeof window.Charts, renderCarbonChart: typeof window.renderCarbonChart, href: location.href })));

  return checks;
}
