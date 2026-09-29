// Attach listeners BEFORE login so the login-redirect page load (which lands on
// index.php with the customized layout) is fully captured. Then query the layout
// API from inside the page to see exactly what the engine would receive.
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
  await page.waitForTimeout(3500);

  // We should now be on index.php (login redirect). Query the layout API as the page.
  const api = await page.evaluate(async () => {
    try {
      const r = await fetch('/ERP/api/dashboard/layout.php?dashboard=main', { credentials: 'same-origin' });
      const j = await r.json();
      return { status: r.status, json: j };
    } catch (e) {
      return { error: String(e) };
    }
  });

  const dom = await page.evaluate(() => {
    const grid = document.getElementById('dashboard-grid-container');
    return {
      url: location.pathname,
      gridKids: grid ? grid.children.length : -1,
      gridDisplay: grid ? getComputedStyle(grid).display : null,
      oldDisplay: document.getElementById('dashboard-old-content') ? getComputedStyle(document.getElementById('dashboard-old-content')).display : null,
    };
  });

  return { api, dom, logs, reqs };
}
