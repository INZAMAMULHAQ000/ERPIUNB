export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(3000);

  const api = await page.evaluate(async () => {
    const results = {};
    for (const [name, url] of Object.entries({
      layout: '/ERP/api/dashboard/layout.php?dashboard=main',
      ticket: '/ERP/api/widgets/data.php?widget=ticket_status_chart',
      revenue: '/ERP/api/widgets/data.php?widget=revenue_trend',
    })) {
      try {
        const r = await fetch(url, { credentials: 'same-origin' });
        const text = await r.text();
        let parsed = null, err = null;
        try { parsed = JSON.parse(text); } catch (e) { err = String(e); }
        results[name] = {
          status: r.status,
          len: text.length,
          startsWith: text.slice(0, 30),
          parsed: parsed ? { success: parsed.success, keys: Object.keys(parsed), dataType: parsed.data ? typeof parsed.data : null } : null,
          err,
        };
      } catch (e) {
        results[name] = { error: String(e) };
      }
    }
    return results;
  });

  return { api };
}
