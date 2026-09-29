# Takines Labada Hub — Plain PHP Edition

This is a **plain PHP + MySQL** rebuild of the Takines Labada laundry shop
management system. The original project was a Laravel skeleton (routes,
Blade views, Eloquent models) with no working backend — this version
re-implements the same screens and features using procedural PHP, PDO,
and plain HTML/CSS/JS so it can run on any standard PHP + MySQL hosting
(e.g. XAMPP, WAMP, MAMP, or shared hosting) **without Composer, Artisan,
or the Laravel framework.**

## Features

- **Login / Logout** — session-based authentication with hashed passwords
  and role-based access (`owner` and `staff`)
- **Owner Dashboard** — today's sales, monthly revenue, net income,
  low-stock alerts, weekly sales chart, QPT (3% quarterly tax) summary
- **Sales** — add / edit / delete laundry transactions, filter by
  today / week / month, pagination, live weight & amount summary
  (1 load = 7 kg)
- **Inventory** — stock levels with low/critical indicators, restock form,
  recent restock history
- **Expenses** — log / edit / delete expenses, category filter, monthly
  summary cards
- **Income & Reports** — monthly income summary, expense breakdown,
  cash flow trend chart
- **Tax Reports** — quarterly 3% Percentage Tax (QPT) breakdown
- **User Management** — owner can add/edit/deactivate/delete Owner & Staff
  accounts
- **Settings** — shop information and password change
- **Staff Portal** — simplified view for staff to record new loads only
  (no access to financial reports, inventory, expenses, or user management)

## Requirements

- PHP 8.0+ with the **PDO MySQL** extension enabled
- MySQL 5.7+ / MariaDB 10.3+
- A web server (Apache/Nginx) or PHP's built-in server for local testing

## Setup

1. **Create the database.**
   Import `sql/schema.sql` into MySQL. This creates the `takines_labada`
   database, all tables, and seed/demo data.

   Using phpMyAdmin: open phpMyAdmin → Import → choose `sql/schema.sql` → Go.

   Using the command line:
   ```bash
   mysql -u root -p < sql/schema.sql
   ```

2. **Configure the database connection.**
   Edit `config.php` and update the constants if needed:
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_NAME', 'takines_labada');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

3. **(Optional) Add a logo.**
   Place a `logo.png` file in `assets/images/`. If no logo is found the
   pages fall back to a "TL" text badge automatically.

4. **Run the app.**
   - With XAMPP/WAMP: copy this folder into `htdocs`/`www` and visit
     `http://localhost/<folder-name>/` in your browser.
   - With PHP's built-in server (from this folder):
     ```bash
     php -S localhost:8000
     ```
     then open `http://localhost:8000/`.

## Default Login Accounts

| Role  | Username | Password      |
|-------|----------|---------------|
| Owner | `owner`  | `password123` |
| Staff | `staff`  | `password123` |

**Change these passwords immediately** via Settings → Change Password
(owner) once you're in the system — the seed accounts use a shared
demo password.

## Project Structure

```
.
├── config.php              # DB connection + session + helper functions
├── index.php                # redirects to login or dashboard
├── login.php / logout.php   # authentication
├── dashboard.php             # owner dashboard
├── sales.php                  # sales transactions (CRUD)
├── inventory.php              # inventory & restock
├── expenses.php               # expense records (CRUD)
├── reports_income.php         # income summary & cash flow
├── reports_tax.php            # quarterly tax (QPT) report
├── users.php                  # user management
├── settings.php               # shop info & password change
├── staff_dashboard.php        # staff portal (record loads only)
├── includes/
│   ├── header_app.php / footer_app.php     # owner layout (sidebar + topbar)
│   └── header_staff.php / footer_staff.php # staff portal layout
├── assets/
│   ├── css/app.css           # all styling (unchanged from design)
│   └── images/                # place logo.png here (optional)
└── sql/schema.sql             # database schema + seed data
```

## Notes on Business Rules

- **1 load = 7 kg** — used throughout the Sales module to compute total
  weight.
- **QPT (Quarterly Percentage Tax)** is calculated at a fixed **3%** of
  gross quarterly sales.
- **Inventory status** thresholds: an item is **critical** at ≤20% of its
  max stock, **low** at ≤40%, and **OK** above that.
- **Net Income** = Total Sales Revenue − Total Expenses (for the selected
  period).
- Staff accounts can only record new sales transactions and view today's
  recorded loads — they cannot see revenue totals, expenses, inventory,
  reports, or user management (enforced server-side via `require_role()`).

## Security Notes

- Passwords are hashed with PHP's `password_hash()` (bcrypt).
- All forms include CSRF tokens, verified via `verify_csrf()`.
- All database queries use PDO prepared statements.
- All user-supplied output is escaped with `h()` (an `htmlspecialchars()`
  wrapper) before being printed.
