# e-RESERVE

**Student registration and ticketing system**
for **SIGLA: A Student's Night 2026** — a flagship event of the 70th Founding
Anniversary of Batangas State University TNEU – ARASOF Nasugbu.

> Register. Upload. Get your ticket. Scan at the gate.

**Stack:** PHP 8 · MySQL · Bootstrap 5.3 · SweetAlert2 · Font Awesome 6 · DataTables

---

## How a student gets in

```
1. Create account      GSuite address (@g.batstate-u.edu.ph) + email confirmation
2. Upload 3 PDFs       signed waiver · COR · school ID  (≤ 5 MB each)
3. Ticket number       issued automatically when the third file is in
4. Gate                the signed QR on the ticket is scanned once
```

There is **no payment**. One student = one ticket.

**Staff review is a safety net, not a gate.** The ticket exists as soon as all
three files are uploaded. SSC staff then open each PDF in **Review Documents**
and approve it or return it with a note. Returning a document **voids the
ticket** (the QR stops working and the student sees why); when the student
uploads a corrected file the **same ticket number is restored**.

---

## Running it

1. Copy the folder to `C:\xampp\htdocs\e-RESERVE`.
2. Start **Apache** and **MySQL** in XAMPP.
3. Open <http://localhost/e-RESERVE/install.php> and press *Create the database*.
   **This drops and rebuilds every table** — it is meant for a fresh copy or a
   deliberate reset.
4. Open <http://localhost/e-RESERVE/>.

PHP 8.0+ required (`match`, `str_ends_with`, typed declarations).
`uploads/documents` must be writable by Apache.

### Seeded accounts (demo only)

| Account | Role | Password | Starts at |
|---|---|---|---|
| `ssc.admin@g.batstate-u.edu.ph` | SSC staff | `SSC@sigla2026` | Admin dashboard |
| `demo.student@g.batstate-u.edu.ph` | Student | `Student@2026` | Nothing uploaded |
| `demo.almost@g.batstate-u.edu.ph` | Student | `Student@2026` | 2 of 3 uploaded — one PDF from a ticket |

Plus 24 sample students in every state (complete and approved, awaiting review,
document returned, partial, nothing), some already checked in. Their documents
are small generated sample PDFs.

**Before going live:** delete `install.php`, remove the `DEMO_*_PASSWORD`
constants and the demo accounts, and set `MAIL_TRANSPORT` to `smtp` in
`includes/config.php` so confirmation emails really send.

---

## Sign in with Google (optional)

The **Continue with Google** button is real OAuth 2.0, but it needs the SSC's own Google credentials, so it
shows "not switched on yet" until they are added:

1. <https://console.cloud.google.com> → APIs & Services → Credentials → *Create credentials* → **OAuth client ID** → Web application.
2. Authorised redirect URI: `http://localhost/e-RESERVE/google.php` (your real https address once live).
3. Put the client ID and secret in `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` in `includes/config.php`.

Only `@g.batstate-u.edu.ph` / `@batstate-u.edu.ph` accounts are accepted. A first-time Google user is asked
for SR-code, programme and year, and needs no password. Staff always sign in with a password.

## Pages

| Student | |
|---|---|
| `index.php` | Landing page: hero, **pipeline**, detailed **guidelines** (tabbed), schedule, FAQ |
| `register.php` · `verify.php` · `login.php` | GSuite sign-up, email confirmation, sign-in |
| `dashboard.php` | Pipeline progress, next step, document status, ticket |
| `documents.php` | Upload the three PDFs (drag-and-drop, validated in browser *and* server) |
| `waiver.php` | Printable waiver pre-filled with the student's details |
| `ticket.php` | Ticket number + QR (printable) |
| `tracking.php` · `profile.php` | Timeline and profile |

| Staff | |
|---|---|
| `admin/index.php` | Overview: students, tickets, review queue, capacity |
| `admin/documents.php` | **Review queue** — open PDF, approve, or return with a note |
| `admin/registrations.php` | Every student with per-document status chips |
| `admin/checkin.php` | **Gate scan** — camera QR scanner + manual entry, per-gate counts |
| `admin/document.php` | Access-checked PDF viewer (owner or staff only) |
| `reports/` | Registration · Ticket Issuance · Attendance — on screen, print, Excel/CSV |

---

## Data model (`database/schema.sql`)

`users` · `documents` (one row per student per type, `UNIQUE(user_id, doc_type)`)
· `tickets` (`UNIQUE(user_id)`) · `checkins` (`UNIQUE(ticket_id)`) ·
`audit_log` · `sequences`

Business logic lives in `includes/functions.php` (section 5): `submit_document()`,
`sync_ticket()`, `review_document()`.

## Security notes

- **Documents** are validated by content (`%PDF-` + MIME sniff), renamed to a random
  name, stored in `uploads/documents` behind `Require all denied`, and served only
  through `admin/document.php` to the owning student or staff. Opening one as staff
  is written to the audit log.
- **Tickets** carry an HMAC in the QR (`ticket_verify_hash`, key in
  `sessions/app_secret.key`), so a made-up or edited code fails at the gate.
- **One scan per ticket** is enforced by the `UNIQUE(ticket_id)` index on `checkins`;
  **one ticket per student** by `UNIQUE(user_id)` on `tickets`.
- CSRF tokens on every form, rate limiting on sign-in / sign-up / upload, prepared
  statements throughout, email confirmation before any upload.

&copy; 2026 Supreme Student Council · Batangas State University TNEU – ARASOF Nasugbu
*PUSO. DEDIKASYON. TALINO.*
