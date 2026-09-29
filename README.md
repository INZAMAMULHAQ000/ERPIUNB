<p align="center">
  <img src="https://img.shields.io/badge/version-2.0.0-indigo?style=for-the-badge&labelColor=1e293b" alt="Version">
  <img src="https://img.shields.io/badge/PHP-8.1-777BB4?style=for-the-badge&logo=php&logoColor=white&labelColor=1e293b" alt="PHP">
  <img src="https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white&labelColor=1e293b" alt="MySQL">
  <img src="https://img.shields.io/badge/Tailwind_CSS-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white&labelColor=1e293b" alt="Tailwind CSS">
  <img src="https://img.shields.io/badge/Chart.js-FF6384?style=for-the-badge&logo=chartdotjs&logoColor=white&labelColor=1e293b" alt="Chart.js">
</p>

<p align="center">
  <img src="https://img.shields.io/badge/License-MIT-22c55e?style=flat-square" alt="MIT License">
  <img src="https://img.shields.io/badge/status-production%20ready-22c55e?style=flat-square" alt="Production Ready">
  <img src="https://img.shields.io/badge/security-audited-22c55e?style=flat-square" alt="Security">

  <img src="https://img.shields.io/badge/coverage-4f46e5?style=flat-square&label=modules&labelColor=1e293b&color=4f46e5" alt="Modules">
  <img src="https://img.shields.io/badge/coverage-4f46e5?style=flat-square&label=reports&labelColor=1e293b&color=4f46e5" alt="Reports">
  <img src="https://img.shields.io/badge/coverage-4f46e5?style=flat-square&label=RBAC%20roles&labelColor=1e293b&color=4f46e5" alt="RBAC">
  <img src="https://img.shields.io/badge/maintained-2026-4f46e5?style=flat-square&labelColor=1e293b" alt="Maintained">
</p>

<h1 align="center">🚀 Enterprise ERP System</h1>

<p align="center">
  <strong>A modular, production-ready Enterprise Resource Planning system built with vanilla PHP & MySQL.</strong><br>
  Lightweight, no-composer-dependency architecture — deploy on any PHP 8.1+ host in minutes.
</p>

---

<br>

## ✨ Highlights

| Area | Capability |
|------|------------|
| **👥 HR & Payroll** | Employees, attendance, leaves, timesheets, holidays, designations |
| **🤝 CRM & Sales** | Leads, deals, clients, follow-ups, pipeline tracking |
| **📋 Projects & Tasks** | Full project lifecycle, task boards, milestones, assignments |
| **🎫 Helpdesk & Support** | Tickets, categories, SLAs, knowledge base with article management |
| **💰 Finance & Accounting** | Invoices, payments, expenses, tax settings, multi-currency |
| **📦 Inventory & Assets** | Asset lifecycle, assignments, returns, maintenance, licenses, vendors |
| **📊 Analytics & Reports** | Custom reports, scheduled exports, executive dashboards, Chart.js visualizations |
| **🔐 Security & Access** | RBAC with granular permissions, audit trails, CSRF protection, session management |
| **🌗 UX & Customization** | Dark/light theme, collapsible sidebar, responsive mobile layout, flash messaging |

<br>

## 🏗 Architecture

```
ERP/
├── config/           # Database connection, seed data, environment detection
├── helpers/          # Security, RBAC, validation, pagination, URL, redirect, currency, menus
├── middleware/        # Authentication guard
├── modules/          # 40+ feature modules (self-contained CRUD + logic)
├── templates/        # Navbar, sidebar, shared layout components
├── includes/         # Header / footer bootstrap
├── assets/           # CSS, JS, uploads
├── uploads/          # User-uploaded documents & files
└── index.php         # Dashboard entry point
```

**Architecture principles:**
- **Zero dependencies** — no Composer, no Node build step, no framework lock-in. Deploy by uploading files.
- **Modular by design** — each module lives in its own directory with independent routes.
- **Environment-aware** — auto-detects local vs production; production silences errors and uses separate credentials.
- **Security-first** — PDO prepared statements, CSRF tokens, XSS sanitization, IP audit logging, .htaccess firewall.

<br>

## 🧩 Modules

### 👥 Core Management
| Module | Description |
|--------|-------------|
| Users | Full user lifecycle with avatars, status, role assignment |
| Roles | Create & manage roles with granular permission sets |
| Permissions | Fine-grained resource-level access control tied to roles |
| Employees | Employee database with documents, status tracking, departments |
| Departments | Organizational unit management |
| Designations | Job title / position management |
| Menu Manager | Dynamic navigation builder with role-based visibility |

### ⏱ HR & Workforce
| Module | Description |
|--------|-------------|
| Attendance | Daily check-in recording with date-based tracking |
| Leave Requests | Apply, approve, reject — full workflow with leave types |
| Leave Types | Configurable leave categories (sick, casual, annual, etc.) |
| Timesheets | Per-project/project time logging |
| Holidays | Company holiday calendar management |

### 🤝 CRM & Sales
| Module | Description |
|--------|-------------|
| Leads | Capture, qualify, and assign sales leads |
| Deals | Pipeline tracking with stage progression |
| Clients | Client profiles, contacts, communication history |
| Follow-ups | Scheduled follow-up reminders |

### 📋 Project Management
| Module | Description |
|--------|-------------|
| Projects | Create, budget, status tracking, team assignment |
| Tasks | Kanban-style boards, subtasks, tags, comments, assignees |
| Milestones | Project milestone tracking with dates |
| Timesheets | Log hours per project/task |

### 🎫 Helpdesk
| Module | Description |
|--------|-------------|
| Tickets | Issue tracking with status workflow, priority, assignments |
| Ticket Categories | Classify and organize support tickets |
| SLAs | Service Level Agreement configurations |
| Knowledge Base | Internal / external articles with categories |

### 💰 Finance
| Module | Description |
|--------|-------------|
| Invoices | Generate, manage, track invoice status (paid/pending/overdue) |
| Payments | Record payments against invoices |
| Expenses | Track and categorize business expenses with approval workflow |
| Expense Categories | Configurable expense classifications |
| Tax Settings | Configure tax rates (VAT, GST, etc.) |
| Multi-currency | Real-time currency switching with session persistence |

### 📦 Assets & Inventory
| Module | Description |
|--------|-------------|
| Assets | Asset registry with status lifecycle |
| Asset Categories | Classification taxonomy |
| Asset Assignments | Track who has what — assignment & return workflow |
| Asset Returns | Formal return processing |
| Maintenance | Schedule & log maintenance activities |
| Licenses | Software license tracking with expiry management |
| Inventory | Stock level management |
| Vendors | Supplier / vendor directory |

### 📊 Analytics & Reports
| Module | Description |
|--------|-------------|
| Executive Dashboard | High-level KPIs for leadership |
| Helpdesk Dashboard | Support metrics and ticket analytics |
| Assets Dashboard | Asset utilization and status overview |
| Analytics | Rich Chart.js dashboards with filtering |
| Reports | Custom report builder with save, export (CSV/PDF), print |
| Scheduled Reports | Auto-generated recurring reports |
| Activity Logs | User activity timeline with export |
| Audit Logs | Security audit trail: who did what and when |

<br>

## ⚙️ Tech Stack

| Technology | Usage |
|------------|-------|
| **PHP 8.1+** | Core application logic — all vanilla, zero framework |
| **MySQL** | Relational data storage with PDO prepared statements |
| **Tailwind CSS** | Utility-first responsive UI — loaded via CDN, zero build step |
| **Chart.js 4** | Real-time interactive charts and analytics dashboards |
| **Font Awesome 6** | Iconography across the interface |
| **Apache mod_rewrite** | Clean URL routing, directory lockdown, security headers |
| **Inter UI** | System font stack for clean typography |

<br>

## 🚀 Quick Start

### 1. Clone
```bash
git clone https://github.com/your-org/erp-system.git
```

### 2. Configure Database
Create a MySQL database, then edit `config/database.php`:

```php
// Local development — auto-detected
define('DB_HOST', 'localhost');
define('DB_NAME', 'erp_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Seed Data
Import `config/schema.sql`, then run `config/migrations/` (in order) into your database, or hit the seed endpoint.

### 4. Serve
Deploy on Apache with `mod_rewrite` enabled — works out of the box on:
- **Local**: XAMPP / MAMP / Laravel Valet
- **Production**: cPanel, hPanel, any shared/VPS hosting with PHP 8.1+

No Composer, no `npm install`, no build step — **upload and go**.

<br>

## 🔒 Security

The system implements defense-in-depth:

- **PDO Prepared Statements** — eliminates SQL injection vectors
- **CSRF Protection** — per-session tokens validated on every state-changing request
- **XSS Sanitization** — `htmlspecialchars()` with UTF-8 on all output
- **Input Validation** — centralized validation helper with email, required-field, and custom rules
- **RBAC** — Role-based access control at the module + action level
- **Audit Logging** — every CRUD operation logged with user ID, IP, action, and timestamp
- **HTTPS Enforcement** — security headers via `.htaccess` (X-Frame-Options, X-Content-Type-Options, HSTS, Referrer-Policy)
- **Directory Hardening** — `config/`, `helpers/`, `middleware/`, `templates/` blocked via Apache rewrite rules; all `.sql` files denied at the HTTP layer
- **Sensitive File Blocking** — `.sql`, `.md`, `.env`, `.jsonl`, `seed_data.php`, dotfiles all denied
- **Error Handling** — production mode silences display errors; all errors logged server-side

<br>

## 📊 Dashboard Preview

The system features a dynamic dashboard with:

| Widget | Data Source |
|--------|-------------|
| **Stat cards** | Live counts: users, employees, projects, tickets, invoices, clients |
| **Bar chart** | Monthly user registrations (last 6 months, Chart.js) |
| **Donut chart** | Ticket status distribution |
| **Donut chart** | Project status breakdown |
| **Recent activity** | Last 8 audit log entries with user names |
| **Pending approvals** | Leave requests, expenses awaiting action |
| **Module activity** | Bar chart of the 6 most active modules in the last 7 days |
| **Greeting** | Time-aware welcome message + current date |

<br>

## 📁 Environment Detection

The system automatically detects its environment:

```
localhost / 127.0.0.1 / ::1   →   Local mode (debug ON, local DB)
Anything else                  →   Production mode (errors OFF, production DB)
```

No manual `.env` files or configuration switches needed.

<br>

## 🧪 Production Checklist

- [ ] Update `config/database.php` production credentials
- [ ] Set strong `APP_KEY` / CSRF salt
- [ ] Enable HTTPS on the server
- [ ] Review `.htaccess` rewrite rules
- [ ] Configure regular database backups
- [ ] Set file upload limits in `php.ini`
- [ ] Keep database backups out of the repo — store dumps on the server / offsite, never commit them

<br>

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing`)
5. Open a Pull Request

**Guidelines:**
- Follow existing module structure and naming conventions
- Use PDO prepared statements for all queries
- Sanitize all output with `sanitize()` / `escape()`
- Add audit logging for state-changing actions
- Maintain backward compatibility

<br>

## 📄 License

MIT &copy; 2026 KMS Digitechs. See [LICENSE](LICENSE) for details.

---

<p align="center">
  Built with ❤️ using vanilla PHP & Tailwind CSS
</p>
