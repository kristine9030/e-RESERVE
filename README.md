# e-RESERVE

**Online Ticket Reservation and Payment System**
for **SIGLA: A Student's Night 2026** — a flagship event of the 70th Founding
Anniversary of Batangas State University TNEU – ARASOF Nasugbu.

> Reserve. Pay. Track. Celebrate.

---

## Status: working prototype (no database)

Everything runs on **PHP sessions + JSON files**. No MySQL, no Laravel, no React.
Every button, page and workflow behaves as if the system were live.

**Stack:** PHP · HTML5 · CSS3 · Bootstrap 5.3 · JavaScript · SweetAlert2 ·
Font Awesome 6 · DataTables · Montserrat

---

## Running it

1. Copy the `e-RESERVE` folder into `C:\xampp\htdocs\`.
2. Start **Apache** from the XAMPP control panel.
3. Open <http://localhost/e-RESERVE/>.

No installation, no import, no `composer install`. PHP 8.0+ recommended
(the code uses `str_ends_with`, arrow functions and typed declarations).

### Demo accounts

The login page accepts **any** `@g.batstate-u.edu.ph` address — a student
profile is fabricated from the handle. One-click chips are provided for:

| Account | Role | Lands on |
|---------|------|----------|
| `althea.ramirez@g.batstate-u.edu.ph` | Student | Student dashboard |
| `joshua.torres@g.batstate-u.edu.ph` | Student | Student dashboard |
| `ssc.admin@g.batstate-u.edu.ph` | Admin | Admin dashboard |
| `finance.office@g.batstate-u.edu.ph` | Finance | Finance overview |

No password is required in prototype mode.

---

## The demo path (2 minutes)

1. **Landing page** → countdown, VIP/SVIP prices, schedule, FAQ → *Reserve Now*
2. **Login** → pick a student chip
3. **Reserve Ticket** → choose **VIP ₱500** or **SVIP ₱800** → confirmation
   dialog → reservation created
4. **Pay Now** → mock PayMongo/GCash checkout with a live reference number
5. *Complete Payment* → **ticket and receipt generate automatically**
6. **View Ticket** (real, scannable QR gate pass) · **Receipt** (printable) ·
   **Track Reservation**
7. Sign in as **Admin** → the reservation you just made appears at the top of the
   admin table, and its ticket works in **Gate Check-in**
8. **Gate Check-in** → *Start Camera* and hold up the e-ticket QR (from a phone
   or a printout). It decodes, admits the student and the live monitor counters
   update. The page also renders a **Demo Ticket QR** you can scan straight off
   a second screen, and manual entry still works if there is no camera.

---

## Folder structure

```
e-RESERVE/
├── assets/
│   ├── css/style.css        Design system (palette, glassmorphism, components)
│   ├── js/app.js            Countdown, sidebar, DataTables, SweetAlert helpers
│   ├── images/              (docs only — no binary assets needed)
│   └── icons/               (docs only — Font Awesome comes from CDN)
├── includes/
│   ├── config.php           Constants, ticket tiers, session bootstrap, BASE_URL
│   ├── functions.php        ALL storage + business logic lives here
│   ├── qr.php               Real QR code encoder (ISO/IEC 18004) + SVG renderer
│   └── data.php             Sample datasets (the future SQL queries)
├── layouts/
│   ├── head.php             Shared <head> + CDN links
│   ├── public_nav.php       Landing-page navbar
│   ├── public_footer.php    Landing-page footer
│   ├── sidebar.php          Role-aware app sidebar
│   ├── app_header.php       Opens the dashboard shell
│   ├── app_footer.php       Closes it
│   └── scripts.php          Closing scripts + flash-message dispatcher
├── student/                 Forwards to the root-level student pages
├── admin/                   index · reservations · payments · tickets · checkin
├── finance/                 index · transactions · history
├── reports/                 index · reservations · payments · attendance · revenue
├── sessions/                counters.json (auto-created) — the "database"
├── index.php                Landing page
├── login.php  logout.php
├── dashboard.php  reserve.php  payment.php
├── ticket.php  receipt.php  tracking.php  profile.php
└── README.md
```

---

## Design

Palette sampled from the Supreme Student Council key visual: a white canvas,
**deep green**, **gold/amber**, warm orange accents and soft teal spheres.
Typeface is **Montserrat** throughout. Cards are rounded and glassy, the sidebar
is deep green with a gold active indicator, and the whole thing is responsive
down to phone width.

---

## Business rules implemented

- **Two ticket tiers only:** VIP ₱500 and SVIP ₱800. Both are declared in
  `TICKET_TIERS` in `includes/config.php` — the single place a price changes.
  The chosen tier is stored on the reservation and carried through to the
  payment, ticket, receipt and every admin/report table.
- **One student = one ticket.** A second reservation on the same account is
  blocked with *"You already have an active ticket reservation."*
- A reservation may be **cancelled while unpaid**; paid ones fall under the SSC
  refund policy.
- **Payment auto-generates both the ticket and the receipt** — no manual step.
- Ticket numbers follow `SIGLA-2026-000001`; receipts `OR-2026-000001`;
  reservations `RSV-2026-000001`.

---

## Connecting the real backend later

The code was organised so this is additive, not a rewrite.

### MySQL

1. Create `includes/db.php` returning a PDO built from the `DB_*` constants
   already declared in `includes/config.php`.
2. Rewrite the bodies (not the signatures) of these functions in
   `includes/functions.php`:
   `next_sequence()` · `get_reservation()` · `get_payment()` · `get_ticket()` ·
   `create_reservation()` · `cancel_reservation()` · `complete_payment()`
3. Replace each `sample_*()` function in `includes/data.php` with the SQL query
   named in its docblock.

Suggested tables: `tbl_students`, `tbl_events`, `tbl_reservations`,
`tbl_payments`, `tbl_tickets`, `tbl_checkins`.

### PayMongo

`payment.php` carries the full integration outline in its header comment:
create a GCash **source**, redirect to `checkout_url`, then handle
`source.chargeable` in a new `webhooks/paymongo.php` that calls
`complete_payment()` — the same function the mock button calls today.

### QR codes

Already real — nothing to swap. `includes/qr.php` is a dependency-free QR
encoder (ISO/IEC 18004: byte mode, versions 1–15, EC levels L/M/Q/H) that any
phone camera or gate scanner can read.

- `qr_svg($payload, $size)` renders an inline SVG; `qr_data_uri()` gives the
  same code for an `<img src>`; `admin/qr.php` serves it as a standalone image.
- `ticket_qr_payload()` in `includes/functions.php` decides what goes *into* the
  code — currently the ticket number, so a scan is directly actionable.
- `admin/checkin.php` decodes the camera stream with `html5-qrcode` and submits
  the result to the same handler as manual entry. `extract_ticket_code()` also
  accepts `code|student_no`, a verification URL or a JSON payload.

### Google Workspace sign-in

`login.php` validates the `@g.batstate-u.edu.ph` domain and fabricates a
profile. Replace that block with an OAuth 2.0 flow using `hd=g.batstate-u.edu.ph`.

---

## Known prototype limits

- Data lives in the session: signing out or clearing cookies resets everything.
- Admin tables mix sample records with the one live session reservation on
  purpose, so the demo visibly connects the student and admin sides.
- Buttons marked with `data-soon` (exports, email, PDF, notifications, bulk
  actions) intentionally explain themselves via SweetAlert instead of doing
  nothing.

&copy; 2026 Supreme Student Council · Batangas State University TNEU – ARASOF Nasugbu
*PUSO. DEDIKASYON. TALINO.*
