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

## 3. Current Task — DONE except packaging (2026-10-19, after owner feedback rounds)

**Release 1.0.11: customer accounts + owner feedback rounds 1-3 — implemented & E2E-verified on dev DB, NOT yet packaged as an update ZIP.**

Feedback rounds 2-3 shipped on top of the base feature (commit history = base `aa7e224` + this round):
- All "🖨️ بون" buttons in `customers/view.php` now open `openReceiptModal()` (POS/gaming/billiards receipt in an iframe modal) instead of a page link.
- "💵 تحصيل عام من الرصيد" is now a modal (`cstCollectOpen/cstCollectSubmit`) calling NEW ajax action `collect_general` in `modules/customers/ajax.php` — mirrors `pay_customer.php` exactly (sequential `payments` row `PAY-#####`, balance += collected, installment plans applied, logActivity; NO cash movement — legacy parity), validates ≤ debt, then opens `../payments/print_customer.php?id=N` in the receipt modal + reload.
- "المبلغ المحصل" inputs no longer rewrite their own value on every keystroke (that made typing jumpy/dots impossible): each page now has a `userX()` wrapper (`userCoPaid`/`userEndPaid`/`userBilliardPaid`/`userTablePaid`) that only sets the touched flag + calls the note-only updater (pos `updateCoPaid`, gaming `updateEndPaid`, billiards `updateBilliardPaid`, tables `updateTablePaid`, profile `cstUpdateRemain`). Clamping happens at submit only. Sync paths (`updateCoNetTotal`, `updateEndNetTotal`, `updateBilliardNet`, `updateTableDiscount`) unchanged semantics via untouched flag.
- محاسبة الكل/المحدد/واحد → after settle success, `modules/customers/settlement_receipt.php?id=<id>` (new compact-print page copied from `modules/pos/receipt.php` thermal 80mm template, renders `customer_settlements.per_item` JSON: per-item label/ref/date/total/applied/left_after + totals + billed/collected/remaining boxes) opens in `openReceiptModal` before reload.
- **Receipt modal no longer auto-closes / reloads behind the user**: `openReceiptModal(url, onClose)` now takes a callback, and `closeReceiptModal()` fires it AFTER the user closes → `location.reload()` only then (profile settle/collect + list collect). Print modal stays open until the user closes it.
- **Keyboard Arabic digit typing everywhere**: money inputs switched `type=number` → `type=text inputmode=decimal` + shared `normDigitInput(el)` in `assets/js/main.js` (converts ٠-٩/۰-۹→0-9, collapses `٫ ، \u066b ,` to one dot incl. leading dot ".2", strips `\u066c` thousands sep; clamp at submit only). Wired in POS (coPaid + discount, userCoPaid), gaming (endPaid + userEndPaid + both updateEndNetTotal), billiards, tables, profile (cstAmountInput + cstCollectAmount `data-max`), customers/index (collectAmount).
- **Thermal receipt look unified**: `modules/payments/print_customer.php` + `modules/customers/settlement_receipt.php` restyled to copy `modules/pos/receipt.php` exactly (80mm, Cairo/monospace, dashed dividers, meta-table, grand-total row, `receipt_header`/`receipt_footer` settings, no-print bar closing via `window.parent.closeReceiptModal`).
- **ثالثاً: كل خنّات البحث سماحية في المشروع كله** ("احمد" يجيب "أحمد"، "حمزه"="حمزة"، "مضمون"="مضمونة"): PHP helpers `mb_search_normalize()` + `search_like_expr($col)` (REPLACE-chains: أإآٱ→ا، ة→ه، ىیئ→ي، ؤ→و، حذف تطويل/تشكيل/أرقام عربية→لاتينية) added in `config/settings.php`; JS mirror `normSearchText()` in `main.js`. Applied server-side in: customers index+ajax picker+view items filter, pos `get_products`, inventory (2×), products (cats/mods/items), suppliers, payments, expenses, invoices list (4 blocks) + invoices ajax (search + 3 pickers), recipes (3 blocks); client-side in: main.js product filter, 3 hall live searches, reports entity filter, pay_customer/pay_supplier, invoices sale/edit_sale/edit_purchase/purchase pickers. Verified via curl (temp customers `أحمد دلوعة`/`حمزة مضمونة` matched by typed `احمد`/`حمزه` and Arabic-digit phone ٠١٠, then deleted).
- **Profile timeline shows payments too**: profile فواتير list now merges `customer_settlements` (🧾 محاسبة مجمعة، N عنصر، بون → settlement_receipt) + `payments` rows (💵 تحصيل عام من الرصيد، طريقة؛ settlement-engine duplicates excluded via `reference NOT LIKE 'STL-%'`) as green event rows (`.pr-row-event`), same search/date-filter, each with بون button. NOT counted in checkbox pool/dues.
- **customers/index 💵 visibility fix**: open-due was computed only when `balance < 0` (zero-balance + open invoice → no 💵). Now computed unconditionally via `customer_account_stats` per customer; 💵 shows when open_due>0 or balance<0.

Balance-ledger clarity in profile money cards: when `customers.balance` differs from `-open_total` (old/manual ledger money not tied to any open invoice), the card shows an explicit explainer + a "⚖️ مطابقة الدفتر مع الفواتير" button (permission `customers.edit`) → NEW ajax action `sync_balance` sets balance = -open_total, logs `مطابقة دفتر عميل` in activity_log + writes an `audit_logs` row (`balance_reconcile`, old/new). The -28.50 the owner saw is such an orphan: E2E reproduction (due → due → settle-all on a scratch customer) returned the balance to 0.00 exactly, proving checkout/settle math is not the cause.
- NOTE clean-up round 2: leftover test rows from the previous session were found and removed (orphan `cash_movements` 143=`7.63` from the deleted test settle, missed `payments` row PAY-00002 for customer 4, settlements/records/logs of both scratch rounds). Shift 4 NOW restored to (606.36 total_sales_cash / 30.83 gaming). Only real rows remain: customer 2 (owner), orders 140/141/144, payments 7+11, settlement 5, activity 207/208.

Verification log (rounds 2-3): `php -l` clean on all 22 touched PHP files + settings/main.js; rendered-JS parse clean on 8 pages (js_check2.js all none); thermal checks pass (80mm + labels both receipts); digit-normalizer cases ALL PASS (incl. leading dot ".2", lone ".", "٠١٠" phone); curl render checks pass, no mojibake; profile timeline verified live (PAY-00002 + STL-20261019-8865 rows correct, no duplicates); customers index re-check: 💵 present with data-owed=28.50 for zero-balance customer; scratch normalization customers deleted after test.

Verification log (this round): `php -l` clean on all 7 touched files; rendered-JS parse clean on the 6 pages (+ settlement receipt + print customer); curl render checks pass, no mojibake; fresh E2E on dev DB: scratch customer → POS checkout `paid_amount:0` JSON via curl → balance -34.20 → second → -68.40 → settle-all → balance 0.00 (checkout/settle math PROVEN correct); `collect_general` (10 of 20 → payments row PAY-00003 -20→-10, over-owed rejected, second 10 → 0, print receipt page renders 6110 bytes); `sync_balance` (-100 → 0 with audit row, repeat correctly says متطابق). ALL test data deleted afterwards; shift totals restored; temp scripts to be deleted before commit.

Next: package v1.0.11 update ZIP when the owner asks (include: `config/customer_accounts.php`, `assets/js/customer_picker.js`, `modules/customers/{ajax.php,view.php,settlement_receipt.php}`, `modules/{pos,gaming,billiards,tables}/index.php`, `modules/pos/ajax.php`, migration `017_customers_linking.sql` in migrations[], + `modules/gaming/ajax.php`, `modules/billiards/ajax.php`, `modules/tables/ajax.php` from the base round), bump `config/version.php` after building. NOTE: hall pages still require `assets/js/customer_picker.js` and `config/customer_accounts.php` shipped alongside.
New/changed (base round, commit `aa7e224`):
- Migration `017_customers_linking.sql` (applied to dev DB, logged row 17): nullable `customer_id` + index on `orders`, `gaming_sessions`, `billiard_sessions`, `table_sessions`; new `customer_settlements` table; careful backfill (phone+name, then exact unique name only, fills NULLs only).
- New engine `config/customer_accounts.php`: `findOrCreateCustomer` / `resolveCustomerInput` (typed name anywhere → auto-registers), `customer_all_items` (orders + completed PS/billiard bills), `customer_open_items`, `customer_account_stats`, `settle_customer_items` (collected amount distributed FIFO oldest-first; writes payment_records per source + ONE cash movement + payments receipt `PAY-#####` + `customer_settlements` row; bumps `customers.balance`; negative-balance = debt convention kept).
- Customers profile rebuilt (`modules/customers/view.php`): money cards, unified invoice/bill list with search + date filter (default = today, "كل التواريخ" toggle), pagination 20, per-row بون/تعديل-ارجاع (POS edit mode)/محاسبه, checkbox selection → "محاسبة المحدد" or "محاسبة الكل" (all open, every date), settlement amount dialog (partial collection allowed, remainder stays open). الأقساط tab removed; "فاتورة بيع جديدة" → `../pos/index.php?customer_id=N`.
- `customers/index.php`: open-dues column (computed from sources), delete-guard against linked orders/sessions.
- `customers/ajax.php`: new actions `get`, `get_dues`, `settle`, `merge_add` (quick customer creation).
- POS (`modules/pos/index.php` + `ajax.php`): customer picker on name input (existing/new/clear + balance badge, `assets/js/customer_picker.js` shared file), hidden `customerIdInput`, checkout modal got "المدفوع" input (default = final), partial < final requires a customer (typed name auto-registers server-side), server resolves/creates customer, session-bound orders inherit the session's customer_id, deep-link `?customer_id=` pre-selects the customer, edit mode returns `customer_id`; deferred orders + full-pay behavior unchanged.
- Halls (gaming/billiards/tables index+ajax): start-session customer name inputs are pickers storing session `customer_id`; end/close modals got "المحصل الآن" input + customer field (defaults to session customer); partial collection requires an attached customer else explicit Arabic error; collected amount (not total) is written to payment_records/cash_movements/shift totals; remainder → customer debt (`balance -=`) + logActivity; gaming/billiard sessions store `paid_amount`=collected; tables close distributes the collected amount FIFO across that session's unpaid orders (keeps per-order remainders as the trail) and carried amounts go straight to balance.
- `installer_build/fresh_install.sql`: appended section 37 (same DDL as 017, no backfills) + schema_migrations seed row for 017 so fresh installs ship the new schema.

Verification log: `php -l` clean on all 9 touched PHP files; rendered-page JS parse clean (POS/gaming/billiards/tables/profile/customers-list); curl render checks pass (new inputs present, no mojibake); live E2E on dev DB: quick customer creation → POS partial order (total 34.20 paid 10 → order partial/customer linked, balance -24.20, payment_record 10) → dues list exact → engine settle 5 (FIFO, PAY-00001, cash movement, shift +5) → settle-all (order paid 34.20, balance 0, dues empty) → gaming session partial (total 8.63 paid 1.00 → balance -7.63, dues show بون بلايستيشن #41) → engine settle of gs:41 full → balance 0. ALL test data deleted and shift 4 restored byte-exact (566.46 / 30.83); temp scripts deleted.

Next: package v1.0.11 update ZIP when the owner asks (files above + 3 unchanged hall `ajax.php` unnecessary now — include what changed only; migration 017 must be in `migrations[]`), bump `config/version.php` after building. NOTE: hall pages still require the new `assets/js/customer_picker.js` and `config/customer_accounts.php` shipped alongside.

**Release 1.0.10 (shipped): hall live search + password-gated dashboard figures.**
- Commit `6bfc246`: live filter (client-side, no new queries) on the 3 hall pages (tables/billiards/gaming) — matches table/device `display_name` OR seated `customer_name`; empty-state message; survives the 15s auto-refresh; timers guard missing elements.
- Commit `45be54b`: dashboard financial figures masked server-side (`index.php` no longer computes/echoes today's queries) + per-card eye 🙈 → own-password dialog (reuses `appDialog*` helpers) → reveal via new `modules/dashboard/ajax.php?action=verify_and_get` (password_verify against session user, 5 fails → 60s lock per session, `logActivity` audit on reveal).
- Update ZIP `StoreSystem_Update_v1.0.10_20261019_013311.zip` (49.8KB, 8 files, 0 migrations, HMAC VALID, min_version 1.0.8, byte-verified + Arabic/mojibake-checked inside) in output/ + Desktop; version.php → 1.0.10.
- Included `modules/{tables,billiards,gaming}/ajax.php` even though unchanged since 1.0.9 — so customers still on 1.0.8 get `customer_name` in hall payloads and customer-search works on first install.

- Update v1.0.9 shipped (reset-submit fix): ZIP `..._175405` HMAC VALID (107 files + 16 migs, fixes byte-verified inside), version.php → 1.0.9, old 1.0.8 ZIP retired from output/; pushed as `99a1a33`. Build driver bug (missing files/migrations POST lines) caught by alert check and fixed.

**Release 1.0.8 shipped: GitHub pushed, update ZIP + setup EXE built, customer-data safety proven.**
- `StoreSystem_Update_v1.0.8_*.zip` (457KB, 107 files, 16 migrations, HMAC signature VALID, min_version 1.0.0) + `StoreSystem_Setup_v1.0.8.exe` (93.7MB, Inno Setup 7).
- Data-safety proof on TRUE 1.0.4 schema + fake customer rows via the REAL updater engine: 16/16 migrations applied, every row intact (users/products/suppliers/invoices/orders/sessions/settings values preserved, warehouse backfill + company carry-over + settings rename all correct) → DATA_SAFE=YES.
- Found & fixed 3 real blockers for old customers: (1) migration 006 seed INSERT assumed modern settings table (removed — code fallback covers it); (2) settings key/value→setting_key/setting_value rename never migrated (new 016); (3) fresh_install.sql internally inconsistent (old settings CREATE + modern INSERTs — fresh installs were broken; fixed + verified 44 tables import clean).
- Data-preservation guarantee proven: 005 rewritten to INSERT IGNORE; audit of all 16 migrations — no DELETE/DROP anywhere. Proof on customer-shaped DB: CUSTOMER_DATA_SAFE=YES. Final update ZIP `..._151338` in output/ + Desktop.
- Installer 1.0.8 REBUILT 16:50 (same version, fixed packaging): root cause of empty POS = stale portable staging; full re-sync (193 identical), staged fresh_install re-proven (142 products), .iss Arabic fixed + BOM, ISCC success (98.2MB). Exe in output/ + Desktop.
- Factory-reset fix (`882eefd`): confirmSubmit used shadowed form.submit — fixed with native submit + hidden do_reset marker; proven on scratch (96/96, 142 products back); patched into C:\StoreSystem too.
- Update v1.0.9 shipped (reset-submit fix): ZIP `..._175405` HMAC VALID (107 files + 16 migs, fixes byte-verified inside), version.php → 1.0.9, old 1.0.8 ZIP retired from output/; pushed as `99a1a33`. Build driver bug (missing files/migrations POST lines) caught by alert check and fixed.
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
