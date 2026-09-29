export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(3500);

  const result = await page.evaluate(async () => {
    return await new Promise((resolve) => {
      const s = document.createElement('script');
      s.textContent = `
        (function () {
          var out = {
            unqualifiedDashboard: typeof Dashboard,
            windowDashboard: typeof window.Dashboard,
            dashGlobal: typeof window.__dashboard,
            chartsLib: typeof window.Charts,
            bridge: typeof window.renderCarbonChart,
          };
          // If the class exists, try instantiating on a scratch element to catch errors.
          if (typeof Dashboard !== 'undefined') {
            try {
              var scratch = document.createElement('div');
              scratch.id = 'scratch-dash';
              document.body.appendChild(scratch);
              var inst = new Dashboard('scratch-dash', { renderOnInit: false });
              out.instantiated = true;
              out.hasInit = typeof inst.init === 'function';
            } catch (e) {
              out.instantiateError = String(e && e.stack || e);
            }
          }
          var marker = document.createElement('div');
          marker.id = 'mainworld-dump2';
          marker.textContent = JSON.stringify(out);
          document.body.appendChild(marker);
          window.__dump2 = true;
        })();
      `;
      document.body.appendChild(s);
      setTimeout(() => {
        const m = document.getElementById('mainworld-dump2');
        let parsed = null;
        try { parsed = m ? JSON.parse(m.textContent) : { noMarker: true }; } catch (e) { parsed = { parseError: String(e), raw: m && m.textContent }; }
        resolve(parsed);
      }, 300);
    });
  });

  return { result };
}
