// Check for JS-generated DOM on the assets page to determine if ANY script ran.
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

  await page.goto('http://localhost/ERP/modules/assets_dashboard/index.php', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2500);
  const out = await page.evaluate(() => ({
    href: location.href,
    mpCards: document.querySelectorAll('#mockPreviewCards .mp-card').length,   // built by mock_preview JS
    carbonChartContainers: document.querySelectorAll('[data-carbon-chart]').length, // stamped by the bridge
    assetCatChartAttr: (function(){ const el = document.getElementById('assetsByCatChart'); return el ? el.getAttribute('data-carbon-chart') : 'missing'; })(),
    assetCatChartChildren: (function(){ const el = document.getElementById('assetsByCatChart'); return el ? el.children.length : -1; })(),
    styleTags: document.querySelectorAll('style').length,
    tailwindStyle: (function(){ return Array.from(document.querySelectorAll('style')).some(s => s.textContent.includes('tw-border-spacing')); })(),
    themeAttr: document.body.getAttribute('data-theme'),
  }));
  return out;
}
