// F7 UI QA — log in, drive to budget dashboard, screenshot key pages.
export default async function run(page, ui) {
  const out = { steps: [] };

  // 1. Login page — grab the snapshot to find the form controls
  const login = await ui.snapshot();
  out.loginTitle = (await page.title()).trim();
  const emailRef = login.match(/@(e\d+) (?:textbox|input)/)?.[1]
    || login.match(/@(e\d+) .*"Email/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  if (!emailRef || !passRef) return { error: 'could not find login fields', login };

  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  const submit = login.match(/@(e\d+) button "Sign in"/)?.[1]
    || login.match(/@(e\d+) button "Submit"/)?.[1];
  if (!submit) return { error: 'no sign-in button', login };
  await ui.click(submit);

  // Wait for the app shell to mount (nav appears post-login)
  await page.waitForSelector('#sidebar, .sidebar, nav', { timeout: 15000 }).catch(() => {});
  await page.waitForTimeout(1200);
  out.loggedIn = !(await page.title()).includes('Sign in');
  out.urlAfterLogin = page.url();

  // 2. Budget dashboard
  // NOTE: use textContent, not innerText — the app is a fixed-height layout, so
  // innerText only exposes viewport-visible text and truncates the main content.
  await page.goto('http://localhost/ERP/modules/finance/budget_reports/dashboard.php');
  await page.waitForTimeout(1000);
  const dashText = await page.evaluate(() => document.body.textContent);
  out.dashboardHasTotal = dashText.includes('Total Budget');
  out.dashboardHasUtil = dashText.includes('Budget Utilization');
  out.dashboardBudgetVisible = dashText.includes('32,000.00');
  out.dashboardActualVisible = dashText.includes('8,000.00');
  await page.screenshot({ path: 'F7-dashboard.png', fullPage: true });

  // 3. Budgets index
  await page.goto('http://localhost/ERP/modules/finance/budgets/index.php');
  await page.waitForTimeout(800);
  const bText = await page.evaluate(() => document.body.innerText.slice(0, 1500));
  out.budgetListShowsTestBudget = bText.includes('F7 TEST Annual Budget');
  await page.screenshot({ path: 'F7-budgets.png', fullPage: true });

  // 4. Variance analysis
  await page.goto('http://localhost/ERP/modules/finance/budget_monitoring/variance.php');
  await page.waitForTimeout(800);
  const vText = await page.evaluate(() => document.body.innerText.slice(0, 1500));
  out.varianceRenders = vText.includes('Variance') || vText.includes('variance');
  await page.screenshot({ path: 'F7-variance.png', fullPage: true });

  return out;
}
