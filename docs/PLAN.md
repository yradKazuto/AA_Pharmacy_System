# AA Pharmacy System — Project Plan

Single-location pharmacy web app for Zamboanga City: staff portal + customer online ordering, one shared database.

## Overview

**App name:** AA Pharmacy System  
**Location:** Zamboanga City  
**Users:** Admin, Pharmacist, Cashier (staff); Customers (online portal)  
**Technology:** PHP 8.x (simple MVC, no framework), MySQL 8 via PDO, Bootstrap 5, Chart.js, Fetch API

---

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.x, simple custom MVC (no framework) |
| Database | MySQL 8 via PDO (prepared statements only) |
| Frontend | HTML/CSS/JS, Bootstrap 5, Chart.js, Fetch API |
| Auth | PHP sessions + `password_hash()` / `password_verify()` |
| AuthZ | Custom RBAC (roles: admin, pharmacist, cashier; customers for the online portal) |
| Server | Apache + XAMPP locally, Git |

---

## Directory Structure

```
AA_Pharmacy_System/
├── app/
│   ├── controllers/
│   ├── models/
│   ├── services/
│   ├── middleware/
│   └── views/
├── config/
├── public/                  ← only public/ is web-accessible
│   ├── css/
│   ├── js/
│   └── images/
├── routes/
├── database/
│   ├── create_db.php        ← run first: creates the aa_pharmacy DB
│   ├── migrate.php          ← run second: creates schema (roles, users, audit_logs)
│   ├── seed.php             ← run third: seeds roles + admin user
│   └── (migrations/ legacy) ← inert Laravel-style stubs; use create_db/migrate/seed above
├── storage/
│   └── uploads/             ← uploaded files (gitignored, only .gitkeep tracked)
└── docs/
    └── PLAN.md             ← this file
```

### Database provisioning (run in order)
```bash
php database/create_db.php
php database/migrate.php
php database/seed.php
```

Default admin login: `admin` / `Admin@1234` (COMPANY_ID: `ADMIN-0001`)

---

## Business Rules (never break these)

1. Expired batches **must never** be sold or reserved.
2. Track stock **by batch** (lot number, expiry, quantity, supplier, cost).
3. **FEFO**: always pick the earliest valid expiry first.
4. Every stock change creates an `inventory_movements` row.
5. A completed sale and its inventory deduction happen in **ONE database transaction**; roll back on any failure.
6. Purchasing only **recommends** reorders; it never auto-purchases.
7. Sensitive staff functions are **role-restricted** and checked server-side.
8. Online orders needing professional review must be **approved by the pharmacist** (and physician when flagged) before fulfillment.
9. Collect only customer data needed for **legitimate business purposes**.
10. Reports use **actual transaction and inventory data only**.

---

## Proposal Requirements

- Every staff/admin account has a unique **Company ID Number** (users table; used for audit tracking).
- Password recovery is handled through the **administrator** (reset by admin, not self-service email).
- Online orders and POS sales feed the **same** sales/inventory tables.

---

## Security

- CSRF tokens on **all forms**, escape all output, validate input server-side.
- Regenerate session ID on login; secure session cookie settings (httponly, strict same-site).
- Write to `audit_logs` for: logins, failed logins, stock adjustments, voids, role changes.
- Role-based access control enforced server-side in every controller.
- No secrets in committed code (credentials via `config/database.php`, non-sensitive defaults only).

---

## Build Order (commit after each phase)

1. **Phase 1** ✅ — Project setup + DB schema/migrations, Auth, roles, audit logging
2. **Phase 2** — (reserved)
3. **Phase 3** ✅ — Products, batches, inventory, FEFO, expiry alerts
4. **Phase 4** ✅ — Suppliers, purchasing, receiving
5. **Phase 5** ✅ — POS (point-of-sale)
6. **Phase 6** ✅ — Reports + dashboard
7. **Phase 7** ✅ — Online ordering + delivery
8. **Phase 8** — Integration and security testing

---

## Phase Summaries

| Phase | Status | Commit | Summary |
|---|---|---|---|
| 1 — Auth, Users, Roles | ✅ Done + Pushed | `b633182` on `dev` | Login, RBAC, audit, Company ID, admin password recovery; verified end-to-end |
| 2 — Reserved | ⬜ Not started | — | — |
| 3 — Products/Batches/Inventory | ✅ Done + Pushed | `5b505ff` on `main` | Product/Batch/Inventory CRUD, FEFO StockService, expiry alerts; 22-check tests; fixed Router redirect + RoleMiddleware role_name bug |
| 4 — Suppliers/Purchasing | ✅ Done + Pushed | `dev` | Suppliers CRUD, PurchaseOrders + line items, reorder suggestions (recommend-only), whole-PO receiving in ONE transaction; 31-check tests |
| 5 — POS | ✅ Done + Pushed | `dev` | POS terminal, FEFO batch deduction, sales + sale_items (batch per line), cash/GCash/card, change, void-restores-stock; sale + deductions in ONE transaction; 34-check tests |
| 6 — Reports/Dashboard | ✅ Done + Pushed | `dev` | Per-role dashboards (admin/pharmacist/cashier) with KPIs + Chart.js weekly trend; sales report, inventory valuation, movement log (all from actual data, rule 10); 14-check tests |
| 7 — Online Ordering | ✅ Done + Pushed | `dev` | Customer portal: catalog, localStorage cart → checkout, COD; status flow pending_review → approved → fulfilled → delivered (approve/reject/fulfill/deliver/cancel); Rx items force pharmacist review (rule 8); fulfillment deducts stock FEFO + records a linked sale in ONE transaction (rules 4, 5, 10); customers see only their own orders (rule 9); 35-check tests; fixed `pending_review → rejected` transition bug |
| 8 — Integration/Security | ⬜ Not started | — | — |

---

## Do NOT Build (v1)

- Multi-branch locations
- Demand forecasting
- AI/medical decision support
- Auto-purchasing
- Route optimization
- Mobile app
- Microservices

---

## Working Style

- Before starting a phase, summarize what you'll build and **wait for OK**.
- Write tests for: login/permissions, expired-batch blocking, FEFO selection, sale + stock deduction, transaction rollback, purchase receiving.
- Keep code simple and readable; this is a **student project that must be explainable**.
- Use migrations for all schema changes; provide seeders with sample data.
- Commit after each phase (see branch strategy below).

---

## Git Branch Strategy

```
main     ← stable releases (merge dev → main after each phase)
dev      ← working branch (all development happens here)
```

- All work on `dev`.
- Commit after each phase with message: "Phase N: <description>"
- After Phase N is verified: merge `dev` → `main` and push both.
- Never commit config with real secrets.
