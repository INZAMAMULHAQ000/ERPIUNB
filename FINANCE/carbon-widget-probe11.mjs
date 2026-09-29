export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(3000);

  const out = await page.evaluate(() => {
    const scripts = [...document.querySelectorAll('script')];
    return scripts.map((s, i) => ({
      i,
      src: s.src ? s.src.split('/').pop() : null,
      // For inline scripts, dump the text near 'Dashboard' or 'new Dashboard'
      dashIdx: s.textContent ? s.textContent.indexOf('Dashboard') : -1,
      text0: s.textContent ? s.textContent.slice(0, 80) : '',
    }));
  });

  return { out };
}
