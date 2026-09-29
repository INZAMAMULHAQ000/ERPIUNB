// Determine whether toggle triggers a full reload by intercepting network and
// checking document readiness + full body length after the click.
export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(2500);

  // Track all requests + responses after we land on home.
  const requests = [];
  page.on('request', r => requests.push(r.url()));
  page.on('response', r => requests.push('RESP ' + r.status() + ' ' + r.url()));

  await page.goto('http://localhost/ERP/index.php', { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => document.querySelectorAll('[data-carbon-chart] svg').length >= 1, { timeout: 8000 });
  await page.waitForTimeout(800);
  requests.length = 0; // clear the initial-load requests

  const before = await page.evaluate(() => ({
    readyState: document.readyState,
    bodyLen: document.body.innerHTML.length,
    oldLen: (document.getElementById('dashboard-old-content') || {}).children?.length ?? -1,
  }));

  await page.click('#themeToggle');
  await page.waitForTimeout(1500);

  const after = await page.evaluate(() => ({
    readyState: document.readyState,
    bodyLen: document.body.innerHTML.length,
    oldLen: (document.getElementById('dashboard-old-content') || {}).children?.length ?? -1,
    ticketChart: !!document.getElementById('ticketChart'),
    dataCarbon: document.querySelectorAll('[data-carbon-chart]').length,
    bodyTheme: document.body.getAttribute('data-theme'),
  }));

  return { before, after, requests };
}
