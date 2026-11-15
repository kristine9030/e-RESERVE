# Email for e-RESERVE — Gmail SMTP (current setup)

e-RESERVE sends the account confirmation link, "document returned", "ticket ready" and
"approved, no seat left" emails. For now it goes out through a Gmail account over plain SMTP —
no extra package needed. Nothing is delivered until the steps below are done.

> **Limit:** a normal Gmail account can send only about **500 emails per day** (Google Workspace
> about 2,000). Fine for testing and a small batch, **not** enough for ~3,500 students × ~3 emails.
> Before opening sign-ups to everyone, move to a transactional service — see "Moving to Amazon SES
> later" at the bottom.

---

## 1. Get a Gmail App Password

1. Sign in to the sender Gmail account (`MAIL_FROM_ADDRESS`).
2. Turn on **2-Step Verification**: https://myaccount.google.com/security
3. Create an App Password: https://myaccount.google.com/apppasswords
   (name it "e-RESERVE"). Copy the **16-character code** — it is shown once.
   This is **not** the account's normal password.

## 2. `.env`

```
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=<the Gmail address>
MAIL_PASSWORD=<16-character app password, no spaces>
MAIL_FROM_ADDRESS="<the same Gmail address>"
MAIL_FROM_NAME="e-RESERVE · Supreme Student Council"

QUEUE_CONNECTION=database
```
* `MAIL_FROM_ADDRESS` must be the same Gmail account as `MAIL_USERNAME` (Gmail rewrites other senders).
* `MAIL_SCHEME` is `smtp` (port 587) or `smtps` (port 465) — `tls`/`ssl` are invalid.
* Never commit `.env` or paste the app password into chat or tickets.

## 3. Run and test

```
php artisan migrate              # creates the jobs table if missing
php artisan config:clear         # on a server: php artisan config:cache
php artisan queue:work --tries=3
php artisan ereserve:mail-test you@example.com
```
Mail is **queued**, so a queue worker must be running or nothing is sent.
* local: keep `php artisan queue:work` open
* server: `deploy/supervisor-queue.conf`

Check the recipient's Spam folder too. Failed sends: `php artisan queue:failed`, then
`php artisan queue:retry all`.

---

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| `535 Username and Password not accepted` | Normal password used instead of an App Password, or 2-Step Verification is off |
| `Application-specific password required` | Same as above — create the App Password |
| `Daily user sending limit exceeded` | Hit Gmail's ~500/day cap; wait 24 h or move to SES |
| `Connection timed out` | Host blocks outbound 587; use port 465 with `MAIL_SCHEME=smtps` |
| Nothing arrives, no error | `MAIL_MAILER=log`, or `queue:work` is not running |
| Arrives in spam | Normal for a personal Gmail sender; ask students to mark "Not spam" |

## Local development
Keep `MAIL_MAILER=log` and read the messages in `storage/logs/laravel.log`, or run Mailpit
(`MAIL_HOST=127.0.0.1 MAIL_PORT=1025`).

---

## Moving to Amazon SES later

Same app, only `.env` changes (region shown: Sydney `ap-southeast-2`; stay in one region):

1. SES → Identities → verify a **domain** (3 DKIM CNAMEs) or a single email address.
2. Account dashboard → **Request production access** (Transactional, ~10,000 emails/day,
   use case: confirmation and ticket emails for registered students). Takes about a day.
   Until approved: 200/day, verified recipients only.
3. SMTP settings → **Create SMTP credentials** (not your AWS access keys).
4. `.env`:
   ```
   MAIL_HOST=email-smtp.ap-southeast-2.amazonaws.com
   MAIL_PORT=587
   MAIL_USERNAME=<SES SMTP user name>
   MAIL_PASSWORD=<SES SMTP password>
   MAIL_FROM_ADDRESS="<verified sender>"
   ```
5. `php artisan config:clear`, restart `queue:work`, run `ereserve:mail-test`.
