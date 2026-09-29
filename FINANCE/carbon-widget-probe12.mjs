export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(3000);

  // Inject a real <script> tag — it executes in the MAIN world and can see
  // globals the isolated world of page.evaluate cannot.
  const result = await page.evaluate(async () => {
    return await new Promise((resolve) => {
      const s = document.createElement('script');
      s.textContent = `
        (function () {
          var out = {
            DashboardClass: typeof window.Dashboard,
            dashGlobal: typeof window.__dashboard,
            gridExists: !!document.getElementById('dashboard-grid-container'),
            chartsLib: typeof window.Charts,
            bridge: typeof window.renderCarbonChart,
          };
          var d = window.__dashboard;
          if (d) {
            out.hasWidgets = !!(d.widgets && Object.keys(d.widgets).length);
            out.widgetKeys = d.widgets ? Object.keys(d.widgets) : [];
            out.layoutLoaded = !!d.layout;
            out.layoutWidgets = d.layout ? Object.keys(d.layout.widgets || {}) : [];
            out.isCustomized = d.isCustomized;
            out.gridKids = (document.getElementById('dashboard-grid-container') || {}).children?.length ?? -1;
            out.chartsKeys = d.charts ? Object.keys(d.charts) : [];
          }
          var marker = document.createElement('div');
          marker.id = 'mainworld-dump';
          marker.textContent = JSON.stringify(out);
          document.body.appendChild(marker);
          window.__dumpReady = true;
        })();
      `;
      document.body.appendChild(s);
      setTimeout(() => {
        const m = document.getElementById('mainworld-dump');
        let parsed = null;
        try { parsed = m ? JSON.parse(m.textContent) : { noMarker: true }; } catch (e) { parsed = { parseError: String(e), raw: m && m.textContent }; }
        resolve(parsed);
      }, 300);
    });
  });

  return { result };
}
