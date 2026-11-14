# Email for e-RESERVE — Amazon SES (SMTP)

e-RESERVE sends: the account confirmation link, "document returned", "ticket ready" and
"approved, no seat left". It talks to Amazon SES over plain SMTP, so no extra package is needed.
Until the SMTP values are filled in, nothing is delivered.

## 1. In AWS (once)

1. **Pick a region** (top right), e.g. Asia Pacific (Singapore) `ap-southeast-1`.
   Identities, the sandbox and SMTP credentials are all **per region** — stay in the same one.
2. **SES → Configuration → Identities → Create identity.**
   * **Domain** (recommended): add the 3 DKIM CNAME records SES shows to your DNS. Wait until it says *Verified*.
   * **Email address** (works, but lands in spam more often): click the link SES emails you.
   You can only send From an address/domain that is verified here.
3. **Leave the sandbox:** SES → *Account dashboard* → **Request production access**
   (mail type *Transactional*, website = the e-RESERVE URL, use case = "account confirmation and ticket
   notices for ~3,000 registered students of one university event"). Takes about a day.
   In the sandbox you can only send to verified addresses, 200 mails/day.
4. **SES → SMTP settings → Create SMTP credentials → Create user.** Copy the **SMTP username** and
   **SMTP password** now — the password is shown once. (These are *not* your AWS access keys.)

## 2. In `.env`

```
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=email-smtp.ap-southeast-1.amazonaws.com   # same region as above
MAIL_PORT=587
MAIL_USERNAME=<SES SMTP username>
MAIL_PASSWORD=<SES SMTP password>
MAIL_FROM_ADDRESS="no-reply@yourdomain.com"         # must be a verified identity
MAIL_FROM_NAME="e-RESERVE · Supreme Student Council"

QUEUE_CONNECTION=database
```

`MAIL_SCHEME` must be `smtp` (port 587) or `smtps` (port 465) — `tls`/`ssl` are not valid.

## 3. Run it

```
php artisan migrate            # creates the jobs table if it is missing
php artisan config:clear       # (config:cache on the server)
php artisan queue:work --tries=3
php artisan ereserve:mail-test you@example.com
```

Mails are queued, so a **queue worker must be running** or nothing is sent:
* local: keep `php artisan queue:work` open
* server: `deploy/supervisor-queue.conf`

Failed sends: `php artisan queue:failed`, then `php artisan queue:retry all`.

## 4. Troubleshooting

| Symptom | Likely cause |
|---|---|
| `Email address is not verified` | From address/domain not verified in this region, or you are still in the sandbox |
| `535 Authentication Credentials Invalid` | Used AWS access keys instead of SES **SMTP** credentials, or wrong region host |
| `Connection timed out` | Host blocks outbound 587; use port 465 with `MAIL_SCHEME=smtps` |
| Nothing arrives, no error | `MAIL_MAILER=log`, or `queue:work` is not running |
| Arrives in spam | Verify a domain (SPF/DKIM) instead of a single address |

## Local development
Keep `MAIL_MAILER=log` and read the messages in `storage/logs/laravel.log`, or run Mailpit
(`MAIL_HOST=127.0.0.1 MAIL_PORT=1025`).
