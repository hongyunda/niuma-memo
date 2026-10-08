# 数据库

MySQL 8.0、InnoDB、utf8mb4 / utf8mb4_unicode_ci；记录全文索引使用 MySQL `ngram`。不支持直接将本结构用于 SQLite 或不含该解析器的 MariaDB。

## 两种空库初始化方式

推荐：配置 `api/.env` 后在 `api` 目录运行 `php think migrate:run`。

也可先手动导入结构，再登记迁移状态：

```bash
mysql -u memo -p niuma_memo < api/database/schema.sql
cd api
php think migrate:run
```

`schema.sql` 只供全新空数据库使用，不是覆盖现有数据的升级脚本。没有 `INSERT`、`REPLACE` 或业务数据。迁移登记表也为空，登记状态由迁移命令生成。

## 全部表

| 表 | 用途 |
|---|---|
| `user` | 用户与个人推送配置；新建库没有用户 |
| `project` | 项目 |
| `note` | 富文本记录、搜索正文、置顶 / 归档 / 软删除、AI 标题任务状态 |
| `attachment` | 附件元信息、存储方式、转写状态 |
| `tag` | 历史标签 |
| `note_tag` | 记录和标签关联 |
| `reminder` | 提醒、重复规则、渠道和状态 |
| `reminder_log` | 提醒发送日志 |
| `push_subscription` | Web Push 订阅 |
| `customer` | 历史客户表，当前界面已移除客户管理 |
| `migrations` | ThinkPHP / Phinx 的迁移版本记录 |

旧分类字段仍保留，保证历史版本的数据库兼容；未使用的字段不代表当前界面提供对应功能。表间关联由应用维护，现有结构没有数据库外键约束。`DB_PREFIX` 应保持为空，初始化迁移使用固定表名。

## 升级与备份

升级前备份自己的数据库和附件，再执行 `php think migrate:run`。代码回滚不等于数据库回滚，不要对有数据的库重新导入此结构文件，也不要无备份执行迁移回退。

完整备份可能包含正文、密码哈希和推送凭据，只能保存在受控位置，不能提交到公开仓库。`schema.sql` 应始终从迁移生成的全新空库使用 `mysqldump --no-data` 导出，不能以删掉生产导出中的 INSERT 作为脱敏方式。
