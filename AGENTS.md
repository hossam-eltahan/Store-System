# AGENTS.md — Store-System (café cashier system)

Vanilla PHP + MySQL app on XAMPP/Windows. No composer, no framework, no tests, no CI. RTL Arabic UI.

## Runtime (trust this, README is stale)

- Docroot: `C:\xampp\htdocs\Store-System` → `http://127.0.0.1/Store-System/`
- DB: `cafe_management` on 127.0.0.1, user `root`, empty password (see `config/database.php`; port auto-detected from `config/ports.ini`, else 3306). README's `store_management` name is outdated.
- PHP CLI: `C:/xampp/php/php.exe`; MySQL CLI: `C:/xampp/mysql/bin/mysql.exe -u root cafe_management`

## Code conventions

- DB access only via helpers in `config/database.php`: `query / getRow / getRows / insert / execute` (PDO, exceptions logged, `query()` returns `false` on error — check it).
- Every page: `requireLogin()` first, then `requirePermission('area.action')` / `requireAnyPermission()`. Admin bypasses all checks. Permission keys live in `config/auth.php::getAllPermissions()`.
- `config/settings.php` runs on every request: sets timezone `Africa/Cairo`, `BASE_URL` (dynamic), and enforces the offline license via `config/license.php::enforceLicense()`. Do not break that include chain.
- Module layout: `modules/<area>/{index.php, ajax.php, receipt.php}`; shared shell in `includes/header.php` + `includes/footer.php`.
- Receipts: each of `pos/gaming/billiards/tables` has its own `receipt.php` (80mm thermal template). Open them only via `openReceiptModal(url)` from `assets/js/main.js` — never `window.open`.
- Global timer badge/sound polling lives in `assets/js/main.js` → `modules/notifications/ajax.php?action=check_timers`. `APP_BASE_URL` is injected by `includes/footer.php`; `main.js`/`style.css` are cache-busted via `filemtime`, so no manual version bumps.

## Encoding — the #1 footgun

- All PHP files MUST stay UTF-8 (Arabic string literals everywhere).
- PowerShell `Set-Content`/`Out-File` default to ANSI and silently corrupt Arabic into `???`/mojibake. Always write with `-Encoding UTF8`, or generate Arabic via `\uXXXX` escapes from an ASCII-only script.
- After any edit touching Arabic text: `php -l` the file AND `curl` the rendered page, checking for `?` runs or `Ã`/`Ø` mojibake bytes in the output before committing.

## Verify (no test suite exists)

1. `C:/xampp/php/php.exe -l <edited-file.php>` — must pass.
2. Render check: log in via `curl -c jar.txt -d "username=...&password=..." http://127.0.0.1/Store-System/login.php`, then `curl -b jar.txt "<page-url>"` and grep the output for expected labels and mojibake.
3. If you temporarily reset the admin password in MySQL to test, restore the original hash immediately afterwards.
4. Never commit temp scripts (e.g. `database/tmp_*.sql`), `backups/*.sql`, `assets/uploads/*`, or `data/updates/*` — all git-ignored for a reason. Delete scratch files before committing.
