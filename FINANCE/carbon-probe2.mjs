// Probe globals on the HOME page right after login, capturing early errors.
export default async function run(page, ui) {
  // Capture any JS error at the earliest possible moment on every navigation.
  await page.addInitScript(() => {
    window.__EARLY_ERRORS = [];
    window.addEventListener('error', e => {
      try { window.__EARLY_ERRORS.push(String(e.message) + ' @ ' + (e.filename || '') + ':' + e.lineno); } catch (x) {}
    });
    window.addEventListener('unhandledrejection', e => {
      try { window.__EARLY_ERRORS.push('rejection: ' + String(e.reason)); } catch (x) {}
    });
  });

  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) (?:textbox|input)/)?.[1]
    || login.match(/@(e\d+) .*"Email/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  const submit   = login.match(/@(e\d+) button "Sign in"/)?.[1]
    || login.match(/@(e\d+) button "Submit"/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(submit);

  // Home page after login — wait for it to settle
  await page.waitForTimeout(3000);
  const home = await page.evaluate(() => ({
    href: location.href,
    Chart: typeof window.Chart,
    Charts: typeof window.Charts,
    renderCarbonChart: typeof window.renderCarbonChart,
    earlyErrors: window.__EARLY_ERRORS || [],
    hasCarbonStyle: !!document.querySelector('style') && Array.from(document.querySelectorAll('style')).some(s => s.textContent.includes('tw-border-spacing')),
  }));

  // Also probe the assets page
  await page.goto('http://localhost/ERP/modules/assets_dashboard/index.php', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2500);
  const assets = await page.evaluate(() => ({
    href: location.href,
    Chart: typeof window.Chart,
    Charts: typeof window.Charts,
    renderCarbonChart: typeof window.renderCarbonChart,
    earlyErrors: window.__EARLY_ERRORS || [],
  }));

  return { home, assets };
}
