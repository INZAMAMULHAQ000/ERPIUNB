// Diagnose "chart card grows infinitely to the bottom" — v2.
// Only measures VISIBLE chart holders, samples their heights over ~4s to catch
// a live growth loop, and records the SVG height + holder computed style.
export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(2500);

  const snapshot = () => page.evaluate(() => {
    const holders = [...document.querySelectorAll('[data-carbon-chart]')]
      .filter(el => el.offsetParent !== null); // visible only
    return {
      visibleCount: holders.length,
      pageScrollH: document.documentElement.scrollHeight,
      innerH: window.innerHeight,
      cards: holders.map(el => {
        const svg = el.querySelector('svg');
        const cs = getComputedStyle(el);
        const parentCS = getComputedStyle(el.parentElement);
        return {
          cls: (el.className && String(el.className).slice(0, 40)) || el.id,
          rectH: Math.round(el.getBoundingClientRect().height),
          rectW: Math.round(el.getBoundingClientRect().width),
          csH: cs.height, minH: cs.minHeight, maxH: cs.maxHeight, overflowY: cs.overflowY,
          svgH: svg ? Math.round(svg.getBoundingClientRect().height) : null,
          svgAttrH: svg ? svg.getAttribute('height') : null,
          viewBox: svg ? svg.getAttribute('viewBox') : null,
          parentCls: (el.parentElement.className && String(el.parentElement.className).slice(0, 40)) || '',
          parentDisplay: parentCS.display,
          parentH: parentCS.height,
        };
      }),
    };
  });

  const results = {};
  for (const [name, path] of [['home', 'index.php'], ['executive', 'modules/executive_dashboard/index.php'], ['analytics', 'modules/analytics/index.php'], ['budget', 'modules/finance/budget_reports/dashboard.php']]) {
    await page.goto('http://localhost/ERP/' + path, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2500);
    const samples = [];
    for (let i = 0; i < 5; i++) {
      samples.push(await snapshot());
      await page.waitForTimeout(1000);
    }
    results[name] = samples;
  }
  return { results };
}
