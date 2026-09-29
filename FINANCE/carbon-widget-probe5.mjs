export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(2500);

  const logs = [];
  const reqs = [];
  page.on('console', m => logs.push(m.type() + ': ' + m.text().slice(0, 300)));
  page.on('pageerror', e => logs.push('PAGEERROR: ' + String(e.message || e).slice(0, 500)));
  page.on('request', r => reqs.push(r.method() + ' ' + r.url()));
  page.on('requestfailed', r => reqs.push('FAILED ' + r.url()));

  await page.goto('http://localhost/ERP/index.php', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(3000);

  const out = await page.evaluate(() => {
    const grid = document.getElementById('dashboard-grid-container');
    return {
      gridHtml: grid ? grid.innerHTML.slice(0, 500) : 'NO GRID',
      gridKids: grid ? grid.children.length : -1,
    };
  });

  return { out, logs, reqs };
}
