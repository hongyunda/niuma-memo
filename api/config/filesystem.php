<?php

return [
    // 默认磁盘
    'default' => 'local',
    // 磁盘列表
    'disks'   => [
        'local'  => [
            'type' => 'local',
            'root' => app()->getRuntimePath() . 'storage',
        ],
        'public' => [
            // 磁盘类型
            'type'       => 'local',
            // 磁盘路径
            'root'       => app()->getRootPath() . 'public/storage',
            // 磁盘路径对应的外部URL路径
            'url'        => '/storage',
            // 可见性
            'visibility' => 'public',
        ],
        // 附件私有目录：不在 public 下，统一经 /api/files/{id} 鉴权后下发
        'uploads' => [
            'type'       => 'local',
            'root'       => app()->getRootPath() . 'storage/uploads',
            'visibility' => 'private',
        ],
    ],
];
