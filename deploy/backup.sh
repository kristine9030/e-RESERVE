#!/usr/bin/env bash
# Nightly backup of the database and the uploaded documents.
#   chmod +x deploy/backup.sh
#   crontab -e  →  30 2 * * * /var/www/e-reserve/deploy/backup.sh >> /var/log/e-reserve-backup.log 2>&1
# Credentials come from ~/.my.cnf ([client] user=… password=…), never from this file.
# Copy /var/backups/e-reserve off the server too (rclone / rsync / provider snapshots),
# and TEST A RESTORE before the event.
set -euo pipefail

APP=/var/www/e-reserve
OUT=/var/backups/e-reserve
STAMP=$(date +%Y%m%d-%H%M)
KEEP_DAYS=14

mkdir -p "$OUT"
umask 077

mysqldump --single-transaction --quick e_reserve | gzip > "$OUT/db-$STAMP.sql.gz"
tar -czf "$OUT/documents-$STAMP.tar.gz" -C "$APP/storage/app/private" documents 2>/dev/null \
  || tar -czf "$OUT/documents-$STAMP.tar.gz" -C "$APP/storage/app" documents

find "$OUT" -type f -mtime +$KEEP_DAYS -delete
echo "[$(date -Is)] backup ok: $STAMP"

# Restore:
#   gunzip < db-YYYYMMDD-HHMM.sql.gz | mysql e_reserve
#   tar -xzf documents-YYYYMMDD-HHMM.tar.gz -C /var/www/e-reserve/storage/app/private
