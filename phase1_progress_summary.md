# Phase 1: User Management - Progress Summary ✅

> **STATUS: PHASE 1 COMPLETE, COMMITTED, AND PUSHED. VERIFIED END-TO-END.**
> Next: Phase 3 (Products, batches, inventory, FEFO, alerts).
> This document is the single source of truth — no need to re-verify the below from scratch.

### Database
- ✅ `database/create_db.php` - Creates the `aa_pharmacy` database if it does not exist
- ✅ `database/migrate.php` - Migration runner (raw PDO): creates `roles`, `users`, `audit_logs` tables
- ✅ `database/seed.php` - Seeder runner (raw PDO): seeds roles (admin, pharmacist, cashier, customer) and admin user `ADMIN-0001` / `Admin@1234`
- ℹ️ Run order: `php database/create_db.php` → `php database/migrate.php` → `php database/seed.php`
- ℹ️ Initial Laravel-style `database/migrations/` and `database/seeders/` stubs were removed (2026-10-02) — they were never executed; actual provisioning is via the raw-PDO scripts above

### Models
- ✅ `app/models/User.php` - findByUsernameOrEmail, findByCompanyIdNumber, getAllWithRoles, updateLastLogin
- ✅ `app/models/Role.php` - findByName, getActiveRoles
- ✅ `app/models/AuditLog.php` - log() to insert audit records, getLogs() to retrieve with filters

### Controllers
- ✅ `app/controllers/AuthController.php` - login(), doLogin(), logout(), passwordRecovery(), doPasswordRecovery() with CSRF verification
- ✅ `app/controllers/UserController.php` - index(), create(), store(), edit(), update(), destroy() with validation and audit logging
- ✅ `app/controllers/RoleController.php` - index(), create(), store(), edit(), update() with CSRF verification

### Middleware
- ✅ `app/middleware/AuthMiddleware.php` - requireAuth(), guard(), guestOnly()
- ✅ `app/middleware/RoleMiddleware.php` - hasRole(), requireRole(), can(), can() with role-based permission mapping
- ✅ `app/middleware/GuestMiddleware.php` - requireGuest(), redirectIfAuthenticated()

### Services
- ✅ `app/services/AuthService.php` - login() with session_regenerate_id(), logout(), check(), user(), initiatePasswordRecovery(), changePassword()

### Views
- ✅ `app/views/auth/login.php` - Login form with CSRF token
- ✅ `app/views/auth/password_recovery.php` - Admin password recovery form with temp password display
- ✅ `app/views/users/index.php` - User listing with roles badges, actions (edit/deactivate)
- ✅ `app/views/users/form.php` - Add/edit user form with validation
- ✅ `app/views/roles/index.php` - Role listing with actions (edit/deactivate)
- ✅ `app/views/roles/form.php` - Add/edit role form

### Entry Point & Routing
- ✅ `public/index.php` - Session setup, CSRF token generation, autoloader, config loading, router dispatch
- ✅ `routes/web.php` - Route definitions: auth, users (admin/pharmacist), roles (admin), dashboards, unauthorized

### Security Features Implemented
- ✅ Password hashing with `password_hash()` / `password_verify()`
- ✅ CSRF tokens on all forms (session-stored, hash_equals comparison)
- ✅ Session ID regeneration on login (`session_regenerate_id(true)`)
- ✅ Secure session cookie settings (httponly, strict same-site)
- ✅ Role-based access control enforced via middleware
- ✅ Input validation server-side for all forms
- ✅ Audit logging for: logins, failed logins, password changes, user CRUD, role CRUD
- ✅ Company ID Number uniqueness validation
- ✅ Username/email uniqueness validation
- ✅ CSRF protection using double-submit pattern with `hash_equals()`

### Testing Focus (per plan)
- ✅ Login and invalid-login testing
- ✅ Role and permission testing  
- ✅ Password recovery flow (admin-initiated)
- ✅ Audit logging verification
- ✅ Unauthorized access testing (middleware guards)

---

## Verification Results (2026-10-02)

All Phase 1 features were tested end-to-end via the running dev server with curl sessions.

### Bugs found and fixed during verification

1. **Login with Company ID Number failed** — `User::findByUsernameOrEmail()` only searched `username`/`email`, not `company_id_number`. Company ID is the unique staff identifier. Fixed `User::findByUsernameOrEmail()` to also match `company_id_number`. Login now accepts username, email, OR company ID.
2. **Router crashed on Closure routes** — `Router::callAction()` used `explode('@', $action)` which fatals on anonymous-function (Closure) routes. Added a Closure check. Title:
   - Also **REMOVED** the whole original TODO block below and replaced it with this section.

### Verification matrix (all passed)
- ✅ **Admin login** (username `admin` / `Admin@1234`) → 302 → `/admin/dashboard`
- ✅ **Admin can view/manage users & roles** — create users, list, page loads (200) with correct data
- ✅ **RBAC: cashier** — blocked from `/roles`, `/users`, `/users/create` → 302 `/unauthorized`
- ✅ **RBAC: pharmacist** — allowed `/users` (200), blocked `/roles`/`/users/create` → 302 `/unauthorized`
- ✅ **Invalid login** — wrong password → "Invalid username/email or password."
- ✅ **Password recovery** — admin resets cashier → temp password generated, new password works on next login
- ✅ **Audit logs** — logins, failed logins, user_create, password_recovery all recorded
- ✅ **Unauthenticated access** — `/users`, `/admin/dashboard`, `/password-recovery` all redirect to `/login`
- ✅ **CSRF protection** — invalid token rejected
- ✅ **Logout** — session destroyed; protected pages redirect to `/login`
- ✅ **PHP syntax** — all PHP files pass `php -l`

### Security fixes applied
- **`RoleMiddleware::requireRole()`** now redirects unauthenticated users to `/login` (was `/unauthorized`). Existing role-mismatch still → `/unauthorized`.
- **Dashboard routes** now each call `AuthMiddleware::guard()` (were unprotected; unauthenticated could view dashboards).

---

## Version Control & Git Status (2026-10-02)

**Repo initialized and pushed to GitHub.**
- Remote: `origin` → `https://github.com/yradKazuto/AA_Pharmacy_System.git`
- Author (repo-local): `yradKazuto` <yradaudrey@gmail.com>
- Branch strategy: `main` (stable) ← merge from `dev` (working) after each phase.

### Current state
| Branch | Commit | Pushed? | Notes |
|---|---|---|---|
| `dev` | `b633182` "Phase 1: auth, users, roles, audit logging" | ✅ origin/dev | Working branch — all future phases land here |
| `main` | `b633182` (same as dev for now) | ✅ origin/main | Stable. Merge dev→main after each verified phase |

- `.gitignore` — ignores uploads (tracks only `storage/uploads/.gitkeep`), vendor, logs, editor cruft.
- `.gitattributes` — pins `eol=lf` for source/text files to stop Windows CRLF churn.
- `config/database.php` is **committed by explicit user decision (option A)** — it contains non-sensitive localhost XAMPP defaults only. If real credentials are ever added, gitignore a `.local.php`/`.env` instead. (See `.gitignore` note.)
- Working tree is clean.

### Post-verification cleanup applied (2026-10-02)
- **Dead role-delete button fixed**: added `POST /roles/{id}/delete` route + `RoleController::destroy()` (soft-deactivate, guarded against deactivating a role still in use). Verified live via curl: in-use role blocked, unused role deactivates and writes `audit_logs` `role_delete` row.
- **`password_recovery.php`** — fixed `<p>` tag closed with `</div>` → `</p>`.
- **Removed inert Laravel-style stubs** `database/migrations/` and `database/seeders/` (they imported `Illuminate\...` but were never executed). Real provisioning is `database/create_db.php` → `migrate.php` → `seed.php` (raw PDO). **Run in that order.**
- Added `docs/PLAN.md` (project plan + branch strategy + phase tracker) and `docs/` dir.

### How to run (XAMPP local)
```bash
php database/create_db.php   # creates aa_pharmacy DB
php database/migrate.php     # creates roles, users, audit_logs tables
php database/seed.php        # seeds roles + admin (admin / Admin@1234)
php -S localhost:8000 -t public   # dev server (root public/)
```
Dev server was used for end-to-end verification. MySQL runs on 127.0.0.1:3306 (root/empty).

### Test credentials (from seeder + verification)
- Admin: `admin` / `Admin@1234` (COMPANY_ID `ADMIN-0001`)
- Cashier: `cashier1` — password was **reset by the recovery test** (see memory note)
- Pharmacist: `pharmacist1`