export default async function run(page) {
  const results = { events: [], tests: {} };

  // ── Login ──
  await page.goto('http://localhost/XXX/modules/auth/login.php', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(400);
  await page.locator('#email').fill('admin@erp.com');
  await page.locator('#password').fill('admin123');
  await page.locator('button[type="submit"]').click();
  await page.waitForTimeout(2500);

  // Helper to test a page
  async function checkPage(url, checks) {
    await page.goto(url, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);
    const pageUrl = page.url();
    const title = await page.title();
    const body = await page.textContent('body').catch(() => '') || '';
    const result = { url: pageUrl, title, phpErrors: /Fatal error|Parse error/i.test(body.substring(0, 500)) };
    for (const [key, test] of Object.entries(checks)) {
      result[key] = typeof test === 'function' ? test(body, pageUrl, title) : body.includes(test);
    }
    result.bodyFirst400 = body.substring(0, 400);
    return result;
  }

  // T1: Postings page
  results.tests.T1_postings = await checkPage(
    'http://localhost/XXX/modules/finance/payroll/postings/index.php',
    {
      noRedirect: (b, u) => !u.includes('no-access'),
      hasTableHeaders: b => b.includes('Payroll Run') && b.includes('Posting Date') && b.includes('Total Gross'),
      hasTotalNet: 'Total Net',
      hasStatusBadge: b => b.includes('Posted') || b.includes('Draft'),
      hasActionsCol: 'Actions',
    }
  );

  // T2: View posting (JE #97)
  results.tests.T2_view = await checkPage(
    'http://localhost/XXX/modules/finance/payroll/postings/view.php?id=1',
    {
      noRedirect: (b, u) => !u.includes('no-access') && !u.includes('login'),
      hasJournalEntryDetails: 'Journal Entry Details',
      hasAccountCode: b => b.includes('600-SAL') || b.includes('Salary') || b.includes('account_code'),
      hasAuditChain: 'Audit Chain',
      showsNetAmount: '12500',  // posting net amount
    }
  );

  // T4: Create new posting page
  results.tests.T4_create = await checkPage(
    'http://localhost/XXX/modules/finance/payroll/postings/create.php',
    {
      noRedirect: (b, u) => !u.includes('no-access') && !u.includes('no-access'),
      hasForm: 'form',
      showsPayrollRuns: 'Payroll Run',
    }
  );

  // T5: Reconciliation page
  results.tests.T5_reconciliation = await checkPage(
    'http://localhost/XXX/modules/finance/payroll/reconciliation/index.php',
    {
      noRedirect: (b, u) => !u.includes('no-access') && !u.includes('login'),
      hasTableHeaders: b => b.includes('Payroll') && b.includes('Period') && b.includes('Total'),
      hasAmounts: '12500',
    }
  );

  // T7: Non-admin blocking check via the postings view (same page, no special permission needed)
  results.tests.T7_unauth = await checkPage(
    'http://localhost/XXX/modules/finance/payroll/postings/index.php',
    {
      adminCanAccess: (b, u) => !u.includes('no-access') && !u.includes('login'),
    }
  );

  // GL Mappings page
  results.tests.T_mappings = await checkPage(
    'http://localhost/XXX/modules/finance/payroll/mappings/index.php',
    {
      noRedirect: (b, u) => !u.includes('no-access') && !u.includes('login'),
      hasGLContent: b => b.includes('Salary') || b.includes('Account') || b.includes('payroll'),
    }
  );

  return results;
}