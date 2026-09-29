// Capture the main-document response headers of the assets page after login.
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

  // Capture headers on the assets navigation
  let docHeaders = null;
  const resp = await page.goto('http://localhost/ERP/modules/assets_dashboard/index.php', { waitUntil: 'domcontentloaded' });
  if (resp) docHeaders = resp.headers();
  await page.waitForTimeout(2500);

  // Also capture all response headers via the response event for this doc
  const responses = [];
  const onResp = r => {
    if (r.request().resourceType() === 'document') {
      responses.push({ url: r.url(), status: r.status(), headers: r.headers() });
    }
  };
  page.on('response', onResp);
  await page.reload({ waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1500);
  page.off('response', onResp);

  const out = await page.evaluate(() => ({
    Charts: typeof window.Charts,
    Chart: typeof window.Chart,
    renderCarbonChart: typeof window.renderCarbonChart,
  }));

  return { docHeaders, responses, out };
}
