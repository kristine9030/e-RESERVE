#!/usr/bin/env bash
# Nightly backup of the database, the uploaded documents and the sponsor logos.
#   sudo install -m 755 deploy/backup.sh /usr/local/sbin/e-reserve-backup
#   sudo crontab -e  →  30 2 * * * /usr/local/sbin/e-reserve-backup >> /var/log/e-reserve-backup.log 2>&1
# Credentials come from ~/.my.cnf ([client] user=… password=…), never from this file.
# Copy /var/backups/e-reserve off the server too (rclone / rsync / provider snapshots),
# and TEST A RESTORE before the event.
set -euo pipefail

SHARED=/var/www/e-reserve/shared   # files that outlive each release (see deploy/e-reserve-deploy)
OUT=/var/backups/e-reserve
STAMP=$(date +%Y%m%d-%H%M)
KEEP_DAYS=14

mkdir -p "$OUT"
umask 077

mysqldump --single-transaction --quick e_reserve | gzip > "$OUT/db-$STAMP.sql.gz"

# Student documents and sponsor logos; either folder may not exist yet.
files=()
[ -d "$SHARED/storage/app/private/documents" ] && files+=(storage/app/private/documents)
[ -d "$SHARED/public/uploads" ] && files+=(public/uploads)
if [ ${#files[@]} -gt 0 ]; then
    tar -czf "$OUT/files-$STAMP.tar.gz" -C "$SHARED" "${files[@]}"
fi

find "$OUT" -type f -mtime +$KEEP_DAYS -delete
echo "[$(date -Is)] backup ok: $STAMP"

# Restore:
#   gunzip < db-YYYYMMDD-HHMM.sql.gz | mysql e_reserve
#   tar -xzf files-YYYYMMDD-HHMM.tar.gz -C /var/www/e-reserve/shared
