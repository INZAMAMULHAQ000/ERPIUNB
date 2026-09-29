// Fetch the RAW response text of a PHP page (in-page fetch keeps the session cookie)
// and inspect the exact bytes around the head script tags for corruption.
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
  await page.waitForTimeout(3000);

  // Fetch the raw HTML of the assets page from within the (logged-in) page.
  const out = await page.evaluate(async () => {
    const r = await fetch('http://localhost/ERP/modules/assets_dashboard/index.php?raw=1');
    const text = await r.text();
    const headEnd = text.indexOf('</head>');
    const head = text.slice(0, headEnd > 0 ? headEnd : 500);
    return {
      len: text.length,
      status: r.status,
      // Show exact bytes around each script tag in the head
      headScriptTags: (text.match(/<script[\s\S]*?<\/script>/g) || []).slice(0, 6).map(s => s.slice(0, 140)),
      styleTagsInHead: (head.match(/<style[\s\S]*?<\/style>/g) || []).map(s => s.slice(0, 80)),
      hasDOCTYPE: text.trimStart().startsWith('<!DOCTYPE'),
      first200: text.slice(0, 200),
      scriptCount: (text.match(/<script[\s>]/g) || []).length,
      closeCount: (text.match(/<\/script>/g) || []).length,
    };
  });
  return out;
}
