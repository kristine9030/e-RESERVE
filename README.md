# e-RESERVE

Student registration and ticketing for **SIGLA: A Student's Night 2026** — BatStateU TNEU · ARASOF-Nasugbu.
The original plain-PHP version is archived in `_legacy/` (not served). Backups: `../_e-RESERVE-backup/`.

**Flow:** sign up (GSuite + SR-code checked against the registrar masterlist) → confirm email →
upload 3 PDFs (waiver, COR, school ID) → ticket + signed QR issued → QR scanned at the gate.

## Setup

```bash
composer install
copy .env.example .env          # then set DB_* (MySQL) — database: e_reserve
php artisan key:generate
php artisan migrate --seed      # tables + the 9,373-student registrar masterlist (no sample data)
php artisan ereserve:make-admin you@g.batstate-u.edu.ph --name="Your Name"   # first staff account
```

Open `http://localhost/e-RESERVE/` (redirects to `/public/`) (XAMPP) or run `php artisan serve`. Set `APP_URL` in `.env` to match.

There is **no demo or sample data**: the database starts with the masterlist and nothing else. Staff accounts
cannot self-register — create them with `ereserve:make-admin` (it prints a generated password once; omit
`--password`). Run the same command again to reset a staff password.

## Features

* **Sign-up** checked against the masterlist (SR-code ↔ college), programme list follows the college, GSuite-only,
  emailed confirmation link, optional Google sign-in.
* **Sign-in** with remember-me, rate limiting, **forgot/reset password** by emailed link, **change password**,
  edit contact number.
* **Documents → ticket**: 3 PDFs, content-checked and stored privately; ticket + signed QR issued on the third.
* **Staff**: dashboard, review queue (approve / return with note — voids and restores the ticket), student list with
  **disable / enable account**, gate scan (camera or typed code, single-use), three reports with print / `.xls` / `.csv`,
  audit trail.
* Privacy Notice and Terms pages.
## Where things are

| | |
|---|---|
| Event, documents, colleges, programmes, sponsors, allowed email domains | `config/ereserve.php` |
| Registrar masterlist (reference data) | `database/data/enrolled_students.sql` → `enrolled_students` table |
| Sign-up rules (SR-code ↔ college check, programme per college) | `app/Services/AccountService.php`, `app/Services/Registrar.php` |
| Documents → ticket → QR, voiding on a returned file | `app/Services/TicketService.php`, `DocumentService.php` |
| Reports (screen, print, `.xls`, `.csv` from one dataset) | `app/Services/ReportService.php` |
| Routes | `routes/web.php` |
| Views | `resources/views/` (`layouts/`, `partials/`, `student/`, `admin/`, `reports/`) |

## Configuration

* **After changing .env, run php artisan config:cache.** Under XAMPP's multi-threaded Apache, reading .env on every request intermittently fails (~1 in 10 requests returns *Server Error 500 — No application encryption key*). The config is therefore cached; .env edits only take effect after re-running that command (php artisan config:clear while developing with php artisan serve).

* **Email** — `MAIL_MAILER=log` writes confirmation and password-reset emails to `storage/logs/laravel.log` (the confirmation link is also shown on screen).
  For real delivery set `MAIL_MAILER=smtp` and the `MAIL_*` values (Gmail/Workspace needs an App Password).
* **Google sign-in** — set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` and (optionally) `GOOGLE_REDIRECT_URI`
  (default `APP_URL/google/callback`; add it as an authorised redirect URI in Google Cloud). Only university
  domains are accepted whatever Google allows.
* **Documents** are stored in `storage/app/private/documents`, never under `public/`, and are served only to their
  owner or to staff through `/documents/{id}/file`.
* **Ticket QR signature** is an HMAC keyed by `APP_KEY` — rotating the key invalidates already-issued QR codes.

## Updating the masterlist

Replace `database/data/enrolled_students.sql` and run `php artisan db:seed --class=EnrolledStudentSeeder`
(it reloads only that table; accounts are untouched).
