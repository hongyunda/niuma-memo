<?php
// 提醒推送渠道配置。用户级的 Bark Key / 企业微信 Webhook / 邮箱保存在 user.push_config
return [
    'app_url' => rtrim(env('APP_URL', 'http://localhost:5173'), '/'),
    'vapid'   => [
        'subject'    => env('VAPID_SUBJECT', 'mailto:admin@example.com'),
        'publicKey'  => env('VAPID_PUBLIC', ''),
        'privateKey' => env('VAPID_PRIVATE', ''),
    ],
    // 可用渠道及其驱动
    'channels' => [
        'webpush' => \app\service\push\WebPushChannel::class,
        'bark'    => \app\service\push\BarkChannel::class,
        'wecom'   => \app\service\push\WeComChannel::class,
    ],
];
