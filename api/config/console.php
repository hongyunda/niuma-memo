<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'reminder:scan' => \app\command\ReminderScan::class,
        'asr:run'       => \app\command\AsrRun::class,
        'user:create'   => \app\command\UserCreate::class,
        'push:vapid'    => \app\command\PushVapid::class,
        'attachments:to-cos' => \app\command\AttachmentsToCos::class,
        'notes:titles'  => \app\command\NoteTitlesRun::class,
    ],
];
