export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(3000);

  // Capture the raw response body of the layout API as the page sees it.
  const raw = await page.evaluate(async () => {
    try {
      const r = await fetch('/ERP/api/dashboard/layout.php?dashboard=main', { credentials: 'same-origin' });
      const text = await r.text();
      return { status: r.status, contentType: r.headers.get('content-type'), len: text.length, head: text.slice(0, 300), tail: text.slice(-200) };
    } catch (e) {
      return { error: String(e) };
    }
  });

  return { raw };
}
