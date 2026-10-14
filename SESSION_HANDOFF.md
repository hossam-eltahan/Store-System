# Session Handoff

## 1. Project Overview

- **Project name:** Store-System (café cashier / management system; README still describes the older home-appliance-store version and is stale)
- **Purpose:** Local café POS and session management: cashier orders (POS), PlayStation sessions, billiards sessions, hall tables sessions, invoices, installments, inventory/warehouses, suppliers, customers, shifts/safe, reports, backups — all with an RTL Arabic UI and 80mm thermal receipts
- **Tech stack:** Vanilla PHP (no framework, no composer) + MySQL/MariaDB via PDO + plain CSS/JS. No test suite, no linter, no CI
- **Environment:** XAMPP on Windows. Docroot `C:\xampp\htdocs\Store-System` → `http://127.0.0.1/Store-System/`. DB `cafe_management` @ 127.0.0.1, user `root`, empty password (port auto-detected from `config/ports.ini`, else 3306). Branch: `cafe-system`

## 2. Architecture

- **Main directories:**
  - `modules/{pos,gaming,billiards,tables,notifications,invoices,products,customers,suppliers,installments,reports,settings,users,shifts,payments,expenses,categories,recipes,about}/` — each module is typically `index.php` + `ajax.php` + `receipt.php`
  - `config/` — `database.php` (PDO + helpers), `auth.php` (permissions), `settings.php` (runs on every request: timezone, BASE_URL, license enforcement), `license.php`, `version.php`
  - `includes/` — `header.php`, `footer.php` (injects `APP_BASE_URL`, cache-busted `main.js`), `notifications.php`
  - `assets/js/main.js` — global timer poller + `openReceiptModal()` receipt preview modal
  - `database/` — `schema.sql`, `cafe_schema.sql`, `migrations/` (`001_baseline.sql`, `002_session_timer_notifications.sql`), seeds
- **Important files:** `index.php` (dashboard), `login.php`, `assets/js/main.js`, `includes/footer.php`, `modules/notifications/ajax.php` (`check_timers`), `modules/gaming|billiards|pos|tables/receipt.php`, `AGENTS.md` (agent playbook)
- **Database schema (verified tables):** `orders`, `order_items`, `payment_records` (`source_type` = `order` / `gaming_session` / `billiard_session`), `gaming_sessions`, `gaming_devices`, `billiard_sessions`, `billiard_tables`, `users`, `user_permissions`, `user_sessions`, `settings`, `products`, `warehouses`, `warehouse_stock`, `invoices`, `activity_log`
- **Important dependencies:** none external (no composer/npm). Runtime depends on XAMPP Apache + MySQL + PHP

## 3. Current Status

- **Completed features:**
  - Extend-time works for expired/paused PlayStation and billiards countdown sessions (extend accounts for ongoing pause duration)
  - Global timer-expiry alerts: `check_timers` covers active + paused expired sessions; `assets/js/main.js` polls every 5s from any page, updates badge/dropdown, plays sound + browser notification
  - Dashboard compacted (no vertical scroll): removed duplicate POS button, latest 3 orders, active PS + billiards sections
  - All receipts unified to the tables-bill base style: 80mm, Arabic labels, previewed in `openReceiptModal()` iframe modal (no leftover windows), auto-print only for top-level tabs
  - PlayStation + billiards bills include: order number, cashier, order count, café items grid (الصنف/العدد/السعر/الإجمالي, single-line totals via `white-space:nowrap`), end time (`غير منتهية` when open), prepaid / paid-at-close / remaining-due (المتبقي للتحصيل), payment method
  - Billiards bill rebuilt to be exactly like the PlayStation bill (only session rows differ: الطاولة/نظام الحساب/المدة or per-game counts)
- **Current implementation:** receipts read cafe orders + `payment_records` per session; remaining due = total − prepaid café − paid-at-close
- **Working features:** verified via `php -l` + `curl` rendered-output checks (labels present, zero mojibake bytes)
- **Known bugs/limits:**
  - Browser sound autoplay / Notification permission require real user interaction — cannot be fully verified headlessly
  - `README.md` is outdated (wrong DB name `store_management`, old paths) — trust `config/database.php` instead

## 4. Current Task

- **What we are working on:** nothing in flight — working tree is clean (only untracked `AGENTS.md`, created this session, not yet committed)
- **Exact file/function:** n/a (last touched: `modules/gaming/receipt.php`, `modules/billiards/receipt.php`)
- **Expected result:** next session should do real-browser verification (timer alerts/sound from the POS page, receipt modal buttons) and an 80mm printer test

## 5. Decisions and Constraints

- **Decisions already made:**
  - All bills follow the tables-bill layout/font/sections; only session-specific rows may differ
  - Receipts open only via `openReceiptModal(url)`; `window.open` bill popups were removed
  - Extend endpoints must handle paused + already-expired sessions; `check_timers` must include paused-expired sessions
  - Branch was once reset to `adc9963` with force-push; old history survives on local branch `backup-before-revert`
- **Requirements that must not change:** Arabic UTF-8 output on every receipt; remaining-due + payment-method rows on session bills; permission checks on every page
- **Coding conventions:** DB only via `query/getRow/getRows/insert/execute`; `requireLogin()` first, then `requirePermission()/requireAnyPermission()`; admin bypasses checks
- **Security requirements:** do not break the `config/license.php::enforceLicense()` chain in `config/settings.php`; local-only system (never expose publicly); restore the real admin password hash immediately after any temporary reset for testing

## 6. Last Changes

- **Files modified:** `modules/gaming/receipt.php`, `modules/billiards/receipt.php`
- **What changed:**
  - `6c7faf9` — PS & billiards bills match table-bill details (order no., cashier, order count, end time, remaining due, payment method)
  - `8e6797d` — billiards bill rebuilt exactly like the PlayStation bill; removed triple-nested `meta-table`, duplicated order/cashier/count rows, duplicated end-time row, duplicated totals block with garbled labels; fixed default end-time label `غير منتهية`; also fixed the same double-`<table>` nesting bug in the gaming bill
- **Tests performed:** `C:/xampp/php/php.exe -l` on both files; `curl` render of `gaming/receipt.php?id=17` and `billiards/receipt.php?id=1` after admin login; byte-scan for mojibake
- **Test results:** lint clean; order-number / end-time / remaining-due / payment-method rows render; **0 mojibake bytes** in both outputs

## 7. Next Steps

1. Real-browser test: expire a timer and confirm alert badge + sound + browser notification fire from the POS page (needs user gesture for autoplay permission)
2. Print POS / gaming / billiards / tables receipts on an 80mm printer and in the in-app modal; confirm café item totals stay on one line
3. Decide whether to commit `AGENTS.md` (currently untracked) and push on `cafe-system`

## 8. Important Notes

- **Unresolved questions:** none blocking — only the browser-permission behavior (autoplay/notifications) depends on real user interaction
- **Relevant commands:**
  - `C:/xampp/php/php.exe -l <file>`
  - `curl.exe -c jar.txt -X POST "http://127.0.0.1/Store-System/login.php" -d "username=admin&password=..."`
  - `curl.exe -b jar.txt "http://127.0.0.1/Store-System/modules/<mod>/receipt.php?id=<N>"`
  - `C:/xampp/mysql/bin/mysql.exe -u root cafe_management`
- **Anything that must be verified before editing:**
  - Keep every PHP file UTF-8 — PowerShell `Set-Content` defaults to ANSI and corrupts Arabic; use `-Encoding UTF8` or `\uXXXX` escapes
  - Never commit scratch files (`database/tmp_*.sql`), `backups/*.sql`, `assets/uploads/*`
  - If tester reports a stale bill after a change, hard-refresh with `Ctrl+F5` (static assets are `filemtime`-busted, but receipt HTML may sit in browser cache)
