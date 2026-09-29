// Dump the rendered app page HTML around the head/footer to find the parser breaker.
export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) (?:textbox|input)/)?.[1]
    || login.match(/@(e\d+) .*"Email/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  const submit   = login.match(/@(e\d+) button "Sign in"/)?.[1]
    || login.match(/@(e\d+) button "Submit"/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(submit);
  await page.waitForTimeout(2500);

  await page.goto('http://localhost/ERP/modules/assets_dashboard/index.php', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  const out = await page.evaluate(() => {
    const html = document.documentElement.outerHTML;
    return {
      readyState: document.readyState,
      htmlLen: html.length,
      // First 2000 chars of head
      headStart: html.slice(0, 2000),
      // Search for unclosed script: count <script vs </script>
      openScripts: (html.match(/<script[\s>]/g) || []).length,
      closeScripts: (html.match(/<\/script>/g) || []).length,
      // Search for unclosed comment
      openComments: (html.match(/<!--/g) || []).length,
      closeComments: (html.match(/-->/g) || []).length,
    };
  });
  return out;
}
