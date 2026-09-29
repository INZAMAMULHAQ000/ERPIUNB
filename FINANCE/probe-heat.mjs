// Diagnose cc-heatmap: inspect container innerHTML / placeholder / svg count.
export default async function run(page, ui) {
  const login = await ui.snapshot();
  await ui.fill(login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1], 'admin@erp.com');
  await ui.fill(login.match(/@(e\d+) .*[Pp]assword/)?.[1], 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(2500);

  await page.goto('http://localhost/ERP/modules/analytics/carbon_catalog.php', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(4000);

  return await page.evaluate(() => {
    const el = document.getElementById('cc-heatmap');
    if (!el) return { missing: true };
    const svgs = el.querySelectorAll('svg').length;
    const rects = el.querySelectorAll('rect').length;
    const text = el.textContent.slice(0, 80);
    const kids = el.children.length;
    const html0 = el.innerHTML.slice(0, 160);
    // Also check how many data-carbon-chart containers total and how many have svg
    const all = [...document.querySelectorAll('[data-carbon-chart]')];
    const withSvg = all.filter(c => c.querySelectorAll('svg').length >= 1).length;
    return { svgs, rects, text, kids, html0, carbonContainers: all.length, withSvg };
  });
}
