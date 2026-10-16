# Session Handoff

## 1. Project Overview

- **Project:** Store-System — café cashier/management system (vanilla PHP, no framework/composer + MySQL/MariaDB via PDO, RTL Arabic UI, 80mm thermal receipts). No test suite, no CI.
- **Runtime (trust this, README is stale):** Docroot `C:\xampp\htdocs\Store-System` → `http://127.0.0.1/Store-System/`. DB `cafe_management` @ 127.0.0.1 root/empty (port from `config/ports.ini`, else 3306). Branch `cafe-system`, HEAD `1f2c6c5`, pushed.
- **Versions:** `APP_VERSION 1.0.7` (`config/version.php`), `DB_SCHEMA_VERSION 3`, migrations `001`–`013` in `database/migrations/`.
- **Live-data rule:** the dev copy at `C:\xampp\htdocs\Store-System` is the owner's live test env — never touch their rows (their invoices `PUR-00001/2/6`, session 27, supplies, return #3). Open shift id 2 is live — reverse any test cash movement. Test admin hash `$2y$10$UaTK6e2Qo1mEuhLHRabvt.ZF4/2EE00MC1NQgWc57rO08grQ5nbzK` (`admin123`); always restore the original hash byte-identically afterward. Delete all scratch `zz_*` files when done.

## 2. Architecture (verified in code)

- **Layout:** `modules/<area>/{index.php, ajax.php, receipt.php}`; shared shell `includes/header.php` + `includes/footer.php`; `config/settings.php` runs on every request (timezone `Africa/Cairo`, `BASE_URL`, license enforcement — do not break that chain).
- **DB access:** only via `config/database.php` helpers (`query/getRow/getRows/insert/execute`); `query()` returns `false` on error — check it.
- **Auth:** every page `requireLogin()` then `requirePermission()/requireAnyPermission()`; admin bypasses. Keys in `config/auth.php::getAllPermissions()`.
- **Stock engine** (`config/settings.php`, verified functions present): `recordProductMovement` (append-only `product_stock_movements` ledger), `addProductStock`/`removeProductStock`, `approvePurchaseInvoice` (exactly-once via status check + row locks), `createInvoiceReturn` (per-line caps incl. cumulative returns), purchase-to-stock conversion via per-product purchase unit + factor.
- **Key semantics:** `track_stock` per product (purchases always add; sales deduct only when tracked; `prevent_negative_stock` default warn-only); purchase invoices draft (no movement) vs approved; POS checkout deducts inside transaction + `client_key` idempotency; POS order edit = in-place reverse+reapply with auto-refund (`REF` records), blocked on cancelled orders / closed sessions; session billing 15-min minimum pro-rata; receipts via `openReceiptModal()` only, never `window.open`.
- **Navbar:** compact grouped (categories merged as products tab, supplies merged as inventory tab).
- **Ship vehicle:** portable self-contained installer + `StoreSystem_Update_*` ZIP; VC++ runtime installs on normal (non-silent) installs only. `config/.dev_license` is dev-only: untracked, git-ignored, excluded from installer.

## 3. Current Task — DONE (verified 2026-10-16)

**Release 1.0.8 shipped: GitHub pushed, update ZIP + setup EXE built, customer-data safety proven.**
- `StoreSystem_Update_v1.0.8_*.zip` (457KB, 107 files, 16 migrations, HMAC signature VALID, min_version 1.0.0) + `StoreSystem_Setup_v1.0.8.exe` (93.7MB, Inno Setup 7).
- Data-safety proof on TRUE 1.0.4 schema + fake customer rows via the REAL updater engine: 16/16 migrations applied, every row intact (users/products/suppliers/invoices/orders/sessions/settings values preserved, warehouse backfill + company carry-over + settings rename all correct) → DATA_SAFE=YES.
- Found & fixed 3 real blockers for old customers: (1) migration 006 seed INSERT assumed modern settings table (removed — code fallback covers it); (2) settings key/value→setting_key/setting_value rename never migrated (new 016); (3) fresh_install.sql internally inconsistent (old settings CREATE + modern INSERTs — fresh installs were broken; fixed + verified 44 tables import clean).
- Data-preservation guarantee proven: 005 rewritten to INSERT IGNORE (warehouse seed + stock backfill never overwrite customer rows; exactly-one-default without touching theirs); full audit of all 16 migrations — only fills NULLs/empties, repairs exact corruption signatures, or adds missing seeds via IGNORE. No DELETE/DROP anywhere. Proof on customer-shaped DB: CUSTOMER_DATA_SAFE=YES. Final ZIP `..._151338` in output/ + Desktop; pushed.
- Modifiers master (`modifiers.php`) merged as third tab `?tab=mods` in products/index (same table → cashier window stays linked); old file is a redirect stub.
- Ingredients row button renamed 🚚 توريد مباشر + optional unit price with weighted-average cost update; products rows gained 🚚 توريد button deep-linking inventory supply modal auto-open (`?supply_product=`).
- Invoices `type=returns` filter fixed (parts now gated on exact type).
- `users/sessions.php` paginated (20/page, filter-preserving).
- Navbar: group labels hidden when collapsed (new `.sidebar-group`); nav scrollbar forced to the right edge (direction trick).
- Customer name banner on busy session cards (all 3 halls, identical style — amber bar, 15px extrabold); start modals already had the input in all halls. Verified `customer_name` present in card payloads.

## 4. Last Changes (all on `cafe-system`, uncommitted)

| Commit | What |
|---|---|
| `1f2c6c5` | grouped boxes screen-only, merged table on print |
| `25ae007` | per-order groups with edit buttons in session receipts |
| `b68fd29` | per-order edit buttons with return-to-settlement (`back=` param) |
| `72efb7d` | empty-cart edit fully cancels order with refund |
| `d0ff998` | POS receipt links session settlement bill for linked orders |
| `c392b17` | services (`is_service`) not returnable; manual-service over timed-session warning |
| `9ba19e6` | auto-refund on edit overpay; block edits on closed sessions, returns on open ones |
| `5fe0601` | capped per-line sale/purchase returns + history + receipts (current returns impl; `a1a7265`/`fbc31f1` in-place attempt was reverted — history intentional, do not "fix") |
| `f3aa757` | unified invoices ledger with POS rows + in-POS order editing |
| `09c0454`/`5dbcf9f` | navbar regroup (18 entries); categories→products, supplies→inventory |

## 5. Tests Run (actual results, 2026-10-16)

- Batch verified: mods tab renders + CRUD works (created/deleted ZZTESTSAUCE, 0 left); old modifiers.php 302-redirects; type=returns page shows only RTN rows (PUR- hits are source-ref column); sessions page "صفحة 1 من N"; supply prefill auto-open present; ingredient purchase 1000×0.60 → avg exactly 0.375 then fully reversed; remember-me cookie issued (Max-Age 30d) → cookie-only dashboard+ajax 200 → logout clears token and cookie dies (302).
- Transfer round 2 (previous): all directions executed with full reversal (baselines matched); billiards-intra success unexecuted (no free pair); owner's live session 29 shows `transferred` (owner-side, untouched by tests).
- `php -l` clean everywhere; `node --check main.js` clean; zero native dialogs; all files byte-clean; password restored; scratch deleted.

## 6. Known Bugs / Unresolved

- Browser sound autoplay / Notification permission need real user gesture — can't verify headlessly.
- `README.md` stale (wrong DB name `store_management`) — trust `config/database.php`.
- `SESSION_HANDOFF.md` was stale pre-1.0.6 before this rewrite.

## 7. Next Steps

1. If shipping, confirm version bump then build `StoreSystem_Update_*` ZIP + `StoreSystem_Setup_*.exe` and validate signature as done for 1.0.6.
2. Still untested in a real browser: pretty dialogs, transfer modals/buttons, unified cards, mods tab, remember-me checkbox, 80mm print output.

## 8. Playbook (must-follow)

- PHP files must stay UTF-8 — never write Arabic through PowerShell (mangles to `?`); write Arabic SQL via UTF-8 files sourced into mysql.
- After any edit with Arabic: `php -l` the file + curl-render and scan for `?` runs / `Ã`/`Ø` mojibake.
- Never commit `database/tmp_*.sql`, `backups/*.sql`, `assets/uploads/*`, `data/updates/*`, `zz_*` scratch.
- Confirm with the user before any destructive remote git action (force-push precedent exists).
