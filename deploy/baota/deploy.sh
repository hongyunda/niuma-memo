#!/usr/bin/env bash
# 更新现有宝塔生产站点；首次部署须先准备 shared 配置和独立 PHP 服务。
set -euo pipefail
SERVER="${NIUMA_SSH_HOST:?请设置 NIUMA_SSH_HOST=user@host}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
BASE=/srv/niuma-memo
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
RELEASE="$BASE/releases/$STAMP"

(cd "$ROOT/web" && npm ci --no-audit --no-fund && npm run build)
ssh "$SERVER" "mkdir -p '$RELEASE/api'"
rsync -az --exclude runtime --exclude storage/uploads --exclude .env \
  --exclude vendor --exclude .DS_Store "$ROOT/api/" "$SERVER:$RELEASE/api/"
rsync -az "$ROOT/web/dist/" "$SERVER:$RELEASE/api/public/"

ssh "$SERVER" bash -s -- "$STAMP" <<'REMOTE'
set -euo pipefail
BASE=/srv/niuma-memo
RELEASE="$BASE/releases/$1"
PHP=/www/server/php/82/bin/php
test -f "$BASE/shared/production.env"
test -f "$BASE/deploy/composer.phar"
bash "$BASE/deploy/backup.sh"
ln -s "$BASE/shared/production.env" "$RELEASE/api/.env"
ln -s "$BASE/shared/runtime" "$RELEASE/api/runtime"
mkdir -p "$RELEASE/api/storage"
ln -s "$BASE/shared/uploads" "$RELEASE/api/storage/uploads"
cd "$RELEASE/api"
COMPOSER_ALLOW_SUPERUSER=1 "$PHP" "$BASE/deploy/composer.phar" install \
  --no-dev --optimize-autoloader --no-interaction --no-progress
COMPOSER_ALLOW_SUPERUSER=1 "$PHP" "$BASE/deploy/composer.phar" check-platform-reqs --no-dev
"$PHP" think migrate:run
chown -R www:www "$BASE/shared/runtime" "$BASE/shared/uploads"
PREVIOUS="$(readlink -f "$BASE/current")"
# 保留上一版的静态文件，避免已打开页面加载旧版懒加载模块时出现 404。
if [[ -d "$PREVIOUS/api/public/assets" ]]; then
  cp -an "$PREVIOUS/api/public/assets/." "$RELEASE/api/public/assets/"
fi
rollback() {
  ln -s "$PREVIOUS" "$BASE/current.rollback"
  mv -Tf "$BASE/current.rollback" "$BASE/current"
  systemctl reload niuma-memo-php
  echo "健康检查失败，已恢复上一版代码；数据库备份位于 $BASE/backups/database" >&2
}
ln -s "$RELEASE" "$BASE/current.next"
mv -Tf "$BASE/current.next" "$BASE/current"
if ! systemctl reload niuma-memo-php; then
  rollback
  exit 1
fi
if ! curl --fail --silent --show-error --max-time 20 \
  --resolve memo.example.com:443:127.0.0.1 \
  https://memo.example.com/api/health; then
  rollback
  exit 1
fi
echo
echo "上线完成：$RELEASE"
REMOTE
