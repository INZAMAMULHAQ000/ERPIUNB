export default async function run(page, ui) {
  const logs = [];
  const reqs = [];
  page.on('console', m => logs.push(m.type() + ': ' + m.text().slice(0, 300)));
  page.on('pageerror', e => logs.push('PAGEERROR: ' + String(e.message || e).slice(0, 500)));
  page.on('request', r => reqs.push(r.method() + ' ' + r.url()));
  page.on('requestfailed', r => reqs.push('FAILED ' + r.url()));

  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(4000);

  const dom = await page.evaluate(() => {
    const grid = document.getElementById('dashboard-grid-container');
    return {
      url: location.pathname,
      gridHtml: grid ? grid.innerHTML.slice(0, 500) : 'NO GRID',
      gridKids: grid ? grid.children.length : -1,
      gridClasses: grid ? [...grid.children].map(c => c.className || c.tagName).slice(0, 8) : [],
      oldDisplay: document.getElementById('dashboard-old-content') ? getComputedStyle(document.getElementById('dashboard-old-content')).display : null,
      carbonInGrid: document.querySelectorAll('#dashboard-grid-container [data-carbon-chart]').length,
      svgInGrid: document.querySelectorAll('#dashboard-grid-container [data-carbon-chart] svg').length,
      canvasesInGrid: document.querySelectorAll('#dashboard-grid-container canvas').length,
    };
  });

  return { dom, logs, reqs };
}
