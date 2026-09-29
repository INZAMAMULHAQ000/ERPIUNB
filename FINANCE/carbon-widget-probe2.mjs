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
  page.on('console', m => logs.push(m.type() + ': ' + m.text().slice(0, 250)));
  page.on('pageerror', e => logs.push('PAGEERROR: ' + String(e.message || e).slice(0, 400)));
  page.on('request', r => reqs.push(r.url()));
  page.on('response', r => { if (r.url().includes('api/')) reqs.push('RESP ' + r.status() + ' ' + r.url()); });

  await page.goto('http://localhost/ERP/index.php', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(5000);

  const out = await page.evaluate(() => {
    const grid = document.getElementById('dashboard-grid-container');
    // Check if dashboard-core.js <script> tag is present in DOM
    const scripts = [...document.querySelectorAll('script[src]')].map(s => s.src);
    return {
      gridHtml0: grid ? grid.innerHTML.slice(0, 400) : 'NO GRID',
      gridLen: grid ? grid.innerHTML.length : -1,
      hasCoreScript: scripts.some(s => s.includes('dashboard-core.js')),
      scriptSrcs: scripts.filter(s => s.includes('dashboard') || s.includes('carbon')),
      gridChildTags: grid ? [...grid.children].map(c => c.tagName + '.' + (c.className || '')).slice(0, 8) : [],
    };
  });

  return { out, logs, reqs: reqs.filter(r => r.includes('api/') || r.includes('dashboard')) };
}
