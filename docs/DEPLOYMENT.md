# 部署与配置

## Docker Compose

先准备一台有 Docker Compose、Node.js 20+ 的服务器、自己的域名和 HTTPS 证书。

1. `cp api/.example.env api/.env`；生成至少 32 字节的随机 `JWT_SECRET`。
2. `cp deploy/.env.example deploy/.env`；分别设置 `MYSQL_ROOT_PASSWORD`、`MYSQL_PASSWORD`。后者同时填入 `api/.env` 的 `DB_PASS`；使用 `DB_HOST=mysql`、`DB_NAME=niuma_memo`、`DB_USER=memo`。
3. 将 `APP_URL` 改为自己的 HTTPS 站点地址，本地存储可用 `UPLOAD_STORAGE=local`、`UPLOAD_ACCEL=true`。
4. 修改 `deploy/nginx.conf` 中两个 `server_name`；证书放入 `deploy/certs/fullchain.pem` 与 `privkey.pem`。

```bash
(cd web && npm ci && npm run build)
rsync -a web/dist/ api/public/
cd deploy
docker compose up -d --build mysql php
docker compose exec php composer install --no-dev --optimize-autoloader
docker compose exec php composer check-platform-reqs --no-dev
docker compose exec php php think migrate:run
docker compose exec php php think user:create laowang laowang --nickname=老王
docker compose exec php sh -c 'mkdir -p runtime storage/uploads && chown -R www-data:www-data runtime storage/uploads'
docker compose up -d nginx cron
```

上述初始化命令创建默认账号 `laowang` / 默认密码 `laowang`，登录后请在设置中修改密码。

先安装依赖和初始化数据库，再启动定时任务容器，避免首次启动时后台任务找不到依赖或数据表。`api/runtime` 和 `api/storage/uploads` 需要运行 PHP 的用户可写。

访问 `https://你的域名/api/health` 检查服务；再登录并测试创建记录、上传 / 下载附件。健康接口只是进程检查，不能替代业务验证。保存好数据库卷、上传目录、环境配置及证书的私有备份。

## 宝塔 / 裸机

安装 PHP 8.2+、Composer、MySQL 8 和需要的扩展；部署源码、配置 `api/.env`、安装依赖、构建前端并复制到 `api/public`、执行迁移和创建用户。

网站根目录只能指向 `api/public`。将 `deploy/nginx.conf` 的路由规则适配到自己的站点，修改 PHP-FPM socket、证书路径与附件 `internal` 路径。`deploy/crontab` 使用 `/var/www/api` 示例路径，替换为实际路径及 PHP 可执行文件。

`deploy/baota/` 另提供独立 PHP 服务及原子发版模板，默认示例目录为 `/srv/niuma-memo`：

```text
/srv/niuma-memo/
  releases/                 每次发版的独立目录
  current -> releases/...   当前版本
  shared/production.env     自己的后端配置
  shared/mysql.cnf          备份用 MySQL 客户端配置，权限 600
  shared/runtime/           运行目录
  shared/uploads/           附件目录
  deploy/                   服务配置、backup.sh、composer.phar
  backups/database/         私有数据库备份
```

这是后续更新模板，需要先完成初次部署并建立 `current`、shared 目录、PHP 服务、Nginx 和数据库。运行前逐项修改：

- `deploy/baota/deploy.sh`：本地与远端的 `BASE`、PHP 路径、健康检查域名、服务名。
- `backup.sh`：目录与数据库名；MySQL 密码只存放在 `shared/mysql.cnf`。
- `php-fpm.conf`、`niuma-memo-php.service`、`niuma-memo.cron`、`nginx-locations.conf`：目录、系统用户、socket 和 PHP 路径。
- 将 `backup.sh`、`php-fpm.conf` 放入服务器 `deploy`；安装适配后的 systemd 服务、cron 配置和 Composer PHAR。

```bash
NIUMA_SSH_HOST=deploy@memo.example.com bash deploy/baota/deploy.sh
```

脚本包含数据库备份、迁移、切换版本和健康检查失败时的代码回滚。数据库迁移不会随代码自动回滚，恢复数据需人工使用自己的备份。`deploy/deploy.sh` 是较简单的 rsync 模板，不提供相同的原子切换能力。

## 主要配置

| 配置 | 作用 |
|---|---|
| `APP_DEBUG` | 生产必须 `false` |
| `APP_URL` | 对外站点地址；用于提醒深链及转写临时链接 |
| `DB_*` | 自己的 MySQL 连接；`DB_PREFIX` 保持为空 |
| `JWT_SECRET` / `JWT_TTL` | 登录令牌密钥 / 有效期；没有可用的默认密钥 |
| `UPLOAD_STORAGE` | `local` 或 `cos` |
| `UPLOAD_MAX_MB` | 单文件上限，需与 PHP、Nginx 限制协调 |
| `UPLOAD_ACCEL` | 有 Nginx internal 附件路由时启用；开发环境关闭 |
| `COS_BUCKET` / `COS_REGION` | 自己的私有桶与区域 |
| `COS_SECRET_ID` / `COS_SECRET_KEY` | 服务端 COS 凭据 |
| `AI_BASE_URL` / `AI_API_KEY` | 兼容接口地址及服务端凭据；Key 为空时不启用 |
| `AI_MODEL` / `AI_VISION_MODEL` | 根据服务商实际可用模型填写；视觉模型可留空 |
| `VAPID_PUBLIC` / `VAPID_PRIVATE` | 运行 `php think push:vapid` 生成自己的密钥 |
| `VAPID_SUBJECT` | 填写自己的联系邮箱 URI |
| `ASR_PROVIDER` | `none`、`tencent` 或 `volcengine` |
| `TENCENT_SECRET_ID` / `TENCENT_SECRET_KEY` | 腾讯语音识别凭据 |
| `VOLC_APP_KEY` / `VOLC_ACCESS_KEY` | 火山语音识别凭据 |
| `FFMPEG_BIN` / `FFPROBE_BIN` | 留空时从 PATH 查找 |

Bark Key、企业微信 Webhook 与浏览器订阅在用户设置页配置，保存在自己的数据库，不要导出到公开仓库。修改服务配置后重启 PHP 和后台任务进程。

## Android

见 [Android 构建说明](../android-shell/README.md)。默认域名是占位地址，需要修改后自行编译、签名并做目标设备验证。无需安装 APK 时，也可直接通过支持的浏览器把 PWA 添加到主屏幕。
