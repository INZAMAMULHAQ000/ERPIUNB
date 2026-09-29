// Inspect why window.Charts is undefined on the assets page.
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
  await page.waitForTimeout(500);
  const out = await page.evaluate(() => {
    const scripts = Array.from(document.querySelectorAll('script[src]')).map(s => s.src);
    const perf = {};
    try {
      const entries = performance.getEntriesByType('resource').map(e => e.name);
      scripts.forEach(src => { perf[src] = entries.includes(src) ? 'resource-seen' : 'NOT-in-performance'; });
    } catch (e) { perf.error = String(e); }
    const carbonScript = scripts.find(s => s.includes('@carbon/charts'));
    return {
      readyState: document.readyState,
      scriptCount: scripts.length,
      scripts,
      perf,
      carbonScriptPresent: !!carbonScript,
      Charts: typeof window.Charts,
      chartsKeys: window.Charts ? Object.keys(window.Charts).length : null,
      bridge: typeof window.renderCarbonChart,
      bodyHasFooter: document.body.innerHTML.includes('renderCarbonChart'),
      pageErr: window.__err || null,
    };
  });
  return out;
}
