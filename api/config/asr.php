<?php
// 语音转文字（ASR）配置
return [
    // tencent | volcengine | none
    'provider'  => env('ASR_PROVIDER', 'none'),
    // 超过该大小(字节)的音频不再走 base64 直传，而是给服务商一个带签名的临时下载链接
    'inline_max_bytes' => 4 * 1024 * 1024,
    // 临时下载链接有效期（秒）
    'signed_url_ttl'   => 1800,
    // 单次转写最长时长（秒），超过直接标记失败，避免账单意外
    'max_duration'     => 2 * 3600,

    'tencent' => [
        'secret_id'  => env('TENCENT_SECRET_ID', ''),
        'secret_key' => env('TENCENT_SECRET_KEY', ''),
        // 录音文件识别引擎：16k_zh 普通话 / 16k_zh_en 中英 / 16k_zh_large 大模型
        'engine'     => env('TENCENT_ASR_ENGINE', '16k_zh'),
        'endpoint'   => 'asr.tencentcloudapi.com',
    ],
    'volcengine' => [
        'app_key'     => env('VOLC_APP_KEY', ''),
        'access_key'  => env('VOLC_ACCESS_KEY', ''),
        'resource_id' => env('VOLC_RESOURCE_ID', 'volc.bigasr.auc_turbo'),
        'endpoint'    => 'https://openspeech.bytedance.com/api/v3/auc/bigmodel/recognize/flash',
    ],
];
