// Task #4 verification — DOM-based: assert each new chart container holds a
// rendered Carbon SVG (data-carbon-chart attr present + svg inside).
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

  const PAGES = [
    { name: 'helpdesk',  url: 'http://localhost/ERP/modules/helpdesk_dashboard/index.php',      charts: ['priorityChart'] },
    { name: 'assets',    url: 'http://localhost/ERP/modules/assets_dashboard/index.php',        charts: ['assetsByCatChart', 'warrantyChart', 'licenseChart'] },
    { name: 'leave',     url: 'http://localhost/ERP/modules/leave_management/dashboard.php',    charts: ['balChart'] },
    { name: 'ar',        url: 'http://localhost/ERP/modules/finance/ar_dashboard/index.php',    charts: ['agingChart', 'invoiceTrendChart'] },
    { name: 'expense',   url: 'http://localhost/ERP/modules/finance/expense_reports/dashboard.php', charts: ['monthBreakdownChart', 'topCatChart', 'deptExpChart', 'expenseTrendChart'] },
    { name: 'analytics', url: 'http://localhost/ERP/modules/analytics/index.php',               charts: ['revenueChart', 'deptChart', 'ticketChart', 'leadChart', 'attChart', 'attHeatChart'] },
  ];

  const evalDom = ids => `(() => {
    const out = {};
    for (const id of ${JSON.stringify(ids)}) {
      const el = document.getElementById(id);
      if (!el) { out[id] = { missing: true }; continue; }
      const svgs = el.querySelectorAll('svg').length;
      out[id] = {
        svgs,
        carbon: el.hasAttribute('data-carbon-chart'),
        height: el.offsetHeight,
      };
    }
    return out;
  })()`;

  const results = [];
  for (const p of PAGES) {
    await page.goto(p.url, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);
    const r = await page.evaluate(evalDom(p.charts));
    // Summarize: each chart should have >=1 svg
    const ok = Object.entries(r).every(([id, st]) => st.svgs >= 1 && st.carbon);
    results.push({ page: p.name, ok, charts: r });
  }
  return results;
}
