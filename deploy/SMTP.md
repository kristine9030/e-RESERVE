# Setting up email (SMTP) for e-RESERVE

e-RESERVE sends: the account confirmation link, "document returned", "ticket ready" and
"approved, no seat left". Until SMTP is set, `MAIL_MAILER=log` writes mail to
`storage/logs/laravel.log` and **nothing is delivered**.

## Option A — the SSC Google mailbox (recommended: no domain needed)

Uses `sscnasugbu@g.batstate-u.edu.ph` over Gmail SMTP. Mail really comes from Google, so it passes
SPF/DKIM and lands in `@g.batstate-u.edu.ph` inboxes (same Workspace).

1. Sign in to that mailbox → **Google Account → Security → 2-Step Verification → turn on**.
2. **Security → App passwords** → name it `e-RESERVE` → copy the 16-character password.
   *If "App passwords" is missing, the school's Workspace admin (ICT) has disabled it — ask them to
   allow it for this account, or use Option B.*
3. In `.env` (no spaces in the password):
   ```
   MAIL_MAILER=smtp
   MAIL_SCHEME=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=sscarasofnasugbu.ereserve@gmail.com
   MAIL_PASSWORD=abcdefghijklmnop
   MAIL_FROM_ADDRESS="sscarasofnasugbu.ereserve@gmail.com"
   MAIL_FROM_NAME="e-RESERVE"
   ```
   The From address must equal the username; Gmail rewrites anything else.
4. `php artisan config:clear` then `php artisan ereserve:mail-test your.name@g.batstate-u.edu.ph`
5. Check the inbox (and Spam). Done.

**Limit:** about **2,000 recipients per day** per Workspace mailbox. With ~3,000 students that is fine
if sign-ups spread over a few days, but not if everyone registers on day one. Beyond the cap Google
rejects mail temporarily, so keep the queue on (`QUEUE_CONNECTION=database` + `queue:work`) so failed
sends are retried rather than lost.

## Option B — a transactional provider (needs a domain you control)

Amazon SES (cheapest, ~$0.10 per 1,000), Brevo, Mailgun, Resend, SendGrid. You must verify a
**sender domain** (add DNS records: SPF, DKIM). You cannot do that for `batstate-u.edu.ph` yourself —
ICT would have to add the records — so this only helps if the SSC has its own domain or ICT cooperates.
Never send "from" `@g.batstate-u.edu.ph` through a third party without that DNS setup: Google will
treat it as spoofing and send it to spam or reject it.

Use the provider's SMTP host/user/password and `MAIL_SCHEME=smtp` with port 587
(or `smtps` with 465). `tls` / `ssl` are not valid values for `MAIL_SCHEME`.

## Local development
Keep `MAIL_MAILER=log` and read the messages in `storage/logs/laravel.log`, or run Mailpit/Mailtrap
(`MAIL_HOST=127.0.0.1 MAIL_PORT=1025`).

## Queue
Notifications are queued. Set `QUEUE_CONNECTION=database` and run the worker:
* local: `php artisan queue:work`
* server: `deploy/supervisor-queue.conf`
With `QUEUE_CONNECTION=sync` (the current local default) mails are sent immediately inside the
request, which works but makes the admin's Approve click wait for SMTP.

Failed sends: `php artisan queue:failed` and `php artisan queue:retry all`.

## Troubleshooting
| Symptom | Likely cause |
|---|---|
| `Username and Password not accepted (535)` | Used the normal password instead of the App Password, or 2-Step is off |
| `Connection timed out` | Host blocks outbound 587; try port 465 + `MAIL_SCHEME=smtps`, or ask the VPS provider |
| `The "tls" scheme is not supported` | `MAIL_SCHEME=tls`: change to `smtp` (587) or `smtps` (465) |
| Arrives in Spam | Option B without SPF/DKIM, or From address differs from the SMTP user |
| Nothing arrives, no error | `MAIL_MAILER=log`, or the queue worker is not running |
