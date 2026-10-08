<?php

use think\migration\Migrator;

/**
 * 初始化全部业务表（原生 SQL，便于精确控制 FULLTEXT ngram 等 MySQL 特性）
 */
class InitSchema extends Migrator
{
    public function up()
    {
        $sqls = [
            // 用户
            "CREATE TABLE IF NOT EXISTS `user` (
              `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `username`      VARCHAR(50)  NOT NULL,
              `password`      VARCHAR(255) NOT NULL COMMENT 'password_hash()',
              `nickname`      VARCHAR(50)  NOT NULL DEFAULT '',
              `avatar`        VARCHAR(255) NOT NULL DEFAULT '',
              `push_config`   JSON NULL COMMENT '推送渠道配置：bark_key / wecom_webhook / email',
              `last_login_at` DATETIME NULL,
              `created_at`    DATETIME NULL,
              `updated_at`    DATETIME NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户'",

            // 客户
            "CREATE TABLE IF NOT EXISTS `customer` (
              `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id`    INT UNSIGNED NOT NULL,
              `name`       VARCHAR(100) NOT NULL COMMENT '客户 / 公司名',
              `contact`    VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '联系人',
              `phone`      VARCHAR(30)  NOT NULL DEFAULT '',
              `wechat`     VARCHAR(50)  NOT NULL DEFAULT '',
              `remark`     TEXT NULL,
              `created_at` DATETIME NULL,
              `updated_at` DATETIME NULL,
              PRIMARY KEY (`id`),
              KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户'",

            // 项目
            "CREATE TABLE IF NOT EXISTS `project` (
              `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id`     INT UNSIGNED NOT NULL,
              `customer_id` INT UNSIGNED NULL COMMENT '客户项目关联客户',
              `name`        VARCHAR(100) NOT NULL,
              `type`        TINYINT NOT NULL DEFAULT 1 COMMENT '1 软件项目 2 客户项目 3 个人/其他',
              `color`       VARCHAR(10)  NOT NULL DEFAULT '#1989fa',
              `icon`        VARCHAR(50)  NOT NULL DEFAULT '',
              `description` TEXT NULL,
              `status`      TINYINT NOT NULL DEFAULT 1 COMMENT '1 进行中 2 已归档',
              `sort`        INT NOT NULL DEFAULT 0,
              `created_at`  DATETIME NULL,
              `updated_at`  DATETIME NULL,
              PRIMARY KEY (`id`),
              KEY `idx_user_status` (`user_id`, `status`),
              KEY `idx_customer` (`customer_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目'",

            // 记录（核心表）
            "CREATE TABLE IF NOT EXISTS `note` (
              `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id`      INT UNSIGNED NOT NULL,
              `project_id`   INT UNSIGNED NULL COMMENT 'NULL = 收集箱',
              `type`         TINYINT NOT NULL DEFAULT 1 COMMENT '1 笔记 2 需求 3 想法 4 沟通记录 5 待办',
              `title`        VARCHAR(200) NOT NULL DEFAULT '',
              `content`      LONGTEXT NULL COMMENT '编辑器 HTML',
              `content_text` LONGTEXT NULL COMMENT '纯文本，供全文检索与摘要',
              `status`       TINYINT NOT NULL DEFAULT 0 COMMENT '0 无 1 待确认 2 已确认 3 进行中 4 已完成 5 已取消',
              `priority`     TINYINT NOT NULL DEFAULT 0 COMMENT '0 普通 1 重要 2 紧急',
              `source`       VARCHAR(30) NOT NULL DEFAULT '' COMMENT '沟通来源',
              `is_pinned`    TINYINT(1) NOT NULL DEFAULT 0,
              `is_archived`  TINYINT(1) NOT NULL DEFAULT 0,
              `done_at`      DATETIME NULL,
              `created_at`   DATETIME NULL,
              `updated_at`   DATETIME NULL,
              `deleted_at`   DATETIME NULL COMMENT '软删除 = 回收站',
              PRIMARY KEY (`id`),
              KEY `idx_user_project_type` (`user_id`, `project_id`, `type`),
              KEY `idx_user_updated` (`user_id`, `updated_at`),
              FULLTEXT KEY `ft_note` (`title`, `content_text`) WITH PARSER ngram
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='记录'",

            // 附件
            "CREATE TABLE IF NOT EXISTS `attachment` (
              `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id`       INT UNSIGNED NOT NULL,
              `note_id`       BIGINT UNSIGNED NULL,
              `project_id`    INT UNSIGNED NULL,
              `file_type`     VARCHAR(10)  NOT NULL COMMENT 'image/audio/video/pdf/word/excel/ppt/other',
              `original_name` VARCHAR(255) NOT NULL,
              `path`          VARCHAR(255) NOT NULL COMMENT '存储相对路径',
              `thumb_path`    VARCHAR(255) NOT NULL DEFAULT '',
              `mime`          VARCHAR(100) NOT NULL,
              `size`          BIGINT UNSIGNED NOT NULL,
              `hash`          CHAR(32) NOT NULL DEFAULT '',
              `duration`      INT NOT NULL DEFAULT 0 COMMENT '音视频时长（秒）',
              `width`         INT NOT NULL DEFAULT 0,
              `height`        INT NOT NULL DEFAULT 0,
              `storage`       VARCHAR(10)  NOT NULL DEFAULT 'local',
              `transcript`    MEDIUMTEXT NULL COMMENT '语音转文字结果',
              `asr_status`    TINYINT NOT NULL DEFAULT 0 COMMENT '0 未转写 1 排队 2 转写中 3 完成 4 失败',
              `asr_task_id`   VARCHAR(100) NOT NULL DEFAULT '',
              `asr_error`     VARCHAR(500) NOT NULL DEFAULT '',
              `created_at`    DATETIME NULL,
              `updated_at`    DATETIME NULL,
              PRIMARY KEY (`id`),
              KEY `idx_note` (`note_id`),
              KEY `idx_user_project_type` (`user_id`, `project_id`, `file_type`),
              KEY `idx_hash` (`hash`),
              KEY `idx_asr` (`asr_status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='附件'",

            // 标签
            "CREATE TABLE IF NOT EXISTS `tag` (
              `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id`    INT UNSIGNED NOT NULL,
              `name`       VARCHAR(30) NOT NULL,
              `color`      VARCHAR(10) NOT NULL DEFAULT '',
              `created_at` DATETIME NULL,
              `updated_at` DATETIME NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_user_name` (`user_id`, `name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='标签'",

            "CREATE TABLE IF NOT EXISTS `note_tag` (
              `note_id` BIGINT UNSIGNED NOT NULL,
              `tag_id`  INT UNSIGNED NOT NULL,
              PRIMARY KEY (`note_id`, `tag_id`),
              KEY `idx_tag` (`tag_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='记录-标签'",

            // 提醒
            "CREATE TABLE IF NOT EXISTS `reminder` (
              `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id`         INT UNSIGNED NOT NULL,
              `note_id`         BIGINT UNSIGNED NULL,
              `title`           VARCHAR(200) NOT NULL,
              `remind_at`       DATETIME NOT NULL COMMENT '下一次提醒时间',
              `advance_minutes` INT NOT NULL DEFAULT 0,
              `repeat_type`     TINYINT NOT NULL DEFAULT 0 COMMENT '0 不重复 1 每天 2 每周 3 每月 4 每年 5 工作日',
              `repeat_until`    DATETIME NULL,
              `channels`        VARCHAR(100) NOT NULL DEFAULT 'webpush',
              `status`          TINYINT NOT NULL DEFAULT 1 COMMENT '1 待提醒 2 已提醒 3 已完成 4 已取消',
              `snooze_until`    DATETIME NULL,
              `last_sent_at`    DATETIME NULL,
              `created_at`      DATETIME NULL,
              `updated_at`      DATETIME NULL,
              PRIMARY KEY (`id`),
              KEY `idx_scan` (`status`, `remind_at`),
              KEY `idx_user_status` (`user_id`, `status`, `remind_at`),
              KEY `idx_note` (`note_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='提醒'",

            "CREATE TABLE IF NOT EXISTS `reminder_log` (
              `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `reminder_id` BIGINT UNSIGNED NOT NULL,
              `channel`     VARCHAR(20) NOT NULL,
              `success`     TINYINT(1) NOT NULL,
              `error`       VARCHAR(500) NOT NULL DEFAULT '',
              `sent_at`     DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_reminder` (`reminder_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='推送日志'",

            // Web Push 订阅
            "CREATE TABLE IF NOT EXISTS `push_subscription` (
              `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id`       INT UNSIGNED NOT NULL,
              `endpoint`      VARCHAR(600) NOT NULL,
              `endpoint_hash` CHAR(32) NOT NULL,
              `p256dh`        VARCHAR(255) NOT NULL,
              `auth`          VARCHAR(255) NOT NULL,
              `user_agent`    VARCHAR(255) NOT NULL DEFAULT '',
              `created_at`    DATETIME NULL,
              `updated_at`    DATETIME NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_endpoint` (`endpoint_hash`),
              KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Web Push 订阅'",
        ];

        foreach ($sqls as $sql) {
            $this->execute($sql);
        }
    }

    public function down()
    {
        foreach (['push_subscription', 'reminder_log', 'reminder', 'note_tag', 'tag', 'attachment', 'note', 'project', 'customer', 'user'] as $table) {
            $this->execute("DROP TABLE IF EXISTS `{$table}`");
        }
    }
}
