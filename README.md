# Computer Nations — Business Intelligence System

A full-stack, multi-branch inventory / stock / order / purchasing management
website built with plain PHP, MySQL, HTML, CSS and JavaScript, designed to
run on XAMPP. It's built for a company operating several branches across
different cities — every branch keeps its own stock, its own orders, and
its own purchase orders, while HQ roles can see everything at once or drill
into one location. Every user logs into their own dashboard, and what they
can see or do is controlled by a role's privileges — not hardcoded per page.

---

## 1. What's included

- **Multi-branch** — unlimited branches, grouped under towns, each with its own address/phone. Stock, orders, and purchase orders are all scoped to a branch. HQ users (Admin/Manager, or anyone with no fixed branch) get a switcher in the topbar to jump between branches or view an "All branches" aggregate; branch staff are pinned to their own location automatically — though every user can look up stock at any branch, read-only.
- **Authentication** — session-based login with hashed passwords (`password_hash`/`password_verify`), CSRF protection on every form.
- **Role-Based Access Control (RBAC)** — 4 default roles (Admin, Manager, Sales Staff, Inventory Staff), each with a checkbox-editable set of privileges across 32 permission slugs. Add as many roles as you like.
- **Dashboard** — live KPI widgets, a revenue chart (14-day trend, or a per-branch comparison when viewing all branches), a stock-by-category chart (Chart.js), low-stock alerts, and recent orders.
- **Inventory** — products with category, supplier, cost/selling price, per-branch reorder levels, optional image, full CRUD, CSV export. Good stock and SAV (in-repair) stock are shown as separate numbers, and any user can view any branch's stock read-only.
- **Categories** — manage product categories, with a guard against deleting a category that's still in use.
- **Stock movements** — every stock change (in / out / adjustment / transfer / purchase / SAV) is logged per branch with who did it and why — quantity is never edited directly. Doubles as full stock traceability.
- **Transfers** — move stock between two branches in the **same town**: send (deducts from source immediately) → receive (adds to destination). Locked once sent — no edit, no cancel.
- **Expedition** — the same mechanism for moving stock between **different towns**, its own page and permissions, numbered `EXP-` instead of `TRF-`.
- **SAV** — damaged/faulty stock goes through a full repair lifecycle (reported → sent to a technician → repaired/still broken/unrepairable), with a complete activity timeline per item, instead of a one-shot write-off.
- **Technicians** — a simple contacts list for your repair partners, linked from every SAV job.
- **Purchase orders (Bon de commande)** — raise a PO against a supplier for a specific receiving branch, mark it ordered, and receive stock against it there (partial receipts supported).
- **Orders** — multi-item order creation scoped to the active branch, validates stock availability, deducts stock atomically, supports cancellation (which returns stock), CSV export, and a print-ready invoice.
- **Branches** — grouped by town; add/edit locations, activate/deactivate, see staff count and stock on hand per branch at a glance.
- **Customers & Suppliers** — simple CRUD, reusable pattern.
- **Reports** — date-ranged sales summary, top-selling products, inventory valuation, CSV export.
- **Activity log** — a searchable/filterable timeline of who did what, when.
- **Account settings** — every user can update their own profile (including phone number) and change their own password.
- **Password recovery** — admin accounts can reset their own password by email; every other account gets reset directly by an admin from Users & roles, no email needed.
- **Working hours** — every role except Admin can only use the system 6am–6pm West African Time, enforced at login and continuously during a session.
- **Professional UI** — a real icon set, a notification bell for low-stock alerts, a user menu, toast notifications, custom confirm dialogs (no native browser popups), and client-side sortable/searchable/paginated tables everywhere.

---

## 2. Folder structure

```
computer-nations-bi/
├── config/
│   ├── database.php         # DB credentials (edit this first)
│   └── config.php           # session, constants, loads functions.php
├── includes/
│   ├── functions.php        # helpers: clean(), redirect(), flash messages, CSRF, logActivity(), exportCsv(), sendAppEmail(), working-hours checks
│   ├── rbac.php              # hasPermission(), requireLogin() (also enforces working hours), requirePermission()
│   ├── branch_context.php     # currentBranchId(), setCurrentBranch(), requireActiveBranch(), resolveViewBranchId(), listBranchesByTown()
│   ├── icons.php              # inline SVG icon set — icon('name')
│   ├── header.php             # sidebar + topbar layout, branch switcher (nav items check hasPermission())
│   └── footer.php
├── auth/
│   ├── login.php
│   ├── logout.php
│   ├── forgot-password.php    # admin-only email reset request
│   └── reset-password.php     # sets a new password from a valid emailed token
├── account.php                # self-service profile (incl. phone) + password change
├── switch_branch.php          # topbar branch switcher endpoint
├── dashboard/
│   └── index.php              # KPI widgets, Chart.js charts, low-stock alerts, recent orders
├── modules/
│   ├── branches/                index.php
│   ├── inventory/                 index.php, create.php, edit.php, delete.php, export.php
│   ├── categories/                  index.php
│   ├── stock/                         index.php, adjust.php
│   ├── transfers/                       index.php, create.php, view.php  (same-town; view.php is shared with Expeditions)
│   ├── expeditions/                       index.php, create.php, view.php  (between towns; view.php just includes transfers/view.php)
│   ├── sav/                                 index.php, create.php, view.php  (repair lifecycle + activity timeline)
│   ├── technicians/                           index.php  (repair partners, same pattern as suppliers)
│   ├── orders/                                  index.php, create.php, view.php, print.php, export.php
│   ├── purchasing/                                index.php, create.php, view.php, receive.php
│   ├── customers/                                   index.php   (list + add/edit + delete, one file)
│   ├── suppliers/                                     index.php   (same pattern)
│   ├── users/                                          index.php, add.php, edit.php, roles.php
│   ├── activity/                                         index.php
│   └── reports/                                            index.php, export.php
├── assets/
│   ├── css/style.css          # the whole design system
│   └── js/
│       ├── main.js            # toasts, confirm modals, dropdowns, button loading states
│       └── tables.js          # generic table search/sort/pagination
├── uploads/products/           # product images land here (write-protected against PHP execution)
├── database/
│   ├── schema.sql              # full schema + seed data + 3 sample branches (fresh installs)
│   ├── migrate_v1_to_v2.sql    # adds purchasing/categories/activity to a v1 install
│   ├── migrate_v2_to_v3.sql    # adds branches/transfers/losses to a v2 install
│   ├── migrate_v3_to_v4.sql    # adds towns (groups branches by city) to a v3 install
│   ├── migrate_v4_to_v5.sql    # adds user attribution (created_by/received_by/cancelled_by) to a v4 install
│   ├── migrate_v5_to_v6.sql    # adds phone numbers + admin password-reset columns to a v5 install
│   └── migrate_v6_to_v7.sql    # adds Expedition, SAV, technicians to a v6 install
├── 403.php                      # shown when a logged-in user lacks a permission
└── index.php                     # routes to login or dashboard
```

---

## 3. Setup on XAMPP

1. **Install XAMPP** (if you haven't) from apachefriends.org and start **Apache** and **MySQL** from the control panel.

2. **Copy the project folder** into your XAMPP web root:
   - Windows: `C:\xampp\htdocs\computer-nations-bi`
   - macOS: `/Applications/XAMPP/htdocs/computer-nations-bi`
   - Linux: `/opt/lampp/htdocs/computer-nations-bi`

3. **Create the database.**
   - **New install:** open `http://localhost/phpmyadmin`, click **Import**, choose `database/schema.sql`, click **Go**. This creates the database, sample towns and branches (including two branches in one town, to show the pattern), all tables, roles/permissions, sample data, and a working admin login.
   - **Already have the v6 database (with phone numbers, admin recovery)?** Don't re-import `schema.sql`. Instead import `database/migrate_v6_to_v7.sql`, which adds Expedition, SAV, and Technicians, and automatically classifies your existing transfers as Transfer or Expedition by comparing the towns of their branches.
   - **Already have the v5 database (with user attribution, no phone/reset columns)?** Import `database/migrate_v5_to_v6.sql`, then `database/migrate_v6_to_v7.sql`.
   - **Already have the v4 database (with Towns)?** Import `database/migrate_v4_to_v5.sql`, then the two above.
   - **Already have the v3 database (with Branches, Transfers)?** Import `database/migrate_v3_to_v4.sql`, then the three above.
   - **Still on the v2 database (Purchasing, no branches)?** Import `database/migrate_v2_to_v3.sql`, then the four above, in order.
   - **Still on the original v1 database?** Import `database/migrate_v1_to_v2.sql` first, then everything above, in order.

4. **Set your DB credentials** in `config/database.php` if they differ from the XAMPP defaults (`root` / no password).

5. **Check the base URL** in `config/config.php`:
   ```php
   define('BASE_URL', 'http://localhost/computer-nations-bi/');
   ```
   Change `computer-nations-bi` if you renamed the folder.

6. **Make the uploads folder writable.** On Linux/macOS:
   ```
   chmod -R 755 uploads/
   ```

7. **Open the site**: `http://localhost/computer-nations-bi/`

8. **Log in** with the seeded admin account:
   - Username: `admin`
   - Password: `ComputerNationsBIset`

   **Change this password immediately** — click your avatar (top right) → Account settings → set a new password.

---

## 4. How towns, branches, and transfers work

- **Towns group branches.** A town (e.g. "Douala") can hold as many branches as you need — `branches.town_id` points at a row in `towns`. This is exactly for the common case of several branches in the same city.
- Every branch is a row in `branches` (name, town, address, phone). Stock lives in `branch_stock` — one row per (branch, product) pair, created automatically the first time that product is stocked there.
- Manage both from **Branches** in the sidebar: add a town, then add branches inside it. The page lists branches grouped by town so it stays readable even with many locations.
- A user with a **specific branch assigned** (set on their account under Users & roles) only ever sees and acts on that branch — no switcher, no way to accidentally sell from the wrong location's stock.
- A user with **no branch assigned** (branch_id = NULL — the default for Admin/Manager) gets a **branch switcher** in the topbar, grouped by town: pick one branch to work in, or choose **"All branches"** for a read-only aggregate view across every location (used on the Dashboard, Inventory, Reports, Orders, and Purchase orders lists).
- Actions that change stock — creating an order, adjusting stock, receiving a PO, sending a transfer — always require one concrete branch. If an HQ user has "All branches" selected, these pages fall back to their first available branch and let them switch explicitly.
- **Any user can view stock at any branch, read-only** — the Inventory page has a "Viewing stock at" selector open to everyone, even staff pinned to one branch. This is purely a lookup: it never changes which branch their actual actions (orders, adjustments, receiving) apply to. Behind the scenes this is `resolveViewBranchId()` in `includes/branch_context.php`, kept deliberately separate from `currentBranchId()`.
- **Transfer vs Expedition — same mechanism, split by whether a town boundary is crossed:**
  - **Transfer** moves stock between two branches in the **same town**. Create one from the Transfers page — the destination dropdown only ever lists branches in your source branch's town.
  - **Expedition** moves stock between two branches in **different towns**. Same idea, its own page, destination dropdown only lists branches in *other* towns. Numbers are prefixed `EXP-` instead of `TRF-` and it has its own permissions (`expeditions.view/create/receive`) so you can require different (e.g. more senior) approval for inter-town shipments than for a routine same-town restock.
  - Both share one lifecycle, enforced identically:
    1. **Send** — one action that both creates the record and deducts stock from the source branch immediately. Status is `pending` from that moment, visible on both branches' lists, and it records who sent it (`stock_transfers.user_id`). It **cannot be edited or cancelled** afterward — no draft state, no undo.
    2. **Receive** — only someone at the destination branch (or an HQ user) can confirm receipt. That adds the stock to the destination, flips status to `received`, and records who confirmed it (`stock_transfers.received_by`).
  - Both types live in the same `stock_transfers` table (a `type` column tells them apart) and share one detail page (`modules/transfers/view.php` — `modules/expeditions/view.php` just points at it) so there's only one implementation of the lifecycle to keep correct.
- Every stock-moving action is tagged with a `category` (`opening`, `manual`, `purchase`, `order`, `transfer`, `loss`, `sav`) in `stock_movements`, so the Stock movements page doubles as full traceability — filter by product to see its entire history across every branch it's touched.

---

## 5. SAV — repair tracking for damaged stock

"Products Lost" is gone; damaged or faulty items now go through **SAV** (Service Après-Vente), a proper repair lifecycle instead of an instant write-off:

1. **Report** (`modules/sav/create.php`, or the "Report SAV" link on any product in Inventory) — takes the unit(s) out of sellable stock immediately, same as the old loss flow, but creates a trackable `sav_items` record instead of just a stock movement.
2. **Send to technician** — pick from the **Technicians** list (a simple contacts table for your repair partners), with an optional note. Status becomes `with_technician`.
3. **Receive from technician** — record one of three outcomes:
   - **Repaired** → stock is added back automatically, status becomes `repaired` (terminal).
   - **Still broken** → status goes back to `reported`, sitting at the branch, ready to send out again (to the same technician or a different one) — this loop can repeat as many times as it takes.
   - **Unrepairable** → status becomes `unrepairable` (terminal), permanently written off — the stock was already removed at step 1, so nothing further changes.
4. Every single step — reported, sent, received, returned to stock, written off — is logged to `sav_activities` with who did it and when, so the SAV detail page shows the complete history for that item, not just its current state.

The Inventory list and each product's per-branch breakdown show **good stock and SAV stock as separate numbers** — units currently in the SAV pipeline (`reported` or `with_technician`) are never counted as sellable, and drop out of that count the moment they're resolved either way.

---

## 6. Every operation is attributed to a person

Beyond the activity log (which records every action system-wide), the key records themselves carry "who did this":

| Record | Attribution columns |
|---|---|
| Products, Categories, Suppliers, Customers, Branches | `created_by` — who added it |
| Orders | `user_id` (sold by), `cancelled_by` |
| Purchase orders | `user_id` (raised by), `received_by` (who completed the final receipt), `cancelled_by` |
| Stock transfers | `user_id` (sent by), `received_by` (confirmed receipt) |
| Stock movements (in/out/adjustment/loss/etc.) | `user_id` — always, for every single line |

A few things this enables directly in the UI:
- **Products lost** always shows who recorded the loss, and you can jump straight there from a **"Mark lost"** link on any product row in Inventory (or from the product's edit page) — no need to hunt for it in a dropdown first.
- Order, purchase order, and transfer detail pages show a running line like *"Sent by Aïcha · Received by Paul"* rather than a single anonymous timestamp.
- Reference lists (Categories, Suppliers, Customers, Branches) show an **"Added by"** column.

Records created before this feature was added show `—` for attribution rather than guessing — only new activity going forward is attributed.

---

## 7. How the privilege system works

Nothing in the codebase checks `if ($role === 'Admin')`. Instead:

- Every page starts with `requirePermission('some.slug')`.
- On login, all of a user's permission slugs (derived from their role) are loaded into `$_SESSION['permissions']`.
- The sidebar itself only renders links the user has permission to see (`includes/header.php`), grouped into Overview / Catalog / Sales / Purchasing / Insights / Administration.
- An admin manages this from **Users & roles → Roles & permissions**: pick a role, tick the boxes, save. The screen builds itself from whatever's in the `permissions` table, grouped by module — so it already understands the new Purchasing/Categories/Activity permissions with no extra work.

To add a brand-new permission (say, a future "Expenses" module):
1. Insert a row into `permissions` (`slug`, `label`, `module`).
2. Assign it to whichever roles should have it via the Roles & permissions screen, or directly in `role_permissions`.
3. Wrap the relevant page with `requirePermission('your.new.slug')` and gate any nav link/button the same way.

To add a new role: insert into `roles`, then configure its permissions the same way — no code changes needed.

---

## 8. Passwords, phone numbers, and working hours

- **Phone numbers** — every user account has one (`users.phone`), editable from Add/Edit User, shown in the Users list, and self-editable from Account settings.
- **Password recovery is admin-only, by design.** Regular staff never need it: from **Users & roles → Edit user**, an admin can set a brand-new password for anyone directly — no email, no token, no waiting. That's the primary path for everyone except the admin.
- For the admin account itself (who has no one above them to reset it), there's a **"Forgot your password?"** link on the login page (`auth/forgot-password.php` → `auth/reset-password.php`):
  - It only ever generates a real reset token for accounts with the **Admin** role. Everyone else gets the exact same generic confirmation message ("If that's an administrator account, a reset link has been sent") — the wording never reveals whether an email exists or what role it belongs to.
  - The token is a random 32-byte value; only its SHA-256 hash is stored (`users.reset_token_hash`), and it expires after 1 hour (`users.reset_token_expires`) and is single-use.
  - **This requires outgoing email to actually work.** XAMPP's PHP does not send mail out of the box. To make `sendAppEmail()` (in `includes/functions.php`, a thin wrapper around PHP's `mail()`) actually deliver:
    - **Windows/XAMPP**: easiest is Mercury Mail (bundled with XAMPP, under `xampp-control.exe`) configured with a real outbound SMTP relay, or point `php.ini`'s `[mail function]` section (`SMTP`, `smtp_port`, `sendmail_from`) at an external SMTP provider (Gmail, SendGrid, Mailtrap for testing, etc.).
    - **Linux**: install and configure `sendmail` or `msmtp`, and set `sendmail_path` in `php.ini` accordingly.
    - Until that's configured, `mail()` will silently fail (the app won't error — the person just won't receive anything). Test it by checking your mail server's logs after submitting the forgot-password form.
- **Working hours** — every role except **Admin** can only use the system between **6:00 AM and 6:00 PM West African Time** (`WORKING_HOURS_START` / `WORKING_HOURS_END` in `config/config.php`; WAT is the timezone already set via `date_default_timezone_set('Africa/Douala')`, so no extra conversion happens anywhere).
  - Enforced at **login** — a non-admin can't sign in outside those hours at all.
  - Enforced **continuously** — `requireLogin()` (called by every protected page) checks the clock on every single request, so a non-admin who's mid-session when 6pm hits is signed out automatically on their very next click, not just at their next login.
  - Admin is fully exempt, 24/7, for oversight and emergencies.
  - To change the hours or add exemptions, edit the two constants in `config/config.php`; the check itself lives in `enforceWorkingHours()` in `includes/rbac.php`.

---

## 9. The professional UI layer

A few things worth knowing if you're extending the front end:

- **Icons** — `includes/icons.php` exports one function, `icon('name', $size)`, returning an inline SVG string. Add new icons by adding a `'name' => '<path .../>'` entry to the `$paths` array.
- **Toasts** — instead of a static banner, `setFlash($type, $message)` in PHP now renders a hidden `<div id="flashData">`, which `main.js` picks up on page load and turns into a bottom-right toast. Call `showToast('success'|'error', 'message')` directly from any inline `<script>` if you need to trigger one outside a page reload.
- **Confirm dialogs** — give any form `class="js-confirm" data-confirm-title="..." data-confirm-message="..."` instead of `onsubmit="return confirm(...)"`. `main.js` intercepts the submit, shows a styled modal, and only submits on explicit confirmation.
- **Tables** — every `<table class="data-table">` automatically gets client-side sorting (click a header), pagination (12 rows/page), and a search box — no markup changes needed. If a page already has server-side search (like Inventory), add `data-has-server-search="true"` on the `<table>` to skip the redundant client search box.
- **Charts** — the dashboard loads Chart.js from a CDN (`cdnjs.cloudflare.com`) only on pages that use it. If you're fully offline, download `chart.umd.min.js` into `assets/js/` and change the `<script src>` in `dashboard/index.php`.

---

## 9.5. Deployment, CDN, and scaling

The app is deployed on Railway as a Docker container (PHP 8.3 + Apache) alongside a managed MySQL service — see `Dockerfile` and `docker/entrypoint.sh`. A few things worth knowing if you're scaling this up:

- **CDN**: static assets (`assets/css`, `assets/js`) are served with long-lived, cache-busting-safe `Cache-Control` headers (see `docker/000-default.conf`), which is the prerequisite for any CDN to actually cache them — but Railway doesn't include a CDN layer itself. For real CDN caching of static assets (and DDoS protection, and a second layer of rate limiting), put a free Cloudflare account in front of the Railway domain: point a custom domain's DNS through Cloudflare, proxy it (orange cloud), and its edge will cache the static assets automatically off these headers, no app changes needed.
- **Load balancing / horizontal scaling**: Railway's own edge load-balances automatically across however many replicas a service runs — this isn't something the app needs to implement itself. Replica count is a Railway dashboard/plan setting (Settings → scale on the service), not something exposed through deploys; bump it there if traffic ever needs it, and the existing session/DB setup (MySQL is a separate service, sessions are server-side) already works correctly across multiple replicas with no code changes.
- **Chart.js is self-hosted** (`assets/js/vendor/chart.umd.min.js`) rather than loaded from a CDN — it was fetched from npm's registry and minified locally so its exact contents are known and verifiable, rather than trusting an unpinned third-party hash.

## 10. Security notes (already handled, but worth knowing)

- All queries use PDO prepared statements — no string-concatenated SQL.
- Passwords are hashed with `password_hash()` (bcrypt), never stored in plain text.
- Every state-changing form (create/edit/delete) checks a CSRF token.
- Output is escaped with `htmlspecialchars()` via the `clean()` helper before being echoed.
- The `uploads/products/` folder has a `.htaccess` that blocks PHP execution, so an uploaded file can never run as a script.
- Sessions are regenerated on login to prevent session fixation.
- CSV exports and the print invoice are permission-gated the same as any other page.

Things to add before putting this on a real public server (not needed for local XAMPP use): HTTPS, rate-limiting on the login form, a "forgot password" flow with emailed reset tokens, and moving DB credentials out of version control (e.g. into a `.env` file ignored by git).

---

## 11. Extending it

The Inventory module (`modules/inventory/`) is written as the reference CRUD
pattern: list page with search → create → edit → delete, each gated by its
own permission. The Purchasing module shows the pattern for a multi-step
workflow (draft → ordered → received) with its own state machine. Copy
whichever fits for any new module (e.g. "Expenses", "Repairs"): one folder,
one permission per action, and add the nav link in `includes/header.php`
behind `hasPermission()`.

Everything currently renders as classic multi-page PHP for simplicity and
compatibility with plain XAMPP hosting. If you later want a more app-like
feel (no full page reloads), the natural next step is to expose JSON
endpoints (e.g. `modules/inventory/api.php`) and progressively enhance the
existing pages with `fetch()` — the database layer and permission checks
don't need to change either way.
