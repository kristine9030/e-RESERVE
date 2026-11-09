# Deploying e-RESERVE to a VPS

Sized for ~3,000 students: **2 vCPU / 2–4 GB RAM**, Ubuntu 22.04/24.04.

## 1. Server packages
```bash
sudo apt update && sudo apt install -y nginx mysql-server supervisor git unzip \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath \
  poppler-utils          # pdftotext (for the planned COR auto-check)
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
```

## 2. PHP settings (`/etc/php/8.3/fpm/conf.d/99-ereserve.ini`)
```ini
upload_max_filesize = 6M      ; > doc_max_bytes (5 MB)
post_max_size       = 8M
memory_limit        = 256M
opcache.enable      = 1
opcache.validate_timestamps = 0   ; run `systemctl reload php8.3-fpm` after each deploy
```
`pm.max_children` in `/etc/php/8.3/fpm/pool.d/www.conf`: about (RAM − 1 GB) / 60 MB. 4 GB → ~40.

## 3. Database
```sql
CREATE DATABASE e_reserve CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ereserve'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT ALL ON e_reserve.* TO 'ereserve'@'localhost';
```
Keep `innodb_buffer_pool_size` ≈ 50% of RAM on a DB-only box, 512M–1G here.
Transactions rely on InnoDB row locks — do not switch to MyISAM.

## 4. App
```bash
cd /var/www && sudo git clone <repo> e-reserve && cd e-reserve
composer install --no-dev --optimize-autoloader
cp deploy/.env.production.example .env && php artisan key:generate   # then edit .env
php artisan migrate --force
php artisan ereserve:make-admin you@g.batstate-u.edu.ph --name="SSC Admin"
php artisan ereserve:make-admin gate1@g.batstate-u.edu.ph --role=scanner --name="Gate 1"   # gate staff: scan only
sudo chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache && php artisan route:cache && php artisan view:cache
npm ci && npm run build        # only if you use the Vite assets
```
The masterlist (`enrolled_students`, 9,373 rows) must exist in the production DB: import it from
a dump of your local `e_reserve` (`mysqldump e_reserve enrolled_students`).

## 5. Nginx, queue, backups, HTTPS
* `deploy/nginx.conf` → `/etc/nginx/sites-available/e-reserve`, then `certbot --nginx -d YOUR-DOMAIN`.
* `deploy/supervisor-queue.conf` → `/etc/supervisor/conf.d/` (sends the notification emails).
* `deploy/backup.sh` + the cron line inside it. **Test a restore.**
* Firewall: `ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw enable`. MySQL stays on 127.0.0.1.

## 6. Email (SMTP) — full guide: deploy/SMTP.md
Fill `MAIL_*` in `.env`, then:
```bash
php artisan config:cache
php artisan ereserve:mail-test you@example.com
```
A normal Gmail/GSuite mailbox has a daily sending cap that ~3,000 students would exceed
(confirmation mail + returned/ticket notices). Use a transactional service (Amazon SES is the
cheapest; Brevo/Mailgun/Resend have free tiers with daily limits) and verify the sender domain/address.

## 7. Updating later
```bash
git pull && composer install --no-dev -o && php artisan migrate --force \
  && php artisan config:cache && php artisan route:cache && php artisan view:cache \
  && php artisan queue:restart && sudo systemctl reload php8.3-fpm
```

## 8. Before opening registration — checklist
- [ ] `APP_DEBUG=false`, HTTPS works, `https://YOUR-DOMAIN/.env` returns 403/404
- [ ] `php artisan ereserve:mail-test` arrives in a real inbox
- [ ] Queue worker running (`supervisorctl status`), failed jobs: `php artisan queue:failed`
- [ ] Event date/capacity in `config/ereserve.php` correct (Nov 26, 2026 · 3,000)
- [ ] Upload a 5 MB PDF end-to-end on the real server (checks nginx/PHP limits)
- [ ] Backup ran once and a restore was tested
- [ ] Load test (see below) run against a staging copy
- [ ] Gate phones tested with the camera scanner over HTTPS (camera needs HTTPS)

## Load testing
Run against a staging copy, never production data:
```bash
# k6 (https://k6.io): 300 virtual users hitting the landing + login pages for 2 minutes
k6 run --vus 300 --duration 2m deploy/loadtest.js
```
The ticket-issue and gate-scan races were already hammered on MySQL (16 parallel processes issuing
tickets for 400 students with capacity 150 → exactly 150, no duplicates; 12 simultaneous scans of one
ticket → exactly 1 admitted).
