export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(2500);

  const logs = [];
  page.on('console', m => logs.push(m.type() + ': ' + m.text().slice(0, 300)));
  page.on('pageerror', e => logs.push('PAGEERROR: ' + String(e.message || e).slice(0, 500)));

  await page.goto('http://localhost/ERP/index.php', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  // Inject a marker by manipulating the DOM to test main-world reachability.
  const injected = await page.evaluate(() => {
    const scripts = [...document.querySelectorAll('script')];
    // find the inline script that instantiates Dashboard
    const inline = scripts.filter(s => !s.src).map(s => s.textContent);
    const hasDashInit = inline.some(t => t.includes('new Dashboard'));
    const hasRenderCarbon = inline.some(t => t.includes('renderCarbonChart'));
    // Count inline scripts total
    return { inlineCount: inline.length, hasDashInit, hasRenderCarbon };
  });

  return { injected, logs };
}
