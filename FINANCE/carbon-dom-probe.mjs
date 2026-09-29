// Inspect why getElementById('ticketChart') fails though the id appears in HTML.
export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) (?:textbox|input)/)?.[1] || login.match(/@(e\d+) .*"Email/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  const submit   = login.match(/@(e\d+) button "Sign in"/)?.[1] || login.match(/@(e\d+) button "Submit"/)?.[1];
  if (!emailRef || !passRef || !submit) return { error: 'no login' };
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(submit);
  await page.waitForTimeout(1500);

  await page.goto('http://localhost/ERP/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);
  const out = await page.evaluate(() => {
    const ids = ['ticketChart', 'projectChart', 'monthlyChart'];
    const res = {};
    for (const id of ids) {
      const el = document.getElementById(id);
      const els = Array.from(document.querySelectorAll('[id]')).filter(e => e.id === id);
      // Find where the id string appears in the raw HTML
      const html = document.documentElement.outerHTML;
      const idx = html.indexOf('id="' + id + '"');
      const ctx = idx >= 0 ? html.slice(idx - 160, idx + 120) : 'NOT FOUND as id= attr';
      res[id] = {
        byId: el ? el.tagName : null,
        byQueryAll: els.map(e => e.tagName),
        count: els.length,
        htmlCtx: ctx,
      };
    }
    return res;
  });
  return out;
}
