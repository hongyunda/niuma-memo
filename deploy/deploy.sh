#!/usr/bin/env bash
# 一条命令发版：本地构建前端 → 同步到服务器 → 装依赖 → 跑迁移
# 用法：SERVER=deploy@memo.example.com REMOTE=/var/www ./deploy/deploy.sh
set -euo pipefail
SERVER="${SERVER:?请设置 SERVER=user@host}"
REMOTE="${REMOTE:-/var/www}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo "▶ 构建前端"
(cd "$ROOT/web" && npm ci --no-audit --no-fund && npm run build)

echo "▶ 同步后端代码"
rsync -az --delete \
  --exclude runtime --exclude storage/uploads --exclude .env --exclude vendor --exclude .DS_Store \
  "$ROOT/api/" "$SERVER:$REMOTE/api/"

echo "▶ 同步前端产物到 api/public"
rsync -az --delete --exclude index.php --exclude router.php --exclude .htaccess --exclude favicon.ico --exclude robots.txt \
  "$ROOT/web/dist/" "$SERVER:$REMOTE/api/public/"

echo "▶ 服务器安装依赖并迁移"
if [[ "${DOCKER:-0}" == "1" ]]; then
  ssh "$SERVER" "cd $REMOTE/deploy && docker compose exec -T php composer install --no-dev -o --no-interaction && docker compose exec -T php php think migrate:run && docker compose restart php cron"
else
  ssh "$SERVER" "cd $REMOTE/api && composer install --no-dev -o --no-interaction && php think migrate:run && (systemctl reload php8.2-fpm 2>/dev/null || true)"
fi
echo "✔ 完成"
