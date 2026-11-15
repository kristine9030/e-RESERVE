# Email for e-RESERVE — Amazon SES (current setup)

e-RESERVE sends the account confirmation link, "document returned", "ticket ready" and
"approved, no seat left" emails, plus a notice to the SSC inbox for every sponsor inquiry.
It uses Amazon SES over plain SMTP — no extra package needed.

Live setup (as done for e-reserve.online):

| | |
|---|---|
| Region | Asia Pacific (Sydney) `ap-southeast-2` — never switch region halfway |
| Sender | `noreply@e-reserve.online` (the whole domain is verified, no mailbox needed) |
| Host / port | `email-smtp.ap-southeast-2.amazonaws.com` · `587` · `MAIL_SCHEME=smtp` |
| Status | Out of the sandbox (production access approved Nov 15, 2026) |
| Queue | `QUEUE_CONNECTION=database`, workers run under Supervisor |

---

## Part 1 — Amazon SES (AWS console)

Identities, the sandbox request and the SMTP credentials are all **per region**.
Open: `https://ap-southeast-2.console.aws.amazon.com/ses/home?region=ap-southeast-2`

### 1. Verify the domain (Configuration → Identities → Create identity → Domain)
Enter the domain, keep *Easy DKIM* ticked, **Create identity**. SES shows 3 CNAME records.

Add them at the DNS host (for e-reserve.online that is **Namecheap → Domain List → Manage →
Advanced DNS → Add New Record**, type *CNAME Record*):

* **Host**: the part **before** `.e-reserve.online`, e.g. `abc123._domainkey`.
  Do **not** paste the full name — Namecheap appends the domain itself and you end up with
  `abc123._domainkey.e-reserve.online.e-reserve.online`, which never verifies.
* **Value**: the target as shown, ending in `.dkim.amazonses.com`.
* TTL: Automatic. Leave the existing A records (`@` and `www`) alone — they point the site at the server.

Check from any terminal (should print a `canonical name = …dkim.amazonses.com`):
```
nslookup -type=CNAME abc123._domainkey.e-reserve.online 8.8.8.8
```
Status turns **Verified** in minutes to a few hours.

### 2. Production access (Account dashboard → Request production access)
Mail type **Transactional**, website `https://e-reserve.online`. Approval took under an hour;
AWS replies by email. Until approved the account is in the **sandbox**: 200 emails/24 h, 1/second,
and only to verified addresses (verify test recipients under Identities → Email address).

### 3. SMTP credentials (SMTP settings → **IAM SMTP credentials** → Create IAM credentials)
Use the *IAM SMTP credentials* card on the right, not "Mail Manager SMTP" (that one has its own
processing charges and a different setup). Accept the suggested user name and group, **Create user**,
and copy the **SMTP user name** (starts `AKIA…`) and **SMTP password** — shown once. These are
not the AWS login or access keys.

**Gotcha — the user is created with no permissions.** Sending then fails with
`554 Access denied: User … is not authorized to perform 'ses:SendRawEmail' …`.
Fix: IAM → Users → the `ses-smtp-user…` → *Permissions* → **Add permissions → Create inline policy**
→ JSON, name it `ses-send`:
```json
{
  "Version": "2012-10-17",
  "Statement": [
    { "Effect": "Allow", "Action": ["ses:SendEmail", "ses:SendRawEmail"], "Resource": "*" }
  ]
}
```

---

## Part 2 — `.env` (on the server: `/var/www/e-reserve/shared/.env`)

```
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=email-smtp.ap-southeast-2.amazonaws.com
MAIL_PORT=587
MAIL_USERNAME=<SES SMTP user name>
MAIL_PASSWORD=<SES SMTP password>
MAIL_FROM_ADDRESS="noreply@e-reserve.online"
MAIL_FROM_NAME="e-RESERVE · Supreme Student Council"

# optional: where new sponsor inquiries are emailed (default: the SSC address in config/ereserve.php)
# SPONSOR_INQUIRY_EMAIL=ssc@example.com

QUEUE_CONNECTION=database
```
* `MAIL_FROM_ADDRESS` must be on the verified domain (or a verified address) in the same region.
* `MAIL_SCHEME` is `smtp` (port 587) or `smtps` (port 465) — `tls`/`ssl` are invalid.
* `.env` is not in git. Edit it on the server, never paste the password into chat or tickets.

## Part 3 — Apply and test (on the server)

```
cd /var/www/e-reserve/current
sudo -u ereserve php artisan config:cache
sudo -u ereserve php artisan queue:restart
sudo -u ereserve php artisan ereserve:mail-test you@example.com
```
Mail is **queued**, so the workers must be running or nothing is sent:
`supervisorctl status` should show both `e-reserve-queue` workers `RUNNING`
(config: `deploy/supervisor-queue.conf`).

Failed sends: `php artisan queue:failed`, then `php artisan queue:retry all`.
Confirmation links stay valid for 24 hours, so retry soon after an outage.

---

## Keeping the account healthy

AWS pauses sending if too many emails bounce or are reported as spam:

| | AWS reviews at | AWS can pause at |
|---|---|---|
| Bounce rate | 5% | 10% |
| Complaint rate | 0.1% | 0.5% |

* Watch **SES → Reputation metrics**, especially on the day registration opens. Both should stay green.
* SES automatically puts a bounced address on its **Suppression list** and never retries it. A student
  who mistyped their mailbox therefore stops receiving mail; if the address was actually correct,
  remove it under **SES → Suppression list**.
* The app does not read bounces yet, so it cannot tell the SSC that an email did not arrive.
* Pricing is about US$0.10 per 1,000 emails — a few dollars for the whole event.
* The AWS account is on the Free Plan with credits until **May 10, 2027**; upgrade the plan before then
  if e-RESERVE must keep running.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| `Email address is not verified` | Sandbox: recipient not verified, or sender not on the verified domain/region |
| `535 Authentication Credentials Invalid` | AWS access keys used instead of the SES **SMTP** credentials, or `MAIL_HOST` region differs |
| `554 … not authorized to perform 'ses:SendRawEmail'` | The SMTP IAM user has no policy — add the inline policy above |
| `Daily message quota exceeded` | Sandbox (200/day) or the production quota is used up |
| `Connection timed out` | Host blocks outbound 587; use port 465 with `MAIL_SCHEME=smtps` |
| Identity stuck on *Verification pending* | DKIM CNAMEs missing or entered with the domain twice — check with `nslookup` |
| Nothing arrives, no error | `MAIL_MAILER=log`, or `queue:work` is not running |
| Arrives in spam | Check SPF/DKIM show Verified on the domain; ask recipients to mark "Not spam" |

## Local development
Keep `MAIL_MAILER=log` and read the messages in `storage/logs/laravel.log`, or run Mailpit
(`MAIL_HOST=127.0.0.1 MAIL_PORT=1025`). A Gmail App Password also works for quick local tests
(`MAIL_HOST=smtp.gmail.com`, port 587), but Gmail caps sending too low to use on the live site —
it ran out of quota within hours of testing.
