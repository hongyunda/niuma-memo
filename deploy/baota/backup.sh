#!/usr/bin/env bash
set -euo pipefail
BASE=/srv/niuma-memo
BACKUPS="$BASE/backups/database"
mkdir -p "$BACKUPS"
chmod 700 "$BACKUPS"
SNAPSHOT="$BACKUPS/memo-$(TZ=Asia/Shanghai date +%F-%H%M%S).sql.gz"
mysqldump --defaults-extra-file="$BASE/shared/mysql.cnf" \
  --single-transaction --no-tablespaces --set-gtid-purged=OFF \
  niuma_memo | gzip > "$SNAPSHOT.tmp"
mv "$SNAPSHOT.tmp" "$SNAPSHOT"
chmod 600 "$SNAPSHOT"
find "$BACKUPS" -type f -name 'memo-*.sql.gz' -mtime +30 -delete
echo "Database backup complete: $SNAPSHOT"
