# 参与贡献

欢迎报告可复现的问题、改进文档和提交 Pull Request。请说明修改解决的问题、验证方法和涉及的数据库变更。

1. 使用自己的空数据库和本地 `.env`；不要复制生产数据用于测试。
2. 前端修改运行 `cd web && npm ci && npm run build`。
3. 后端修改执行 PHP 语法检查、`composer check-platform-reqs` 和相关接口验证。
4. 修改数据库时新增迁移，并更新由空库生成的 `api/database/schema.sql`；验证从空库迁移与导入 SQL 后运行迁移两条路径。
5. 运行 `python3 scripts/check-public-tree.py`，检查最终提交内容。

截图只使用虚构内容。不要在 Issue、PR、日志或附件中放真实账号、密码、令牌、Webhook、SQL 备份、录音或用户文件。依赖安装目录、构建产物和 Android 签名不随源码提交。

贡献按项目 Apache-2.0 许可证提供，第三方代码须保留原始声明。
