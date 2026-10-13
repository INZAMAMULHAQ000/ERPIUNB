<?php
$pageTitle = 'Financial Dashboard';
$dashboardKey = 'finance';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once 'helpers/finance_helpers.php';

if (!hasPermission('finance.view')) { die('Access Denied'); }

$db = getDB();

// ——— Foundation Dashboard ———
// Current Financial Year
$currentFY = $db->query("SELECT * FROM financial_years WHERE status = 'active' LIMIT 1")->fetch();

// Account counts
$totalAccounts  = (int)$db->query("SELECT COUNT(*) as cnt FROM chart_of_accounts WHERE status = 'active'")->fetch()['cnt'];
$assetAccounts  = (int)$db->query("SELECT COUNT(*) as cnt FROM chart_of_accounts coa JOIN account_groups ag ON coa.group_id = ag.id WHERE ag.nature = 'asset' AND coa.status = 'active'")->fetch()['cnt'];
$incomeAccounts = (int)$db->query("SELECT COUNT(*) as cnt FROM chart_of_accounts coa JOIN account_groups ag ON coa.group_id = ag.id WHERE ag.nature = 'income' AND coa.status = 'active'")->fetch()['cnt'];
$expenseAccounts = (int)$db->query("SELECT COUNT(*) as cnt FROM chart_of_accounts coa JOIN account_groups ag ON coa.group_id = ag.id WHERE ag.nature = 'expense' AND coa.status = 'active'")->fetch()['cnt'];

// Recent accounts (last 5)
$recentAccounts = $db->query("
    SELECT coa.account_code, coa.account_name, ag.group_name, coa.created_at
    FROM chart_of_accounts coa
    JOIN account_groups ag ON coa.group_id = ag.id
    ORDER BY coa.created_at DESC
    LIMIT 5
")->fetchAll();

// ——— Journal Accounting Dashboard ———
$todayJournals = (int)$db->query("SELECT COUNT(*) as cnt FROM journal_entries WHERE status = 'posted' AND DATE(voucher_date) = CURDATE()")->fetch()['cnt'];
$todayDebit = (float)$db->query("SELECT COALESCE(SUM(jed.debit_amount), 0) as total FROM journal_entry_details jed JOIN journal_entries je ON jed.journal_entry_id = je.id WHERE je.status = 'posted' AND DATE(je.voucher_date) = CURDATE()")->fetch()['total'];
$todayCredit = (float)$db->query("SELECT COALESCE(SUM(jed.credit_amount), 0) as total FROM journal_entry_details jed JOIN journal_entries je ON jed.journal_entry_id = je.id WHERE je.status = 'posted' AND DATE(je.voucher_date) = CURDATE()")->fetch()['total'];
$pendingJournals = (int)$db->query("SELECT COUNT(*) as cnt FROM journal_entries WHERE status = 'draft'")->fetch()['cnt'];
$recentJournals = $db->query("SELECT * FROM journal_entries ORDER BY created_at DESC LIMIT 5")->fetchAll();

// ——— Banking KPIs ———
$totalBankBal = (float)$db->query("SELECT COALESCE(SUM(ba.opening_balance), 0) + COALESCE((SELECT SUM(gl.running_balance) FROM general_ledger gl JOIN bank_accounts ba2 ON gl.account_id = ba2.chart_account_id WHERE gl.id IN (SELECT MAX(id) FROM general_ledger GROUP BY account_id)), 0) AS bal FROM bank_accounts ba WHERE ba.status = 'active'")->fetch()['bal'];
$totalCashBal = (float)$db->query("SELECT COALESCE(SUM(ca.opening_balance), 0) + COALESCE((SELECT SUM(gl.running_balance) FROM general_ledger gl JOIN cash_accounts ca2 ON gl.account_id = ca2.chart_account_id WHERE gl.id IN (SELECT MAX(id) FROM general_ledger GROUP BY account_id)), 0) AS bal FROM cash_accounts ca WHERE ca.status = 'active'")->fetch()['bal'];
$todayDeposits = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM bank_transactions WHERE transaction_type = 'deposit' AND DATE(transaction_date) = CURDATE() AND status = 'posted'")->fetchColumn();
$todayWithdrawals = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM bank_transactions WHERE transaction_type = 'withdrawal' AND DATE(transaction_date) = CURDATE() AND status = 'posted'")->fetchColumn();
$pendingRecon = (int)$db->query("SELECT COUNT(*) FROM bank_reconciliation WHERE status = 'in_progress'")->fetchColumn();
$recentTransfers = $db->query("SELECT mt.*, COALESCE(fba.account_name, fca.cash_name) AS from_name, COALESCE(tba.account_name, tca.cash_name) AS to_name FROM money_transfers mt LEFT JOIN bank_accounts fba ON mt.from_account_type = 'bank' AND mt.from_account_id = fba.id LEFT JOIN bank_accounts tba ON mt.to_account_type = 'bank' AND mt.to_account_id = tba.id LEFT JOIN cash_accounts fca ON mt.from_account_type = 'cash' AND mt.from_account_id = fca.id LEFT JOIN cash_accounts tca ON mt.to_account_type = 'cash' AND mt.to_account_id = tca.id ORDER BY mt.created_at DESC LIMIT 5")->fetchAll();
?>

<style>
/* ============================================================
   Finance Module Launcher — Apple Watermorphism
   Deep gradients, water droplet highlights, super rounded
   ============================================================ */

/* --- Section header --- */
.finance-section-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}
.finance-section-header .bar {
    width: 4px;
    height: 1.25rem;
    border-radius: 999px;
    flex-shrink: 0;
}
.finance-section-header h2 {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
}

/* --- Card grid --- */
.finance-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1rem;
}

/* --- Water droplet card --- */
.water-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1.5rem 1.5rem 1.25rem;
    border-radius: 1.25rem;
    text-decoration: none;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-shadow:
        0 10px 40px rgba(0,0,0,0.08),
        0 2px 8px rgba(0,0,0,0.04),
        inset 0 1px 0 rgba(255,255,255,0.20);
}
/* Primary water highlight — top-left radial glow */
.water-card::before {
    content: '';
    position: absolute;
    top: -40%;
    left: -20%;
    width: 140%;
    height: 140%;
    background: radial-gradient(
        ellipse 80% 60% at 25% 20%,
        rgba(255,255,255,0.20) 0%,
        rgba(255,255,255,0.08) 30%,
        transparent 70%
    );
    pointer-events: none;
    transition: opacity 0.35s ease;
}
/* Secondary subsurface glow — bottom-right */
.water-card::after {
    content: '';
    position: absolute;
    bottom: -20%;
    right: -10%;
    width: 80%;
    height: 60%;
    background: radial-gradient(
        ellipse at 70% 80%,
        rgba(255,255,255,0.06) 0%,
        transparent 60%
    );
    pointer-events: none;
}
.water-card:hover {
    transform: translateY(-4px) scale(1.01);
    box-shadow:
        0 20px 60px rgba(0,0,0,0.12),
        0 4px 16px rgba(0,0,0,0.06),
        inset 0 1px 0 rgba(255,255,255,0.25);
}
.water-card:active {
    transform: translateY(-1px) scale(0.99);
}

/* --- Frosted icon inset --- */
.water-card-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 0.875rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #fff;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.25);
    position: relative;
    z-index: 1;
}

/* --- Card text --- */
.water-card-content {
    position: relative;
    z-index: 1;
}
.water-card-content h3 {
    font-size: 0.9375rem;
    font-weight: 700;
    color: #fff;
    line-height: 1.2;
    letter-spacing: -0.01em;
    text-shadow: 0 1px 2px rgba(0,0,0,0.08);
}
.water-card-content p {
    font-size: 0.75rem;
    font-weight: 400;
    color: rgba(255,255,255,0.75);
    margin-top: 0.1875rem;
    line-height: 1.3;
}

/* --- Launcher hero area --- */
.finance-hero {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0 0 0.25rem;
    margin-bottom: 2rem;
}
.finance-hero-icon {
    width: 3.25rem;
    height: 3.25rem;
    min-width: 3.25rem;
    border-radius: 0.875rem;
    background: linear-gradient(135deg, #6366f1, #7c3aed, #a855f7);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.375rem;
    box-shadow: 0 6px 16px rgba(99,102,241,0.30);
    position: relative;
}
.finance-hero-icon::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(135deg, rgba(255,255,255,0.20) 0%, transparent 60%);
    pointer-events: none;
}
.finance-hero-text h1 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary, #1e293b);
    line-height: 1.2;
}
.finance-hero-text p {
    font-size: 0.875rem;
    color: var(--text-muted, #94a3b8);
    margin-top: 0.125rem;
}

/* --- Dark mode: water cards are already dark by design, just tweak shadows --- */
[data-theme="dark"] .water-card {
    box-shadow:
        0 10px 40px rgba(0,0,0,0.25),
        0 2px 8px rgba(0,0,0,0.15),
        inset 0 1px 0 rgba(255,255,255,0.10);
}
[data-theme="dark"] .water-card:hover {
    box-shadow:
        0 20px 60px rgba(0,0,0,0.35),
        0 4px 16px rgba(0,0,0,0.20),
        inset 0 1px 0 rgba(255,255,255,0.12);
}
</style>

<!-- ============================================================
     FINANCE MODULE LAUNCHER — Odoo-style Card Grid
     ============================================================ -->

<div class="finance-hero">
    <div class="finance-hero-icon">
        <i class="fas fa-coins"></i>
    </div>
    <div class="finance-hero-text">
        <h1>Finance</h1>
        <p>Foundation accounting, banking &amp; cash management, accounts receivable, accounts payable, and expense management</p>
    </div>
</div>

<!-- View Toggle -->
<div class="flex gap-2 mb-6">
    <a href="?view=modules" class="view-toggle px-5 py-2 rounded-lg text-sm font-medium transition <?= ($_GET['view'] ?? 'modules') === 'modules' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
        <i class="fas fa-th-large mr-1.5"></i>Modules
    </a>
    <a href="?view=dashboard" class="view-toggle px-5 py-2 rounded-lg text-sm font-medium transition <?= ($_GET['view'] ?? 'modules') === 'dashboard' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
        <i class="fas fa-chart-bar mr-1.5"></i>Dashboard
    </a>
</div>

<div id="view-modules" class="<?= ($_GET['view'] ?? 'modules') !== 'modules' ? 'hidden' : '' ?>">

<!-- ===== 1. Foundation Setup ===== -->
<?php if (hasPermission('finance.view')): ?>
<section class="mb-8">
    <div class="finance-section-header">
        <div class="bar" style="background: linear-gradient(180deg, #6366f1, #818cf8);"></div>
        <h2>Foundation Setup</h2>
    </div>
    <div class="finance-grid">
        <a href="financial_year/index.php" class="water-card" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #4338ca 100%);">
            <div class="water-card-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="water-card-content">
                <h3>Financial Years</h3>
                <p>Define and manage fiscal periods</p>
            </div>
        </a>
        <a href="chart_of_accounts/index.php" class="water-card" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1d4ed8 100%);">
            <div class="water-card-icon"><i class="fas fa-book"></i></div>
            <div class="water-card-content">
                <h3>Chart of Accounts</h3>
                <p>Manage your full COA structure</p>
            </div>
        </a>
        <a href="account_groups/index.php" class="water-card" style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 50%, #0369a1 100%);">
            <div class="water-card-icon"><i class="fas fa-sitemap"></i></div>
            <div class="water-card-content">
                <h3>Account Groups</h3>
                <p>Organise accounts into groups</p>
            </div>
        </a>
        <a href="account_types/index.php" class="water-card" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 50%, #0e7490 100%);">
            <div class="water-card-icon"><i class="fas fa-tags"></i></div>
            <div class="water-card-content">
                <h3>Account Types</h3>
                <p>Classify account categories</p>
            </div>
        </a>
    </div>
</section>
<?php endif; ?>

<!-- ===== 2. Journals & Ledger ===== -->
<?php if (hasPermission('finance.journal.view') || hasPermission('finance.voucher.manage') || hasPermission('finance.ledger.view') || hasPermission('finance.balance.edit')): ?>
<section class="mb-8">
    <div class="finance-section-header">
        <div class="bar" style="background: linear-gradient(180deg, #f59e0b, #fbbf24);"></div>
        <h2>Journals &amp; Ledger</h2>
    </div>
    <div class="finance-grid">
        <?php if (hasPermission('finance.journal.view')): ?>
        <a href="journal_entries/index.php" class="water-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 50%, #b45309 100%);">
            <div class="water-card-icon"><i class="fas fa-journal-whills"></i></div>
            <div class="water-card-content">
                <h3>Journal Entries</h3>
                <p>Record and post transactions</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('finance.voucher.manage')): ?>
        <a href="voucher_types/index.php" class="water-card" style="background: linear-gradient(135deg, #f97316 0%, #ea580c 50%, #c2410c 100%);">
            <div class="water-card-icon"><i class="fas fa-receipt"></i></div>
            <div class="water-card-content">
                <h3>Voucher Types</h3>
                <p>Configure voucher templates</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('finance.ledger.view')): ?>
        <a href="ledger/index.php" class="water-card" style="background: linear-gradient(135deg, #d946ef 0%, #c026d3 50%, #a21caf 100%);">
            <div class="water-card-icon"><i class="fas fa-book-open"></i></div>
            <div class="water-card-content">
                <h3>Ledger</h3>
                <p>View the general ledger</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('finance.balance.edit')): ?>
        <a href="opening_balances/index.php" class="water-card" style="background: linear-gradient(135deg, #e11d48 0%, #be123c 50%, #9f1239 100%);">
            <div class="water-card-icon"><i class="fas fa-scale-balanced"></i></div>
            <div class="water-card-content">
                <h3>Opening Balances</h3>
                <p>Set opening balances for accounts</p>
            </div>
        </a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ===== 3. Banking & Cash ===== -->
<?php if (hasPermission('finance.bank.view') || hasPermission('finance.cash.view') || hasPermission('finance.bank.transaction') || hasPermission('finance.cash.transaction') || hasPermission('finance.transfer.create') || hasPermission('finance.reconciliation.manage')): ?>
<section class="mb-8">
    <div class="finance-section-header">
        <div class="bar" style="background: linear-gradient(180deg, #10b981, #34d399);"></div>
        <h2>Banking &amp; Cash</h2>
    </div>
    <div class="finance-grid">
        <?php if (hasPermission('finance.bank.view')): ?>
        <a href="bank_accounts/index.php" class="water-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);">
            <div class="water-card-icon"><i class="fas fa-university"></i></div>
            <div class="water-card-content">
                <h3>Bank Accounts</h3>
                <p>Manage bank accounts</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('finance.cash.view')): ?>
        <a href="cash_accounts/index.php" class="water-card" style="background: linear-gradient(135deg, #14b8a6 0%, #0d9488 50%, #0f766e 100%);">
            <div class="water-card-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="water-card-content">
                <h3>Cash Accounts</h3>
                <p>Manage cash accounts</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('finance.bank.transaction')): ?>
        <a href="bank_transactions/index.php" class="water-card" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 50%, #15803d 100%);">
            <div class="water-card-icon"><i class="fas fa-exchange-alt"></i></div>
            <div class="water-card-content">
                <h3>Bank Transactions</h3>
                <p>Record deposits &amp; withdrawals</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('finance.cash.transaction')): ?>
        <a href="cash_transactions/index.php" class="water-card" style="background: linear-gradient(135deg, #84cc16 0%, #65a30d 50%, #4d7c0f 100%);">
            <div class="water-card-icon"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="water-card-content">
                <h3>Cash Transactions</h3>
                <p>Record cash movements</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('finance.transfer.create')): ?>
        <a href="money_transfers/index.php" class="water-card" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #3730a3 100%);">
            <div class="water-card-icon"><i class="fas fa-arrow-right"></i></div>
            <div class="water-card-content">
                <h3>Money Transfers</h3>
                <p>Transfer between accounts</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('finance.cash.transaction')): ?>
        <a href="petty_cash/index.php" class="water-card" style="background: linear-gradient(135deg, #a855f7 0%, #9333ea 50%, #7e22ce 100%);">
            <div class="water-card-icon"><i class="fas fa-coins"></i></div>
            <div class="water-card-content">
                <h3>Petty Cash</h3>
                <p>Track small expenses &amp; top-ups</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('finance.reconciliation.manage')): ?>
        <a href="bank_reconciliation/index.php" class="water-card" style="background: linear-gradient(135deg, #ec4899 0%, #db2777 50%, #be185d 100%);">
            <div class="water-card-icon"><i class="fas fa-balance-scale"></i></div>
            <div class="water-card-content">
                <h3>Reconciliation</h3>
                <p>Reconcile bank statements</p>
            </div>
        </a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ===== 4. Accounts Receivable ===== -->
<?php if (hasPermission('ar_dashboard.view') || hasPermission('customer_invoices.view') || hasPermission('customer_receipts.view') || hasPermission('credit_notes.view') || hasPermission('customer_statements.view') || hasPermission('aging_reports.view')): ?>
<section class="mb-8">
    <div class="finance-section-header">
        <div class="bar" style="background: linear-gradient(180deg, #ef4444, #f87171);"></div>
        <h2>Accounts Receivable</h2>
    </div>
    <div class="finance-grid">
        <?php if (hasPermission('ar_dashboard.view')): ?>
        <a href="ar_dashboard/index.php" class="water-card" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 50%, #b91c1c 100%);">
            <div class="water-card-icon"><i class="fas fa-chart-pie"></i></div>
            <div class="water-card-content">
                <h3>AR Dashboard</h3>
                <p>AR analytics &amp; ageing overview</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('customer_invoices.view')): ?>
        <a href="customer_invoices/index.php" class="water-card" style="background: linear-gradient(135deg, #f43f5e 0%, #e11d48 50%, #be123c 100%);">
            <div class="water-card-icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="water-card-content">
                <h3>Customer Invoices</h3>
                <p>Create and manage invoices</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('customer_receipts.view')): ?>
        <a href="customer_receipts/index.php" class="water-card" style="background: linear-gradient(135deg, #fb7185 0%, #f43f5e 50%, #e11d48 100%);">
            <div class="water-card-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="water-card-content">
                <h3>Customer Receipts</h3>
                <p>Record incoming payments</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('credit_notes.view')): ?>
        <a href="credit_notes/index.php" class="water-card" style="background: linear-gradient(135deg, #b91c1c 0%, #991b1b 50%, #7f1d1d 100%);">
            <div class="water-card-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="water-card-content">
                <h3>Credit Notes</h3>
                <p>Issue credit adjustments</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('customer_statements.view')): ?>
        <a href="customer_statements/index.php" class="water-card" style="background: linear-gradient(135deg, #be185d 0%, #9d174d 50%, #831843 100%);">
            <div class="water-card-icon"><i class="fas fa-file-alt"></i></div>
            <div class="water-card-content">
                <h3>Customer Statements</h3>
                <p>Generate account statements</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('aging_reports.view')): ?>
        <a href="aging_reports/index.php" class="water-card" style="background: linear-gradient(135deg, #f97316 0%, #ea580c 50%, #c2410c 100%);">
            <div class="water-card-icon"><i class="fas fa-clock"></i></div>
            <div class="water-card-content">
                <h3>Aging Reports</h3>
                <p>Analyse AR ageing buckets</p>
            </div>
        </a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ===== 5. Accounts Payable ===== -->
<?php if (hasPermission('procurement_dashboard.view') || hasPermission('vendor_master.view') || hasPermission('purchase_orders.view') || hasPermission('goods_receipt.view') || hasPermission('vendor_bills.view') || hasPermission('vendor_payments.view') || hasPermission('debit_notes.view') || hasPermission('vendor_statements.view') || hasPermission('aging_reports_ap.view')): ?>
<section class="mb-8">
    <div class="finance-section-header">
        <div class="bar" style="background: linear-gradient(180deg, #8b5cf6, #a78bfa);"></div>
        <h2>Accounts Payable</h2>
    </div>
    <div class="finance-grid">
        <?php if (hasPermission('procurement_dashboard.view')): ?>
        <a href="procurement_dashboard/index.php" class="water-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 50%, #6d28d9 100%);">
            <div class="water-card-icon"><i class="fas fa-chart-pie"></i></div>
            <div class="water-card-content">
                <h3>AP Dashboard</h3>
                <p>Procurement &amp; AP analytics</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('vendor_master.view')): ?>
        <a href="vendor_master/index.php" class="water-card" style="background: linear-gradient(135deg, #a78bfa 0%, #8b5cf6 50%, #7c3aed 100%);">
            <div class="water-card-icon"><i class="fas fa-truck"></i></div>
            <div class="water-card-content">
                <h3>Vendor Master</h3>
                <p>Manage vendor information</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('purchase_orders.view')): ?>
        <a href="purchase_orders/index.php" class="water-card" style="background: linear-gradient(135deg, #c084fc 0%, #a855f7 50%, #9333ea 100%);">
            <div class="water-card-icon"><i class="fas fa-file-purchase"></i></div>
            <div class="water-card-content">
                <h3>Purchase Orders</h3>
                <p>Create and manage POs</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('goods_receipt.view')): ?>
        <a href="goods_receipt/index.php" class="water-card" style="background: linear-gradient(135deg, #e879f9 0%, #d946ef 50%, #c026d3 100%);">
            <div class="water-card-icon"><i class="fas fa-clipboard-check"></i></div>
            <div class="water-card-content">
                <h3>Goods Receipt</h3>
                <p>Record incoming goods</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('vendor_bills.view')): ?>
        <a href="vendor_bills/index.php" class="water-card" style="background: linear-gradient(135deg, #f0abfc 0%, #e879f9 50%, #d946ef 100%);">
            <div class="water-card-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="water-card-content">
                <h3>Vendor Bills</h3>
                <p>Record accounts payable bills</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('vendor_payments.view')): ?>
        <a href="vendor_payments/index.php" class="water-card" style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 50%, #5b21b6 100%);">
            <div class="water-card-icon"><i class="fas fa-credit-card"></i></div>
            <div class="water-card-content">
                <h3>Vendor Payments</h3>
                <p>Process payments to vendors</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('debit_notes.view')): ?>
        <a href="debit_notes/index.php" class="water-card" style="background: linear-gradient(135deg, #6d28d9 0%, #5b21b6 50%, #4c1d95 100%);">
            <div class="water-card-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="water-card-content">
                <h3>Debit Notes</h3>
                <p>Vendor credit &amp; adjustments</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('vendor_statements.view')): ?>
        <a href="vendor_statements/index.php" class="water-card" style="background: linear-gradient(135deg, #9333ea 0%, #7e22ce 50%, #6b21a8 100%);">
            <div class="water-card-icon"><i class="fas fa-file-alt"></i></div>
            <div class="water-card-content">
                <h3>Vendor Statements</h3>
                <p>Vendor account activity</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('aging_reports_ap.view')): ?>
        <a href="ap_aging/index.php" class="water-card" style="background: linear-gradient(135deg, #a21caf 0%, #86198f 50%, #701a75 100%);">
            <div class="water-card-icon"><i class="fas fa-clock"></i></div>
            <div class="water-card-content">
                <h3>AP Aging</h3>
                <p>Analyse AP ageing buckets</p>
            </div>
        </a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ===== 6. Expense Management ===== -->
<?php if (hasPermission('expense_dashboard.view') || hasPermission('expense_categories.view') || hasPermission('company_expenses.view') || hasPermission('employee_claims.view') || hasPermission('expense_approvals.view') || hasPermission('expense_reimbursements.view') || hasPermission('expense_reports.view')): ?>
<section class="mb-8">
    <div class="finance-section-header">
        <div class="bar" style="background: linear-gradient(180deg, #f59e0b, #fbbf24);"></div>
        <h2>Expense Management</h2>
    </div>
    <div class="finance-grid">
        <?php if (hasPermission('expense_dashboard.view')): ?>
        <a href="expense_reports/dashboard.php" class="water-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 50%, #b45309 100%);">
            <div class="water-card-icon"><i class="fas fa-chart-pie"></i></div>
            <div class="water-card-content">
                <h3>Expense Dashboard</h3>
                <p>Real-time expense analytics &amp; KPIs</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('expense_categories.view')): ?>
        <a href="expense_categories/index.php" class="water-card" style="background: linear-gradient(135deg, #f97316 0%, #ea580c 50%, #c2410c 100%);">
            <div class="water-card-icon"><i class="fas fa-tags"></i></div>
            <div class="water-card-content">
                <h3>Expense Categories</h3>
                <p>Manage categories &amp; GL mapping</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('company_expenses.view')): ?>
        <a href="company_expenses/index.php" class="water-card" style="background: linear-gradient(135deg, #eab308 0%, #ca8a04 50%, #a16207 100%);">
            <div class="water-card-icon"><i class="fas fa-building"></i></div>
            <div class="water-card-content">
                <h3>Company Expenses</h3>
                <p>Record and manage company spending</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('employee_claims.view')): ?>
        <a href="employee_claims/index.php" class="water-card" style="background: linear-gradient(135deg, #facc15 0%, #eab308 50%, #ca8a04 100%);">
            <div class="water-card-icon"><i class="fas fa-users"></i></div>
            <div class="water-card-content">
                <h3>Employee Claims</h3>
                <p>Employee reimbursement requests</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('expense_approvals.view')): ?>
        <a href="expense_approvals/index.php" class="water-card" style="background: linear-gradient(135deg, #84cc16 0%, #65a30d 50%, #4d7c0f 100%);">
            <div class="water-card-icon"><i class="fas fa-check-double"></i></div>
            <div class="water-card-content">
                <h3>Expense Approvals</h3>
                <p>Approve or reject expenses</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('expense_reimbursements.view')): ?>
        <a href="expense_reimbursements/index.php" class="water-card" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 50%, #15803d 100%);">
            <div class="water-card-icon"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="water-card-content">
                <h3>Reimbursements</h3>
                <p>Process employee claim payments</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('expense_reports.view')): ?>
        <a href="expense_reports/index.php" class="water-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);">
            <div class="water-card-icon"><i class="fas fa-file-alt"></i></div>
            <div class="water-card-content">
                <h3>Expense Reports</h3>
                <p>Employee, department &amp; category reports</p>
            </div>
        </a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ===== 7. Budget Management (F7) ===== -->
<?php if (hasPermission('budget.view') || hasPermission('budget.transfer') || hasPermission('budget.revise') || hasPermission('budget.report')): ?>
<section class="mb-8">
    <div class="finance-section-header">
        <div class="bar" style="background: linear-gradient(180deg, #0ea5e9, #38bdf8);"></div>
        <h2>Budget Management &amp; Cost Control</h2>
    </div>
    <div class="finance-grid">
        <?php if (hasPermission('budget.view')): ?>
        <a href="budget_reports/dashboard.php" class="water-card" style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 50%, #0369a1 100%);">
            <div class="water-card-icon"><i class="fas fa-chart-line"></i></div>
            <div class="water-card-content">
                <h3>Budget Dashboard</h3>
                <p>KPIs, utilization &amp; alerts</p>
            </div>
        </a>
        <a href="budgets/index.php" class="water-card" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1d4ed8 100%);">
            <div class="water-card-icon"><i class="fas fa-coins"></i></div>
            <div class="water-card-content">
                <h3>Budgets</h3>
                <p>Master budgets &amp; versions</p>
            </div>
        </a>
        <a href="budget_monitoring/index.php" class="water-card" style="background: linear-gradient(135deg, #14b8a6 0%, #0d9488 50%, #0f766e 100%);">
            <div class="water-card-icon"><i class="fas fa-tasks"></i></div>
            <div class="water-card-content">
                <h3>Budget Monitoring</h3>
                <p>Budget vs actual tracking</p>
            </div>
        </a>
        <a href="budget_monitoring/variance.php" class="water-card" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 50%, #0e7490 100%);">
            <div class="water-card-icon"><i class="fas fa-arrows-alt-h"></i></div>
            <div class="water-card-content">
                <h3>Variance Analysis</h3>
                <p>Account, dept &amp; project variance</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('budget.transfer')): ?>
        <a href="budget_transfers/index.php" class="water-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 50%, #6d28d9 100%);">
            <div class="water-card-icon"><i class="fas fa-exchange-alt"></i></div>
            <div class="water-card-content">
                <h3>Budget Transfers</h3>
                <p>Move funds between lines</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('budget.revise')): ?>
        <a href="budget_revisions/index.php" class="water-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 50%, #b45309 100%);">
            <div class="water-card-icon"><i class="fas fa-pen-alt"></i></div>
            <div class="water-card-content">
                <h3>Budget Revisions</h3>
                <p>Increase, decrease &amp; adjust</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('budget.report')): ?>
        <a href="budget_reports/index.php" class="water-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);">
            <div class="water-card-icon"><i class="fas fa-file-alt"></i></div>
            <div class="water-card-content">
                <h3>Budget Reports</h3>
                <p>Department, project &amp; account reports</p>
            </div>
        </a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ===== 8. Fixed Assets (F-9) ===== -->
<?php if (hasPermission('fixedasset.view')): ?>
<section class="mb-8">
    <div class="finance-section-header">
        <div class="bar" style="background: linear-gradient(180deg, #059669, #047857);"></div>
        <h2>Fixed Assets (F-9)</h2>
    </div>
    <div class="finance-grid">
        <a href="fixed_assets/dashboard/index.php" class="water-card" style="background: linear-gradient(135deg, #059669 0%, #047857 50%, #065f46 100%);">
            <div class="water-card-icon"><i class="fas fa-building"></i></div>
            <div class="water-card-content">
                <h3>Fixed Assets Dashboard</h3>
                <p>Asset capitalization & depreciation</p>
            </div>
        </a>
    </div>
</section>
<?php endif; ?>

<!-- ===== 9. Payroll Finance (F-10) ===== -->
<?php if (hasPermission('payroll_finance.view')): ?>
<section class="mb-8">
    <div class="finance-section-header">
        <div class="bar" style="background: linear-gradient(180deg, #7c3aed, #8b5cf6);"></div>
        <h2>Payroll Finance (F-10)</h2>
    </div>
    <div class="finance-grid">
        <a href="payroll_finance/index.php" class="water-card" style="background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 50%, #a78bfa 100%);">
            <div class="water-card-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="water-card-content">
                <h3>Payroll Finance Integration</h3>
                <p>Payroll & finance bridging</p>
            </div>
        </a>
    </div>
</section>
<?php endif; ?>

<!-- ===== 10. Financial Reporting (F-11) ===== -->
<?php if (hasPermission('report.dashboard.view') || hasPermission('report.view')): ?>
<section class="mb-8">
    <div class="finance-section-header">
        <div class="bar" style="background: linear-gradient(180deg, #0f766e, #14b8a6);"></div>
        <h2>Financial Reporting (F-11)</h2>
    </div>
    <div class="finance-grid">
        <a href="reports/dashboard/index.php" class="water-card" style="background: linear-gradient(135deg, #0f766e 0%, #14b8a6 50%, #5eead4 100%);">
            <div class="water-card-icon"><i class="fas fa-chart-pie"></i></div>
            <div class="water-card-content">
                <h3>Financial Reports</h3>
                <p>Statements, ledgers, exports & integrity checks</p>
            </div>
        </a>
    </div>
</section>
<?php endif; ?>

<!-- ===== 10b. Period & Year-End Close (F12) ===== -->
<?php if (hasPermission('period.view')): ?>
<section class="mb-8">
    <div class="finance-section-header">
        <div class="bar" style="background: linear-gradient(180deg, #0f766e, #14b8a6);"></div>
        <h2>Period &amp; Year-End Close</h2>
    </div>
    <div class="finance-grid">
        <a href="period_close/index.php" class="water-card" style="background: linear-gradient(135deg, #0f766e 0%, #0d9488 50%, #115e59 100%);">
            <div class="water-card-icon"><i class="fas fa-flag-checkered"></i></div>
            <div class="water-card-content">
                <h3>Closing Dashboard</h3>
                <p>Period status, checklist & net P/L</p>
            </div>
        </a>
        <?php if (hasPermission('period.create')): ?>
        <a href="period_close/financial_years/index.php" class="water-card" style="background: linear-gradient(135deg, #4338ca 0%, #4f46e5 50%, #3730a3 100%);">
            <div class="water-card-icon"><i class="fas fa-calendar-days"></i></div>
            <div class="water-card-content">
                <h3>Financial Years</h3>
                <p>Open → Closing → Closed lifecycle</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('period.close')): ?>
        <a href="period_close/periods/index.php" class="water-card" style="background: linear-gradient(135deg, #b45309 0%, #d97706 50%, #92400e 100%);">
            <div class="water-card-icon"><i class="fas fa-lock"></i></div>
            <div class="water-card-content">
                <h3>Accounting Periods</h3>
                <p>Close, lock and reopen months</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('year.close')): ?>
        <a href="period_close/closing/index.php" class="water-card" style="background: linear-gradient(135deg, #9f1239 0%, #be123c 50%, #881337 100%);">
            <div class="water-card-icon"><i class="fas fa-hourglass-end"></i></div>
            <div class="water-card-content">
                <h3>Year-End Close</h3>
                <p>Validate, close temporary accounts, carry forward</p>
            </div>
        </a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ===== 10c. Group & Consolidation (F13) ===== -->
<?php if (hasPermission('company.view')): ?>
<section class="mb-8">
    <div class="finance-section-header">
        <div class="bar" style="background: linear-gradient(180deg, #1d4ed8, #3b82f6);"></div>
        <h2>Group &amp; Consolidation</h2>
    </div>
    <div class="finance-grid">
        <a href="group/index.php" class="water-card" style="background: linear-gradient(135deg, #1e40af 0%, #2563eb 50%, #1e3a8a 100%);">
            <div class="water-card-icon"><i class="fas fa-sitemap"></i></div>
            <div class="water-card-content">
                <h3>Group Dashboard</h3>
                <p>Multi-company overview &amp; KPIs</p>
            </div>
        </a>
        <?php if (hasPermission('company.create')): ?>
        <a href="../companies/index.php" class="water-card" style="background: linear-gradient(135deg, #0e7490 0%, #0891b2 50%, #155e75 100%);">
            <div class="water-card-icon"><i class="fas fa-building"></i></div>
            <div class="water-card-content">
                <h3>Companies</h3>
                <p>Legal entities &amp; relationships</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('intercompany.create')): ?>
        <a href="group/intercompany/transactions.php" class="water-card" style="background: linear-gradient(135deg, #7c2d12 0%, #ea580c 50%, #9a3412 100%);">
            <div class="water-card-icon"><i class="fas fa-right-left"></i></div>
            <div class="water-card-content">
                <h3>Intercompany</h3>
                <p>Transactions with auto-counterpart</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('consolidation.create')): ?>
        <a href="group/consolidation/runs.php" class="water-card" style="background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 50%, #5b21b6 100%);">
            <div class="water-card-icon"><i class="fas fa-layer-group"></i></div>
            <div class="water-card-content">
                <h3>Consolidation</h3>
                <p>Eliminations &amp; group statements</p>
            </div>
        </a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ===== 11. Tools & Settings ===== -->
<?php if (hasPermission('finance.settings.manage') || hasPermission('invoices.view') || hasPermission('payments.view') || hasPermission('expenses.view') || hasPermission('finance.view')): ?>
<section class="mb-8">
    <div class="finance-section-header">
        <div class="bar" style="background: linear-gradient(180deg, #64748b, #94a3b8);"></div>
        <h2>Tools & Settings</h2>
    </div>
    <div class="finance-grid">
        <?php if (hasPermission('finance.settings.manage')): ?>
        <a href="settings/index.php" class="water-card" style="background: linear-gradient(135deg, #475569 0%, #334155 50%, #1e293b 100%);">
            <div class="water-card-icon"><i class="fas fa-cog"></i></div>
            <div class="water-card-content">
                <h3>Finance Settings</h3>
                <p>Configure module preferences</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('finance.view')): ?>
        <a href="<?= url('modules/tax_settings/index.php') ?>" class="water-card" style="background: linear-gradient(135deg, #78716c 0%, #57534e 50%, #44403c 100%);">
            <div class="water-card-icon"><i class="fas fa-percentage"></i></div>
            <div class="water-card-content">
                <h3>Tax Settings</h3>
                <p>Configure tax rates & rules</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('invoices.view')): ?>
        <a href="<?= url('modules/invoices/index.php') ?>" class="water-card" style="background: linear-gradient(135deg, #6d28d9 0%, #5b21b6 50%, #4c1d95 100%);">
            <div class="water-card-icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="water-card-content">
                <h3>Invoices</h3>
                <p>Legacy invoice management</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('payments.view')): ?>
        <a href="<?= url('modules/payments/index.php') ?>" class="water-card" style="background: linear-gradient(135deg, #4f46e5 0%, #4338ca 50%, #3730a3 100%);">
            <div class="water-card-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="water-card-content">
                <h3>Payments</h3>
                <p>Legacy payment tracking</p>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('expenses.view')): ?>
        <a href="<?= url('modules/expenses/index.php') ?>" class="water-card" style="background: linear-gradient(135deg, #0f766e 0%, #115e59 50%, #134e4a 100%);">
            <div class="water-card-icon"><i class="fas fa-receipt"></i></div>
            <div class="water-card-content">
                <h3>Expenses</h3>
                <p>Track business expenses</p>
            </div>
        </a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

</div><!-- /view-modules -->

<div id="view-dashboard" class="<?= ($_GET['view'] ?? 'modules') !== 'dashboard' ? 'hidden' : '' ?>">

<!-- ============================================================
     DASHBOARD CONTENT
     ============================================================ -->

<div id="dashboard-old-content">
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Financial Dashboard</h1>
    <p class="text-gray-600">Foundation overview of your finance module</p>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">

    <!-- Card 1: Current Financial Year -->
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
        <div class="text-sm text-gray-500">Current Financial Year</div>
        <div class="text-lg font-bold text-gray-800 mt-1">
            <?= $currentFY ? sanitize($currentFY['year_name']) : '—' ?>
        </div>
        <?php if ($currentFY): ?>
            <div class="text-xs text-gray-500 mt-1">
                <?= date('M d, Y', strtotime($currentFY['start_date'])) ?> &rarr; <?= date('M d, Y', strtotime($currentFY['end_date'])) ?>
            </div>
            <div class="mt-2">
                <span class="px-2 py-0.5 text-xs rounded <?= $currentFY['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                    <?= ucfirst($currentFY['status']) ?>
                </span>
            </div>
        <?php else: ?>
            <div class="text-xs text-gray-400 mt-1">No active financial year</div>
        <?php endif; ?>
    </div>

    <!-- Card 2: Total Accounts -->
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-indigo-500">
        <div class="text-sm text-gray-500">Total Accounts</div>
        <div class="text-2xl font-bold text-gray-800"><?= $totalAccounts ?></div>
        <div class="text-xs text-gray-500 mt-1">Active chart of accounts</div>
    </div>

    <!-- Card 3: Asset Accounts -->
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
        <div class="text-sm text-gray-500">Asset Accounts</div>
        <div class="text-2xl font-bold text-gray-800"><?= $assetAccounts ?></div>
        <div class="text-xs text-gray-500 mt-1">Nature: Asset</div>
    </div>

    <!-- Card 4: Income Accounts -->
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-yellow-500">
        <div class="text-sm text-gray-500">Income Accounts</div>
        <div class="text-2xl font-bold text-gray-800"><?= $incomeAccounts ?></div>
        <div class="text-xs text-gray-500 mt-1">Nature: Income</div>
    </div>

    <!-- Card 5: Expense Accounts -->
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
        <div class="text-sm text-gray-500">Expense Accounts</div>
        <div class="text-2xl font-bold text-gray-800"><?= $expenseAccounts ?></div>
        <div class="text-xs text-gray-500 mt-1">Nature: Expense</div>
    </div>

</div>

<!-- Recent Accounts Table -->
<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Recent Accounts</h2>
    <?php if ($recentAccounts): ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Code</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Group</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($recentAccounts as $acc): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono text-gray-600"><?= sanitize($acc['account_code']) ?></td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= sanitize($acc['account_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($acc['group_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-500 text-right"><?= date('M d, Y', strtotime($acc['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="text-center py-8 text-gray-500">
        <i class="fas fa-book text-4xl mb-3 text-gray-300"></i>
        <p>No accounts created yet</p>
    </div>
    <?php endif; ?>
</div>

</div><!-- /dashboard-old-content -->

<!-- F2 Dashboard Section -->
<div class="mt-8">
  <h2 class="text-xl font-bold mb-4">Accounting Overview</h2>
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
      <div class="text-sm text-gray-500">Today's Posted Journals</div>
      <div class="text-2xl font-bold"><?= $todayJournals ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
      <div class="text-sm text-gray-500">Today's Debit</div>
      <div class="text-2xl font-bold text-green-700">AED <?= formatNumber($todayDebit) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
      <div class="text-sm text-gray-500">Today's Credit</div>
      <div class="text-2xl font-bold text-orange-700">AED <?= formatNumber($todayCredit) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-yellow-500">
      <div class="text-sm text-gray-500">Pending Drafts</div>
      <div class="text-2xl font-bold"><?= $pendingJournals ?></div>
    </div>
  </div>

  <div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b flex justify-between items-center">
      <h3 class="font-semibold">Recent Journal Entries</h3>
      <a href="<?= url('modules/finance/journal_entries/index.php') ?>" class="text-blue-600 text-sm hover:underline">View All</a>
    </div>
    <table class="w-full">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Voucher No</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        <?php foreach ($recentJournals as $j): ?>
        <tr>
          <td class="px-4 py-2"><?= $j['voucher_date'] ?></td>
          <td class="px-4 py-2 font-mono"><?= sanitize($j['voucher_no']) ?></td>
          <td class="px-4 py-2"><?= sanitize(substr($j['description'] ?? '', 0, 60)) ?></td>
          <td class="px-4 py-2"><?= formatVoucherStatus($j['status']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- F3 Banking Dashboard Section -->
<div class="mt-8">
  <h2 class="text-xl font-bold mb-4">Banking &amp; Cash Overview</h2>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-600">
      <div class="text-sm text-gray-500">Total Bank Balance</div>
      <div class="text-2xl font-bold text-blue-700">AED <?= formatNumber($totalBankBal) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-600">
      <div class="text-sm text-gray-500">Total Cash Balance</div>
      <div class="text-2xl font-bold text-green-700">AED <?= formatNumber($totalCashBal) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-emerald-500">
      <div class="text-sm text-gray-500">Today's Deposits</div>
      <div class="text-2xl font-bold text-emerald-700">AED <?= formatNumber($todayDeposits) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
      <div class="text-sm text-gray-500">Today's Withdrawals</div>
      <div class="text-2xl font-bold text-red-700">AED <?= formatNumber($todayWithdrawals) ?></div>
    </div>
  </div>

  <?php if ($pendingRecon > 0): ?>
  <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded">
    <p class="text-sm"><i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i><span class="font-medium"><?= $pendingRecon ?> reconciliation(s)</span> in progress. <a href="<?= url('modules/finance/bank_reconciliation/index.php') ?>" class="text-blue-600 hover:underline">View pending</a></p>
  </div>
  <?php endif; ?>

  <?php if ($recentTransfers): ?>
  <div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b flex justify-between items-center">
      <h3 class="font-semibold">Recent Transfers</h3>
      <a href="<?= url('modules/finance/money_transfers/index.php') ?>" class="text-blue-600 text-sm hover:underline">View All</a>
    </div>
    <table class="w-full">
      <thead class="bg-gray-50"><tr>
        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">From</th>
        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">To</th>
        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
      </tr></thead>
      <tbody class="divide-y">
        <?php foreach ($recentTransfers as $tr): ?>
        <tr>
          <td class="px-4 py-2 text-sm"><?= date('M d', strtotime($tr['transfer_date'])) ?></td>
          <td class="px-4 py-2 text-sm"><?= sanitize($tr['from_name']) ?></td>
          <td class="px-4 py-2 text-sm"><?= sanitize($tr['to_name']) ?></td>
          <td class="px-4 py-2 text-sm text-right font-mono"><?= formatNumber($tr['amount']) ?></td>
          <td class="px-4 py-2 text-center"><?= formatTxStatus($tr['status']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
</div><!-- /view-dashboard -->


<script>
(function () {

    const STORAGE_KEY = 'finance_dashboard_scroll_position';

    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }

    window.addEventListener('pagehide', function () {

        sessionStorage.setItem(
            STORAGE_KEY,
            String(window.scrollY)
        );

    });

    document.addEventListener('click', function (event) {

        const link = event.target.closest('a');

        if (!link) {
            return;
        }

        const href = link.getAttribute('href');

        if (
            !href ||
            href === '#' ||
            href.startsWith('javascript:')
        ) {
            return;
        }

        const targetUrl =
            new URL(link.href, window.location.href);

        const currentUrl =
            new URL(window.location.href);

        if (
            targetUrl.pathname !== currentUrl.pathname ||
            targetUrl.search !== currentUrl.search
        ) {

            sessionStorage.setItem(
                STORAGE_KEY,
                String(window.scrollY)
            );

        }

    });


    function restoreDashboardScroll() {

        const savedPosition =
            sessionStorage.getItem(STORAGE_KEY);

        if (savedPosition === null) {
            return;
        }

        const position =
            parseInt(savedPosition, 10);

        if (isNaN(position)) {
            sessionStorage.removeItem(STORAGE_KEY);
            return;
        }

        window.scrollTo(0, position);

        requestAnimationFrame(function () {

            window.scrollTo(0, position);

        });

        setTimeout(function () {

            window.scrollTo(0, position);

            sessionStorage.removeItem(
                STORAGE_KEY
            );

        }, 300);

    }


    window.addEventListener('load', function () {

        restoreDashboardScroll();

    });


    window.addEventListener('pageshow', function () {

        restoreDashboardScroll();

    });

})();
</script>


<?php
require_once '../../includes/dashboard_template.php';
require_once '../../includes/footer.php';
?>